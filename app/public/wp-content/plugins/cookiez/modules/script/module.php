<?php

namespace Cookiez\Modules\Script;

use Cookiez\Classes\Module_Base;
use Cookiez\Modules\Script\Database\Script_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module `Script`
 * Manages script entity storage and relationships
 */
class Module extends Module_Base {

	/**
	 * Get module name
	 * @return string
	 */
	public function get_name(): string {
		return 'Script';
	}

	/**
	 * Get list of REST routes
	 * @return array
	 */
	public static function routes_list(): array {
		return [
			'Create_Script',
			'Get_Scripts',
			'Update_Script',
			'Delete_Script',
		];
	}

	public static function component_list(): array {
		return [
			'Script',
		];
	}

	/**
	 * Install database tables
	 * Called on plugin activation
	 */
	public static function install_tables(): void {
		Script_Table::install();
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
