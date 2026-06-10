<?php

namespace Cookiez\Classes\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sanitizer
 *
 * Static factory that returns callables for use in Route::sanitize_fields().
 * Every method returns fn( mixed $value ): mixed using standard WP sanitization functions.
 */
final class Sanitizer {

	/**
	 * General-purpose text field.
	 * Strips tags, extra whitespace, and encodes HTML entities.
	 */
	public static function text(): callable {
		return fn( $value ) => isset( $value ) ? sanitize_text_field( $value ) : null;
	}

	/**
	 * Multi-line text field.
	 * Like text() but preserves newlines.
	 */
	public static function textarea(): callable {
		return fn( $value ) => isset( $value ) ? sanitize_textarea_field( $value ) : null;
	}

	/**
	 * URL.
	 * Returns an empty string for invalid URLs.
	 */
	public static function url(): callable {
		return fn( $value ) => isset( $value ) ? esc_url_raw( $value ) : null;
	}

	/**
	 * Slug / DB key.
	 * Lowercases, strips non-alphanumeric characters except hyphens and underscores.
	 */
	public static function key(): callable {
		return fn( $value ) => isset( $value ) ? sanitize_key( $value ) : null;
	}

	/**
	 * E-mail address.
	 * Returns an empty string for invalid addresses.
	 */
	public static function email(): callable {
		return fn( $value ) => isset( $value ) ? sanitize_email( $value ) : null;
	}

	/**
	 * Integer (signed). Returns null when the value is absent (null).
	 */
	public static function int(): callable {
		return fn( $value ) => isset( $value ) ? (int) $value : null;
	}

	/**
	 * Absolute integer (>= 0).
	 */
	public static function absint(): callable {
		return fn( $value ) => isset( $value ) ? absint( $value ) : null;
	}

	/**
	 * Boolean cast.
	 */
	public static function bool(): callable {
		return fn( $value ) => isset( $value ) ? (bool) $value : null;
	}

	/**
	 * Array cast.
	 */
	public static function array( callable $map ): callable {
		return function( $value ) use ( $map ) {
			if ( ! isset( $value ) ) {
				return null;
			}

			if ( ! is_array( $value ) ) {
				return [];
			}

			return array_map( fn( $item ) => $map( $item ), $value );
		};
	}

	/**
	 * CSS hex color (#rrggbb / #rgb).
	 * Returns an empty string for values that are not valid hex colors.
	 */
	public static function hex_color(): callable {
		return fn( $value ) => isset( $value ) ? sanitize_hex_color( $value ) : null;
	}
}
