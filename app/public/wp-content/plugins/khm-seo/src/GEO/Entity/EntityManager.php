<?php
/**
 * Entity Manager - Core Business Logic
 * 
 * Central coordinator for entity management operations including CRUD,
 * validation, relationships, and governance policies.
 * 
 * Features:
 * - Entity CRUD operations with validation
 * - Alias management and conflict resolution  
 * - Link rule enforcement and auto-linking policies
 * - Scope inheritance (Global → Client → Site)
 * - Governance and audit trails
 * - Integration with existing schema system
 * 
 * @package KHM_SEO\GEO\Entity
 * @since 2.0.0
 * @version 2.0.0
 */

namespace KHM_SEO\GEO\Entity;

use KHM_SEO\GEO\Database\EntityTables;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Entity Manager Class
 * Main controller for all entity operations
 */
class EntityManager {
    /**
     * Set aliases for an entity (replace all)
     * @param int $entity_id
     * @param array $aliases
     * @return bool
     */
    public function set_entity_aliases( $entity_id, $aliases ) {
        global $wpdb;
        $table = $this->tables->get_table_name( 'entity_aliases' );
        // Remove existing aliases
        $wpdb->delete( $table, array( 'entity_id' => $entity_id ) );
        // Insert new aliases
        foreach ( $aliases as $alias ) {
            if ( ! empty( $alias ) ) {
                $wpdb->insert( $table, array( 'entity_id' => $entity_id, 'alias' => $alias ) );
            }
        }
        return true;
    }

    /**
     * Delete an entity and its related data
     * @param int $entity_id
     * @return bool
     */
    public function delete_entity( $entity_id ) {
        global $wpdb;
        // Delete from entities table
        $wpdb->delete( $this->tables->get_table_name( 'entities' ), array( 'id' => $entity_id ) );
        // Delete aliases
        $wpdb->delete( $this->tables->get_table_name( 'entity_aliases' ), array( 'entity_id' => $entity_id ) );
        // Delete link rules
        $wpdb->delete( $this->tables->get_table_name( 'entity_link_rules' ), array( 'entity_id' => $entity_id ) );
        // Delete scopes
        $wpdb->delete( $this->tables->get_table_name( 'entity_scopes' ), array( 'entity_id' => $entity_id ) );
        // Delete page-entity relationships
        $wpdb->delete( $this->tables->get_table_name( 'page_entities' ), array( 'entity_id' => $entity_id ) );
        $this->clear_entity_cache();
        return true;
    }
    /**
     * Get valid entity types
     * @return array
     */
    public function get_valid_types() {
        return $this->valid_types;
    }

    /**
     * Get valid entity scopes
     * @return array
     */
    public function get_valid_scopes() {
        return $this->valid_scopes;
    }

    /**
     * Get table name
     * @param string $table Table key
     * @return string
     */
    public function get_table_name( $table ) {
        return $this->tables->get_table_name( $table );
    }

    /**
     * Get content analyzer instance
     * @return \KHM_SEO\GEO\Content\ContentAnalyzer
     */
    public function get_content_analyzer() {
        return $this->content_analyzer;
    }

    /**
     * Get scoring engine instance
     * @return \KHM_SEO\GEO\Scoring\ScoringEngine
     */
    public function get_scoring_engine() {
        return $this->scoring_engine;
    }
    
    /**
     * @var EntityTables Database tables manager
     */
    private $tables;
    
    /**
     * @var array Valid entity types
     */
    private $valid_types = array(
        'Organization', 'Product', 'Technology', 'Metric', 
        'Acronym', 'Term', 'Person', 'Place', 'Thing'
    );
    
    /**
     * @var array Valid entity scopes
     */
    private $valid_scopes = array( 'global', 'client', 'site' );
    
    /**
     * @var array Valid entity statuses
     */
    private $valid_statuses = array( 'active', 'deprecated' );
    
    /**
     * @var array Valid link modes
     */
    private $valid_link_modes = array( 'first_only', 'all', 'manual', 'never' );
    
    /**
     * @var array Valid entity roles
     */
    private $valid_roles = array( 'primary', 'about', 'mentions' );
    
    /**
     * @var array Cache for frequently accessed entities
     */
    private $entity_cache = array();

    /**
     * @var \KHM_SEO\GEO\AutoLink\AutoLinker Auto-linker instance
     */
    private $auto_linker;

    /**
     * @var \KHM_SEO\GEO\Content\ContentAnalyzer Content analyzer instance
     */
    private $content_analyzer;

