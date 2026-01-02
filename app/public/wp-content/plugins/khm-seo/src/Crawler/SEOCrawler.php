<?php

namespace KHM_SEO\Crawler;

use KHM_SEO\PageSpeed\PSIManager;
use WP_Error;

/**
 * Technical SEO Crawler
 * 
 * Intelligent web crawler for comprehensive technical SEO analysis
 * 
 * Features:
 * - Complete site crawling with configurable depth
 * - Meta tags and schema markup analysis
 * - Internal linking structure analysis
 * - Redirect chains and broken link detection
 * - Page speed and mobile-friendliness assessment
 * - Content quality and SEO optimization scoring
 * - Automated issue detection and reporting
 * - Background processing with queue management
 * 
 * @package KHM_SEO\Crawler
 * @since 1.0.0
 */
class SEOCrawler {

    /**
     * PSI Manager instance
     */
    private $psi_manager;

    /**
     * Crawler configuration
     */
    private $config = [
        'max_depth' => 5,
        'max_pages' => 1000,
        'delay_between_requests' => 1, // seconds
        'timeout' => 30,
        'user_agent' => 'KHM-SEO-Crawler/1.0 (+https://khm-seo.com/crawler)',
        'respect_robots_txt' => true,
        'follow_redirects' => true,
        'max_redirects' => 5,
        'concurrent_requests' => 3
    ];

    /**
     * Crawl queue and tracking
     */
    private $crawl_queue = [];
    private $crawled_urls = [];
    private $failed_urls = [];
    private $crawl_session_id;

    /**
     * Analysis patterns and rules
     */
    private $seo_rules = [
        'title_min_length' => 30,
        'title_max_length' => 60,
        'meta_description_min_length' => 120,
        'meta_description_max_length' => 160,
        'h1_required' => true,
        'h1_max_count' => 1,
        'min_content_length' => 300,
        'max_load_time' => 3.0,
        'required_schema_types' => ['WebPage', 'Organization']
    ];

    /**
     * Initialize SEO Crawler
     */
    public function __construct() {
        $this->psi_manager = new PSIManager();
        $this->crawl_session_id = uniqid('crawl_', true);
        
        add_action('init', [$this, 'init_hooks']);
        add_action('wp_loaded', [$this, 'maybe_resume_crawl']);
    }

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_seo_start_crawl', [$this, 'ajax_start_crawl']);
        add_action('wp_ajax_seo_get_crawl_status', [$this, 'ajax_get_crawl_status']);
        add_action('wp_ajax_seo_pause_crawl', [$this, 'ajax_pause_crawl']);
        add_action('wp_ajax_seo_resume_crawl', [$this, 'ajax_resume_crawl']);
        add_action('wp_ajax_seo_get_crawl_results', [$this, 'ajax_get_crawl_results']);

        // Background processing
        add_action('seo_crawler_process_queue', [$this, 'process_crawl_queue']);
        add_action('seo_crawler_analyze_page', [$this, 'analyze_single_page'], 10, 2);

