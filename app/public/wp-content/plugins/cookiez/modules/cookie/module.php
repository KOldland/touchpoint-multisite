<?php

namespace Cookiez\Modules\Cookie;

use Cookiez\Classes\Module_Base;
use Cookiez\Modules\Cookie\Database\Cookie_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module `Cookie`
 * Manages cookie entity storage and relationships
 */
class Module extends Module_Base {

	/**
	 * Get module name
	 * @return string
	 */
	public function get_name(): string {
		return 'Cookie';
	}

	/**
	 * Get list of REST routes
	 * @return array
	 */
	public static function routes_list(): array {
		return [
			'Create_Cookie',
			'Get_Cookies',
			'Update_Cookie',
			'Delete_Cookie',
		];
	}

	public static function component_list(): array {
		return [
			'Cookie',
		];
	}

	/**
	 * Install database tables
	 * Called on plugin activation
	 */
	public static function install_tables(): void {
		Cookie_Table::install();
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->install_tables();
		$this->register_components();
		$this->register_routes();
	}
}
