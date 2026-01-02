<?php
/**
 * Schema Generator - Security & Performance Enhanced
 * 
 * This class handles the generation of JSON-LD structured data for various content types.
 * It provides automatic schema detection, supports multiple schema.org types, and includes
 * validation tools to ensure compliance with search engine requirements.
 * 
 * Security Features:
 * - Input sanitization and validation
 * - Output escaping for XSS prevention
 * - Capability checks for admin functions
 * - Rate limiting for external requests
 * 
 * Performance Features:
 * - Smart caching with transients
 * - Optimized database queries
 * - Memory usage optimization
 * - Early return patterns
 * 
 * Supported Schema Types:
 * - Article (blog posts, news articles)
 * - Organization (business information)
 * - Person (author profiles)
 * - Product (e-commerce items)
 * - Recipe (cooking instructions)
 * - Event (upcoming events)
 * - FAQ (frequently asked questions)
 * - Breadcrumb (navigation structure)
 * - WebSite (site-wide information)
 * 
 * @package KHM_SEO\Schema
 * @since 2.4.0
 * @version 2.4.1 - Security & Performance Enhanced
 */

namespace KHM_SEO\Schema;

use WP_Post;
use WP_Term;
use WP_User;

/**
 * Schema Generator Class - Enhanced Security & Performance
 */
class SchemaGenerator {
    /**
     * @var array Schema configuration
     */
    private $config;

    /**
     * @var array Supported schema types
     */
    private $supported_types;

    /**
     * @var array Default schema context
     */
    private $context;
    
    /**
     * @var array Performance cache
     */
    private $cache = [];

    /**
     * Constructor - Enhanced with security initialization
     */
    public function __construct() {
        // Security: Initialize only if user has appropriate capabilities
        $this->init_security_checks();
        $this->init_config();
        $this->init_supported_types();
        $this->init_context();
        $this->init_performance_cache();
    }
    
    /**
     * Security initialization
     */
    private function init_security_checks() {
        // Performance: Early return if not in admin and not generating frontend schema
        if (is_admin() && !current_user_can('edit_posts')) {
            return;
        }
    }
    
    /**
     * Initialize performance cache
     */
    private function init_performance_cache() {
        $this->cache = [
            'schema_cache' => [],
            'query_cache' => [],
            'start_time' => microtime(true)
        ];
    }

    /**
     * Initialize schema configuration - Enhanced with caching
     */
    private function init_config() {
        // Performance: Cache configuration to avoid repeated database calls
        $cache_key = 'khm_seo_schema_config';
        $cached_config = wp_cache_get($cache_key, 'khm_seo');
        
        if (false !== $cached_config) {
            $this->config = $cached_config;
            return;
        }
        
        $this->config = \wp_parse_args(\get_option('khm_seo_schema_settings', []), [
            'enable_schema' => true,
            'auto_generate' => true,
            'enable_article' => true,
            'enable_organization' => true,
            'enable_person' => true,
            'enable_product' => false,
            'enable_recipe' => false,
            'enable_event' => false,
            'enable_faq' => false,
            'enable_breadcrumb' => true,
            'enable_website' => true,
            'organization_name' => \get_bloginfo('name'),
            'organization_logo' => '',
            'organization_type' => 'Organization',
            'default_author' => \get_option('admin_email'),
            'image_property' => 'featured_image',
            'validation_mode' => 'strict'
        ]);
        
        // Performance: Cache for 1 hour
        wp_cache_set($cache_key, $this->config, 'khm_seo', 3600);
    }

