<?php
/**
 * Handover Endpoint
 *
 * Orchestrates the three handover paths after buyer accepts seller response:
 *   diary_link      – seller shares a booking link
 *   email_brief     – platform sends a structured brief email to seller
 *   external_portal – seller provides their own intake portal URL
 *
 * On handover:
 *   1. Generates a unique buyer discount code (12 chars, stored in connect_discount_codes)
 *   2. Records handover preference on the thread
 *   3. Fires khm_handover_completed action (listener sends 4 confirmation emails)
 *
 * Routes:
 *   POST /khm/v1/connect/intro-threads/(?P<id>\d+)/handover/initiate
 *   GET  /khm/v1/connect/intro-threads/(?P<id>\d+)/handover/status
 */

namespace KHM\Connect;

use KHM\Migrations\ConnectWorkflowMigration;
use KHM\Migrations\CreateRFPSupportTables;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class ConnectHandoverEndpoint {

	private ConnectSellerPaymentRepository $payment_repo;

	public function __construct( ?ConnectSellerPaymentRepository $payment_repo = null ) {
		$this->payment_repo = $payment_repo ?? new ConnectSellerPaymentRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( 'khm/v1', '/connect/intro-threads/(?P<id>\d+)/handover/initiate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'initiate_handover' ],
			'permission_callback' => [ $this, 'require_seller_for_thread' ],
			'args'                => [
				'handover_type' => [
					'required'          => true,
					'type'              => 'string',
					'enum'              => [ 'diary_link', 'email_brief', 'external_portal' ],
				],
				'handover_detail' => [
					'required' => false,
					'type'     => 'string',
					'description' => 'URL for diary_link/external_portal; empty for email_brief.',
				],
			],
		] );

		register_rest_route( 'khm/v1', '/connect/intro-threads/(?P<id>\d+)/handover/status', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_status' ],
			'permission_callback' => [ $this, 'require_thread_party' ],
		] );
	}

	// ─── Handlers ──────────────────────────────────────────────────────────────

	/**
	 * POST …/handover/initiate
	 *
	 * Called by the seller after the buyer has accepted their response.
	 * Records handover preference, generates discount code, fires emails.
	 */
	public function initiate_handover( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$thread_id = (int) $request->get_param( 'id' );
		$thread    = $this->get_thread_or_error( $thread_id );

		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		// Guard: thread must be in accepted state
		if ( 'accepted' !== $thread->seller_response_status ) {
			return new WP_Error( 'not_accepted', 'Buyer must accept the seller response before handover.', [ 'status' => 409 ] );
		}

		// Guard: do not double-initiate
		if ( ! empty( $thread->buyer_discount_code ) ) {
			return new WP_Error( 'already_initiated', 'Handover already initiated for this thread.', [ 'status' => 409 ] );
		}

		$handover_type   = $request->get_param( 'handover_type' );
		$handover_detail = sanitize_text_field( (string) $request->get_param( 'handover_detail' ) );

		// Validate URL for link-based handover types
		if ( in_array( $handover_type, [ 'diary_link', 'external_portal' ], true ) ) {
			if ( ! empty( $handover_detail ) && ! filter_var( $handover_detail, FILTER_VALIDATE_URL ) ) {
				return new WP_Error( 'invalid_url', 'handover_detail must be a valid URL for this handover type.', [ 'status' => 422 ] );
			}
		}

		// Check that the seller has a valid payment method (or card fallback is enabled)
		$seller_profile = $this->payment_repo->get_by_seller_id( (int) $thread->provider_id );
		$has_payment    = $seller_profile && ! empty( $seller_profile->stripe_customer_id );
		$fallback_ok    = $seller_profile ? (bool) $seller_profile->card_enabled_fallback : true;

		if ( ! $has_payment && ! $fallback_ok ) {
			return new WP_Error(
				'payment_required',
				'Seller must register a payment method before completing handover.',
				[ 'status' => 402 ]
			);
		}

		// Generate unique discount code
		$discount_code = $this->generate_unique_code();

		if ( ! $discount_code ) {
			return new WP_Error( 'code_generation_failed', 'Failed to generate a unique discount code.', [ 'status' => 500 ] );
		}

		$threads_table = ConnectWorkflowMigration::threads_table_name();
		$codes_table   = CreateRFPSupportTables::discount_codes_table_name();

		// Save code to connect_discount_codes
		$wpdb->insert(
			$codes_table,
			[
				'code'       => $discount_code,
				'thread_id'  => $thread_id,
				'created_at' => current_time( 'mysql' ),
			],
			[ '%s', '%d', '%s' ]
		);

		// Update thread
		$wpdb->update(
			$threads_table,
			[
				'handover_preference'  => $handover_type,
				'buyer_discount_code'  => $discount_code,
				'handover_status'      => 'completed',
			],
			[ 'id' => $thread_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		/**
		 * Fires when a handover is completed.
		 * Listeners are responsible for sending the 4 confirmation emails:
		 *   1. Buyer confirmation (includes discount code + claim instructions)
		 *   2. Seller confirmation (includes commission rate reminder)
		 *   3. Platform admin notification
		 *   4. Buyer: "How was your experience?" (30-day deferred via cron)
		 *
		 * @param int    $thread_id
		 * @param string $handover_type    diary_link|email_brief|external_portal
		 * @param string $handover_detail  URL or empty string
		 * @param string $discount_code
		 */
		do_action( 'khm_handover_completed', $thread_id, $handover_type, $handover_detail, $discount_code );

		return new WP_REST_Response( [
			'success'       => true,
			'handover_type' => $handover_type,
			'discount_code' => $discount_code,
		], 200 );
	}

	/**
	 * GET …/handover/status
	 */
	public function get_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$thread_id = (int) $request->get_param( 'id' );
		$thread    = $this->get_thread_or_error( $thread_id );

		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		return new WP_REST_Response( [
			'handover_status'      => $thread->handover_status,
			'handover_preference'  => $thread->handover_preference,
			'discount_code_issued' => ! empty( $thread->buyer_discount_code ),
			'commission_rate'      => (int) $thread->seller_commission_rate,
		], 200 );
	}

	// ─── Discount code generator ───────────────────────────────────────────────

	/**
	 * Generate a unique 12-character alphanumeric discount code.
	 * Retries up to 10 times to avoid collisions (astronomically unlikely).
	 *
	 * @return string|null
	 */
	private function generate_unique_code(): ?string {
		global $wpdb;

		$table = CreateRFPSupportTables::discount_codes_table_name();

		for ( $i = 0; $i < 10; $i++ ) {
			$code = strtoupper( wp_generate_password( 12, false, false ) );

			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE code = %s LIMIT 1", $code ) );

			if ( ! $exists ) {
				return $code;
			}
		}

		return null;
	}

	// ─── Permissions ───────────────────────────────────────────────────────────

	public function require_seller_for_thread( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'not_logged_in', 'Authentication required.', [ 'status' => 401 ] );
		}

		$thread_id = (int) $request->get_param( 'id' );
		$thread    = $this->get_thread_or_error( $thread_id );

		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		if ( (int) $thread->provider_id !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', 'Only the assigned seller can initiate handover.', [ 'status' => 403 ] );
		}

		return true;
	}

	public function require_thread_party( WP_REST_Request $request ): bool|WP_Error {
		$thread_id = (int) $request->get_param( 'id' );
		$thread    = $this->get_thread_or_error( $thread_id );

		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		if ( is_user_logged_in() && (int) $thread->provider_id === get_current_user_id() ) {
			return true;
		}

		$token = $request->get_param( 'buyer_token' );
		if ( $token && hash_equals( (string) $thread->buyer_token, (string) $token ) ) {
			return true;
		}

		return new WP_Error( 'forbidden', 'Access denied.', [ 'status' => 403 ] );
	}

	// ─── Helpers ───────────────────────────────────────────────────────────────

	private function get_thread_or_error( int $thread_id ): object {
		global $wpdb;

		$table  = ConnectWorkflowMigration::threads_table_name();
		$thread = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $thread_id ) );

		if ( ! $thread ) {
			return new WP_Error( 'thread_not_found', 'Thread not found.', [ 'status' => 404 ] );
		}

		return $thread;
	}
}
