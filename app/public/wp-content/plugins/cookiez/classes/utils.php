<?php

namespace Cookiez\Classes;

use Cookiez\Classes\Services\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Utils {
	/**
	 * get_elementor
	 *
	 * @param bool $instance
	 *
	 * @return \Elementor\Plugin|false|null
	 */
	public static function get_elementor( bool $instance = false ) {
		static $_instance = null;

		if ( false !== $instance ) {
			$_instance = $instance;

			return $instance;
		}

		if ( null !== $_instance ) {
			return $_instance;
		}

		if ( class_exists( 'Elementor\Plugin' ) ) {
			return \Elementor\Plugin::instance(); // @codeCoverageIgnore
		}

		return false;
	}

	public static function is_elementor_editor(): bool {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return false;
		}

		$elementor = self::get_elementor();

		if ( ! $elementor ) {
			return false;
		}

		if ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() ) {
			return true;
		}

		if ( isset( $elementor->preview ) && $elementor->preview->is_preview_mode() ) {
			return true;
		}

		return false;
	}

	public static function get_api_client(): ?Client {
		return Client::get_instance();
	}

	public static function is_plugin_page(): bool {
		$current_screen = get_current_screen();

		return str_contains( $current_screen->id, '_page_cookiez-settings' );
	}

	public static function get_translations(): array {
		return [
			'cookiez' => esc_html__( 'Cookie Consent', 'cookiez' ),
			'cookies' => esc_html__( 'Cookies', 'cookiez' ),
			'close' => esc_html__( 'Close', 'cookiez' ),
			'cookie' => esc_html__( 'Cookie:', 'cookiez' ),
			'duration' => esc_html__( 'Duration:', 'cookiez' ),
			'description' => esc_html__( 'Description:', 'cookiez' ),
			'noCookiesInCategory' => esc_html__( 'No cookies currently in this category.', 'cookiez' ),
			/* translators: %s is a cookie category title (e.g. "Functional"). */
			'enableCategoryCookies' => esc_html__( 'Enable %s cookies', 'cookiez' ),
			'alwaysActive' => esc_html__( 'Always active', 'cookiez' ),
		];
	}

	/**
	 * get the current page url
	 */
	public static function get_current_page_url(): ?string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = wp_parse_url( $request_uri, PHP_URL_PATH ); // removes query string
		return rtrim( home_url( $path ), '/' );
	}

	/**
	 * Checks whether $value is a syntactically valid cookie domain.
	 */
	public static function is_valid_cookie_domain( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$candidate = str_starts_with( $value, '.' ) ? substr( $value, 1 ) : $value;

		return false !== filter_var( $candidate, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME )
			&& false !== strpos( $candidate, '.' )
			&& false === filter_var( $candidate, FILTER_VALIDATE_IP );
	}

	/**
	 * Checks whether $value is a syntactically valid cookie name per RFC 6265 §4.1.1
	 * (token from RFC 7230). Allowed characters: ALPHA / DIGIT / ! # $ % & ' * + - . ^ _ ` | ~
	 */
	public static function is_valid_cookie_name( $value ): bool {
		if ( ! is_string( $value ) || '' === $value ) {
			return false;
		}

		return 1 === preg_match( "/^[!#$%&'*+\-.^_`|~A-Za-z0-9]+$/", $value );
	}
}
