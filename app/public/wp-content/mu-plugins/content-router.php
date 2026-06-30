<?php
/**
 * Plugin Name: Centralized Content Registry Virtual Router
 * Description: Intercepts 404 routes on child sites and maps them to the central content registry database.
 * Version: 1.0.0
 * Author: Enterprise Platform Dev
 * File: wp-content/mu-plugins/content-router.php
 */

defined('ABSPATH') || exit;

class ContentRegistryVirtualRouter {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->base_prefix . 'content_registry';

        // Initialization Hooks
        add_action('init', [$this, 'add_virtual_rewrite_rules'], 1);
        add_action('template_redirect', [$this, 'intercept_and_render_virtual_post'], 5);
        
        // Asset Filters
        add_filter('the_content', [$this, 'rewrite_media_urls_to_main_site']);
    }

    /**
     * Registers high-priority rewrite rules for the network
     */
    public function add_virtual_rewrite_rules() {
        add_rewrite_rule('^([^/]+)/?$', 'index.php?virtual_slug=$matches[1]', 'top');
        add_filter('query_vars', function($vars) {
            $vars[] = 'virtual_slug';
            return $vars;
        });
    }

    /**
     * Intercepts requests on child sites and constructs fake global WP_Post structures
     */
    public function intercept_and_render_virtual_post() {
        if (is_admin()) return;

        $slug = get_query_var('virtual_slug');
        if (empty($slug)) {
            $slug = get_query_var('name'); // Fallback check for standard query loops
        }

        if (empty($slug)) return;

        $blog_id = get_current_blog_id();
        
        // Attempt to fetch from Object Cache first
        $cache_key = "article_registry_{$blog_id}_{$slug}";
        $article = wp_cache_get($cache_key, 'content_registry');

        if (false === $article) {
            global $wpdb;
            
            // Query registry for allocated live content matching the child site context
            $article = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE slug = %s AND target_blog_id = %d AND article_status = 'Live' LIMIT 1",
                $slug,
                $blog_id
            ));

            if ($article) {
                wp_cache_set($cache_key, $article, 'content_registry', HOUR_IN_SECONDS);
            }
        }

        if (!$article) return;

        // Reset the 404 flag explicitly
        global $wp_query, $post;
        $wp_query->is_404 = false;
        status_header(200);

        // Map and parse site-specific SEO metadata
        $seo = !empty($article->seo_metadata) ? json_decode($article->seo_metadata, true) : [];
        $slug = $article->slug; // Get slug from article for canonical URL
        $this->inject_seo_metadata($seo, $slug);

        // Combine raw body content with custom sponsor text blocks
        $full_content = $article->content_body;
        if (!empty($article->sponsor_commentary)) {
            $full_content .= '<div class="sponsor-commentary-wrap">' . $article->sponsor_commentary . '</div>';
        }

        // Mock object generation using negative ID configuration
        $mock_id = -absint($article->id);
        $post_data = [
            'ID'             => $mock_id,
            'post_author'    => 1,
            'post_date'      => $article->created_at,
            'post_date_gmt'  => $article->created_at,
            'post_title'     => $article->title,
            'post_content'   => $full_content,
            'post_excerpt'   => $article->excerpt ?? '',
            'post_status'    => 'publish',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
            'post_name'      => $article->slug,
            'post_type'      => 'post',
            'filter'         => 'raw'
        ];

        $post = new WP_Post((object)$post_data);
        
        // Establish the simulated global loop environment
        $wp_query->post = $post;
        $wp_query->posts = [$post];
        $wp_query->post_count = 1;
        $wp_query->queried_object = $post;
        $wp_query->queried_object_id = $mock_id;
        $wp_query->is_single = true;
        $wp_query->is_singular = true;

        // Force execution path directly into the custom layout script
        $template_path = get_template_directory() . '/single-virtual-article.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            include get_single_template();
        }
        exit;
    }

    /**
     * Intercepts HTML output stream to rewrite media asset paths back to Main Site
     */
    public function rewrite_media_urls_to_main_site($content) {
        if (get_current_blog_id() === 1) return $content;

        // Extract internal relative/local paths and force mapping to network main site domain
        $main_site_url = get_site_url(1);
        $current_site_url = get_site_url();

        // Targets paths containing uploads directory
        $pattern = '/src=["\']' . preg_quote($current_site_url, '/') . '([^"\']*\/wp-content\/uploads\/[^"\']+)["\']/i';
        $replacement = 'src="' . $main_site_url . '$1"';
        
        return preg_replace($pattern, $replacement, $content);
    }

    /**
     * Maps the schema's JSON metadata block to structural theme hooks and SEO frameworks
     */
    private function inject_seo_metadata($seo, $slug) {
        if (empty($seo)) return;

        // Canonical URL — point search engines to the main site
        add_action('wp_head', function() use ($slug) {
            $canonical_url = get_site_url(1) . '/' . $slug . '/';
            echo '<link rel="canonical" href="' . esc_url($canonical_url) . '" />' . "\n";
        }, 1);

        // Handle standard theme framework document title configurations
        add_filter('document_title_parts', function($title_parts) use ($seo) {
            if (!empty($seo['title'])) {
                $title_parts['title'] = $seo['title'];
            }
            return $title_parts;
        }, 999);

        // Core compatibility injection points for Yoast / RankMath filters
        add_filter('wpseo_title', function() use ($seo) { return $seo['title'] ?? ''; }, 999);
        add_filter('wpseo_metadesc', function() use ($seo) { return $seo['description'] ?? ''; }, 999);
        add_filter('rank_math/frontend/title', function() use ($seo) { return $seo['title'] ?? ''; }, 999);
        add_filter('rank_math/frontend/description', function() use ($seo) { return $seo['description'] ?? ''; }, 999);
    }
}

new ContentRegistryVirtualRouter();