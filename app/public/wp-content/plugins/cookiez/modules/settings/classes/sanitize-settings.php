<?php

namespace Cookiez\Modules\Settings\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sanitize_Settings {
	private const ALLOWED_ONBOARDING_STEPS = [ 'step_one', 'step_two', 'step_three' ];
	private const ALLOWED_TEMPLATE_TYPES = [ 'opt-in', 'opt-out', 'opt-in-out' ];
	private const ALLOWED_GEO_TARGETING = [ 'worldwide' ];
	private const MAX_CONSENT_EXPIRATION_DAYS = 365;
	private const MIN_CONSENT_EXPIRATION_DAYS = 1;

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $raw ): array {
		$out = [];

		if ( array_key_exists( 'bannerDisplayStatus', $raw ) ) {
			$out['bannerDisplayStatus'] = (bool) $raw['bannerDisplayStatus'];
		}

		if ( array_key_exists( 'disableBannerPages', $raw ) && is_array( $raw['disableBannerPages'] ) ) {
			$out['disableBannerPages'] = array_values( array_filter(
				array_map( 'esc_url_raw', $raw['disableBannerPages'] )
			) );
		}

		if ( isset( $raw['templateType'] ) && in_array( $raw['templateType'], self::ALLOWED_TEMPLATE_TYPES, true ) ) {
			$out['templateType'] = $raw['templateType'];
		}

		if ( isset( $raw['geoTargeting'] ) && in_array( $raw['geoTargeting'], self::ALLOWED_GEO_TARGETING, true ) ) {
			$out['geoTargeting'] = $raw['geoTargeting'];
		}

		if ( array_key_exists( 'consentExpiration', $raw ) ) {
			$out['consentExpiration'] = min(
				self::MAX_CONSENT_EXPIRATION_DAYS,
				max( self::MIN_CONSENT_EXPIRATION_DAYS, absint( $raw['consentExpiration'] ) )
			);
		}

		if ( array_key_exists( 'gpcDntSupport', $raw ) ) {
			$out['gpcDntSupport'] = (bool) $raw['gpcDntSupport'];
		}

		if ( array_key_exists( 'supportGcm', $raw ) ) {
			$out['supportGcm'] = (bool) $raw['supportGcm'];
		}

		if ( array_key_exists( 'googleTagsBeforeConsent', $raw ) ) {
			$out['googleTagsBeforeConsent'] = (bool) $raw['googleTagsBeforeConsent'];
		}

		if ( array_key_exists( 'contentAlertDismissed', $raw ) ) {
			$out['contentAlertDismissed'] = (bool) $raw['contentAlertDismissed'];
		}

		if ( array_key_exists( 'designBannerInfotipDismissed', $raw ) ) {
			$out['designBannerInfotipDismissed'] = (bool) $raw['designBannerInfotipDismissed'];
		}

		if ( array_key_exists( 'isOnboardingCompleted', $raw ) ) {
			$out['isOnboardingCompleted'] = (bool) $raw['isOnboardingCompleted'];
		}

		if ( isset( $raw['onboardingCurrentStep'] ) && in_array( $raw['onboardingCurrentStep'], self::ALLOWED_ONBOARDING_STEPS, true ) ) {
			$out['onboardingCurrentStep'] = $raw['onboardingCurrentStep'];
		}

		return $out;
	}
}
