<?php

namespace Cookiez\Modules\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Cookiez\Classes\Logger;
use Cookiez\Classes\Module_Base;
use Cookiez\Classes\Utils;
use Cookiez\Modules\Connect\Classes\Config;
use Cookiez\Modules\Connect\Module as Connect;
use Cookiez\Modules\Core\Components\Notices;
use Cookiez\Modules\Settings\Banners\Elementor_Birthday_Banner;
use Cookiez\Modules\Settings\Classes\Settings;
use Throwable;
use Exception;
use WP_Error;

/**
 * Module `Settings`
 */
class Module extends Module_Base {
	const SETTING_BASE_SLUG = 'cookiez-settings';
	const SETTING_CAPABILITY = 'manage_options';

	public function get_name(): string {
		return 'Settings';
	}

	/**
	 * Declare components list
	 *
	 * @return array
	 */
	public static function component_list(): array {
		return [
			'Settings_Pointer',
		];
	}

	/**
	 * Get Plugin ENV
	 * @return string
	 */
	private static function get_plugin_env(): string {
		return apply_filters( 'cookiez_settings_plugin_env', 'production' );
	}

	public function render_app() {
		?>
		<?php Elementor_Birthday_Banner::get_banner( 'https://go.elementor.com/cookiez-10th-bd-sale' ); ?>

		<!-- The hack required to wrap WP notifications -->
		<div class="wrap">
			<h1 style="display: none;" role="presentation"></h1>
		</div>

		<div id="cookiez-app"></div>
		<?php
	}

	public function register_page() {
		add_submenu_page(
			'elementor-home',
			__( 'Cookie Consent', 'cookiez' ),
			__( 'Cookie Consent', 'cookiez' ),
			self::SETTING_CAPABILITY,
			self::SETTING_BASE_SLUG,
			[ $this, 'render_app' ],
			30
		);
	}

	/**
	 * Enqueue Scripts and Styles
	 */
	public function enqueue_scripts(): void {
		if ( ! Utils::is_plugin_page() ) {
			return;
		}

		/**
		 * These styles are braking MUI component styling.
		 * Re-registering as empty so wp-admin's dependency doesn't break
	 */
		wp_deregister_style( 'forms' );
		wp_register_style( 'forms', false );

		self::refresh_plan_data();

		wp_enqueue_style(
			'cookiez-admin-fonts',
			'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
			[],
			COOKIEZ_VERSION
		);

		Utils\Assets::enqueue_app_assets( 'settings' );

		$settings_data = [
			'isConnected' => Connect::is_connected(),
			'isUrlMismatch' => ! Connect::get_connect()->utils()->is_valid_home_url(),
			'wpRestNonce' => wp_create_nonce( 'wp_rest' ),
			'restRoot' => rest_url(),
			'pluginEnv' => self::get_plugin_env(),
			'isDevelopment' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			'appSlug' => Config::PLUGIN_SLUG,
			'appVersion' => COOKIEZ_VERSION,
			'isRTL' => is_rtl(),
			'dateFormat' => get_option( 'date_format', 'F j, Y' ),
			'timeFormat' => get_option( 'time_format', 'g:i a' ),
			'translations' => Utils::get_translations(),
			'isElementorOne' => self::is_elementor_one(),
			'settings' => Settings::get( Settings::COOKIEZ_SETTINGS ),
			'content' => Settings::get( Settings::COOKIEZ_CONTENT ),
			'planData' => get_option( Settings::PLAN_DATA ),
			'planScope' => get_option( Settings::PLAN_SCOPE ),
			'isElementorActive' => defined( 'ELEMENTOR_VERSION' ),
			'isElementorProActive' => defined( 'ELEMENTOR_PRO_VERSION' ),
		];

		$settings_data = apply_filters( 'cookiez/settings/data', $settings_data );

		wp_localize_script(
			'settings',
			'cookiezSettingsData',
			$settings_data
		);
	}

