<?php
/**
 * Plugin Name: KH Social Media Management & Automation (KH-SMMA)
 * Description: Adds a Hootsuite-inspired social scheduling and automation layer that integrates with KH Ad Manager, Marketing Suite, and SEO modules.
 * Version: 0.1.0
 * Author: KH Marketing Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'KH_SMMA_PATH' ) ) {
    define( 'KH_SMMA_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'KH_SMMA_URL' ) ) {
    define( 'KH_SMMA_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'KH_SMMA_VERSION' ) ) {
    define( 'KH_SMMA_VERSION', '0.1.0' );
}

require_once KH_SMMA_PATH . 'src/Plugin.php';

/**
 * Boot the plugin after init so translations load at the correct time.
 */
function kh_smma_bootstrap() {
    load_plugin_textdomain(
        'kh-smma',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );

    $plugin = new \KH_SMMA\Plugin();
    $plugin->register();
}
add_action( 'init', 'kh_smma_bootstrap' );

register_activation_hook( __FILE__, array( '\\KH_SMMA\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\KH_SMMA\\Plugin', 'deactivate' ) );
