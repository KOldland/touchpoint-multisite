<?php
/**
 * Schema De-duplication Manager
 *
 * Prevents duplicate structured data markup and resolves conflicts
 * Ensures clean, optimized schema output for better SEO
 *
 * @package KHM_SEO\GEO\Schema
 * @since 2.0.0
 */

namespace KHM_SEO\GEO\Schema;

use KHM_SEO\GEO\Entity\EntityManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * SchemaDedupManager Class
 */
class SchemaDedupManager {

    /**
     * @var EntityManager Entity manager instance
     */
    private $entity_manager;

    /**
     * @var array Schema deduplication configuration
     */
    private $config = array();

    /**
     * @var array Collected schemas for current page
     */
    private $collected_schemas = array();

    /**
     * @var array Deduplication rules
     */
    private $dedup_rules = array();

    /**
     * Constructor - Initialize schema deduplication
     *
     * @param EntityManager $entity_manager
     */
    public function __construct( EntityManager $entity_manager ) {
        $this->entity_manager = $entity_manager;
        $this->init_hooks();
        $this->load_config();
        $this->init_dedup_rules();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Schema collection and processing
        add_filter( 'khm_seo_schema_data', array( $this, 'process_schema_deduplication' ), 100 );
        add_action( 'wp_head', array( $this, 'inject_deduplicated_schema' ), 5 );

        // Admin integration
        add_action( 'admin_notices', array( $this, 'display_schema_conflicts_notice' ) );
        add_action( 'wp_ajax_khm_geo_resolve_schema_conflict', array( $this, 'ajax_resolve_schema_conflict' ) );

        // Plugin integrations
        add_action( 'khm_schema_collected', array( $this, 'collect_schema_data' ), 10, 2 );

        // Elementor integration
        add_filter( 'elementor/frontend/the_content', array( $this, 'filter_elementor_content_schema' ), 100 );

        // Yoast/Other SEO plugin compatibility
        add_filter( 'wpseo_json_ld_output', array( $this, 'deduplicate_yoast_schema' ), 100 );
        add_filter( 'rank_math/json_ld', array( $this, 'deduplicate_rankmath_schema' ), 100 );
    }

    /**
     * Load deduplication configuration
     */
    private function load_config() {
        $this->config = array(
            'enabled' => true,
            'strict_mode' => false, // Remove all duplicates vs merge intelligently
            'prioritize_complete' => true, // Prefer more complete schemas
            'prioritize_entity_linked' => true, // Prefer schemas linked to entities
            'max_schemas_per_type' => 1, // Maximum schemas of same type per page
            'conflict_resolution' => 'merge', // merge, keep_first, keep_last, manual
            'admin_notifications' => true,
            'log_conflicts' => true
        );

        // Allow override from options
        $saved_config = get_option( 'khm_geo_schema_dedup_config', array() );
        $this->config = array_merge( $this->config, $saved_config );
    }

    /**
     * Initialize deduplication rules
     */
    private function init_dedup_rules() {
        $this->dedup_rules = array(
            // Organization schemas - keep only one
            'Organization' => array(
                'max_instances' => 1,
                'merge_strategy' => 'merge_properties',
                'priority_fields' => array( 'name', 'url', 'logo', 'sameAs' )
            ),

            // Person schemas - keep only one per person
            'Person' => array(
                'max_instances' => 1,
                'merge_strategy' => 'merge_by_sameAs',
                'priority_fields' => array( 'name', 'jobTitle', 'worksFor', 'sameAs' )
            ),

            // Article schemas - keep only one main article
            'Article' => array(
                'max_instances' => 1,
                'merge_strategy' => 'keep_most_complete',
                'priority_fields' => array( 'headline', 'description', 'author', 'publisher', 'datePublished' )
            ),

            // Product schemas - merge by same product
            'Product' => array(
                'max_instances' => 3,
                'merge_strategy' => 'merge_by_offers',
                'priority_fields' => array( 'name', 'sku', 'brand', 'offers', 'aggregateRating' )
            ),

            // Breadcrumb schemas - keep only one
            'BreadcrumbList' => array(
                'max_instances' => 1,
                'merge_strategy' => 'merge_items',
                'priority_fields' => array( 'itemListElement' )
            ),

            // FAQ schemas - merge all FAQ pages
            'FAQPage' => array(
                'max_instances' => 1,
                'merge_strategy' => 'merge_questions',
                'priority_fields' => array( 'mainEntity' )
            ),

            // HowTo schemas - keep most complete
            'HowTo' => array(
                'max_instances' => 1,
                'merge_strategy' => 'keep_most_complete',
                'priority_fields' => array( 'name', 'description', 'step' )
            ),

            // Event schemas - allow multiple but dedupe by same event
            'Event' => array(
                'max_instances' => 5,
                'merge_strategy' => 'merge_by_sameAs',
                'priority_fields' => array( 'name', 'startDate', 'location', 'sameAs' )
            )
        );
    }

