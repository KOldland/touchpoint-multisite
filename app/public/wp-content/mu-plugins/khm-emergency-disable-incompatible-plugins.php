<?php
/**
 * Emergency guard: prevent known incompatible plugins from loading.
 *
 * This keeps the site bootable even if active plugin options contain renamed paths.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'khm_filter_incompatible_plugins' ) ) {
	function khm_filter_incompatible_plugins( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return $plugins;
		}

		$blocked_slugs = array(
			'pojo-accessibility',
		);

		return array_values(
			array_filter(
				$plugins,
				static function ( $plugin_file ) use ( $blocked_slugs ) {
					$plugin_file = (string) $plugin_file;
					foreach ( $blocked_slugs as $slug ) {
						if ( strpos( $plugin_file, $slug . '/' ) === 0 ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}
}

if ( ! function_exists( 'khm_filter_incompatible_network_plugins' ) ) {
	function khm_filter_incompatible_network_plugins( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return $plugins;
		}

		foreach ( array_keys( $plugins ) as $plugin_file ) {
			if ( strpos( (string) $plugin_file, 'pojo-accessibility/' ) === 0 ) {
				unset( $plugins[ $plugin_file ] );
			}
		}

		return $plugins;
	}
}

add_filter( 'option_active_plugins', 'khm_filter_incompatible_plugins', 1 );
add_filter( 'site_option_active_sitewide_plugins', 'khm_filter_incompatible_network_plugins', 1 );
