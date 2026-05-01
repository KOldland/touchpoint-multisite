<?php

namespace KHM\REST;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * LinkedIn OAuth + post-scheduling REST controller.
 *
 * Namespace : khm/v1
 * Routes    :
 *   GET  /social/linkedin/auth-url   — returns the LinkedIn OAuth 2 authorise URL
 *   GET  /social/linkedin/callback   — OAuth callback (public, code exchange)
 *   POST /social/linkedin/disconnect — revoke + clear stored token
 *   GET  /social/linkedin/status     — current connection status
 *   POST /social/linkedin/schedule   — queue a post (text + optional link)
 *   GET  /social/linkedin/queue      — list queued/sent posts
 *   POST /social/linkedin/cancel     — cancel a queued post
 *
 * Meta keys:
 *   khm_linkedin_access_token  — string
 *   khm_linkedin_token_expiry  — int (Unix timestamp)
 *   khm_linkedin_profile_id    — string (LI member URN)
 *   khm_linkedin_queue         — array of { id, text, url, scheduled_at, status }
 */
class LinkedInController {

	private const OPTION_CLIENT_ID     = 'khm_linkedin_client_id';
	private const OPTION_CLIENT_SECRET = 'khm_linkedin_client_secret';
	private const SCOPE                = 'openid profile w_member_social';
	private const AUTH_URL             = 'https://www.linkedin.com/oauth/v2/authorization';
	private const TOKEN_URL            = 'https://www.linkedin.com/oauth/v2/accessToken';
	private const PROFILE_URL          = 'https://api.linkedin.com/v2/userinfo';
	private const SHARE_URL            = 'https://api.linkedin.com/v2/ugcPosts';
	private const CRON_HOOK            = 'khm_linkedin_publish_post';

	// -------------------------------------------------------------------------
	// Route registration
	// -------------------------------------------------------------------------

	public function register(): void {
		register_rest_route( 'khm/v1', '/social/linkedin/auth-url', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_auth_url' ],
			'permission_callback' => [ $this, 'require_sponsor' ],
		] );