    /**
     * Collect schema data from various sources
     *
     * @param array $schema Schema data
     * @param string $source Source identifier
     */
    public function collect_schema_data( $schema, $source = 'unknown' ) {
        if ( ! $this->config['enabled'] ) {
            return;
        }

        $schema_key = $this->generate_schema_key( $schema );
        $schema_type = $this->get_schema_type( $schema );

        if ( ! isset( $this->collected_schemas[ $schema_type ] ) ) {
            $this->collected_schemas[ $schema_type ] = array();
        }

        $this->collected_schemas[ $schema_type ][ $schema_key ] = array(
            'data' => $schema,
            'source' => $source,
            'collected_at' => current_time( 'timestamp' ),
            'priority_score' => $this->calculate_schema_priority( $schema, $schema_type )
        );
    }

    /**
     * Process schema deduplication
     *
     * @param array $schemas Existing schemas
     * @return array Deduplicated schemas
     */
    public function process_schema_deduplication( $schemas ) {
        if ( ! $this->config['enabled'] || empty( $this->collected_schemas ) ) {
            return $schemas;
        }

        $deduplicated = array();
        $conflicts = array();

        // Process each schema type
        foreach ( $this->collected_schemas as $schema_type => $type_schemas ) {
            $processed = $this->deduplicate_schema_type( $schema_type, $type_schemas );

            if ( $processed['conflicts'] ) {
                $conflicts[ $schema_type ] = $processed['conflicts'];
            }

            if ( ! empty( $processed['schemas'] ) ) {
                $deduplicated = array_merge( $deduplicated, $processed['schemas'] );
            }
        }

        // Store conflicts for admin notification
        if ( ! empty( $conflicts ) ) {
            $this->store_schema_conflicts( $conflicts );
        }

        return array_merge( $schemas, $deduplicated );
    }

    /**
     * Deduplicate schemas of a specific type
     *
     * @param string $schema_type Schema type
     * @param array $type_schemas Schemas of this type
     * @return array Processed schemas and conflicts
     */
    private function deduplicate_schema_type( $schema_type, $type_schemas ) {
        $rules = $this->dedup_rules[ $schema_type ] ?? $this->get_default_rules();
        $max_instances = $rules['max_instances'];

        // Sort by priority score (highest first)
        uasort( $type_schemas, function( $a, $b ) {
            return $b['priority_score'] <=> $a['priority_score'];
        });

        $result = array(
            'schemas' => array(),
            'conflicts' => array()
        );

        if ( count( $type_schemas ) <= $max_instances ) {
            // No deduplication needed
            foreach ( $type_schemas as $schema_data ) {
                $result['schemas'][] = $schema_data['data'];
            }
            return $result;
        }

        // Apply deduplication strategy
        switch ( $rules['merge_strategy'] ) {
            case 'merge_properties':
                $result['schemas'][] = $this->merge_schema_properties( $type_schemas );
                break;

            case 'merge_by_sameAs':
                $merged = $this->merge_schemas_by_sameAs( $type_schemas );
                $result['schemas'] = array_merge( $result['schemas'], $merged['schemas'] );
                $result['conflicts'] = $merged['conflicts'];
                break;

            case 'merge_by_offers':
                $result['schemas'][] = $this->merge_product_offers( $type_schemas );
                break;

            case 'merge_items':
                $result['schemas'][] = $this->merge_breadcrumb_items( $type_schemas );
                break;

            case 'merge_questions':
                $result['schemas'][] = $this->merge_faq_questions( $type_schemas );
                break;

            case 'keep_most_complete':
            default:
                $kept = array_slice( $type_schemas, 0, $max_instances );
                $discarded = array_slice( $type_schemas, $max_instances );

                foreach ( $kept as $schema_data ) {
                    $result['schemas'][] = $schema_data['data'];
                }

                if ( ! empty( $discarded ) ) {
                    $result['conflicts'] = array(
                        'type' => 'excess_schemas',
                        'kept' => count( $kept ),
                        'discarded' => count( $discarded ),
                        'discarded_sources' => array_column( $discarded, 'source' )
                    );
                }
                break;
        }

        return $result;
    }

