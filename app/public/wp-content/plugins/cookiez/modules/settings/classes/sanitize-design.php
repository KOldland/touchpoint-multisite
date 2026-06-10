<?php

namespace Cookiez\Modules\Settings\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sanitize_Design {
	private const MAX_CORNER_RADIUS = 32;

	private const ALLOWED_BANNER_TYPES = [ 'banner', 'box' ];
	private const ALLOWED_VERTICAL_POSITIONS = [ 'bottom', 'top' ];
	private const ALLOWED_HORIZONTAL_POSITIONS = [ 'bottom-left', 'bottom-center', 'bottom-right' ];
	private const ALLOWED_BUTTON_SIZES = [ 'small', 'medium', 'large' ];
	private const ALLOWED_HORIZONTAL_DIRECTIONS = [ 'left', 'right' ];
	private const ALLOWED_VERTICAL_DIRECTIONS = [ 'higher', 'lower' ];
	private const ALLOWED_CONSENT_ICON_TYPES = [ 'cookie', 'text' ];
	private const ALLOWED_CONSENT_ICON_SIZES = [ 'small', 'medium', 'large' ];
	private const ALLOWED_CONSENT_ICON_POSITIONS_DESKTOP = [
		'top-left',
		'top-center',
		'top-right',
		'center-left',
		'center-right',
		'bottom-left',
		'bottom-center',
		'bottom-right',
	];
	private const ALLOWED_CONSENT_ICON_POSITIONS_MOBILE = [
		'top-left',
		'top-right',
		'center-left',
		'center-right',
		'bottom-left',
		'bottom-right',
	];

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $raw ): array {
		$out = [];

		if ( isset( $raw['bannerType'] ) && in_array( $raw['bannerType'], self::ALLOWED_BANNER_TYPES, true ) ) {
			$out['bannerType'] = $raw['bannerType'];
		}
		if ( isset( $raw['bannerPositionDesktop'] ) && in_array( $raw['bannerPositionDesktop'], self::ALLOWED_VERTICAL_POSITIONS, true ) ) {
			$out['bannerPositionDesktop'] = $raw['bannerPositionDesktop'];
		}
		if ( isset( $raw['bannerPositionMobile'] ) && in_array( $raw['bannerPositionMobile'], self::ALLOWED_VERTICAL_POSITIONS, true ) ) {
			$out['bannerPositionMobile'] = $raw['bannerPositionMobile'];
		}
		if ( array_key_exists( 'bannerCornerRadius', $raw ) ) {
			$out['bannerCornerRadius'] = min( absint( $raw['bannerCornerRadius'] ), self::MAX_CORNER_RADIUS );
		}
		$color_fields = [ 'bannerBackgroundColor', 'bannerTextColor', 'bannerLinkColor', 'buttonsAndTogglesColor' ];
		foreach ( $color_fields as $field ) {
			if ( isset( $raw[ $field ] ) && is_string( $raw[ $field ] ) ) {
				$color = self::sanitize_css_color( $raw[ $field ] );
				if ( $color ) {
					$out[ $field ] = $color;
				}
			}
		}
		if ( array_key_exists( 'titleTextSize', $raw ) ) {
			$out['titleTextSize'] = absint( $raw['titleTextSize'] );
		}
		if ( array_key_exists( 'bodyTextSize', $raw ) ) {
			$out['bodyTextSize'] = absint( $raw['bodyTextSize'] );
		}
		if ( isset( $raw['buttonSize'] ) && in_array( $raw['buttonSize'], self::ALLOWED_BUTTON_SIZES, true ) ) {
			$out['buttonSize'] = $raw['buttonSize'];
		}
		if ( array_key_exists( 'buttonsCornerRadius', $raw ) ) {
			$out['buttonsCornerRadius'] = min( absint( $raw['buttonsCornerRadius'] ), self::MAX_CORNER_RADIUS );
		}
		if ( array_key_exists( 'hidePoweredBy', $raw ) ) {
			$out['hidePoweredBy'] = (bool) $raw['hidePoweredBy'];
		}

		self::sanitize_custom_position( $raw, $out, 'Desktop' );
		self::sanitize_custom_position( $raw, $out, 'Mobile' );

		if ( isset( $raw['boxPositionDesktop'] ) && in_array( $raw['boxPositionDesktop'], self::ALLOWED_HORIZONTAL_POSITIONS, true ) ) {
			$out['boxPositionDesktop'] = $raw['boxPositionDesktop'];
		}
		if ( isset( $raw['boxPositionMobile'] ) && in_array( $raw['boxPositionMobile'], self::ALLOWED_VERTICAL_POSITIONS, true ) ) {
			$out['boxPositionMobile'] = $raw['boxPositionMobile'];
		}

		self::sanitize_custom_position( $raw, $out, 'BoxDesktop' );
		self::sanitize_custom_position( $raw, $out, 'BoxMobile' );

		if ( array_key_exists( 'consentIconActivation', $raw ) ) {
			$out['consentIconActivation'] = (bool) $raw['consentIconActivation'];
		}
		if ( isset( $raw['consentIconType'] ) && in_array( $raw['consentIconType'], self::ALLOWED_CONSENT_ICON_TYPES, true ) ) {
			$out['consentIconType'] = $raw['consentIconType'];
		}
		if ( isset( $raw['consentIconSize'] ) && in_array( $raw['consentIconSize'], self::ALLOWED_CONSENT_ICON_SIZES, true ) ) {
			$out['consentIconSize'] = $raw['consentIconSize'];
		}
		if ( isset( $raw['consentIconColor'] ) && is_string( $raw['consentIconColor'] ) ) {
			$color = self::sanitize_css_color( $raw['consentIconColor'] );
			if ( $color ) {
				$out['consentIconColor'] = $color;
			}
		}
		if ( array_key_exists( 'consentIconCornerRadius', $raw ) ) {
			$out['consentIconCornerRadius'] = absint( $raw['consentIconCornerRadius'] );
		}
		if ( isset( $raw['consentIconPositionDesktop'] ) && in_array( $raw['consentIconPositionDesktop'], self::ALLOWED_CONSENT_ICON_POSITIONS_DESKTOP, true ) ) {
			$out['consentIconPositionDesktop'] = $raw['consentIconPositionDesktop'];
		}
		if ( isset( $raw['consentIconPositionMobile'] ) && in_array( $raw['consentIconPositionMobile'], self::ALLOWED_CONSENT_ICON_POSITIONS_MOBILE, true ) ) {
			$out['consentIconPositionMobile'] = $raw['consentIconPositionMobile'];
		}

		self::sanitize_custom_position( $raw, $out, 'IconDesktop' );
		self::sanitize_custom_position( $raw, $out, 'IconMobile' );

		return $out;
	}

	private static function sanitize_css_color( string $value ): string {
		$value = trim( $value );

		$hex = sanitize_hex_color( $value );
		if ( $hex ) {
			return $hex;
		}

		$number    = '\d{1,3}';
		$percent   = '\d{1,3}%';
		$alpha     = '(0|1|0?\.\d+)';
		$rgb_regex = '/^rgba?\(\s*' . $number . '\s*,\s*' . $number . '\s*,\s*' . $number . '\s*(,\s*' . $alpha . ')?\s*\)$/';
		$hsl_regex = '/^hsla?\(\s*' . $number . '\s*,\s*' . $percent . '\s*,\s*' . $percent . '\s*(,\s*' . $alpha . ')?\s*\)$/';

		if ( preg_match( $rgb_regex, $value ) || preg_match( $hsl_regex, $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $raw
	 * @param array<string, mixed> $out
	 */
	private static function sanitize_custom_position( array $raw, array &$out, string $viewport ): void {
		$prefix = 'customPosition' . $viewport;

		$enabled_key = $prefix . 'Enabled';
		if ( array_key_exists( $enabled_key, $raw ) ) {
			$out[ $enabled_key ] = (bool) $raw[ $enabled_key ];
		}

		$h_offset_key = $prefix . 'HorizontalOffset';
		if ( array_key_exists( $h_offset_key, $raw ) ) {
			$out[ $h_offset_key ] = absint( $raw[ $h_offset_key ] );
		}

		$h_dir_key = $prefix . 'HorizontalDirection';
		if ( isset( $raw[ $h_dir_key ] ) && in_array( $raw[ $h_dir_key ], self::ALLOWED_HORIZONTAL_DIRECTIONS, true ) ) {
			$out[ $h_dir_key ] = $raw[ $h_dir_key ];
		}

		$v_offset_key = $prefix . 'VerticalOffset';
		if ( array_key_exists( $v_offset_key, $raw ) ) {
			$out[ $v_offset_key ] = absint( $raw[ $v_offset_key ] );
		}

		$v_dir_key = $prefix . 'VerticalDirection';
		if ( isset( $raw[ $v_dir_key ] ) && in_array( $raw[ $v_dir_key ], self::ALLOWED_VERTICAL_DIRECTIONS, true ) ) {
			$out[ $v_dir_key ] = $raw[ $v_dir_key ];
		}
	}
}
