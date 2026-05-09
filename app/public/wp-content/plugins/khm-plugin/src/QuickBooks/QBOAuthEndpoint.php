<?php
/**
 * QuickBooks Online OAuth 2.0 REST Endpoints
 *
 * Endpoints:
 *   GET  /khm/v1/qbo/oauth/connect   — Admin-only: redirect to Intuit consent screen
 *   GET  /khm/v1/qbo/oauth/callback  — Intuit redirects here after consent
 *   POST /khm/v1/qbo/oauth/disconnect — Admin-only: clear stored tokens
 *
 * @package KHM\QuickBooks
 */

namespace KHM\QuickBooks;

defined( 'ABSPATH' ) || exit;

class QBOAuthEndpoint {

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		// Connect uses admin-post so WP cookie auth works without a JS nonce.
		add_action( 'admin_post_khm_qbo_connect', [ $this, 'connect' ] );
	}

	public function register_routes(): void {
		// Note: /connect is intentionally NOT a REST route — it's an admin-post action.
		// Visit: /wp-admin/admin-post.php?action=khm_qbo_connect&_wpnonce=<nonce>

		register_rest_route( 'khm/v1', '/qbo/oauth/callback', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'callback' ],
			'permission_callback' => '__return_true', // Intuit redirects here; state param guards CSRF
		] );

		register_rest_route( 'khm/v1', '/qbo/oauth/disconnect', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'disconnect' ],
			'permission_callback' => [ $this, 'admin_only' ],
		] );

		register_rest_route( 'khm/v1', '/qbo/oauth/status', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'status' ],
			'permission_callback' => [ $this, 'admin_only' ],
		] );
	}

	// ─── Permission ───────────────────────────────────────────────────────────

	public function admin_only(): bool {
		return current_user_can( 'manage_options' );
	}

	// ─── Endpoints ────────────────────────────────────────────────────────────

	/**
	 * Redirect admin to Intuit OAuth consent screen.
	 * Triggered via admin-post.php?action=khm_qbo_connect&_wpnonce=<nonce>
	 */
	public function connect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.', 'Forbidden', [ 'response' => 403 ] );
		}

		check_admin_referer( 'khm_qbo_connect' );
		// Generate and store a CSRF state token.
		$state = wp_generate_password( 24, false );
		set_transient( 'khm_qbo_oauth_state_' . $state, 1, 300 );

		try {
			$service = new QBOService();
			$auth_url = $service->get_authorization_url();
		} catch ( \Throwable $e ) {
			wp_die( 'QB OAuth setup error: ' . esc_html( $e->getMessage() ), 'QB OAuth Error', [ 'response' => 500 ] );
		}

		// Append state to the URL returned by the SDK.
		$redirect = add_query_arg( 'state', $state, $auth_url );
		wp_redirect( $redirect );
		exit;
	}

	/**
	 * Handle Intuit's OAuth callback — exchange code for tokens.
	 */
	public function callback( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$code     = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$realm_id = sanitize_text_field( (string) $request->get_param( 'realmId' ) );
		$state    = sanitize_text_field( (string) $request->get_param( 'state' ) );
		$error    = sanitize_text_field( (string) $request->get_param( 'error' ) );

		// Handle user-denied flow.
		if ( $error ) {
			return $this->redirect_to_admin( 'qbo_error', urlencode( $error ) );
		}

		if ( ! $code || ! $realm_id ) {
			return new \WP_Error( 'qbo_invalid_callback', 'Missing code or realmId.', [ 'status' => 400 ] );
		}

		// CSRF state check.
		$state_key = 'khm_qbo_oauth_state_' . $state;
		if ( ! $state || ! get_transient( $state_key ) ) {
			return new \WP_Error( 'qbo_invalid_state', 'OAuth state mismatch — possible CSRF.', [ 'status' => 403 ] );
		}
		delete_transient( $state_key );

		try {
			$service = new QBOService();
			$service->exchange_code_for_tokens( $code, $realm_id );
		} catch ( \Throwable $e ) {
			error_log( '[KHM QBO] OAuth exchange error: ' . $e->getMessage() );
			return $this->redirect_to_admin( 'qbo_error', urlencode( 'Token exchange failed: ' . $e->getMessage() ) );
		}

		return $this->redirect_to_admin( 'qbo_connected', '1' );
	}

	/**
	 * Clear stored QB tokens (disconnect).
	 */
	public function disconnect( \WP_REST_Request $request ): \WP_REST_Response {
		foreach ( [
			'khm_qbo_access_token',
			'khm_qbo_refresh_token',
			'khm_qbo_token_expiry',
			'khm_qbo_refresh_expiry',
		] as $option ) {
			delete_option( $option );
		}

		return new \WP_REST_Response( [ 'disconnected' => true ] );
	}

	/**
	 * Return connection status.
	 */
	public function status( \WP_REST_Request $request ): \WP_REST_Response {
		$access_token = (string) get_option( 'khm_qbo_access_token', '' );
		$expiry       = (int) get_option( 'khm_qbo_token_expiry', 0 );
		$realm_id     = (string) get_option( 'khm_qbo_realm_id', '' );
		$env          = (string) get_option( 'khm_qbo_environment', 'sandbox' );

		$connected   = $access_token !== '' && $expiry > time();
		$expires_in  = $connected ? ( $expiry - time() ) : 0;

		return new \WP_REST_Response( [
			'connected'  => $connected,
			'realm_id'   => $realm_id,
			'expires_in' => $expires_in,
			'environment'=> $env,
		] );
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Build a redirect response back to WP admin with a query param notice.
	 */
	private function redirect_to_admin( string $param, string $value ): void {
		$admin_url = add_query_arg( $param, $value, admin_url( 'admin.php?page=khm-settings' ) );
		wp_redirect( $admin_url );
		exit;
	}
}