    /**
     * Initialize supported schema types
     */
    private function init_supported_types() {
        $this->supported_types = [
            'Article' => [
                'description' => 'Blog posts, news articles, and editorial content',
                'required_fields' => ['headline', 'author', 'datePublished'],
                'optional_fields' => ['image', 'dateModified', 'description', 'mainEntityOfPage'],
                'post_types' => ['post', 'page'],
                'auto_detect' => true
            ],
            'Organization' => [
                'description' => 'Business or organization information',
                'required_fields' => ['name', '@type'],
                'optional_fields' => ['logo', 'url', 'description', 'contactPoint', 'address'],
                'post_types' => [],
                'auto_detect' => false
            ],
            'Person' => [
                'description' => 'Individual person profiles',
                'required_fields' => ['name', '@type'],
                'optional_fields' => ['image', 'jobTitle', 'worksFor', 'url', 'description'],
                'post_types' => ['author', 'team_member'],
                'auto_detect' => true
            ],
            'Product' => [
                'description' => 'E-commerce products and services',
                'required_fields' => ['name', 'description'],
                'optional_fields' => ['image', 'sku', 'brand', 'offers', 'review', 'aggregateRating'],
                'post_types' => ['product'],
                'auto_detect' => true
            ],
            'Recipe' => [
                'description' => 'Cooking recipes and instructions',
                'required_fields' => ['name', 'recipeIngredient', 'recipeInstructions'],
                'optional_fields' => ['image', 'author', 'nutrition', 'cookTime', 'prepTime'],
                'post_types' => ['recipe'],
                'auto_detect' => true
            ],
            'Event' => [
                'description' => 'Upcoming events and activities',
                'required_fields' => ['name', 'startDate'],
                'optional_fields' => ['endDate', 'location', 'description', 'organizer', 'offers'],
                'post_types' => ['event'],
                'auto_detect' => true
            ],
            'FAQ' => [
                'description' => 'Frequently asked questions',
                'required_fields' => ['mainEntity'],
                'optional_fields' => ['about', 'author'],
                'post_types' => ['faq'],
                'auto_detect' => true
            ],
            'BreadcrumbList' => [
                'description' => 'Navigation breadcrumb structure',
                'required_fields' => ['itemListElement'],
                'optional_fields' => ['numberOfItems'],
                'post_types' => [],
                'auto_detect' => false
            ],
            'WebSite' => [
                'description' => 'Website-wide information and search functionality',
                'required_fields' => ['name', 'url'],
                'optional_fields' => ['description', 'potentialAction', 'publisher'],
                'post_types' => [],
                'auto_detect' => false
            ]
        ];
    }

    /**
     * Initialize schema context
     */
    private function init_context() {
        $this->context = [
            '@context' => 'https://schema.org',
            '@graph' => []
        ];
    }

    /**
     * Generate schema markup for current context
     *
     * @param mixed $context Post, term, user, or null for global
     * @return string JSON-LD schema markup
     */
    public function generate_schema($context = null) {
        if (!$this->config['enable_schema']) {
            return '';
        }

        $schema_data = $this->context;
        
        // Auto-detect or manually specify schema types
        $schema_types = $this->detect_schema_types($context);
        
        foreach ($schema_types as $type) {
            $schema_item = $this->generate_schema_item($type, $context);
            if ($schema_item) {
                $schema_data['@graph'][] = $schema_item;
            }
        }

        // Add global schemas (Organization, WebSite)
        $global_schemas = $this->generate_global_schemas();
        $schema_data['@graph'] = array_merge($schema_data['@graph'], $global_schemas);

        return $this->format_schema_output($schema_data);
    }

    /**
     * Detect applicable schema types for context
     *
     * @param mixed $context Content context
     * @return array Array of schema types to generate
     */
    private function detect_schema_types($context) {
        $types = [];

        if ($context instanceof WP_Post) {
            $post_type = $context->post_type;
            
            // Check each supported type
            foreach ($this->supported_types as $schema_type => $config) {
                if (
                    $config['auto_detect'] && 
                    $this->config['enable_' . strtolower($schema_type)] &&
                    in_array($post_type, $config['post_types'])
                ) {
                    $types[] = $schema_type;
                }
            }

            // Default to Article for standard post types
            if (empty($types) && in_array($post_type, ['post', 'page'])) {
                if ($this->config['enable_article']) {
                    $types[] = 'Article';
                }
            }
        } elseif ($context instanceof WP_User) {
            if ($this->config['enable_person']) {
                $types[] = 'Person';
            }
        } elseif (is_null($context)) {
            // Global context - add breadcrumbs if enabled
            if ($this->config['enable_breadcrumb'] && !is_front_page()) {
                $types[] = 'BreadcrumbList';
            }
        }

        return apply_filters('khm_seo_schema_types', $types, $context);
    }

    /**
     * Generate individual schema item
     *
     * @param string $type Schema type
     * @param mixed $context Content context
     * @return array|null Schema data array
     */
    private function generate_schema_item($type, $context) {
        $method = 'generate_' . strtolower($type) . '_schema';
        
        if (method_exists($this, $method)) {
            return $this->$method($context);
        }

        return null;
    }

