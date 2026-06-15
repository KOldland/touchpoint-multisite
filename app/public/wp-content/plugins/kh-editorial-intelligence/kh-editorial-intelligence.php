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
    // 1. Service Container (Boot First)
    if ( class_exists( 'KH\\Editorial\\Core\\Container' ) ) {
        KH\Editorial\Core\Container::boot();
    }

    // 2. Database Migrations
    if ( class_exists( 'KH\\Editorial\\Database\\AtomicEmbeddingsMigration' ) ) {
        KH\Editorial\Database\AtomicEmbeddingsMigration::run();
    }

    // 2b. Content Allocation DB Table
    if ( class_exists( 'KH\\Editorial\\Database\\AllocationTable' ) ) {
        KH\Editorial\Database\AllocationTable::install();
    }

    // 3. Taxonomies
    if ( class_exists( 'KH\\Editorial\\Taxonomies\\EditorialTaxonomy' ) ) {
        KH\Editorial\Taxonomies\EditorialTaxonomy::init();
    }
    
    // 4. Admin UI
    if ( is_admin() && class_exists( 'KH\\Editorial\\Admin\\EditorialAdmin' ) ) {
        $editorial_admin = new KH\Editorial\Admin\EditorialAdmin();
        $editorial_admin->init();
    }

    // 5. Atomic Article System (GEO tool)
    if ( class_exists( 'KH\\Editorial\\PostTypes\\AtomicArticlePostType' ) ) {
        ( new KH\Editorial\PostTypes\AtomicArticlePostType() )->register();
    }
    if ( class_exists( 'KH\\Editorial\\Services\\GEO\\AtomicArticleGenerator' ) ) {
        ( new KH\Editorial\Services\GEO\AtomicArticleGenerator() )->register();
    }
    if ( class_exists( 'KH\\Editorial\\Admin\\AtomicMetaBox' ) ) {
        ( new KH\Editorial\Admin\AtomicMetaBox() )->register();
    }
    if ( class_exists( 'KH\\Editorial\\Services\\GEO\\AtomicEmbeddingService' ) ) {
        ( new KH\Editorial\Services\GEO\AtomicEmbeddingService() )->register();
    }
    if ( class_exists( 'KH\\Editorial\\Services\\GEO\\AtomicSchemaEmitter' ) ) {
        ( new KH\Editorial\Services\GEO\AtomicSchemaEmitter() )->register();
    }
    if ( class_exists( 'KH\\Editorial\\API\\AtomicRegenerateEndpoint' ) ) {
        ( new KH\Editorial\API\AtomicRegenerateEndpoint() )->register();
    }
    if ( class_exists( 'KH\\Editorial\\API\\AtomicSearchEndpoint' ) ) {
        ( new KH\Editorial\API\AtomicSearchEndpoint() )->register();
    }
    if ( class_exists( 'KH\\Editorial\\Services\\GEO\\AtomicSearchWidget' ) ) {
        ( new KH\Editorial\Services\GEO\AtomicSearchWidget() )->register();
    }

    // 6. AI Worker
    if ( class_exists( 'KH\\Editorial\\Services\\AI\\AIWorker' ) ) {
        $ai_worker = new KH\Editorial\Services\AI\AIWorker();
        $ai_worker->init();
    }

    // 7. Recommendation Agent (Initialize via Container Singleton)
    if ( KH\Editorial\Core\Container::has( 'RecommendationAgent' ) ) {
        KH\Editorial\Core\Container::get( 'RecommendationAgent' )->init();
    }

     // 8. REST API
    if ( class_exists( 'KH\\Editorial\\API\\Rest_Api' ) ) {
        $rest_api = new KH\Editorial\API\Rest_Api();
        $rest_api->init();
    }

    // 9. Content Allocation System
    if ( class_exists( 'KH\\Editorial\\API\\AllocationEndpoints' ) ) {
        ( new KH\Editorial\API\AllocationEndpoints() )->register();
    }
    if ( is_admin() && class_exists( 'KH\\Editorial\\Admin\\AllocationMetaBox' ) ) {
        ( new KH\Editorial\Admin\AllocationMetaBox() )->init();
    }

     // 7. Gutenberg Editor Assets
    add_action( 'enqueue_block_editor_assets', function() {
        $script_path = KH_EDITORIAL_PLUGIN_DIR . 'assets/js/editor-image-sidebar.js';
        if ( ! file_exists( $script_path ) ) {
            return;
        }
        wp_enqueue_script(
            'kh-editorial-image-sidebar',
            KH_EDITORIAL_PLUGIN_URL . 'assets/js/editor-image-sidebar.js',
            [ 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ],
            filemtime( $script_path ),
            true
        );
        wp_localize_script( 'kh-editorial-image-sidebar', 'khEditorialSettings', \KH\Editorial\Admin\EditorialAdmin::get_sidebar_settings() );

} );
} );
