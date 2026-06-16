<?php
/**
 * Schema Admin Interface Manager - Phase 4.1
 * 
 * Comprehensive admin interface for schema configuration and management.
 * Provides user-friendly tools for configuring structured data markup.
 * 
 * Features:
 * - Meta boxes for post/page schema configuration
 * - Real-time JSON-LD preview functionality
 * - Bulk schema management tools
 * - Schema validation and testing interface
 * - Settings page integration
 * - Schema debugging tools
 * 
 * @package KHM_SEO\Schema\Admin
 * @since 4.0.0
 * @version 4.0.0
 */

namespace KHM_SEO\Schema\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Schema Admin Interface Manager Class
 * Handles all admin interface components for schema management
 */
class SchemaAdminManager {
    
    /**
     * @var array Admin configuration
     */
    private $config;
    
    /**
     * @var array Supported post types for schema
     */
    private $supported_post_types;
    
    /**
     * @var array Available schema types
     */
    private $schema_types = array(
        'article' => array(
            'label' => 'Article',
            'description' => 'Standard article schema for blog posts and articles',
            'applicable_to' => array( 'post', 'page' ),
            'fields' => array( 'headline', 'author', 'datePublished', 'dateModified', 'description' )
        ),
        'organization' => array(
            'label' => 'Organization',
            'description' => 'Business organization schema for company information',
            'applicable_to' => array( 'page' ),
            'fields' => array( 'name', 'url', 'logo', 'contactPoint', 'address', 'sameAs' )
        ),
        'person' => array(
            'label' => 'Person',
            'description' => 'Person schema for author and biography pages',
            'applicable_to' => array( 'page', 'post' ),
            'fields' => array( 'name', 'jobTitle', 'image', 'sameAs', 'worksFor' )
        ),
        'product' => array(
            'label' => 'Product',
            'description' => 'Product schema for WooCommerce products',
            'applicable_to' => array( 'product' ),
            'fields' => array( 'name', 'description', 'sku', 'brand', 'offers' )
        ),
        'breadcrumb' => array(
            'label' => 'BreadcrumbList',
            'description' => 'Breadcrumb navigation schema',
            'applicable_to' => array( 'all' ),
            'fields' => array( 'itemListElement' )
        ),
        'techarticle' => array(
            'label' => 'TechArticle (Atomic Article)',
            'description' => 'Atomic article with isPartOf parent guide link',
            'applicable_to' => array( 'post' ),
            'fields' => array( 'headline', 'author', 'datePublished', 'isPartOf', 'about', 'articleSection' )
        ),
        'qapage' => array(
            'label' => 'QAPage (Answer Cards)',
            'description' => 'Q&A page with nested Question and acceptedAnswer',
            'applicable_to' => array( 'post', 'page' ),
            'fields' => array( 'mainEntity' )
        ),
        'videoobject' => array(
            'label' => 'VideoObject (YouTube)',
            'description' => 'Video content with embed URL and key moments',
            'applicable_to' => array( 'post' ),
            'fields' => array( 'name', 'thumbnailUrl', 'embedUrl', 'hasPart' )
        ),
        'audioobject' => array(
            'label' => 'AudioObject (Podcast)',
            'description' => 'Audio content nested in BlogPosting or Article',
            'applicable_to' => array( 'post' ),
            'fields' => array( 'name', 'contentUrl', 'encodingFormat' )
        )
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_config();
        $this->init_hooks();
    }
    