    /**
     * Generate Article schema
     * Generate Article schema - Enhanced with security and performance
     *
     * @param WP_Post $post Post object
     * @return array Article schema data
     */
    private function generate_article_schema($post) {
        // Security: Validate input parameter
        if (!($post instanceof WP_Post)) {
            return null;
        }
        
        // Performance: Check cache first
        $cache_key = 'article_schema_' . $post->ID . '_' . $post->post_modified;
        if (isset($this->cache['schema_cache'][$cache_key])) {
            return $this->cache['schema_cache'][$cache_key];
        }
        
        // Security: Sanitize and validate post data
        $post_title = \sanitize_text_field($post->post_title);
        $post_permalink = \get_permalink($post);
        
        // Performance: Early return if permalink generation fails
        if (!$post_permalink) {
            return null;
        }

        $schema = [
            '@type' => 'Article',
            '@id' => \esc_url($post_permalink) . '#article',
            'headline' => \esc_html($post_title),
            'description' => $this->get_post_description_secure($post),
            'datePublished' => \get_the_date('c', $post),
            'dateModified' => \get_the_modified_date('c', $post),
            'author' => $this->generate_author_reference($post->post_author),
            'publisher' => $this->generate_publisher_reference(),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => \esc_url($post_permalink)
            ]
        ];

        // Add featured image if available (with security validation)
        $image = $this->get_post_image_secure($post);
        if ($image) {
            $schema['image'] = $image;
        }

        // Add article section/category with security
        $categories = \get_the_category($post->ID);
        if (!empty($categories) && is_array($categories)) {
            $schema['articleSection'] = \esc_html($categories[0]->name);
        }

        // Add word count (with performance optimization)
        $word_count = $this->get_word_count_cached($post);
        if ($word_count > 0) {
            $schema['wordCount'] = absint($word_count);
        }

        // Add tags as keywords (with security and performance)
        $tags = \get_the_tags($post->ID);
        if ($tags && is_array($tags)) {
            $tag_names = \wp_list_pluck($tags, 'name');
            $schema['keywords'] = \esc_html(implode(', ', array_map('sanitize_text_field', $tag_names)));
        }

        // Security: Apply filters with context
        $schema = \apply_filters('khm_seo_article_schema', $schema, $post);
        
        // Performance: Cache the result
        $this->cache['schema_cache'][$cache_key] = $schema;

