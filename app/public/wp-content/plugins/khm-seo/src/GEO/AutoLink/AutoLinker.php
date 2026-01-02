<?php
/**
 * Auto-linking Engine
 *
 * Handles automatic internal linking of entities in content with first-occurrence logic,
 * skip-zones, and performance optimizations.
 *
 * @package KHM_SEO\GEO\AutoLink
 * @since 2.0.0
 */

namespace KHM_SEO\GEO\AutoLink;

use KHM_SEO\GEO\Entity\EntityManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * AutoLinker Class
 * Manages automatic entity linking in content
 */
class AutoLinker {

    /**
     * @var EntityManager Entity manager instance
     */
    private $entity_manager;

    /**
     * @var array Skip zones (HTML tags to avoid linking in)
     */
    private $skip_zones = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'strong', 'em', 'code', 'pre' );

    /**
     * @var int Max links per post
     */
    private $max_links_per_post = 10;

    /**
     * @var string Link mode: 'first_only', 'all', 'manual', 'never'
     */
    private $link_mode = 'first_only';

    /**
     * Constructor
     */
    public function __construct( EntityManager $entity_manager ) {
        $this->entity_manager = $entity_manager;
    }

    /**
     * Set configuration
     *
     * @param array $config Configuration array
     */
    public function set_config( $config ) {
        $this->max_links_per_post = $config['max_auto_links_per_post'] ?? 10;
        $this->link_mode = $config['auto_linking_mode'] ?? 'first_only';
    }

    /**
     * Process content for auto-linking
     *
     * @param string $content Post content
     * @param int $post_id Post ID
     * @return string Modified content
     */
    public function process_content( $content, $post_id = 0 ) {
        if ( $this->link_mode === 'never' ) {
            return $content;
        }

        if ( $this->link_mode === 'manual' ) {
            // Only link manually specified entities
            return $this->process_manual_links( $content, $post_id );
        }

        // Get active entities
        $entities = $this->entity_manager->search_entities( array(
            'status' => 'active',
            'limit' => 100 // Limit for performance
        ) );

        if ( empty( $entities ) ) {
            return $content;
        }

        // Prepare entity patterns
        $patterns = $this->prepare_entity_patterns( $entities );

        // Process content
        $content = $this->apply_auto_linking( $content, $patterns, $post_id );

        return $content;
    }

    /**
     * Prepare regex patterns for entities
     *
     * @param array $entities Entity objects
     * @return array Patterns array
     */
    private function prepare_entity_patterns( $entities ) {
        $patterns = array();

        foreach ( $entities as $entity ) {
            $terms = array( $entity->canonical );

            // Add aliases
            $aliases = $this->entity_manager->get_entity_aliases( $entity->id );
            $terms = array_merge( $terms, $aliases );

            // Sort by length descending to match longer terms first
            usort( $terms, function( $a, $b ) {
                return strlen( $b ) - strlen( $a );
            } );

            foreach ( $terms as $term ) {
                if ( strlen( $term ) < 3 ) {
                    continue; // Skip very short terms
                }

                $pattern = preg_quote( $term, '/' );
                $patterns[] = array(
                    'pattern' => '/\b' . $pattern . '\b/ui', // Word boundaries, case insensitive
                    'replacement' => '<a href="' . esc_url( get_permalink( $entity->id ) ) . '" class="khm-entity-link" data-entity-id="' . $entity->id . '">' . $term . '</a>',
                    'entity_id' => $entity->id,
                    'term' => $term
                );
            }
        }

        return $patterns;
    }

    /**
     * Apply auto-linking to content
     *
     * @param string $content Content
     * @param array $patterns Patterns
     * @param int $post_id Post ID
     * @return string Modified content
     */
    private function apply_auto_linking( $content, $patterns, $post_id ) {
        // Parse HTML to avoid linking in skip zones
        $dom = new \DOMDocument();
        @$dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

        $this->process_dom_node( $dom->documentElement, $patterns, $post_id );

        // Get body content back
        $body = $dom->getElementsByTagName( 'body' )->item( 0 );
        if ( $body ) {
            $content = $dom->saveHTML( $body );
            // Remove body tags
            $content = preg_replace( '/<\/?body[^>]*>/', '', $content );
        }

        return $content;
    }

    /**
     * Process DOM node recursively
     *
     * @param \DOMNode $node Node
     * @param array $patterns Patterns
     * @param int $post_id Post ID
     * @param int $link_count Current link count
     */
    private function process_dom_node( $node, &$patterns, $post_id, &$link_count = 0 ) {
        if ( $link_count >= $this->max_links_per_post ) {
            return;
        }

        if ( $node->nodeType === XML_TEXT_NODE ) {
            $text = $node->nodeValue;

            foreach ( $patterns as &$pattern ) {
                if ( $link_count >= $this->max_links_per_post ) {
                    break;
                }

                if ( $this->link_mode === 'first_only' && $pattern['linked'] ) {
                    continue;
                }

                $text = preg_replace_callback( $pattern['pattern'], function( $matches ) use ( &$pattern, &$link_count, $post_id ) {
                    if ( $link_count >= $this->max_links_per_post ) {
                        return $matches[0];
                    }

                    // Check if already linked in this post
                    if ( $this->is_already_linked( $post_id, $pattern['entity_id'] ) ) {
                        return $matches[0];
                    }

                    $link_count++;
                    $pattern['linked'] = true;

                    // Log the linking
                    $this->log_entity_linking( $post_id, $pattern['entity_id'], $matches[0] );

                    return $pattern['replacement'];
                }, $text, 1 ); // Limit to 1 replacement per pattern per text node
            }

            $node->nodeValue = $text;
        } elseif ( $node->nodeType === XML_ELEMENT_NODE ) {
            $tag_name = strtolower( $node->tagName );

            // Skip certain tags
            if ( in_array( $tag_name, $this->skip_zones ) ) {
                return;
            }

            // Process child nodes
            foreach ( $node->childNodes as $child ) {
                $this->process_dom_node( $child, $patterns, $post_id, $link_count );
            }
        }
    }

    /**
     * Process manual links (placeholder for future)
     *
     * @param string $content Content
     * @param int $post_id Post ID
     * @return string Content
     */
    private function process_manual_links( $content, $post_id ) {
        // For now, return unchanged
        return $content;
    }

    /**
     * Check if entity is already linked in post
     *
     * @param int $post_id Post ID
     * @param int $entity_id Entity ID
     * @return bool
     */
    private function is_already_linked( $post_id, $entity_id ) {
        global $wpdb;
        $table = $this->entity_manager->get_table_name( 'page_entities' );
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE post_id = %d AND entity_id = %d AND role = 'link'",
            $post_id, $entity_id
        ) ) !== null;
    }

    /**
     * Log entity linking
     *
     * @param int $post_id Post ID
     * @param int $entity_id Entity ID
     * @param string $term Term linked
     */
    private function log_entity_linking( $post_id, $entity_id, $term ) {
        global $wpdb;
        $table = $this->entity_manager->get_table_name( 'page_entities' );
        $wpdb->insert( $table, array(
            'post_id' => $post_id,
            'entity_id' => $entity_id,
            'role' => 'link',
            'confidence' => 1.0,
            'detected_by' => 'auto_link',
            'created_at' => current_time( 'mysql' )
        ) );
    }
}
