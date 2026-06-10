<?php

namespace Cookiez\Modules\Scanner\Classes;

use Cookiez\Modules\Scanner\Classes\Enums\Scan_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scan_Url_Collector {
	public static function collect( string $type, array $custom_urls = [], array $excluded_urls = [] ): array {
		switch ( $type ) {
			case Scan_Type::FULL:
				return self::all_public_urls( $excluded_urls );

			case Scan_Type::CUSTOM:
				return self::custom_urls( $custom_urls );

			case Scan_Type::HOMEPAGE:
			default:
				return self::homepage_url();
		}
	}

	private static function custom_urls( array $urls ): array {
		$site_host = self::site_host();

		$filtered = array_filter(
			$urls,
			static function ( $url ) use ( $site_host ) {
				$host = wp_parse_url( (string) $url, PHP_URL_HOST );

				return is_string( $host ) && strtolower( $host ) === $site_host;
			}
		);

		return array_values( $filtered );
	}

	private static function homepage_url(): array {
		return [ get_home_url() ];
	}

	private static function all_public_urls( array $excluded_urls ): array {
		$post_types = array_values(
			get_post_types( [
				'public' => true,
				'exclude_from_search' => false,
			], 'names' )
		);

		$post_ids = get_posts( [
			'post_status' => 'publish',
			'post_type'   => $post_types,
			'numberposts' => -1,
			'fields'      => 'ids',
		] );

		$urls = [ get_home_url() ];

		foreach ( $post_ids as $post_id ) {
			$permalink = get_permalink( $post_id );

			if ( $permalink ) {
				$urls[] = $permalink;
			}
		}

		$unique = array_values( array_unique( $urls ) );

		if ( empty( $excluded_urls ) ) {
			return $unique;
		}

		$excluded = array_map( [ self::class, 'normalize_url' ], $excluded_urls );

		return array_values( array_filter(
			$unique,
			static fn( $url ) => ! in_array( self::normalize_url( $url ), $excluded, true )
		) );
	}

	private static function site_host(): string {
		$host = wp_parse_url( get_home_url(), PHP_URL_HOST );

		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/**
	 * Normalize a URL for equality comparison: lowercase host, strip the
	 * trailing slash from the path. Keeps the scheme so a stray http/https
	 * mismatch in the exclude list still excludes the intended URL.
	 */
	private static function normalize_url( string $url ): string {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) ) {
			return rtrim( $url, '/' );
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) . '://' : '';
		$host = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$port = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$path = rtrim( $parts['path'] ?? '', '/' );
		$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

		return $scheme . $host . $port . $path . $query;
	}
}
