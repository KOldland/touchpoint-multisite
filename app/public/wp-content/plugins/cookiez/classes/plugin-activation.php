<?php

namespace Cookiez\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin_Activation {
	const APP_PREFIX = 'cookiez';
	const ACCESS_TOKEN = '_access_token';
	const SETTING_PREFIX = 'elementor_one';

	public function __construct( $file ) {
		register_activation_hook( $file, [ $this, 'handle' ] );
	}

	public function handle(): void {
		$cookiez_token_option = self::APP_PREFIX . self::ACCESS_TOKEN;
		$one_token_option = self::SETTING_PREFIX . self::ACCESS_TOKEN;
		$migrated_plugins_option = self::SETTING_PREFIX . '_migrated_plugins';

		$is_cookiez_connected = ! empty( get_option( $cookiez_token_option ) );
		$is_one_connected = ! empty( get_option( $one_token_option ) );

		if ( $is_cookiez_connected || ! $is_one_connected ) {
			return;
		}

		$migrated_plugins = get_option( $migrated_plugins_option, [] );
		if ( in_array( self::APP_PREFIX, $migrated_plugins, true ) ) {
			return;
		}

		$migrated_plugins[] = self::APP_PREFIX;
		update_option( $migrated_plugins_option, $migrated_plugins, false );
	}
}
