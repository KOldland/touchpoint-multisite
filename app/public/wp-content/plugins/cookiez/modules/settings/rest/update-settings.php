<?php

namespace Cookiez\Modules\Settings\Rest;

use Cookiez\Modules\Settings\Classes\Route_Base;
use Cookiez\Modules\Settings\Classes\Sanitize_Content;
use Cookiez\Modules\Settings\Classes\Sanitize_Design;
use Cookiez\Modules\Settings\Classes\Sanitize_Settings;
use Cookiez\Modules\Settings\Classes\Settings;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Update_Settings extends Route_Base {
	public string $path = 'update-settings';

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function get_name(): string {
		return 'update-settings';
	}

	/**
	 * @return WP_REST_Response
	 */
	public function POST( WP_REST_Request $request ) {
		try {
			$body = $request->get_json_params();
			if ( ! is_array( $body ) ) {
				return $this->respond_error_json( [
					'message' => __( 'Invalid request body.', 'cookiez' ),
					'code'    => 'invalid_body',
				], 400 );
			}

			$response = [];

			$has_content = isset( $body['languages'] ) || isset( $body['disabledLanguages'] ) || isset( $body['content'] );
			if ( $has_content ) {
				$sanitized_content = Sanitize_Content::sanitize( $body );
				Settings::set( Settings::COOKIEZ_CONTENT, $sanitized_content );
				$response = $sanitized_content;
			} else {
				$existing = Settings::get( Settings::COOKIEZ_SETTINGS );
				$merged = array_merge(
					is_array( $existing ) ? $existing : [],
					Sanitize_Design::sanitize( $body ),
					Sanitize_Settings::sanitize( $body ),
				);
				Settings::set( Settings::COOKIEZ_SETTINGS, $merged );
				$response = $merged;
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
