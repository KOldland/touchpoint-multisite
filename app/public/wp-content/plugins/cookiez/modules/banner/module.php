<?php

namespace Cookiez\Modules\Banner;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Cookiez\Classes\Module_Base;
use Cookiez\Classes\Utils;
use Cookiez\Modules\Banner\Dynamic_Tags\Preferences_Trigger;
use Cookiez\Modules\Connect\Module as Connect_Module;
use Cookiez\Modules\Cookie\Database\Cookie_Entry;
use Cookiez\Modules\Settings\Classes\Sanitize_Content;
use Cookiez\Modules\Settings\Classes\Settings;

/**
 * Module `Banner`
 */
class Module extends Module_Base {

	public const CONSENT_COOKIE_NAME = 'cookiez-user-consent';

	private const SCANNER_USER_AGENT_MARKERS = [
		'Cookiez/',
		'elementor.com',
	];

	private array $page_cookies = [];

	public function get_name(): string {
		return 'Banner';
	}

	public static function component_list(): array {
		return [
			'Gutenberg_Preferences_Link_Block',
			'Script_Blocker',
			'Script_Blocker_Fallback',
		];
	}

	/**
	 * Get service URL
	 * @return string
	 */
	public static function get_service_api_url(): string {
		return apply_filters( 'cookiez_service_api_url', 'https://my.elementor.com/apps/api/v1/cookiez-consent' );
	}

	/**
	 * Enqueue Scripts and Styles
	 */
	public function enqueue_scripts(): void {
		if ( Utils::is_elementor_editor() ) {
			return;
		}

		Utils\Assets::enqueue_app_assets( 'banner', false );

		$content            = Settings::get( Settings::COOKIEZ_CONTENT );
		$default_language   = $content['languages'][0] ?? Sanitize_Content::FALLBACK_LANGUAGE;
		$disabled_languages = $content['disabledLanguages'] ?? [];

		$banner_settings = [
			'isDevelopment' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			'isRTL'         => is_rtl(),
			'language'      => Sanitize_Content::resolve_language( get_locale(), $default_language, $disabled_languages ),
			'subscription'  => get_option( Settings::SUBSCRIPTION_ID ),
			'planData'      => get_option( Settings::PLAN_DATA ),
			'url'           => Utils::get_current_page_url(),
			'serviceUrl'    => self::get_service_api_url(),
			'settings'      => Settings::get( Settings::COOKIEZ_SETTINGS ),
			'content'       => $content,
			'cookies'       => $this->page_cookies,
			'translations'  => Utils::get_translations(),
			'cookiesHash'   => self::get_cookies_hash(),
		];

		$banner_settings = apply_filters( 'cookiez/banner/settings', $banner_settings );

		wp_localize_script(
			'banner',
			'cookiezBannerSettings',
			$banner_settings
		);
	}


	public static function should_blocker_run(): bool {
		if ( is_admin() ) {
			return false;
		}

		if ( self::is_cookiez_scanner_request() ) {
			return false;
		}

		if ( ! Connect_Module::is_connected() ) {
			return false;
		}

		$settings = Settings::get( Settings::COOKIEZ_SETTINGS );

		if ( array_key_exists( 'bannerDisplayStatus', $settings ) && ! $settings['bannerDisplayStatus'] ) {
			return false;
		}

		$disabled_pages = $settings['disableBannerPages'] ?? [];

		if ( empty( $disabled_pages ) || ! is_array( $disabled_pages ) ) {
			return true;
		}

		$current_url = home_url( add_query_arg( null, null ) );

		foreach ( $disabled_pages as $url ) {
			if ( trailingslashit( $current_url ) === trailingslashit( $url ) ) {
				return false;
			}
		}

		return true;
	}

	private static function is_cookiez_scanner_request(): bool {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}

		$ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );

		foreach ( self::SCANNER_USER_AGENT_MARKERS as $marker ) {
			if ( ! str_contains( $ua, $marker ) ) {
				return false;
			}
		}

		return true;
	}

	// Check if cookies or cookies category was changed
	private function get_cookies_hash(): string {
		$signature = array_map(
			fn( $cookie ) => $cookie->name . ':' . $cookie->category,
			$this->page_cookies
		);
		sort( $signature );

		return md5( implode( '|', $signature ) );
	}

	private function should_show_banner(): bool {
		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::CONSENT_COOKIE_NAME ] ?? '' ) );
		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) || empty( $decoded['meta'] ) ) {
			return true;
		}

		$settings = Settings::get( Settings::COOKIEZ_SETTINGS );
		$meta = $decoded['meta'];
		$expiration_days = $settings['consentExpiration'] ?? 180;
		$consent_time = $meta['timestamp'] ?? 0;
		$is_expired = ( time() - $consent_time ) > ( $expiration_days * DAY_IN_SECONDS );

		if ( $is_expired ) {
			return true;
		}

		$stored_hash = $meta['cookiesHash'] ?? '';

		return $stored_hash !== $this->get_cookies_hash();
	}

	private function clear_consent_cookie(): void {
		setcookie(
			self::CONSENT_COOKIE_NAME,
			'',
			time() - HOUR_IN_SECONDS,
			'/'
		);
		unset( $_COOKIE[ self::CONSENT_COOKIE_NAME ] );
	}

	/**
	 * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager Elementor dynamic tags manager.
	 * @return void
	 */
	public function register_dynamic_tag( $dynamic_tags_manager ): void {
		if ( ! class_exists( '\Elementor\Core\DynamicTags\Data_Tag' ) ) {
			return;
		}

		$dynamic_tags_manager->register( new Preferences_Trigger() );
	}

	/**
	 * Registers Elementor Pro URL action handler (inline script on a dedicated handle).
	 *
	 * @return void
	 */
	public function enqueue_elementor_prefs_url_action(): void {
		if ( is_admin() ) {
			return;
		}

		wp_add_inline_script( 'banner', "
			(function() {
				const registerCookiezPreferencesAction = () => {
					if ( ! window?.ElementorProFrontendConfig || ! window?.elementorFrontend?.utils?.urlActions ) {
						return;
					}

					elementorFrontend.utils.urlActions.addAction( 'cookiezBanner:openPreferences', () => {
						window?.cookiezBanner?.screenManager?.openPreferences?.();
					} );
				};

				const waitingLimit = 30;
				let retryCounter = 0;

				const waitForElementorPro = () => {
					return new Promise( ( resolve ) => {
						const intervalId = setInterval( () => {
							if ( retryCounter === waitingLimit ) {
								resolve( null );
							}

							retryCounter++;

							if ( window.elementorFrontend && window?.elementorFrontend?.utils?.urlActions ) {
								clearInterval( intervalId );
								resolve( window.elementorFrontend );
							}
						}, 100 );
					});
				};

				waitForElementorPro().then( () => { registerCookiezPreferencesAction(); } );
			}());
		" );
	}

	public function __construct() {
		if ( ! self::should_blocker_run() ) {
			return;
		}

		$this->page_cookies = Cookie_Entry::find_all();

		if ( $this->should_show_banner() ) {
			$this->clear_consent_cookie();
		}

		$this->register_components();

		add_action( 'elementor/dynamic_tags/register', [ $this, 'register_dynamic_tag' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_elementor_prefs_url_action' ], 20 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}
}