        // Schedule cleanup
        if (!wp_next_scheduled('seo_crawler_cleanup')) {
            wp_schedule_event(time(), 'daily', 'seo_crawler_cleanup');
        }
        add_action('seo_crawler_cleanup', [$this, 'cleanup_old_crawls']);
    }

    /**
     * Start comprehensive site crawl
     */
    public function start_crawl($start_url, $options = []) {
        // Validate start URL
        if (!$this->validate_url($start_url)) {
            return new \WP_Error('invalid_url', 'Invalid starting URL provided');
        }

        // Merge options with defaults
        $this->config = array_merge($this->config, $options);

        // Initialize crawl session
        $this->initialize_crawl_session($start_url);

        // Parse robots.txt if enabled
        if ($this->config['respect_robots_txt']) {
            $this->parse_robots_txt($start_url);
        }

        // Add start URL to queue
        $this->add_to_queue($start_url, 0);

        // Start background processing
        wp_schedule_single_event(time() + 1, 'seo_crawler_process_queue');

        return [
            'session_id' => $this->crawl_session_id,
            'start_url' => $start_url,
            'config' => $this->config,
            'status' => 'started'
        ];
    }

    /**
     * Process crawl queue in background
     */
    public function process_crawl_queue() {
        $session = $this->get_active_crawl_session();
        if (!$session) {
            return;
        }

        $this->crawl_session_id = $session['session_id'];
        $processed = 0;

        while (!empty($this->crawl_queue) && $processed < $this->config['concurrent_requests']) {
            $queue_item = array_shift($this->crawl_queue);
            
            if ($this->should_crawl_url($queue_item['url'], $queue_item['depth'])) {
                $this->crawl_page($queue_item['url'], $queue_item['depth']);
                $processed++;
                
                // Delay between requests
                if (!empty($this->crawl_queue)) {
                    sleep($this->config['delay_between_requests']);
                }
            }
        }

        // Update crawl session
        $this->update_crawl_session();

        // Schedule next batch if queue not empty
        if (!empty($this->crawl_queue)) {
            wp_schedule_single_event(time() + 5, 'seo_crawler_process_queue');
        } else {
            $this->complete_crawl_session();
        }
    }

    /**
     * Crawl and analyze individual page
     */
    public function crawl_page($url, $depth = 0) {
        if (in_array($url, $this->crawled_urls)) {
            return null;
        }

        $start_time = microtime(true);
        
        // Fetch page content
        $response = $this->fetch_page($url);
        
        if (is_wp_error($response)) {
            $this->failed_urls[] = [
                'url' => $url,
                'error' => $response->get_error_message(),
                'timestamp' => current_time('mysql')
            ];
            return $response;
        }

        $load_time = microtime(true) - $start_time;
        $content = $response['body'];
        $headers = $response['headers'];

        // Parse HTML content
        $dom = $this->parse_html($content);
        if (!$dom) {
            return new \WP_Error('parse_error', 'Failed to parse HTML content');
        }

        // Perform comprehensive analysis
        $analysis_data = [
            'url' => $url,
            'depth' => $depth,
            'load_time' => $load_time,
            'timestamp' => current_time('mysql'),
            'status_code' => $response['response_code'],
            'content_length' => strlen($content),
            'headers' => $headers,
            
            // Technical analysis
            'meta_analysis' => $this->analyze_meta_tags($dom),
            'heading_analysis' => $this->analyze_headings($dom),
            'content_analysis' => $this->analyze_content($dom, $content),
            'link_analysis' => $this->analyze_links($dom, $url),
            'schema_analysis' => $this->analyze_schema_markup($content),
            'performance_analysis' => $this->analyze_performance($url, $load_time),
            'mobile_analysis' => $this->analyze_mobile_friendliness($dom),
            'seo_score' => 0 // Will be calculated
        ];

        // Calculate SEO score
        $analysis_data['seo_score'] = $this->calculate_seo_score($analysis_data);

        // Store analysis results
        $this->store_crawl_data($analysis_data);

        // Add discovered links to queue
        $this->process_discovered_links($analysis_data['link_analysis']['internal_links'], $depth);

        $this->crawled_urls[] = $url;
        
        return $analysis_data;
    }

    /**
     * Analyze meta tags
     */
    private function analyze_meta_tags($dom) {
        $xpath = new \DOMXPath($dom);
        $analysis = [
            'title' => '',
            'meta_description' => '',
            'meta_keywords' => '',
            'canonical_url' => '',
            'og_tags' => [],
            'twitter_cards' => [],
            'issues' => []
        ];

        // Title analysis
        $title_nodes = $xpath->query('//title');
        if ($title_nodes->length > 0) {
            $analysis['title'] = trim($title_nodes->item(0)->textContent);
            
            $title_length = strlen($analysis['title']);
            if ($title_length < $this->seo_rules['title_min_length']) {
                $analysis['issues'][] = "Title too short ({$title_length} chars, recommended: {$this->seo_rules['title_min_length']}+)";
            } elseif ($title_length > $this->seo_rules['title_max_length']) {
                $analysis['issues'][] = "Title too long ({$title_length} chars, recommended: <{$this->seo_rules['title_max_length']})";
            }
        } else {
            $analysis['issues'][] = 'Missing title tag';
        }

        // Meta description
        $meta_desc = $xpath->query('//meta[@name="description"]');
        if ($meta_desc->length > 0) {
            $analysis['meta_description'] = trim($meta_desc->item(0)->getAttribute('content'));
            
            $desc_length = strlen($analysis['meta_description']);
            if ($desc_length < $this->seo_rules['meta_description_min_length']) {
                $analysis['issues'][] = "Meta description too short ({$desc_length} chars)";
            } elseif ($desc_length > $this->seo_rules['meta_description_max_length']) {
                $analysis['issues'][] = "Meta description too long ({$desc_length} chars)";
            }
        } else {
            $analysis['issues'][] = 'Missing meta description';
        }

        // Canonical URL
        $canonical = $xpath->query('//link[@rel="canonical"]');
        if ($canonical->length > 0) {
            $analysis['canonical_url'] = $canonical->item(0)->getAttribute('href');
        }

        // Open Graph tags
        $og_tags = $xpath->query('//meta[starts-with(@property, "og:")]');
        foreach ($og_tags as $tag) {
            $property = $tag->getAttribute('property');
            $content = $tag->getAttribute('content');
            $analysis['og_tags'][$property] = $content;
        }

        // Twitter Cards
        $twitter_tags = $xpath->query('//meta[starts-with(@name, "twitter:")]');
        foreach ($twitter_tags as $tag) {
            $name = $tag->getAttribute('name');
            $content = $tag->getAttribute('content');
            $analysis['twitter_cards'][$name] = $content;
        }

        return $analysis;
    }

    /**
     * Analyze heading structure
     */
    private function analyze_headings($dom) {
        $xpath = new \DOMXPath($dom);
        $analysis = [
            'h1_count' => 0,
            'h1_text' => [],
            'heading_structure' => [],
            'issues' => []
        ];

        // Analyze all headings
        for ($i = 1; $i <= 6; $i++) {
            $headings = $xpath->query("//h{$i}");
            $count = $headings->length;
            
            $analysis['heading_structure']["h{$i}"] = [
                'count' => $count,
                'texts' => []
            ];

            if ($i === 1) {
                $analysis['h1_count'] = $count;
                
                if ($count === 0 && $this->seo_rules['h1_required']) {
                    $analysis['issues'][] = 'Missing H1 heading';
                } elseif ($count > $this->seo_rules['h1_max_count']) {
                    $analysis['issues'][] = "Multiple H1 headings found ({$count})";
                }

                foreach ($headings as $h1) {
                    $text = trim($h1->textContent);
                    $analysis['h1_text'][] = $text;
                    $analysis['heading_structure']['h1']['texts'][] = $text;
                }
            } else {
                foreach ($headings as $heading) {
                    $analysis['heading_structure']["h{$i}"]['texts'][] = trim($heading->textContent);
                }
            }
        }

        return $analysis;
    }

    /**
     * Analyze page content
     */
    private function analyze_content($dom, $raw_content) {
        $xpath = new \DOMXPath($dom);
        
        // Remove script and style content
        $body_nodes = $xpath->query('//body');
        $text_content = '';
        
        if ($body_nodes->length > 0) {
            $body = $body_nodes->item(0);
            $text_content = $this->extract_text_content($body);
        }

        $word_count = str_word_count($text_content);
        $char_count = strlen($text_content);

        $analysis = [
            'word_count' => $word_count,
            'character_count' => $char_count,
            'text_content_ratio' => $char_count > 0 ? ($char_count / strlen($raw_content)) : 0,
            'images' => $this->analyze_images($xpath),
            'issues' => []
        ];

        // Content length check
        if ($word_count < $this->seo_rules['min_content_length']) {
            $analysis['issues'][] = "Insufficient content length ({$word_count} words, recommended: {$this->seo_rules['min_content_length']}+)";
        }

        return $analysis;
    }

    /**
     * Analyze links (internal and external)
     */
    private function analyze_links($dom, $base_url) {
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//a[@href]');
        
        $analysis = [
            'total_links' => $links->length,
            'internal_links' => [],
            'external_links' => [],
            'broken_links' => [],
            'redirect_chains' => [],
            'nofollow_count' => 0,
            'issues' => []
        ];

        $base_domain = parse_url($base_url, PHP_URL_HOST);

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $anchor_text = trim($link->textContent);
            $rel = $link->getAttribute('rel');
            
            // Skip empty or hash links
            if (empty($href) || $href === '#') {
                continue;
            }

            // Resolve relative URLs
            $absolute_url = $this->resolve_url($href, $base_url);
            $link_domain = parse_url($absolute_url, PHP_URL_HOST);

            $link_data = [
                'href' => $absolute_url,
                'anchor_text' => $anchor_text,
                'rel' => $rel,
                'is_nofollow' => strpos($rel, 'nofollow') !== false
            ];

            if ($link_data['is_nofollow']) {
                $analysis['nofollow_count']++;
            }

            // Categorize as internal or external
            if ($link_domain === $base_domain) {
                $analysis['internal_links'][] = $link_data;
            } else {
                $analysis['external_links'][] = $link_data;
            }
        }

        return $analysis;
    }

    /**
     * Analyze schema markup
     */
    private function analyze_schema_markup($content) {
        $analysis = [
            'json_ld_schemas' => [],
            'microdata_items' => [],
            'rdfa_properties' => [],
            'total_schemas' => 0,
            'issues' => []
        ];

        // JSON-LD Schema analysis
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $content, $matches)) {
            foreach ($matches[1] as $json) {
                $schema_data = json_decode(trim($json), true);
                if ($schema_data) {
                    $analysis['json_ld_schemas'][] = $schema_data;
                }
            }
        }

        // Microdata analysis
        if (preg_match_all('/itemtype=["\']([^"\']+)["\']/i', $content, $matches)) {
            $analysis['microdata_items'] = array_unique($matches[1]);
        }

        // RDFa analysis
        if (preg_match_all('/property=["\']([^"\']+)["\']/i', $content, $matches)) {
            $analysis['rdfa_properties'] = array_unique($matches[1]);
        }

        $analysis['total_schemas'] = count($analysis['json_ld_schemas']) + 
                                   count($analysis['microdata_items']) + 
                                   count($analysis['rdfa_properties']);

        // Check for required schema types
        $found_schemas = [];
        foreach ($analysis['json_ld_schemas'] as $schema) {
            if (isset($schema['@type'])) {
                $found_schemas[] = $schema['@type'];
            }
        }

        foreach ($this->seo_rules['required_schema_types'] as $required_type) {
            if (!in_array($required_type, $found_schemas)) {
                $analysis['issues'][] = "Missing recommended schema type: {$required_type}";
            }
        }

        return $analysis;
    }

    /**
     * Analyze performance metrics
     */
    private function analyze_performance($url, $load_time) {
        $analysis = [
            'load_time' => $load_time,
            'performance_grade' => 'A',
            'issues' => []
        ];

        // Basic load time analysis
        if ($load_time > $this->seo_rules['max_load_time']) {
            $analysis['issues'][] = "Slow load time ({$load_time}s, recommended: <{$this->seo_rules['max_load_time']}s)";
            $analysis['performance_grade'] = $load_time > 5 ? 'F' : ($load_time > 4 ? 'D' : 'C');
        }

        // Integration with PageSpeed Insights if available
        $psi_data = $this->get_cached_psi_data($url);
        if ($psi_data) {
            $analysis['psi_score'] = $psi_data['performance_score'];
            $analysis['core_web_vitals'] = $psi_data['core_web_vitals'];
        }

        return $analysis;
    }

    /**
     * Analyze mobile friendliness
     */
    private function analyze_mobile_friendliness($dom) {
        $xpath = new \DOMXPath($dom);
        
        $analysis = [
            'viewport_meta' => false,
            'responsive_design' => false,
            'mobile_issues' => []
        ];

        // Check for viewport meta tag
        $viewport = $xpath->query('//meta[@name="viewport"]');
        if ($viewport->length > 0) {
            $analysis['viewport_meta'] = true;
            $content = $viewport->item(0)->getAttribute('content');
            
            if (strpos($content, 'width=device-width') === false) {
                $analysis['mobile_issues'][] = 'Viewport meta tag does not include width=device-width';
            }
        } else {
            $analysis['mobile_issues'][] = 'Missing viewport meta tag';
        }

        // Basic responsive design checks
        $media_queries = preg_match('/@media[^{]+\{/', $dom->textContent);
        if ($media_queries) {
            $analysis['responsive_design'] = true;
        }

        return $analysis;
    }

    /**
     * Calculate overall SEO score
     */
    private function calculate_seo_score($analysis_data) {
        $score = 100;
        $penalties = [
            'meta_analysis' => 0,
            'heading_analysis' => 0,
            'content_analysis' => 0,
            'schema_analysis' => 0,
            'performance_analysis' => 0,
            'mobile_analysis' => 0
        ];

        // Meta tags penalties
        foreach ($analysis_data['meta_analysis']['issues'] as $issue) {
            if (strpos($issue, 'Missing title') !== false) $penalties['meta_analysis'] += 15;
            elseif (strpos($issue, 'Missing meta description') !== false) $penalties['meta_analysis'] += 10;
            elseif (strpos($issue, 'too short') !== false) $penalties['meta_analysis'] += 5;
            elseif (strpos($issue, 'too long') !== false) $penalties['meta_analysis'] += 3;
        }

        // Heading structure penalties
        foreach ($analysis_data['heading_analysis']['issues'] as $issue) {
            if (strpos($issue, 'Missing H1') !== false) $penalties['heading_analysis'] += 10;
            elseif (strpos($issue, 'Multiple H1') !== false) $penalties['heading_analysis'] += 5;
        }

        // Content penalties
        foreach ($analysis_data['content_analysis']['issues'] as $issue) {
            if (strpos($issue, 'Insufficient content') !== false) $penalties['content_analysis'] += 10;
        }

        // Performance penalties
        if ($analysis_data['performance_analysis']['load_time'] > $this->seo_rules['max_load_time']) {
            $penalties['performance_analysis'] += 15;
        }

        // Mobile penalties
        foreach ($analysis_data['mobile_analysis']['mobile_issues'] as $issue) {
            $penalties['mobile_analysis'] += 5;
        }

        // Apply penalties
        $total_penalty = array_sum($penalties);
        $score = max(0, $score - $total_penalty);

        return round($score);
    }

    /**
     * Utility methods
     */
    private function fetch_page($url) {
        $response = wp_remote_get($url, [
            'timeout' => $this->config['timeout'],
            'user-agent' => $this->config['user_agent'],
            'follow_redirects' => $this->config['follow_redirects'],
            'redirection' => $this->config['max_redirects']
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $headers = isset($response['headers']) ? $response['headers'] : [];
        $response_code = isset($response['response']['code']) ? $response['response']['code'] : 200;
        $body = isset($response['body']) ? $response['body'] : '';

        return [
            'body' => $body,
            'headers' => $headers,
            'response_code' => $response_code
        ];
    }

    private function parse_html($content) {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        return $dom;
    }

    private function validate_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function resolve_url($relative_url, $base_url) {
        if (parse_url($relative_url, PHP_URL_SCHEME) !== null) {
            return $relative_url;
        }

        $base_parts = parse_url($base_url);
        
        if ($relative_url[0] === '/') {
            return $base_parts['scheme'] . '://' . $base_parts['host'] . $relative_url;
        }

        $base_path = dirname($base_parts['path'] ?? '/');
        return $base_parts['scheme'] . '://' . $base_parts['host'] . $base_path . '/' . $relative_url;
    }

    private function extract_text_content($node) {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE && 
                     !in_array($child->nodeName, ['script', 'style', 'noscript'])) {
                $text .= $this->extract_text_content($child);
            }
        }
        return $text;
    }

    private function analyze_images($xpath) {
        $images = $xpath->query('//img');
        $analysis = [
            'total_count' => $images->length,
            'missing_alt' => 0,
            'missing_title' => 0,
            'oversized_images' => 0
        ];

        foreach ($images as $img) {
            if (!$img->hasAttribute('alt') || empty($img->getAttribute('alt'))) {
                $analysis['missing_alt']++;
            }
            if (!$img->hasAttribute('title')) {
                $analysis['missing_title']++;
            }
        }

        return $analysis;
    }

    /**
     * Database operations
     */
    private function store_crawl_data($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'gsc_crawl_data';
        
        $wpdb->insert($table_name, [
            'session_id' => $this->crawl_session_id,
            'url' => $data['url'],
            'depth' => $data['depth'],
            'status_code' => $data['status_code'],
            'load_time' => $data['load_time'],
            'content_length' => $data['content_length'],
            'seo_score' => $data['seo_score'],
            'meta_data' => json_encode($data['meta_analysis']),
            'heading_data' => json_encode($data['heading_analysis']),
            'content_data' => json_encode($data['content_analysis']),
            'link_data' => json_encode($data['link_analysis']),
            'schema_data' => json_encode($data['schema_analysis']),
            'performance_data' => json_encode($data['performance_analysis']),
            'mobile_data' => json_encode($data['mobile_analysis']),
            'created_at' => $data['timestamp']
        ]);
    }

    /**
     * AJAX handlers
     */
    public function ajax_start_crawl() {
        if (!wp_verify_nonce($_POST['nonce'], 'seo_crawler')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $start_url = isset($_POST['start_url']) ? filter_var($_POST['start_url'], FILTER_SANITIZE_URL) : '';
        $options = isset($_POST['options']) ? $_POST['options'] : [];

        $result = $this->start_crawl($start_url, $options);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Background processing methods
     */
    private function add_to_queue($url, $depth) {
        if (!in_array($url, array_column($this->crawl_queue, 'url'))) {
            $this->crawl_queue[] = [
                'url' => $url,
                'depth' => $depth,
                'added_at' => time()
            ];
        }
    }

    private function should_crawl_url($url, $depth) {
        return $depth < $this->config['max_depth'] && 
               count($this->crawled_urls) < $this->config['max_pages'] &&
               !in_array($url, $this->crawled_urls);
    }

    private function process_discovered_links($internal_links, $current_depth) {
        if ($current_depth >= $this->config['max_depth'] - 1) {
            return;
        }

        foreach ($internal_links as $link) {
            $this->add_to_queue($link['href'], $current_depth + 1);
        }
    }

    // Placeholder methods for session management
    private function initialize_crawl_session($start_url) {
        // Initialize session in database
    }

    private function get_active_crawl_session() {
        // Get active session from database
        return null;
    }

    private function update_crawl_session() {
        // Update session progress
    }

    private function complete_crawl_session() {
        // Mark session as complete
    }

    private function parse_robots_txt($url) {
        // Parse robots.txt file
    }

    private function get_cached_psi_data($url) {
        // Get cached PageSpeed Insights data
        return null;
    }

    private function maybe_resume_crawl() {
        // Resume interrupted crawl if needed
    }

    public function cleanup_old_crawls() {
        // Clean up old crawl data
    }
}