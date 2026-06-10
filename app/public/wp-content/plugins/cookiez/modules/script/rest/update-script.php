<?php

namespace Cookiez\Modules\Script\Rest;

use Cookiez\Classes\Rest\{
	Sanitizer,
	Validator,
};
use Cookiez\Modules\Script\Classes\{
	Enums\Script_Blocking_Mode,
	Route_Base,
};
use Cookiez\Modules\Script\Classes\Dto\Script_DTO;
use Cookiez\Modules\Script\Components\Script;
use Cookiez\Classes\Enums\Cookie_Category;

use RuntimeException;
use Throwable;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Update_Script
 * REST endpoint for updating a managed script
 */
class Update_Script extends Route_Base {
	public string $path = '(?P<id>\d+)';

	public function get_methods(): array {
		return [ 'PUT', 'PATCH' ];
	}

	public function get_name(): string {
		return 'update-script';
	}

	protected function sanitize_fields(): array {
		return [
			'id' => Sanitizer::absint(),
			'cookie_id' => Sanitizer::absint(),
			'name' => Sanitizer::text(),
			'value' => Sanitizer::text(),
			'description' => Sanitizer::textarea(),
			'category' => Sanitizer::text(),
			'blocking_mode' => Sanitizer::text(),
		];
	}

	protected function validate_fields(): array {
		return [
			'id' => ( new Validator() )
				->number( [
					'min' => 1,
					'message' => esc_html__( 'Expected a positive number', 'cookiez' ),
				] ),
			'cookie_id' => ( new Validator() )
				->number( [
					'min' => 1,
					'message' => esc_html__( 'Expected a positive number', 'cookiez' ),
				] )
				->nullable(),
			'name' => ( new Validator() )
				->string( [
					'message' => esc_html__( 'Expected a string', 'cookiez' ),
				] )
				->nullable(),
			'value' => ( new Validator() )
				->string( [
					'message' => esc_html__( 'Expected a string', 'cookiez' ),
				] )
				->nullable(),
			'description' => ( new Validator() )
				->string( [
					'message' => esc_html__( 'Expected a string', 'cookiez' ),
				] )
				->nullable(),
			'category' => ( new Validator() )
				->enum(
					Cookie_Category::class,
					[ 'message' => esc_html__( 'Expected a valid category value', 'cookiez' ) ]
				)
				->nullable(),
			'blocking_mode' => ( new Validator() )
				->enum(
					Script_Blocking_Mode::class,
					[ 'message' => esc_html__( 'Expected a valid blocking mode value', 'cookiez' ) ]
				)
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

			$script_id = (int) $this->params['id'];
			$dto = Script_DTO::from_rest_api( $this->params );

			try {
				( new Script() )->{ $method }( $script_id, $dto );
			} catch ( RuntimeException $e ) {
				if ( 'script_not_found' === $e->getMessage() ) {
					return $this->respond_error_json( [
						'message' => esc_html__( 'Script not found', 'cookiez' ),
						'code' => 'script_not_found',
					], 404 );
				}

				if ( 'script_duplicate' === $e->getMessage() ) {
					return $this->respond_validation_error( [
						'value' => esc_html__( 'A script with this pattern already exists', 'cookiez' ),
					] );
				}

				throw $e;
			}

			return $this->respond_success_json( [
				'message' => esc_html__( 'Script updated successfully', 'cookiez' ),
				'script_id' => $script_id,
			] );

		} catch ( Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code' => 'internal_server_error',
			], 500 );
		}
	}
}