		// Public — LinkedIn redirects the browser here after auth.
		register_rest_route( 'khm/v1', '/social/linkedin/callback', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_callback' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'khm/v1', '/social/linkedin/disconnect', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'disconnect' ],
			'permission_callback' => [ $this, 'require_sponsor' ],
		] );

		register_rest_route( 'khm/v1', '/social/linkedin/status', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_status' ],
			'permission_callback' => [ $this, 'require_sponsor' ],
		] );

		register_rest_route( 'khm/v1', '/social/linkedin/schedule', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'schedule_post' ],
			'permission_callback' => [ $this, 'require_sponsor' ],
		] );

		register_rest_route( 'khm/v1', '/social/linkedin/queue', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_queue' ],
			'permission_callback' => [ $this, 'require_sponsor' ],
		] );

		register_rest_route( 'khm/v1', '/social/linkedin/cancel', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'cancel_post' ],
			'permission_callback' => [ $this, 'require_sponsor' ],
		] );

		// WP cron handler — registered once here so the hook is always declared.
		add_action( self::CRON_HOOK, [ $this, 'publish_scheduled_post' ], 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Permission
	// -------------------------------------------------------------------------

	public function require_sponsor(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$user = wp_get_current_user();
		return in_array( 'khm_sponsor', (array) $user->roles, true ) || current_user_can( 'manage_options' );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function client_id(): string {
		return (string) get_option( self::OPTION_CLIENT_ID, '' );
	}

	private function client_secret(): string {
		return (string) get_option( self::OPTION_CLIENT_SECRET, '' );
	}

	private function redirect_uri(): string {
		return rest_url( 'khm/v1/social/linkedin/callback' );
	}

	private function get_token( int $user_id ): string {
		$expiry = (int) get_user_meta( $user_id, 'khm_linkedin_token_expiry', true );
		if ( $expiry && $expiry < time() ) {
			// Token expired — clear it.
			delete_user_meta( $user_id, 'khm_linkedin_access_token' );
			delete_user_meta( $user_id, 'khm_linkedin_token_expiry' );
			delete_user_meta( $user_id, 'khm_linkedin_profile_id' );
			return '';
		}
		return (string) get_user_meta( $user_id, 'khm_linkedin_access_token', true );
	}

	private function get_queue_items( int $user_id ): array {
		$items = get_user_meta( $user_id, 'khm_linkedin_queue', true );
		return is_array( $items ) ? $items : [];
	}

	private function save_queue_items( int $user_id, array $items ): void {
		update_user_meta( $user_id, 'khm_linkedin_queue', $items );
	}

	// -------------------------------------------------------------------------
	// Auth URL
	// -------------------------------------------------------------------------

	public function get_auth_url( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$client_id = $this->client_id();
		if ( ! $client_id ) {
			return new WP_Error( 'not_configured', 'LinkedIn app credentials not configured.', [ 'status' => 503 ] );
		}

		$user_id = get_current_user_id();
		$state   = wp_create_nonce( 'khm_li_oauth_' . $user_id );
		// Store state + user_id so we can recover the user in the callback.
		set_transient( 'khm_li_state_' . $state, $user_id, 600 );

		$url = add_query_arg( [
			'response_type' => 'code',
			'client_id'     => $client_id,
			'redirect_uri'  => rawurlencode( $this->redirect_uri() ),
			'state'         => $state,
			'scope'         => rawurlencode( self::SCOPE ),
		], self::AUTH_URL );

		return new WP_REST_Response( [ 'success' => true, 'auth_url' => $url ], 200 );
	}

	// -------------------------------------------------------------------------
	// OAuth callback
	// -------------------------------------------------------------------------

	public function handle_callback( WP_REST_Request $request ): never {
		$code  = sanitize_text_field( $request->get_param( 'code' ) ?: '' );
		$state = sanitize_text_field( $request->get_param( 'state' ) ?: '' );
		$error = sanitize_text_field( $request->get_param( 'error' ) ?: '' );

		// LinkedIn sends user back to a REST endpoint URL, so we redirect
		// to the portal page after processing.
		$portal_url = home_url( '/?qc_section=social' );

		if ( $error || ! $code || ! $state ) {
			wp_safe_redirect( add_query_arg( 'li_error', rawurlencode( $error ?: 'cancelled' ), $portal_url ) );
			exit;
		}

		$user_id = (int) get_transient( 'khm_li_state_' . $state );
		delete_transient( 'khm_li_state_' . $state );

		if ( ! $user_id ) {
			wp_safe_redirect( add_query_arg( 'li_error', 'invalid_state', $portal_url ) );
			exit;
		}

		// Exchange code for access token.
		$response = wp_remote_post( self::TOKEN_URL, [
			'timeout' => 15,
			'body'    => [
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'redirect_uri'  => $this->redirect_uri(),
				'client_id'     => $this->client_id(),
				'client_secret' => $this->client_secret(),
			],
		] );

		if ( is_wp_error( $response ) ) {
			error_log( '[KHM LinkedIn] Token exchange failed: ' . $response->get_error_message() );
			wp_safe_redirect( add_query_arg( 'li_error', 'token_exchange', $portal_url ) );
			exit;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			error_log( '[KHM LinkedIn] No access_token in response: ' . wp_remote_retrieve_body( $response ) );
			wp_safe_redirect( add_query_arg( 'li_error', 'no_token', $portal_url ) );
			exit;
		}

		$access_token = sanitize_text_field( $body['access_token'] );
		$expires_in   = (int) ( $body['expires_in'] ?? 5184000 ); // default 60 days
		$expiry       = time() + $expires_in;

		update_user_meta( $user_id, 'khm_linkedin_access_token', $access_token );
		update_user_meta( $user_id, 'khm_linkedin_token_expiry', $expiry );

		// Fetch profile ID (member URN) from OpenID Connect userinfo endpoint.
		$profile = wp_remote_get( self::PROFILE_URL, [
			'timeout' => 10,
			'headers' => [ 'Authorization' => 'Bearer ' . $access_token ],
		] );

		if ( ! is_wp_error( $profile ) ) {
			$pdata = json_decode( wp_remote_retrieve_body( $profile ), true );
			if ( ! empty( $pdata['sub'] ) ) {
				update_user_meta( $user_id, 'khm_linkedin_profile_id', sanitize_text_field( $pdata['sub'] ) );
			}
		}

		wp_safe_redirect( add_query_arg( 'li_connected', '1', $portal_url ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Disconnect
	// -------------------------------------------------------------------------

	public function disconnect( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		delete_user_meta( $user_id, 'khm_linkedin_access_token' );
		delete_user_meta( $user_id, 'khm_linkedin_token_expiry' );
		delete_user_meta( $user_id, 'khm_linkedin_profile_id' );
		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	// -------------------------------------------------------------------------
	// Status
	// -------------------------------------------------------------------------

	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		$user_id    = get_current_user_id();
		$token      = $this->get_token( $user_id );
		$profile_id = (string) get_user_meta( $user_id, 'khm_linkedin_profile_id', true );
		$expiry     = (int) get_user_meta( $user_id, 'khm_linkedin_token_expiry', true );
		$configured = (bool) $this->client_id();

		return new WP_REST_Response( [
			'success'     => true,
			'configured'  => $configured,
			'connected'   => ! empty( $token ),
			'profile_id'  => $profile_id,
			'expires_at'  => $expiry ? gmdate( 'c', $expiry ) : null,
		], 200 );
	}

	// -------------------------------------------------------------------------
	// Schedule a post
	// -------------------------------------------------------------------------

	public function schedule_post( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$token   = $this->get_token( $user_id );
		if ( ! $token ) {
			return new WP_Error( 'not_connected', 'LinkedIn account not connected.', [ 'status' => 403 ] );
		}

		$text         = sanitize_textarea_field( $request->get_param( 'text' ) ?: '' );
		$link         = esc_url_raw( $request->get_param( 'url' ) ?: '' );
		$scheduled_at = sanitize_text_field( $request->get_param( 'scheduled_at' ) ?: '' );

		if ( ! $text ) {
			return new WP_Error( 'missing_text', 'Post text is required.', [ 'status' => 400 ] );
		}

		if ( strlen( $text ) > 3000 ) {
			return new WP_Error( 'text_too_long', 'Post text must be 3000 characters or fewer.', [ 'status' => 400 ] );
		}

		// Parse and validate the scheduled time.
		$ts = $scheduled_at ? strtotime( $scheduled_at ) : false;
		if ( ! $ts || $ts < time() + 60 ) {
			// Default: post in 5 minutes.
			$ts = time() + 300;
		}

		$post_id = uniqid( 'li_', true );
		$item    = [
			'id'           => $post_id,
			'text'         => $text,
			'url'          => $link,
			'scheduled_at' => $ts,
			'status'       => 'queued',
			'created_at'   => time(),
		];

		$queue   = $this->get_queue_items( $user_id );
		$queue[] = $item;
		$this->save_queue_items( $user_id, $queue );

		// Schedule the WP cron event.
		wp_schedule_single_event( $ts, self::CRON_HOOK, [ $user_id, $post_id ] );

		return new WP_REST_Response( [
			'success' => true,
			'post'    => [
				'id'           => $post_id,
				'text'         => $text,
				'url'          => $link,
				'scheduled_at' => gmdate( 'c', $ts ),
				'status'       => 'queued',
			],
		], 201 );
	}

	// -------------------------------------------------------------------------
	// Queue list
	// -------------------------------------------------------------------------

	public function get_queue( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$items   = $this->get_queue_items( $user_id );

		// Newest first, limit to 50.
		usort( $items, fn( $a, $b ) => $b['created_at'] - $a['created_at'] );
		$items = array_slice( $items, 0, 50 );

		return new WP_REST_Response( [
			'success' => true,
			'posts'   => array_map( fn( $i ) => [
				'id'           => $i['id'],
				'text'         => $i['text'],
				'url'          => $i['url'] ?? '',
				'scheduled_at' => gmdate( 'c', $i['scheduled_at'] ),
				'status'       => $i['status'],
				'li_post_id'   => $i['li_post_id'] ?? null,
				'error'        => $i['error'] ?? null,
			], $items ),
		], 200 );
	}

	// -------------------------------------------------------------------------
	// Cancel
	// -------------------------------------------------------------------------

	public function cancel_post( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$post_id = sanitize_text_field( $request->get_param( 'post_id' ) ?: '' );
		if ( ! $post_id ) {
			return new WP_Error( 'missing_id', 'post_id is required.', [ 'status' => 400 ] );
		}

		$queue = $this->get_queue_items( $user_id );
		$found = false;
		foreach ( $queue as &$item ) {
			if ( $item['id'] === $post_id && $item['status'] === 'queued' ) {
				$item['status'] = 'cancelled';
				$found          = true;
				// Remove scheduled cron event.
				wp_clear_scheduled_hook( self::CRON_HOOK, [ $user_id, $post_id ] );
				break;
			}
		}
		unset( $item );

		if ( ! $found ) {
			return new WP_Error( 'not_found', 'Queued post not found.', [ 'status' => 404 ] );
		}

		$this->save_queue_items( $user_id, $queue );
		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	// -------------------------------------------------------------------------
	// WP cron executor
	// -------------------------------------------------------------------------

	/**
	 * Fired by WP cron — sends the queued post to LinkedIn.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $post_id Internal queue item ID.
	 */
	public function publish_scheduled_post( int $user_id, string $post_id ): void {
		$queue = $this->get_queue_items( $user_id );
		$index = null;
		foreach ( $queue as $i => $item ) {
			if ( $item['id'] === $post_id ) {
				$index = $i;
				break;
			}
		}

		if ( $index === null || $queue[ $index ]['status'] !== 'queued' ) {
			return; // Already cancelled or missing.
		}

		$token      = $this->get_token( $user_id );
		$profile_id = (string) get_user_meta( $user_id, 'khm_linkedin_profile_id', true );

		if ( ! $token || ! $profile_id ) {
			$queue[ $index ]['status'] = 'failed';
			$queue[ $index ]['error']  = 'Token or profile ID missing at publish time.';
			$this->save_queue_items( $user_id, $queue );
			return;
		}

		$item = $queue[ $index ];
		$text = $item['text'];
		$link = ! empty( $item['url'] ) ? esc_url_raw( $item['url'] ) : '';

		// UGC Post payload for LinkedIn API v2.
		// When a URL is present attach it as a rich ARTICLE preview instead of
		// appending it to the text body (better click-through + LinkedIn ranking).
		if ( $link ) {
			$share_content = [
				'shareCommentary'    => [ 'text' => $text ],
				'shareMediaCategory' => 'ARTICLE',
				'media'              => [
					[
						'status'      => 'READY',
						'originalUrl' => $link,
					],
				],
			];
		} else {
			$share_content = [
				'shareCommentary'    => [ 'text' => $text ],
				'shareMediaCategory' => 'NONE',
			];
		}

		// UGC Post payload for LinkedIn API v2.
		$body = [
			'author'          => 'urn:li:person:' . $profile_id,
			'lifecycleState'  => 'PUBLISHED',
			'specificContent' => [
				'com.linkedin.ugc.ShareContent' => $share_content,
			],
			'visibility' => [
				'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
			],
		];

		$response = wp_remote_post( self::SHARE_URL, [
			'timeout' => 15,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'X-Restli-Protocol-Version' => '2.0.0',
			],
			'body' => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			$queue[ $index ]['status'] = 'failed';
			$queue[ $index ]['error']  = $response->get_error_message();
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			if ( $code === 201 ) {
				$queue[ $index ]['status']      = 'published';
				$queue[ $index ]['li_post_id']  = wp_remote_retrieve_header( $response, 'x-restli-id' );
				$queue[ $index ]['published_at'] = time();
			} else {
				$queue[ $index ]['status'] = 'failed';
				$queue[ $index ]['error']  = 'LI API returned HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response );
			}
		}

		$this->save_queue_items( $user_id, $queue );
	}
}
