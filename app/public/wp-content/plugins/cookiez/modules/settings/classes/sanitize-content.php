<?php

namespace Cookiez\Modules\Settings\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sanitize_Content {

	public const ALLOWED_LANGUAGES = [
		'ar',
		'cs',
		'da',
		'de',
		'el',
		'en',
		'es',
		'et',
		'fi',
		'fr',
		'he',
		'hi',
		'hr',
		'hu',
		'it',
		'ja',
		'ko',
		'nb',
		'nl',
		'nn',
		'pl',
		'pt',
		'ro',
		'ru',
		'sk',
		'sl',
		'sr',
		'sv',
		'th',
		'tr',
		'uk',
		'uz',
		'vi',
		'zh',
	];

	private const CATEGORY_KEYS = [
		'necessary',
		'functional',
		'analytics',
		'advertising',
		'unclassified',
	];
	public const FALLBACK_LANGUAGE = 'en';

	/**
	 * Normalize a WordPress locale (e.g. `uk_UA`, `pt_BR`) to a supported short
	 * language code matching the bundled translation JSON files and stored
	 * content keys. Falls back to English when the locale cannot be mapped.
	 * @param string[] $disabled_languages
	 */
	public static function resolve_language(
		string $wp_locale,
		string $default_language = self::FALLBACK_LANGUAGE,
		array $disabled_languages = []
	): string {
		if ( in_array( $wp_locale, self::ALLOWED_LANGUAGES, true ) ) {
			$resolved = $wp_locale;
		} else {
			$short = strtolower( strtok( $wp_locale, '_-' ) );
			$resolved = in_array( $short, self::ALLOWED_LANGUAGES, true )
				? $short
				: $default_language;
		}

		if ( in_array( $resolved, $disabled_languages, true ) ) {
			return $default_language;
		}

		return $resolved;
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $raw ): array {
		$out = [];

		if ( isset( $raw['languages'] ) && is_array( $raw['languages'] ) ) {
			$out['languages'] = array_values(
				array_filter(
					array_map( 'sanitize_text_field', $raw['languages'] ),
					fn( $lang ) => in_array( $lang, self::ALLOWED_LANGUAGES, true )
				)
			);
		}

		if ( isset( $raw['disabledLanguages'] ) && is_array( $raw['disabledLanguages'] ) ) {
			$out['disabledLanguages'] = array_values(
				array_filter(
					array_map( 'sanitize_text_field', $raw['disabledLanguages'] ),
					fn( $lang ) => in_array( $lang, self::ALLOWED_LANGUAGES, true )
				)
			);
		}

		if ( isset( $raw['content'] ) && is_array( $raw['content'] ) ) {
			$out['content'] = [];
			foreach ( $raw['content'] as $lang => $lang_data ) {
				$lang = sanitize_text_field( (string) $lang );
				if ( ! in_array( $lang, self::ALLOWED_LANGUAGES, true ) || ! is_array( $lang_data ) ) {
					continue;
				}
				$out['content'][ $lang ] = self::sanitize_locale_entry( $lang_data );
			}
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private static function sanitize_locale_entry( array $raw ): array {
		$out = [];
		if ( array_key_exists( 'name', $raw ) ) {
			$out['name'] = sanitize_text_field( (string) $raw['name'] );
		}
		if ( isset( $raw['opt-in'] ) && is_array( $raw['opt-in'] ) ) {
			$out['opt-in'] = self::sanitize_opt_in_wire( $raw['opt-in'] );
		}
		if ( isset( $raw['opt-out'] ) && is_array( $raw['opt-out'] ) ) {
			$out['opt-out'] = self::sanitize_opt_out_wire( $raw['opt-out'] );
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private static function sanitize_opt_in_wire( array $raw ): array {
		$out = [];
		if ( array_key_exists( 'title', $raw ) ) {
			$out['title'] = sanitize_text_field( (string) $raw['title'] );
		}
		if ( array_key_exists( 'cookie-message', $raw ) ) {
			$out['cookie-message'] = sanitize_text_field( (string) $raw['cookie-message'] );
		}
		if ( isset( $raw['buttons'] ) && is_array( $raw['buttons'] ) ) {
			$out['buttons'] = self::sanitize_buttons( $raw['buttons'] );
		}
		if ( isset( $raw['banner-options'] ) && is_array( $raw['banner-options'] ) ) {
			$out['banner-options'] = self::sanitize_banner_options( $raw['banner-options'] );
		}
		if ( isset( $raw['preferences'] ) && is_array( $raw['preferences'] ) ) {
			$out['preferences'] = self::sanitize_preferences_wire( $raw['preferences'] );
		}
		if ( isset( $raw['cookie-categories'] ) && is_array( $raw['cookie-categories'] ) ) {
			$out['cookie-categories'] = self::sanitize_cookie_categories_wire( $raw['cookie-categories'] );
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private static function sanitize_opt_out_wire( array $raw ): array {
		$out = [];
		if ( array_key_exists( 'title', $raw ) ) {
			$out['title'] = sanitize_text_field( (string) $raw['title'] );
		}
		if ( array_key_exists( 'cookie-message', $raw ) ) {
			$out['cookie-message'] = sanitize_text_field( (string) $raw['cookie-message'] );
		}
		if ( isset( $raw['buttons'] ) && is_array( $raw['buttons'] ) ) {
			$out['buttons'] = self::sanitize_buttons( $raw['buttons'] );
		}
		if ( isset( $raw['banner-options'] ) && is_array( $raw['banner-options'] ) ) {
			$out['banner-options'] = self::sanitize_banner_options( $raw['banner-options'] );
		}
		if ( isset( $raw['opt-out-dialog'] ) && is_array( $raw['opt-out-dialog'] ) ) {
			$out['opt-out-dialog'] = self::sanitize_opt_out_dialog_wire( $raw['opt-out-dialog'] );
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private static function sanitize_buttons( array $raw ): array {
		$out = [];
		foreach ( [ 'accept-all', 'deny', 'customize' ] as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$out[ $key ] = sanitize_text_field( (string) $raw[ $key ] );
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private static function sanitize_banner_options( array $raw ): array {
		$out = [];
		$bools = [ 'cookie-policy-link', 'privacy-policy-link', 'do-not-sell' ];
		foreach ( $bools as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$out[ $key ] = (bool) $raw[ $key ];
			}
		}
		$texts = [ 'cookie-policy-link-text', 'privacy-policy-link-text', 'do-not-sell-link-text' ];
		foreach ( $texts as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$out[ $key ] = sanitize_text_field( (string) $raw[ $key ] );
			}
		}
		$urls = [ 'cookie-policy-url', 'privacy-policy-url' ];
		foreach ( $urls as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$out[ $key ] = esc_url_raw( (string) $raw[ $key ] );
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private static function sanitize_preferences_wire( array $raw ): array {
		$out = [];
		foreach ( [ 'header', 'privacy-overview', 'accept-button', 'deny-button', 'save-preferences' ] as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$out[ $key ] = sanitize_text_field( (string) $raw[ $key ] );
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private static function sanitize_opt_out_dialog_wire( array $raw ): array {
		$out = [];
		foreach ( [ 'header', 'privacy-overview', 'consent-label', 'deny-button', 'save-preferences' ] as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$out[ $key ] = sanitize_text_field( (string) $raw[ $key ] );
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private static function sanitize_cookie_categories_wire( array $raw ): array {
		$out = [];
		foreach ( self::CATEGORY_KEYS as $key ) {
			if ( ! isset( $raw[ $key ] ) || ! is_array( $raw[ $key ] ) ) {
				continue;
			}
			$cat = $raw[ $key ];
			$out[ $key ] = [];
			if ( array_key_exists( 'title', $cat ) ) {
				$out[ $key ]['title'] = sanitize_text_field( (string) $cat['title'] );
			}
			if ( array_key_exists( 'description', $cat ) ) {
				$out[ $key ]['description'] = sanitize_text_field( (string) $cat['description'] );
			}
		}
		return $out;
	}
}
