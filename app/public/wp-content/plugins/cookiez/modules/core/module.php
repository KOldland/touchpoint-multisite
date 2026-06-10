<?php

namespace Cookiez\Modules\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Cookiez\Classes\Module_Base;
use Cookiez\Modules\Connect\Module as Connect;
use Cookiez\Modules\Settings\Module as Settings;

/**
 * Core module
 */
class Module extends Module_Base {

	/**
	 * @return string
	 */
	public function get_name(): string {
		return 'Core';
	}

	/**
	 * Declare components list
	 *
	 * @return array
	 */
	public static function component_list(): array {
		return [
			'Notices',
			'Pointers',
		];
	}

	/**
	 * Prepends Settings / Upgrade / Connect links for this plugin on the Plugins screen.
	 *
	 * @param array|string $links            Existing action links.
	 * @param string       $plugin_file_name Plugin basename relative to WP_PLUGIN_DIR.
	 * @return array
	 */
	public function add_plugin_links( $links, $plugin_file_name ): array {
		if ( ! str_ends_with( $plugin_file_name, '/cookiez.php' ) ) {
			return (array) $links;
		}

		$custom_links = [
			'settings' => sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'admin.php?page=' . Settings::SETTING_BASE_SLUG ),
				esc_html__( 'Settings', 'cookiez' )
			),
		];

		if ( Connect::is_connected() && ! Settings::is_elementor_one() ) {
			$custom_links['upgrade'] = sprintf(
				'<a href="%s" style="color: #524CFF; font-weight: 700;" target="_blank" rel="noopener noreferrer">%s</a>',
				'https://go.elementor.com/cookiez-add-quota-button',
				esc_html__( 'Upgrade', 'cookiez' )
			);
		}
		if ( ! Connect::is_connected() ) {
			$custom_links['connect'] = sprintf(
				'<a href="%s" style="color: #524CFF; font-weight: 700;">%s</a>',
				admin_url( 'admin.php?page=' . Settings::SETTING_BASE_SLUG ),
				esc_html__( 'Connect', 'cookiez' )
			);
		}

		return array_merge( $custom_links, $links );
	}

	/**
	 * Enqueue global admin styles (e.g. quota notices).
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		wp_enqueue_style(
			'cookiez-admin-global',
			COOKIEZ_ASSETS_URL . 'css/admin.css',
			[],
			COOKIEZ_VERSION
		);
	}

	/**
	 * Module constructor.
	 */
	public function __construct() {
		$this->register_components();

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'plugin_action_links', [ $this, 'add_plugin_links' ], 10, 2 );
	}
}
