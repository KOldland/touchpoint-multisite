<?php

namespace Cookiez\Modules\Script\Rest;

use Cookiez\Classes\Rest\{
	Sanitizer,
	Validator,
};
use Cookiez\Modules\Script\Classes\{
	Enums\Script_Blocking_Mode,
	Enums\Script_Type,
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
 * Class Create_Script
 * REST endpoint for creating a managed script
 */
class Create_Script extends Route_Base {
	public string $path = '';

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function get_name(): string {
		return 'create-script';
	}

	protected function sanitize_fields(): array {
		return [
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
				] ),
			'description' => ( new Validator() )
				->string( [
					'message' => esc_html__( 'Expected a string', 'cookiez' ),
				] )
				->nullable(),
			'category' => ( new Validator() )
				->enum(
					Cookie_Category::class,
					[ 'message' => esc_html__( 'Expected a valid category value', 'cookiez' ) ]
				),
			'blocking_mode' => ( new Validator() )
				->enum(
					Script_Blocking_Mode::class,
					[ 'message' => esc_html__( 'Expected a valid blocking mode value', 'cookiez' ) ]
				),
		];
	}

	public function POST(): WP_REST_Response {
		try {
			$error = $this->verify_capability();

			if ( $error ) {
				return $error;
			}

			$validation_errors = $this->validate( $this->params );

			if ( ! empty( $validation_errors ) ) {
				return $this->respond_validation_error( $validation_errors );
			}

			$dto = Script_DTO::from_rest_api( $this->params );
			$dto->type = Script_Type::INLINE;

			try {
				$created = ( new Script() )->create( $dto );
			} catch ( RuntimeException $e ) {
				if ( 'script_duplicate' === $e->getMessage() ) {
					return $this->respond_validation_error( [
						'value' => esc_html__( 'A script with this pattern already exists', 'cookiez' ),
					] );
				}

				throw $e;
			}

			return $this->respond_success_json( [
				'message' => esc_html__( 'Script created successfully', 'cookiez' ),
				'script_id' => $created->id,
			], 201 );

		} catch ( Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code' => 'internal_server_error',
			], 500 );
		}
	}
}