    /**
     * Load admin configuration
     */
    private function load_config() {
        $defaults = array(
            'enable_meta_boxes' => true,
            'enable_bulk_management' => true,
            'enable_schema_preview' => true,
            'enable_validation' => true,
            'show_debug_info' => false,
            'supported_post_types' => array( 'post', 'page' ),
            'default_schema_type' => 'article',
        );
        
        $this->config = \wp_parse_args( \get_option( 'khm_seo_schema_admin', array() ), $defaults );
        $this->supported_post_types = $this->config['supported_post_types'];
        
        // Add WooCommerce support if available
        if ( class_exists( 'WooCommerce' ) ) {
            $this->supported_post_types[] = 'product';
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Meta boxes
        if ( $this->config['enable_meta_boxes'] ) {
            \add_action( 'add_meta_boxes', array( $this, 'add_schema_meta_boxes' ) );
            \add_action( 'save_post', array( $this, 'save_schema_meta' ) );
        }
        
        // Admin pages
        \add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        \add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // AJAX handlers
        \error_log( 'KHM SEO: SchemaAdminManager::init_hooks() registering AJAX handlers.' );
        \add_action( 'wp_ajax_khm_seo_preview_schema', array( $this, 'ajax_preview_schema' ) );
        \add_action( 'wp_ajax_khm_seo_validate_schema', array( $this, 'ajax_validate_schema' ) );
        \add_action( 'wp_ajax_khm_seo_test_with_google', array( $this, 'ajax_test_with_google' ) );
        \add_action( 'wp_ajax_khm_seo_bulk_schema_update', array( $this, 'ajax_bulk_schema_update' ) );
        
        // Scripts and styles
        \add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        
        // Columns for post lists
        foreach ( $this->supported_post_types as $post_type ) {
            \add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_schema_column' ) );
            \add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'populate_schema_column' ), 10, 2 );
        }
        
        // Quick edit
        \add_action( 'quick_edit_custom_box', array( $this, 'add_quick_edit_schema' ), 10, 2 );
        
