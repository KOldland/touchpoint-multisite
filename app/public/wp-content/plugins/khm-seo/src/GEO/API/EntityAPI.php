<?php
/**
 * Entity REST API Endpoints
 * 
 * Provides REST API endpoints for entity management, search, and operations.
 * Supports AJAX functionality for Elementor widgets and admin interface.
 * 
 * Endpoints:
 * - GET /geo/v1/entities - Search and list entities
 * - POST /geo/v1/entities - Create new entity
 * - PUT /geo/v1/entities/{id} - Update entity
 * - DELETE /geo/v1/entities/{id} - Delete entity
 * - GET /geo/v1/entities/{id}/aliases - Get entity aliases
 * - POST /geo/v1/entities/{id}/aliases - Add entity alias
 * - POST /geo/v1/entities/import - Import entities from CSV
 * - POST /geo/v1/entities/refactor - Bulk refactor operations
 * 
 * @package KHM_SEO\GEO\API
 * @since 2.0.0
 * @version 2.0.0
 */

namespace KHM_SEO\GEO\API;

use KHM_SEO\GEO\Entity\EntityManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Entity REST API Class
 * Handles all entity-related REST endpoints
 */
class EntityAPI {
    
    /**
     * @var EntityManager Entity manager instance
     */
    private $entity_manager;
    
