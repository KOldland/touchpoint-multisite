<?php

namespace Cookiez\Modules\Reviews\Rest;

use Cookiez\Classes\Rest\{
	Sanitizer,
	Validator,
};
use Cookiez\Modules\Connect\Classes\Config;
use Cookiez\Modules\Reviews\Classes\Feedback_Client;
use Cookiez\Modules\Reviews\Classes\Route_Base;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class Feedback extends Route_Base {
	public string $path = 'review';

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function get_name(): string {
		return 'feedback';
	}

	protected function sanitize_fields(): array {
		return [
			'feedback' => Sanitizer::textarea(),
			'rating'   => Sanitizer::absint(),
		];
	}

	protected function validate_fields(): array {
		return [
			'rating' => ( new Validator() )->number( [
				'min'     => 1,
				'max'     => 5,
				'message' => esc_html__( 'Invalid rating.', 'cookiez' ),
			] ),
			'feedback' => ( new Validator() )
				->string()
				->nullable(),
		];
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function POST( WP_REST_Request $request ) {
		try {
			$error = $this->verify_capability();

			if ( $error ) {
				return $error;
			}

			$errors = $this->validate( $this->params );

			if ( ! empty( $errors ) ) {
				return $this->respond_validation_error( $errors );
			}

			$payload = [
				'feedback' => $this->params['feedback'] ?? '',
				'rating'   => $this->params['rating'],
				'app_name' => Config::APP_NAME,
			];

			$response = Feedback_Client::post_feedback( $payload );

			return $this->respond_success_json( $response );

		} catch ( Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code' => 'internal_server_error',
			], 500 );
		}
	}
}
