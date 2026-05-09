<?php
/**
 * Buyer Discount Code Claim Endpoint
 *
 * Handles the one-time buyer discount code claim flow:
 *   GET  /khm/v1/connect/discount-codes/(?P<code>[A-Z0-9]{12})/status  – check claim state
 *   POST /khm/v1/connect/discount-codes/(?P<code>[A-Z0-9]{12})/claim   – submit claim (one-time gate)
 *   POST /khm/v1/connect/discount-codes/(?P<code>[A-Z0-9]{12})/dispute – raise dispute within 30-day window
 *
 * Claim flow:
 *   1. Buyer submits: signed contract ref, deal value (GBP), contract upload URL
 *   2. Commission amount = deal_value × (commission_rate / 100)
 *   3. Invoice row created in connect_pending_commission_invoices with auto_debit_date = now + 15 days
 *   4. Thread buyer_discount_claimed_at stamped
 *   5. Fires khm_discount_code_claimed (listener sends 4 emails)
 *
 * Dispute flow:
 *   - Buyer can raise dispute within 30 days of claim
 *   - Invoice status → 'disputed'; admin reviews before charging
 */

namespace KHM\Connect;

use KHM\Migrations\ConnectWorkflowMigration;
use KHM\Migrations\CreateRFPSupportTables;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class ConnectDiscountCodeClaimEndpoint {

	private ConnectSellerPaymentRepository $payment_repo;

	public function __construct( ?ConnectSellerPaymentRepository $payment_repo = null ) {
		$this->payment_repo = $payment_repo ?? new ConnectSellerPaymentRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		$code_regex = '(?P<code>[A-Z0-9]{12})';

		// Check whether a code has been claimed yet
		register_rest_route( 'khm/v1', "/connect/discount-codes/{$code_regex}/status", [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_status' ],
			'permission_callback' => [ $this, 'require_buyer_for_code' ],
		] );

		// Submit the one-time claim
		register_rest_route( 'khm/v1', "/connect/discount-codes/{$code_regex}/claim", [
			'methods'             => 'POST',
			'callback'            => [ $this, 'submit_claim' ],
			'permission_callback' => [ $this, 'require_buyer_for_code' ],
			'args'                => [
				'contract_ref'     => [ 'required' => true,  'type' => 'string', 'minLength' => 2 ],
				'deal_value_gbp'   => [ 'required' => true,  'type' => 'number', 'minimum' => 0.01 ],
				'contract_url'     => [ 'required' => false, 'type' => 'string' ],
				'buyer_token'      => [ 'required' => false, 'type' => 'string' ],
			],
		] );

		// Raise a dispute within the 30-day window
		register_rest_route( 'khm/v1', "/connect/discount-codes/{$code_regex}/dispute", [
			'methods'             => 'POST',
			'callback'            => [ $this, 'raise_dispute' ],
			'permission_callback' => [ $this, 'require_buyer_for_code' ],
			'args'                => [
				'reason'      => [ 'required' => true, 'type' => 'string', 'minLength' => 10 ],
				'buyer_token' => [ 'required' => false, 'type' => 'string' ],
			],
		] );
	}

	// ─── Handlers ──────────────────────────────────────────────────────────────

	/**
	 * GET …/status
	 */
	public function get_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$code_row = $this->get_code_row( $request->get_param( 'code' ) );

		if ( is_wp_error( $code_row ) ) {
			return $code_row;
		}

		$invoice = $this->get_invoice_for_thread( (int) $code_row->thread_id );

		return new WP_REST_Response( [
			'code'            => $code_row->code,
			'claimed'         => ! empty( $code_row->claimed_by_buyer_at ),
			'claimed_at'      => $code_row->claimed_by_buyer_at,
			'invoice_status'  => $invoice ? $invoice->status : null,
			'auto_debit_date' => $invoice ? $invoice->auto_debit_date : null,
		], 200 );
	}

	/**
	 * POST …/claim
	 * One-time gate: once submitted, cannot be re-submitted.
	 */
	public function submit_claim( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$code     = strtoupper( $request->get_param( 'code' ) );
		$code_row = $this->get_code_row( $code );

		if ( is_wp_error( $code_row ) ) {
			return $code_row;
		}

		// One-time gate: reject if already claimed
		if ( ! empty( $code_row->claimed_by_buyer_at ) ) {
			return new WP_Error( 'already_claimed', 'This discount code has already been claimed.', [ 'status' => 409 ] );
		}

		$thread_id = (int) $code_row->thread_id;
		$thread    = $this->get_thread( $thread_id );

		if ( ! $thread ) {
			return new WP_Error( 'thread_not_found', 'Associated thread not found.', [ 'status' => 404 ] );
		}

		$commission_rate = (int) $thread->seller_commission_rate;
		if ( $commission_rate < 5 || $commission_rate > 25 ) {
			return new WP_Error( 'invalid_commission', 'Commission rate on thread is invalid.', [ 'status' => 500 ] );
		}

		$deal_value_gbp   = (float) $request->get_param( 'deal_value_gbp' );
		$commission_amount = round( $deal_value_gbp * ( $commission_rate / 100 ), 2 );
		$contract_ref      = sanitize_text_field( (string) $request->get_param( 'contract_ref' ) );
		$contract_url      = esc_url_raw( (string) $request->get_param( 'contract_url' ) );
		$now               = current_time( 'mysql' );
		$auto_debit_date   = gmdate( 'Y-m-d H:i:s', strtotime( '+15 days' ) );

		// Stamp the claim timestamp on the discount code row
		$codes_table = CreateRFPSupportTables::discount_codes_table_name();
		$wpdb->update(
			$codes_table,
			[ 'claimed_by_buyer_at' => $now ],
			[ 'code' => $code ],
			[ '%s' ],
			[ '%s' ]
		);

		// Stamp claim timestamp on the thread
		$threads_table = ConnectWorkflowMigration::threads_table_name();
		$wpdb->update(
			$threads_table,
			[
				'buyer_discount_claimed_at' => $now,
				'auto_debit_date'           => $auto_debit_date,
				'seller_payment_status'     => 'debit_scheduled',
			],
			[ 'id' => $thread_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		// Create the commission invoice
		$invoices_table = CreateRFPSupportTables::invoices_table_name();
		$wpdb->insert(
			$invoices_table,
			[
				'thread_id'       => $thread_id,
				'contract_ref'    => $contract_ref,
				'commission_rate' => $commission_rate,
				'commission_amount' => $commission_amount,
				'auto_debit_date' => $auto_debit_date,
				'status'          => 'pending',
				'claimed_at'      => $now,
			],
			[ '%d', '%s', '%d', '%f', '%s', '%s', '%s' ]
		);

		$invoice_id = (int) $wpdb->insert_id;

		// Reserve the amount against the seller's monthly spend limit
		$seller_id = (int) $thread->provider_id;
		$within_limit = $this->payment_repo->reserve_spend( $seller_id, $commission_amount );

		/**
		 * Fires when a buyer successfully claims their discount code.
		 *
		 * @param int    $thread_id
		 * @param int    $invoice_id
		 * @param string $code
		 * @param float  $commission_amount  GBP
		 * @param string $auto_debit_date    ISO datetime
		 * @param bool   $within_limit       false if seller is over cap (admin must be notified)
		 */
		do_action( 'khm_discount_code_claimed', $thread_id, $invoice_id, $code, $commission_amount, $auto_debit_date, $within_limit );

		return new WP_REST_Response( [
			'success'          => true,
			'commission_amount' => $commission_amount,
			'commission_rate'  => $commission_rate,
			'auto_debit_date'  => $auto_debit_date,
			'invoice_id'       => $invoice_id,
		], 200 );
	}

	/**
	 * POST …/dispute
	 * Buyer can raise a dispute within 30 days of the claim date.
	 */
	public function raise_dispute( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$code     = strtoupper( $request->get_param( 'code' ) );
		$code_row = $this->get_code_row( $code );

		if ( is_wp_error( $code_row ) ) {
			return $code_row;
		}

		if ( empty( $code_row->claimed_by_buyer_at ) ) {
			return new WP_Error( 'not_claimed', 'This code has not been claimed — nothing to dispute.', [ 'status' => 409 ] );
		}

		$thread_id = (int) $code_row->thread_id;
		$invoice   = $this->get_invoice_for_thread( $thread_id );

		if ( ! $invoice ) {
			return new WP_Error( 'invoice_not_found', 'Commission invoice not found.', [ 'status' => 404 ] );
		}

		if ( in_array( $invoice->status, [ 'charged', 'cancelled', 'disputed' ], true ) ) {
			return new WP_Error( 'dispute_not_allowed', 'Invoice is not in a disputable state.', [ 'status' => 409 ] );
		}

		// Enforce 30-day dispute window from claimed_at
		$claimed_at = strtotime( $invoice->claimed_at );
		$dispute_window_close = $claimed_at + ( 30 * DAY_IN_SECONDS );

		if ( time() > $dispute_window_close ) {
			return new WP_Error( 'dispute_window_closed', 'The 30-day dispute window has passed.', [ 'status' => 409 ] );
		}

		$reason         = sanitize_textarea_field( (string) $request->get_param( 'reason' ) );
		$invoices_table = CreateRFPSupportTables::invoices_table_name();

		$wpdb->update(
			$invoices_table,
			[
				'status'         => 'disputed',
				'dispute_reason' => $reason,
			],
			[ 'id' => (int) $invoice->id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		do_action( 'khm_commission_disputed', $thread_id, (int) $invoice->id, $reason );

		return new WP_REST_Response( [
			'success' => true,
			'status'  => 'disputed',
		], 200 );
	}

	// ─── Permissions ───────────────────────────────────────────────────────────

	/**
	 * Verify the request is from the buyer who owns the code.
	 * Accepts: buyer_token param OR logged-in session matching the thread's sponsor_id.
	 */
	public function require_buyer_for_code( WP_REST_Request $request ): bool|WP_Error {
		$code     = strtoupper( (string) $request->get_param( 'code' ) );
		$code_row = $this->get_code_row( $code );

		if ( is_wp_error( $code_row ) ) {
			return $code_row;
		}

		$thread = $this->get_thread( (int) $code_row->thread_id );

		if ( ! $thread ) {
			return new WP_Error( 'thread_not_found', 'Thread not found.', [ 'status' => 404 ] );
		}

		// Buyer token check
		$token = $request->get_param( 'buyer_token' );
		if ( $token && hash_equals( (string) $thread->buyer_token, (string) $token ) ) {
			return true;
		}

		// Logged-in sponsor match
		if ( is_user_logged_in() && (int) $thread->sponsor_id === get_current_user_id() ) {
			return true;
		}

		return new WP_Error( 'forbidden', 'Buyer token or login required.', [ 'status' => 403 ] );
	}

	// ─── Helpers ───────────────────────────────────────────────────────────────

	private function get_code_row( string $code ): object {
		global $wpdb;

		$table = CreateRFPSupportTables::discount_codes_table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE code = %s LIMIT 1", $code ) );

		if ( ! $row ) {
			return new WP_Error( 'code_not_found', 'Discount code not found.', [ 'status' => 404 ] );
		}

		return $row;
	}

	private function get_thread( int $thread_id ): ?object {
		global $wpdb;

		$table = ConnectWorkflowMigration::threads_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $thread_id ) ) ?: null;
	}

	private function get_invoice_for_thread( int $thread_id ): ?object {
		global $wpdb;

		$table = CreateRFPSupportTables::invoices_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE thread_id = %d ORDER BY id DESC LIMIT 1", $thread_id ) ) ?: null;
	}
}
