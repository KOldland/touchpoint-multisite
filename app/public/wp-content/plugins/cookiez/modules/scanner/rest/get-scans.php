<?php

namespace Cookiez\Modules\Scanner\Rest;

use Cookiez\Classes\Rest\{
	Sanitizer,
	Validator,
};
use Cookiez\Modules\Scanner\Classes\Route_Base;
use Cookiez\Modules\Scanner\Classes\Dto\Scan_DTO;
use Cookiez\Modules\Scanner\Database\Scan_Entry;

use Throwable;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Get_Scans
 * REST endpoint for retrieving all scans
 */
class Get_Scans extends Route_Base {
	public string $path = '';

	public function get_methods(): array {
		return [ 'GET' ];
	}

	public function get_name(): string {
		return 'get-scans';
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

	/**
	 * Returns all scans.
	 *
	 * @return WP_REST_Response
	 */
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

			$scans = Scan_Entry::find_many(
				[],
				$this->params['limit'] ?? null,
				$this->params['offset'] ?? null
			);

			$formatted_scans = array_map( function( $scan_data ) {
				return Scan_DTO::from_entry( $scan_data )->to_array();
			}, $scans );

			return $this->respond_success_json( [
				'scans' => $formatted_scans,
			] );
		} catch ( Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code' => 'internal_server_error',
			], 500 );
		}
	}
}
