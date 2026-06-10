<?php

namespace Cookiez\Classes\Utils;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class Constants' wrapper to allow testability
 */
class Constants {
	/**
	 * define
	 *
	 * @param string $key
	 * @param $value
	 *
	 * @throws Exception
	 */
	public function define( string $key, $value ) {
		if ( defined( $key ) ) {
			throw new Exception( 'Constant ' . esc_html( $key ) . ' is already defined' );
		}
		//@phpcs ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound
		define( $key, $value );
	}

	/**
	 * defined
	 *
	 * @param $key
	 *
	 * @return bool
	 */
	public function defined( $key ): bool {
		return defined( $key );
	}

	/**
	 * constant
	 *
	 * @param $key
	 *
	 * @return mixed
	 */
	public function constant( $key ) {
		return constant( $key );
	}

	/**
	 * get_constant
	 *
	 * @param string $key
	 * @param mixed $default
	 * @param $instance
	 *
	 * @return mixed
	 */
	public static function get_constant( string $key, $default = null, $instance = null ) {
		static $inner_instance = null;
		if ( null === $inner_instance ) {
			$inner_instance = $instance ?? new static();
		}
		if ( ! $inner_instance->defined( $key ) ) {
			return $default;
		}

		return $inner_instance->constant( $key );
	}
}
