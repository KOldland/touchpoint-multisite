<?php

namespace Cookiez\Modules\Elementor\Documents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ElementorPro\Modules\Popup\Document;

abstract class Document_Base extends Document {

	public static function get_type(): string {
		return static::DOCUMENT_TYPE;
	}

	public function get_name(): string {
		return static::DOCUMENT_TYPE;
	}

	public static function get_properties(): array {
		$properties = parent::get_properties();

		$properties['admin_tab_group'] = 'popup';

		return $properties;
	}

	public function get_initial_config(): array {
		$config = parent::get_initial_config();
		$config['panel']['default_route'] = 'panel/page-settings/settings';

		return $config;
	}

	public function get_frontend_settings(): array {
		$settings = parent::get_frontend_settings();

		unset( $settings['triggers'], $settings['timing'] );

		return $settings;
	}

	/**
	 * Override the edit url to remove the library filter.
	 */
	public function get_edit_url(): string {
		return str_replace( '#library', '', parent::get_edit_url() );
	}
}
