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

    // 2c. Search Log Schema
    if ( class_exists( 'KH\\Editorial\\Database\\SearchLogSchema' ) ) {
        KH\Editorial\Database\SearchLogSchema::install();
    }

    // 2d. Migration Runner Schema
    if ( class_exists( 'KH\\Editorial\\Database\\MigrationRunner' ) ) {
        $migration_runner = new KH\Editorial\Database\MigrationRunner();
        // Install the tracking table (idempotent — uses IF NOT EXISTS).
        // Migrations are executed manually via the admin UI, not automatically.
        $migration_runner->install_schema();
    }

    // 3. Taxonomies
    if ( class_exists( 'KH\\Editorial\\Taxonomies\\EditorialTaxonomy' ) ) {
        KH\Editorial\Taxonomies\EditorialTaxonomy::init();
    }
    
    // 4. Admin UI
    if ( function_exists( 'khm_can_show_admin_ui' ) && khm_can_show_admin_ui() && class_exists( 'KH\\Editorial\\Admin\\EditorialAdmin' ) ) {
        $editorial_admin = new KH\Editorial\Admin\EditorialAdmin();
        $editorial_admin->init();
    } elseif ( is_admin() && class_exists( 'KH\\Editorial\\Admin\\EditorialAdmin' ) ) {
        // Fallback when lockdown not active (single site or earlier WP version)
        $editorial_admin = new KH\Editorial\Admin\EditorialAdmin();
        $editorial_admin->init();
    }

    // 5. Atomic Article System (GEO tool)
    // Post types and services are for content creation - only on main site
    if ( function_exists( 'khm_can_show_admin_ui' ) && khm_can_show_admin_ui() && class_exists( 'KH\\Editorial\\PostTypes\\AtomicArticlePostType' ) ) {
        ( new KH\Editorial\PostTypes\AtomicArticlePostType() )->register();
    } elseif ( is_admin() && class_exists( 'KH\\Editorial\\PostTypes\\AtomicArticlePostType' ) ) {
        // Fallback when lockdown not active
        ( new KH\Editorial\PostTypes\AtomicArticlePostType() )->register();
    }
    if ( class_exists( 'KH\\Editorial\\Services\\GEO\\AtomicArticleGenerator' ) ) {
        ( new KH\Editorial\Services\GEO\AtomicArticleGenerator() )->register();
    }
    // AtomicMetaBox is editor-only admin UI
    if ( function_exists( 'khm_can_show_admin_ui' ) && khm_can_show_admin_ui() && class_exists( 'KH\\Editorial\\Admin\\AtomicMetaBox' ) ) {
        ( new KH\Editorial\Admin\AtomicMetaBox() )->register();
    } elseif ( is_admin() && class_exists( 'KH\\Editorial\\Admin\\AtomicMetaBox' ) ) {
        // Fallback when lockdown not active
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
    // Allocation endpoints are API-only - always register
    if ( class_exists( 'KH\\Editorial\\API\\AllocationEndpoints' ) ) {
        ( new KH\Editorial\API\AllocationEndpoints() )->register();
    }
    // AllocationMetaBox is admin-only
    if ( function_exists( 'khm_can_show_admin_ui' ) && khm_can_show_admin_ui() && class_exists( 'KH\\Editorial\\Admin\\AllocationMetaBox' ) ) {
        ( new KH\Editorial\Admin\AllocationMetaBox() )->init();
    } elseif ( is_admin() && class_exists( 'KH\\Editorial\\Admin\\AllocationMetaBox' ) ) {
        // Fallback when lockdown not active
        ( new KH\Editorial\Admin\AllocationMetaBox() )->init();
    }

    // 9b. Posts Table Columns (custom column set for edit.php)
    if ( function_exists( 'khm_can_show_admin_ui' ) && khm_can_show_admin_ui() && class_exists( 'KH\\Editorial\\Admin\\PostsTableColumns' ) ) {
        ( new KH\Editorial\Admin\PostsTableColumns() )->init();
    } elseif ( is_admin() && class_exists( 'KH\\Editorial\\Admin\\PostsTableColumns' ) ) {
        // Fallback when lockdown not active
        ( new KH\Editorial\Admin\PostsTableColumns() )->init();
    }

    // 9c. Distribution Overview Page
    if ( function_exists( 'khm_can_show_admin_ui' ) && khm_can_show_admin_ui() && class_exists( 'KH\\Editorial\\Admin\\DistributionPage' ) ) {
        ( new KH\Editorial\Admin\DistributionPage() )->init();
    } elseif ( is_admin() && class_exists( 'KH\\Editorial\\Admin\\DistributionPage' ) ) {
        // Fallback when lockdown not active
        ( new KH\Editorial\Admin\DistributionPage() )->init();
    }

    // 10. Unified Search API (Phase 5) — Internal
    if ( class_exists( 'KH\\Editorial\\Search\\API\\SearchEndpoint' ) ) {
        ( new KH\Editorial\Search\API\SearchEndpoint() )->register();
    }

    // 10b. Unified Search API — Public (rate-limited, no auth required)
    if ( class_exists( 'KH\\Editorial\\Search\\API\\PublicSearchEndpoint' ) ) {
        ( new KH\Editorial\Search\API\PublicSearchEndpoint() )->register();
    }

    // 10c. Public-Facing Search Shortcode [khm_site_search]
    if ( class_exists( 'KH\\Editorial\\Search\\Frontend\\SearchShortcode' ) ) {
        ( new KH\Editorial\Search\Frontend\SearchShortcode() )->register();
    }

     // 11. Gutenberg Editor Assets
    // Only load on main site or for super admins (editor UI)
    if ( function_exists( 'khm_can_show_admin_ui' ) && khm_can_show_admin_ui() ) {
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
    } elseif ( is_admin() ) {
        // Fallback when lockdown not active
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
    }
} );
