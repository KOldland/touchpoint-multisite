<?php
/**
 * Plugin Name: KH Editorial Planner
 * Description: Dedicated workspace for content planning, article frameworks, and agentic brief generation.
 * Version: 0.1.0
 * Author: KHM Dev
 * Text Domain: kh-editorial-planner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
define( 'KH_PLANNER_VERSION', '0.1.0' );
define( 'KH_PLANNER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KH_PLANNER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader
spl_autoload_register( function ( $class ) {
    $prefix = 'KH\\Planner\\';
    $base_dir = KH_PLANNER_PLUGIN_DIR . 'src/';

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

// Initialize Planner Components
add_action( 'plugins_loaded', function() {
    // 1. Register CPT
    if ( class_exists( 'KH\\Planner\\PostTypes\\PlannerSession' ) ) {
        KH\Planner\PostTypes\PlannerSession::init();
    }
    
    // 2. Register REST API
    if ( class_exists( 'KH\\Planner\\API\\PlannerEndpoints' ) ) {
        $endpoints = new KH\Planner\API\PlannerEndpoints();
        $endpoints->init();
    }

    // 3. Register Admin Workspace
    if ( is_admin() && class_exists( 'KH\\Planner\\Admin\\PlannerWorkspace' ) ) {
        $workspace = new KH\Planner\Admin\PlannerWorkspace();
        $workspace->init();
    }
    
    // 4. Initialize Agentic Orchestrator (The Brain)
    if ( class_exists( 'KH\\Planner\\Agents\\PlannerOrchestrator' ) ) {
        KH\Planner\Agents\PlannerOrchestrator::init();
    }
} );

// Create custom DB tables on activation
register_activation_hook( __FILE__, function() {
    if ( class_exists( 'KH\\Planner\\Core\\CitationStore' ) ) {
        KH\Planner\Core\CitationStore::create_table();
    }
    if ( class_exists( 'KH\\Planner\\Core\\BriefStore' ) ) {
        KH\Planner\Core\BriefStore::create_table();
        KH\Planner\Core\BriefStore::create_exports_table();
    }
} );
