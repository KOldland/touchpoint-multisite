<?php

namespace Cookiez\Modules\Scanner\Classes;

use Cookiez\Classes\Rest\Route;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Route_Base
 * Base class for cookie REST endpoints
 */
class Route_Base extends Route {
	protected bool $override = false;
	protected $auth = true;
	protected string $path = '';

	public function get_methods(): array {
		return [];
	}

	public function get_endpoint(): string {
		return 'scan/' . $this->get_path();
	}

	public function get_path(): string {
		return $this->path;
	}

	public function get_name(): string {
		return '';
	}
}
