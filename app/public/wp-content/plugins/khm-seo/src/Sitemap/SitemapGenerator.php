<?php
/**
 * Sitemap Generator - XML sitemap generation and management
 * 
 * Generates comprehensive XML sitemaps for WordPress content including
 * posts, pages, custom post types, taxonomies, and media files.
 * Handles automatic updates, caching, and search engine notifications.
 * 
 * @package KHM_SEO\Sitemap
 * @since 2.1.0
 */

namespace KHM_SEO\Sitemap;

/**
 * Sitemap Generator Class
 */
class SitemapGenerator {
    /**
     * @var Database Database instance
     */
    private $database;

    /**
     * @var array Sitemap configuration
     */
    private $config;

    /**
     * @var array Registered post types
     */
    private $post_types;

    /**
     * @var array Registered taxonomies
     */
    private $taxonomies;

    /**
     * @var string Sitemap cache key prefix
     */
    private $cache_prefix = 'khm_seo_sitemap_';

    /**
     * @var int Cache expiration time (1 hour)
     */
    private $cache_expiration = 3600;

    /**
     * Constructor
     *
     * @param mixed $database Optional database instance.
     */
    public function __construct($database = null) {
        $this->database = $database;
        $this->init_config();
        $this->init_post_types();
        $this->init_taxonomies();
    }

    /**
     * Initialize sitemap configuration
     */
    private function init_config() {
        $this->config = wp_parse_args(get_option('khm_seo_sitemap_settings', []), [
            'enable_sitemap' => true,
            'enable_index' => true,
            'enable_posts' => true,
            'enable_pages' => true,
            'enable_categories' => true,
            'enable_tags' => true,
            'enable_authors' => true,
            'enable_media' => false,
            'exclude_empty_terms' => true,
            'exclude_noindex' => true,
            'max_urls_per_sitemap' => 50000,
            'ping_search_engines' => true,
            'auto_regenerate' => true,
            'custom_post_types' => [],
            'custom_taxonomies' => [],
            'excluded_posts' => [],
            'excluded_terms' => [],
            'image_sitemap' => true,
            'video_sitemap' => false,
            'news_sitemap' => false,
            'priority_rules' => [
                'homepage' => 1.0,
                'posts' => 0.8,
                'pages' => 0.6,
                'categories' => 0.5,
                'tags' => 0.4,
                'archives' => 0.3
            ],
            'changefreq_rules' => [
                'homepage' => 'daily',
                'posts' => 'weekly',
                'pages' => 'monthly',
                'categories' => 'weekly',
                'tags' => 'monthly',
                'archives' => 'monthly'
            ]
        ]);
    }

    /**
     * Initialize enabled post types
     */
    private function init_post_types() {
        $this->post_types = [];
        
        // Built-in post types
        if ($this->config['enable_posts']) {
            $this->post_types[] = 'post';
        }
        
        if ($this->config['enable_pages']) {
            $this->post_types[] = 'page';
        }
        
        // Custom post types
        foreach ($this->config['custom_post_types'] as $post_type => $enabled) {
            if ($enabled && post_type_exists($post_type)) {
                $this->post_types[] = $post_type;
            }
        }
        
        // Filter out excluded post types
        $this->post_types = array_diff($this->post_types, ['revision', 'nav_menu_item', 'attachment']);
    }

    /**
     * Initialize enabled taxonomies
     */
    private function init_taxonomies() {
        $this->taxonomies = [];
        
        // Built-in taxonomies
        if ($this->config['enable_categories']) {
            $this->taxonomies[] = 'category';
        }
        
        if ($this->config['enable_tags']) {
            $this->taxonomies[] = 'post_tag';
        }
        
        // Custom taxonomies
        foreach ($this->config['custom_taxonomies'] as $taxonomy => $enabled) {
            if ($enabled && taxonomy_exists($taxonomy)) {
                $this->taxonomies[] = $taxonomy;
            }
        }
    }

