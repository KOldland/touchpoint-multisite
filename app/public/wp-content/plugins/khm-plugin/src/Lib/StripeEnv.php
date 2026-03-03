<?php
/**
 * Environment/config resolution helpers for Stripe-sensitive values.
 *
 * @package KHM\Lib
 */

namespace KHM\Lib {
	/**
	 * Resolve a value from environment variables or wp-config constants.
	 *
	 * Lookup order:
	 * 1) getenv($key_name)
	 * 2) $_ENV[$key_name]
	 * 3) defined($key_name) constant
	 *
	 * @param string $key_name Key name (for example KH_STRIPE_SECRET_KEY).
	 * @return string|null
	 */
	function get_env_value( string $key_name ): ?string {
		$key_name = trim( $key_name );
		if ( $key_name === '' ) {
			return null;
		}

		$env_value = getenv( $key_name );
		if ( is_string( $env_value ) && trim( $env_value ) !== '' ) {
			return trim( $env_value );
		}

		if ( isset( $_ENV[ $key_name ] ) ) {
			$raw = $_ENV[ $key_name ];
			if ( is_string( $raw ) && trim( $raw ) !== '' ) {
				return trim( $raw );
			}
		}

		if ( defined( $key_name ) ) {
			$constant = constant( $key_name );
			if ( is_string( $constant ) && trim( $constant ) !== '' ) {
				return trim( $constant );
			}
		}

		return null;
	}
}

namespace {
	/**
	 * Public helper for Stripe key resolution.
	 *
	 * @param string $key_name Key name (for example KH_STRIPE_SECRET_KEY).
	 * @return string|null
	 */
	function khm_get_stripe_secret( string $key_name ): ?string {
		return \KHM\Lib\get_env_value( $key_name );
	}
}
