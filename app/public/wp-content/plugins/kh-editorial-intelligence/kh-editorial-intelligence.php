<?php
/**
 * Plugin Name: KH Editorial Intelligence
 * Description: Orchestration layer for editorial workflow, agentic tools, and content planning.
 * Version: 0.1.0
 * Author: KHM Dev
 * Text Domain: kh-editorial-intelligence
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
define( 'KH_EDITORIAL_VERSION', '0.1.0' );
define( 'KH_EDITORIAL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KH_EDITORIAL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader
spl_autoload_register( function ( $class ) {
    $prefix = 'KH\\Editorial\\';
    $base_dir = KH_EDITORIAL_PLUGIN_DIR . 'src/';

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

// Initialize components
add_action( 'plugins_loaded', function() {
    // Taxonomies
    if ( class_exists( 'KH\\Editorial\\Taxonomies\\EditorialTaxonomy' ) ) {
        KH\Editorial\Taxonomies\EditorialTaxonomy::init();
    }
    
    // Admin UI
    if ( is_admin() && class_exists( 'KH\\Editorial\\Admin\\EditorialAdmin' ) ) {
        $editorial_admin = new KH\Editorial\Admin\EditorialAdmin();
        $editorial_admin->init();
    }

    // AI Worker
    if ( class_exists( 'KH\\Editorial\\Services\\AI\\AIWorker' ) ) {
        $ai_worker = new KH\Editorial\Services\AI\AIWorker();
        $ai_worker->init();
    }
} );
