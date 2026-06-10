<?php

namespace Cookiez\Modules\Cookie\Rest;

use Cookiez\Classes\Rest\{
	Sanitizer,
	Validator,
};
use Cookiez\Modules\Cookie\Classes\Route_Base;
use Cookiez\Modules\Cookie\Components\Cookie;

use Throwable;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Get_Cookies
 * REST endpoint for retrieving all cookies
 */
class Get_Cookies extends Route_Base {
	public string $path = '';

	public function get_methods(): array {
		return [ 'GET' ];
	}

	public function get_name(): string {
		return 'get-cookies';
	}

	protected function sanitize_fields(): array {
		return [
			'limit' => Sanitizer::int(),
			'offset' => Sanitizer::int(),
		];
	}

	protected function validate_fields(): array {
		return [
			'limit' => ( new Validator() )
				->number( [
					'min' => 1,
					'message' => esc_html__( 'Expected a positive number', 'cookiez' ),
				] )
				->nullable(),
			'offset' => ( new Validator() )
				->number( [
					'min' => 0,
					'message' => esc_html__( 'Expected a positive number', 'cookiez' ),
				] )
				->nullable(),
		];
	}

	public function GET(): WP_REST_Response {
		try {
			$error = $this->verify_capability();

			if ( $error ) {
				return $error;
			}

			$validation_errors = $this->validate( $this->params );

			if ( ! empty( $validation_errors ) ) {
				return $this->respond_validation_error( $validation_errors );
			}

			$cookies = ( new Cookie() )->list( [
				'limit' => $this->params['limit'] ?? null,
				'offset' => $this->params['offset'] ?? null,
			] );

			$formatted = array_map( fn( $dto ) => $dto->to_array(), $cookies );

			return $this->respond_success_json( [
				'cookies' => $formatted,
			] );

		} catch ( Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code' => 'internal_server_error',
			], 500 );
		}
	}
}
