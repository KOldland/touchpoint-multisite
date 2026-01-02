<?php
/**
 * KHM SEO Schema Manager - Phase 3.1 Enhanced
 * 
 * Comprehensive structured data management system for enhanced search engine visibility.
 * Supports multiple schema types including Article, Organization, Person, Product, and more.
 * 
 * Features:
 * - Automatic JSON-LD generation
 * - Multiple schema type support
 * - Content-aware schema selection
 * - Schema validation and testing
 * - Integration with WordPress content types
 * - Rich snippets optimization
 * - Social media markup integration
 * 
 * @package KHM_SEO
 * @version 3.0.0
 */

namespace KHM_SEO\Schema;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schema Manager Class
 * Central coordinator for all structured data functionality
 */
class SchemaManager {
    
    /**
     * @var array Registered schema types
     */
    private $schema_types = [];
    
    /**
     * @var array Schema configuration
     */
    private $config = [];
    
    /**
     * @var bool Whether schema output is enabled
     */
    private $schema_enabled = true;
    
    /**
     * @var array Current page schema data
     */
    private $current_schema = [];

    /**
     * Initialize the schema manager.
     */
    public function __construct() {
        $this->load_config();
        $this->register_schema_types();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        // Schema output hooks
        add_action( 'wp_head', array( $this, 'output_schema' ), 25 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_schema_assets' ) );
        
        // Content-specific schema hooks
        add_action( 'wp', array( $this, 'determine_schema_for_current_page' ) );
        add_filter( 'khm_seo_schema_data', array( $this, 'filter_schema_data' ), 10, 2 );
        
        // Admin hooks for schema configuration
        add_action( 'admin_init', array( $this, 'register_schema_settings' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_schema_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_schema_meta_data' ) );
        
        // AJAX hooks for schema preview
        add_action( 'wp_ajax_khm_seo_preview_schema', array( $this, 'ajax_preview_schema' ) );
        add_action( 'wp_ajax_khm_seo_validate_schema', array( $this, 'ajax_validate_schema' ) );
    }

    /**
     * Register schema-related settings (placeholder to avoid missing callback fatals).
     */
    public function register_schema_settings() {
        // Settings registration can be expanded later; keep method defined to satisfy hooks.
    }

    /**
     * Legacy placeholder to avoid missing callback fatals.
     *
     * Schema meta boxes are handled by Schema\Admin\SchemaAdminManager; this
     * exists to keep older hook registrations from breaking post saves.
     */
    public function add_schema_meta_boxes() {
        // Intentionally left blank.
    }

    /**
     * Legacy placeholder to avoid missing callback fatals when saving posts.
     *
     * Schema meta saving is handled by Schema\Admin\SchemaAdminManager. This
     * stub keeps the legacy hook from triggering fatal errors.
     *
     * @param int $post_id Post ID.
     */
    public function save_schema_meta_data( $post_id ) {
        // Intentionally left blank.
    }

    /**
     * Legacy placeholder to avoid missing callback fatals when enqueueing assets.
     *
     * Frontend schema assets are not required; this exists to satisfy the
     * wp_enqueue_scripts hook that was previously registered.
     */
    public function enqueue_schema_assets() {
        // Intentionally left blank.
    }
    
    /**
     * Load schema configuration
     */
    private function load_config() {
        $defaults = array(
            'enable_schema' => true,
            'enable_article_schema' => true,
            'enable_organization_schema' => true,
            'enable_person_schema' => true,
            'enable_breadcrumb_schema' => true,
            'enable_website' => true,
            'enable_breadcrumbs' => true,
            'organization_name' => get_bloginfo( 'name' ),
            'organization_url' => home_url(),
            'organization_logo' => '',
            'default_author_type' => 'Person',
            'schema_debug' => false,
            'auto_generate_schema' => true,
            'default_article_type' => 'Article'
        );
        
        $this->config = wp_parse_args( get_option( 'khm_seo_schema_settings', array() ), $defaults );
        $this->schema_enabled = $this->config['enable_schema'];
    }
    
    /**
     * Register available schema types
     */
    private function register_schema_types() {
        // Core schema types
        $this->register_schema_type( 'Article', 'KHM_SEO\\Schema\\Types\\ArticleSchema' );
        $this->register_schema_type( 'Organization', 'KHM_SEO\\Schema\\Types\\OrganizationSchema' );
        $this->register_schema_type( 'Person', 'KHM_SEO\\Schema\\Types\\PersonSchema' );
        $this->register_schema_type( 'Website', 'KHM_SEO\\Schema\\Types\\WebsiteSchema' );
        $this->register_schema_type( 'BreadcrumbList', 'KHM_SEO\\Schema\\Types\\BreadcrumbSchema' );
        
        // E-commerce schema types (if WooCommerce is active)
        if ( class_exists( 'WooCommerce' ) ) {
            $this->register_schema_type( 'Product', 'KHM_SEO\\Schema\\Types\\ProductSchema' );
        }
        
        // Allow third-party schema type registration
        do_action( 'khm_seo_register_schema_types', $this );
    }
    
    /**
     * Register a schema type
     * 
     * @param string $name Schema type name
     * @param string $class_name Full class name for schema type
     */
    public function register_schema_type( $name, $class_name ) {
        $this->schema_types[ $name ] = $class_name;
    }
    
    /**
     * Determine appropriate schema for current page
     */
    public function determine_schema_for_current_page() {
        if ( ! $this->schema_enabled ) {
            return;
        }
        
        global $wp_query;
        $schema_data = array();
        
        // Website schema (always present)
        if ( $this->should_output_website_schema() ) {
            $schema_data[] = $this->get_website_schema();
        }
        
        // Organization schema
        if ( $this->should_output_organization_schema() ) {
            $schema_data[] = $this->get_organization_schema();
        }
        
        // Page-specific schema
        if ( \is_single() || \is_page() ) {
            if ( \is_single() && $this->config['enable_article_schema'] ) {
                $schema_data[] = $this->get_article_schema();
            }
            
            // Author schema for posts
            if ( \is_single() && $this->config['enable_person_schema'] ) {
                global $post;
                $author = \get_user_by( 'id', $post->post_author );
                if ( $author ) {
                    $schema_data[] = $this->generate_person_schema( $author );
                }
            }
        }
        
        // Archive pages
        if ( \is_category() || \is_tag() || \is_tax() ) {
            $schema_data[] = $this->get_collection_page_schema();
        }
        
        // Product pages (WooCommerce)
        if ( function_exists( 'is_product' ) && \is_product() ) {
            $schema_data[] = $this->generate_product_schema( \get_queried_object() );
        }
        
        // Breadcrumb schema
        if ( $this->should_output_breadcrumb_schema() && ! \is_front_page() ) {
            $breadcrumb_schema = $this->get_breadcrumb_schema();
            if ( ! empty( $breadcrumb_schema ) ) {
                $schema_data[] = $breadcrumb_schema;
            }
        }
        
        // Filter schema data
        $schema_data = apply_filters( 'khm_seo_page_schema_data', $schema_data, $wp_query );
        
        $this->current_schema = array_filter( $schema_data );
    }
    
    /**
     * Generate person schema for author
     * 
     * @param WP_User $author Author user object
     * @return array Person schema
     */
    private function generate_person_schema( $author ) {
        $schema = array(
            '@type' => 'Person',
            '@id' => \get_author_posts_url( $author->ID ) . '#person',
            'name' => $author->display_name,
            'url' => \get_author_posts_url( $author->ID ),
        );
        
        // Add biography if available
        if ( ! empty( $author->description ) ) {
            $schema['description'] = $author->description;
        }
        
        // Add avatar if available
        $avatar_url = \get_avatar_url( $author->ID, array( 'size' => 512 ) );
        if ( $avatar_url ) {
            $schema['image'] = array(
                '@type' => 'ImageObject',
                'url' => $avatar_url,
            );
        }
        
        return $schema;
    }
    
    /**
     * Generate product schema for WooCommerce products
     * 
     * @param WC_Product $product Product object
     * @return array Product schema
     */
    private function generate_product_schema( $product ) {
        if ( ! class_exists( 'WC_Product' ) || ! is_a( $product, 'WC_Product' ) ) {
            return array();
        }
        
        $schema = array(
            '@type' => 'Product',
            '@id' => \get_permalink( $product->get_id() ) . '#product',
            'name' => $product->get_name(),
            'description' => $product->get_short_description() ?: $product->get_description(),
            'sku' => $product->get_sku(),
            'offers' => array(
                '@type' => 'Offer',
                'price' => $product->get_price(),
                'priceCurrency' => \get_woocommerce_currency(),
                'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => \get_permalink( $product->get_id() ),
            ),
        );
        
        // Add product images
        $image_ids = $product->get_gallery_image_ids();
        if ( \has_post_thumbnail( $product->get_id() ) ) {
            array_unshift( $image_ids, \get_post_thumbnail_id( $product->get_id() ) );
        }
        
        if ( ! empty( $image_ids ) ) {
            $images = array();
            foreach ( $image_ids as $image_id ) {
                $image_data = \wp_get_attachment_image_src( $image_id, 'full' );
                if ( $image_data ) {
                    $images[] = $image_data[0];
                }
            }
            $schema['image'] = $images;
        }
        
        return $schema;
    }

    /**
     * Output schema markup.
     */
    public function output_schema() {
        if ( empty( $this->current_schema ) ) {
            // Fallback to old method if current_schema is empty
            $this->determine_schema_for_current_page();
        }
        
        if ( empty( $this->current_schema ) ) {
            return;
        }

        $cache_key = $this->get_schema_cache_key();
        if ( ! $this->config['schema_debug'] ) {
            $cached = wp_cache_get( $cache_key, 'khm_seo_schema' );
            if ( $cached ) {
                echo '<script type="application/ld+json">' . $cached . '</script>' . "\n";
                return;
            }
        }

        // Combine all schema data into a single JSON-LD block
        $combined_schema = array(
            '@context' => 'https://schema.org',
            '@graph'   => $this->current_schema
        );
        
        // Validate and clean schema data
        $combined_schema = $this->validate_schema_data( $combined_schema );
        
        if ( empty( $combined_schema['@graph'] ) ) {
            return;
        }
        
        // Output JSON-LD
        $encoded = wp_json_encode( $combined_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( ! $encoded ) {
            return;
        }

        echo '<script type="application/ld+json">' . $encoded . '</script>' . "\n";

        if ( ! $this->config['schema_debug'] ) {
            wp_cache_set( $cache_key, $encoded, 'khm_seo_schema', HOUR_IN_SECONDS );
        }
        
        // Debug output if enabled
        if ( $this->config['schema_debug'] && current_user_can( 'manage_options' ) ) {
            echo '<!-- KHM SEO Schema Debug: ' . count( $this->current_schema ) . ' schema types generated -->' . "\n";
        }
    }

    /**
     * Build a cache key for the current page schema output.
     */
    private function get_schema_cache_key() {
        $id  = get_queried_object_id();
        $url = home_url( add_query_arg( array(), $_SERVER['REQUEST_URI'] ?? '' ) );
        return 'schema_' . md5( $id . '|' . $url );
    }
    
    /**
     * Validate and clean schema data
     * 
     * @param array $schema_data Raw schema data
     * @return array Validated schema data
     */
    private function validate_schema_data( $schema_data ) {
        // Remove empty values and null entries
        array_walk_recursive( $schema_data, function( &$value, $key ) {
            if ( is_string( $value ) ) {
                $value = trim( $value );
            }
        } );
        
        // Filter out empty schema objects
        if ( isset( $schema_data['@graph'] ) ) {
            $schema_data['@graph'] = array_filter( $schema_data['@graph'], function( $schema ) {
                return ! empty( $schema ) && isset( $schema['@type'] );
            } );
        }
        
        return $schema_data;
    }

    /**
     * Get schema data for current page.
     *
     * @return array Schema data.
     */
    private function get_schema_data() {
        $schema_data = array(
            '@context' => 'https://schema.org',
            '@graph'   => array()
        );

        // Add website schema
        if ( $this->should_output_website_schema() ) {
            $schema_data['@graph'][] = $this->get_website_schema();
        }

        // Add organization schema
        if ( $this->should_output_organization_schema() ) {
            $schema_data['@graph'][] = $this->get_organization_schema();
        }

        // Add page-specific schema
        if ( is_singular() ) {
            $schema_data['@graph'][] = $this->get_article_schema();
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $schema_data['@graph'][] = $this->get_collection_page_schema();
        }

        // Add breadcrumb schema
        if ( $this->should_output_breadcrumb_schema() ) {
            $schema_data['@graph'][] = $this->get_breadcrumb_schema();
        }

        return $schema_data;
    }

    /**
     * Get website schema.
     *
     * @return array Website schema.
     */
    private function get_website_schema() {
        return array(
            '@type'         => 'WebSite',
            '@id'           => home_url( '/#website' ),
            'url'           => home_url( '/' ),
            'name'          => get_bloginfo( 'name' ),
            'description'   => get_bloginfo( 'description' ),
            'potentialAction' => array(
                '@type'       => 'SearchAction',
                'target'      => home_url( '/?s={search_term_string}' ),
                'query-input' => 'required name=search_term_string'
            )
        );
    }

    /**
     * Get organization schema.
     *
     * @return array Organization schema.
     */
    private function get_organization_schema() {
        $options = get_option( 'khm_seo_general', array() );
        
        $schema = array(
            '@type' => 'Organization',
            '@id'   => home_url( '/#organization' ),
            'name'  => isset( $options['company_name'] ) ? $options['company_name'] : get_bloginfo( 'name' ),
            'url'   => home_url( '/' )
        );

        if ( ! empty( $options['company_logo'] ) ) {
            $schema['logo'] = array(
                '@type' => 'ImageObject',
                'url'   => $options['company_logo']
            );
        }

        if ( ! empty( $options['social_profiles'] ) && is_array( $options['social_profiles'] ) ) {
            $schema['sameAs'] = array_values( $options['social_profiles'] );
        }

        return $schema;
    }

    /**
     * Get article schema for posts.
     *
     * @return array Article schema.
     */
    private function get_article_schema() {
        global $post;
        
        if ( ! $post ) {
            return array();
        }

        $options = get_option( 'khm_seo_schema', array() );
        $article_type = isset( $options['default_article_type'] ) ? $options['default_article_type'] : 'Article';

        $schema = array(
            '@type'           => $article_type,
            '@id'             => get_permalink( $post ) . '#article',
            'headline'        => get_the_title( $post ),
            'datePublished'   => get_the_date( 'c', $post ),
            'dateModified'    => \get_the_modified_date( 'c', $post ),
            'author'          => array(
                '@type' => 'Person',
                'name'  => \get_the_author_meta( 'display_name', $post->post_author ),
                'url'   => \get_author_posts_url( $post->post_author )
            ),
            'publisher'       => array(
                '@id' => home_url( '/#organization' )
            ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => get_permalink( $post )
            )
        );

        // Add featured image if available
        if ( has_post_thumbnail( $post ) ) {
            $image_id = get_post_thumbnail_id( $post );
            $image_data = wp_get_attachment_image_src( $image_id, 'full' );
            
            if ( $image_data ) {
                $schema['image'] = array(
                    '@type' => 'ImageObject',
                    'url'   => $image_data[0],
                    'width' => $image_data[1],
                    'height'=> $image_data[2]
                );
            }
        }

        return $schema;
    }

    /**
     * Get collection page schema for archives.
     *
     * @return array Collection page schema.
     */
    private function get_collection_page_schema() {
        $term = get_queried_object();
        
        if ( ! $term ) {
            return array();
        }

        return array(
            '@type'       => 'CollectionPage',
            '@id'         => get_term_link( $term ) . '#webpage',
            'url'         => get_term_link( $term ),
            'name'        => $term->name,
            'description' => $term->description ?: null,
            'isPartOf'    => array(
                '@id' => home_url( '/#website' )
            )
        );
    }

    /**
     * Get breadcrumb schema.
     *
     * @return array Breadcrumb schema.
     */
    private function get_breadcrumb_schema() {
        $breadcrumbs = $this->get_breadcrumb_trail();
        
        if ( empty( $breadcrumbs ) ) {
            return array();
        }

        $breadcrumb_list = array(
            '@type'           => 'BreadcrumbList',
            'itemListElement' => array()
        );

        foreach ( $breadcrumbs as $index => $breadcrumb ) {
            $breadcrumb_list['itemListElement'][] = array(
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'item'     => array(
                    '@type' => 'WebPage',
                    '@id'   => $breadcrumb['url'],
                    'name'  => $breadcrumb['title']
                )
            );
        }

        return $breadcrumb_list;
    }

    /**
     * Get breadcrumb trail for current page.
     *
     * @return array Breadcrumb items.
     */
    private function get_breadcrumb_trail() {
        $breadcrumbs = array();

        // Home page
        $breadcrumbs[] = array(
            'title' => get_bloginfo( 'name' ),
            'url'   => home_url( '/' )
        );

        // Add current page
        if ( is_singular() ) {
            global $post;
            
            // Add parent pages for hierarchical post types
            if ( $post->post_parent ) {
                $parent_ids = array_reverse( \get_ancestors( $post->ID, $post->post_type ) );
                foreach ( $parent_ids as $parent_id ) {
                    $breadcrumbs[] = array(
                        'title' => get_the_title( $parent_id ),
                        'url'   => get_permalink( $parent_id )
                    );
                }
            }

            $breadcrumbs[] = array(
                'title' => get_the_title( $post ),
                'url'   => get_permalink( $post )
            );
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            $breadcrumbs[] = array(
                'title' => $term->name,
                'url'   => get_term_link( $term )
            );
        }

        return $breadcrumbs;
    }

    /**
     * Check if website schema should be output.
     *
     * @return bool
     */
    private function should_output_website_schema() {
        $options = get_option( 'khm_seo_schema', array() );
        return ! empty( $options['enable_website'] );
    }

    /**
     * Check if organization schema should be output.
     *
     * @return bool
     */
    private function should_output_organization_schema() {
        $options = get_option( 'khm_seo_schema', array() );
        return ! empty( $options['enable_organization'] );
    }

    /**
     * Check if breadcrumb schema should be output.
     *
     * @return bool
     */
    private function should_output_breadcrumb_schema() {
        $options = get_option( 'khm_seo_schema', array() );
        return ! empty( $options['enable_breadcrumbs'] );
    }
}