    /**
     * @var \KHM_SEO\GEO\Scoring\ScoringEngine Scoring engine instance
     */
    private $scoring_engine;

    /**
     * Constructor
     */
    public function __construct() {
        $this->tables = new EntityTables();
        $this->auto_linker = new \KHM_SEO\GEO\AutoLink\AutoLinker( $this );
        $this->content_analyzer = new \KHM_SEO\GEO\Content\ContentAnalyzer( $this );
        $this->scoring_engine = new \KHM_SEO\GEO\Scoring\ScoringEngine();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Post save hooks for entity detection
        add_action( 'save_post', array( $this, 'on_post_save' ), 20 );
        add_action( 'delete_post', array( $this, 'on_post_delete' ) );
        
        // Content filtering for auto-linking
        add_filter( 'the_content', array( $this, 'auto_link_entities' ), 15 );
        
        // Schema integration hooks
        add_filter( 'khm_seo_schema_data', array( $this, 'add_entities_to_schema' ), 10, 2 );
    }
    
    // ===== ENTITY CRUD OPERATIONS =====
    
    /**
     * Create a new entity
     * 
     * @param array $data Entity data
     * @return int|false Entity ID on success, false on failure
     */
    public function create_entity( $data ) {
        global $wpdb;
        
        // Validate required fields
        if ( empty( $data['canonical'] ) || empty( $data['type'] ) ) {
            return false;
        }
        
        // Validate data
        $validated_data = $this->validate_entity_data( $data );
        if ( is_wp_error( $validated_data ) ) {
            return false;
        }
        
        // Check for duplicates
        if ( $this->canonical_exists( $validated_data['canonical'], $validated_data['scope'] ) ) {
            return false;
        }
        
        // Generate slug if not provided
        if ( empty( $validated_data['slug'] ) ) {
            $validated_data['slug'] = $this->generate_entity_slug( $validated_data['canonical'] );
        }
        
        // Insert entity
        $result = $wpdb->insert(
            $this->tables->get_table_name( 'entities' ),
            $validated_data,
            array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 
                '%d', '%d', '%d', '%s'
            )
        );
        
        if ( $result === false ) {
            return false;
        }
        
        $entity_id = $wpdb->insert_id;
        
        // Clear cache
        $this->clear_entity_cache();
        
        // Log action
        $this->log_entity_action( $entity_id, 'created', $validated_data );
        
