<?php

namespace Cookiez\Modules\Settings\Rest;

use Cookiez\Modules\Settings\Classes\Route_Base;
use Cookiez\Modules\Settings\Module as Settings;
use Throwable;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Get_Settings extends Route_Base {
	public string $path = 'get-settings';

	public function get_methods(): array {
		return [ 'GET' ];
	}

	public function get_name(): string {
		return 'get-settings';
	}

	/**
	 * @return WP_REST_Response
	 */
	public function GET() {
		try {
			$data = Settings::get_plugin_settings();

			return $this->respond_success_json( $data );
		} catch ( Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code'    => 'internal_server_error',
			], 500 );
		}
	}
}
