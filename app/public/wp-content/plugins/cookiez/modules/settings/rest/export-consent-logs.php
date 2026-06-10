<?php

namespace Cookiez\Modules\Settings\Rest;

use Cookiez\Classes\Utils;
use Cookiez\Modules\Settings\Classes\Route_Base;
use Cookiez\Modules\Settings\Classes\Settings;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Export_Consent_Logs extends Route_Base {
	public string $path = 'export-consent-logs';

	public function get_methods(): array {
		return [ 'GET' ];
	}

	public function get_name(): string {
		return 'export-consent-logs';
	}

	public function GET( WP_REST_Request $request ): WP_REST_Response {
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

			$query_params = [];

			if ( $request->get_param( 'startDate' ) ) {
				$query_params['startDate'] = sanitize_text_field( $request->get_param( 'startDate' ) );
			}

			if ( $request->get_param( 'endDate' ) ) {
				$query_params['endDate'] = sanitize_text_field( $request->get_param( 'endDate' ) );
			}

			if ( $request->get_param( 'search' ) ) {
				$query_params['search'] = sanitize_text_field( $request->get_param( 'search' ) );
			}

			if ( $request->get_param( 'consentType' ) ) {
				$query_params['consentType'] = sanitize_text_field( $request->get_param( 'consentType' ) );
			}

			$endpoint    = 'consent-logs/export?' . http_build_query( $query_params );
			$body_params = [ 'api_key' => $plan_data->public_api_key ];

			$response = $client->make_request( 'POST', $endpoint, $body_params, [], true );

			if ( is_wp_error( $response ) ) {
				return $this->respond_error_json( [
					'message' => $response->get_error_message(),
					'code'    => $response->get_error_code(),
				], 502 );
			}

			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=consent-logs-export.csv' );
			echo $response; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw CSV pass-through from service.
			exit;
		} catch ( Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code'    => 'internal_server_error',
			], 500 );
		}
	}
}
