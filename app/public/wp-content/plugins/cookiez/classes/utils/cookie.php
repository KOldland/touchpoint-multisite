<?php

namespace Cookiez\Classes\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class Cookie
 */
class Cookie {
	/**
	 * get
	 * @param string $cookie_name
	 *
	 * @return string|false
	 */
	public function get( string $cookie_name ) {
		if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
			return false;
		}
		return sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
	}

	/**
	 * set
	 *
	 * @param string $cookie_name
	 * @param string $cookie_value
	 * @param int|null $expires
	 * @param string $path
	 * @param bool $httponly
	 *
	 * @codeCoverageIgnore
	 */
	public function set( string $cookie_name, string $cookie_value = '', ?int $expires = null, string $path = '/', bool $httponly = true ): void {
		if ( null === $expires ) {
			$expires = time() + 360;
		} else {
			$expires = time() + $expires;
		}
		setcookie(
			$cookie_name,
			$cookie_value,
			$expires,
			$path,
			COOKIE_DOMAIN,
			is_ssl(),
			$httponly
		);
	}

	/**
	 * delete
	 * @param string $cookie_name
	 * @codeCoverageIgnore
	 */
	public function delete( string $cookie_name ): void {
		setcookie(
			$cookie_name,
			'',
			time() - 3600
		);
	}
}
