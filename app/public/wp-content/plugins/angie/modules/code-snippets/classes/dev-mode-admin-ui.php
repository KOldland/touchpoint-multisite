<?php
namespace Angie\Modules\CodeSnippets\Classes;

use Angie\Modules\CodeSnippets\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dev_Mode_Admin_UI {

	public static function init() {
		add_action( 'admin_notices', [ __CLASS__, 'render_dev_mode_notice' ] );
		add_filter( 'angie_config', [ __CLASS__, 'add_dev_mode_state_to_angie_config' ] );
	}

	public static function render_dev_mode_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . Module::CPT_NAME !== $screen->id ) {
			return;
		}

		if ( ! Module::current_user_can_manage_snippets() ) {
			return;
		}

		$is_dev_mode = Dev_Mode_Manager::is_dev_mode_enabled();

		if ( $is_dev_mode ) {
			echo '<div class="notice notice-warning">';
			echo '<p>';
			echo '<strong>' . esc_html__( 'Test Mode is Active', 'angie' ) . '</strong> - ' . esc_html__( 'Snippets are loading from the development environment.', 'angie' ) . ' ';
			echo '<button type="button" class="button button-secondary" id="angie-disable-dev-mode">' . esc_html__( 'Disable Test Mode', 'angie' ) . '</button>';
			echo '</p>';
			echo '</div>';
		} else {
			echo '<div class="notice notice-info">';
			echo '<p>';
			echo esc_html__( 'Snippets are loading from production.', 'angie' ) . ' ';
			echo '<button type="button" class="button button-primary" id="angie-enable-dev-mode">' . esc_html__( 'Enable Test Mode', 'angie' ) . '</button>';
			echo '</p>';
			echo '</div>';
		}
	}

	public static function add_dev_mode_state_to_angie_config( $angie_config ) {
		$angie_config['isDevModeEnabled'] = Dev_Mode_Manager::is_dev_mode_enabled();
		return $angie_config;
	}
}
