<?php

namespace Cookiez\Modules\Elementor\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cookiez\Classes\Rest\Route;
use WP_REST_Request;

class Route_Base extends Route {

	protected bool $override = false;
	protected $auth = true;
	protected string $path = '';

	public function get_methods(): array {
		return [];
	}

	public function get_endpoint(): string {
		return 'elementor/' . $this->get_path();
	}

	public function get_path(): string {
		return $this->path;
	}

	public function get_name(): string {
		return '';
	}

	public function get_permission_callback( WP_REST_Request $request ): bool {
		$valid = $this->permission_callback( $request );

		return $valid && user_can( $this->current_user_id, 'manage_options' );
	}
}
