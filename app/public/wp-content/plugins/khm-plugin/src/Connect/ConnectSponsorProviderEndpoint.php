<?php

namespace KHM\Connect;

use KHM\Services\SponsorService;
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
		$sub = array_merge( $existing, array(
			'tier'         => $tier,
			'scope'        => $scope,
			'status'       => 'pending',
			'requested_at' => $now,
		) );

		update_user_meta( get_current_user_id(), 'khm_connect_subscription', $sub );

		// Notify admin.
		$sponsor = SponsorService::get_user_sponsor( get_current_user_id() );
		$sponsor_name = is_array( $sponsor ) ? ( (string) ( $sponsor['name'] ?? '' ) ) : '';
		wp_mail(
			get_option( 'admin_email' ),
			sprintf(
				/* translators: %s sponsor name */
				__( '[Connect] Subscription request: %s', 'khm-membership' ),
				$sponsor_name
			),
			sprintf(
				/* translators: 1: sponsor name, 2: tier, 3: scope */
				__( 'Sponsor "%1$s" has requested a Connect subscription.\n\nTier: %2$s\nScope: %3$s\nRequested at: %4$s\n\nPlease activate in the admin panel.', 'khm-membership' ),
				$sponsor_name,
				$tier,
				$scope,
				$now
			)
		);

		return rest_ensure_response( array(
			'success'      => true,
			'subscription' => $sub,
			'message'      => __( 'Your Connect subscription request has been received. We will activate it within 1 business day.', 'khm-membership' ),
		) );
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