    /**
     * @var string API namespace
     */
    private $namespace = 'geo/v1';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->entity_manager = new EntityManager();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        
        // Legacy AJAX support for backward compatibility
        add_action( 'wp_ajax_khm_seo_search_entities', array( $this, 'ajax_search_entities' ) );
        add_action( 'wp_ajax_khm_seo_create_entity', array( $this, 'ajax_create_entity' ) );
        add_action( 'wp_ajax_khm_seo_validate_content', array( $this, 'ajax_validate_content' ) );
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Entities collection
        register_rest_route( $this->namespace, '/entities', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_entities' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
                'args' => $this->get_search_params()
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'create_entity' ),
                'permission_callback' => array( $this, 'check_write_permission' ),
                'args' => $this->get_entity_schema()
            )
        ) );
        
        // Individual entity
        register_rest_route( $this->namespace, '/entities/(?P<id>\d+)', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_entity' ),
                'permission_callback' => array( $this, 'check_read_permission' )
            ),
            array(
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => array( $this, 'update_entity' ),
                'permission_callback' => array( $this, 'check_write_permission' ),
                'args' => $this->get_entity_schema()
            ),
            array(
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => array( $this, 'delete_entity' ),
                'permission_callback' => array( $this, 'check_delete_permission' )
            )
        ) );
        
        // Entity aliases
        register_rest_route( $this->namespace, '/entities/(?P<id>\d+)/aliases', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_entity_aliases' ),
                'permission_callback' => array( $this, 'check_read_permission' )
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'add_entity_alias' ),
                'permission_callback' => array( $this, 'check_write_permission' ),
                'args' => array(
                    'alias' => array(
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'is_banned' => array(
                        'type' => 'boolean',
                        'default' => false
                    ),
                    'notes' => array(
                        'type' => 'string',
                        'sanitize_callback' => 'wp_kses_post'
                    )
                )
            )
        ) );
        
        // Import/Export operations
        register_rest_route( $this->namespace, '/entities/import', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'import_entities' ),
            'permission_callback' => array( $this, 'check_import_permission' )
        ) );
        
        register_rest_route( $this->namespace, '/entities/export', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array( $this, 'export_entities' ),
            'permission_callback' => array( $this, 'check_read_permission' )
        ) );
        
        // Bulk operations
        register_rest_route( $this->namespace, '/entities/refactor', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'refactor_entities' ),
            'permission_callback' => array( $this, 'check_write_permission' ),
            'args' => array(
                'operation' => array(
                    'required' => true,
                    'enum' => array( 'alias_to_canonical', 'bulk_update', 'deprecate_replace' )
                ),
                'entity_ids' => array(
                    'required' => true,
                    'type' => 'array'
                ),
                'post_ids' => array(
                    'type' => 'array',
                    'default' => array()
                )
            )
        ) );
        
        // Content validation
        register_rest_route( $this->namespace, '/content/validate', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'validate_content' ),
            'permission_callback' => array( $this, 'check_read_permission' ),
            'args' => array(
                'content' => array(
                    'required' => true,
                    'type' => 'string'
                ),
                'post_id' => array(
                    'type' => 'integer'
                )
            )
        ) );
    }
    
    // ===== ENTITIES ENDPOINTS =====
    
    /**
     * Get entities with search and filtering
     * 
     * @param \WP_REST_Request $request REST request object
     * @return \WP_REST_Response Response object
     */
    public function get_entities( $request ) {
        $params = array(
            'search' => $request->get_param( 'search' ),
            'type' => $request->get_param( 'type' ),
            'scope' => $request->get_param( 'scope' ),
            'status' => $request->get_param( 'status' ) ?: 'active',
            'limit' => min( $request->get_param( 'per_page' ) ?: 50, 100 ),
            'offset' => ( ( $request->get_param( 'page' ) ?: 1 ) - 1 ) * ( $request->get_param( 'per_page' ) ?: 50 ),
            'orderby' => $request->get_param( 'orderby' ) ?: 'canonical',
            'order' => $request->get_param( 'order' ) ?: 'ASC'
        );
        
        $entities = $this->entity_manager->search_entities( $params );
        
        $formatted_entities = array();
        foreach ( $entities as $entity ) {
            $formatted_entities[] = $this->format_entity_response( $entity );
        }
        
        $response = rest_ensure_response( $formatted_entities );
        
        // Add pagination headers if needed
        $total_entities = $this->get_total_entities_count( $params );
        $max_pages = ceil( $total_entities / $params['limit'] );
        
        $response->header( 'X-WP-Total', $total_entities );
        $response->header( 'X-WP-TotalPages', $max_pages );
        
        return $response;
    }
    
    /**
     * Get single entity
     * 
     * @param \WP_REST_Request $request REST request object
     * @return \WP_REST_Response Response object
     */
    public function get_entity( $request ) {
        $entity_id = $request->get_param( 'id' );
        $entity = $this->entity_manager->get_entity( $entity_id );
        
        if ( ! $entity ) {
            return new \WP_Error( 'entity_not_found', 'Entity not found', array( 'status' => 404 ) );
        }
        
        return rest_ensure_response( $this->format_entity_response( $entity, true ) );
    }
    
    /**
     * Create new entity
     * 
     * @param \WP_REST_Request $request REST request object
     * @return \WP_REST_Response Response object
     */
    public function create_entity( $request ) {
        $data = array(
            'canonical' => $request->get_param( 'canonical' ),
            'type' => $request->get_param( 'type' ),
            'scope' => $request->get_param( 'scope' ) ?: 'site',
            'definition' => $request->get_param( 'definition' ),
            'preferred_capitalization' => $request->get_param( 'preferred_capitalization' ),
            'same_as' => $request->get_param( 'same_as' ),
            'owner_user_id' => get_current_user_id(),
            'review_cadence_days' => $request->get_param( 'review_cadence_days' ) ?: 365
        );
        
        $entity_id = $this->entity_manager->create_entity( $data );
        
        if ( ! $entity_id ) {
            return new \WP_Error( 'entity_creation_failed', 'Failed to create entity', array( 'status' => 500 ) );
        }
        
        // Add aliases if provided
        $aliases = $request->get_param( 'aliases' );
        if ( is_array( $aliases ) ) {
            foreach ( $aliases as $alias ) {
                if ( ! empty( $alias['alias'] ) ) {
                    $this->entity_manager->add_entity_alias(
                        $entity_id,
                        $alias['alias'],
                        $alias['is_banned'] ?? false,
                        $alias['notes'] ?? ''
                    );
                }
            }
        }
        
        // Set link rules if provided
        $link_rules = $request->get_param( 'link_rules' );
        if ( is_array( $link_rules ) && ! empty( $link_rules ) ) {
            $this->entity_manager->set_entity_link_rules( $entity_id, $link_rules );
        }
        
        $entity = $this->entity_manager->get_entity( $entity_id );
        
        return rest_ensure_response( array(
            'id' => $entity_id,
            'entity' => $this->format_entity_response( $entity, true ),
            'message' => 'Entity created successfully'
        ) );
    }
    
    /**
     * Update entity
     * 
     * @param \WP_REST_Request $request REST request object
     * @return \WP_REST_Response Response object
     */
    public function update_entity( $request ) {
        $entity_id = $request->get_param( 'id' );
        
        $data = array();
        $updatable_fields = array(
            'canonical', 'type', 'definition', 'preferred_capitalization',
            'same_as', 'status', 'replacement_entity_id', 'review_cadence_days',
            'last_reviewed_at'
        );
        
        foreach ( $updatable_fields as $field ) {
            if ( $request->has_param( $field ) ) {
                $data[ $field ] = $request->get_param( $field );
            }
        }
        
        if ( empty( $data ) ) {
            return new \WP_Error( 'no_data', 'No data provided for update', array( 'status' => 400 ) );
        }
        
        $success = $this->entity_manager->update_entity( $entity_id, $data );
        
        if ( ! $success ) {
            return new \WP_Error( 'update_failed', 'Failed to update entity', array( 'status' => 500 ) );
        }
        
        $entity = $this->entity_manager->get_entity( $entity_id );
        
        return rest_ensure_response( array(
            'entity' => $this->format_entity_response( $entity, true ),
            'message' => 'Entity updated successfully'
        ) );
    }
    
    /**
     * Delete entity
     * 
     * @param \WP_REST_Request $request REST request object
     * @return \WP_REST_Response Response object
     */
    public function delete_entity( $request ) {
        $entity_id = $request->get_param( 'id' );
        
        $entity = $this->entity_manager->get_entity( $entity_id );
        if ( ! $entity ) {
            return new \WP_Error( 'entity_not_found', 'Entity not found', array( 'status' => 404 ) );
        }
        
        global $wpdb;
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'geo_entities',
            array( 'id' => $entity_id ),
            array( '%d' )
        );
        
        if ( $result === false ) {
            return new \WP_Error( 'delete_failed', 'Failed to delete entity', array( 'status' => 500 ) );
        }
        
        return rest_ensure_response( array(
            'deleted' => true,
            'message' => 'Entity deleted successfully'
        ) );
    }
    
    // ===== ALIAS ENDPOINTS =====
    
    /**
     * Get entity aliases
     * 
     * @param \WP_REST_Request $request REST request object
     * @return \WP_REST_Response Response object
     */
    public function get_entity_aliases( $request ) {
        $entity_id = $request->get_param( 'id' );
        
        $entity = $this->entity_manager->get_entity( $entity_id );
        if ( ! $entity ) {
            return new \WP_Error( 'entity_not_found', 'Entity not found', array( 'status' => 404 ) );
        }
        
        $aliases = $this->entity_manager->get_entity_aliases( $entity_id );
        
        return rest_ensure_response( $aliases );
    }
    
    /**
     * Add entity alias
     * 
     * @param \WP_REST_Request $request REST request object
     * @return \WP_REST_Response Response object
     */
    public function add_entity_alias( $request ) {
        $entity_id = $request->get_param( 'id' );
        $alias = $request->get_param( 'alias' );
        $is_banned = $request->get_param( 'is_banned' ) ?: false;
        $notes = $request->get_param( 'notes' ) ?: '';
        
        $entity = $this->entity_manager->get_entity( $entity_id );
        if ( ! $entity ) {
            return new \WP_Error( 'entity_not_found', 'Entity not found', array( 'status' => 404 ) );
        }
        
        $success = $this->entity_manager->add_entity_alias( $entity_id, $alias, $is_banned, $notes );
        
        if ( ! $success ) {
            return new \WP_Error( 'alias_creation_failed', 'Failed to add alias', array( 'status' => 500 ) );
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Alias added successfully'
        ) );
    }
    
    // ===== VALIDATION ENDPOINT =====
    
    /**
     * Validate content for entity issues
     * 
     * @param \WP_REST_Request $request REST request object
     * @return \WP_REST_Response Response object
     */
    public function validate_content( $request ) {
        $content = $request->get_param( 'content' );
        $post_id = $request->get_param( 'post_id' );
        
        $validation_result = $this->perform_content_validation( $content, $post_id );
        
        return rest_ensure_response( $validation_result );
    }
    
    // ===== AJAX HANDLERS (Legacy Support) =====
    
    /**
     * AJAX search entities
     */
    public function ajax_search_entities() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        
        $search = sanitize_text_field( $_POST['search'] ?? '' );
        $limit = min( intval( $_POST['limit'] ?? 20 ), 50 );
        
        $entities = $this->entity_manager->search_entities( array(
            'search' => $search,
            'limit' => $limit,
            'status' => 'active'
        ) );
        
        $formatted = array();
        foreach ( $entities as $entity ) {
            $formatted[] = array(
                'id' => $entity->id,
                'canonical' => $entity->canonical,
                'type' => $entity->type,
                'scope' => $entity->scope
            );
        }
        
        wp_send_json_success( $formatted );
    }
    
    /**
     * AJAX create entity
     */
    public function ajax_create_entity() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        
        $canonical = sanitize_text_field( $_POST['canonical'] ?? '' );
        $type = sanitize_text_field( $_POST['type'] ?? 'Term' );
        
        if ( empty( $canonical ) ) {
            wp_send_json_error( 'Canonical name is required' );
        }
        
        $entity_id = $this->entity_manager->create_entity( array(
            'canonical' => $canonical,
            'type' => $type,
            'owner_user_id' => get_current_user_id()
        ) );
        
        if ( ! $entity_id ) {
            wp_send_json_error( 'Failed to create entity' );
        }
        
        $entity = $this->entity_manager->get_entity( $entity_id );
        
        wp_send_json_success( array(
            'id' => $entity_id,
            'canonical' => $entity->canonical,
            'type' => $entity->type
        ) );
    }
    
    /**
     * AJAX validate content
     */
    public function ajax_validate_content() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        
        $content = wp_kses_post( $_POST['content'] ?? '' );
        $post_id = intval( $_POST['post_id'] ?? 0 );
        
        $result = $this->perform_content_validation( $content, $post_id );
        
        wp_send_json_success( $result );
    }
    
    // ===== HELPER METHODS =====
    
    /**
     * Format entity for REST response
     * 
     * @param object $entity Entity object
     * @param bool $detailed Include detailed information
     * @return array Formatted entity
     */
    private function format_entity_response( $entity, $detailed = false ) {
        $formatted = array(
            'id' => intval( $entity->id ),
            'canonical' => $entity->canonical,
            'slug' => $entity->slug,
            'type' => $entity->type,
            'scope' => $entity->scope,
            'status' => $entity->status,
            'preferred_capitalization' => $entity->preferred_capitalization,
            'created_at' => $entity->created_at,
            'updated_at' => $entity->updated_at
        );
        
        if ( $detailed ) {
            $formatted['definition'] = $entity->definition;
            $formatted['same_as'] = ! empty( $entity->same_as ) ? json_decode( $entity->same_as, true ) : array();
            $formatted['replacement_entity_id'] = $entity->replacement_entity_id;
            $formatted['owner_user_id'] = $entity->owner_user_id;
            $formatted['review_cadence_days'] = intval( $entity->review_cadence_days );
            $formatted['last_reviewed_at'] = $entity->last_reviewed_at;
            
            // Include aliases
            $formatted['aliases'] = $this->entity_manager->get_entity_aliases( $entity->id );
            
            // Include link rules
            $formatted['link_rules'] = $this->entity_manager->get_entity_link_rules( $entity->id );
            
            // Include usage stats
            if ( isset( $entity->alias_count ) ) {
                $formatted['alias_count'] = intval( $entity->alias_count );
            }
            if ( isset( $entity->usage_count ) ) {
                $formatted['usage_count'] = intval( $entity->usage_count );
            }
        }
        
        return $formatted;
    }
    
    /**
     * Perform content validation
     * 
     * @param string $content Content to validate
     * @param int $post_id Post ID (optional)
     * @return array Validation results
     */
    private function perform_content_validation( $content, $post_id = null ) {
        $issues = array();
        $suggestions = array();
        
        // Get all entities for checking
        $entities = $this->entity_manager->search_entities( array( 'limit' => 1000 ) );
        
        foreach ( $entities as $entity ) {
            // Check for aliases used instead of canonical
            $aliases = $this->entity_manager->get_entity_aliases( $entity->id );
            foreach ( $aliases as $alias ) {
                if ( ! $alias->is_banned && stripos( $content, $alias->alias ) !== false ) {
                    if ( stripos( $content, $entity->canonical ) === false ) {
                        $issues[] = array(
                            'type' => 'alias_used',
                            'message' => "Alias '{$alias->alias}' used. Canonical: '{$entity->canonical}'",
                            'entity_id' => $entity->id,
                            'alias' => $alias->alias,
                            'canonical' => $entity->canonical,
                            'severity' => 'warning'
                        );
                        
                        $suggestions[] = array(
                            'type' => 'replace_alias',
                            'from' => $alias->alias,
                            'to' => $entity->canonical,
                            'entity_id' => $entity->id
                        );
                    }
                }
                
                // Check for banned terms
                if ( $alias->is_banned && stripos( $content, $alias->alias ) !== false ) {
                    $issues[] = array(
                        'type' => 'banned_term',
                        'message' => "Banned term '{$alias->alias}' found",
                        'entity_id' => $entity->id,
                        'term' => $alias->alias,
                        'severity' => 'error'
                    );
                }
            }
        }
        
        // Check for missing primary entity
        $has_primary = false;
        if ( $post_id ) {
            $post_entities = $this->entity_manager->get_post_entities( $post_id );
            foreach ( $post_entities as $post_entity ) {
                if ( $post_entity->role === 'primary' ) {
                    $has_primary = true;
                    break;
                }
            }
        }
        
        if ( ! $has_primary ) {
            $issues[] = array(
                'type' => 'missing_primary_entity',
                'message' => 'No primary entity assigned to this content',
                'severity' => 'warning'
            );
        }
        
        return array(
            'issues' => $issues,
            'suggestions' => $suggestions,
            'score' => $this->calculate_governance_score( $issues )
        );
    }
    
    /**
     * Calculate governance score based on issues
     * 
     * @param array $issues Validation issues
     * @return int Score (0-100)
     */
    private function calculate_governance_score( $issues ) {
        $base_score = 100;
        $deductions = 0;
        
        foreach ( $issues as $issue ) {
            switch ( $issue['severity'] ) {
                case 'error':
                    $deductions += 20;
                    break;
                case 'warning':
                    $deductions += 10;
                    break;
                default:
                    $deductions += 5;
            }
        }
        
        return max( 0, $base_score - $deductions );
    }
    
    /**
     * Get total entities count for pagination
     * 
     * @param array $params Search parameters
     * @return int Total count
     */
    private function get_total_entities_count( $params ) {
        global $wpdb;
        
        $where_clauses = array( '1=1' );
        $prepare_values = array();
        
        if ( ! empty( $params['search'] ) ) {
            $where_clauses[] = "canonical LIKE %s";
            $prepare_values[] = '%' . $wpdb->esc_like( $params['search'] ) . '%';
        }
        
        if ( ! empty( $params['type'] ) ) {
            $where_clauses[] = "type = %s";
            $prepare_values[] = $params['type'];
        }
        
        if ( ! empty( $params['status'] ) ) {
            $where_clauses[] = "status = %s";
            $prepare_values[] = $params['status'];
        }
        
        $where_sql = implode( ' AND ', $where_clauses );
        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}geo_entities WHERE $where_sql";
        
        if ( ! empty( $prepare_values ) ) {
            return intval( $wpdb->get_var( $wpdb->prepare( $sql, $prepare_values ) ) );
        }
        
        return intval( $wpdb->get_var( $sql ) );
    }
    
    // ===== SCHEMA AND VALIDATION =====
    
    /**
     * Get entity schema for validation
     * 
     * @return array Schema definition
     */
    private function get_entity_schema() {
        return array(
            'canonical' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'type' => array(
                'required' => true,
                'enum' => array( 'Organization', 'Product', 'Technology', 'Metric', 'Acronym', 'Term', 'Person', 'Place', 'Thing' )
            ),
            'scope' => array(
                'enum' => array( 'global', 'client', 'site' ),
                'default' => 'site'
            ),
            'definition' => array(
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post'
            ),
            'preferred_capitalization' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'same_as' => array(
                'type' => 'array',
                'items' => array( 'type' => 'string' )
            ),
            'status' => array(
                'enum' => array( 'active', 'deprecated' ),
                'default' => 'active'
            )
        );
    }
    
    /**
     * Get search parameters schema
     * 
     * @return array Search parameters
     */
    private function get_search_params() {
        return array(
            'search' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'type' => array(
                'enum' => array( 'Organization', 'Product', 'Technology', 'Metric', 'Acronym', 'Term', 'Person', 'Place', 'Thing' )
            ),
            'scope' => array(
                'enum' => array( 'global', 'client', 'site' )
            ),
            'status' => array(
                'enum' => array( 'active', 'deprecated' ),
                'default' => 'active'
            ),
            'page' => array(
                'type' => 'integer',
                'minimum' => 1,
                'default' => 1
            ),
            'per_page' => array(
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'default' => 50
            ),
            'orderby' => array(
                'enum' => array( 'canonical', 'type', 'created_at', 'updated_at' ),
                'default' => 'canonical'
            ),
            'order' => array(
                'enum' => array( 'asc', 'desc' ),
                'default' => 'asc'
            )
        );
    }
    
    // ===== PERMISSION CALLBACKS =====
    
    /**
     * Check read permissions
     * 
     * @return bool Has permission
     */
    public function check_read_permission() {
        return current_user_can( 'edit_posts' );
    }
    
    /**
     * Check write permissions
     * 
     * @return bool Has permission
     */
    public function check_write_permission() {
        return current_user_can( 'edit_posts' );
    }
    
    /**
     * Check delete permissions
     * 
     * @return bool Has permission
     */
    public function check_delete_permission() {
        return current_user_can( 'delete_posts' );
    }
    
    /**
     * Check import permissions
     * 
     * @return bool Has permission
     */
    public function check_import_permission() {
        return current_user_can( 'manage_options' );
    }
}