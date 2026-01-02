<?php
/**
 * Sitemap Manager - Sitemap routing, caching, and search engine notifications
 * 
 * Manages sitemap requests, handles automatic regeneration,
 * integrates with WordPress rewrite rules, and notifies search engines.
 * 
 * @package KHM_SEO\Sitemap
 * @since 2.1.0
 */

namespace KHM_SEO\Sitemap;

/**
 * Sitemap Manager Class
 */
class SitemapManager {
    /**
     * @var SitemapGenerator Sitemap generator instance
     */
    private $generator;

    /**
     * @var array Configuration settings
     */
    private $settings;

    /**
     * @var array Search engines to ping
     */
    private $search_engines;

    /**
     * Constructor
     *
     * @param SitemapGenerator $generator Sitemap generator
     */
    public function __construct(SitemapGenerator $generator) {
        $this->generator = $generator;
        $this->init_settings();
        $this->init_search_engines();
        $this->init_hooks();
    }

    /**
     * Initialize settings
     */
    private function init_settings() {
        $this->settings = wp_parse_args(get_option('khm_seo_sitemap_settings', []), [
            'enable_sitemap' => true,
            'auto_regenerate' => true,
            'ping_search_engines' => true,
            'regenerate_on_post_save' => true,
            'regenerate_on_term_save' => true,
            'max_age' => 86400, // 24 hours
            'gzip_compression' => true,
            'cache_control' => true
        ]);
    }

    /**
     * Initialize search engines for pinging
     */
    private function init_search_engines() {
        $this->search_engines = [
            'google' => [
                'name' => 'Google',
                'ping_url' => 'https://www.google.com/ping?sitemap=',
                'enabled' => true
            ],
            'bing' => [
                'name' => 'Bing',
                'ping_url' => 'https://www.bing.com/ping?sitemap=',
                'enabled' => true
            ],
            'yandex' => [
                'name' => 'Yandex',
                'ping_url' => 'https://webmaster.yandex.com/ping?sitemap=',
                'enabled' => false
            ],
            'baidu' => [
                'name' => 'Baidu',
                'ping_url' => 'https://ping.baidu.com/ping/RPC2',
                'enabled' => false
            ]
        ];

        // Apply user settings
        $engine_settings = get_option('khm_seo_search_engine_settings', []);
        foreach ($engine_settings as $engine => $enabled) {
            if (isset($this->search_engines[$engine])) {
                $this->search_engines[$engine]['enabled'] = $enabled;
            }
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        if (!$this->settings['enable_sitemap']) {
            return;
        }

        // Rewrite rules
        add_action('init', [$this, 'add_rewrite_rules'], 1);
        add_filter('query_vars', [$this, 'add_query_vars']);
        
        // Template redirect
        add_action('template_redirect', [$this, 'handle_sitemap_request'], 1);
        
        // Auto-regeneration hooks
        if ($this->settings['auto_regenerate']) {
            $this->init_regeneration_hooks();
        }
        
        // Admin hooks
        add_action('admin_init', [$this, 'maybe_flush_rewrite_rules']);
        
        // Cron hooks
        add_action('khm_seo_regenerate_sitemap', [$this, 'regenerate_sitemap_background']);
        add_action('khm_seo_ping_search_engines', [$this, 'ping_search_engines']);
    }

    /**
     * Add sitemap rewrite rules
     */
    public function add_rewrite_rules() {
        // Main sitemap index
        add_rewrite_rule(
            '^sitemap\.xml$',
            'index.php?khm_sitemap=index',
            'top'
        );
        
        // Sitemap XSL stylesheet
        add_rewrite_rule(
            '^sitemap\.xsl$',
            'index.php?khm_sitemap_xsl=1',
            'top'
        );
        
        // Individual sitemaps
        add_rewrite_rule(
            '^sitemap_([^/]+?)\.xml$',
            'index.php?khm_sitemap=$matches[1]',
            'top'
        );
        
        // Paginated sitemaps
        add_rewrite_rule(
            '^sitemap_([^/]+?)_([0-9]+)\.xml$',
            'index.php?khm_sitemap=$matches[1]&khm_sitemap_page=$matches[2]',
            'top'
        );
    }

    /**
     * Add query variables
     *
     * @param array $vars Query variables
     * @return array Modified query variables
     */
    public function add_query_vars($vars) {
        $vars[] = 'khm_sitemap';
        $vars[] = 'khm_sitemap_page';
        $vars[] = 'khm_sitemap_xsl';
        return $vars;
    }

    /**
     * Handle sitemap requests
     */
    public function handle_sitemap_request() {
        // Check if this is a sitemap request
        $sitemap_type = get_query_var('khm_sitemap');
        $sitemap_page = get_query_var('khm_sitemap_page', 1);
        $is_xsl = get_query_var('khm_sitemap_xsl');
        
        if ($is_xsl) {
            $this->serve_xsl_stylesheet();
            return;
        }
        
        if (!$sitemap_type) {
            return;
        }
        
        // Serve sitemap
        $this->serve_sitemap($sitemap_type, (int) $sitemap_page);
    }

    /**
     * Serve sitemap content
     *
     * @param string $type Sitemap type
     * @param int $page Page number
     */
    private function serve_sitemap($type, $page = 1) {
        // Set headers
        $this->set_sitemap_headers();
        
        // Generate sitemap content
        $content = $this->generate_sitemap_content($type, $page);
        
        if (empty($content)) {
            $this->serve_404();
            return;
        }
        
        // Apply compression if enabled
        if ($this->settings['gzip_compression'] && $this->supports_gzip()) {
            $content = gzencode($content, 9);
            header('Content-Encoding: gzip');
        }
        
        // Output content
        echo $content;
        exit;
    }

    /**
     * Generate sitemap content
     *
     * @param string $type Sitemap type
     * @param int $page Page number
     * @return string Sitemap content
     */
    private function generate_sitemap_content($type, $page = 1) {
        switch ($type) {
            case 'index':
                return $this->generator->generate_sitemap_index();
            
            case 'post':
            case 'page':
                return $this->generator->generate_post_sitemap($type, $page);
            
            case 'category':
            case 'post_tag':
                return $this->generator->generate_taxonomy_sitemap($type);
            
            case 'author':
                return $this->generator->generate_author_sitemap();
            
            case 'images':
                return $this->generator->generate_image_sitemap();
            
            default:
                // Check if it's a custom post type
                if (post_type_exists($type)) {
                    return $this->generator->generate_post_sitemap($type, $page);
                }
                
                // Check if it's a custom taxonomy
                if (taxonomy_exists($type)) {
                    return $this->generator->generate_taxonomy_sitemap($type);
                }
                
                return '';
        }
    }

    /**
     * Set sitemap headers
     */
    private function set_sitemap_headers() {
        // Set sitemap headers
        header('Content-Type: application/xml; charset=UTF-8', true);
        
        if ($this->settings['cache_control']) {
            header('Cache-Control: public, max-age=' . $this->settings['max_age']);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $this->settings['max_age']) . ' GMT');
        }
        