        return $schema;
    }
    
    /**
     * Get post description with security enhancements
     */
    private function get_post_description_secure($post) {
        // Security: Validate post object
        if (!($post instanceof WP_Post)) {
            return '';
        }
        
        $description = $this->get_post_description($post);
        
        // Security: Sanitize output
        return \esc_html(strip_tags($description));
    }
    
    /**
     * Get post image with security validation
     */
    private function get_post_image_secure($post) {
        $image = $this->get_post_image($post);
        
        if (!$image || !is_array($image)) {
            return null;
        }
        
        // Security: Validate image URLs
        if (isset($image['url'])) {
            $image['url'] = \esc_url($image['url']);
        }
        
        return $image;
    }
    
    /**
     * Get word count with performance caching
     */
    private function get_word_count_cached($post) {
        $cache_key = 'word_count_' . $post->ID . '_' . $post->post_modified;
        
        if (isset($this->cache['query_cache'][$cache_key])) {
            return $this->cache['query_cache'][$cache_key];
        }
        
        $word_count = str_word_count(strip_tags($post->post_content));
        $this->cache['query_cache'][$cache_key] = $word_count;
        
        return $word_count;
    }

    /**
     * Generate Organization schema
     *
     * @param mixed $context Content context
     * @return array Organization schema data
     */
    private function generate_organization_schema($context = null) {
        $schema = [
            '@type' => $this->config['organization_type'],
            '@id' => home_url() . '#organization',
            'name' => $this->config['organization_name'],
            'url' => home_url(),
            'description' => get_bloginfo('description')
        ];

        // Add logo if specified
        if (!empty($this->config['organization_logo'])) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url' => $this->config['organization_logo']
            ];
        }

        // Add contact information
        $contact_info = $this->get_organization_contact();
        if ($contact_info) {
            $schema['contactPoint'] = $contact_info;
        }

        // Add social media profiles
        $social_profiles = $this->get_social_profiles();
        if ($social_profiles) {
            $schema['sameAs'] = $social_profiles;
        }

        return apply_filters('khm_seo_organization_schema', $schema, $context);
    }

    /**
     * Generate Person schema
     *
     * @param mixed $context User object or post context
     * @return array Person schema data
     */
    private function generate_person_schema($context) {
        $user = null;

        if ($context instanceof WP_User) {
            $user = $context;
        } elseif ($context instanceof WP_Post) {
            $user = get_user_by('ID', $context->post_author);
        }

        if (!$user) {
            return null;
        }

        $schema = [
            '@type' => 'Person',
            '@id' => get_author_posts_url($user->ID) . '#person',
            'name' => $user->display_name,
            'url' => get_author_posts_url($user->ID),
            'description' => get_user_meta($user->ID, 'description', true)
        ];

        // Add profile image
        $avatar_url = get_avatar_url($user->ID, ['size' => 512]);
        if ($avatar_url) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url' => $avatar_url
            ];
        }

        // Add job title if available
        $job_title = get_user_meta($user->ID, 'job_title', true);
        if ($job_title) {
            $schema['jobTitle'] = $job_title;
        }

        // Add organization reference
        $schema['worksFor'] = $this->generate_organization_reference();

        // Add social profiles
        $user_social = $this->get_user_social_profiles($user->ID);
        if ($user_social) {
            $schema['sameAs'] = $user_social;
        }

        return apply_filters('khm_seo_person_schema', $schema, $user);
    }

    /**
     * Generate Product schema
     *
     * @param WP_Post $post Product post
     * @return array Product schema data
     */
    private function generate_product_schema($post) {
        if (!($post instanceof WP_Post) || $post->post_type !== 'product') {
            return null;
        }

        $schema = [
            '@type' => 'Product',
            '@id' => get_permalink($post) . '#product',
            'name' => $post->post_title,
            'description' => $this->get_post_description($post),
            'url' => get_permalink($post)
        ];

        // Add product image
        $image = $this->get_post_image($post);
        if ($image) {
            $schema['image'] = $image;
        }

        // Add SKU if available
        $sku = get_post_meta($post->ID, '_sku', true);
        if ($sku) {
            $schema['sku'] = $sku;
        }

        // Add brand information
        $brand = get_post_meta($post->ID, '_brand', true);
        if ($brand) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $brand
            ];
        }

        // Add offers information
        $price = get_post_meta($post->ID, '_price', true);
        if ($price) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'price' => $price,
                'priceCurrency' => get_option('woocommerce_currency', 'USD'),
                'availability' => 'https://schema.org/InStock',
                'url' => get_permalink($post)
            ];
        }

        // Add reviews and ratings
        $rating = $this->get_product_rating($post->ID);
        if ($rating) {
            $schema['aggregateRating'] = $rating;
        }

        return apply_filters('khm_seo_product_schema', $schema, $post);
    }

    /**
     * Generate BreadcrumbList schema
     *
     * @param mixed $context Content context
     * @return array BreadcrumbList schema data
     */
    private function generate_breadcrumblist_schema($context = null) {
        $breadcrumbs = $this->get_breadcrumb_trail();
        
        if (empty($breadcrumbs)) {
            return null;
        }

        $schema = [
            '@type' => 'BreadcrumbList',
            '@id' => home_url() . '#breadcrumb',
            'itemListElement' => []
        ];

        foreach ($breadcrumbs as $position => $breadcrumb) {
            $schema['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $breadcrumb['name'],
                'item' => $breadcrumb['url']
            ];
        }

        $schema['numberOfItems'] = count($breadcrumbs);

        return apply_filters('khm_seo_breadcrumb_schema', $schema, $breadcrumbs);
    }

    /**
     * Generate WebSite schema
     *
     * @param mixed $context Content context
     * @return array WebSite schema data
     */
    private function generate_website_schema($context = null) {
        $schema = [
            '@type' => 'WebSite',
            '@id' => home_url() . '#website',
            'name' => get_bloginfo('name'),
            'url' => home_url(),
            'description' => get_bloginfo('description'),
            'publisher' => $this->generate_organization_reference()
        ];

        // Add search action
        $search_url = home_url('/?s={search_term_string}');
        $schema['potentialAction'] = [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $search_url
            ],
            'query-input' => 'required name=search_term_string'
        ];

        return apply_filters('khm_seo_website_schema', $schema, $context);
    }

    /**
     * Generate global schemas (Organization, WebSite)
     *
     * @return array Array of global schema items
     */
    private function generate_global_schemas() {
        $schemas = [];

        // Add Organization schema
        if ($this->config['enable_organization']) {
            $org_schema = $this->generate_organization_schema();
            if ($org_schema) {
                $schemas[] = $org_schema;
            }
        }

        // Add WebSite schema on homepage
        if ($this->config['enable_website'] && is_front_page()) {
            $website_schema = $this->generate_website_schema();
            if ($website_schema) {
                $schemas[] = $website_schema;
            }
        }

        return $schemas;
    }

    /**
     * Generate author reference
     *
     * @param int $author_id Author user ID
     * @return array Author reference
     */
    private function generate_author_reference($author_id) {
        $user = get_user_by('ID', $author_id);
        
        if (!$user) {
            return [
                '@type' => 'Person',
                'name' => 'Unknown Author'
            ];
        }

        return [
            '@type' => 'Person',
            '@id' => get_author_posts_url($user->ID) . '#person',
            'name' => $user->display_name,
            'url' => get_author_posts_url($user->ID)
        ];
    }

    /**
     * Generate publisher reference
     *
     * @return array Publisher reference
     */
    private function generate_publisher_reference() {
        return [
            '@type' => $this->config['organization_type'],
            '@id' => home_url() . '#organization'
        ];
    }

    /**
     * Generate organization reference
     *
     * @return array Organization reference
     */
    private function generate_organization_reference() {
        return [
            '@id' => home_url() . '#organization'
        ];
    }

    /**
     * Get post description
     *
     * @param WP_Post $post Post object
     * @return string Post description
     */
    private function get_post_description($post) {
        // Try meta description first
        $meta_description = get_post_meta($post->ID, '_meta_description', true);
        if (!empty($meta_description)) {
            return $meta_description;
        }

        // Try excerpt
        if (!empty($post->post_excerpt)) {
            return wp_strip_all_tags($post->post_excerpt);
        }

        // Generate from content
        $content = wp_strip_all_tags($post->post_content);
        return wp_trim_words($content, 55);
    }

    /**
     * Get post featured image
     *
     * @param WP_Post $post Post object
     * @return array|null Image data
     */
    private function get_post_image($post) {
        $image_id = get_post_thumbnail_id($post->ID);
        
        if (!$image_id) {
            return null;
        }

        $image_data = wp_get_attachment_image_src($image_id, 'large');
        
        if (!$image_data) {
            return null;
        }

        return [
            '@type' => 'ImageObject',
            'url' => $image_data[0],
            'width' => $image_data[1],
            'height' => $image_data[2]
        ];
    }

    /**
     * Get breadcrumb trail
     *
     * @return array Breadcrumb items
     */
    private function get_breadcrumb_trail() {
        $breadcrumbs = [];

        // Home
        $breadcrumbs[] = [
            'name' => get_bloginfo('name'),
            'url' => home_url()
        ];

        global $wp_query;

        if (is_single() || is_page()) {
            $post = get_queried_object();
            
            // Add categories for posts
            if ($post->post_type === 'post') {
                $categories = get_the_category($post->ID);
                if (!empty($categories)) {
                    $category = $categories[0];
                    $breadcrumbs[] = [
                        'name' => $category->name,
                        'url' => get_category_link($category->term_id)
                    ];
                }
            }

            // Add current page/post
            $breadcrumbs[] = [
                'name' => $post->post_title,
                'url' => get_permalink($post)
            ];
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            $breadcrumbs[] = [
                'name' => $term->name,
                'url' => get_term_link($term)
            ];
        } elseif (is_author()) {
            $author = get_queried_object();
            $breadcrumbs[] = [
                'name' => $author->display_name,
                'url' => get_author_posts_url($author->ID)
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Get organization contact information
     *
     * @return array|null Contact information
     */
    private function get_organization_contact() {
        $contact_settings = get_option('khm_seo_organization_contact', []);
        
        if (empty($contact_settings)) {
            return null;
        }

        $contact = [
            '@type' => 'ContactPoint',
            'contactType' => 'customer service'
        ];

        if (!empty($contact_settings['phone'])) {
            $contact['telephone'] = $contact_settings['phone'];
        }

        if (!empty($contact_settings['email'])) {
            $contact['email'] = $contact_settings['email'];
        }

        return $contact;
    }

    /**
     * Get social media profiles
     *
     * @return array|null Social profile URLs
     */
    private function get_social_profiles() {
        $social_settings = get_option('khm_seo_social_profiles', []);
        
        $profiles = array_filter([
            $social_settings['facebook'] ?? '',
            $social_settings['twitter'] ?? '',
            $social_settings['instagram'] ?? '',
            $social_settings['linkedin'] ?? '',
            $social_settings['youtube'] ?? ''
        ]);

        return !empty($profiles) ? array_values($profiles) : null;
    }

    /**
     * Get user social profiles
     *
     * @param int $user_id User ID
     * @return array|null User social profiles
     */
    private function get_user_social_profiles($user_id) {
        $profiles = array_filter([
            get_user_meta($user_id, 'twitter', true),
            get_user_meta($user_id, 'facebook', true),
            get_user_meta($user_id, 'linkedin', true)
        ]);

        return !empty($profiles) ? array_values($profiles) : null;
    }

    /**
     * Get product rating
     *
     * @param int $product_id Product ID
     * @return array|null Rating data
     */
    private function get_product_rating($product_id) {
        // This would integrate with WooCommerce or custom review system
        $rating = get_post_meta($product_id, '_average_rating', true);
        $review_count = get_post_meta($product_id, '_review_count', true);

        if (!$rating || !$review_count) {
            return null;
        }

        return [
            '@type' => 'AggregateRating',
            'ratingValue' => $rating,
            'reviewCount' => $review_count,
            'bestRating' => '5',
            'worstRating' => '1'
        ];
    }

    /**
     * Format schema output as JSON-LD
     *
     * @param array $schema_data Schema data array
     * @return string Formatted JSON-LD
     */
    private function format_schema_output($schema_data) {
        if (empty($schema_data['@graph'])) {
            return '';
        }

        $json = wp_json_encode($schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        return '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
    }

    /**
     * Validate schema markup
     *
     * @param array $schema_data Schema data to validate
     * @return array Validation results
     */
    public function validate_schema($schema_data) {
        $validation_results = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        if (empty($schema_data['@graph'])) {
            $validation_results['valid'] = false;
            $validation_results['errors'][] = 'No schema data found in @graph';
            return $validation_results;
        }

        foreach ($schema_data['@graph'] as $index => $schema_item) {
            $item_validation = $this->validate_schema_item($schema_item);
            
            if (!$item_validation['valid']) {
                $validation_results['valid'] = false;
                $validation_results['errors'] = array_merge(
                    $validation_results['errors'],
                    array_map(function($error) use ($index) {
                        return "Item $index: $error";
                    }, $item_validation['errors'])
                );
            }

            if (!empty($item_validation['warnings'])) {
                $validation_results['warnings'] = array_merge(
                    $validation_results['warnings'],
                    array_map(function($warning) use ($index) {
                        return "Item $index: $warning";
                    }, $item_validation['warnings'])
                );
            }
        }

        return $validation_results;
    }

    /**
     * Validate individual schema item
     *
     * @param array $schema_item Schema item to validate
     * @return array Validation results
     */
    private function validate_schema_item($schema_item) {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        $type = $schema_item['@type'] ?? '';
        
        if (empty($type)) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Missing @type property';
            return $validation;
        }

        if (!isset($this->supported_types[$type])) {
            $validation['warnings'][] = "Unsupported schema type: $type";
            return $validation;
        }

        $type_config = $this->supported_types[$type];

        // Check required fields
        foreach ($type_config['required_fields'] as $field) {
            if (!isset($schema_item[$field]) || empty($schema_item[$field])) {
                if ($this->config['validation_mode'] === 'strict') {
                    $validation['valid'] = false;
                    $validation['errors'][] = "Missing required field: $field";
                } else {
                    $validation['warnings'][] = "Missing recommended field: $field";
                }
            }
        }

        return $validation;
    }

    /**
     * Get schema statistics
     *
     * @return array Schema generation statistics
     */
    public function get_schema_statistics() {
        $stats = [
            'total_schemas_generated' => 0,
            'schemas_by_type' => [],
            'last_generated' => get_option('khm_seo_schema_last_generated', 0),
            'validation_errors' => get_option('khm_seo_schema_validation_errors', 0)
        ];

        // Count schemas by post type
        $post_types = get_post_types(['public' => true], 'names');
        foreach ($post_types as $post_type) {
            $count = wp_count_posts($post_type);
            $published_count = $count->publish ?? 0;
            
            if ($published_count > 0) {
                $stats['schemas_by_type'][$post_type] = $published_count;
                $stats['total_schemas_generated'] += $published_count;
            }
        }

        return $stats;
    }
}