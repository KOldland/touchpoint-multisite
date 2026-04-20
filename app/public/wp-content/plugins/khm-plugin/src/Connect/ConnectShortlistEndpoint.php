<?php

namespace KHM\Connect;

use WP_Error;

defined( 'ABSPATH' ) || exit;

class ConnectShortlistEndpoint {

	private ConnectProviderRepository $providers;

	private ConnectShortlistService $shortlist_service;

	public function __construct( ?ConnectProviderRepository $providers = null, ?ConnectShortlistService $shortlist_service = null ) {
		$this->providers         = $providers ?? new ConnectProviderRepository();
		$this->shortlist_service = $shortlist_service ?? new ConnectShortlistService();
	}

	public function register(): void {
		add_action(
			'rest_api_init',
			function (): void {
				register_rest_route(
					'khm/v1',
					'/connect/shortlist',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'handle' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}

	public function handle( \WP_REST_Request $request ) {
		$params        = $request->get_json_params();
		$params        = is_array( $params ) ? $params : array();
		$site_guard    = $this->validate_site_context( $params );

		if ( is_wp_error( $site_guard ) ) {
			return $site_guard;
		}

		$site_id       = (int) $site_guard;
		$title_context = $this->normalize_slug( (string) ( $params['title_context'] ?? '' ) );
		$criteria      = isset( $params['criteria'] ) && is_array( $params['criteria'] ) ? $params['criteria'] : array();
		$limit         = isset( $params['limit'] ) ? (int) $params['limit'] : ConnectShortlistService::DEFAULT_LIMIT;

		if ( empty( $criteria ) ) {
			return new WP_Error( 'connect_missing_criteria', __( 'At least one criteria group is required.', 'khm-membership' ), array( 'status' => 400 ) );
		}

		$providers = $this->providers->list_active( $title_context );
		$results   = $this->shortlist_service->shortlist( $providers, $criteria, $title_context, min( 10, max( 1, $limit ) ) );

		return rest_ensure_response(
			array(
				'site_id'       => $site_id,
				'title_context' => $title_context,
				'count'         => count( $results ),
				'providers'     => array_values( $results ),
			)
		);
	}

	private function validate_site_context( array $params ) {
		$current_site_id   = max( 1, (int) apply_filters( 'khm_connect_current_blog_id', function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1 ) );
		$requested_site_id = isset( $params['site_id'] ) ? (int) $params['site_id'] : 0;

		if ( $requested_site_id > 0 && $requested_site_id !== $current_site_id ) {
			return new WP_Error(
				'connect_site_context_mismatch',
				__( 'Requested site context does not match the active site.', 'khm-membership' ),
				array(
					'status' => 403,
					'site_id' => $current_site_id,
				)
			);
		}

		return $current_site_id;
	}

	private function normalize_slug( string $value ): string {
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( (string) $value, '-' );
	}
}