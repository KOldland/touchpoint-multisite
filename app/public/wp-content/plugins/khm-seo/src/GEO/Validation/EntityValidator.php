<?php
/**
 * Entity Validation System
 *
 * Provides real-time validation for entity management, including canonical terms,
 * alias enforcement, banned terms detection, and pre-publish checks.
 *
 * @package KHM_SEO\GEO\Validation
 * @since 2.0.0
 */

namespace KHM_SEO\GEO\Validation;

use KHM_SEO\GEO\Entity\EntityManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Entity Validation Class
 * Handles all entity-related validation logic
 */
class EntityValidator {

    /**
     * @var EntityManager Entity manager instance
     */
    private $entity_manager;

    /**
     * @var array Banned terms list
     */
    private $banned_terms = array();

    /**
     * Constructor
     */
    public function __construct( EntityManager $entity_manager ) {
        $this->entity_manager = $entity_manager;
        $this->load_banned_terms();
    }

    /**
     * Load banned terms from options or config
     */
    private function load_banned_terms() {
        $this->banned_terms = get_option( 'khm_seo_geo_banned_terms', array() );
    }

    /**
     * Validate entity data before creation/update
     *
     * @param array $data Entity data
     * @param int $entity_id Entity ID (0 for new)
     * @return array|WP_Error Validation result
     */
    public function validate_entity_data( $data, $entity_id = 0 ) {
        $errors = array();

        // Validate canonical name
        if ( empty( $data['canonical'] ) ) {
            $errors[] = __( 'Canonical name is required.', 'khm-seo' );
        } elseif ( strlen( $data['canonical'] ) > 100 ) {
            $errors[] = __( 'Canonical name must be less than 100 characters.', 'khm-seo' );
        } elseif ( $this->is_banned_term( $data['canonical'] ) ) {
            $errors[] = __( 'Canonical name contains banned terms.', 'khm-seo' );
        } elseif ( $this->canonical_exists( $data['canonical'], $data['scope'] ?? 'site', $entity_id ) ) {
            $errors[] = __( 'Canonical name already exists in this scope.', 'khm-seo' );
        }

        // Validate type
        if ( empty( $data['type'] ) || ! in_array( $data['type'], $this->entity_manager->get_valid_types() ) ) {
            $errors[] = __( 'Invalid entity type.', 'khm-seo' );
        }

        // Validate scope
        if ( ! empty( $data['scope'] ) && ! in_array( $data['scope'], $this->entity_manager->get_valid_scopes() ) ) {
            $errors[] = __( 'Invalid entity scope.', 'khm-seo' );
        }

        // Validate status
        if ( ! empty( $data['status'] ) && ! in_array( $data['status'], $this->entity_manager->get_valid_statuses() ) ) {
            $errors[] = __( 'Invalid entity status.', 'khm-seo' );
        }

        // Validate aliases
        if ( ! empty( $data['aliases'] ) && is_array( $data['aliases'] ) ) {
            foreach ( $data['aliases'] as $alias ) {
                if ( ! empty( $alias ) ) {
                    if ( strlen( $alias ) > 100 ) {
                        $errors[] = sprintf( __( 'Alias "%s" is too long.', 'khm-seo' ), $alias );
                    } elseif ( $this->is_banned_term( $alias ) ) {
                        $errors[] = sprintf( __( 'Alias "%s" contains banned terms.', 'khm-seo' ), $alias );
                    } elseif ( $this->alias_conflicts( $alias, $entity_id ) ) {
                        $errors[] = sprintf( __( 'Alias "%s" conflicts with another entity.', 'khm-seo' ), $alias );
                    }
                }
            }
        }

        if ( ! empty( $errors ) ) {
            return new \WP_Error( 'validation_failed', implode( ' ', $errors ), $errors );
        }

        return $data;
    }

    /**
     * Validate content for entity usage
     *
     * @param string $content Post content
     * @param int $post_id Post ID
     * @return array Validation results
     */
    public function validate_content_entities( $content, $post_id = 0 ) {
        $results = array(
            'warnings' => array(),
            'errors' => array(),
            'suggestions' => array()
        );

        // Check for banned terms
        foreach ( $this->banned_terms as $banned ) {
            if ( stripos( $content, $banned ) !== false ) {
                $results['errors'][] = sprintf( __( 'Content contains banned term: %s', 'khm-seo' ), $banned );
            }
        }

        // Check for deprecated entities
        $deprecated_entities = $this->entity_manager->search_entities( array( 'status' => 'deprecated' ) );
        foreach ( $deprecated_entities as $entity ) {
            if ( stripos( $content, $entity->canonical ) !== false ) {
                $results['warnings'][] = sprintf( __( 'Content uses deprecated entity: %s', 'khm-seo' ), $entity->canonical );
            }
        }

        // Suggest entity usage
        $active_entities = $this->entity_manager->search_entities( array( 'status' => 'active', 'limit' => 50 ) );
        foreach ( $active_entities as $entity ) {
            if ( stripos( $content, $entity->canonical ) === false ) {
                // Check aliases too
                $aliases = $this->entity_manager->get_entity_aliases( $entity->id );
                $found = false;
                foreach ( $aliases as $alias ) {
                    if ( stripos( $content, $alias ) !== false ) {
                        $found = true;
                        break;
                    }
                }
                if ( ! $found ) {
                    $results['suggestions'][] = sprintf( __( 'Consider using entity: %s', 'khm-seo' ), $entity->canonical );
                }
            }
        }

        return $results;
    }

    /**
     * Check if term is banned
     *
     * @param string $term Term to check
     * @return bool
     */
    private function is_banned_term( $term ) {
        foreach ( $this->banned_terms as $banned ) {
            if ( stripos( $term, $banned ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if canonical exists
     *
     * @param string $canonical Canonical name
     * @param string $scope Scope
     * @param int $exclude_id Exclude entity ID
     * @return bool
     */
    private function canonical_exists( $canonical, $scope, $exclude_id = 0 ) {
        return $this->entity_manager->find_entity_by_canonical( $canonical, $scope ) !== null;
    }

    /**
     * Check if alias conflicts
     *
     * @param string $alias Alias
     * @param int $exclude_entity_id Exclude entity ID
     * @return bool
     */
    private function alias_conflicts( $alias, $exclude_entity_id = 0 ) {
        global $wpdb;
        $table = $this->entity_manager->get_table_name( 'entity_aliases' );
        $query = $wpdb->prepare( "SELECT entity_id FROM {$table} WHERE alias = %s", $alias );
        if ( $exclude_entity_id ) {
            $query .= $wpdb->prepare( " AND entity_id != %d", $exclude_entity_id );
        }
        return $wpdb->get_var( $query ) !== null;
    }

    /**
     * Pre-publish validation hook
     *
     * @param array $data Post data
     * @param array $postarr Post array
     * @return array
     */
    public function pre_publish_validation( $data, $postarr ) {
        if ( $data['post_status'] !== 'publish' ) {
            return $data;
        }

        $content = $data['post_content'] . ' ' . $data['post_title'];
        $validation = $this->validate_content_entities( $content, $postarr['ID'] );

        if ( ! empty( $validation['errors'] ) ) {
            // Block publishing if errors
            $data['post_status'] = 'draft';
            add_filter( 'redirect_post_location', function( $location ) {
                return add_query_arg( 'message', 10, $location ); // Custom message
            } );
        }

        return $data;
    }
}