    /**
     * Merge schema properties intelligently
     *
     * @param array $schemas Schemas to merge
     * @return array Merged schema
     */
    private function merge_schema_properties( $schemas ) {
        $merged = array();
        $priority_fields = array();

        // Get priority fields for this schema type
        $first_schema = reset( $schemas );
        $schema_type = $this->get_schema_type( $first_schema['data'] );
        if ( isset( $this->dedup_rules[ $schema_type ] ) ) {
            $priority_fields = $this->dedup_rules[ $schema_type ]['priority_fields'];
        }

        // Start with the highest priority schema
        $merged = $first_schema['data'];

        // Merge properties from other schemas
        foreach ( $schemas as $schema_data ) {
            $schema = $schema_data['data'];

            foreach ( $schema as $key => $value ) {
                if ( ! isset( $merged[ $key ] ) || in_array( $key, $priority_fields ) ) {
                    $merged[ $key ] = $value;
                } elseif ( is_array( $value ) && is_array( $merged[ $key ] ) ) {
                    // Merge arrays intelligently
                    $merged[ $key ] = $this->merge_arrays_intelligently( $merged[ $key ], $value );
                }
            }
        }

        return $merged;
    }

    /**
     * Merge schemas by sameAs property
     *
     * @param array $schemas Schemas to merge
     * @return array Merged result
     */
    private function merge_schemas_by_sameAs( $schemas ) {
        $grouped = array();
        $conflicts = array();

        foreach ( $schemas as $schema_data ) {
            $schema = $schema_data['data'];
            $same_as = $schema['sameAs'] ?? array();

            if ( is_string( $same_as ) ) {
                $same_as = array( $same_as );
            }

            $key = ! empty( $same_as ) ? md5( json_encode( $same_as ) ) : 'no_sameas_' . uniqid();

            if ( ! isset( $grouped[ $key ] ) ) {
                $grouped[ $key ] = array();
            }

            $grouped[ $key ][] = $schema_data;
        }

        $result = array( 'schemas' => array(), 'conflicts' => array() );

        foreach ( $grouped as $group ) {
            if ( count( $group ) === 1 ) {
                $result['schemas'][] = $group[0]['data'];
            } else {
                // Multiple schemas for same entity - merge them
                $merged = $this->merge_schema_properties( $group );
                $result['schemas'][] = $merged;

                $result['conflicts'][] = array(
                    'type' => 'duplicate_entity',
                    'entity_urls' => $merged['sameAs'] ?? array(),
                    'sources' => array_column( $group, 'source' ),
                    'merged' => true
                );
            }
        }

        return $result;
    }

