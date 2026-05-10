<?php
/**
 * Seller Response Endpoint
 *
 * Handles the seller's structured initial response to an RFQ intro thread:
 *   POST /khm/v1/connect/intro-threads/(?P<id>\d+)/seller-response   – submit response
 *   GET  /khm/v1/connect/intro-threads/(?P<id>\d+)/seller-response   – get response (seller + buyer)
 *   POST /khm/v1/connect/intro-threads/(?P<id>\d+)/seller-response/accept – buyer accepts
 *   POST /khm/v1/connect/intro-threads/(?P<id>\d+)/seller-response/reject – buyer rejects (starts 90-day cooldown)
 *
 * Commission rate is set by the seller (5–25%) on submission.
 * Status machine: not_requested → awaiting_response → submitted → accepted | rejected
 */

namespace KHM\Connect;

use KHM\Migrations\ConnectWorkflowMigration;
use KHM\Migrations\CreateRFPSupportTables;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class ConnectSellerResponseEndpoint {

	private ConnectIntroThreadRepository $threads;

	public function __construct( ?ConnectIntroThreadRepository $threads = null ) {
		$this->threads = $threads ?? new ConnectIntroThreadRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		// Submit seller response (direct numeric route)
		register_rest_route( 'khm/v1', '/connect/intro-threads/(?P<id>\d+)/seller-response', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'submit_response' ],
			'permission_callback' => [ $this, 'require_seller_for_thread' ],
			'args'                => $this->response_schema_args(),
		] );

		// Submit seller response via the seller-portal "mine/" prefix (used by quote-club-connect.js)
		register_rest_route( 'khm/v1', '/connect/intro-threads/mine/(?P<id>\d+)/seller-response', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'submit_response' ],
			'permission_callback' => [ $this, 'require_seller_for_thread' ],
			'args'                => $this->response_schema_args(),
		] );

		// Get seller response (buyer + seller can both read)
		register_rest_route( 'khm/v1', '/connect/intro-threads/(?P<id>\d+)/seller-response', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_response' ],
			'permission_callback' => [ $this, 'require_thread_party' ],
		] );

		// Buyer accepts response (proceeds to handover flow)
		register_rest_route( 'khm/v1', '/connect/intro-threads/(?P<id>\d+)/seller-response/accept', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'buyer_accept' ],
			'permission_callback' => [ $this, 'require_buyer_token_or_login' ],
		] );

		// Buyer rejects response (starts 90-day seller cooldown)
		register_rest_route( 'khm/v1', '/connect/intro-threads/(?P<id>\d+)/seller-response/reject', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'buyer_reject' ],
			'permission_callback' => [ $this, 'require_buyer_token_or_login' ],
		] );
	}

	// ─── Handlers ──────────────────────────────────────────────────────────────

	/**
	 * POST …/seller-response
	 * Seller submits structured RFQ response + sets commission rate.
	 */
	public function submit_response( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$thread_id = (int) $request->get_param( 'id' );
		$thread    = $this->get_thread_or_error( $thread_id );

		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		if ( ! in_array( $thread->seller_response_status, [ 'not_requested', 'awaiting_response' ], true ) ) {
			return new WP_Error( 'already_submitted', 'A response has already been submitted for this thread.', [ 'status' => 409 ] );
		}

		$commission_rate = (int) $request->get_param( 'commission_rate' );

		if ( $commission_rate < 5 || $commission_rate > 25 ) {
			return new WP_Error( 'invalid_commission', 'Commission rate must be between 5 and 25.', [ 'status' => 422 ] );
		}

		$response_payload = [
			'capability'   => sanitize_textarea_field( (string) $request->get_param( 'capability' ) ),
			'cost_range'   => sanitize_text_field( (string) $request->get_param( 'cost_range' ) ),
			'approach'     => sanitize_textarea_field( (string) $request->get_param( 'approach' ) ),
			'timeline'     => sanitize_text_field( (string) $request->get_param( 'timeline' ) ),
			'lead_contact' => [
				'name'  => sanitize_text_field( (string) $request->get_param( 'lead_name' ) ),
				'email' => sanitize_email( (string) $request->get_param( 'lead_email' ) ),
				'title' => sanitize_text_field( (string) $request->get_param( 'lead_title' ) ),
			],
		];

		$table = ConnectWorkflowMigration::threads_table_name();

		$wpdb->update(
			$table,
			[
				'seller_initial_response'      => wp_json_encode( $response_payload ),
				'seller_response_status'       => 'submitted',
				'seller_response_submitted_at' => current_time( 'mysql' ),
				'seller_commission_rate'       => $commission_rate,
			],
			[ 'id' => $thread_id ],
			[ '%s', '%s', '%s', '%d' ],
			[ '%d' ]
		);

		do_action( 'khm_seller_response_submitted', $thread_id, $commission_rate );

		return new WP_REST_Response( [ 'success' => true, 'status' => 'submitted' ], 200 );
	}

	/**
	 * GET …/seller-response
	 * Returns the seller's structured response for buyer review.
	 */
	public function get_response( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$thread_id = (int) $request->get_param( 'id' );
		$thread    = $this->get_thread_or_error( $thread_id );

		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		$response_data = null;
		if ( ! empty( $thread->seller_initial_response ) ) {
			$response_data = json_decode( $thread->seller_initial_response, true );
		}

		return new WP_REST_Response( [
			'status'            => $thread->seller_response_status,
			'submitted_at'      => $thread->seller_response_submitted_at,
			'commission_rate'   => (int) $thread->seller_commission_rate,
			'response'          => $response_data,
		], 200 );
	}

	/**
	 * POST …/seller-response/accept
	 * Buyer accepts the seller's response — moves to handover.
	 */
	public function buyer_accept( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$thread_id = (int) $request->get_param( 'id' );
		$thread    = $this->get_thread_or_error( $thread_id );

		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		if ( 'submitted' !== $thread->seller_response_status ) {
			return new WP_Error( 'not_ready', 'No submitted response to accept.', [ 'status' => 409 ] );
		}

		$table = ConnectWorkflowMigration::threads_table_name();

		$wpdb->update(
			$table,
			[ 'seller_response_status' => 'accepted' ],
			[ 'id' => $thread_id ],
			[ '%s' ],
			[ '%d' ]
		);

		do_action( 'khm_seller_response_accepted', $thread_id );

		return new WP_REST_Response( [ 'success' => true, 'status' => 'accepted' ], 200 );
	}

	/**
	 * POST …/seller-response/reject
	 * Buyer rejects the seller's response.
	 * Records a 90-day rejection cooldown so the same seller doesn't appear again.
	 */
	public function buyer_reject( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$thread_id = (int) $request->get_param( 'id' );
		$thread    = $this->get_thread_or_error( $thread_id );

		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		if ( 'submitted' !== $thread->seller_response_status ) {
			return new WP_Error( 'not_ready', 'No submitted response to reject.', [ 'status' => 409 ] );
		}

		$threads_table  = ConnectWorkflowMigration::threads_table_name();
		$cooldown_table = CreateRFPSupportTables::cooldown_table_name();

		// Update thread status
		$wpdb->update(
			$threads_table,
			[ 'seller_response_status' => 'rejected' ],
			[ 'id' => $thread_id ],
			[ '%s' ],
			[ '%d' ]
		);

		// Record rejection cooldown (90 days) if we have buyer account ID
		$buyer_id  = $this->resolve_buyer_id( $request, $thread );
		$seller_id = (int) $thread->provider_id;

		if ( $buyer_id && $seller_id ) {
			$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+90 days' ) );

			$wpdb->query( $wpdb->prepare(
				"INSERT INTO `{$cooldown_table}` (seller_id, buyer_id, rejected_at, expires_at)
				 VALUES (%d, %d, %s, %s)
				 ON DUPLICATE KEY UPDATE rejected_at = VALUES(rejected_at), expires_at = VALUES(expires_at)",
				$seller_id,
				$buyer_id,
				current_time( 'mysql' ),
				$expires_at
			) );
		}

		do_action( 'khm_seller_response_rejected', $thread_id, $seller_id, $buyer_id );

		return new WP_REST_Response( [ 'success' => true, 'status' => 'rejected' ], 200 );
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
			return new WP_Error( 'forbidden', 'Only the assigned seller can respond.', [ 'status' => 403 ] );
		}

		return true;
	}

	public function require_thread_party( WP_REST_Request $request ): bool|WP_Error {
		$thread_id = (int) $request->get_param( 'id' );
		$thread    = $this->get_thread_or_error( $thread_id );

		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		// Seller is logged in
		if ( is_user_logged_in() && (int) $thread->provider_id === get_current_user_id() ) {
			return true;
		}

		// Buyer identified by token param
		$token = $request->get_param( 'buyer_token' );
		if ( $token && hash_equals( (string) $thread->buyer_token, (string) $token ) ) {
			return true;
		}

		return new WP_Error( 'forbidden', 'Access denied.', [ 'status' => 403 ] );
	}

	public function require_buyer_token_or_login( WP_REST_Request $request ): bool|WP_Error {
		$thread_id = (int) $request->get_param( 'id' );
		$thread    = $this->get_thread_or_error( $thread_id );

		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		$token = $request->get_param( 'buyer_token' );
		if ( $token && hash_equals( (string) $thread->buyer_token, (string) $token ) ) {
			return true;
		}

		if ( is_user_logged_in() ) {
			return true;
		}

		return new WP_Error( 'forbidden', 'Buyer token or login required.', [ 'status' => 403 ] );
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

	private function resolve_buyer_id( WP_REST_Request $request, object $thread ): int {
		if ( is_user_logged_in() ) {
			return get_current_user_id();
		}

		// Try to look up buyer by sponsor_id on the thread
		return (int) ( $thread->sponsor_id ?? 0 );
	}

	private function response_schema_args(): array {
		return [
			'capability'      => [ 'required' => true,  'type' => 'string', 'minLength' => 10 ],
			'cost_range'      => [ 'required' => true,  'type' => 'string', 'minLength' => 2 ],
			'approach'        => [ 'required' => true,  'type' => 'string', 'minLength' => 10 ],
			'timeline'        => [ 'required' => true,  'type' => 'string', 'minLength' => 2 ],
			'lead_name'       => [ 'required' => true,  'type' => 'string', 'minLength' => 2 ],
			'lead_email'      => [ 'required' => true,  'type' => 'string', 'format' => 'email' ],
			'lead_title'      => [ 'required' => false, 'type' => 'string' ],
			'commission_rate' => [
				'required' => true,
				'type'     => 'integer',
				'minimum'  => 5,
				'maximum'  => 25,
			],
			'buyer_token'     => [ 'required' => false, 'type' => 'string' ],
		];
	}
}
