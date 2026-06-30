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

// Load vendor autoloader (for PHPWord and other dependencies)
// Try multiple possible locations for the vendor autoloader
$vendor_paths = [
    WP_PLUGIN_DIR . '/khm-plugin/vendor/autoload.php',
    dirname( __FILE__ ) . '/../khm-plugin/vendor/autoload.php',
    KH_PLANNER_PLUGIN_DIR . '../khm-plugin/vendor/autoload.php',
];
foreach ( $vendor_paths as $vendor_autoload ) {
    if ( file_exists( $vendor_autoload ) ) {
        require_once $vendor_autoload;
        break;
    }
}


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

/**
 * Get dependency status for graceful degradation.
 * Returns an array indicating which external dependencies are available.
 * 
 * @return array Dependency status map with keys: aiworker, draft_agent, intelligence_bridge
 */
function kh_planner_get_dependency_status() {
    return [
        'aiworker' => class_exists( '\KH\Editorial\Services\AI\AIWorker' ),
        'draft_agent' => class_exists( '\KH\EditorialAuthor\Agents\DraftAgent' ),
        'intelligence_bridge' => class_exists( '\KH\EditorialAuthor\Integration\IntelligenceBridge' ),
    ];
}

/**
 * Admin notice for missing dependencies.
 */
add_action( 'admin_notices', function() {
    // Only show on plugin pages
    $screen = get_current_screen();
    if ( ! $screen || ! isset( $screen->id ) || strpos( $screen->id, 'editorial-planner' ) === false ) {
        return;
    }
    
    $deps = kh_planner_get_dependency_status();
    $missing = [];
    
    if ( ! $deps['aiworker'] ) {
        $missing[] = 'kh-editorial (AIWorker for background job processing)';
    }
    if ( ! $deps['draft_agent'] ) {
        $missing[] = 'kh-editorial-author (DraftAgent for author generation)';
    }
    
    if ( ! empty( $missing ) ) {
        echo '<div class="notice notice-warning"><p><strong>Editorial Planner:</strong> The following dependencies are missing and some features will be unavailable: ' . esc_html( implode( ', ', $missing ) ) . '</p></div>';
    }
} );

// Initialize Planner Components
add_action( 'plugins_loaded', function() {
    // Check dependencies and initialize components gracefully
    $deps = kh_planner_get_dependency_status();
    
    // 1. Register CPT (no external dependencies)
    if ( class_exists( 'KH\\Planner\\PostTypes\\PlannerSession' ) ) {
        KH\Planner\PostTypes\PlannerSession::init();
    }
    
    // 2. Register REST API (no external dependencies for basic endpoints)
    if ( class_exists( 'KH\\Planner\\API\\PlannerEndpoints' ) ) {
        $endpoints = new KH\Planner\API\PlannerEndpoints();
        $endpoints->init();
    }

    // 3. Register Admin Workspace (no external dependencies)
    if ( is_admin() && class_exists( 'KH\\Planner\\Admin\\PlannerWorkspace' ) ) {
        $workspace = new KH\Planner\Admin\PlannerWorkspace();
        $workspace->init();
    }
    
    // 4. Initialize Agentic Orchestrator - depends on DraftAgent for author generation
    if ( class_exists( 'KH\\Planner\\Agents\\PlannerOrchestrator' ) ) {
        // Pass dependency status to orchestrator for graceful degradation
        KH\Planner\Agents\PlannerOrchestrator::init( $deps );
    }

    // 5. Initialize Taxonomy Bridge (no external dependencies)
    if ( class_exists( 'KH\\Planner\\Core\\PlannerTaxonomyBridge' ) ) {
        $bridge = new KH\Planner\Core\PlannerTaxonomyBridge();
        $bridge->init();
    }
} );

// Note: Script enqueuing is handled by PlannerWorkspace class with proper ES module type attribute
// See src/Admin/PlannerWorkspace.php for the enqueue_assets method

// ─── Cron Hook: Process dive_deeper jobs asynchronously ─────────
// Scheduled by PlannerOrchestrator::enqueue_job for research agent_key.
// Runs on the next WP cron execution, preventing HTTP timeout on the
// REST endpoint that enqueued the job.
add_action( 'kh_editorial_process_planner_job', function( $job_id ) {
    if ( ! empty( $job_id ) && class_exists( 'KH\\Editorial\\Services\\AI\\AIWorker' ) ) {
        $worker = new KH\Editorial\Services\AI\AIWorker();
        $worker->process_job( $job_id, 'planner' );
    } else {
        // Graceful degradation: log if AIWorker unavailable
        error_log( '[PLANNER] AIWorker not available - cannot process background job: ' . $job_id );
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