    /**
     * Generate complete sitemap index
     *
     * @return string XML sitemap index
     */
    public function generate_sitemap_index() {
        $cache_key = $this->cache_prefix . 'index';
        $cached = get_transient($cache_key);
        
        if ($cached !== false && !$this->is_debug_mode()) {
            return $cached;
        }

        $sitemaps = [];
        
        // Add post type sitemaps
        foreach ($this->post_types as $post_type) {
            $count = $this->get_post_count($post_type);
            if ($count > 0) {
                $pages = ceil($count / $this->config['max_urls_per_sitemap']);
                
                for ($page = 1; $page <= $pages; $page++) {
                    $sitemaps[] = [
                        'loc' => $this->get_sitemap_url($post_type, $page),
                        'lastmod' => $this->get_post_type_lastmod($post_type)
                    ];
                }
            }
        }
        
        // Add taxonomy sitemaps
        foreach ($this->taxonomies as $taxonomy) {
            $count = $this->get_taxonomy_count($taxonomy);
            if ($count > 0) {
                $sitemaps[] = [
                    'loc' => $this->get_sitemap_url($taxonomy),
                    'lastmod' => $this->get_taxonomy_lastmod($taxonomy)
                ];
            }
        }
        
        // Add author sitemap
        if ($this->config['enable_authors']) {
            $author_count = $this->get_author_count();
            if ($author_count > 0) {
                $sitemaps[] = [
                    'loc' => $this->get_sitemap_url('author'),
                    'lastmod' => $this->get_author_lastmod()
                ];
            }
        }
        
        // Add image sitemap
        if ($this->config['image_sitemap']) {
            $sitemaps[] = [
                'loc' => $this->get_sitemap_url('images'),
                'lastmod' => $this->get_images_lastmod()
            ];
        }
        
        $xml = $this->build_sitemap_index_xml($sitemaps);
        
        set_transient($cache_key, $xml, $this->cache_expiration);
        
        return $xml;
    }

    /**
     * Generate post type sitemap
     *
     * @param string $post_type Post type
     * @param int $page Page number
     * @return string XML sitemap
     */
    public function generate_post_sitemap($post_type, $page = 1) {
        if (!in_array($post_type, $this->post_types)) {
            return '';
        }

        $cache_key = $this->cache_prefix . "posts_{$post_type}_{$page}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false && !$this->is_debug_mode()) {
            return $cached;
        }

        $offset = ($page - 1) * $this->config['max_urls_per_sitemap'];
        $posts = $this->get_posts_for_sitemap($post_type, $offset, $this->config['max_urls_per_sitemap']);
        
        $urls = [];
        foreach ($posts as $post) {
            $url_data = $this->build_post_url_data($post);
            if ($url_data) {
                $urls[] = $url_data;
            }
        }
        
        $xml = $this->build_sitemap_xml($urls);
        
        set_transient($cache_key, $xml, $this->cache_expiration);
        
