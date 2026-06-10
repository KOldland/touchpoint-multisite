<?php

namespace Cookiez\Modules\Elementor\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Core\Base\Document as Elementor_Document;
use WP_Query;

class Popup_Admin_Filter {

	private function get_admin_get_string( string $key ): string {
		if ( ! isset( $_GET[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin list query; read-only.
		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
	}

	public function restrict_popup_admin_list_to_standard_popups( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		global $pagenow;

		if ( 'edit.php' !== $pagenow ) {
			return;
		}

		if ( 'elementor_library' !== $this->get_admin_get_string( 'post_type' ) ) {
			return;
		}

		if ( 'popup' !== $this->get_admin_get_string( 'tabs_group' ) ) {
			return;
		}

		if ( '' !== $this->get_admin_get_string( 'elementor_library_type' ) ) {
			return;
		}

		$query->set( 'meta_key', Elementor_Document::TYPE_META_KEY );
		$query->set( 'meta_value', 'popup' );
	}

	public function hide_standard_popup_subtype_tab(): void {
		$screen = get_current_screen();

		if ( ! $screen || 'edit-elementor_library' !== $screen->id ) {
			return;
		}

		if ( 'popup' !== $this->get_admin_get_string( 'tabs_group' ) ) {
			return;
		}

		// TODO: fix this
		echo '<style id="cookiez-hide-elementor-popup-type-tab">#elementor-template-library-tabs-wrapper a[href*="elementor_library_type=popup"]{display:none!important;}</style>';
	}
}
