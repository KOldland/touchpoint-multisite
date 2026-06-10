<?php

namespace Cookiez\Modules\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cookiez\Classes\Module_Base;
use Cookiez\Modules\Elementor\Classes\{
	Banner_Settings_Integration,
	Document_Utils,
	Popup_Admin_Filter,
};
use Cookiez\Modules\Elementor\Documents\Types\{
	Cookie_Consent_Document,
	Preferences_Banner_Document,
};
use Cookiez\Modules\Elementor\Widgets\{
	Cookie_Consent_Content,
	Cookie_Consent_Footer,
	Cookie_Consent_Heading,
	Preferences_Content,
	Preferences_Footer,
	Preferences_Heading,
};
use Elementor\Core\Base\Document as Elementor_Document;
use Elementor\Core\Documents_Manager;
use Elementor\Elements_Manager;
use Elementor\Widgets_Manager;

class Module extends Module_Base {

	private Popup_Admin_Filter $popup_admin_filter;
	private Banner_Settings_Integration $banner_settings_integration;

	public function get_name(): string {
		return 'Elementor';
	}

	public static function is_active(): bool {
		return defined( 'ELEMENTOR_VERSION' ) && defined( 'ELEMENTOR_PRO_VERSION' );
	}

	public static function routes_list(): array {
		return [
			'Create_Template',
		];
	}

	public function register_documents( Documents_Manager $documents_manager ): void {
		$documents_manager->register_document_type(
			Cookie_Consent_Document::DOCUMENT_TYPE,
			Cookie_Consent_Document::class
		);

		$documents_manager->register_document_type(
			Preferences_Banner_Document::DOCUMENT_TYPE,
			Preferences_Banner_Document::class
		);
	}

	public function register_widget_categories( Elements_Manager $elements_manager ): void {
		$elements_manager->add_category(
			'cookiez',
			[
				'title' => esc_html__( 'Cookie Consent', 'cookiez' ),
				'icon' => 'fa fa-cookie',
			]
		);
	}

	public function register_widgets( Widgets_Manager $widgets_manager ): void {
		$widgets_manager->register( new Cookie_Consent_Heading() );
		$widgets_manager->register( new Cookie_Consent_Content() );
		$widgets_manager->register( new Cookie_Consent_Footer() );
		$widgets_manager->register( new Preferences_Heading() );
		$widgets_manager->register( new Preferences_Content() );
		$widgets_manager->register( new Preferences_Footer() );
	}

	public function register_widget_styles(): void {
		wp_register_style(
			'cookiez-widget-cookie-consent',
			COOKIEZ_ASSETS_URL . 'build/elementor-widgets/cookie-consent.css',
			[],
			COOKIEZ_VERSION
		);
		wp_register_style(
			'cookiez-widget-preferences-banner',
			COOKIEZ_ASSETS_URL . 'build/elementor-widgets/preferences-banner.css',
			[],
			COOKIEZ_VERSION
		);
	}

	public function ensure_cookie_consent_defaults_on_document_save( $document ): void {
		$document_name = Document_Utils::get_document_name( $document );
		if ( null === $document_name || ! method_exists( $document, 'get_main_id' ) ) {
			return;
		}

		$post_id = (int) $document->get_main_id();
		if ( 0 >= $post_id ) {
			return;
		}

		$defaults_map = Document_Utils::get_document_defaults_map();
		if ( isset( $defaults_map[ $document_name ] ) ) {
			$defaults_map[ $document_name ]::set_default_template_data( $post_id );
		}
	}

	public function force_popup_editor_config_for_cookiez_documents( array $additional_config, int $post_id ): array {
		$document_type = get_post_meta( $post_id, Elementor_Document::TYPE_META_KEY, true );

		if ( ! is_string( $document_type ) || ! Document_Utils::is_cookiez_document_type( $document_type ) ) {
			return $additional_config;
		}

		$additional_config['container'] = '.elementor-popup-modal .dialog-widget-content';

		return $additional_config;
	}

	public function force_popup_wrapper_type_for_cookiez_documents( array $attributes, $document ): array {
		$document_name = Document_Utils::get_document_name( $document );
		if ( null === $document_name || ! Document_Utils::is_cookiez_document_type( $document_name ) ) {
			return $attributes;
		}

		$attributes['data-elementor-type'] = 'popup';

		return $attributes;
	}

	public function __construct() {
		$this->register_routes();

		$this->popup_admin_filter = new Popup_Admin_Filter();
		$this->banner_settings_integration = new Banner_Settings_Integration();

		add_action( 'elementor/documents/register', [ $this, 'register_documents' ], 100 );
		add_action( 'elementor/document/after_save', [ $this, 'ensure_cookie_consent_defaults_on_document_save' ], 20, 1 );
		add_filter( 'elementor/document/config', [ $this, 'force_popup_editor_config_for_cookiez_documents' ], 10, 2 );
		add_filter( 'elementor/document/wrapper_attributes', [ $this, 'force_popup_wrapper_type_for_cookiez_documents' ], 10, 2 );

		add_action( 'elementor/elements/categories_registered', [ $this, 'register_widget_categories' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_widget_styles' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'register_widget_styles' ] );

		add_action( 'parse_query', [ $this->popup_admin_filter, 'restrict_popup_admin_list_to_standard_popups' ], 20 );
		add_action( 'admin_head', [ $this->popup_admin_filter, 'hide_standard_popup_subtype_tab' ] );

		add_filter( 'cookiez/banner/settings', [ $this->banner_settings_integration, 'inject_elementor_cookie_consent_id' ] );
		add_filter( 'cookiez/banner/settings', [ $this->banner_settings_integration, 'inject_elementor_preferences_banner_id' ] );

		add_filter( 'cookiez/settings/data', [ $this->banner_settings_integration, 'inject_elementor_cookie_consent_id_for_admin' ] );
		add_filter( 'cookiez/settings/data', [ $this->banner_settings_integration, 'inject_elementor_preferences_banner_id_for_admin' ] );
	}
}