        return $xml;
    }

    /**
     * Generate taxonomy sitemap
     *
     * @param string $taxonomy Taxonomy name
     * @return string XML sitemap
     */
    public function generate_taxonomy_sitemap($taxonomy) {
        if (!in_array($taxonomy, $this->taxonomies)) {
            return '';
        }

        $cache_key = $this->cache_prefix . "taxonomy_{$taxonomy}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false && !$this->is_debug_mode()) {
            return $cached;
        }

        $terms = $this->get_terms_for_sitemap($taxonomy);
        
        $urls = [];
        foreach ($terms as $term) {
            $url_data = $this->build_term_url_data($term);
            if ($url_data) {
                $urls[] = $url_data;
            }
        }
        
        $xml = $this->build_sitemap_xml($urls);
        
        set_transient($cache_key, $xml, $this->cache_expiration);
        
        return $xml;
    }

    /**
     * Generate author sitemap
     *
     * @return string XML sitemap
     */
    public function generate_author_sitemap() {
        $cache_key = $this->cache_prefix . 'authors';
        $cached = get_transient($cache_key);
        
        if ($cached !== false && !$this->is_debug_mode()) {
            return $cached;
        }

        $authors = $this->get_authors_for_sitemap();
        
        $urls = [];
        foreach ($authors as $author) {
            $url_data = $this->build_author_url_data($author);
            if ($url_data) {
                $urls[] = $url_data;
            }
        }
        
        $xml = $this->build_sitemap_xml($urls);
        
        set_transient($cache_key, $xml, $this->cache_expiration);
        
        return $xml;
    }

    /**
     * Generate image sitemap
     *
     * @return string XML sitemap
     */
    public function generate_image_sitemap() {
        $cache_key = $this->cache_prefix . 'images';
        $cached = get_transient($cache_key);
        
        if ($cached !== false && !$this->is_debug_mode()) {
            return $cached;
        }

        $images = $this->get_images_for_sitemap();
        
        $urls = [];
        foreach ($images as $image) {
            $url_data = $this->build_image_url_data($image);
            if ($url_data) {
                $urls[] = $url_data;
            }
        }
        
        $xml = $this->build_image_sitemap_xml($urls);
        
        set_transient($cache_key, $xml, $this->cache_expiration);
        
        return $xml;
    }

    /**
     * Build sitemap index XML
     *
     * @param array $sitemaps Sitemap entries
     * @return string XML content
     */
    private function build_sitemap_index_xml($sitemaps) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= "\n" . '<?xml-stylesheet type="text/xsl" href="' . $this->get_sitemap_xsl_url() . '"?>';
        $xml .= "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        foreach ($sitemaps as $sitemap) {
            $xml .= "\n\t<sitemap>";
            $xml .= "\n\t\t<loc>" . esc_url($sitemap['loc']) . '</loc>';
            
            if (!empty($sitemap['lastmod'])) {
                $xml .= "\n\t\t<lastmod>" . esc_xml($sitemap['lastmod']) . '</lastmod>';
            }
            
            $xml .= "\n\t</sitemap>";
        }
        
        $xml .= "\n</sitemapindex>";
        
        return $xml;
    }

    /**
     * Build standard sitemap XML
     *
     * @param array $urls URL entries
     * @return string XML content
     */
    private function build_sitemap_xml($urls) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= "\n" . '<?xml-stylesheet type="text/xsl" href="' . $this->get_sitemap_xsl_url() . '"?>';
        $xml .= "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        $xml .= ' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';
        
        foreach ($urls as $url) {
            $xml .= "\n\t<url>";
            $xml .= "\n\t\t<loc>" . esc_url($url['loc']) . '</loc>';
            
            if (!empty($url['lastmod'])) {
                $xml .= "\n\t\t<lastmod>" . esc_xml($url['lastmod']) . '</lastmod>';
            }
            
            if (!empty($url['changefreq'])) {
                $xml .= "\n\t\t<changefreq>" . esc_xml($url['changefreq']) . '</changefreq>';
            }
            
            if (!empty($url['priority'])) {
                $xml .= "\n\t\t<priority>" . esc_xml($url['priority']) . '</priority>';
            }
            
            // Add images
            if (!empty($url['images'])) {
                foreach ($url['images'] as $image) {
                    $xml .= "\n\t\t<image:image>";
                    $xml .= "\n\t\t\t<image:loc>" . esc_url($image['loc']) . '</image:loc>';
                    
                    if (!empty($image['title'])) {
                        $xml .= "\n\t\t\t<image:title>" . esc_xml($image['title']) . '</image:title>';
                    }
                    
                    if (!empty($image['caption'])) {
                        $xml .= "\n\t\t\t<image:caption>" . esc_xml($image['caption']) . '</image:caption>';
                    }
                    
                    $xml .= "\n\t\t</image:image>";
                }
            }
            
            $xml .= "\n\t</url>";
        }
        
        $xml .= "\n</urlset>";
        
        return $xml;
    }

    /**
     * Build image sitemap XML
     *
     * @param array $urls URL entries with images
     * @return string XML content
     */
    private function build_image_sitemap_xml($urls) {
        return $this->build_sitemap_xml($urls);
    }

    /**
     * Build post URL data
     *
     * @param \WP_Post $post Post object
     * @return array|null URL data
     */
    private function build_post_url_data($post) {
        // Skip if post should be excluded
        if ($this->should_exclude_post($post)) {
            return null;
        }

        $url_data = [
            'loc' => get_permalink($post),
            'lastmod' => $this->format_date($post->post_modified_gmt),
            'changefreq' => $this->get_post_changefreq($post),
            'priority' => $this->get_post_priority($post)
        ];

        // Add images if enabled
        if ($this->config['image_sitemap']) {
            $images = $this->extract_post_images($post);
            if (!empty($images)) {
                $url_data['images'] = $images;
            }
        }

        return $url_data;
    }

    /**
     * Build term URL data
     *
     * @param \WP_Term $term Term object
     * @return array|null URL data
     */
    private function build_term_url_data($term) {
        // Skip if term should be excluded
        if ($this->should_exclude_term($term)) {
            return null;
        }

        return [
            'loc' => get_term_link($term),
            'lastmod' => $this->get_term_lastmod($term),
            'changefreq' => $this->get_taxonomy_changefreq($term->taxonomy),
            'priority' => $this->get_taxonomy_priority($term->taxonomy)
        ];
    }

    /**
     * Build author URL data
     *
     * @param \WP_User $author Author object
     * @return array|null URL data
     */
    private function build_author_url_data($author) {
        if ($this->get_user_post_count($author->ID) === 0) {
            return null;
        }

        return [
            'loc' => get_author_posts_url($author->ID),
            'lastmod' => $this->get_author_lastmod($author->ID),
            'changefreq' => 'monthly',
            'priority' => '0.3'
        ];
    }

    /**
     * Build image URL data
     *
     * @param array $image Image data
     * @return array URL data
     */
    private function build_image_url_data($image) {
        return [
            'loc' => $image['post_url'],
            'lastmod' => $image['lastmod'],
            'changefreq' => 'monthly',
            'priority' => '0.4',
            'images' => [
                [
                    'loc' => $image['url'],
                    'title' => $image['title'],
                    'caption' => $image['caption']
                ]
            ]
        ];
    }

    /**
     * Get posts for sitemap
     *
     * @param string $post_type Post type
     * @param int $offset Offset
     * @param int $limit Limit
     * @return array Posts
     */
    private function get_posts_for_sitemap($post_type, $offset = 0, $limit = 50000) {
        global $wpdb;

        $sql = $wpdb->prepare("
            SELECT p.*
            FROM {$wpdb->posts} p
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND p.post_password = ''
            ORDER BY p.post_modified_gmt DESC
            LIMIT %d OFFSET %d
        ", $post_type, $limit, $offset);

        return $wpdb->get_results($sql);
    }

    /**
     * Get terms for sitemap
     *
     * @param string $taxonomy Taxonomy
     * @return array Terms
     */
    private function get_terms_for_sitemap($taxonomy) {
        $args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => $this->config['exclude_empty_terms'],
            'number' => $this->config['max_urls_per_sitemap']
        ];

        return get_terms($args);
    }

    /**
     * Get authors for sitemap
     *
     * @return array Authors
     */
    private function get_authors_for_sitemap() {
        return get_users([
            'who' => 'authors',
            'has_published_posts' => $this->post_types,
            'number' => $this->config['max_urls_per_sitemap']
        ]);
    }

    /**
     * Get images for sitemap
     *
     * @return array Images
     */
    private function get_images_for_sitemap() {
        global $wpdb;

        $sql = "
            SELECT p.ID, p.post_title, p.post_excerpt, p.post_parent, p.post_modified_gmt,
                   pm.meta_value as file_url,
                   parent.guid as post_url
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
            LEFT JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image%'
            AND p.post_status = 'inherit'
            AND parent.post_status = 'publish'
            ORDER BY p.post_modified_gmt DESC
            LIMIT {$this->config['max_urls_per_sitemap']}
        ";

        $attachments = $wpdb->get_results($sql);
        $images = [];

        foreach ($attachments as $attachment) {
            $images[] = [
                'url' => wp_get_attachment_url($attachment->ID),
                'title' => $attachment->post_title,
                'caption' => $attachment->post_excerpt,
                'post_url' => $attachment->post_url ?: get_permalink($attachment->post_parent),
                'lastmod' => $this->format_date($attachment->post_modified_gmt)
            ];
        }

        return $images;
    }

    /**
     * Clear sitemap cache
     *
     * @param string $type Optional specific type to clear
     */
    public function clear_cache($type = null) {
        global $wpdb;
        
        if ($type) {
            $pattern = $wpdb->esc_like($this->cache_prefix . $type) . '%';
        } else {
            $pattern = $wpdb->esc_like($this->cache_prefix) . '%';
        }
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            '_transient_' . $pattern
        ));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            '_transient_timeout_' . $pattern
        ));
    }

    // Utility methods for data retrieval and formatting
    private function get_post_count($post_type) {
        return (int) wp_count_posts($post_type)->publish;
    }

    private function get_taxonomy_count($taxonomy) {
        return wp_count_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => $this->config['exclude_empty_terms']
        ]);
    }

    private function get_author_count() {
        return count($this->get_authors_for_sitemap());
    }

    private function get_user_post_count($user_id) {
        return count_user_posts($user_id, $this->post_types);
    }

    private function format_date($date) {
        return gmdate('Y-m-d\TH:i:s+00:00', strtotime($date . ' UTC'));
    }

    private function get_sitemap_url($type, $page = null) {
        $base_url = home_url('/sitemap_');
        $url = $base_url . $type . '.xml';
        
        if ($page && $page > 1) {
            $url = $base_url . $type . '_' . $page . '.xml';
        }
        
        return $url;
    }

    private function get_sitemap_xsl_url() {
        return home_url('/sitemap.xsl');
    }

    private function is_debug_mode() {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    // Placeholder methods for configuration and filtering
    private function should_exclude_post($post) { return false; }
    private function should_exclude_term($term) { return false; }
    private function get_post_changefreq($post) { return $this->config['changefreq_rules']['posts']; }
    private function get_post_priority($post) { return $this->config['priority_rules']['posts']; }
    private function get_taxonomy_changefreq($taxonomy) { return $this->config['changefreq_rules']['categories']; }
    private function get_taxonomy_priority($taxonomy) { return $this->config['priority_rules']['categories']; }
    private function extract_post_images($post) { return []; }
    private function get_post_type_lastmod($post_type) { return gmdate('Y-m-d\TH:i:s+00:00'); }
    private function get_taxonomy_lastmod($taxonomy) { return gmdate('Y-m-d\TH:i:s+00:00'); }
    private function get_author_lastmod($author_id = null) { return gmdate('Y-m-d\TH:i:s+00:00'); }
    private function get_images_lastmod() { return gmdate('Y-m-d\TH:i:s+00:00'); }
    private function get_term_lastmod($term) { return gmdate('Y-m-d\TH:i:s+00:00'); }
}
