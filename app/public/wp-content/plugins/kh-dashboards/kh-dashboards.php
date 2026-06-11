<?php
/**
 * Plugin Name: KH Dashboards
 * Description: Consolidated admin dashboards for Sponsorship & Promotion, Editorial Studio, and Commerce.
 * Version: 1.0.0
 * Author: KHM Dev
 * Text Domain: kh-dashboards
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KH_DASHBOARDS_VERSION', '1.0.0' );
define( 'KH_DASHBOARDS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KH_DASHBOARDS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader
spl_autoload_register( function ( $class ) {
    $prefix = 'KH\\Dashboards\\';
    $base_dir = KH_DASHBOARDS_PLUGIN_DIR . 'src/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

// Initialize
add_action( 'plugins_loaded', function () {
    // Menu manager — registers 3 top-level menus + hides old items
    $menu_manager = new \KH\Dashboards\Admin\MenuManager();
    $menu_manager->init();

    // REST API endpoints for dashboard summary stats
    $stats_controller = new \KH\Dashboards\Api\StatsController();
    $stats_controller->init();
} );