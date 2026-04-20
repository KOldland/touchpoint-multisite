<?php

namespace KHM\Connect;

use WP_Error;

defined( 'ABSPATH' ) || exit;

class ConnectComparisonEndpoint {

	private ConnectProviderRepository $providers;

	private ConnectComparisonService $comparison_service;

	public function __construct( ?ConnectProviderRepository $providers = null, ?ConnectComparisonService $comparison_service = null ) {
		$this->providers = $providers ?? new ConnectProviderRepository();
		$this->comparison_service = $comparison_service ?? new ConnectComparisonService();
	}

	public function register(): void {
		add_action(
			'rest_api_init',
			function (): void {
				register_rest_route(
					'khm/v1',
					'/connect/compare',
					array(
						'methods' => 'POST',
						'callback' => array( $this, 'handle' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}

	public function handle( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		$site_guard = $this->validate_site_context( $params );

		if ( is_wp_error( $site_guard ) ) {
			return $site_guard;
		}

		$site_id = (int) $site_guard;
		$title_context = $this->normalize_slug( (string) ( $params['title_context'] ?? '' ) );
		$provider_ids = isset( $params['provider_ids'] ) && is_array( $params['provider_ids'] ) ? array_values( array_filter( array_map( 'intval', $params['provider_ids'] ) ) ) : array();
		$fields = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array();

		if ( ! $this->comparison_service->validate_provider_count( $provider_ids ) ) {
			return new WP_Error( 'connect_invalid_provider_count', __( 'Compare between 2 and 5 providers.', 'khm-membership' ), array( 'status' => 400 ) );
		}

		$providers = $this->filter_requested_providers( $this->providers->list_active( $title_context ), $provider_ids );
		if ( count( $providers ) !== count( array_unique( $provider_ids ) ) ) {
			return new WP_Error( 'connect_provider_not_available', __( 'One or more providers could not be compared in this context.', 'khm-membership' ), array( 'status' => 404 ) );
		}

		$matrix = $this->comparison_service->build_matrix( $providers, $fields );

		return rest_ensure_response(
			array(
				'site_id' => $site_id,
				'title_context' => $title_context,
				'comparison' => $matrix,
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

	private function filter_requested_providers( array $providers, array $provider_ids ): array {
		$index = array();
		foreach ( $providers as $provider ) {
			$index[ (int) ( $provider['id'] ?? 0 ) ] = $provider;
		}

		$selected = array();
		foreach ( array_values( array_unique( $provider_ids ) ) as $provider_id ) {
			if ( isset( $index[ $provider_id ] ) ) {
				$selected[] = $index[ $provider_id ];
			}
		}

		return $selected;
	}

	private function normalize_slug( string $value ): string {
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( (string) $value, '-' );
	}
}