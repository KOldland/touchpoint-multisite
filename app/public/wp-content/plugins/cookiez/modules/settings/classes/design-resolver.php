<?php

namespace Cookiez\Modules\Settings\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Design_Resolver {
	private const ALLOWED_BUTTON_SIZES = [ 'small', 'medium', 'large' ];

	private const DEFAULT_DESIGN = [
		'bannerBackgroundColor'  => '#ffffff',
		'bannerTextColor'        => '#0C0D0E',
		'bannerLinkColor'        => '#005FCC',
		'titleTextSize'          => 20,
		'bodyTextSize'           => 14,
		'buttonSize'             => 'medium',
		'buttonsAndTogglesColor' => '#0C0D0E',
		'buttonsCornerRadius'    => 8,
	];

	private const BUTTON_SIZE_STYLES = [
		'small'  => [
			'padding'   => '4px 10px',
			'font_size' => '14px',
		],
		'medium' => [
			'padding'   => '6px 16px',
			'font_size' => '14px',
		],
		'large'  => [
			'padding'   => '8px 22px',
			'font_size' => '16px',
		],
	];

	/**
	 * @return array<string, string|int>
	 */
	public static function get_active_design(): array {
		$settings = Settings::get( Settings::COOKIEZ_SETTINGS );

		$button_size = self::DEFAULT_DESIGN['buttonSize'];
		if ( isset( $settings['buttonSize'] ) && in_array( $settings['buttonSize'], self::ALLOWED_BUTTON_SIZES, true ) ) {
			$button_size = $settings['buttonSize'];
		}

		return [
			'bannerBackgroundColor'  => self::get_color_or_default( $settings['bannerBackgroundColor'] ?? null, self::DEFAULT_DESIGN['bannerBackgroundColor'] ),
			'bannerTextColor'        => self::get_color_or_default( $settings['bannerTextColor'] ?? null, self::DEFAULT_DESIGN['bannerTextColor'] ),
			'bannerLinkColor'        => self::get_color_or_default( $settings['bannerLinkColor'] ?? null, self::DEFAULT_DESIGN['bannerLinkColor'] ),
			'titleTextSize'          => self::get_positive_int_or_default( $settings['titleTextSize'] ?? null, self::DEFAULT_DESIGN['titleTextSize'] ),
			'bodyTextSize'           => self::get_positive_int_or_default( $settings['bodyTextSize'] ?? null, self::DEFAULT_DESIGN['bodyTextSize'] ),
			'buttonSize'             => $button_size,
			'buttonsAndTogglesColor' => self::get_color_or_default( $settings['buttonsAndTogglesColor'] ?? null, self::DEFAULT_DESIGN['buttonsAndTogglesColor'] ),
			'buttonsCornerRadius'    => self::get_positive_int_or_default( $settings['buttonsCornerRadius'] ?? null, self::DEFAULT_DESIGN['buttonsCornerRadius'] ),
		];
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_button_size_styles( string $button_size ): array {
		return self::BUTTON_SIZE_STYLES[ $button_size ] ?? self::BUTTON_SIZE_STYLES[ self::DEFAULT_DESIGN['buttonSize'] ];
	}

	private static function get_color_or_default( $value, string $fallback ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$sanitized = sanitize_hex_color( $value );
		if ( $sanitized ) {
			return $sanitized;
		}

		return $fallback;
	}

	private static function get_positive_int_or_default( $value, int $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		$normalized = absint( $value );
		return 0 < $normalized ? $normalized : $fallback;
	}
}
