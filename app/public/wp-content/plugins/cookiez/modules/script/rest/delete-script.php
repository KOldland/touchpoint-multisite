<?php

namespace Cookiez\Modules\Script\Rest;

use Cookiez\Classes\Rest\Sanitizer;
use Cookiez\Modules\Script\Classes\Route_Base;
use Cookiez\Modules\Script\Components\Script;

use RuntimeException;
use Throwable;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Delete_Script
 * REST endpoint for deleting a managed script
 */
class Delete_Script extends Route_Base {
	public string $path = '(?P<id>\d+)';

	public function get_methods(): array {
		return [ 'DELETE' ];
	}

	public function get_name(): string {
		return 'delete-script';
	}

	protected function sanitize_fields(): array {
		return [
			'id' => Sanitizer::absint(),
		];
	}

	public function DELETE(): WP_REST_Response {
		try {
			$error = $this->verify_capability();

			if ( $error ) {
				return $error;
			}

			$script_id = (int) $this->params['id'];

			try {
				( new Script() )->delete( $script_id );
			} catch ( RuntimeException $e ) {
				if ( 'script_not_found' === $e->getMessage() ) {
					return $this->respond_error_json( [
						'message' => esc_html__( 'Script not found', 'cookiez' ),
						'code' => 'script_not_found',
					], 404 );
				}

				throw $e;
			}

			return $this->respond_success_json( [
				'message' => esc_html__( 'Script deleted successfully', 'cookiez' ),
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
