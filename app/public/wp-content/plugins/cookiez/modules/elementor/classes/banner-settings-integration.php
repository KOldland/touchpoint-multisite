<?php

namespace Cookiez\Modules\Elementor\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cookiez\Modules\Elementor\Documents\Types\Cookie_Consent_Document;
use Cookiez\Modules\Elementor\Documents\Types\Preferences_Banner_Document;
use Elementor\Core\Base\Document as Elementor_Document;
use ElementorPro\Modules\ThemeBuilder\Module as ThemeBuilderModule;
use WP_Query;

class Banner_Settings_Integration {

	public function inject_elementor_cookie_consent_id( array $settings ): array {
		return $this->inject_popup_id( $settings, Cookie_Consent_Document::DOCUMENT_TYPE, 'elementorCookieConsentId' );
	}

	public function inject_elementor_preferences_banner_id( array $settings ): array {
		return $this->inject_popup_id( $settings, Preferences_Banner_Document::DOCUMENT_TYPE, 'elementorPreferencesBannerId' );
	}

	public function inject_elementor_cookie_consent_id_for_admin( array $settings ): array {
		return $this->inject_popup_id( $settings, Cookie_Consent_Document::DOCUMENT_TYPE, 'elementorCookieConsentId', false );
	}

	public function inject_elementor_preferences_banner_id_for_admin( array $settings ): array {
		return $this->inject_popup_id( $settings, Preferences_Banner_Document::DOCUMENT_TYPE, 'elementorPreferencesBannerId', false );
	}

	private function inject_popup_id( array $settings, string $document_type, string $settings_key, bool $check_conditions = true ): array {
		$elementor_popup_id = self::get_active_document_id( $document_type );

		if ( 0 >= $elementor_popup_id ) {
			return $settings;
		}

		if ( $check_conditions ) {
			$popups_for_page = ThemeBuilderModule::instance()
				->get_conditions_manager()
				->get_documents_for_location( 'popup' );

			if ( ! isset( $popups_for_page[ $elementor_popup_id ] ) ) {
				return $settings;
			}
		}

		$settings[ $settings_key ] = $elementor_popup_id;

		return $settings;
	}

	private static function get_active_document_id( string $document_type ): int {
		$query = new WP_Query( [
			'post_type'              => 'elementor_library',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'meta_query'             => [
				[
					'key'   => Elementor_Document::TYPE_META_KEY,
					'value' => $document_type,
				],
			],
		] );

		$ids = $query->get_posts();

		return empty( $ids ) ? 0 : (int) $ids[0];
	}
}
