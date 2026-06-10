<?php

namespace Cookiez\Modules\Cookie\Rest;

use Cookiez\Classes\Rest\Sanitizer;
use Cookiez\Modules\Cookie\Classes\Route_Base;
use Cookiez\Modules\Cookie\Components\Cookie;

use RuntimeException;
use Throwable;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Delete_Cookie
 * REST endpoint for deleting a cookie
 */
class Delete_Cookie extends Route_Base {
	public string $path = '(?P<id>\d+)';

	public function get_methods(): array {
		return [ 'DELETE' ];
	}

	public function get_name(): string {
		return 'delete-cookie';
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

			$cookie_id = (int) $this->params['id'];

			try {
				( new Cookie() )->delete( $cookie_id );
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
				'message'   => esc_html__( 'Cookie deleted successfully', 'cookiez' ),
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
