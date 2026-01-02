<?php
/**
 * Performance Monitor - Phase 7
 * 
 * Comprehensive performance tracking and monitoring system with Core Web Vitals,
 * real-time performance analysis, PageSpeed Insights integration, and optimization suggestions.
 * 
 * Features:
 * - Core Web Vitals tracking (LCP, FID, CLS)
 * - Real-time performance monitoring
 * - PageSpeed Insights API integration
 * - Performance scoring and recommendations
 * - Historical performance data storage
 * - Admin dashboard with visualizations
 * - Performance optimization suggestions
 * - Automated performance alerts
 * 
 * @package KHM_SEO\Performance
 * @since 7.0.0
 * @version 7.0.0
 */

namespace KHM_SEO\Performance;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Performance Monitor Class
 * Handles comprehensive performance tracking and optimization
 */
class PerformanceMonitor {
    
    /**
     * @var array Performance configuration
     */
    private $config;
    
    /**
     * @var string PageSpeed Insights API key
     */
    private $api_key;
    
    /**
     * @var array Core Web Vitals thresholds
     */
    private $cwv_thresholds;
    
    /**
     * @var array Performance metrics cache
     */
    private $metrics_cache = [];
    
    /**
     * @var int Cache duration in seconds
     */
    private $cache_duration = 3600; // 1 hour
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_config();
        $this->init_hooks();
        $this->setup_cwv_thresholds();
    }
    
    /**
     * Initialize configuration
     */
    private function init_config() {
        $this->config = [
            'enabled' => get_option('khm_performance_monitoring', true),
            'tracking_interval' => get_option('khm_performance_interval', 300), // 5 minutes
            'store_history' => get_option('khm_performance_history', true),
            'history_days' => get_option('khm_performance_history_days', 30),
            'alert_threshold' => get_option('khm_performance_alert_threshold', 50), // Performance score
            'enable_alerts' => get_option('khm_performance_alerts', true),
            'monitor_pages' => get_option('khm_performance_monitor_pages', ['home', 'posts', 'pages']),
            'pagespeed_api_key' => get_option('khm_pagespeed_api_key', ''),
            'real_user_monitoring' => get_option('khm_real_user_monitoring', true)
        ];
        
        $this->api_key = $this->config['pagespeed_api_key'];
    }
    
    /**
     * Setup Core Web Vitals thresholds
     */
    private function setup_cwv_thresholds() {
        $this->cwv_thresholds = [
            'lcp' => [
                'good' => 2500,      // 2.5 seconds
                'needs_improvement' => 4000, // 4.0 seconds
                'poor' => 4001       // > 4.0 seconds
            ],
            'fid' => [
                'good' => 100,       // 100 milliseconds
                'needs_improvement' => 300, // 300 milliseconds
                'poor' => 301        // > 300 milliseconds
            ],
            'cls' => [
                'good' => 0.1,       // 0.1
                'needs_improvement' => 0.25, // 0.25
                'poor' => 0.251      // > 0.25
            ],
            'fcp' => [
                'good' => 1800,      // 1.8 seconds
                'needs_improvement' => 3000, // 3.0 seconds
                'poor' => 3001       // > 3.0 seconds
            ],
            'ttfb' => [
                'good' => 600,       // 600 milliseconds
                'needs_improvement' => 1500, // 1.5 seconds
                'poor' => 1501       // > 1.5 seconds
            ]
        ];
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_khm_get_performance_data', [$this, 'ajax_get_performance_data']);
        add_action('wp_ajax_khm_run_performance_test', [$this, 'ajax_run_performance_test']);
        add_action('wp_ajax_khm_get_cwv_data', [$this, 'ajax_get_cwv_data']);
        add_action('wp_ajax_khm_save_performance_settings', [$this, 'ajax_save_settings']);
        
        // Frontend hooks for RUM (Real User Monitoring)
        if ($this->config['real_user_monitoring']) {
            add_action('wp_footer', [$this, 'inject_rum_script']);
            add_action('wp_ajax_khm_store_rum_data', [$this, 'ajax_store_rum_data']);
            add_action('wp_ajax_nopriv_khm_store_rum_data', [$this, 'ajax_store_rum_data']);
        }
        
        // Scheduled performance checks
        add_action('khm_performance_check', [$this, 'run_scheduled_check']);
        if (!wp_next_scheduled('khm_performance_check')) {
            wp_schedule_event(time(), 'hourly', 'khm_performance_check');
        }
        
        // Performance optimization hooks
        add_action('wp_enqueue_scripts', [$this, 'optimize_frontend_performance']);
        add_action('init', [$this, 'init_performance_optimizations']);
        
        // Database cleanup
        add_action('khm_performance_cleanup', [$this, 'cleanup_old_data']);
        if (!wp_next_scheduled('khm_performance_cleanup')) {
            wp_schedule_event(time(), 'daily', 'khm_performance_cleanup');
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'khm-seo',
            'Performance Monitor',
            'Performance',
            'manage_options',
            'khm-performance',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'khm-seo_page_khm-performance') {
            return;
        }
        
        // Performance monitor CSS
        wp_enqueue_style(
            'khm-performance-monitor',
            KHM_SEO_PLUGIN_URL . 'src/Performance/assets/css/performance-monitor.css',
            [],
            KHM_SEO_VERSION
        );
        
        // Performance monitor JavaScript
        wp_enqueue_script(
            'khm-performance-monitor',
            KHM_SEO_PLUGIN_URL . 'src/Performance/assets/js/performance-monitor.js',
            ['jquery', 'chart-js'],
            KHM_SEO_VERSION,
            true
        );
        
        // Chart.js library
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1',
            true
        );
        
        // Localize script data
        wp_localize_script('khm-performance-monitor', 'khmPerformance', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_performance_nonce'),
            'siteUrl' => home_url(),
            'config' => $this->config,
            'thresholds' => $this->cwv_thresholds,
            'strings' => [
                'loading' => __('Loading performance data...', 'khm-seo'),
                'error' => __('Error loading performance data', 'khm-seo'),
                'testing' => __('Running performance test...', 'khm-seo'),
                'test_complete' => __('Performance test complete', 'khm-seo'),
                'no_data' => __('No performance data available', 'khm-seo')
            ]
        ]);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        include KHM_SEO_PLUGIN_DIR . 'src/Performance/templates/admin-dashboard.php';
    }
    
    /**
     * Run PageSpeed Insights API test
     */
    public function run_pagespeed_test($url = null, $strategy = 'mobile') {
        if (!$url) {
            $url = home_url();
        }
        
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error' => 'PageSpeed Insights API key not configured'
            ];
        }
        
        // Check cache first
        $cache_key = 'khm_pagespeed_' . md5($url . $strategy);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result && is_array($cached_result)) {
            return $cached_result;
        }
        
        // Build API URL
        $api_url = add_query_arg([
            'url' => $url,
            'key' => $this->api_key,
            'strategy' => $strategy,
            'category' => 'performance',
            'fields' => 'lighthouseResult,originLoadingExperience'
        ], 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed');
        
        // Make API request
        $response = wp_remote_get($api_url, [
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'WordPress KHM SEO Plugin'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || isset($data['error'])) {
            return [
                'success' => false,
                'error' => $data['error']['message'] ?? 'API request failed'
            ];
        }
        
        // Process results
        $result = $this->process_pagespeed_results($data, $url, $strategy);
        
        // Cache results for 1 hour
        set_transient($cache_key, $result, $this->cache_duration);
        
        // Store in database
        $this->store_performance_data($result);
        
        return $result;
    }
    
    /**
     * Process PageSpeed Insights results
     */
    private function process_pagespeed_results($data, $url, $strategy) {
        $lighthouse = $data['lighthouseResult'] ?? [];
        $origin = $data['originLoadingExperience'] ?? [];
        $audits = $lighthouse['audits'] ?? [];
        
        // Extract Core Web Vitals
        $core_web_vitals = [
            'lcp' => $this->extract_metric_value($audits, 'largest-contentful-paint'),
            'fid' => $this->extract_metric_value($audits, 'first-input-delay'),
            'cls' => $this->extract_metric_value($audits, 'cumulative-layout-shift'),
            'fcp' => $this->extract_metric_value($audits, 'first-contentful-paint'),
            'ttfb' => $this->extract_metric_value($audits, 'server-response-time')
        ];
        
        // Extract performance score
        $performance_score = $lighthouse['categories']['performance']['score'] ?? 0;
        $performance_score = round($performance_score * 100);
        
        // Extract opportunities
        $opportunities = $this->extract_opportunities($audits);
        
        // Extract diagnostics
        $diagnostics = $this->extract_diagnostics($audits);
        
        // Generate recommendations
        $recommendations = $this->generate_recommendations($core_web_vitals, $opportunities, $diagnostics);
        
        return [
            'success' => true,
            'timestamp' => time(),
            'url' => $url,
            'strategy' => $strategy,
            'performance_score' => $performance_score,
            'core_web_vitals' => $core_web_vitals,
            'opportunities' => $opportunities,
            'diagnostics' => $diagnostics,
            'recommendations' => $recommendations,
            'origin_data' => $origin,
            'raw_data' => $lighthouse
        ];
    }
    
    /**
     * Extract metric value from audit
     */
    private function extract_metric_value($audits, $metric_id) {
        if (!isset($audits[$metric_id])) {
            return null;
        }
        
        $audit = $audits[$metric_id];
        
        // Handle different metric types
        switch ($metric_id) {
            case 'cumulative-layout-shift':
                return $audit['numericValue'] ?? null;
            case 'first-input-delay':
                return isset($audit['numericValue']) ? round($audit['numericValue']) : null;
            default:
                return isset($audit['numericValue']) ? round($audit['numericValue']) : null;
        }
    }
    
    /**
     * Extract optimization opportunities
     */
    private function extract_opportunities($audits) {
        $opportunities = [];
        
        $opportunity_audits = [
            'unused-css-rules',
            'unused-javascript',
            'modern-image-formats',
            'offscreen-images',
            'render-blocking-resources',
            'unminified-css',
            'unminified-javascript',
            'efficiently-encode-images',
            'serve-responsive-images',
            'uses-text-compression'
        ];
        
        foreach ($opportunity_audits as $audit_id) {
            if (isset($audits[$audit_id]) && 
                isset($audits[$audit_id]['details']) &&
                $audits[$audit_id]['score'] < 1) {
                
                $audit = $audits[$audit_id];
                $opportunities[] = [
                    'id' => $audit_id,
                    'title' => $audit['title'],
                    'description' => $audit['description'],
                    'score' => $audit['score'],
                    'impact' => $audit['details']['overallSavingsMs'] ?? 0,
                    'savings' => $audit['displayValue'] ?? '',
                    'items' => $audit['details']['items'] ?? []
                ];
            }
        }
        
        // Sort by potential impact
        usort($opportunities, function($a, $b) {
            return $b['impact'] <=> $a['impact'];
        });
        
        return $opportunities;
    }
    
    /**
     * Extract diagnostics
     */
    private function extract_diagnostics($audits) {
        $diagnostics = [];
        
        $diagnostic_audits = [
            'dom-size',
            'critical-request-chains',
            'uses-long-cache-ttl',
            'total-byte-weight',
            'uses-rel-preconnect',
            'font-display',
            'third-party-summary',
            'largest-contentful-paint-element',
            'layout-shift-elements'
        ];
        
        foreach ($diagnostic_audits as $audit_id) {
            if (isset($audits[$audit_id])) {
                $audit = $audits[$audit_id];
                $diagnostics[] = [
                    'id' => $audit_id,
                    'title' => $audit['title'],
                    'description' => $audit['description'],
                    'score' => $audit['score'] ?? null,
                    'value' => $audit['displayValue'] ?? '',
                    'details' => $audit['details'] ?? []
                ];
            }
        }
        
        return $diagnostics;
    }
    
    /**
     * Generate optimization recommendations
     */
    private function generate_recommendations($cwv, $opportunities, $diagnostics) {
        $recommendations = [];
        
        // Core Web Vitals recommendations
        foreach ($cwv as $metric => $value) {
            if ($value && isset($this->cwv_thresholds[$metric])) {
                $threshold = $this->cwv_thresholds[$metric];
                
                if ($value >= $threshold['poor']) {
                    $recommendations[] = [
                        'type' => 'cwv',
                        'priority' => 'high',
                        'metric' => $metric,
                        'current_value' => $value,
                        'target_value' => $threshold['good'],
                        'title' => $this->get_cwv_recommendation_title($metric),
                        'description' => $this->get_cwv_recommendation_description($metric),
                        'actions' => $this->get_cwv_actions($metric)
                    ];
                } elseif ($value >= $threshold['needs_improvement']) {
                    $recommendations[] = [
                        'type' => 'cwv',
                        'priority' => 'medium',
                        'metric' => $metric,
                        'current_value' => $value,
                        'target_value' => $threshold['good'],
                        'title' => $this->get_cwv_recommendation_title($metric),
                        'description' => $this->get_cwv_recommendation_description($metric),
                        'actions' => $this->get_cwv_actions($metric)
                    ];
                }
            }
        }
        
        // Opportunity-based recommendations
        $top_opportunities = array_slice($opportunities, 0, 5);
        foreach ($top_opportunities as $opportunity) {
            $recommendations[] = [
                'type' => 'opportunity',
                'priority' => $opportunity['impact'] > 1000 ? 'high' : 'medium',
                'title' => $opportunity['title'],
                'description' => $opportunity['description'],
                'potential_savings' => $opportunity['savings'],
                'actions' => $this->get_opportunity_actions($opportunity['id'])
            ];
        }
        
        // Sort by priority
        usort($recommendations, function($a, $b) {
            $priorities = ['high' => 3, 'medium' => 2, 'low' => 1];
            return $priorities[$b['priority']] <=> $priorities[$a['priority']];
        });
        
        return $recommendations;
    }
    
    /**
     * Get CWV recommendation title
     */
    private function get_cwv_recommendation_title($metric) {
        $titles = [
            'lcp' => 'Improve Largest Contentful Paint',
            'fid' => 'Improve First Input Delay',
            'cls' => 'Reduce Cumulative Layout Shift',
            'fcp' => 'Improve First Contentful Paint',
            'ttfb' => 'Reduce Time to First Byte'
        ];
        
        return $titles[$metric] ?? 'Improve ' . strtoupper($metric);
    }
    
    /**
     * Get CWV recommendation description
     */
    private function get_cwv_recommendation_description($metric) {
        $descriptions = [
            'lcp' => 'LCP measures loading performance. To improve LCP, optimize your largest content element.',
            'fid' => 'FID measures interactivity. To improve FID, reduce JavaScript execution time.',
            'cls' => 'CLS measures visual stability. To improve CLS, ensure elements don\'t shift during loading.',
            'fcp' => 'FCP measures when content first appears. Optimize resource loading to improve FCP.',
            'ttfb' => 'TTFB measures server response time. Optimize server performance and caching.'
        ];
        
        return $descriptions[$metric] ?? 'Optimize this Core Web Vital metric for better performance.';
    }
    
    /**
     * Get CWV improvement actions
     */
    private function get_cwv_actions($metric) {
        $actions = [
            'lcp' => [
                'Optimize images and use next-gen formats',
                'Implement lazy loading for images',
                'Remove or defer non-critical CSS',
                'Optimize server response times',
                'Use a Content Delivery Network (CDN)'
            ],
            'fid' => [
                'Minimize and defer JavaScript',
                'Remove unused JavaScript',
                'Use code splitting',
                'Optimize third-party scripts',
                'Use web workers for heavy computations'
            ],
            'cls' => [
                'Set explicit dimensions for images and videos',
                'Reserve space for dynamic content',
                'Avoid inserting content above existing content',
                'Use CSS aspect ratio for responsive media',
                'Preload fonts to prevent layout shifts'
            ],
            'fcp' => [
                'Optimize server response times',
                'Remove render-blocking resources',
                'Minify CSS and JavaScript',
                'Use efficient cache policies',
                'Optimize critical rendering path'
            ],
            'ttfb' => [
                'Optimize server configuration',
                'Use efficient database queries',
                'Implement server-side caching',
                'Use a CDN',
                'Optimize DNS lookups'
            ]
        ];
        
        return $actions[$metric] ?? [];
    }
    
    /**
     * Get opportunity-specific actions
     */
    private function get_opportunity_actions($opportunity_id) {
        $actions = [
            'unused-css-rules' => [
                'Remove unused CSS rules',
                'Use CSS purging tools',
                'Implement critical CSS',
                'Defer non-critical stylesheets'
            ],
            'unused-javascript' => [
                'Remove unused JavaScript code',
                'Use tree shaking for bundles',
                'Implement code splitting',
                'Defer non-critical scripts'
            ],
            'modern-image-formats' => [
                'Convert images to WebP format',
                'Use AVIF for supported browsers',
                'Implement responsive images',
                'Optimize image compression'
            ],
            'render-blocking-resources' => [
                'Inline critical CSS',
                'Defer non-critical CSS',
                'Use async/defer for scripts',
                'Optimize resource loading order'
            ]
        ];
        
        return $actions[$opportunity_id] ?? [
            'Review and implement the suggested optimization',
            'Test the impact on performance metrics',
            'Monitor for any negative effects'
        ];
    }
    
    /**
     * Store performance data in database
     */
    private function store_performance_data($data) {
        if (!$this->config['store_history']) {
            return;
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_performance_history';
        
        // Create table if it doesn't exist
        $this->create_performance_table();
        
        // Prepare data for storage
        $insert_data = [
            'timestamp' => $data['timestamp'],
            'url' => $data['url'],
            'strategy' => $data['strategy'],
            'performance_score' => $data['performance_score'],
            'lcp' => $data['core_web_vitals']['lcp'],
            'fid' => $data['core_web_vitals']['fid'],
            'cls' => $data['core_web_vitals']['cls'],
            'fcp' => $data['core_web_vitals']['fcp'],
            'ttfb' => $data['core_web_vitals']['ttfb'],
            'opportunities' => json_encode($data['opportunities']),
            'diagnostics' => json_encode($data['diagnostics']),
            'recommendations' => json_encode($data['recommendations'])
        ];
        
        // Insert data
        $wpdb->insert($table_name, $insert_data);
    }
    
    /**
     * Create performance history table
     */
    private function create_performance_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_performance_history';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            timestamp int(11) NOT NULL,
            url varchar(255) NOT NULL,
            strategy varchar(20) NOT NULL DEFAULT 'mobile',
            performance_score int(3) DEFAULT NULL,
            lcp int(11) DEFAULT NULL,
            fid int(11) DEFAULT NULL,
            cls decimal(5,3) DEFAULT NULL,
            fcp int(11) DEFAULT NULL,
            ttfb int(11) DEFAULT NULL,
            opportunities longtext DEFAULT NULL,
            diagnostics longtext DEFAULT NULL,
            recommendations longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY url (url),
            KEY strategy (strategy)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get historical performance data
     */
    public function get_performance_history($url = null, $days = 30, $strategy = 'mobile') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_performance_history';
        
        $where_conditions = [
            'strategy = %s',
            'timestamp >= %d'
        ];
        $where_values = [$strategy, time() - ($days * 24 * 60 * 60)];
        
        if ($url) {
            $where_conditions[] = 'url = %s';
            $where_values[] = $url;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY timestamp ASC";
        $prepared_query = $wpdb->prepare($query, ...$where_values);
        
        $results = $wpdb->get_results($prepared_query, ARRAY_A);
        
        // Process results for charting
        $processed = [];
        foreach ($results as $row) {
            $processed[] = [
                'timestamp' => $row['timestamp'],
                'date' => date('Y-m-d H:i', $row['timestamp']),
                'url' => $row['url'],
                'performance_score' => (int) $row['performance_score'],
                'lcp' => (int) $row['lcp'],
                'fid' => (int) $row['fid'],
                'cls' => (float) $row['cls'],
                'fcp' => (int) $row['fcp'],
                'ttfb' => (int) $row['ttfb']
            ];
        }
        
        return $processed;
    }
    
    /**
     * AJAX: Get performance data
     */
    public function ajax_get_performance_data() {
        check_ajax_referer('khm_performance_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $url = \sanitize_url($_POST['url'] ?? \home_url());
        $days = (int) ($_POST['days'] ?? 30);
        $strategy = sanitize_text_field($_POST['strategy'] ?? 'mobile');
        
        $history = $this->get_performance_history($url, $days, $strategy);
        
        wp_send_json_success([
            'history' => $history,
            'latest' => end($history) ?: null,
            'thresholds' => $this->cwv_thresholds
        ]);
    }
    
    /**
     * AJAX: Run performance test
     */
    public function ajax_run_performance_test() {
        check_ajax_referer('khm_performance_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $url = sanitize_url($_POST['url'] ?? home_url());
        $strategy = sanitize_text_field($_POST['strategy'] ?? 'mobile');
        
        $result = $this->run_pagespeed_test($url, $strategy);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * AJAX: Get Core Web Vitals data
     */
    public function ajax_get_cwv_data() {
        check_ajax_referer('khm_performance_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Get Real User Monitoring data
        $rum_data = $this->get_rum_data();
        
        // Get latest PageSpeed data
        $url = sanitize_url($_POST['url'] ?? home_url());
        $pagespeed_data = $this->get_latest_pagespeed_data($url);
        
        wp_send_json_success([
            'rum' => $rum_data,
            'pagespeed' => $pagespeed_data,
            'thresholds' => $this->cwv_thresholds
        ]);
    }
    
    /**
     * Get Real User Monitoring data
     */
    private function get_rum_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_rum_data';
        
        // Get average metrics from last 7 days
        $query = "
            SELECT 
                AVG(lcp) as avg_lcp,
                AVG(fid) as avg_fid, 
                AVG(cls) as avg_cls,
                AVG(fcp) as avg_fcp,
                COUNT(*) as sample_count
            FROM $table_name 
            WHERE timestamp >= %d
        ";
        
        $result = $wpdb->get_row($wpdb->prepare(
            $query, 
            time() - (7 * 24 * 60 * 60)
        ), ARRAY_A);
        
        return $result ?: [];
    }
    
    /**
     * Get latest PageSpeed data
     */
    private function get_latest_pagespeed_data($url) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_performance_history';
        
        $query = "SELECT * FROM $table_name WHERE url = %s ORDER BY timestamp DESC LIMIT 1";
        $result = $wpdb->get_row($wpdb->prepare($query, $url), ARRAY_A);
        
        if ($result) {
            return [
                'lcp' => (int) $result['lcp'],
                'fid' => (int) $result['fid'],
                'cls' => (float) $result['cls'],
                'fcp' => (int) $result['fcp'],
                'ttfb' => (int) $result['ttfb'],
                'performance_score' => (int) $result['performance_score'],
                'timestamp' => $result['timestamp']
            ];
        }
        
        return null;
    }
    
    /**
     * Inject Real User Monitoring script
     */
    public function inject_rum_script() {
        if (!$this->config['real_user_monitoring'] || is_admin()) {
            return;
        }
        
        ?>
        <script>
        (function() {
            // Core Web Vitals measurement
            var khmRUM = {
                data: {},
                
                // Measure LCP
                measureLCP: function() {
                    if ('PerformanceObserver' in window) {
                        var observer = new PerformanceObserver(function(list) {
                            var entries = list.getEntries();
                            var lastEntry = entries[entries.length - 1];
                            khmRUM.data.lcp = Math.round(lastEntry.startTime);
                        });
                        observer.observe({ entryTypes: ['largest-contentful-paint'] });
                    }
                },
                
                // Measure FID
                measureFID: function() {
                    if ('PerformanceObserver' in window) {
                        var observer = new PerformanceObserver(function(list) {
                            var entries = list.getEntries();
                            entries.forEach(function(entry) {
                                if (entry.name === 'first-input-delay') {
                                    khmRUM.data.fid = Math.round(entry.value);
                                }
                            });
                        });
                        observer.observe({ entryTypes: ['first-input'] });
                    }
                },
                
                // Measure CLS
                measureCLS: function() {
                    if ('PerformanceObserver' in window) {
                        var clsValue = 0;
                        var observer = new PerformanceObserver(function(list) {
                            var entries = list.getEntries();
                            entries.forEach(function(entry) {
                                if (!entry.hadRecentInput) {
                                    clsValue += entry.value;
                                }
                            });
                            khmRUM.data.cls = clsValue;
                        });
                        observer.observe({ entryTypes: ['layout-shift'] });
                    }
                },
                
                // Measure FCP
                measureFCP: function() {
                    if ('PerformanceObserver' in window) {
                        var observer = new PerformanceObserver(function(list) {
                            var entries = list.getEntries();
                            entries.forEach(function(entry) {
                                if (entry.name === 'first-contentful-paint') {
                                    khmRUM.data.fcp = Math.round(entry.startTime);
                                }
                            });
                        });
                        observer.observe({ entryTypes: ['paint'] });
                    }
                },
                
                // Send data to server
                sendData: function() {
                    if (Object.keys(khmRUM.data).length === 0) {
                        return;
                    }
                    
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    
                    var params = new URLSearchParams();
                    params.append('action', 'khm_store_rum_data');
                    params.append('nonce', '<?php echo wp_create_nonce('khm_rum_nonce'); ?>');
                    params.append('url', window.location.href);
                    params.append('data', JSON.stringify(khmRUM.data));
                    
                    xhr.send(params.toString());
                }
            };
            
            // Start measurements
            khmRUM.measureLCP();
            khmRUM.measureFID();
            khmRUM.measureCLS();
            khmRUM.measureFCP();
            
            // Send data after page is fully loaded
            window.addEventListener('load', function() {
                setTimeout(function() {
                    khmRUM.sendData();
                }, 5000); // Wait 5 seconds after load
            });
            
            // Send data before page unload
            window.addEventListener('beforeunload', function() {
                khmRUM.sendData();
            });
        })();
        </script>
        <?php
    }
    
    /**
     * AJAX: Store RUM data
     */
    public function ajax_store_rum_data() {
        check_ajax_referer('khm_rum_nonce', 'nonce');
        
        $url = sanitize_url($_POST['url'] ?? '');
        $data = json_decode(stripslashes($_POST['data'] ?? '{}'), true);
        
        if (!$url || !$data) {
            wp_die('Invalid data');
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_rum_data';
        
        // Create table if it doesn't exist
        $this->create_rum_table();
        
        // Store RUM data
        $insert_data = [
            'timestamp' => time(),
            'url' => $url,
            'lcp' => (int) ($data['lcp'] ?? 0),
            'fid' => (int) ($data['fid'] ?? 0),
            'cls' => (float) ($data['cls'] ?? 0),
            'fcp' => (int) ($data['fcp'] ?? 0),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        $wpdb->insert($table_name, $insert_data);
        
        wp_die('success');
    }
    
    /**
     * Create RUM data table
     */
    private function create_rum_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_rum_data';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            timestamp int(11) NOT NULL,
            url varchar(255) NOT NULL,
            lcp int(11) DEFAULT NULL,
            fid int(11) DEFAULT NULL,
            cls decimal(5,3) DEFAULT NULL,
            fcp int(11) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY url (url)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Run scheduled performance check
     */
    public function run_scheduled_check() {
        if (!$this->config['enabled']) {
            return;
        }
        
        // Test homepage
        $result = $this->run_pagespeed_test(home_url(), 'mobile');
        
        // Check if performance is below threshold
        if ($result['success'] && 
            $this->config['enable_alerts'] && 
            $result['performance_score'] < $this->config['alert_threshold']) {
            
            $this->send_performance_alert($result);
        }
        
        // Test other important pages
        $pages_to_test = $this->get_pages_to_monitor();
        foreach ($pages_to_test as $page_url) {
            $this->run_pagespeed_test($page_url, 'mobile');
        }
    }
    
    /**
     * Get pages to monitor
     */
    private function get_pages_to_monitor() {
        $pages = [];
        
        if (in_array('posts', $this->config['monitor_pages'])) {
            // Get latest posts
            $posts = get_posts(['numberposts' => 3, 'post_status' => 'publish']);
            foreach ($posts as $post) {
                $pages[] = get_permalink($post->ID);
            }
        }
        
        if (in_array('pages', $this->config['monitor_pages'])) {
            // Get important pages
            $static_pages = get_pages(['number' => 3, 'sort_column' => 'menu_order']);
            foreach ($static_pages as $page) {
                $pages[] = get_permalink($page->ID);
            }
        }
        
        return array_unique($pages);
    }
    
    /**
     * Send performance alert
     */
    private function send_performance_alert($result) {
        $admin_email = get_option('admin_email');
        
        $subject = 'Performance Alert: ' . get_bloginfo('name');
        
        $message = sprintf(
            "Performance alert for %s\n\n" .
            "URL: %s\n" .
            "Performance Score: %d/100\n" .
            "Timestamp: %s\n\n" .
            "Core Web Vitals:\n" .
            "- LCP: %dms\n" .
            "- FID: %dms\n" .
            "- CLS: %.3f\n\n" .
            "Please review the performance dashboard for detailed recommendations.",
            get_bloginfo('name'),
            $result['url'],
            $result['performance_score'],
            date('Y-m-d H:i:s', $result['timestamp']),
            $result['core_web_vitals']['lcp'] ?? 0,
            $result['core_web_vitals']['fid'] ?? 0,
            $result['core_web_vitals']['cls'] ?? 0
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Cleanup old performance data
     */
    public function cleanup_old_data() {
        if (!$this->config['store_history']) {
            return;
        }
        
        global $wpdb;
        
        $cutoff_time = time() - ($this->config['history_days'] * 24 * 60 * 60);
        
        // Clean performance history
        $performance_table = $wpdb->prefix . 'khm_performance_history';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $performance_table WHERE timestamp < %d",
            $cutoff_time
        ));
        
        // Clean RUM data (keep for 7 days max)
        $rum_table = $wpdb->prefix . 'khm_rum_data';
        $rum_cutoff = time() - (7 * 24 * 60 * 60);
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $rum_table WHERE timestamp < %d",
            $rum_cutoff
        ));
    }
    
    /**
     * Initialize performance optimizations
     */
    public function init_performance_optimizations() {
        // Add performance optimization features here
        // This could include:
        // - Lazy loading
        // - Resource minification
        // - Caching headers
        // - Database query optimization
        
        // Enable lazy loading for images
        add_filter('wp_lazy_loading_enabled', '__return_true');
        
        // Optimize database queries
        add_action('pre_get_posts', [$this, 'optimize_queries']);
    }
    
    /**
     * Optimize frontend performance
     */
    public function optimize_frontend_performance() {
        // Add resource hints
        add_action('wp_head', [$this, 'add_resource_hints']);
        
        // Defer non-critical CSS
        add_filter('style_loader_tag', [$this, 'defer_non_critical_css'], 10, 2);
        
        // Defer non-critical JavaScript
        add_filter('script_loader_tag', [$this, 'defer_non_critical_js'], 10, 2);
    }
    
    /**
     * Add resource hints
     */
    public function add_resource_hints() {
        // DNS prefetch for external resources
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//www.google-analytics.com">' . "\n";
        
        // Preconnect to critical external resources
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    }
    
    /**
     * Defer non-critical CSS
     */
    public function defer_non_critical_css($tag, $handle) {
        // List of non-critical CSS handles
        $non_critical = ['wp-block-library', 'wp-block-library-theme'];
        
        if (in_array($handle, $non_critical)) {
            return str_replace('rel="stylesheet"', 'rel="preload" as="style" onload="this.rel=\'stylesheet\'"', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Defer non-critical JavaScript
     */
    public function defer_non_critical_js($tag, $handle) {
        // Skip admin and jQuery
        if (is_admin() || $handle === 'jquery') {
            return $tag;
        }
        
        // List of critical scripts that shouldn't be deferred
        $critical_scripts = ['khm-performance-monitor'];
        
        if (!in_array($handle, $critical_scripts)) {
            return str_replace(' src', ' defer src', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Optimize database queries
     */
    public function optimize_queries($query) {
        if (!is_admin() && $query->is_main_query()) {
            if (is_home()) {
                $query->set('posts_per_page', 10);
                $query->set('no_found_rows', true);
            }
        }
    }
    
    /**
     * AJAX: Save performance settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('khm_performance_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $settings = $_POST['settings'] ?? [];
        
        // Save settings
        foreach ($settings as $key => $value) {
            $option_name = 'khm_' . sanitize_key($key);
            $sanitized_value = sanitize_text_field($value);
            update_option($option_name, $sanitized_value);
        }
        
        wp_send_json_success('Settings saved successfully');
    }
    
    /**
     * Get performance summary
     */
    public function get_performance_summary() {
        $latest_data = $this->get_performance_history(null, 1);
        
        if (empty($latest_data)) {
            return [
                'score' => null,
                'status' => 'No data',
                'last_check' => null,
                'recommendations_count' => 0
            ];
        }
        
        $latest = $latest_data[0];
        
        $status = 'Poor';
        if ($latest['performance_score'] >= 90) {
            $status = 'Good';
        } elseif ($latest['performance_score'] >= 50) {
            $status = 'Needs Improvement';
        }
        
        return [
            'score' => $latest['performance_score'],
            'status' => $status,
            'last_check' => $latest['date'],
            'recommendations_count' => $this->count_active_recommendations()
        ];
    }
    
    /**
     * Count active recommendations
     */
    private function count_active_recommendations() {
        // This would count recommendations that haven't been addressed
        // For now, return a placeholder
        return 3;
    }

    /**
     * Render the performance dashboard interface
     */
    public function render_dashboard() {
        // Enqueue scripts and styles
        $this->enqueue_dashboard_assets();
        
        // Include the dashboard template
        $template_path = plugin_dir_path( __FILE__ ) . 'templates/admin-dashboard.php';
        
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __( 'Performance Monitor', 'khm-seo' ) . '</h1>';
            echo '<div class="notice notice-error"><p>' . __( 'Dashboard template not found.', 'khm-seo' ) . '</p></div>';
            echo '</div>';
        }
    }

    /**
     * Enqueue dashboard assets
     */
    private function enqueue_dashboard_assets() {
        // CSS
        wp_enqueue_style(
            'khm-performance-monitor',
            plugin_dir_url( __FILE__ ) . 'assets/css/performance-monitor.css',
            array(),
            KHM_SEO_VERSION
        );
        
        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.9.1',
            true
        );
        
        // Dashboard JavaScript
        wp_enqueue_script(
            'khm-performance-dashboard',
            plugin_dir_url( __FILE__ ) . 'assets/js/performance-monitor.js',
            array( 'jquery', 'chartjs' ),
            KHM_SEO_VERSION,
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script( 'khm-performance-dashboard', 'khmPerformance', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'khm_performance_nonce' ),
            'strings'  => array(
                'runningTest'      => __( 'Running performance test...', 'khm-seo' ),
                'testComplete'     => __( 'Test completed successfully!', 'khm-seo' ),
                'testFailed'       => __( 'Test failed. Please try again.', 'khm-seo' ),
                'noData'          => __( 'No data available', 'khm-seo' ),
                'loading'         => __( 'Loading...', 'khm-seo' ),
                'error'           => __( 'Error occurred', 'khm-seo' ),
                'settingsSaved'   => __( 'Settings saved successfully!', 'khm-seo' ),
                'pleaseWait'      => __( 'Please wait...', 'khm-seo' ),
            )
        ) );
    }
}