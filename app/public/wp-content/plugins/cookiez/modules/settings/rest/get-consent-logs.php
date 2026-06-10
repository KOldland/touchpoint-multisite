<?php

namespace Cookiez\Modules\Settings\Rest;

use Cookiez\Classes\Utils;
use Cookiez\Modules\Settings\Classes\{
	Route_Base,
	Settings,
};

use Throwable;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Get_Consent_Logs extends Route_Base {
	public string $path = 'get-consent-logs';

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function get_name(): string {
		return 'get-consent-logs';
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function POST( WP_REST_Request $request ): WP_REST_Response {
		try {
			$client = Utils::get_api_client();

			if ( ! $client ) {
				return $this->respond_error_json( [
					'message' => 'API client not available.',
					'code'    => 'client_unavailable',
				], 503 );
			}

			$plan_data = get_option( Settings::PLAN_DATA );

			if ( empty( $plan_data->public_api_key ) ) {
				return $this->respond_error_json( [
					'message' => 'Public API key not available.',
					'code'    => 'missing_api_key',
				], 400 );
			}

			$body = $request->get_json_params();

			$query_params = [
				'page'  => absint( $body['page'] ?? 1 ),
				'limit' => absint( $body['limit'] ?? 20 ),
			];

			if ( ! empty( $body['startDate'] ) ) {
				$query_params['startDate'] = sanitize_text_field( $body['startDate'] );
			}

			if ( ! empty( $body['endDate'] ) ) {
				$query_params['endDate'] = sanitize_text_field( $body['endDate'] );
			}

			if ( ! empty( $body['search'] ) ) {
				$query_params['search'] = sanitize_text_field( $body['search'] );
			}

			if ( ! empty( $body['consentType'] ) ) {
				$query_params['consentType'] = sanitize_text_field( $body['consentType'] );
			}

			$endpoint    = 'consent-logs/list?' . http_build_query( $query_params );
			$body_params = [ 'api_key' => $plan_data->public_api_key ];

			$response = $client->make_request( 'POST', $endpoint, $body_params, [], true );

			if ( is_wp_error( $response ) ) {
				return $this->respond_error_json( [
					'message' => $response->get_error_message(),
					'code'    => $response->get_error_code(),
				], 502 );
			}

			return $this->respond_success_json( $response );
		} catch ( Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code'    => 'internal_server_error',
			], 500 );
		}
	}
}
