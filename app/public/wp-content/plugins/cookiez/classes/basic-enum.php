<?php

namespace Cookiez\Classes;

use ReflectionClass;
use ReflectionException;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

abstract class Basic_Enum {
	private static array $entries = [];

	/**
	 * @throws ReflectionException
	 */
	public static function get_values(): array {
		return array_values( self::get_entries() );
	}

	/**
	 * Whether the given value is a defined enum member.
	 *
	 * @param mixed $value
	 * @return bool
	 * @throws ReflectionException
	 */
	public static function is_valid( $value ): bool {
		return in_array( $value, self::get_values(), true );
	}

	/**
	 * @throws ReflectionException
	 */
	protected static function get_entries(): array {
		$caller = get_called_class();

		if ( ! array_key_exists( $caller, self::$entries ) ) {
			$reflect = new ReflectionClass( $caller );
			self::$entries[ $caller ] = $reflect->getConstants();
		}

		return self::$entries[ $caller ];
	}
}