        // Bulk edit
        \add_action( 'bulk_edit_custom_box', array( $this, 'add_bulk_edit_schema' ), 10, 2 );
    }
    
    /**
     * Add schema meta boxes to post edit screens
     */
    public function add_schema_meta_boxes() {
        foreach ( $this->supported_post_types as $post_type ) {
            \add_meta_box(
                'khm-seo-schema',
                __( 'Schema Markup', 'khm-seo' ),
                array( $this, 'render_schema_meta_box' ),
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render schema meta box
     * 
     * @param \WP_Post $post Current post object
     */
    public function render_schema_meta_box( $post ) {
        // Security nonce
        \wp_nonce_field( 'khm_seo_schema_meta', 'khm_seo_schema_nonce' );
        
        // Get current schema settings — default to enabled
        $current_schema = \get_post_meta( $post->ID, '_khm_seo_schema_config', true );
        if ( ! is_array( $current_schema ) || empty( $current_schema ) ) {
            $current_schema = array( 'enabled' => true );
        }
        $current_type = $current_schema['type'] ?? $this->get_default_schema_type( $post );
        $custom_fields = $current_schema['custom_fields'] ?? array();
        
        include dirname( __FILE__ ) . '/templates/meta-box-schema.php';
    }
    
    /**
     * Save schema meta data
     * 
     * @param int $post_id Post ID
     */
    public function save_schema_meta( $post_id ) {
        // Security checks
        if ( ! isset( $_POST['khm_seo_schema_nonce'] ) || 
             ! \wp_verify_nonce( $_POST['khm_seo_schema_nonce'], 'khm_seo_schema_meta' ) ) {
            return;
        }
        
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        if ( ! \current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Process schema configuration
        $schema_config = array();
        
        // Schema type
        if ( isset( $_POST['khm_seo_schema_type'] ) ) {
            $schema_config['type'] = \sanitize_text_field( $_POST['khm_seo_schema_type'] );
        }
        
        // Enable/disable schema
        $schema_config['enabled'] = isset( $_POST['khm_seo_schema_enabled'] ) ? true : false;
        
        // Custom fields
        if ( isset( $_POST['khm_seo_schema_fields'] ) && is_array( $_POST['khm_seo_schema_fields'] ) ) {
            $schema_config['custom_fields'] = array();
            
            foreach ( $_POST['khm_seo_schema_fields'] as $field_name => $field_value ) {
                $schema_config['custom_fields'][ \sanitize_key( $field_name ) ] = \sanitize_textarea_field( $field_value );
            }
        }
        
        // Advanced options
        if ( isset( $_POST['khm_seo_schema_options'] ) && is_array( $_POST['khm_seo_schema_options'] ) ) {
            $schema_config['options'] = array();
            
            foreach ( $_POST['khm_seo_schema_options'] as $option_key => $option_value ) {
                $schema_config['options'][ \sanitize_key( $option_key ) ] = \sanitize_text_field( $option_value );
            }
        }
        
        // Save configuration
        \update_post_meta( $post_id, '_khm_seo_schema_config', $schema_config );
        
        // Update schema cache
        $this->update_schema_cache( $post_id, $schema_config );
        
        // Trigger action for external integrations
        \do_action( 'khm_seo_schema_meta_saved', $post_id, $schema_config );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        \add_submenu_page(
            'khm-seo',
            __( 'Schema Settings', 'khm-seo' ),
            __( 'Schema', 'khm-seo' ),
            'manage_options',
            'khm-seo-schema',
            array( $this, 'render_schema_admin_page' )
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        \register_setting(
            'khm_seo_schema_admin',
            'khm_seo_schema_admin',
            array( $this, 'sanitize_settings' )
        );
    }
    
    /**
     * Sanitize admin settings
     * 
     * @param array $settings Raw settings
     * @return array Sanitized settings
     */
    public function sanitize_settings( $settings ) {
        $clean = array();
        
        // Boolean settings
        $boolean_fields = array(
            'enable_meta_boxes', 'enable_bulk_management',
            'enable_schema_preview', 'enable_validation',
            'show_debug_info'
        );
        
        foreach ( $boolean_fields as $field ) {
            $clean[ $field ] = ! empty( $settings[ $field ] );
        }
        
        // Text settings
        $clean['default_schema_type'] = \sanitize_text_field( $settings['default_schema_type'] ?? 'article' );
        
        // Array settings
        if ( ! empty( $settings['supported_post_types'] ) && is_array( $settings['supported_post_types'] ) ) {
            $clean['supported_post_types'] = array_map( 'sanitize_text_field', $settings['supported_post_types'] );
        } else {
            $clean['supported_post_types'] = array( 'post', 'page' );
        }
        
        return $clean;
    }
    
    /**
     * Render schema admin page
     */
    public function render_schema_admin_page() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        // Handle form submission
        if ( isset( $_POST['submit'] ) && \wp_verify_nonce( $_POST['khm_seo_schema_admin_nonce'], 'khm_seo_schema_admin' ) ) {
            $this->config = array_merge( $this->config, $_POST['khm_seo_schema_admin'] ?? array() );
            \update_option( 'khm_seo_schema_admin', $this->sanitize_settings( $this->config ) );
            
            echo '<div class="notice notice-success"><p>' . __( 'Settings saved successfully!', 'khm-seo' ) . '</p></div>';
        }
        
        include dirname( __FILE__ ) . '/templates/admin-page-schema.php';
    }

    /**
     * Get schema cache statistics for the tools tab.
     *
     * @return array Associative array with 'count' and 'last_updated' keys.
     */
    public function get_schema_cache_stats() {
        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                '_khm_seo_schema_cache'
            )
        );
        $last_updated = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id DESC LIMIT 1",
                '_khm_seo_schema_cache_updated'
            )
        );

        return array(
            'count'        => (int) $count,
            'last_updated' => $last_updated
                ? date( 'M j, Y g:i a', strtotime( $last_updated ) )
                : __( 'Never', 'khm-seo' ),
        );
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets( $hook ) {
        global $post;
        
        // Only load on relevant admin pages
        $load_assets = false;
        
        if ( in_array( $hook, array( 'post.php', 'post-new.php' ) ) ) {
            if ( $post && in_array( $post->post_type, $this->supported_post_types ) ) {
                $load_assets = true;
            }
        } elseif ( strpos( $hook, 'khm-seo-schema' ) !== false ) {
            $load_assets = true;
        }
        
        if ( ! $load_assets ) {
            return;
        }
        
        \error_log( 'KHM SEO: enqueue_admin_assets() FIRING on hook=' . $hook . ' post_type=' . ( $post->post_type ?? 'none' ) );
        
        // Schema admin CSS
        \wp_enqueue_style(
            'khm-seo-schema-admin',
            plugins_url( 'assets/css/schema-admin.css', dirname( __FILE__ ) ),
            array(),
            KHM_SEO_VERSION
        );
        
        // Schema admin JavaScript
        \wp_enqueue_script(
            'khm-seo-schema-admin',
            plugins_url( 'assets/js/schema-admin.js', dirname( __FILE__ ) ),
            array( 'jquery', 'wp-util' ),
            KHM_SEO_VERSION,
            true
        );
        
        // Localize script
        \wp_localize_script( 'khm-seo-schema-admin', 'khm_seo_schema', array(
            'ajax_url' => \admin_url( 'admin-ajax.php' ),
            'nonce' => \wp_create_nonce( 'khm_seo_ajax' ),
            'schema_types' => $this->schema_types,
            'strings' => array(
                'preview_loading' => __( 'Loading preview...', 'khm-seo' ),
                'preview_error' => __( 'Error loading preview', 'khm-seo' ),
                'validation_success' => __( 'Schema validation successful', 'khm-seo' ),
                'validation_error' => __( 'Schema validation failed', 'khm-seo' ),
                'bulk_update_confirm' => __( 'Are you sure you want to update schema for selected posts?', 'khm-seo' ),
            )
        ) );
        
        // CodeMirror for JSON editing
        if ( $this->config['show_debug_info'] ) {
            \wp_enqueue_code_editor( array( 'type' => 'application/json' ) );
        }
    }
    
    /**
     * Add schema column to post list
     * 
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_schema_column( $columns ) {
        $new_columns = array();
        
        foreach ( $columns as $key => $title ) {
            $new_columns[ $key ] = $title;
            
            // Add schema column after title
            if ( $key === 'title' ) {
                $new_columns['khm_schema'] = __( 'Schema', 'khm-seo' );
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Populate schema column content
     * 
     * @param string $column_name Column name
     * @param int    $post_id Post ID
     */
    public function populate_schema_column( $column_name, $post_id ) {
        if ( $column_name !== 'khm_schema' ) {
            return;
        }
        
        $schema_config = \get_post_meta( $post_id, '_khm_seo_schema_config', true );
        
        if ( empty( $schema_config ) || ! $schema_config['enabled'] ) {
            echo '<span class="khm-schema-status disabled">' . __( 'Disabled', 'khm-seo' ) . '</span>';
            return;
        }
        
        $schema_type = $schema_config['type'] ?? 'article';
        $schema_label = $this->schema_types[ $schema_type ]['label'] ?? ucfirst( $schema_type );
        
        echo '<span class="khm-schema-status enabled" title="' . esc_attr( $schema_label ) . '">';
        echo '<span class="dashicons dashicons-yes-alt"></span> ' . esc_html( $schema_label );
        echo '</span>';
        
        // Add validation status if available
        $validation_status = \get_post_meta( $post_id, '_khm_seo_schema_validation', true );
        if ( ! empty( $validation_status ) ) {
            $status_icon = $validation_status['valid'] ? 'yes' : 'warning';
            $status_class = $validation_status['valid'] ? 'valid' : 'invalid';
            
            echo '<br><small class="khm-schema-validation ' . esc_attr( $status_class ) . '">';
            echo '<span class="dashicons dashicons-' . esc_attr( $status_icon ) . '"></span> ';
            echo $validation_status['valid'] ? __( 'Valid', 'khm-seo' ) : __( 'Issues', 'khm-seo' );
            echo '</small>';
        }
    }
    
    /**
     * Add quick edit schema options
     * 
     * @param string $column_name Column name
     * @param string $post_type Post type
     */
    public function add_quick_edit_schema( $column_name, $post_type ) {
        if ( $column_name !== 'khm_schema' || ! in_array( $post_type, $this->supported_post_types ) ) {
            return;
        }
        
        include dirname( __FILE__ ) . '/templates/quick-edit-schema.php';
    }
    
    /**
     * Add bulk edit schema options
     * 
     * @param string $column_name Column name
     * @param string $post_type Post type
     */
    public function add_bulk_edit_schema( $column_name, $post_type ) {
        if ( $column_name !== 'khm_schema' || ! in_array( $post_type, $this->supported_post_types ) ) {
            return;
        }
        
        include dirname( __FILE__ ) . '/templates/bulk-edit-schema.php';
    }
    
    /**
     * Get default schema type for post
     * 
     * @param \WP_Post $post Post object
     * @return string Default schema type
     */
    private function get_default_schema_type( $post ) {
        // Determine based on post type and content
        switch ( $post->post_type ) {
            case 'product':
                return 'product';
            case 'page':
                // Check if it's a company/about page
                if ( strpos( strtolower( $post->post_title ), 'about' ) !== false ||
                     strpos( strtolower( $post->post_slug ), 'about' ) !== false ) {
                    return 'organization';
                }
                return 'article';
            case 'post':
            default:
                return 'article';
        }
    }
    
    /**
     * Update schema cache for faster access
     * 
     * @param int   $post_id Post ID
     * @param array $schema_config Schema configuration
     */
    private function update_schema_cache( $post_id, $schema_config ) {
        if ( ! $schema_config['enabled'] ) {
            \delete_post_meta( $post_id, '_khm_seo_schema_cache' );
            return;
        }
        
        // Generate schema JSON-LD
        $schema_manager = new \KHM_SEO\Schema\SchemaManager();
        $schema_json = $schema_manager->generate_post_schema( $post_id, $schema_config );
        
        // Cache the generated schema
        \update_post_meta( $post_id, '_khm_seo_schema_cache', array(
            'json_ld' => $schema_json,
            'generated' => current_time( 'mysql' ),
            'config_hash' => md5( serialize( $schema_config ) )
        ) );
    }
    
    /**
     * AJAX handlers
     */
    
    /**
     * AJAX preview schema
     */
    public function ajax_preview_schema() {
        \error_log( 'KHM SEO: ajax_preview_schema called. POST=' . wp_json_encode( $_POST ) );
        \check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        
        $post_id = intval( $_POST['post_id'] ?? 0 );
        $schema_config = $_POST['schema_config'] ?? array();
        
        if ( ! $post_id || ! \current_user_can( 'edit_post', $post_id ) ) {
            \wp_send_json_error( __( 'Invalid post or insufficient permissions', 'khm-seo' ) );
        }
        
        try {
            // Use config-aware preview generator that respects the user's selected schema type
            $schema_json = $this->generate_schema_for_preview( $post_id, $schema_config );
            
            \wp_send_json_success( array(
                'schema' => $schema_json,
                'formatted' => wp_json_encode( $schema_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
            ) );
            
        } catch ( \Exception $e ) {
            \wp_send_json_error( $e->getMessage() );
        }
    }
    
    /**
     * AJAX validate schema
     */
    public function ajax_validate_schema() {
        \check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        
        // WordPress applies addslashes to $_POST via wp_magic_quotes.
        // json_decode will choke on escaped quotes like {\"@context\":...
        $schema_json = \wp_unslash( $_POST['schema_json'] ?? '' );
        
        \error_log( 'KHM SEO: ajax_validate_schema received. Length=' . strlen( $schema_json ) . ' First 200 chars: ' . substr( $schema_json, 0, 200 ) );
        
        if ( empty( $schema_json ) ) {
            \wp_send_json_error( __( 'No schema data provided', 'khm-seo' ) );
        }
        
        try {
            // Basic JSON validation
            $schema_data = json_decode( $schema_json, true );
            
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $json_err = json_last_error_msg();
                \error_log( 'KHM SEO: ajax_validate_schema JSON decode error: ' . $json_err . ' Input first 500 chars: ' . substr( $schema_json, 0, 500 ) );
                \wp_send_json_error( __( 'Invalid JSON format', 'khm-seo' ) . ' — ' . $json_err );
            }
            
            // Schema.org validation
            $validation_result = $this->validate_schema_structure( $schema_data );
            
            \wp_send_json_success( $validation_result );
            
        } catch ( Exception $e ) {
            \wp_send_json_error( $e->getMessage() );
        }
    }
    
    /**
     * AJAX bulk schema update
     */
    public function ajax_bulk_schema_update() {
        \check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        
        if ( ! \current_user_can( 'edit_posts' ) ) {
            \wp_send_json_error( __( 'Insufficient permissions', 'khm-seo' ) );
        }
        
        $post_ids = array_map( 'intval', $_POST['post_ids'] ?? array() );
        $schema_config = $_POST['schema_config'] ?? array();
        
        $updated = 0;
        $errors = array();
        
        foreach ( $post_ids as $post_id ) {
            if ( ! \current_user_can( 'edit_post', $post_id ) ) {
                $errors[] = sprintf( __( 'Cannot edit post %d', 'khm-seo' ), $post_id );
                continue;
            }
            
            \update_post_meta( $post_id, '_khm_seo_schema_config', $schema_config );
            $this->update_schema_cache( $post_id, $schema_config );
            $updated++;
        }
        
        \wp_send_json_success( array(
            'updated' => $updated,
            'errors' => $errors,
            'message' => sprintf( __( 'Updated schema for %d posts', 'khm-seo' ), $updated )
        ) );
    }
    
    /**
     * Validate schema structure using SchemaGenerator's comprehensive validation
     * which covers all 12+ schema types including the 4 new rich types.
     *
     * @param array $schema_data Schema data
     * @return array Validation result
     */
    private function validate_schema_structure( $schema_data ) {
        // Delegate to SchemaGenerator::validate_schema() for comprehensive validation
        $generator = new \KHM_SEO\Schema\SchemaGenerator();
        return $generator->validate_schema( $schema_data );
    }

    /**
     * Generate schema preview for a post and return formatted JSON-LD.
     *
     * Called via AJAX from editor-modal.js and schema-admin.js.
     *
     * @param int   $post_id       Post ID.
     * @param array $schema_config Schema configuration from form.
     * @return array Full schema data array.
     */
    public function generate_schema_for_preview( $post_id, $schema_config = array() ) {
        $post = \get_post( $post_id );
        if ( ! $post ) {
            return array();
        }

        // Start with SchemaGenerator's native generation
        $generator = new \KHM_SEO\Schema\SchemaGenerator();
        $json_ld = $generator->generate_schema( $post );

        // Strip <script> wrapper
        $json = preg_replace( '#<script[^>]*>|</script>#', '', $json_ld );
        $decoded = json_decode( trim( $json ), true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return array();
        }

        // If a specific schema config type was provided, swap the primary type
        if ( ! empty( $schema_config['type'] ) ) {
            $target_type = $generator->resolve_schema_type_key( $schema_config['type'] );
            // Walk @graph and replace the first non-global type with the target
            if ( $target_type ) {
                $global_types = array( 'Organization', 'WebSite', 'BreadcrumbList' );
                foreach ( $decoded['@graph'] as &$item ) {
                    $t = $item['@type'] ?? '';
                    if ( ! in_array( $t, $global_types, true ) ) {
                        // Re-generate with the specific type
                        $schema_item = $this->generate_schema_item_for_type( $target_type, $post, $generator );
                        if ( $schema_item ) {
                            $item = $schema_item;
                        }
                        break;
                    }
                }
                unset( $item );
            }
        }

        return $decoded;
    }

    /**
     * Generate a single schema item of a given type using SchemaGenerator.
     *
     * @param string          $type      PascalCase type (e.g. 'TechArticle').
     * @param \WP_Post        $post      Post object.
     * @param SchemaGenerator $generator SchemaGenerator instance.
     * @return array|null Schema item array.
     */
    private function generate_schema_item_for_type( $type, $post, $generator ) {
        $method = 'generate_' . strtolower( $type ) . '_schema';
        // Access private methods on SchemaGenerator via reflection for types not exposed publicly
        $reflection = new \ReflectionClass( $generator );
        if ( $reflection->hasMethod( $method ) ) {
            $rm = $reflection->getMethod( $method );
            $rm->setAccessible( true );
            $result = $rm->invoke( $generator, $post );
            if ( is_array( $result ) && ! empty( $result ) ) {
                return $result;
            }
        }
        return null;
    }

    /**
     * AJAX test with Google — open Rich Results Test URL.
     *
     * For drafts, generates a temporary preview URL that Google can crawl
     * (requires the post to be saved at least once). Returns the Google
     * Rich Results Test URL with the post permalink.
     */
    public function ajax_test_with_google() {
        \check_ajax_referer( 'khm_seo_ajax', 'nonce' );

        $post_id = intval( $_POST['post_id'] ?? 0 );

        if ( ! $post_id || ! \current_user_can( 'edit_post', $post_id ) ) {
            \wp_send_json_error( __( 'Invalid post or insufficient permissions', 'khm-seo' ) );
        }

        $permalink = \get_permalink( $post_id );

        if ( ! $permalink ) {
            \wp_send_json_error( __( 'Post must be saved (not auto-draft) before testing with Google. Please save the post first.', 'khm-seo' ) );
        }

        $google_url = 'https://search.google.com/test/rich-results?url=' . urlencode( $permalink );

        \wp_send_json_success( array(
            'google_url' => $google_url,
            'post_url'   => $permalink,
        ) );
    }
}