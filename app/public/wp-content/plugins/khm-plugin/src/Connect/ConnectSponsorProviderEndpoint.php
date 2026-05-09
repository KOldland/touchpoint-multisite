<?php

namespace KHM\Connect;

use KHM\Services\SponsorService;
use KHM\QuickBooks\QBOService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

// ConnectTiering is in the same namespace (KHM\Connect) — no additional import needed.

defined( 'ABSPATH' ) || exit;

class ConnectSponsorProviderEndpoint {

	private ConnectProviderRepository $providers;

	public function __construct( ?ConnectProviderRepository $providers = null ) {
		$this->providers = $providers ?? new ConnectProviderRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'khm/v1',
			'/connect/providers/mine',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_mine' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_mine' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/boost',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_boost' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'request_boost' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/subscription',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_subscription' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'request_subscription' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/subscription/checkout',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_subscription_checkout' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/providers/mine/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_mine' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_mine' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	public function check_permission(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return SponsorService::get_user_sponsor( get_current_user_id() ) !== null;
	}

	public function list_mine( WP_REST_Request $request ) {
		$sponsor_id = $this->resolve_sponsor_id();

		if ( is_wp_error( $sponsor_id ) ) {
			return $sponsor_id;
		}

		if ( 'network' === $request->get_param( 'scope' ) ) {
			$providers = $this->providers->list_sites_for_sponsor( (int) $sponsor_id );
			return rest_ensure_response(
				array(
					'success'   => true,
					'providers' => array_values( array_map( array( $this, 'format_provider' ), $providers ) ),
				)
			);
		}

		$providers = $this->providers->list_for_sponsor( (int) $sponsor_id );

		return rest_ensure_response(
			array(
				'success'   => true,
				'providers' => array_values( array_map( array( $this, 'format_provider' ), $providers ) ),
			)
		);
	}

	public function create_mine( WP_REST_Request $request ) {
		$sponsor_id = $this->resolve_sponsor_id();

		if ( is_wp_error( $sponsor_id ) ) {
			return $sponsor_id;
		}

		$params = $this->get_request_params( $request );
		$params['sponsor_id'] = (int) $sponsor_id;

		$validation = $this->validate_payload( $params );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$provider_id = $this->providers->save( $params );
		$provider    = $provider_id > 0 ? $this->providers->get_by_id( $provider_id ) : null;

		if ( ! is_array( $provider ) ) {
			return new WP_Error( 'connect_provider_save_failed', __( 'Unable to save provider offering.', 'khm-membership' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'provider' => $this->format_provider( $provider ),
			)
		);
	}

	public function update_mine( WP_REST_Request $request ) {
		$provider = $this->resolve_managed_provider( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		$params              = $this->get_request_params( $request );
		$params['id']        = (int) $provider['id'];
		$params['sponsor_id'] = (int) $provider['sponsor_id'];

		$validation = $this->validate_payload( $params );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$provider_id = $this->providers->save( $params );
		$updated     = $provider_id > 0 ? $this->providers->get_by_id( $provider_id ) : null;

		if ( ! is_array( $updated ) ) {
			return new WP_Error( 'connect_provider_save_failed', __( 'Unable to update provider offering.', 'khm-membership' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'provider' => $this->format_provider( $updated ),
			)
		);
	}

	public function delete_mine( WP_REST_Request $request ) {
		$provider = $this->resolve_managed_provider( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		$deleted = $this->providers->delete( (int) $provider['id'] );

		if ( ! $deleted ) {
			return new WP_Error( 'connect_provider_delete_failed', __( 'Unable to delete provider offering.', 'khm-membership' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	private function resolve_sponsor_id() {
		if ( current_user_can( 'manage_options' ) ) {
			$sponsor_id = absint( $_GET['sponsor_id'] ?? 0 );
			if ( $sponsor_id > 0 ) {
				return $sponsor_id;
			}
		}

		$sponsor = SponsorService::get_user_sponsor( get_current_user_id() );
		if ( ! is_array( $sponsor ) || empty( $sponsor['id'] ) ) {
			return new WP_Error( 'connect_sponsor_required', __( 'Sponsor account required.', 'khm-membership' ), array( 'status' => 403 ) );
		}

		return (int) $sponsor['id'];
	}

	private function resolve_managed_provider( int $provider_id ) {
		$provider = $provider_id > 0 ? $this->providers->get_by_id( $provider_id ) : null;
		if ( ! is_array( $provider ) ) {
			return new WP_Error( 'connect_provider_not_found', __( 'Provider offering not found.', 'khm-membership' ), array( 'status' => 404 ) );
		}

		if ( current_user_can( 'manage_options' ) ) {
			return $provider;
		}

		$sponsor_id = $this->resolve_sponsor_id();
		if ( is_wp_error( $sponsor_id ) ) {
			return $sponsor_id;
		}

		if ( (int) $provider['sponsor_id'] !== (int) $sponsor_id ) {
			return new WP_Error( 'connect_provider_forbidden', __( 'You cannot manage this provider offering.', 'khm-membership' ), array( 'status' => 403 ) );
		}

		return $provider;
	}

	public function get_boost( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( array(
			'success' => true,
			'balance' => (int) get_user_meta( get_current_user_id(), 'khm_boost_credits', true ),
		) );
	}

	public function request_boost( WP_REST_Request $request ): WP_REST_Response {
		$params   = $request->get_json_params();
		$params   = is_array( $params ) ? $params : array();
		$quantity = max( 1, min( 100, (int) ( $params['quantity'] ?? 1 ) ) );

		// Notify admin — boost credits are fulfilled manually / via invoice until a payment gateway is wired.
		$sponsor      = SponsorService::get_user_sponsor( get_current_user_id() );
		$sponsor_name = is_array( $sponsor ) ? ( (string) ( $sponsor['name'] ?? '' ) ) : '';

		wp_mail(
			get_option( 'admin_email' ),
			sprintf(
				/* translators: %s sponsor name */
				__( '[Boost] Credit request: %s', 'khm-membership' ),
				$sponsor_name
			),
			sprintf(
				/* translators: 1: sponsor name, 2: quantity */
				__( 'Sponsor "%1$s" has requested %2$d Boost Credit(s).%3$sPlease invoice and then add the credits via WP admin: Users → Edit → khm_boost_credits meta field.', 'khm-membership' ),
				$sponsor_name,
				$quantity,
				"\n\n"
			)
		);

		$current_balance = (int) get_user_meta( get_current_user_id(), 'khm_boost_credits', true );

		return rest_ensure_response( array(
			'success' => true,
			'balance' => $current_balance,
			'message' => sprintf(
				/* translators: %d number of boost credits requested */
				__( 'Your request for %d Boost Credit(s) has been received. We will invoice you and apply the credits within 1 business day.', 'khm-membership' ),
				$quantity
			),
		) );
	}

	public function get_subscription( WP_REST_Request $request ): WP_REST_Response {
		$sponsor_id = $this->resolve_sponsor_id();
		if ( is_wp_error( $sponsor_id ) ) {
			return rest_ensure_response( array( 'success' => false, 'subscription' => null ) );
		}

		$meta = get_user_meta( get_current_user_id(), 'khm_connect_subscription', true );
		$sub  = is_array( $meta ) ? $meta : array();

		return rest_ensure_response( array(
			'success'      => true,
			'subscription' => array(
				'tier'         => (string) ( $sub['tier']         ?? '' ),
				'scope'        => (string) ( $sub['scope']        ?? '' ),
				'status'       => (string) ( $sub['status']       ?? 'inactive' ),
				'requested_at' => (string) ( $sub['requested_at'] ?? '' ),
				'activated_at' => (string) ( $sub['activated_at'] ?? '' ),
			),
			'pricing'      => ConnectTiering::get_config(),
		) );
	}

	public function request_subscription( WP_REST_Request $request ): WP_REST_Response {
		$sponsor_id = $this->resolve_sponsor_id();
		if ( is_wp_error( $sponsor_id ) ) {
			return rest_ensure_response( array( 'success' => false, 'message' => __( 'Sponsor account required.', 'khm-membership' ) ) );
		}

		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();

		$tier  = sanitize_key( (string) ( $params['tier']  ?? '' ) );
		$scope = sanitize_key( (string) ( $params['scope'] ?? 'site' ) );

		if ( ! in_array( $tier, ConnectTiering::TIERS, true ) ) {
			return rest_ensure_response( array( 'success' => false, 'message' => __( 'Invalid tier.', 'khm-membership' ) ) );
		}

		if ( ! in_array( $scope, array( 'site', 'portfolio' ), true ) ) {
			$scope = 'site';
		}

		$existing = get_user_meta( get_current_user_id(), 'khm_connect_subscription', true );
		$existing = is_array( $existing ) ? $existing : array();

		$now = current_time( 'mysql', true );

		$user_id      = get_current_user_id();
		$sponsor      = SponsorService::get_user_sponsor( $user_id );
		$sponsor_name = is_array( $sponsor ) ? ( (string) ( $sponsor['name'] ?? '' ) ) : '';
		$user         = get_userdata( $user_id );
		$user_email   = $user ? $user->user_email : '';

		// ── Attempt QuickBooks invoice creation ───────────────────────────────
		$qbo_invoice_id  = null;
		$qbo_invoice_url = null;
		$sub_status      = 'pending';

		try {
			$qbo = new QBOService();
			if ( $qbo->is_connected() ) {
				// Resolve price for description.
				$tier_config  = ConnectTiering::get_config();
				$tier_data    = $tier_config[ $tier ] ?? [];
				$unit_pence   = (int) ( $tier_data['unit_price_cents'] ?? 0 );
				$amount_gbp   = round( $unit_pence / 100, 2 );
				$currency     = strtoupper( (string) get_option( 'khm_connect_match_currency', 'gbp' ) );
				$description  = sprintf( 'Connect %s subscription (%s scope)', ucfirst( $tier ), $scope );

				$qb_customer_id = $qbo->find_or_create_customer(
					$user_email,
					$sponsor_name ?: $user_email
				);

				$invoice = $qbo->create_invoice(
					$qb_customer_id,
					$description,
					$amount_gbp,
					$currency,
					[
						'user_id'    => $user_id,
						'tier'       => $tier,
						'scope'      => $scope,
						'source'     => 'connect_subscription',
					]
				);

				$qbo_invoice_id  = $invoice['id'];
				$qbo_invoice_url = $invoice['deep_link'];
				$sub_status      = 'pending_invoice';
			}
		} catch ( \Throwable $e ) {
			// Non-fatal — fall back to admin-email path.
			error_log( '[KHM QBO] create_subscription invoice error: ' . $e->getMessage() );
		}

		$sub = array_merge( $existing, array(
			'tier'         => $tier,
			'scope'        => $scope,
			'status'       => $sub_status,
			'requested_at' => $now,
		) );

		if ( $qbo_invoice_id ) {
			$sub['qbo_invoice_id']  = $qbo_invoice_id;
			$sub['qbo_invoice_url'] = $qbo_invoice_url;
		}

		update_user_meta( $user_id, 'khm_connect_subscription', $sub );

		// ── Notify admin (always) ─────────────────────────────────────────────
		$invoice_line = $qbo_invoice_url
			? sprintf( "\n\nQuickBooks Invoice: %s", $qbo_invoice_url )
			: "\n\n(No QB invoice — please activate manually.)";

		wp_mail(
			get_option( 'admin_email' ),
			sprintf(
				/* translators: %s sponsor name */
				__( '[Connect] Subscription request: %s', 'khm-membership' ),
				$sponsor_name
			),
			sprintf(
				/* translators: 1: sponsor name, 2: tier, 3: scope, 4: date, 5: invoice line */
				__( 'Sponsor "%1$s" has requested a Connect subscription.\n\nTier: %2$s\nScope: %3$s\nRequested at: %4$s%5$s', 'khm-membership' ),
				$sponsor_name,
				$tier,
				$scope,
				$now,
				$invoice_line
			)
		);

		$message = $qbo_invoice_id
			? __( 'A QuickBooks invoice has been sent to your email. Your subscription will activate once payment is received.', 'khm-membership' )
			: __( 'Your Connect subscription request has been received. We will activate it within 1 business day.', 'khm-membership' );

		return rest_ensure_response( array(
			'success'      => true,
			'subscription' => $sub,
			'message'      => $message,
		) );
	}

	/**
	 * Create a Stripe Checkout Session for an annual site connection membership fee.
	 *
	 * POST /khm/v1/connect/subscription/checkout
	 * Body: { tier: string, scope: 'site'|'portfolio' }
	 *
	 * Returns { success: true, checkout_url: string } on success.
	 * Price IDs are configured via the khm_connect_subscription_price_ids option:
	 *   { 'premium_site' => 'price_xxx', 'premium_portfolio' => 'price_xxx', ... }
	 */
	public function create_subscription_checkout( WP_REST_Request $request ): WP_REST_Response {
		$sponsor_id = $this->resolve_sponsor_id();
		if ( is_wp_error( $sponsor_id ) ) {
			return rest_ensure_response( [ 'success' => false, 'message' => __( 'Sponsor account required.', 'khm-membership' ) ] );
		}

		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : [];

		$tier  = sanitize_key( (string) ( $params['tier']  ?? '' ) );
		$scope = sanitize_key( (string) ( $params['scope'] ?? 'site' ) );

		if ( ! in_array( $tier, ConnectTiering::TIERS, true ) ) {
			return rest_ensure_response( [ 'success' => false, 'message' => __( 'Invalid tier.', 'khm-membership' ) ] );
		}
		if ( ! in_array( $scope, [ 'site', 'portfolio' ], true ) ) {
			$scope = 'site';
		}

		// Resolve the Stripe annual subscription price ID.
		$price_map = get_option( 'khm_connect_subscription_price_ids', [] );
		$price_key = $tier . '_' . $scope;
		$price_id  = is_array( $price_map ) ? ( (string) ( $price_map[ $price_key ] ?? '' ) ) : '';

		if ( empty( $price_id ) ) {
			return rest_ensure_response( [
				'success' => false,
				'code'    => 'no_price_configured',
				'message' => __( 'Stripe price not yet configured for this plan. Please contact support.', 'khm-membership' ),
			] );
		}

		$stripe_secret = function_exists( 'khm_get_stripe_secret' )
			? (string) ( khm_get_stripe_secret( 'KH_STRIPE_SECRET_KEY' ) ?? '' )
			: '';

		if ( empty( $stripe_secret ) ) {
			return rest_ensure_response( [ 'success' => false, 'message' => __( 'Payment system not configured.', 'khm-membership' ) ] );
		}

		$user_id    = get_current_user_id();
		$user_email = wp_get_current_user()->user_email;
		$sponsor    = SponsorService::get_user_sponsor( $user_id );

		$success_url = apply_filters(
			'khm_connect_subscription_checkout_success_url',
			home_url( '/partner-portal/?connect_sub=success' ),
			$tier,
			$scope
		);
		$cancel_url = apply_filters(
			'khm_connect_subscription_checkout_cancel_url',
			home_url( '/partner-portal/?connect_sub=cancelled' ),
			$tier,
			$scope
		);

		try {
			\Stripe\Stripe::setApiKey( $stripe_secret );

			$session_params = [
				'mode'       => 'subscription',
				'line_items' => [
					[
						'price'    => $price_id,
						'quantity' => 1,
					],
				],
				'success_url'         => $success_url,
				'cancel_url'          => $cancel_url,
				'customer_email'      => $user_email,
				'metadata'            => [
					'purchase_type' => 'connect_subscription',
					'user_id'       => (string) $user_id,
					'sponsor_id'    => is_array( $sponsor ) ? (string) ( $sponsor['id'] ?? '' ) : '',
					'tier'          => $tier,
					'scope'         => $scope,
					'stripe_price_id' => $price_id,
				],
				'subscription_data'   => [
					'metadata' => [
						'purchase_type' => 'connect_subscription',
						'user_id'       => (string) $user_id,
						'tier'          => $tier,
						'scope'         => $scope,
					],
				],
			];

			$session_params = apply_filters( 'khm_connect_subscription_checkout_params', $session_params, $tier, $scope, $user_id );
			$session        = \Stripe\Checkout\Session::create( $session_params );

			if ( empty( $session->url ) ) {
				throw new \Exception( 'Checkout session created but missing URL' );
			}

			// Record pending state so UI can poll.
			$now = current_time( 'mysql', true );
			$sub = [
				'tier'               => $tier,
				'scope'              => $scope,
				'status'             => 'pending_stripe',
				'stripe_session_id'  => $session->id,
				'requested_at'       => $now,
			];
			update_user_meta( $user_id, 'khm_connect_subscription', $sub );

			return rest_ensure_response( [
				'success'      => true,
				'checkout_url' => $session->url,
			] );

		} catch ( \Exception $e ) {
			error_log( '[KHM Connect] Subscription checkout session failed: ' . $e->getMessage() );
			return rest_ensure_response( [ 'success' => false, 'message' => __( 'Unable to start checkout. Please try again.', 'khm-membership' ) ] );
		}
	}

	private function get_request_params( WP_REST_Request $request ): array {
		$params = $request->get_json_params();

		return is_array( $params ) ? $params : array();
	}

	private function validate_payload( array $params ) {
		$name = trim( (string) ( $params['name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'connect_provider_name_required', __( 'Provider name is required.', 'khm-membership' ), array( 'status' => 400 ) );
		}

		$status = (string) ( $params['status'] ?? 'active' );
		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			return new WP_Error( 'connect_provider_status_invalid', __( 'Invalid provider status.', 'khm-membership' ), array( 'status' => 400 ) );
		}

		return true;
	}

	private function format_provider( array $provider ): array {
		return array(
			'id'                   => (int) $provider['id'],
			'sponsor_id'           => (int) $provider['sponsor_id'],
			'blog_id'              => (int) ( $provider['blog_id'] ?? 0 ),
			'site_domain'          => (string) ( $provider['site_domain'] ?? '' ),
			'site_path'            => (string) ( $provider['site_path'] ?? '' ),
			'name'                 => (string) $provider['name'],
			'slug'                 => (string) $provider['slug'],
			'description'          => (string) $provider['description'],
			'website_url'          => (string) $provider['website_url'],
			'provider_type'        => (string) ( $provider['provider_type'] ?? '' ),
			'sweet_spot_summary'   => (string) ( $provider['sweet_spot_summary'] ?? '' ),
			'company_size_min'     => $provider['company_size_min'],
			'company_size_max'     => $provider['company_size_max'],
			'budget_min'           => $provider['budget_min'],
			'budget_max'           => $provider['budget_max'],
			'onboarding_days'      => $provider['onboarding_days'],
			'regions'              => array_values( (array) ( $provider['regions'] ?? array() ) ),
			'deployment_modes'     => array_values( (array) ( $provider['deployment_modes'] ?? array() ) ),
			'support_tiers'        => array_values( (array) ( $provider['support_tiers'] ?? array() ) ),
			'status'               => (string) $provider['status'],
			'commentary_enabled'   => ! empty( $provider['commentary_enabled'] ),
			'ad_targeting_enabled' => ! empty( $provider['ad_targeting_enabled'] ),
			'titles'               => array_values( (array) ( $provider['titles'] ?? array() ) ),
			'comparison_fields'    => (array) ( $provider['comparison_fields'] ?? array() ),
			'match_rules'          => (array) ( $provider['match_rules'] ?? array() ),
		);
	}
}