        return $entity_id;
    }
    
    /**
     * Update an existing entity
     * 
     * @param int $entity_id Entity ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function update_entity( $entity_id, $data ) {
        global $wpdb;
        
        $entity = $this->get_entity( $entity_id );
        if ( ! $entity ) {
            return false;
        }
        
        // Validate data
        $validated_data = $this->validate_entity_data( $data, $entity_id );
        if ( is_wp_error( $validated_data ) ) {
            return false;
        }
        
        // Update entity
        $result = $wpdb->update(
            $this->tables->get_table_name( 'entities' ),
            $validated_data,
            array( 'id' => $entity_id ),
            null,
            array( '%d' )
        );
        
        if ( $result === false ) {
            return false;
        }
        
        // Clear cache
        $this->clear_entity_cache();
        
        // Log action
        $this->log_entity_action( $entity_id, 'updated', $validated_data );
        
        // Trigger content regeneration if significant changes
        if ( isset( $validated_data['canonical'] ) && $validated_data['canonical'] !== $entity->canonical ) {
            $this->trigger_content_regeneration( $entity_id );
        }
        
        return true;
    }
    
    /**
     * Get entity by ID
     * 
     * @param int $entity_id Entity ID
     * @return object|null Entity object or null
     */
    public function get_entity( $entity_id ) {
        global $wpdb;
        
        // Check cache first
        if ( isset( $this->entity_cache[ $entity_id ] ) ) {
            return $this->entity_cache[ $entity_id ];
        }
        
        $entity = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables->get_table_name( 'entities' )} WHERE id = %d",
            $entity_id
        ) );
        
        if ( $entity ) {
            // Decode JSON fields
            if ( ! empty( $entity->same_as ) ) {
                $entity->same_as = json_decode( $entity->same_as, true );
            }
            
            $this->entity_cache[ $entity_id ] = $entity;
        }
        
        return $entity;
    }
    
    /**
     * Find entity by canonical name and scope
     * 
     * @param string $canonical Canonical name
     * @param string $scope Entity scope
     * @return object|null Entity object or null
     */
    public function find_entity_by_canonical( $canonical, $scope = 'site' ) {
        global $wpdb;
        
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables->get_table_name( 'entities' )} 
             WHERE canonical = %s AND scope = %s AND status = 'active'",
            $canonical, $scope
        ) );
    }
    
    /**
     * Search entities with filters
     * 
     * @param array $args Search parameters
     * @return array Search results
     */
    public function search_entities( $args = array() ) {
        global $wpdb;
        
        $defaults = array(
            'search' => '',
            'type' => '',
            'scope' => '',
            'status' => 'active',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'canonical',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        $where_clauses = array( '1=1' );
        $prepare_values = array();
        
        // Search in canonical and aliases
        if ( ! empty( $args['search'] ) ) {
            $where_clauses[] = "(e.canonical LIKE %s OR e.id IN (
                SELECT a.entity_id FROM {$this->tables->get_table_name( 'aliases' )} a 
                WHERE a.alias LIKE %s
            ))";
            $search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $prepare_values[] = $search_term;
            $prepare_values[] = $search_term;
        }
        
        // Filter by type
        if ( ! empty( $args['type'] ) && in_array( $args['type'], $this->valid_types ) ) {
            $where_clauses[] = "e.type = %s";
            $prepare_values[] = $args['type'];
        }
        
        // Filter by scope
        if ( ! empty( $args['scope'] ) && in_array( $args['scope'], $this->valid_scopes ) ) {
            $where_clauses[] = "e.scope = %s";
            $prepare_values[] = $args['scope'];
        }
        
        // Filter by status
        if ( ! empty( $args['status'] ) && in_array( $args['status'], $this->valid_statuses ) ) {
            $where_clauses[] = "e.status = %s";
            $prepare_values[] = $args['status'];
        }
        
        $where_sql = implode( ' AND ', $where_clauses );
        
        // Build order clause
        $orderby = in_array( $args['orderby'], array( 'canonical', 'type', 'updated_at', 'created_at' ) ) 
            ? $args['orderby'] : 'canonical';
        $order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';
        
        // Build final query
        $sql = "SELECT e.*, 
                (SELECT COUNT(*) FROM {$this->tables->get_table_name( 'aliases' )} WHERE entity_id = e.id) as alias_count,
                (SELECT COUNT(*) FROM {$this->tables->get_table_name( 'page_entities' )} WHERE entity_id = e.id) as usage_count
                FROM {$this->tables->get_table_name( 'entities' )} e
                WHERE $where_sql
                ORDER BY e.$orderby $order
                LIMIT %d OFFSET %d";
        
        $prepare_values[] = intval( $args['limit'] );
        $prepare_values[] = intval( $args['offset'] );
        
        return $wpdb->get_results( $wpdb->prepare( $sql, $prepare_values ) );
    }
    
    // ===== ALIAS MANAGEMENT =====
    
    /**
     * Add alias to entity
     * 
     * @param int $entity_id Entity ID
     * @param string $alias Alias text
     * @param bool $is_banned Whether alias is banned
     * @param string $notes Optional notes
     * @return bool Success status
     */
    public function add_entity_alias( $entity_id, $alias, $is_banned = false, $notes = '' ) {
        global $wpdb;
        
        if ( empty( $alias ) || ! $this->get_entity( $entity_id ) ) {
            return false;
        }
        
        // Check for existing alias
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->tables->get_table_name( 'aliases' )} 
             WHERE entity_id = %d AND alias = %s",
            $entity_id, $alias
        ) );
        
        if ( $existing ) {
            return false; // Alias already exists
        }
        
        $result = $wpdb->insert(
            $this->tables->get_table_name( 'aliases' ),
            array(
                'entity_id' => $entity_id,
                'alias' => $alias,
                'is_banned' => $is_banned ? 1 : 0,
                'notes' => $notes
            ),
            array( '%d', '%s', '%d', '%s' )
        );
        
        return $result !== false;
    }
    
    /**
     * Get all aliases for an entity
     * 
     * @param int $entity_id Entity ID
     * @return array Aliases
     */
    public function get_entity_aliases( $entity_id ) {
        global $wpdb;
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->tables->get_table_name( 'aliases' )} 
             WHERE entity_id = %d ORDER BY alias ASC",
            $entity_id
        ) );
    }
    
    /**
     * Find entity by alias
     * 
     * @param string $alias Alias text
     * @return object|null Entity object or null
     */
    public function find_entity_by_alias( $alias ) {
        global $wpdb;
        
        $result = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.* FROM {$this->tables->get_table_name( 'entities' )} e
             JOIN {$this->tables->get_table_name( 'aliases' )} a ON e.id = a.entity_id
             WHERE a.alias = %s AND e.status = 'active' AND a.is_banned = 0",
            $alias
        ) );
        
        if ( $result && ! empty( $result->same_as ) ) {
            $result->same_as = json_decode( $result->same_as, true );
        }
        
        return $result;
    }
    
    // ===== LINK RULES MANAGEMENT =====
    
    /**
     * Set link rules for entity
     * 
     * @param int $entity_id Entity ID
     * @param array $rules Link rules data
     * @return bool Success status
     */
    public function set_entity_link_rules( $entity_id, $rules ) {
        global $wpdb;
        
        if ( ! $this->get_entity( $entity_id ) ) {
            return false;
        }
        
        // Validate link mode
        if ( ! empty( $rules['link_mode'] ) && ! in_array( $rules['link_mode'], $this->valid_link_modes ) ) {
            return false;
        }
        
        $defaults = array(
            'link_mode' => 'first_only',
            'nofollow' => false,
            'new_tab' => false,
            'skip_in_headings' => true,
            'skip_in_quotes' => true,
            'skip_in_code' => true
        );
        
        $rules = wp_parse_args( $rules, $defaults );
        $rules['entity_id'] = $entity_id;
        
        // Check if rules exist
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->tables->get_table_name( 'link_rules' )} WHERE entity_id = %d",
            $entity_id
        ) );
        
        if ( $existing ) {
            // Update existing
            return $wpdb->update(
                $this->tables->get_table_name( 'link_rules' ),
                $rules,
                array( 'entity_id' => $entity_id )
            ) !== false;
        } else {
            // Insert new
            return $wpdb->insert(
                $this->tables->get_table_name( 'link_rules' ),
                $rules,
                array( '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d' )
            ) !== false;
        }
    }
    
    /**
     * Get link rules for entity
     * 
     * @param int $entity_id Entity ID
     * @return object|null Link rules object or null
     */
    public function get_entity_link_rules( $entity_id ) {
        global $wpdb;
        
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables->get_table_name( 'link_rules' )} WHERE entity_id = %d",
            $entity_id
        ) );
    }
    
    // ===== VALIDATION METHODS =====
    
    /**
     * Validate entity data
     * 
     * @param array $data Entity data
     * @param int $entity_id Existing entity ID for updates
     * @return array|WP_Error Validated data or error
     */
    private function validate_entity_data( $data, $entity_id = null ) {
        $validated = array();
        
        // Canonical (required)
        if ( empty( $data['canonical'] ) ) {
            return new \WP_Error( 'missing_canonical', 'Canonical name is required' );
        }
        $validated['canonical'] = sanitize_text_field( $data['canonical'] );
        
        // Type (required)
        if ( empty( $data['type'] ) || ! in_array( $data['type'], $this->valid_types ) ) {
            return new \WP_Error( 'invalid_type', 'Invalid entity type' );
        }
        $validated['type'] = $data['type'];
        
        // Scope (optional, default to 'site')
        $validated['scope'] = isset( $data['scope'] ) && in_array( $data['scope'], $this->valid_scopes ) 
            ? $data['scope'] : 'site';
        
        // Status (optional, default to 'active')
        $validated['status'] = isset( $data['status'] ) && in_array( $data['status'], $this->valid_statuses ) 
            ? $data['status'] : 'active';
        
        // Optional fields
        if ( isset( $data['slug'] ) ) {
            $validated['slug'] = \sanitize_title( $data['slug'] );
        }
        
        if ( isset( $data['definition'] ) ) {
            $validated['definition'] = wp_kses_post( $data['definition'] );
        }
        
        if ( isset( $data['preferred_capitalization'] ) ) {
            $validated['preferred_capitalization'] = sanitize_text_field( $data['preferred_capitalization'] );
        }
        
        if ( isset( $data['same_as'] ) && is_array( $data['same_as'] ) ) {
            $validated['same_as'] = \wp_json_encode( $data['same_as'] );
        }
        
        if ( isset( $data['replacement_entity_id'] ) && is_numeric( $data['replacement_entity_id'] ) ) {
            $validated['replacement_entity_id'] = intval( $data['replacement_entity_id'] );
        }
        
        if ( isset( $data['owner_user_id'] ) && is_numeric( $data['owner_user_id'] ) ) {
            $validated['owner_user_id'] = intval( $data['owner_user_id'] );
        }
        
        if ( isset( $data['review_cadence_days'] ) && is_numeric( $data['review_cadence_days'] ) ) {
            $validated['review_cadence_days'] = intval( $data['review_cadence_days'] );
        }
        
        if ( isset( $data['last_reviewed_at'] ) ) {
            $validated['last_reviewed_at'] = sanitize_text_field( $data['last_reviewed_at'] );
        }
        
        return $validated;
    }
    
    /**
     * Check if canonical name exists
     * 
     * @param string $canonical Canonical name
     * @param string $scope Entity scope
     * @param int $exclude_id Entity ID to exclude
     * @return bool Whether canonical exists
     */
    private function canonical_exists( $canonical, $scope, $exclude_id = null ) {
        global $wpdb;
        
        $sql = "SELECT id FROM {$this->tables->get_table_name( 'entities' )} 
                WHERE canonical = %s AND scope = %s";
        $params = array( $canonical, $scope );
        
        if ( $exclude_id ) {
            $sql .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        return $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) !== null;
    }
    
    /**
     * Generate entity slug
     * 
     * @param string $canonical Canonical name
     * @return string Generated slug
     */
    private function generate_entity_slug( $canonical ) {
        return sanitize_title( $canonical );
    }
    
    // ===== UTILITY METHODS =====
    
    /**
     * Clear entity cache
     */
    private function clear_entity_cache() {
        $this->entity_cache = array();
    }
    
    /**
     * Log entity action
     * 
     * @param int $entity_id Entity ID
     * @param string $action Action type
     * @param array $data Action data
     */
    private function log_entity_action( $entity_id, $action, $data = array() ) {
        // This could be expanded to use a separate audit log table
        error_log( sprintf( 
            'KHM SEO Entity Action: %s on entity %d by user %d', 
            $action, 
            $entity_id, 
            get_current_user_id() 
        ) );
    }
    
    /**
     * Trigger content regeneration for entity changes
     * 
     * @param int $entity_id Entity ID
     */
    private function trigger_content_regeneration( $entity_id ) {
        // Find all posts using this entity
        global $wpdb;
        
        $post_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$this->tables->get_table_name( 'page_entities' )} 
             WHERE entity_id = %d",
            $entity_id
        ) );
        
        foreach ( $post_ids as $post_id ) {
            // Clear schema cache
            delete_post_meta( $post_id, '_khm_seo_schema_cache' );
            
            // Schedule regeneration (could use wp_cron)
            wp_schedule_single_event( time() + 60, 'khm_seo_regenerate_post_schema', array( $post_id ) );
        }
    }
    
    // ===== CONTENT PROCESSING HOOKS =====
    
    /**
     * Handle post save to detect entities
     * 
     * @param int $post_id Post ID
     */
    public function on_post_save( $post_id ) {
        // Skip revisions and auto-saves
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        
        // Auto-detect entities in content (basic implementation)
        $this->auto_detect_entities_in_post( $post_id );
    }
    
    /**
     * Handle post deletion
     * 
     * @param int $post_id Post ID
     */
    public function on_post_delete( $post_id ) {
        global $wpdb;
        
        // Clean up page-entity relationships
        $wpdb->delete(
            $this->tables->get_table_name( 'page_entities' ),
            array( 'post_id' => $post_id ),
            array( '%d' )
        );
    }
    
    /**
     * Auto-link entities in content
     * 
     * @param string $content Post content
     * @return string Modified content
     */
    public function auto_link_entities( $content ) {
        global $post;

        if ( ! $post || is_admin() ) {
            return $content;
        }

        // Configure auto-linker based on global settings
        $config = array(
            'max_auto_links_per_post' => get_option( 'khm_geo_max_auto_links', 10 ),
            'auto_linking_mode' => get_option( 'khm_geo_auto_linking_mode', 'first_only' )
        );
        $this->auto_linker->set_config( $config );

        return $this->auto_linker->process_content( $content, $post->ID );
    }
    
    /**
     * Add entities to schema markup
     * 
     * @param array $schema_data Existing schema data
     * @param int $post_id Post ID
     * @return array Modified schema data
     */
    public function add_entities_to_schema( $schema_data, $post_id ) {
        $page_entities = $this->get_post_entities( $post_id );
        
        if ( empty( $page_entities ) ) {
            return $schema_data;
        }
        
        $about_entities = array();
        $mentions_entities = array();
        
        foreach ( $page_entities as $page_entity ) {
            $entity = $this->get_entity( $page_entity->entity_id );
            if ( ! $entity ) {
                continue;
            }
            
            $entity_schema = array(
                '@type' => $entity->type,
                'name' => $entity->canonical
            );
            
            if ( ! empty( $entity->same_as ) ) {
                $entity_schema['sameAs'] = $entity->same_as;
            }
            
            if ( $page_entity->role === 'about' || $page_entity->role === 'primary' ) {
                $about_entities[] = $entity_schema;
            } else {
                $mentions_entities[] = $entity_schema;
            }
        }
        
        // Add to schema data
        if ( ! empty( $about_entities ) ) {
            $schema_data['about'] = count( $about_entities ) === 1 ? $about_entities[0] : $about_entities;
        }
        
        if ( ! empty( $mentions_entities ) ) {
            $schema_data['mentions'] = $mentions_entities;
        }
        
        return $schema_data;
    }
    
    // ===== HELPER METHODS =====
    
    /**
     * Get entities that can be auto-linked
     * 
     * @return array Linkable entities
     */
    private function get_linkable_entities() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT e.* FROM {$this->tables->get_table_name( 'entities' )} e
             JOIN {$this->tables->get_table_name( 'link_rules' )} lr ON e.id = lr.entity_id
             WHERE e.status = 'active' 
             AND lr.link_mode IN ('first_only', 'all') 
             AND lr.internal_url IS NOT NULL"
        );
    }
    
    /**
     * Get entities associated with a post
     * 
     * @param int $post_id Post ID
     * @return array Post entities
     */
    public function get_post_entities( $post_id ) {
        global $wpdb;
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->tables->get_table_name( 'page_entities' )} 
             WHERE post_id = %d ORDER BY role ASC, confidence DESC",
            $post_id
        ) );
    }
    
    /**
     * Auto-detect entities in post content
     * 
     * @param int $post_id Post ID
     */
    private function auto_detect_entities_in_post( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }
        
        $content = $post->post_title . ' ' . $post->post_content;
        
        // Get all active entities and their aliases
        $entities = $this->search_entities( array( 'limit' => 1000 ) );
        
        foreach ( $entities as $entity ) {
            $found = false;
            $confidence = 0.5; // Base confidence
            
            // Check for canonical name
            if ( stripos( $content, $entity->canonical ) !== false ) {
                $found = true;
                $confidence = 0.8;
            }
            
            // Check aliases
            $aliases = $this->get_entity_aliases( $entity->id );
            foreach ( $aliases as $alias ) {
                if ( ! $alias->is_banned && stripos( $content, $alias->alias ) !== false ) {
                    $found = true;
                    $confidence = max( $confidence, 0.6 );
                }
            }
            
            if ( $found ) {
                $this->add_entity_to_post( $post_id, $entity->id, 'mentions', $confidence, 'auto_link' );
            }
        }
    }
    
    /**
     * Add entity to post relationship
     * 
     * @param int $post_id Post ID
     * @param int $entity_id Entity ID
     * @param string $role Entity role
     * @param float $confidence Confidence score
     * @param string $detected_by Detection method
     * @return bool Success status
     */
    public function add_entity_to_post( $post_id, $entity_id, $role = 'mentions', $confidence = 1.0, $detected_by = 'manual' ) {
        global $wpdb;
        
        // Check if relationship already exists
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->tables->get_table_name( 'page_entities' )} 
             WHERE post_id = %d AND entity_id = %d AND role = %s",
            $post_id, $entity_id, $role
        ) );
        
        if ( $existing ) {
            // Update confidence if this is higher
            return $wpdb->update(
                $this->tables->get_table_name( 'page_entities' ),
                array( 
                    'confidence' => $confidence,
                    'detected_by' => $detected_by
                ),
                array( 'id' => $existing ),
                array( '%f', '%s' ),
                array( '%d' )
            ) !== false;
        }
        
        // Insert new relationship
        return $wpdb->insert(
            $this->tables->get_table_name( 'page_entities' ),
            array(
                'post_id' => $post_id,
                'entity_id' => $entity_id,
                'role' => $role,
                'confidence' => $confidence,
                'detected_by' => $detected_by
            ),
            array( '%d', '%d', '%s', '%f', '%s' )
        ) !== false;
    }
}