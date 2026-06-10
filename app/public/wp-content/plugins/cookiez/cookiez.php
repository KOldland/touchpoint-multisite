<?php
/**
 * Plugin Name: Cookie Consent - GDPR & CCPA Cookie Banner & Consent Manager
 * Description: Simplify cookie consent with a customizable banner that helps you cover global privacy laws like GDPR and CCPA. Scan your site for cookies, block scripts based on visitor preferences, and keep audit-ready logs of every choice.
 * Plugin URI: https://elementor.com/
 * Version: 0.0.6
 * Author: Elementor.com
 * Text Domain: cookiez
 * License: GPLv3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'COOKIEZ_VERSION', '0.0.6' );
define( 'COOKIEZ_PLUGIN_BASE', plugin_basename( __FILE__ ) );
define( 'COOKIEZ_PATH', plugin_dir_path( __FILE__ ) );
define( 'COOKIEZ_URL', plugins_url( '/', __FILE__ ) );
define( 'COOKIEZ_ASSETS_PATH', COOKIEZ_PATH . 'assets/' );
define( 'COOKIEZ_ASSETS_URL', COOKIEZ_URL . 'assets/' );

if ( ! defined( 'COOKIEZ_MINIMUM_LOG_LEVEL' ) ) {
	define( 'COOKIEZ_MINIMUM_LOG_LEVEL', 3 );
}

/**
 *  Cookiez Class
 *
 */
final class Cookiez {

	/**
	 * Constructor
	 *
	 * @access public
	 */
	public function __construct() {
		require_once COOKIEZ_PATH . 'vendor/autoload.php';
		require_once COOKIEZ_PATH . 'classes/plugin-activation.php';

		add_action( 'plugins_loaded', [ $this, 'init' ] );
		new \Cookiez\Classes\Plugin_Activation( __FILE__ );
	}

	/**
	 * Initialize the plugin
	 *
	 * Do your Validations here:
	 * for example checks for basic plugin requirements, if one check fail don't continue,
	 * if all check have passed include the plugin class.
	 *
	 * Fired by `plugins_loaded` action hook.
	 *
	 * @since 1.2.0
	 * @access public
	 */
	public function init() {
		// Once we get here, We have passed all validation checks, so we can safely include our plugin
		require_once 'plugin.php';
	}
}
// Instantiate Cookiez..
new Cookiez();