        header('X-Robots-Tag: noindex, follow', true);
    }

    /**
     * Serve XSL stylesheet
     */
    private function serve_xsl_stylesheet() {
        header('Content-Type: text/xsl; charset=UTF-8', true);
        
        $xsl_content = $this->get_sitemap_xsl_content();
        echo $xsl_content;
        exit;
    }

    /**
     * Get XSL stylesheet content
     *
     * @return string XSL content
     */
    private function get_sitemap_xsl_content() {
        return '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
    <xsl:template match="/">
        <html>
        <head>
            <title>XML Sitemap</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
                th { background: #f2f2f2; }
            </style>
        </head>
        <body>
            <h1>XML Sitemap</h1>
            <table>
                <tr><th>URL</th><th>Last Modified</th><th>Priority</th></tr>
                <xsl:for-each select="//url">
                    <tr>
                        <td><xsl:value-of select="loc"/></td>
                        <td><xsl:value-of select="lastmod"/></td>
                        <td><xsl:value-of select="priority"/></td>
                    </tr>
                </xsl:for-each>
            </table>
        </body>
        </html>
    </xsl:template>
</xsl:stylesheet>';
    }

    /**
     * Serve 404 error
     */
    private function serve_404() {
        status_header(404);
        exit;
    }

    /**
     * Initialize regeneration hooks
     */
    private function init_regeneration_hooks() {
        // Post save/delete
        if ($this->settings['regenerate_on_post_save']) {
            add_action('save_post', [$this, 'maybe_regenerate_on_post_save'], 10, 2);
            add_action('delete_post', [$this, 'regenerate_sitemap']);
        }
        
        // Term save/delete
        if ($this->settings['regenerate_on_term_save']) {
            add_action('created_term', [$this, 'regenerate_sitemap']);
            add_action('edited_term', [$this, 'regenerate_sitemap']);
        }
    }

    /**
     * Maybe regenerate on post save
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     */
    public function maybe_regenerate_on_post_save($post_id, $post) {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Only regenerate for published posts
        if ($post->post_status === 'publish') {
            $this->regenerate_sitemap($post->post_type);
        }
    }

    /**
     * Regenerate sitemap
     *
     * @param string $type Optional specific type to regenerate
     */
    public function regenerate_sitemap($type = null) {
        // Clear relevant cache
        $this->generator->clear_cache($type);
        
        // Schedule background regeneration
        wp_schedule_single_event(time(), 'khm_seo_regenerate_sitemap', [$type]);
        
        // Ping search engines if enabled
        if ($this->settings['ping_search_engines']) {
            $this->schedule_search_engine_ping();
        }
    }

    /**
     * Background sitemap regeneration
     *
     * @param string $type Optional specific type to regenerate
     */
    public function regenerate_sitemap_background($type = null) {
        // Pre-generate common sitemaps to warm cache
        $this->generator->generate_sitemap_index();
        
        if (!$type || $type === 'post') {
            $this->generator->generate_post_sitemap('post', 1);
        }
        
        if (!$type || $type === 'page') {
            $this->generator->generate_post_sitemap('page', 1);
        }
        
        // Update last generated timestamp
        update_option('khm_seo_sitemap_last_generated', time());
    }

    /**
     * Schedule search engine ping
     */
    private function schedule_search_engine_ping() {
        if (!wp_next_scheduled('khm_seo_ping_search_engines')) {
            wp_schedule_single_event(time() + 60, 'khm_seo_ping_search_engines');
        }
    }

    /**
     * Ping search engines
     */
    public function ping_search_engines() {
        $sitemap_url = home_url('/sitemap.xml');
        
        foreach ($this->search_engines as $engine => $config) {
            if (!$config['enabled']) {
                continue;
            }
            
            $ping_url = $config['ping_url'] . urlencode($sitemap_url);
            
            $response = wp_remote_get($ping_url, [
                'timeout' => 30,
                'user-agent' => 'KHM SEO Plugin'
            ]);
            
            $this->log_ping_result($engine, $response);
        }
    }

    /**
     * Log ping result
     *
     * @param string $engine Search engine
     * @param array|\WP_Error $response HTTP response
     */
    private function log_ping_result($engine, $response) {
        $ping_log = get_option('khm_seo_ping_log', []);
        
        $result = [
            'engine' => $engine,
            'timestamp' => time(),
            'success' => !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200,
            'response_code' => !is_wp_error($response) ? wp_remote_retrieve_response_code($response) : 0,
            'error' => is_wp_error($response) ? $response->get_error_message() : ''
        ];
        
        $ping_log[] = $result;
        $ping_log = array_slice($ping_log, -50);
        
        update_option('khm_seo_ping_log', $ping_log);
    }

    /**
     * Check if gzip compression is supported
     *
     * @return bool Whether gzip is supported
     */
    private function supports_gzip() {
        return function_exists('gzencode') && 
               isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
               strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
    }

    /**
     * Maybe flush rewrite rules
     */
    public function maybe_flush_rewrite_rules() {
        if (get_option('khm_seo_sitemap_rewrite_rules_flushed') !== '1') {
            flush_rewrite_rules(false);
            update_option('khm_seo_sitemap_rewrite_rules_flushed', '1');
        }
    }

    /**
     * Get sitemap statistics
     *
     * @return array Sitemap statistics
     */
    public function get_sitemap_statistics() {
        $stats = [
            'total_urls' => 0,
            'post_types' => [],
            'taxonomies' => [],
            'last_generated' => get_option('khm_seo_sitemap_last_generated'),
            'ping_history' => get_option('khm_seo_ping_log', [])
        ];
        
        // Get post type counts
        $post_types = get_post_types(['public' => true], 'objects');
        foreach ($post_types as $post_type) {
            $count = wp_count_posts($post_type->name);
            $stats['post_types'][$post_type->name] = [
                'label' => $post_type->label,
                'count' => $count->publish ?? 0
            ];
            $stats['total_urls'] += $count->publish ?? 0;
        }
        
        return $stats;
    }

    /**
     * Test sitemap accessibility
     *
     * @return array Test results
     */
    public function test_sitemap_accessibility() {
        $tests = [
            'index' => ['url' => home_url('/sitemap.xml'), 'status' => null]
        ];
        
        foreach ($tests as $type => &$test) {
            $response = wp_remote_get($test['url'], ['timeout' => 10]);
            
            if (is_wp_error($response)) {
                $test['status'] = 'error';
                $test['message'] = $response->get_error_message();
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $test['status'] = $code === 200 ? 'success' : 'warning';
                $test['response_code'] = $code;
            }
        }
        
        return $tests;
    }
}