    /**
     * Merge product offers
     *
     * @param array $schemas Product schemas
     * @return array Merged product schema
     */
    private function merge_product_offers( $schemas ) {
        $merged = $this->merge_schema_properties( $schemas );

        // Special handling for offers array
        $all_offers = array();
        foreach ( $schemas as $schema_data ) {
            $offers = $schema_data['data']['offers'] ?? array();
            if ( is_array( $offers ) ) {
                $all_offers = array_merge( $all_offers, $offers );
            } elseif ( $offers ) {
                $all_offers[] = $offers;
            }
        }

        // Remove duplicate offers by URL or SKU
        $unique_offers = array();
        $seen_identifiers = array();

        foreach ( $all_offers as $offer ) {
            $identifier = $offer['url'] ?? $offer['sku'] ?? md5( json_encode( $offer ) );

            if ( ! in_array( $identifier, $seen_identifiers ) ) {
                $unique_offers[] = $offer;
                $seen_identifiers[] = $identifier;
            }
        }

        if ( ! empty( $unique_offers ) ) {
            $merged['offers'] = count( $unique_offers ) === 1 ? $unique_offers[0] : $unique_offers;
        }

        return $merged;
    }

    /**
     * Merge breadcrumb items
     *
     * @param array $schemas Breadcrumb schemas
     * @return array Merged breadcrumb schema
     */
    private function merge_breadcrumb_items( $schemas ) {
        $merged = $this->merge_schema_properties( $schemas );

        // Collect all breadcrumb items
        $all_items = array();
        foreach ( $schemas as $schema_data ) {
            $items = $schema_data['data']['itemListElement'] ?? array();
            if ( is_array( $items ) ) {
                $all_items = array_merge( $all_items, $items );
            }
        }

        // Sort by position and remove duplicates
        usort( $all_items, function( $a, $b ) {
            return ( $a['position'] ?? 0 ) <=> ( $b['position'] ?? 0 );
        });

        $unique_items = array();
        $seen_urls = array();

        foreach ( $all_items as $item ) {
            $url = $item['item']['@id'] ?? $item['item'] ?? '';
            if ( ! in_array( $url, $seen_urls ) ) {
                $unique_items[] = $item;
                $seen_urls[] = $url;
            }
        }

        $merged['itemListElement'] = $unique_items;
        $merged['numberOfItems'] = count( $unique_items );

        return $merged;
    }

    /**
     * Merge FAQ questions
     *
     * @param array $schemas FAQ schemas
     * @return array Merged FAQ schema
     */
    private function merge_faq_questions( $schemas ) {
        $merged = $this->merge_schema_properties( $schemas );

        // Collect all questions
        $all_questions = array();
        foreach ( $schemas as $schema_data ) {
            $questions = $schema_data['data']['mainEntity'] ?? array();
            if ( is_array( $questions ) ) {
                $all_questions = array_merge( $all_questions, $questions );
            }
        }

        // Remove duplicate questions by text
        $unique_questions = array();
        $seen_questions = array();

        foreach ( $all_questions as $question ) {
            $question_text = $question['name'] ?? '';
            if ( ! in_array( $question_text, $seen_questions ) ) {
                $unique_questions[] = $question;
                $seen_questions[] = $question_text;
            }
        }

        $merged['mainEntity'] = $unique_questions;

        return $merged;
    }

    /**
     * Merge arrays intelligently
     *
     * @param array $array1 First array
     * @param array $array2 Second array
     * @return array Merged array
     */
    private function merge_arrays_intelligently( $array1, $array2 ) {
        if ( $this->is_associative_array( $array1 ) && $this->is_associative_array( $array2 ) ) {
            // Merge associative arrays
            return array_merge( $array1, $array2 );
        } elseif ( $this->is_associative_array( $array1 ) || $this->is_associative_array( $array2 ) ) {
            // Mixed types - return the more complete one
            return count( $array1 ) >= count( $array2 ) ? $array1 : $array2;
        } else {
            // Both indexed arrays - merge and dedupe
            return array_unique( array_merge( $array1, $array2 ) );
        }
    }

    /**
     * Check if array is associative
     *
     * @param array $array Array to check
     * @return bool True if associative
     */
    private function is_associative_array( $array ) {
        if ( ! is_array( $array ) ) {
            return false;
        }

        return array_keys( $array ) !== range( 0, count( $array ) - 1 );
    }