	/**
	 * Check if elementor one
	 * @return bool
	 */
	public static function is_elementor_one(): bool {
		return Connect::get_connect()->get_config( 'app_type' ) !== Config::APP_TYPE;
	}

	/**
	 * Get all plugin settings data
	 * @return array
	 * @throws Throwable
	 */
	public static function get_plugin_settings(): array {
		$content = Settings::get( Settings::COOKIEZ_CONTENT );

		return array_merge(
			[
				'isDevelopment' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
				'siteUrl' => wp_parse_url( get_site_url(), PHP_URL_HOST ),
				'settings' => Settings::get( Settings::COOKIEZ_SETTINGS ),
			],
			is_array( $content ) ? $content : []
		);
	}

	public static function routes_list(): array {
		return [
			'Get_Settings',
			'Update_Settings',
			'Get_Consent_Logs',
			'Export_Consent_Logs',
		];
	}

	/**
	 * @throws Exception
	 */
	public function on_connect(): void {
		if ( ! Connect::is_connected() ) {
			return;
		}

		self::register_site_with_data();
	}

	/**
	 * Fetch and save plan data on Elementor One app_cookie connection.
	 * @return void
	 */
	public function on_app_cookie_connected(): void {
		if ( ! Connect::is_connected() ) {
			return;
		}

		$plan_data = get_option( Settings::PLAN_DATA );

		$response = Utils::get_api_client()->make_request(
			'POST',
			'sites/info'
		);

		self::save_plan_data( $response );
	}

	/**
	 * On disconnect
	 * @return void
	 */
	public function on_disconnect() {
		delete_option( Settings::SUBSCRIPTION_ID );
	}

	/**
	 * Register or update site data for One connect
	 * @throws Exception
	 */
	public function on_migration_run() {
		if ( ! Connect::is_connected() ) {
			return;
		}

		$client_id = Settings::get( Settings::CLIENT_ID );

		if ( $client_id ) {
			try {
				$migration_response = Utils::get_api_client()->make_request(
					'POST',
					'site/migration',
					[ 'old_client_id' => $client_id ],
				);

				self::save_plan_data( $migration_response );

				$old_options = [
					'cookiez_client_secret',
					'cookiez_home_url',
					'cookiez_access_token',
					'cookiez_token_id',
					'cookiez_refresh_token',
					'cookiez_user_access_token',
					'cookiez_owner_user_id',
					Settings::SUBSCRIPTION_ID,
					Settings::CLIENT_ID,
				];

				foreach ( $old_options as $option ) {
					delete_option( $option );
				}
			} catch ( Throwable $t ) {
				Logger::error( esc_html( $t->getMessage() ) );
			}
		} else {
			$this->on_connect();
		}
	}

	/**
	 * Register the website and save the plan data.
	 * @return void
	 */
	public static function register_site_with_data(): void {
		$register_response = Utils::get_api_client()->make_request(
			'POST',
			'site/register'
		);

		if ( is_wp_error( $register_response ) ) {
			Logger::error( esc_html( $register_response->get_error_message() ) );
		} else {
			self::save_plan_data( $register_response );
			if ( isset( $register_response->scopes ) ) {
				Settings::set( Settings::PLAN_SCOPE, $register_response->scopes );
			}
		}
	}

	/**
	 * Refresh the plan data if the refresh transient has expired.
	 * @return void
	 */
	public static function refresh_plan_data(): void {
		if ( ! Connect::is_connected() ) {
			return;
		}

		if ( self::get_plan_data_refresh_transient() ) {
			return;
		}

		$plan_data = get_option( Settings::PLAN_DATA );

		if ( empty( $plan_data->public_api_key ) ) {
			Logger::error( 'Cannot refresh the plan data. No public API key found.' );
			self::register_site_with_data();
			return;
		}

		$response = Utils::get_api_client()->make_request(
			'POST',
			'site/info'
		);

		self::save_plan_data( $response );
	}

	public static function set_plan_data_refresh_transient(): void {
		set_transient( Settings::PLAN_DATA_REFRESH_TRANSIENT, true, MINUTE_IN_SECONDS * 15 );
	}

