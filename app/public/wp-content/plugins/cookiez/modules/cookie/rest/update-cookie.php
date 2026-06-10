<?php

namespace Cookiez\Modules\Cookie\Rest;

use Cookiez\Classes\Rest\{
	Sanitizer,
	Validator,
};
use Cookiez\Classes\Enums\Cookie_Category;
use Cookiez\Classes\Utils;
use Cookiez\Modules\Cookie\Classes\Route_Base;
use Cookiez\Modules\Cookie\Classes\Dto\Cookie_DTO;
use Cookiez\Modules\Cookie\Components\Cookie;

use RuntimeException;
use Throwable;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Update_Cookie
 * REST endpoint for updating a cookie
 */
class Update_Cookie extends Route_Base {
	public string $path = '(?P<id>\d+)';

	public function get_methods(): array {
		return [ 'PUT', 'PATCH' ];
	}

	public function get_name(): string {
		return 'update-cookie';
	}

	protected function sanitize_fields(): array {
		return [
			'id' => Sanitizer::absint(),
			'name' => Sanitizer::text(),
			'domain' => Sanitizer::text(),
			'duration' => Sanitizer::int(),
			'category' => Sanitizer::text(),
			'description' => Sanitizer::textarea(),
		];
	}

	protected function validate_fields(): array {
		return [
			'id' => ( new Validator() )
				->number( [
					'min' => 1,
					'message' => esc_html__( 'Expected a positive number', 'cookiez' ),
				] ),
			'name' => ( new Validator() )
				->fn(
					fn( $value ) => Utils::is_valid_cookie_name( $value ),
					[ 'message' => esc_html__( 'Cookie name has invalid characters', 'cookiez' ) ]
				)
				->nullable(),
			'domain' => ( new Validator() )
				->fn(
					fn( $value ) => Utils::is_valid_cookie_domain( $value ),
					[ 'message' => esc_html__( 'Expected a valid domain', 'cookiez' ) ]
				)
				->nullable(),
			'duration' => ( new Validator() )
				->number( [
					'min' => 1,
					'message' => esc_html__( 'Expected a positive number', 'cookiez' ),
				] )
				->nullable(),
			'category' => ( new Validator() )
				->enum(
					Cookie_Category::class,
					[ 'message' => esc_html__( 'Expected a valid category value', 'cookiez' ) ]
				)
				->nullable(),
			'description' => ( new Validator() )
				->string( [
					'message' => esc_html__( 'Expected a string', 'cookiez' ),
				] )
				->nullable(),
		];
	}

	public function PUT(): WP_REST_Response {
		return $this->dispatch( 'replace' );
	}

	public function PATCH(): WP_REST_Response {
		return $this->dispatch( 'patch' );
	}

	private function dispatch( string $method ): WP_REST_Response {
		try {
			$error = $this->verify_capability();

			if ( $error ) {
				return $error;
			}

			$validation_errors = $this->validate( $this->params );

			if ( ! empty( $validation_errors ) ) {
				return $this->respond_validation_error( $validation_errors );
			}

			$cookie_id = (int) $this->params['id'];
			$dto = Cookie_DTO::from_rest_api( $this->params );

			try {
				( new Cookie() )->{ $method }( $cookie_id, $dto );
			} catch ( RuntimeException $e ) {
				if ( 'cookie_not_found' === $e->getMessage() ) {
					return $this->respond_error_json( [
						'message' => esc_html__( 'Cookie not found', 'cookiez' ),
						'code' => 'cookie_not_found',
					], 404 );
				}

				throw $e;
			}

			return $this->respond_success_json( [
				'message' => esc_html__( 'Cookie updated successfully', 'cookiez' ),
				'cookie_id' => $cookie_id,
			] );

		} catch ( Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code' => 'internal_server_error',
			], 500 );
		}
	}
}
