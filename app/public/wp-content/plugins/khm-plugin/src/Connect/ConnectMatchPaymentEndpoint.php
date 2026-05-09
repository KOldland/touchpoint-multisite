<?php
/**
 * Connect Match Payment Endpoint
 *
 * Handles upfront Stripe Elements payment for active match / lead acceptance.
 *
 * Flow:
 *  1. POST /khm/v1/connect/match/{opportunity_id}/payment-intent
 *     → Creates a Stripe PaymentIntent for the match price.
 *     → Returns { client_secret, amount, currency, publishable_key }.
 *
 *  2. POST /khm/v1/connect/match/{opportunity_id}/accept
 *     → Verifies the PaymentIntent succeeded, then accepts the opportunity
 *       and creates the intro thread (delegates to ConnectOpportunityEndpoint logic).
 *     → Body: { provider_id, engaged_option?, payment_intent_id }
 *
 * Price source: ConnectTiering::get_unit_price_for_tier( $tier ) → unit_price_cents
 * Currency: khm_connect_match_currency option (default 'gbp').
 *
 * @package KHM\Connect
 */

namespace KHM\Connect;

use KHM\Services\SponsorService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class ConnectMatchPaymentEndpoint {

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			'khm/v1',
			'/connect/match/(?P<opportunity_id>\d+)/payment-intent',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_payment_intent' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'opportunity_id' => [
							'required'          => true,
							'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			'khm/v1',
			'/connect/match/(?P<opportunity_id>\d+)/accept',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'accept_with_payment' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'opportunity_id' => [
							'required'          => true,
							'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);
	}

	public function check_permission(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return SponsorService::get_user_sponsor( get_current_user_id() ) !== null
			|| current_user_can( 'manage_options' );
	}

	/**
	 * Create a Stripe PaymentIntent for the match tier price.
	 */
	public function create_payment_intent( WP_REST_Request $request ): WP_REST_Response {
		$opportunity_id = (int) $request->get_param( 'opportunity_id' );
		$sponsor        = SponsorService::get_user_sponsor( get_current_user_id() );
		if ( ! is_array( $sponsor ) ) {
			return $this->error( 'sponsor_required', __( 'Sponsor account required.', 'khm-membership' ), 403 );
		}

		$opportunity = $this->get_opportunity( $opportunity_id, (int) $sponsor['id'] );
		if ( is_wp_error( $opportunity ) ) {
			return rest_ensure_response( [ 'success' => false, 'message' => $opportunity->get_error_message() ] );
		}

		$tier        = $this->resolve_tier( $opportunity );
		$amount      = $this->resolve_amount_cents( $tier );
		$currency    = strtolower( (string) apply_filters( 'khm_connect_match_currency', get_option( 'khm_connect_match_currency', 'gbp' ) ) );
		$stripe_key  = function_exists( 'khm_get_stripe_secret' )
			? (string) ( khm_get_stripe_secret( 'KH_STRIPE_SECRET_KEY' ) ?? '' )
			: '';
		$pub_key     = (string) get_option( 'khm_stripe_publishable_key', '' );

		if ( empty( $stripe_key ) ) {
			return $this->error( 'stripe_not_configured', __( 'Payment system not configured.', 'khm-membership' ), 500 );
		}

		if ( $amount <= 0 ) {
			return $this->error( 'no_price', __( 'No price configured for this match tier.', 'khm-membership' ), 400 );
		}

		try {
			\Stripe\Stripe::setApiKey( $stripe_key );

			$intent = \Stripe\PaymentIntent::create( [
				'amount'               => $amount,
				'currency'             => $currency,
				'automatic_payment_methods' => [ 'enabled' => true ],
				'metadata'             => [
					'purchase_type'    => 'active_match',
					'opportunity_id'   => (string) $opportunity_id,
					'sponsor_id'       => (string) $sponsor['id'],
					'user_id'          => (string) get_current_user_id(),
					'tier'             => $tier,
				],
			] );

			return rest_ensure_response( [
				'success'         => true,
				'client_secret'   => $intent->client_secret,
				'payment_intent_id' => $intent->id,
				'amount'          => $amount,
				'currency'        => $currency,
				'publishable_key' => $pub_key,
			] );

		} catch ( \Exception $e ) {
			error_log( '[KHM Connect Match] PaymentIntent creation failed: ' . $e->getMessage() );
			return $this->error( 'stripe_error', __( 'Unable to create payment. Please try again.', 'khm-membership' ), 500 );
		}
	}

	/**
	 * Verify payment succeeded, then accept the opportunity and create the intro thread.
	 */
	public function accept_with_payment( WP_REST_Request $request ): WP_REST_Response {
		$opportunity_id = (int) $request->get_param( 'opportunity_id' );
		$params         = $request->get_json_params();
		$params         = is_array( $params ) ? $params : [];

		$provider_id       = absint( $params['provider_id'] ?? 0 );
		$engaged_option    = sanitize_key( (string) ( $params['engaged_option'] ?? '' ) );
		$payment_intent_id = sanitize_text_field( (string) ( $params['payment_intent_id'] ?? '' ) );

		if ( empty( $payment_intent_id ) ) {
			return $this->error( 'payment_required', __( 'Payment reference is required.', 'khm-membership' ), 400 );
		}

		$sponsor = SponsorService::get_user_sponsor( get_current_user_id() );
		if ( ! is_array( $sponsor ) ) {
			return $this->error( 'sponsor_required', __( 'Sponsor account required.', 'khm-membership' ), 403 );
		}

		// Verify the PaymentIntent succeeded.
		$stripe_key = function_exists( 'khm_get_stripe_secret' )
			? (string) ( khm_get_stripe_secret( 'KH_STRIPE_SECRET_KEY' ) ?? '' )
			: '';

		if ( empty( $stripe_key ) ) {
			return $this->error( 'stripe_not_configured', __( 'Payment system not configured.', 'khm-membership' ), 500 );
		}

		try {
			\Stripe\Stripe::setApiKey( $stripe_key );
			$intent = \Stripe\PaymentIntent::retrieve( $payment_intent_id );
		} catch ( \Exception $e ) {
			error_log( '[KHM Connect Match] PaymentIntent retrieval failed: ' . $e->getMessage() );
			return $this->error( 'stripe_error', __( 'Unable to verify payment. Please try again.', 'khm-membership' ), 500 );
		}

		// Security: verify the intent belongs to this opportunity + sponsor.
		$intent_meta_opportunity = (string) ( $intent->metadata['opportunity_id'] ?? '' );
		$intent_meta_sponsor     = (string) ( $intent->metadata['sponsor_id'] ?? '' );
		if ( $intent_meta_opportunity !== (string) $opportunity_id
			|| $intent_meta_sponsor !== (string) $sponsor['id']
		) {
			error_log( sprintf(
				'[KHM Connect Match] Intent mismatch: intent=%s opp=%s/%s sponsor=%s/%s',
				$payment_intent_id,
				$intent_meta_opportunity, $opportunity_id,
				$intent_meta_sponsor, $sponsor['id']
			) );
			return $this->error( 'payment_mismatch', __( 'Payment could not be verified for this opportunity.', 'khm-membership' ), 403 );
		}

		if ( 'succeeded' !== $intent->status ) {
			return $this->error(
				'payment_not_succeeded',
				sprintf(
					/* translators: %s payment status */
					__( 'Payment not yet complete (status: %s). Please complete checkout first.', 'khm-membership' ),
					sanitize_text_field( $intent->status )
				),
				402
			);
		}

		// Delegate actual opportunity acceptance to the existing endpoint handler.
		$accept_endpoint = new ConnectOpportunityEndpoint();
		$synthetic       = new \WP_REST_Request( 'POST', "/khm/v1/connect/opportunities/mine/{$opportunity_id}/accept" );
		$synthetic->set_param( 'id', $opportunity_id );
		$synthetic->set_body_params( [
			'provider_id'         => $provider_id,
			'engaged_option'      => $engaged_option ?: null,
			'payment_intent_id'   => $payment_intent_id,
			'payment_verified'    => true,
		] );

		return $accept_endpoint->accept_mine( $synthetic );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/** @return array<string,mixed>|WP_Error */
	private function get_opportunity( int $opportunity_id, int $sponsor_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'connect_opportunities';
		$row   = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
			$opportunity_id
		), ARRAY_A );

		if ( ! $row ) {
			return new WP_Error( 'opportunity_not_found', __( 'Opportunity not found.', 'khm-membership' ), [ 'status' => 404 ] );
		}

		return $row;
	}

	private function resolve_tier( array $opportunity ): string {
		return sanitize_key( (string) ( $opportunity['commercial_tier'] ?? $opportunity['tier'] ?? 'exploratory' ) );
	}

	private function resolve_amount_cents( string $tier ): int {
		$config = ConnectTiering::get_config();
		return isset( $config[ $tier ]['unit_price_cents'] ) ? (int) $config[ $tier ]['unit_price_cents'] : 0;
	}

	/** @return WP_REST_Response */
	private function error( string $code, string $message, int $status ): WP_REST_Response {
		return rest_ensure_response( [
			'success' => false,
			'code'    => $code,
			'message' => $message,
		] );
	}
}
