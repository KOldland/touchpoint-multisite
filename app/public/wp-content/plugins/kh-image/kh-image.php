<?php
/**
 * Plugin Name: KH-Image Optimizer
 * Plugin URI:  https://example.com/kh-image
 * Description: Modern image optimization engine for the KH marketing stack.
 * Version:     0.1.0
 * Author:      KH Engineering
 * Author URI:  https://example.com
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kh-image
 *
 * @package KHImage
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'KH_IMAGE_FILE' ) ) {
	define( 'KH_IMAGE_FILE', __FILE__ );
}

if ( ! defined( 'KH_IMAGE_DIR' ) ) {
	define( 'KH_IMAGE_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'KH_IMAGE_URL' ) ) {
	define( 'KH_IMAGE_URL', plugin_dir_url( __FILE__ ) );
}

$kh_image_autoloader = KH_IMAGE_DIR . 'src/Autoloader.php';
if ( file_exists( $kh_image_autoloader ) ) {
	require_once $kh_image_autoloader;
	\KHImage\Autoloader::register();
} else {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p><strong>kh-image</strong> plugin: missing files (src/Autoloader.php). Plugin disabled until resolved.</p></div>';
		}
	);
	return;
}

/**
 * Boot the plugin and load translations after init to satisfy WP 6.7 timing rules.
 */
function kh_image_bootstrap() {
	load_plugin_textdomain(
		'kh-image',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	\KHImage\Core\Plugin::instance();
}
add_action( 'init', 'kh_image_bootstrap' );

register_activation_hook(
	KH_IMAGE_FILE,
	function () {
		kh_image_bootstrap();
		\KHImage\Core\Plugin::instance()->activate();
	}
);
register_deactivation_hook(
	KH_IMAGE_FILE,
	function () {
		\KHImage\Core\Plugin::instance()->deactivate();
	}
);
