<?php

namespace Cookiez\Modules\Scanner;

use Cookiez\Classes\Module_Base;
use Cookiez\Modules\Scanner\Database\{
	Scan_Table,
	Scan_Url_Table,
};

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module `Scanner`
 * Manages cookie scanning functionality and scan data storage
 */
class Module extends Module_Base {
	/**
	 * Get module name
	 * @return string
	 */
	public function get_name(): string {
		return 'Scanner';
	}

	public static function component_list(): array {
		return [
			'Scanner',
		];
	}

		/**
	 * Get REST route list
	 * @return array
	 */
	public static function routes_list(): array {
		return [
			'Create_Scan',
			'Get_Scan',
			'Get_Scans',
			'Scan_Webhook',
			'Cancel_Scan',
		];
	}

	/**
	 * Install database tables
	 * Called on plugin activation
	 */
	public static function install_tables(): void {
		Scan_Table::install();
		Scan_Url_Table::install();
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->install_tables();
		$this->register_routes();
	}
}
