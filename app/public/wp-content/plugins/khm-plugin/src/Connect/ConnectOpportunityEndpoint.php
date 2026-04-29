<?php

namespace KHM\Connect;

use KHM\Services\SponsorService;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

class ConnectOpportunityEndpoint {

	private ConnectOpportunityRepository $opportunities;

	private ConnectProviderRepository $providers;

	public function __construct( ?ConnectOpportunityRepository $opportunities = null, ?ConnectProviderRepository $providers = null ) {
		$this->opportunities = $opportunities ?? new ConnectOpportunityRepository();
		$this->providers     = $providers ?? new ConnectProviderRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'khm/v1',
			'/connect/opportunities/mine',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_mine' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/opportunities/mine/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_mine' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/opportunities/mine/(?P<id>\d+)/accept',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'accept_mine' ),
				'permission_callback' => array( $this, 'check_permission' ),
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

		$rows = $this->opportunities->list_inbox_for_sponsor( (int) $sponsor_id );

		return rest_ensure_response(
			array(
				'success'       => true,
				'opportunities' => array_values( array_map( array( $this, 'format_anonymized_opportunity' ), $rows ) ),
			)
		);
	}

	public function get_mine( WP_REST_Request $request ) {
		$sponsor_id = $this->resolve_sponsor_id();
		if ( is_wp_error( $sponsor_id ) ) {
			return $sponsor_id;
		}

		$opportunity = $this->opportunities->get_inbox_for_sponsor( (int) $request->get_param( 'id' ), (int) $sponsor_id );
		if ( ! is_array( $opportunity ) ) {
			return new WP_Error( 'connect_opportunity_not_found', __( 'Opportunity not found.', 'khm-membership' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'opportunity' => $this->format_anonymized_opportunity( $opportunity ),
			)
		);
	}

	public function accept_mine( WP_REST_Request $request ) {
		$sponsor_id = $this->resolve_sponsor_id();
		if ( is_wp_error( $sponsor_id ) ) {
			return $sponsor_id;
		}

		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();

		$provider_id = isset( $params['provider_id'] ) ? (int) $params['provider_id'] : 0;
		if ( $provider_id <= 0 ) {
			return new WP_Error( 'connect_provider_required', __( 'Provider is required to accept an opportunity.', 'khm-membership' ), array( 'status' => 400 ) );
		}

		$provider = $this->providers->get_by_id( $provider_id );
		if ( ! is_array( $provider ) || (int) $provider['sponsor_id'] !== (int) $sponsor_id ) {
			return new WP_Error( 'connect_provider_forbidden', __( 'You cannot accept this opportunity with that provider.', 'khm-membership' ), array( 'status' => 403 ) );
		}

		$opportunity_id = (int) $request->get_param( 'id' );
		$opportunity = $this->opportunities->get_inbox_for_sponsor( $opportunity_id, (int) $sponsor_id );
		if ( ! is_array( $opportunity ) ) {
			return new WP_Error( 'connect_opportunity_not_found', __( 'Opportunity not found.', 'khm-membership' ), array( 'status' => 404 ) );
		}

		if ( ! in_array( (string) ( $opportunity['opportunity_status'] ?? '' ), array( 'detected', 'offered', 'sponsor_accepted' ), true ) ) {
			return new WP_Error( 'connect_opportunity_locked', __( 'Opportunity can no longer be accepted.', 'khm-membership' ), array( 'status' => 409 ) );
		}

		$updated = $this->opportunities->mark_sponsor_acceptance( $opportunity_id, (int) $sponsor_id, $provider_id );
		if ( ! $updated ) {
			return new WP_Error( 'connect_opportunity_accept_failed', __( 'Unable to accept opportunity.', 'khm-membership' ), array( 'status' => 500 ) );
		}

		$fresh = $this->opportunities->get_inbox_for_sponsor( $opportunity_id, (int) $sponsor_id );

		return rest_ensure_response(
			array(
				'success'     => true,
				'opportunity' => is_array( $fresh ) ? $this->format_anonymized_opportunity( $fresh ) : null,
			)
		);
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

	private function format_anonymized_opportunity( array $row ): array {
		return array(
			'id'                 => (int) ( $row['id'] ?? 0 ),
			'commercial_tier'    => (string) ( $row['commercial_tier'] ?? '' ),
			'internal_stage'     => (string) ( $row['internal_stage'] ?? '' ),
			'person_score'       => (float) ( $row['person_score'] ?? 0 ),
			'opportunity_status' => (string) ( $row['opportunity_status'] ?? 'detected' ),
			'pricing_model'      => (string) ( $row['pricing_model'] ?? 'cpl' ),
			'unit_price_cents'   => (int) ( $row['unit_price_cents'] ?? 0 ),
			'commission_eligible'=> (bool) ( (int) ( $row['commission_eligible'] ?? 0 ) ),
			'provider_id'        => (int) ( $row['provider_id'] ?? 0 ),
			'created_at'         => (string) ( $row['created_at'] ?? '' ),
		);
	}
}
