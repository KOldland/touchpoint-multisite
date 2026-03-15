<?php
/**
 * GEO Module Integration Manager
 * 
 * Integrates the Generative Engine Optimization functionality with the existing KHM SEO plugin.
 * Handles initialization, hooks, and coordination between GEO components and existing SEO features.
 * 
 * @package KHM_SEO\GEO
 * @since 2.0.0
 * @version 2.0.0
 */

namespace KHM_SEO\GEO;

use KHM_SEO\GEO\Entity\EntityManager;
use KHM_SEO\GEO\Database\EntityTables;
use KHM_SEO\GEO\API\EntityAPI;
use KHM_SEO\GEO\Validation\EntityValidator;
use KHM_SEO\GEO\Validation\ValidationManager;
use KHM_SEO\GEO\Measurement\MeasurementManager;
use KHM_SEO\GEO\Measurement\MeasurementTables;
use KHM_SEO\GEO\Schema\SchemaDedupManager;
use KHM_SEO\GEO\Series\SeriesManager;
use KHM_SEO\GEO\Series\SeriesTables;
use KHM_SEO\GEO\Export\ExportManager;
use KHM_SEO\GEO\Export\ExportTables;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * GEO Integration Manager Class
 * Main coordinator for GEO functionality within the KHM SEO plugin
 */