	public static function get_plan_data_refresh_transient(): bool {
		return get_transient( Settings::PLAN_DATA_REFRESH_TRANSIENT );
	}

	public static function delete_plan_data_refresh_transient(): bool {
		return delete_transient( Settings::PLAN_DATA_REFRESH_TRANSIENT );
	}

	/**
	 * Save plan data to plan_data option
	 * @param $register_response
	 *
	 * @return void
	 */
	public static function save_plan_data( $register_response ): void {
		if ( $register_response && ! is_wp_error( $register_response ) ) {
			$decoded_response = $register_response;

			update_option( Settings::SUBSCRIPTION_ID, $decoded_response->plan->subscription_id );
			update_option( Settings::PLAN_DATA, $decoded_response );
			update_option( Settings::IS_VALID_PLAN_DATA, true );

			self::set_plan_data_refresh_transient();
		} else {
			Logger::error( $register_response instanceof WP_Error ? esc_html( $register_response->get_error_message() ) : $register_response );
			update_option( Settings::IS_VALID_PLAN_DATA, false );
		}
	}

	/**
	 * Get upgrade link with UTM parameters
	 *
	 * @param string $campaign Campaign identifier for tracking.
	 * @return string
	 */
	public static function get_upgrade_link( string $campaign ): string {
		$subscription_id = get_option( Settings::SUBSCRIPTION_ID );

		if ( $subscription_id ) {
			return add_query_arg(
				[
					'utm_source'      => $campaign . '-upgrade',
					'utm_medium'      => 'wp-dash',
					'subscription_id' => $subscription_id,
				],
				'https://go.elementor.com/' . $campaign
			);
		}

		return add_query_arg(
			[
				'utm_source' => $campaign . '-upgrade',
				'utm_medium' => 'wp-dash',
			],
			'https://go.elementor.com/' . $campaign
		);
	}

	/**
	 * Cookie consent quota usage percentage (cookie_consents only).
	 *
	 * @return float Percent used (0–100+), or 0 if unavailable.
	 */
	public static function get_consent_quota_usage_percent(): float {
		$plan_data = get_option( Settings::PLAN_DATA );

		if ( ! $plan_data || ! isset( $plan_data->quota->cookie_consents ) ) {
			return 0;
		}

		$consents = $plan_data->quota->cookie_consents;

		if ( ! isset( $consents->allowed, $consents->used ) || $consents->allowed <= 0 ) {
			return 0;
		}

		return round( $consents->used / $consents->allowed * 100, 2 );
	}

	/**
	 * Register quota notices with the notice manager.
	 *
	 * @param Notices $notice_manager The notice manager instance.
	 * @return void
	 */
	public function register_notices( Notices $notice_manager ): void {
		if ( self::is_elementor_one() ) {
			return;
		}

		if ( ! Connect::is_connected() && ! get_option( Settings::PLAN_DATA ) ) {
			return;
		}

		$notices = [
			'Quota_80',
			'Quota_100',
		];

		foreach ( $notices as $notice ) {
			$class_name = 'Cookiez\Modules\Settings\Notices\\' . $notice;
			$notice_manager->register_notice( new $class_name() );
		}
	}

	public function __construct() {
		$this->register_components();
		$this->register_routes();

		add_action( 'admin_menu', [ $this, 'register_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		add_action( 'elementor_one/' . Config::APP_PREFIX . '_connected', [ $this, 'on_connect' ] );
		add_action( 'elementor_one/' . Config::APP_PREFIX . '_disconnected', [ $this, 'on_disconnect' ] );
		add_action( 'elementor_one/' . Config::APP_PREFIX . '_migration_run', [ $this, 'on_migration_run' ] );
		add_action( 'elementor_one/' . Config::APP_TYPE . '_connected', [ $this, 'on_app_cookie_connected' ] );

		add_action( 'cookiez_register_notices', [ $this, 'register_notices' ] );
	}
}
