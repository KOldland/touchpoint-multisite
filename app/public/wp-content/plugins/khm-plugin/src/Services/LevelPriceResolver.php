<?php

namespace KHM\Services;

/**
 * Resolve Stripe price IDs for membership levels.
 */
class LevelPriceResolver {
	public const PRICE_ID_REGEX = '/^price_[A-Za-z0-9]+$/';
	private static array $cache = [];

	private LevelRepository $levels;

	public function __construct( ?LevelRepository $levels = null ) {
		$this->levels = $levels ?: new LevelRepository();
	}

	/**
	 * Resolve a Stripe Price ID for a membership level.
	 *
	 * Priority:
	 * 1) stripe_price_id meta key
	 * 2) khm_level_meta.stripe_price_ids[currency][interval]
	 * 3) khm_stripe_membership_price_map filter
	 * 4) legacy options (khm_stripe_membership_price_map or khm_stripe_price_map)
	 */
	public function get_price_id( int $level_id, ?string $currency = null, string $interval = 'monthly' ): ?string {
		[ $normalized_currency, $normalized_interval ] = $this->normalize_currency_interval( $currency, $interval );
		$cache_key = $this->cache_key( $level_id, $normalized_currency, $normalized_interval );
		if ( array_key_exists( $cache_key, self::$cache ) ) {
			return self::$cache[ $cache_key ];
		}

		$price_id = $this->get_single_price_id( $level_id );
		if ( $price_id ) {
			return self::$cache[ $cache_key ] = $price_id;
		}

		$price_id = $this->get_multi_currency_price_id( $level_id, $normalized_currency, $normalized_interval );
		if ( $price_id ) {
			return self::$cache[ $cache_key ] = $price_id;
		}

		$price_id = $this->get_filtered_price_id( $level_id, $normalized_currency, $normalized_interval );
		if ( $price_id ) {
			return self::$cache[ $cache_key ] = $price_id;
		}

		$price_id = $this->get_option_price_id( $level_id );
		if ( $price_id ) {
			return self::$cache[ $cache_key ] = $price_id;
		}

		self::$cache[ $cache_key ] = null;
		return null;
	}

	private function get_single_price_id( int $level_id ): ?string {
		$meta = $this->levels->getMeta( $level_id, 'stripe_price_id' );
		if ( is_string( $meta ) && $meta !== '' ) {
			$meta = sanitize_text_field( $meta );
			return $this->is_valid_price_id( $meta ) ? $meta : null;
		}

		return null;
	}

	private function get_multi_currency_price_id( int $level_id, ?string $currency, string $interval ): ?string {
		$meta = $this->levels->getMeta( $level_id, 'khm_level_meta', [] );
		if ( is_string( $meta ) ) {
			$decoded = json_decode( $meta, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$meta = $decoded;
			}
		}

		if ( ! is_array( $meta ) ) {
			return null;
		}

		$currency = $currency ? strtoupper( $currency ) : strtoupper( (string) get_option( 'khm_currency', 'USD' ) );
		$interval = sanitize_key( $interval ?: 'monthly' );

		$map = $meta['stripe_price_ids'] ?? null;
		if ( ! is_array( $map ) ) {
			return null;
		}

		$candidate = $map[ $currency ][ $interval ] ?? null;
		if ( is_string( $candidate ) ) {
			$candidate = sanitize_text_field( $candidate );
			return $this->is_valid_price_id( $candidate ) ? $candidate : null;
		}

		return null;
	}

	private function get_filtered_price_id( int $level_id, ?string $currency, string $interval ): ?string {
		$filtered = apply_filters( 'khm_stripe_membership_price_map', [], $level_id, $currency, $interval );

		if ( is_string( $filtered ) && $filtered !== '' ) {
			$filtered = sanitize_text_field( $filtered );
			return $this->is_valid_price_id( $filtered ) ? $filtered : null;
		}

		if ( is_array( $filtered ) ) {
			if ( isset( $filtered[ $level_id ] ) && is_string( $filtered[ $level_id ] ) ) {
				$candidate = sanitize_text_field( $filtered[ $level_id ] );
				return $this->is_valid_price_id( $candidate ) ? $candidate : null;
			}
		}

		return null;
	}

	private function get_option_price_id( int $level_id ): ?string {
		$map = get_option( 'khm_stripe_membership_price_map', [] );
		if ( ! is_array( $map ) || empty( $map ) ) {
			$map = get_option( 'khm_stripe_price_map', [] );
		}

		if ( is_array( $map ) && isset( $map[ $level_id ] ) && is_string( $map[ $level_id ] ) ) {
			$candidate = sanitize_text_field( $map[ $level_id ] );
			return $this->is_valid_price_id( $candidate ) ? $candidate : null;
		}

		return null;
	}

	private function is_valid_price_id( string $price_id ): bool {
		return (bool) preg_match( self::PRICE_ID_REGEX, $price_id );
	}

	private function normalize_currency_interval( ?string $currency, string $interval ): array {
		$normalized_currency = $currency ? strtoupper( $currency ) : strtoupper( (string) get_option( 'khm_currency', 'USD' ) );
		$normalized_interval = sanitize_key( $interval ?: 'monthly' );

		return [ $normalized_currency, $normalized_interval ];
	}

	private function cache_key( int $level_id, string $currency, string $interval ): string {
		return implode( '|', [ (string) $level_id, $currency, $interval ] );
	}
}