    /**
     * Generate unique key for schema
     *
     * @param array $schema Schema data
     * @return string Unique key
     */
    private function generate_schema_key( $schema ) {
        $key_parts = array();

        // Use identifying properties to create key
        $identifiers = array( '@id', 'url', 'sameAs', 'name', 'headline' );

        foreach ( $identifiers as $identifier ) {
            if ( isset( $schema[ $identifier ] ) ) {
                $value = $schema[ $identifier ];
                if ( is_array( $value ) ) {
                    $value = json_encode( $value );
                }
                $key_parts[] = $identifier . ':' . $value;
            }
        }

        return md5( implode( '|', $key_parts ) );
    }

    /**
     * Get schema type from schema data
     *
     * @param array $schema Schema data
     * @return string Schema type
     */
    private function get_schema_type( $schema ) {
        return $schema['@type'] ?? 'Unknown';
    }

    /**
     * Calculate schema priority score
     *
     * @param array $schema Schema data
     * @param string $schema_type Schema type
     * @return int Priority score
     */
    private function calculate_schema_priority( $schema, $schema_type ) {
        $score = 0;

        // Base score from completeness
        $field_count = count( $schema );
        $score += min( 50, $field_count * 2 ); // Max 50 points for completeness

        // Bonus for entity-linked schemas
        if ( $this->config['prioritize_entity_linked'] && $this->is_entity_linked( $schema ) ) {
            $score += 20;
        }

        // Bonus for schemas with sameAs (authority signals)
        if ( isset( $schema['sameAs'] ) && ! empty( $schema['sameAs'] ) ) {
            $score += 15;
        }

        // Type-specific bonuses
        switch ( $schema_type ) {
            case 'Article':
                if ( isset( $schema['author'] ) && isset( $schema['publisher'] ) ) {
                    $score += 10;
                }
                break;

            case 'Product':
                if ( isset( $schema['offers'] ) && isset( $schema['aggregateRating'] ) ) {
                    $score += 10;
                }
                break;

            case 'Organization':
                if ( isset( $schema['logo'] ) && isset( $schema['contactPoint'] ) ) {
                    $score += 10;
                }
                break;
        }

        return $score;
    }

