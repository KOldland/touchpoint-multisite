<?php
/**
 * Buyer Validation Endpoint
 *
 * REST routes for buyer identity verification and RFP cap enforcement:
 *   POST  /khm/v1/connect/buyer/request-verification  – buyer submits verification request
 *   GET   /khm/v1/connect/buyer/validation-status     – buyer checks their own status
 *   GET   /khm/v1/connect/admin/buyer-verifications   – admin: list pending approvals
 *   POST  /khm/v1/connect/admin/buyer-verifications/(?P<id>\d+)/approve – admin approves
 *   POST  /khm/v1/connect/admin/buyer-verifications/(?P<id>\d+)/reject  – admin rejects
 *   GET   /khm/v1/connect/buyer/rfp-cap               – buyer checks remaining RFP slots
 */

namespace KHM\Connect;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class ConnectBuyerValidationEndpoint {

	private ConnectBuyerValidationService $validation;

	public function __construct( ?ConnectBuyerValidationService $validation = null ) {
		$this->validation = $validation ?? new ConnectBuyerValidationService();
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		// Buyer: submit verification request
		register_rest_route( 'khm/v1', '/connect/buyer/request-verification', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'request_verification' ],
			'permission_callback' => [ $this, 'require_login' ],
		] );

		// Buyer: check own validation status + badge
		register_rest_route( 'khm/v1', '/connect/buyer/validation-status', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_buyer_status' ],
			'permission_callback' => [ $this, 'require_login' ],
		] );

		// Buyer: how many RFP slots remain?
		register_rest_route( 'khm/v1', '/connect/buyer/rfp-cap', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_rfp_cap' ],
			'permission_callback' => [ $this, 'require_login' ],
		] );

		// Admin: list pending approvals
		register_rest_route( 'khm/v1', '/connect/admin/buyer-verifications', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_pending' ],
			'permission_callback' => [ $this, 'require_admin' ],
		] );

		// Admin: approve a buyer
		register_rest_route( 'khm/v1', '/connect/admin/buyer-verifications/(?P<id>\d+)/approve', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'approve_buyer' ],
			'permission_callback' => [ $this, 'require_admin' ],
		] );

		// Admin: reject a buyer
		register_rest_route( 'khm/v1', '/connect/admin/buyer-verifications/(?P<id>\d+)/reject', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'reject_buyer' ],
			'permission_callback' => [ $this, 'require_admin' ],
		] );
	}

	// ─── Buyer handlers ────────────────────────────────────────────────────────

	public function request_verification( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$buyer_id = get_current_user_id();
		$current  = $this->validation->get_status( $buyer_id );

		if ( in_array( $current, [ 'pending', 'verified' ], true ) ) {
			return new WP_Error( 'already_requested', 'Verification already submitted or approved.', [ 'status' => 409 ] );
		}

		$saved = $this->validation->set_status( $buyer_id, 'pending' );

		if ( ! $saved ) {
			// No opportunities linked yet — store the pending status on the user meta
			// as a fallback until their first RFP is created.
			update_user_meta( $buyer_id, 'khm_buyer_validation_status', 'pending' );
		}

		/**
		 * Fires after a buyer requests identity verification.
		 * Listeners can send notification emails to admins here.
		 *
		 * @param int $buyer_id
		 */
		do_action( 'khm_buyer_verification_requested', $buyer_id );

		return new WP_REST_Response( [ 'status' => 'pending' ], 200 );
	}

	public function get_buyer_status( WP_REST_Request $request ): WP_REST_Response {
		$buyer_id = get_current_user_id();

		// Prefer DB status; fall back to user meta for buyers without RFPs yet
		$status = $this->validation->get_status( $buyer_id );
		if ( 'unverified' === $status ) {
			$meta = get_user_meta( $buyer_id, 'khm_buyer_validation_status', true );
			if ( $meta ) {
				$status = $meta;
			}
		}

		return new WP_REST_Response( [
			'status'       => $status,
			'badge_visible' => $this->validation->badge_is_visible( $buyer_id ),
		], 200 );
	}

	public function get_rfp_cap( WP_REST_Request $request ): WP_REST_Response {
		$buyer_id     = get_current_user_id();
		$active_count = $this->validation->count_active_rfps( $buyer_id );

		return new WP_REST_Response( [
			'active_rfps'   => $active_count,
			'max_rfps'      => ConnectBuyerValidationService::MAX_ACTIVE_RFPS,
			'slots_remaining' => max( 0, ConnectBuyerValidationService::MAX_ACTIVE_RFPS - $active_count ),
			'can_open_rfp'  => $this->validation->can_open_rfp( $buyer_id ),
		], 200 );
	}

	// ─── Admin handlers ────────────────────────────────────────────────────────

	public function list_pending( WP_REST_Request $request ): WP_REST_Response {
		$pending = $this->validation->get_pending_approvals();

		$data = array_map( function ( $row ) {
			$user = get_userdata( (int) $row->buyer_account_id );
			return [
				'buyer_id'     => (int) $row->buyer_account_id,
				'display_name' => $user ? $user->display_name : 'Unknown',
				'email'        => $user ? $user->user_email : '',
				'submitted_at' => $row->submitted_at,
				'status'       => $row->buyer_validation_status,
			];
		}, $pending );

		return new WP_REST_Response( $data, 200 );
	}

	public function approve_buyer( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$buyer_id = (int) $request->get_param( 'id' );

		if ( ! get_userdata( $buyer_id ) ) {
			return new WP_Error( 'buyer_not_found', 'Buyer not found.', [ 'status' => 404 ] );
		}

		$this->validation->set_status( $buyer_id, 'verified' );
		update_user_meta( $buyer_id, 'khm_buyer_validation_status', 'verified' );

		do_action( 'khm_buyer_verification_approved', $buyer_id );

		return new WP_REST_Response( [ 'success' => true, 'status' => 'verified' ], 200 );
	}

	public function reject_buyer( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$buyer_id = (int) $request->get_param( 'id' );

		if ( ! get_userdata( $buyer_id ) ) {
			return new WP_Error( 'buyer_not_found', 'Buyer not found.', [ 'status' => 404 ] );
		}

		$this->validation->set_status( $buyer_id, 'rejected' );
		update_user_meta( $buyer_id, 'khm_buyer_validation_status', 'rejected' );

		do_action( 'khm_buyer_verification_rejected', $buyer_id );

		return new WP_REST_Response( [ 'success' => true, 'status' => 'rejected' ], 200 );
	}

	// ─── Permissions ───────────────────────────────────────────────────────────

	public function require_login(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'not_logged_in', 'Authentication required.', [ 'status' => 401 ] );
		}

		return true;
	}

	public function require_admin(): bool|WP_Error {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return new WP_Error( 'forbidden', 'Administrator access required.', [ 'status' => 403 ] );
		}

		return true;
	}
}
