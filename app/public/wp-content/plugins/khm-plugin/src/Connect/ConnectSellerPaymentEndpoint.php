<?php
/**
 * Seller Payment Endpoint
 *
 * Exposes REST routes for seller Stripe pre-registration:
 *   POST /khm/v1/connect/seller/payment-setup          → create Stripe SetupIntent
 *   POST /khm/v1/connect/seller/payment-setup/confirm  → confirm + save Stripe Customer ID
 *   GET  /khm/v1/connect/seller/payment-profile        → return spend limit / status
 *   PATCH /khm/v1/connect/seller/payment-profile       → update spend limit
 *
 * Stripe calls are made via the helper service ConnectStripeService.
 * This endpoint does not store raw card data — Stripe handles PCI.
 */

namespace KHM\Connect;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class ConnectSellerPaymentEndpoint {

	private ConnectSellerPaymentRepository $payment_repo;

	public function __construct( ?ConnectSellerPaymentRepository $payment_repo = null ) {
		$this->payment_repo = $payment_repo ?? new ConnectSellerPaymentRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		// Create a SetupIntent so the seller can enter their card in the frontend
		register_rest_route(
			'khm/v1',
			'/connect/seller/payment-setup',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_setup_intent' ],
				'permission_callback' => [ $this, 'require_seller_login' ],
			]
		);