    /**
     * Check if schema is linked to a GEO entity
     *
     * @param array $schema Schema data
     * @return bool True if entity-linked
     */
    private function is_entity_linked( $schema ) {
        // Check if schema has entity metadata
        if ( isset( $schema['_geo_entity_id'] ) ) {
            return true;
        }

        // Check sameAs URLs against entity sameAs
        $same_as = $schema['sameAs'] ?? array();
        if ( is_string( $same_as ) ) {
            $same_as = array( $same_as );
        }

        if ( ! empty( $same_as ) ) {
            foreach ( $same_as as $url ) {
                $entity = $this->entity_manager->find_entity_by_canonical( $url );
                if ( $entity ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get default deduplication rules
     *
     * @return array Default rules
     */
    private function get_default_rules() {
        return array(
            'max_instances' => 1,
            'merge_strategy' => 'keep_most_complete',
            'priority_fields' => array()
        );
    }

    /**
     * Inject deduplicated schema into page head
     */
    public function inject_deduplicated_schema() {
        if ( ! $this->config['enabled'] || empty( $this->collected_schemas ) ) {
            return;
        }

        $deduplicated_schemas = $this->process_schema_deduplication( array() );

        if ( ! empty( $deduplicated_schemas ) ) {
            echo "\n<!-- GEO Schema De-duplication -->\n";
            foreach ( $deduplicated_schemas as $schema ) {
                echo '<script type="application/ld+json">' . json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
            }
            echo "<!-- End GEO Schema De-duplication -->\n";
        }
    }

    /**
     * Filter Elementor content for schema deduplication
     *
     * @param string $content Content
     * @return string Filtered content
     */
    public function filter_elementor_content_schema( $content ) {
        // Elementor-specific schema processing
        // This would integrate with Elementor's schema generation
        return $content;
    }

    /**
     * Deduplicate Yoast SEO schema
     *
     * @param array $schema Yoast schema data
     * @return array Deduplicated schema
     */
    public function deduplicate_yoast_schema( $schema ) {
        if ( ! $this->config['enabled'] ) {
            return $schema;
        }

        // Collect Yoast schema for deduplication
        $this->collect_schema_data( $schema, 'yoast' );

        // Return empty array - let our system handle output
        return array();
    }

    /**
     * Deduplicate RankMath schema
     *
     * @param array $schema RankMath schema data
     * @return array Deduplicated schema
     */
    public function deduplicate_rankmath_schema( $schema ) {
        if ( ! $this->config['enabled'] ) {
            return $schema;
        }

        // Collect RankMath schema for deduplication
        $this->collect_schema_data( $schema, 'rankmath' );

        // Return empty array - let our system handle output
        return array();
    }

    /**
     * Store schema conflicts for admin notification
     *
     * @param array $conflicts Conflicts data
     */
    private function store_schema_conflicts( $conflicts ) {
        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return;
        }

        update_post_meta( $post_id, '_khm_geo_schema_conflicts', $conflicts );

        if ( $this->config['log_conflicts'] ) {
            error_log( 'GEO Schema Conflicts detected on post ' . $post_id . ': ' . json_encode( $conflicts ) );
        }
    }

    /**
     * Display schema conflicts admin notice
     */
    public function display_schema_conflicts_notice() {
        if ( ! $this->config['admin_notifications'] ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || $screen->base !== 'post' ) {
            return;
        }

        $post_id = get_the_ID();
        $conflicts = get_post_meta( $post_id, '_khm_geo_schema_conflicts', true );

        if ( empty( $conflicts ) ) {
            return;
        }

        $conflict_count = count( $conflicts );
        $conflict_types = array_keys( $conflicts );

        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>GEO Schema Conflicts Detected</strong>
                <?php printf(
                    _n(
                        'Found %d schema conflict that was automatically resolved.',
                        'Found %d schema conflicts that were automatically resolved.',
                        $conflict_count,
                        'khm-seo'
                    ),
                    $conflict_count
                ); ?>
            </p>
            <p>
                <strong>Conflict Types:</strong> <?php echo esc_html( implode( ', ', $conflict_types ) ); ?>
                <a href="#" class="khm-schema-conflicts-details" data-conflicts="<?php echo esc_attr( json_encode( $conflicts ) ); ?>">
                    View Details
                </a>
            </p>
        </div>

        <div id="khm-schema-conflicts-modal" class="khm-modal" style="display: none;">
            <div class="khm-modal-content">
                <span class="khm-modal-close">&times;</span>
                <h3>Schema Conflicts Details</h3>
                <div id="khm-conflicts-details"></div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for resolving schema conflicts
     */
    public function ajax_resolve_schema_conflict() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        $conflict_type = sanitize_text_field( $_POST['conflict_type'] ?? '' );
        $resolution = sanitize_text_field( $_POST['resolution'] ?? '' );

        if ( ! $post_id || ! $conflict_type ) {
            wp_send_json_error( 'Missing required parameters' );
        }

        // Store resolution preference
        $resolutions = get_post_meta( $post_id, '_khm_geo_schema_resolutions', true ) ?: array();
        $resolutions[ $conflict_type ] = $resolution;
        update_post_meta( $post_id, '_khm_geo_schema_resolutions', $resolutions );

        wp_send_json_success( array( 'resolved' => true ) );
    }

    /**
     * Get schema deduplication configuration
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

    /**
     * Get collected schemas (for debugging)
     *
     * @return array Collected schemas
     */
    public function get_collected_schemas() {
        return $this->collected_schemas;
    }

    /**
     * Clear collected schemas
     */
    public function clear_collected_schemas() {
        $this->collected_schemas = array();
    }
}