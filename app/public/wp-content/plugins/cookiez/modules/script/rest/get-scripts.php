<?php

namespace Cookiez\Modules\Script\Rest;

use Cookiez\Modules\Script\Classes\Route_Base;
use Cookiez\Modules\Script\Components\Script;

use Throwable;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Get_Scripts
 * REST endpoint for retrieving all managed scripts
 */
class Get_Scripts extends Route_Base {
	public string $path = '';

	public function get_methods(): array {
		return [ 'GET' ];
	}

	public function get_name(): string {
		return 'get-scripts';
	}

	public function GET(): WP_REST_Response {
		try {
			$error = $this->verify_capability();

			if ( $error ) {
				return $error;
			}

			$scripts = ( new Script() )->list();

			$formatted = array_map( fn( $dto ) => $dto->to_array(), $scripts );

			return $this->respond_success_json( [
				'scripts' => $formatted,
			] );

		} catch ( Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code' => 'internal_server_error',
			], 500 );
		}
	}
}