		// Confirm the SetupIntent and save the resulting Stripe Customer ID
		register_rest_route(
			'khm/v1',
			'/connect/seller/payment-setup/confirm',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'confirm_setup_intent' ],
				'permission_callback' => [ $this, 'require_seller_login' ],
				'args'                => [
					'stripe_customer_id' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static function ( $v ) {
							return is_string( $v ) && str_starts_with( $v, 'cus_' );
						},
					],
				],
			]
		);

		// Get the seller's current payment profile (spend limit, auth status, card fallback)
		register_rest_route(
			'khm/v1',
			'/connect/seller/payment-profile',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_profile' ],
				'permission_callback' => [ $this, 'require_seller_login' ],
			]
		);

		// Update spend limit
		register_rest_route(
			'khm/v1',
			'/connect/seller/payment-profile',
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_spend_limit' ],
				'permission_callback' => [ $this, 'require_seller_login' ],
				'args'                => [
					'spend_limit' => [
						'required'          => true,
						'type'              => 'number',
						'minimum'           => 0,
						'maximum'           => 10000,
					],
				],
			]
		);
	}

	// ─── Handlers ──────────────────────────────────────────────────────────────

	/**
	 * POST /connect/seller/payment-setup
	 *
	 * Creates a Stripe SetupIntent with usage=off_session.
	 * Returns the client_secret for Stripe Elements on the frontend.
	 */
	public function create_setup_intent( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$seller_id = get_current_user_id();

		$stripe_secret_key = defined( 'KHM_STRIPE_SECRET_KEY' ) ? KHM_STRIPE_SECRET_KEY : get_site_option( 'khm_stripe_secret_key', '' );

		if ( empty( $stripe_secret_key ) ) {
			return new WP_Error( 'stripe_not_configured', 'Stripe is not configured.', [ 'status' => 503 ] );
		}

		// Create or retrieve Stripe Customer for this seller
		$profile = $this->payment_repo->get_by_seller_id( $seller_id );

		$stripe_customer_id = $profile->stripe_customer_id ?? null;

		if ( empty( $stripe_customer_id ) ) {
			$user               = get_userdata( $seller_id );
			$customer_response  = $this->stripe_post( $stripe_secret_key, 'customers', [
				'email'    => $user->user_email,
				'name'     => $user->display_name,
				'metadata' => [ 'wp_seller_id' => (string) $seller_id ],
			] );

			if ( is_wp_error( $customer_response ) ) {
				return $customer_response;
			}

			$stripe_customer_id = $customer_response['id'] ?? null;

			if ( empty( $stripe_customer_id ) ) {
				return new WP_Error( 'stripe_customer_failed', 'Failed to create Stripe Customer.', [ 'status' => 502 ] );
			}
		}

		// Create SetupIntent with off_session usage (required for UK/EU SCA deferred debits)
		$intent_response = $this->stripe_post( $stripe_secret_key, 'setup_intents', [
			'customer'             => $stripe_customer_id,
			'usage'                => 'off_session',
			'payment_method_types' => [ 'card' ],
			'metadata'             => [ 'wp_seller_id' => (string) $seller_id ],
		] );

		if ( is_wp_error( $intent_response ) ) {
			return $intent_response;
		}

		$client_secret = $intent_response['client_secret'] ?? null;

		if ( empty( $client_secret ) ) {
			return new WP_Error( 'stripe_intent_failed', 'Failed to create SetupIntent.', [ 'status' => 502 ] );
		}

		return new WP_REST_Response( [
			'client_secret'      => $client_secret,
			'stripe_customer_id' => $stripe_customer_id,
		], 200 );
	}

	/**
	 * POST /connect/seller/payment-setup/confirm
	 *
	 * Frontend calls this after Stripe.js confirms the SetupIntent.
	 * Saves the stripe_customer_id against this seller.
	 */
	public function confirm_setup_intent( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$seller_id          = get_current_user_id();
		$stripe_customer_id = $request->get_param( 'stripe_customer_id' );

		$saved = $this->payment_repo->save_stripe_customer( $seller_id, $stripe_customer_id );

		if ( ! $saved ) {
			return new WP_Error( 'save_failed', 'Failed to save payment profile.', [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * GET /connect/seller/payment-profile
	 *
	 * Returns the seller's current payment status for the dashboard.
	 */
	public function get_profile( WP_REST_Request $request ): WP_REST_Response {
		$seller_id = get_current_user_id();
		$profile   = $this->payment_repo->get_by_seller_id( $seller_id );

		if ( ! $profile ) {
			return new WP_REST_Response( [
				'payment_authorised'    => false,
				'spend_limit_monthly'   => 500.00,
				'spend_used_this_month' => 0.00,
				'card_fallback_enabled' => true,
			], 200 );
		}

		return new WP_REST_Response( [
			'payment_authorised'    => ! empty( $profile->stripe_customer_id ),
			'payment_authorised_at' => $profile->payment_auth_granted_at,
			'spend_limit_monthly'   => (float) $profile->spend_limit_monthly,
			'spend_used_this_month' => (float) $profile->spend_used_current_month,
			'card_fallback_enabled' => (bool) $profile->card_enabled_fallback,
		], 200 );
	}

	/**
	 * PATCH /connect/seller/payment-profile
	 *
	 * Lets the seller adjust their monthly spend limit.
	 */
	public function update_spend_limit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$seller_id = get_current_user_id();
		$limit     = (float) $request->get_param( 'spend_limit' );

		$saved = $this->payment_repo->update_spend_limit( $seller_id, $limit );

		if ( ! $saved ) {
			return new WP_Error( 'update_failed', 'Failed to update spend limit.', [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'success' => true, 'spend_limit_monthly' => max( 0.0, min( 10000.0, $limit ) ) ], 200 );
	}

	// ─── Permission ────────────────────────────────────────────────────────────

	public function require_seller_login(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'not_logged_in', 'Authentication required.', [ 'status' => 401 ] );
		}

		return true;
	}

	// ─── Stripe HTTP helper ────────────────────────────────────────────────────

	/**
	 * Make a POST request to the Stripe API.
	 *
	 * @param string $secret_key  Stripe secret key.
	 * @param string $endpoint    e.g. 'customers', 'setup_intents'.
	 * @param array  $body        Form params.
	 * @return array|WP_Error
	 */
	private function stripe_post( string $secret_key, string $endpoint, array $body ): array|WP_Error {
		$response = wp_remote_post(
			'https://api.stripe.com/v1/' . $endpoint,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Stripe-Version' => '2023-10-16',
				],
				'body'    => $this->flatten_body( $body ),
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		$code    = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$error_message = $decoded['error']['message'] ?? 'Unknown Stripe error.';
			return new WP_Error( 'stripe_error', $error_message, [ 'status' => $code ] );
		}

		return $decoded;
	}

	/**
	 * Flatten nested arrays into Stripe's bracket notation.
	 * e.g. ['metadata' => ['x' => 'y']] → ['metadata[x]' => 'y']
	 */
	private function flatten_body( array $data, string $prefix = '' ): array {
		$result = [];

		foreach ( $data as $key => $value ) {
			$full_key = $prefix ? "{$prefix}[{$key}]" : $key;

			if ( is_array( $value ) ) {
				$result = array_merge( $result, $this->flatten_body( $value, $full_key ) );
			} else {
				$result[ $full_key ] = $value;
			}
		}

		return $result;
	}
}
