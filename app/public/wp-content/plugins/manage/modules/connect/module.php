<?php
namespace Manage\Modules\Connect;

use ElementorOne\Connect\Facade;
use Manage\Classes\Client;
use Manage\Classes\Logger;
use Manage\Classes\Module_Base;
use Manage\Classes\System_User;
use Manage\Modules\Settings\Components\Page;
use Manage\Modules\Connect\Classes\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Module extends Module_Base {

	public function get_name(): string {
		return 'connect';
	}

	public static function is_connected(): bool {
		$facade = self::get_connect();
		$access_token = $facade->data()->get_access_token();

		return ! ! $access_token && $facade->utils()->is_valid_home_url();
	}

	public function __construct() {
		add_action( 'elementor_one/manage_connected', [ $this, 'on_connect' ] );
		add_action( 'elementor_one/manage_migration_run', [ $this, 'on_migration_run' ] );
		add_action( 'permalink_structure_changed', [ $this, 'on_permalink_structure_changed' ], 10, 0 );

		// Disable license check for Manage (Free version)
		add_filter( 'elementor_one/' . Config::APP_PREFIX . '_license_check_enabled', '__return_false' );

		Facade::make( [
			'app_name' => Config::APP_NAME,
			'app_prefix' => Config::APP_PREFIX,
			'app_rest_namespace' => Config::APP_REST_NAMESPACE,
			'base_url' => Config::BASE_URL,
			'admin_page' => Config::ADMIN_PAGE,
			'app_type' => Config::APP_TYPE,
			'scopes' => Config::SCOPES,
			'state_nonce' => Config::STATE_NONCE,
			'connect_mode' => Config::CONNECT_MODE,
			'plugin_slug' => Config::PLUGIN_SLUG,
		] );
	}

	public static function get_connect(): Facade {
		return Facade::get( Config::PLUGIN_SLUG );
	}

	public function on_connect() {
		$system_user = System_User::autofix_system_user();
		if ( is_wp_error( $system_user ) ) {
			Logger::error( 'Failed to autofix system user on connection: ' . esc_html( $system_user->get_error_message() ) );
		}

		$site_registered = Client::register_website();
		if ( is_wp_error( $site_registered ) ) {
			Logger::error( 'Failed to register website: ' . esc_html( $site_registered->get_error_message() ) );
		}

		$has_errors = is_wp_error( $system_user ) || is_wp_error( $site_registered );

		if ( ! $has_errors ) {
			$redirect_url = add_query_arg( [ 'ref' => 'connect' ], Page::get_manage_all_sites_url() );
			wp_redirect( $redirect_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}
	}

	public function on_migration_run() {
		$system_user = System_User::autofix_system_user();
		if ( is_wp_error( $system_user ) ) {
			Logger::error( 'Failed to autofix system user on migration run: ' . esc_html( $system_user->get_error_message() ) );
		}

		$site_registered = Client::register_website();
		if ( is_wp_error( $site_registered ) ) {
			Logger::error( 'Failed to register website on migration run: ' . esc_html( $site_registered->get_error_message() ) );
		}
	}

	public function on_permalink_structure_changed() {
		if ( ! static::is_connected() ) {
			return;
		}

		$site_registered = Client::register_website();
		if ( is_wp_error( $site_registered ) ) {
			Logger::error( 'Failed to re-register website after permalink change: ' . esc_html( $site_registered->get_error_message() ) );
		}
	}

	public static function is_elementor_one(): bool {
		return self::get_connect()->get_config( 'app_type' ) !== Config::APP_TYPE;
	}
}