class GEOManager {
    /**
     * AJAX: Remove alias from entity
     */
    public function ajax_remove_alias() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
            return;
        }
        $entity_id = intval( $_POST['entity_id'] ?? 0 );
        $alias = sanitize_text_field( $_POST['alias'] ?? '' );
        if ( ! $entity_id || ! $alias ) {
            wp_send_json_error( 'Missing entity or alias' );
        }
        $entity = $this->entity_manager->get_entity( $entity_id );
        if ( ! $entity ) {
            wp_send_json_error( 'Entity not found' );
        }
        $aliases = $this->entity_manager->get_entity_aliases( $entity_id );
        $aliases = array_diff( $aliases, array( $alias ) );
        $this->entity_manager->set_entity_aliases( $entity_id, $aliases );
        wp_send_json_success();
    }

    /**
     * AJAX: Add alias to entity
     */
    public function ajax_add_alias() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
            return;
        }
        $entity_id = intval( $_POST['entity_id'] ?? 0 );
        $alias = sanitize_text_field( $_POST['alias'] ?? '' );
        if ( ! $entity_id || ! $alias ) {
            wp_send_json_error( 'Missing entity or alias' );
        }
        $entity = $this->entity_manager->get_entity( $entity_id );
        if ( ! $entity ) {
            wp_send_json_error( 'Entity not found' );
        }
        $aliases = $this->entity_manager->get_entity_aliases( $entity_id );
        if ( in_array( $alias, $aliases ) ) {
            wp_send_json_error( 'Alias already exists' );
        }
        $aliases[] = $alias;
        $this->entity_manager->set_entity_aliases( $entity_id, $aliases );
        wp_send_json_success();
    }
    /**
     * Handle entity edit form submission
     */
    public function handle_entity_edit_submit() {
        if ( ! isset( $_POST['khm_geo_edit_nonce'] ) || ! wp_verify_nonce( $_POST['khm_geo_edit_nonce'], 'khm_geo_edit_entity' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $entity_id = intval( $_POST['entity_id'] ?? 0 );
        $canonical = sanitize_text_field( $_POST['canonical'] ?? '' );
        $type = sanitize_text_field( $_POST['type'] ?? '' );
        $scope = sanitize_text_field( $_POST['scope'] ?? '' );
        $status = sanitize_text_field( $_POST['status'] ?? '' );
        $aliases = array_map( 'sanitize_text_field', $_POST['aliases'] ?? array() );
        $data = array(
            'canonical' => $canonical,
            'type' => $type,
            'scope' => $scope,
            'status' => $status
        );
        if ( $entity_id > 0 ) {
            $this->entity_manager->update_entity( $entity_id, $data );
            $this->entity_manager->set_entity_aliases( $entity_id, $aliases );
        } else {
            $new_id = $this->entity_manager->create_entity( $data );
            if ( $new_id ) {
                $this->entity_manager->set_entity_aliases( $new_id, $aliases );
            }
        }
        wp_redirect( admin_url( 'admin.php?page=khm-seo-entities' ) );
        exit;
    }

    /**
     * Handle bulk actions in entity list
     */
    public function handle_entity_bulk_action() {
        if ( ! isset( $_POST['khm_geo_bulk_nonce'] ) || ! wp_verify_nonce( $_POST['khm_geo_bulk_nonce'], 'khm_geo_bulk_action' ) ) {
            return;
        }
        if ( ! current_user_can( 'delete_posts' ) ) {
            return;
        }
        $action = sanitize_text_field( $_POST['bulk_action'] ?? '' );
        $ids = array_map( 'intval', $_POST['entity_ids'] ?? array() );
        foreach ( $ids as $id ) {
            switch ( $action ) {
                case 'activate':
                    $this->entity_manager->update_entity( $id, array( 'status' => 'active' ) );
                    break;
                case 'deprecate':
                    $this->entity_manager->update_entity( $id, array( 'status' => 'deprecated' ) );
                    break;
                case 'delete':
                    $this->entity_manager->delete_entity( $id );
                    break;
            }
        }
        // Set an admin notice (redirect flow) — but for AJAX we return JSON
        wp_redirect( admin_url( 'admin.php?page=khm-seo-entities' ) );
        exit;
    }

    /**
     * AJAX handler for bulk actions — returns JSON summary
     */
    public function ajax_handle_bulk_action() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        $action = sanitize_text_field( $_POST['bulk_action'] ?? '' );
        $ids = array_map( 'intval', $_POST['entity_ids'] ?? array() );
        if ( empty( $action ) || empty( $ids ) ) {
            wp_send_json_error( 'Missing action or entity IDs' );
        }
        $result = array( 'updated_ids' => array(), 'failed_ids' => array() );
        foreach ( $ids as $id ) {
            try {
                switch ( $action ) {
                    case 'activate':
                        $ok = $this->entity_manager->update_entity( $id, array( 'status' => 'active' ) );
                        break;
                    case 'deprecate':
                        $ok = $this->entity_manager->update_entity( $id, array( 'status' => 'deprecated' ) );
                        break;
                    case 'delete':
                        $ok = $this->entity_manager->delete_entity( $id );
                        break;
                    default:
                        $ok = false;
                }
            } catch ( \Exception $e ) {
                $ok = false;
            }
            if ( $ok ) {
                $result['updated_ids'][] = $id;
            } else {
                $result['failed_ids'][] = $id;
            }
        }
        $message = sprintf( '%d processed, %d failed', count( $result['updated_ids'] ), count( $result['failed_ids'] ) );
        wp_send_json_success( array( 'message' => $message, 'updated_ids' => $result['updated_ids'], 'failed_ids' => $result['failed_ids'] ) );
    }
    
    /**
     * @var EntityManager Entity manager instance
     */
    private $entity_manager;
    
    /**
     * @var EntityTables Database tables manager
     */
    private $entity_tables;
    
    /**
     * @var EntityAPI API handler
     */
    private $entity_api;

    /**
     * @var EntityValidator Validation handler
     */
    private $entity_validator;
    
    /**
     * @var array GEO configuration
     */
    private $config = array();
    
    /**
     * @var ValidationManager Pre-publish validation manager
     */
    private $validation_manager;
    
    /**
     * @var MeasurementManager Analytics and tracking manager
     */
    private $measurement_manager;
    
    /**
     * @var MeasurementTables Measurement database tables manager
     */
    private $measurement_tables;
    
    /**
     * @var SchemaDedupManager Schema deduplication manager
     */
    private $schema_dedup_manager;
    
    /**
     * @var SeriesManager Series management for AnswerCards
     */
    private $series_manager;
    
    /**
     * @var SeriesTables Series database tables manager
     */
    private $series_tables;
    
    /**
     * @var ExportManager Data export functionality
     */
    private $export_manager;
    
    /**
     * @var ExportTables Export database tables manager
     */
    private $export_tables;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_components();
        $this->init_hooks();
        $this->load_config();
    }
    
    /**
     * Initialize GEO components
     */
    private function init_components() {
        // Initialize database tables
        $this->entity_tables = new EntityTables();
        
        // Initialize entity manager
        $this->entity_manager = new EntityManager();
        
        // Initialize API
        $this->entity_api = new EntityAPI();

        // Initialize validator
        $this->entity_validator = new EntityValidator( $this->entity_manager );
        
        // Initialize validation manager
        $this->validation_manager = new ValidationManager( $this->entity_manager );
        
        // Initialize measurement tables
        $this->measurement_tables = new MeasurementTables();
        
        // Initialize measurement manager
        $this->measurement_manager = new MeasurementManager( $this->entity_manager );
        
        // Initialize schema deduplication manager
        $this->schema_dedup_manager = new SchemaDedupManager( $this->entity_manager );
        
        // Initialize series tables
        $this->series_tables = new SeriesTables();
        
        // Initialize series manager
        $this->series_manager = new SeriesManager( $this->entity_manager );
        $this->series_manager->set_series_tables( $this->series_tables );
        
        // Initialize export tables
        $this->export_tables = new ExportTables();
        
        // Initialize export manager
        $this->export_manager = new ExportManager( $this->entity_manager, $this->series_manager, $this->measurement_manager );
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action( 'wp_ajax_khm_geo_remove_alias', array( $this, 'ajax_remove_alias' ) );
        add_action( 'wp_ajax_khm_geo_add_alias', array( $this, 'ajax_add_alias' ) );
        // Plugin initialization
        add_action( 'init', array( $this, 'on_init' ) );
        add_action( 'admin_init', array( $this, 'on_admin_init' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        
        // Admin menu integration
        add_action( 'admin_menu', array( $this, 'add_admin_pages' ), 15 );
        
        // Assets enqueue
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        
        // Plugin activation/deactivation hooks
        register_activation_hook( KHM_SEO_PLUGIN_FILE, array( $this, 'on_activation' ) );
        register_deactivation_hook( KHM_SEO_PLUGIN_FILE, array( $this, 'on_deactivation' ) );
        
        // Integration with existing schema system
        add_filter( 'khm_seo_schema_data', array( $this, 'integrate_entities_with_schema' ), 10, 2 );
        
        // Content processing hooks
        add_filter( 'the_content', array( $this, 'process_content_entities' ), 12 );
        add_action( 'save_post', array( $this, 'on_post_save_integration' ), 25 );
        
        // AJAX actions
        add_action( 'wp_ajax_khm_geo_quick_search', array( $this, 'ajax_quick_entity_search' ) );
        add_action( 'wp_ajax_khm_geo_validate_post', array( $this, 'ajax_validate_post_entities' ) );
        add_action( 'wp_ajax_khm_geo_bulk_action_ajax', array( $this, 'ajax_handle_bulk_action' ) );

        // Validation hooks
        add_filter( 'wp_insert_post_data', array( $this->entity_validator, 'pre_publish_validation' ), 10, 2 );
        
        // Pre-publish validation hooks
        add_action( 'save_post', array( $this->validation_manager, 'validate_on_save' ), 20 );
        add_action( 'wp_ajax_khm_geo_validate_answer_card', array( $this->validation_manager, 'ajax_validate_answer_card' ) );
    }

    /**
     * Register GEO REST routes.
     */
    public function register_rest_routes() {
        register_rest_route( 'khm-seo/v1', '/geo-sponsor', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'handle_geo_sponsor_rest' ),
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
        ) );
    }

    /**
     * REST handler for GEO sponsor mapping.
     */
    public function handle_geo_sponsor_rest( $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        $geo = sanitize_text_field( $request->get_param( 'geo' ) ?? 'global' );
        if ( ! $post_id ) {
            return new \WP_Error( 'khm_geo_missing_post', 'post_id is required.', array( 'status' => 400 ) );
        }

        $policy = $this->getSponsorPolicyForPost( $post_id, $geo );
        return rest_ensure_response( array( 'policy' => $policy ) );
    }

    /**
     * Return sponsor policy for a post + geo code.
     */
    public function getSponsorPolicyForPost( int $post_id, string $geo_code ): ?array {
        if ( ! $post_id ) {
            return null;
        }

        $geo_code = $geo_code ?: 'global';
        $post_map = get_post_meta( $post_id, '_khm_geo_sponsor_map', true );
        if ( is_array( $post_map ) && isset( $post_map[ $geo_code ] ) ) {
            return $post_map[ $geo_code ];
        }

        $global_map = get_option( 'khm_geo_sponsor_map', array() );
        if ( is_array( $global_map ) && isset( $global_map[ $geo_code ] ) ) {
            return $global_map[ $geo_code ];
        }

        return null;
    }
    
    /**
     * Load GEO configuration
     */
    private function load_config() {
        $defaults = array(
            'auto_linking_enabled' => true,
            'auto_linking_mode' => 'first_only',
            'governance_strict_mode' => false,
            'entity_detection_auto' => true,
            'schema_integration' => true,
            'default_entity_scope' => 'site',
            'review_cadence_days' => 365,
            'max_auto_links_per_post' => 10
        );
        
        $saved_config = get_option( 'khm_seo_geo_config', array() );
        $this->config = array_merge( $defaults, $saved_config );
    }
    
    /**
     * WordPress init handler
     */
    public function on_init() {
        // Register custom post types if needed
        $this->register_custom_post_types();
        
        // Register taxonomies if needed
        $this->register_taxonomies();
        
        // Load text domain
        load_plugin_textdomain( 'khm-seo', false, dirname( plugin_basename( KHM_SEO_PLUGIN_FILE ) ) . '/languages' );
    }
    
    /**
     * Admin init handler
     */
    public function on_admin_init() {
        // Register settings
        $this->register_geo_settings();
        
        // Check database version
        $this->maybe_update_database();

        // Ensure measurement tables exist (self-heal in case activation didn't run)
        if ( $this->measurement_tables instanceof MeasurementTables ) {
            global $wpdb;
            $metrics_table = $this->measurement_tables->get_table_name( 'metrics' );
            if ( $metrics_table && $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $metrics_table ) ) !== $metrics_table ) {
                $this->measurement_tables->install_tables();
            }
        }
    }
    
    /**
     * Add admin pages for entity management
     */
    public function add_admin_pages() {
        // Check if user has permission
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        
        // Add main entity dictionary page
        add_submenu_page(
            'khm-seo',
            __( 'Entity Dictionary', 'khm-seo' ),
            __( 'Entities', 'khm-seo' ),
            'edit_posts',
            'khm-seo-entities',
            array( $this, 'render_entity_dictionary_page' )
        );
        
        // Add entity governance page
        add_submenu_page(
            'khm-seo',
            __( 'Content Governance', 'khm-seo' ),
            __( 'Governance', 'khm-seo' ),
            'edit_posts',
            'khm-seo-governance',
            array( $this, 'render_governance_page' )
        );
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook_suffix Current admin page
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Only load on our pages
        if ( strpos( $hook_suffix, 'khm-seo' ) === false ) {
            return;
        }
        
        wp_enqueue_script(
            'khm-geo-admin',
            plugins_url( 'assets/js/geo-admin.js', KHM_SEO_PLUGIN_FILE ),
            array( 'jquery', 'wp-util' ),
            KHM_SEO_VERSION,
            true
        );
        
        wp_enqueue_style(
            'khm-geo-admin',
            plugins_url( 'assets/css/geo-admin.css', KHM_SEO_PLUGIN_FILE ),
            array(),
            KHM_SEO_VERSION
        );
        
        // Localize script
        wp_localize_script( 'khm-geo-admin', 'khmGeoAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'khm_seo_ajax' ),
            'rest_url' => rest_url( 'geo/v1/' ),
            'rest_nonce' => wp_create_nonce( 'wp_rest' ),
            'strings' => array(
                'search_entities' => __( 'Search entities...', 'khm-seo' ),
                'create_entity' => __( 'Create Entity', 'khm-seo' ),
                'edit_entity' => __( 'Edit Entity', 'khm-seo' ),
                'delete_entity' => __( 'Delete Entity', 'khm-seo' ),
                'add_alias' => __( 'Add Alias', 'khm-seo' ),
                'confirm_delete' => __( 'Are you sure you want to delete this entity?', 'khm-seo' )
            )
        ) );
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue if auto-linking is enabled
        if ( ! $this->config['auto_linking_enabled'] ) {
            return;
        }
        
        wp_enqueue_style(
            'khm-geo-frontend',
            plugins_url( 'assets/css/geo-frontend.css', KHM_SEO_PLUGIN_FILE ),
            array(),
            KHM_SEO_VERSION
        );
    }
    
    /**
     * Plugin activation handler
     */
    public function on_activation() {
        // Create/update database tables
        $this->entity_tables->install_tables();
        // Ensure measurement tables (geo_metrics, etc.) exist
        if ( $this->measurement_tables instanceof MeasurementTables ) {
            $this->measurement_tables->install_tables();
        }
        
        // Set default configuration
        if ( ! get_option( 'khm_seo_geo_config' ) ) {
            add_option( 'khm_seo_geo_config', $this->config );
        }
        
        // Create default entities if none exist
        $this->create_default_entities();
    }
    
    /**
     * Plugin deactivation handler
     */
    public function on_deactivation() {
        // Clear scheduled events
        wp_clear_scheduled_hook( 'khm_geo_entity_review_reminder' );
    }
    
    /**
     * Integrate entities with schema output
     * 
     * @param array $schema_data Current schema data
     * @param int $post_id Post ID
     * @return array Modified schema data
     */
    public function integrate_entities_with_schema( $schema_data, $post_id ) {
        if ( ! $this->config['schema_integration'] ) {
            return $schema_data;
        }
        
        return $this->entity_manager->add_entities_to_schema( $schema_data, $post_id );
    }
    
    /**
     * Process content for entity auto-linking
     * 
     * @param string $content Post content
     * @return string Modified content
     */
    public function process_content_entities( $content ) {
        if ( ! $this->config['auto_linking_enabled'] ) {
            return $content;
        }
        
        return $this->entity_manager->auto_link_entities( $content );
    }
    
    /**
     * Handle post save for entity processing
     * 
     * @param int $post_id Post ID
     */
    public function on_post_save_integration( $post_id ) {
        // Skip if auto-detection is disabled
        if ( ! $this->config['entity_detection_auto'] ) {
            return;
        }
        
        // Let the entity manager handle the detection
        $this->entity_manager->on_post_save( $post_id );
    }
    
    // ===== ADMIN PAGE RENDERERS =====
    
    /**
     * Render entity dictionary admin page
     */
    public function render_entity_dictionary_page() {
        // Get current action
        $action = $_GET['action'] ?? 'list';
        $entity_id = intval( $_GET['entity_id'] ?? 0 );
        
        switch ( $action ) {
            case 'edit':
                $this->render_entity_edit_form( $entity_id );
                break;
            case 'new':
                $this->render_entity_edit_form();
                break;
            default:
                $this->render_entity_list();
        }
    }
    
    /**
     * Render entity list
     */
    private function render_entity_list() {
        // Get search parameters
        $search = sanitize_text_field( $_GET['search'] ?? '' );
        $type_filter = sanitize_text_field( $_GET['type'] ?? '' );
        $scope_filter = sanitize_text_field( $_GET['scope'] ?? '' );
        $status_filter = sanitize_text_field( $_GET['status'] ?? 'active' );
        
        // Search entities
        $entities = $this->entity_manager->search_entities( array(
            'search' => $search,
            'type' => $type_filter,
            'scope' => $scope_filter,
            'status' => $status_filter,
            'limit' => 50
        ) );
        
        // Get database stats
        $stats = $this->entity_tables->get_database_stats();
        
        include KHM_SEO_PLUGIN_DIR . 'templates/geo/entity-list.php';
    }
    
    /**
     * Render entity edit form
     * 
     * @param int $entity_id Entity ID (0 for new)
     */
    private function render_entity_edit_form( $entity_id = 0 ) {
        $entity = null;
        $aliases = array();
        $link_rules = null;
        $valid_types = $this->entity_manager->get_valid_types();
        $valid_scopes = $this->entity_manager->get_valid_scopes();
        $valid_statuses = $this->entity_manager->get_valid_statuses();
        if ( $entity_id > 0 ) {
            $entity = $this->entity_manager->get_entity( $entity_id );
            if ( ! $entity ) {
                wp_die( __( 'Entity not found.', 'khm-seo' ) );
            }
            $aliases = $this->entity_manager->get_entity_aliases( $entity_id );
            $link_rules = $this->entity_manager->get_entity_link_rules( $entity_id );
            $entity['aliases'] = $aliases;
        }
        include KHM_SEO_PLUGIN_DIR . 'templates/geo/entity-edit.php';
    }
    
    /**
     * Render governance page
     */
    public function render_governance_page() {
        // Get governance statistics
        $governance_stats = $this->get_governance_statistics();
        
        include KHM_SEO_PLUGIN_DIR . 'templates/geo/governance.php';
    }
    
    // ===== AJAX HANDLERS =====
    
    /**
     * Quick entity search for autocomplete
     */
    public function ajax_quick_entity_search() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        
        $search = sanitize_text_field( $_POST['search'] ?? '' );
        $limit = min( intval( $_POST['limit'] ?? 10 ), 20 );
        
        $entities = $this->entity_manager->search_entities( array(
            'search' => $search,
            'limit' => $limit,
            'status' => 'active'
        ) );
        
        $results = array();
        foreach ( $entities as $entity ) {
            $results[] = array(
                'id' => $entity->id,
                'canonical' => $entity->canonical,
                'type' => $entity->type,
                'scope' => $entity->scope,
                'definition' => wp_trim_words( $entity->definition, 20 )
            );
        }
        
        wp_send_json_success( $results );
    }
    
    /**
     * Validate post entities
     */
    public function ajax_validate_post_entities() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        
        $post_id = intval( $_POST['post_id'] ?? 0 );
        
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( 'Invalid post or insufficient permissions' );
        }
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( 'Post not found' );
        }
        
        $content = $post->post_title . ' ' . $post->post_content;
        
        // Perform validation through the API
        $validation_result = $this->entity_api->perform_content_validation( $content, $post_id );
        
        wp_send_json_success( $validation_result );
    }
    
    // ===== HELPER METHODS =====
    
    /**
     * Register GEO settings
     */
    private function register_geo_settings() {
        register_setting( 'khm_seo_geo', 'khm_seo_geo_config', array(
            'type' => 'array',
            'sanitize_callback' => array( $this, 'sanitize_geo_config' )
        ) );

        register_setting( 'khm_seo_geo', 'khm_geo_public_label', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( 'khm_seo_geo', 'khm_geo_date_format', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
    }
    
    /**
     * Sanitize GEO configuration
     * 
     * @param array $config Configuration array
     * @return array Sanitized configuration
     */
    public function sanitize_geo_config( $config ) {
        $sanitized = array();
        
        $sanitized['auto_linking_enabled'] = ! empty( $config['auto_linking_enabled'] );
        $sanitized['auto_linking_mode'] = in_array( $config['auto_linking_mode'] ?? '', array( 'first_only', 'all', 'manual', 'never' ) )
            ? $config['auto_linking_mode'] : 'first_only';
        $sanitized['governance_strict_mode'] = ! empty( $config['governance_strict_mode'] );
        $sanitized['entity_detection_auto'] = ! empty( $config['entity_detection_auto'] );
        $sanitized['schema_integration'] = ! empty( $config['schema_integration'] );
        $sanitized['default_entity_scope'] = in_array( $config['default_entity_scope'] ?? '', array( 'global', 'client', 'site' ) )
            ? $config['default_entity_scope'] : 'site';
        $sanitized['review_cadence_days'] = max( 30, intval( $config['review_cadence_days'] ?? 365 ) );
        $sanitized['max_auto_links_per_post'] = max( 1, min( 50, intval( $config['max_auto_links_per_post'] ?? 10 ) ) );
        
        return $sanitized;
    }
    
    /**
     * Check if database needs updating
     */
    private function maybe_update_database() {
        $current_version = get_option( EntityTables::DB_VERSION_OPTION, '0.0.0' );
        
        if ( version_compare( $current_version, EntityTables::DB_VERSION, '<' ) ) {
            $this->entity_tables->install_tables();
        }
    }
    
    /**
     * Register custom post types
     */
    private function register_custom_post_types() {
        // No custom post types needed currently
        // Entities are stored in custom tables for performance
    }
    
    /**
     * Register taxonomies
     */
    private function register_taxonomies() {
        // No custom taxonomies needed currently
    }
    
    /**
     * Create default entities
     */
    private function create_default_entities() {
        // Check if any entities exist
        $existing = $this->entity_manager->search_entities( array( 'limit' => 1 ) );
        if ( ! empty( $existing ) ) {
            return; // Entities already exist
        }
        
        // Create some default entities based on site
        $site_name = get_bloginfo( 'name' );
        if ( ! empty( $site_name ) ) {
            $this->entity_manager->create_entity( array(
                'canonical' => $site_name,
                'type' => 'Organization',
                'scope' => 'site',
                'definition' => sprintf( 'The %s organization.', $site_name ),
                'owner_user_id' => get_current_user_id(),
                'status' => 'active'
            ) );
        }
        
        // Create default terms
        $default_entities = array(
            array(
                'canonical' => 'SEO',
                'type' => 'Acronym',
                'definition' => 'Search Engine Optimization',
                'preferred_capitalization' => 'SEO'
            ),
            array(
                'canonical' => 'Content Marketing',
                'type' => 'Term',
                'definition' => 'Marketing strategy focused on creating and distributing valuable content'
            ),
            array(
                'canonical' => 'WordPress',
                'type' => 'Product',
                'definition' => 'Open-source content management system',
                'same_as' => array( 'https://wordpress.org' )
            )
        );
        
        foreach ( $default_entities as $entity_data ) {
            $entity_data['scope'] = 'site';
            $entity_data['owner_user_id'] = get_current_user_id();
            $entity_data['status'] = 'active';
            
            $this->entity_manager->create_entity( $entity_data );
        }
    }
    
    /**
     * Get governance statistics
     * 
     * @return array Governance statistics
     */
    private function get_governance_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Get entity stats
        $entity_stats = $this->entity_tables->get_database_stats();
        $stats['total_entities'] = $entity_stats['total_entities'];
        $stats['active_entities'] = $entity_stats['active_entities'];
        $stats['deprecated_entities'] = $entity_stats['deprecated_entities'];
        
        // Get stale entities (past review date)
        $stale_entities = $wpdb->get_var( 
            "SELECT COUNT(*) FROM {$wpdb->prefix}geo_entities 
             WHERE status = 'active' 
             AND last_reviewed_at IS NOT NULL 
             AND DATE_ADD(last_reviewed_at, INTERVAL review_cadence_days DAY) < NOW()"
        );
        $stats['stale_entities'] = intval( $stale_entities );
        
        // Get posts with entities
        $posts_with_entities = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}geo_page_entities"
        );
        $stats['posts_with_entities'] = intval( $posts_with_entities );
        
        // Get posts missing primary entity
        $posts_missing_primary = $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->prefix}geo_page_entities pe ON p.ID = pe.post_id AND pe.role = 'primary'
             WHERE p.post_type IN ('post', 'page') 
             AND p.post_status = 'publish'
             AND pe.post_id IS NULL"
        );
        $stats['posts_missing_primary'] = intval( $posts_missing_primary );
        
        return $stats;
    }
    
    // ===== GETTER METHODS =====
    
    /**
     * Get entity manager instance
     * 
     * @return EntityManager
     */
    public function get_entity_manager() {
        return $this->entity_manager;
    }
    
    /**
     * Get entity tables instance
     * 
     * @return EntityTables
     */
    public function get_entity_tables() {
        return $this->entity_tables;
    }
    
    /**
     * Get validation manager instance
     * 
     * @return ValidationManager
     */
    public function get_validation_manager() {
        return $this->validation_manager;
    }
    
    /**
     * Get measurement manager instance
     * 
     * @return MeasurementManager
     */
    public function get_measurement_manager() {
        return $this->measurement_manager;
    }
    
    /**
     * Get measurement tables instance
     * 
     * @return MeasurementTables
     */
    public function get_measurement_tables() {
        return $this->measurement_tables;
    }
    
    /**
     * Get schema deduplication manager instance
     * 
     * @return SchemaDedupManager
     */
    public function get_schema_dedup_manager() {
        return $this->schema_dedup_manager;
    }
    
    /**
     * Get series manager instance
     * 
     * @return SeriesManager
     */
    public function get_series_manager() {
        return $this->series_manager;
    }
    
    /**
     * Get series tables instance
     * 
     * @return SeriesTables
     */
    public function get_series_tables() {
        return $this->series_tables;
    }
    
    /**
     * Get export manager
     * 
     * @return ExportManager Export manager instance
     */
    public function get_export_manager() {
        return $this->export_manager;
    }
    
    /**
     * Get export tables
     * 
     * @return ExportTables Export tables instance
     */
    public function get_export_tables() {
        return $this->export_tables;
    }
    
    /**
     * Get configuration
     * 
     * @param string $key Configuration key
     * @return mixed Configuration value
     */
    public function get_config( $key = null ) {
        if ( $key ) {
            return $this->config[ $key ] ?? null;
        }
        
        return $this->config;
    }
}
