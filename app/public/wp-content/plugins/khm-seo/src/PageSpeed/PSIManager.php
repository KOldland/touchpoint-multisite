<?php

namespace KHM_SEO\PageSpeed;

use KHM_SEO\OAuth\OAuthManager;
use WP_Error;

/**
 * PageSpeed Insights Manager
 * 
 * Comprehensive Core Web Vitals monitoring and performance analysis
 * using Google PageSpeed Insights API v5
 * 
 * Features:
 * - Real-time and historical performance data
 * - Core Web Vitals tracking (LCP, FID, CLS)
 * - Mobile and desktop analysis
 * - Field and lab data correlation
 * - Performance trend analysis
 * - Optimization recommendations
 * - Automated monitoring and alerts
 * 
 * @package KHM_SEO\PageSpeed
 * @since 1.0.0
 */
class PSIManager {

    /**
     * OAuth Manager instance
     */
    private $oauth_manager;

    /**
     * API Configuration
     */
    private $api_config = [
        'base_url' => 'https://www.googleapis.com/pagespeedonline/v5',
        'rate_limit' => 200, // requests per day
        'timeout' => 30
    ];

    /**
     * Core Web Vitals thresholds
     */
    private $cwv_thresholds = [
        'lcp' => ['good' => 2.5, 'poor' => 4.0], // seconds
        'fid' => ['good' => 100, 'poor' => 300], // milliseconds
        'cls' => ['good' => 0.1, 'poor' => 0.25], // score
        'fcp' => ['good' => 1.8, 'poor' => 3.0], // seconds
        'inp' => ['good' => 200, 'poor' => 500], // milliseconds
        'ttfb' => ['good' => 0.8, 'poor' => 1.8] // seconds
    ];

    /**
     * Performance categories
     */
    private $categories = [
        'performance',
        'accessibility', 
        'best-practices',
        'seo',
        'pwa'
    ];

    /**
     * Analysis strategies
     */
    private $strategies = ['mobile', 'desktop'];

    /**
     * Initialize PSI Manager
     */
    public function __construct() {
        $this->oauth_manager = new OAuthManager();
        add_action('init', [$this, 'init_hooks']);
    }

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_psi_analyze_url', [$this, 'ajax_analyze_url']);
        add_action('wp_ajax_psi_get_report', [$this, 'ajax_get_report']);
        add_action('wp_ajax_psi_bulk_analysis', [$this, 'ajax_bulk_analysis']);
        add_action('wp_ajax_psi_get_trends', [$this, 'ajax_get_trends']);

        // Background processing
        add_action('psi_background_analysis', [$this, 'background_analysis']);
        add_action('psi_daily_monitoring', [$this, 'daily_monitoring']);

        // Schedule automated analysis
        if (!wp_next_scheduled('psi_daily_monitoring')) {
            wp_schedule_event(time(), 'daily', 'psi_daily_monitoring');
        }
    }

    /**
     * Analyze single URL performance
     */
    public function analyze_url($url, $strategy = 'mobile', $categories = null) {
        if (!$this->validate_url($url)) {
            return new \WP_Error('invalid_url', 'Invalid URL provided');
        }

        $categories = $categories ?: $this->categories;
        $api_key = $this->get_api_key();
        
        if (!$api_key) {
            return new \WP_Error('no_api_key', 'PageSpeed Insights API key not configured');
        }

        // Check rate limits
        if (!$this->check_rate_limit()) {
            return new \WP_Error('rate_limit', 'API rate limit exceeded');
        }

        $endpoint = $this->api_config['base_url'] . '/runPagespeed';
        $params = [
            'url' => $url,
            'strategy' => $strategy,
            'category' => implode('&category=', $categories),
            'key' => $api_key
        ];

        $request_url = $endpoint . '?' . http_build_query($params);

        $response = wp_remote_get($request_url, [
            'timeout' => $this->api_config['timeout'],
            'headers' => [
                'User-Agent' => 'KHM-SEO-Suite/1.0'
            ]
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || isset($data['error'])) {
            return new \WP_Error('api_error', $data['error']['message'] ?? 'API request failed');
        }

        // Process and store results
        $processed_data = $this->process_psi_data($data, $url, $strategy);
        $this->store_analysis_results($processed_data);
        
        // Update rate limit counter
        $this->update_rate_limit_counter();

        return $processed_data;
    }

    /**
     * Process PageSpeed Insights API response
     */
    private function process_psi_data($data, $url, $strategy) {
        $lighthouse_result = $data['lighthouseResult'] ?? [];
        $loading_experience = $data['loadingExperience'] ?? [];
        $origin_loading_experience = $data['originLoadingExperience'] ?? [];

        // Extract Core Web Vitals
        $core_web_vitals = $this->extract_core_web_vitals($lighthouse_result, $loading_experience);
        
        // Extract performance metrics
        $performance_metrics = $this->extract_performance_metrics($lighthouse_result);
        
        // Extract optimization opportunities
        $opportunities = $this->extract_opportunities($lighthouse_result);
        
        // Extract diagnostics
        $diagnostics = $this->extract_diagnostics($lighthouse_result);

        return [
            'url' => $url,
            'strategy' => $strategy,
            'timestamp' => current_time('mysql'),
            'scores' => [
                'performance' => $lighthouse_result['categories']['performance']['score'] ?? 0,
                'accessibility' => $lighthouse_result['categories']['accessibility']['score'] ?? 0,
                'best_practices' => $lighthouse_result['categories']['best-practices']['score'] ?? 0,
                'seo' => $lighthouse_result['categories']['seo']['score'] ?? 0,
                'pwa' => $lighthouse_result['categories']['pwa']['score'] ?? 0
            ],
            'core_web_vitals' => $core_web_vitals,
            'performance_metrics' => $performance_metrics,
            'field_data' => $this->process_field_data($loading_experience),
            'origin_data' => $this->process_field_data($origin_loading_experience),
            'opportunities' => $opportunities,
            'diagnostics' => $diagnostics,
            'screenshot' => $lighthouse_result['audits']['screenshot']['details']['data'] ?? null
        ];
    }

    /**
     * Extract Core Web Vitals from Lighthouse data
     */
    private function extract_core_web_vitals($lighthouse_result, $loading_experience) {
        $audits = $lighthouse_result['audits'] ?? [];
        $field_metrics = $loading_experience['metrics'] ?? [];

        $cwv = [
            // Lab data (Lighthouse)
            'lab' => [
                'lcp' => $audits['largest-contentful-paint']['numericValue'] ?? null,
                'fid' => null, // FID not available in lab
                'cls' => $audits['cumulative-layout-shift']['numericValue'] ?? null,
                'fcp' => $audits['first-contentful-paint']['numericValue'] ?? null,
                'inp' => $audits['interaction-to-next-paint']['numericValue'] ?? null,
                'ttfb' => $audits['time-to-first-byte']['numericValue'] ?? null
            ],
            // Field data (Real User Metrics)
            'field' => [
                'lcp' => $field_metrics['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] ?? null,
                'fid' => $field_metrics['FIRST_INPUT_DELAY_MS']['percentile'] ?? null,
                'cls' => $field_metrics['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] ?? null,
                'fcp' => $field_metrics['FIRST_CONTENTFUL_PAINT_MS']['percentile'] ?? null,
                'inp' => $field_metrics['INTERACTION_TO_NEXT_PAINT']['percentile'] ?? null,
                'ttfb' => $field_metrics['EXPERIMENTAL_TIME_TO_FIRST_BYTE']['percentile'] ?? null
            ]
        ];

        // Add performance classifications
        foreach (['lab', 'field'] as $data_type) {
            foreach ($cwv[$data_type] as $metric => $value) {
                if ($value !== null) {
                    $cwv[$data_type][$metric . '_rating'] = $this->classify_metric($metric, $value);
                }
            }
        }

        return $cwv;
    }

    /**
     * Extract performance metrics
     */
    private function extract_performance_metrics($lighthouse_result) {
        $audits = $lighthouse_result['audits'] ?? [];

        return [
            'speed_index' => $audits['speed-index']['numericValue'] ?? null,
            'total_blocking_time' => $audits['total-blocking-time']['numericValue'] ?? null,
            'max_potential_fid' => $audits['max-potential-fid']['numericValue'] ?? null,
            'dom_size' => $audits['dom-size']['numericValue'] ?? null,
            'resource_summary' => $this->extract_resource_summary($audits),
            'runtime_settings' => $lighthouse_result['configSettings'] ?? []
        ];
    }

    /**
     * Extract optimization opportunities
     */
    private function extract_opportunities($lighthouse_result) {
        $opportunities = [];
        $audits = $lighthouse_result['audits'] ?? [];

        $opportunity_audits = [
            'unused-css-rules',
            'unused-javascript', 
            'modern-image-formats',
            'offscreen-images',
            'render-blocking-resources',
            'unminified-css',
            'unminified-javascript',
            'efficient-animated-content',
            'duplicated-javascript',
            'legacy-javascript'
        ];

        foreach ($opportunity_audits as $audit_id) {
            if (isset($audits[$audit_id]) && isset($audits[$audit_id]['details']['overallSavingsMs'])) {
                $audit = $audits[$audit_id];
                $opportunities[] = [
                    'id' => $audit_id,
                    'title' => $audit['title'],
                    'description' => $audit['description'],
                    'savings_ms' => $audit['details']['overallSavingsMs'],
                    'savings_bytes' => $audit['details']['overallSavingsBytes'] ?? 0,
                    'score' => $audit['score'],
                    'impact' => $this->calculate_impact($audit['details']['overallSavingsMs'])
                ];
            }
        }

        // Sort by potential savings
        usort($opportunities, function($a, $b) {
            return $b['savings_ms'] - $a['savings_ms'];
        });

        return $opportunities;
    }

    /**
     * Extract diagnostics information
     */
    private function extract_diagnostics($lighthouse_result) {
        $diagnostics = [];
        $audits = $lighthouse_result['audits'] ?? [];

        $diagnostic_audits = [
            'critical-request-chains',
            'main-thread-tasks',
            'bootup-time',
            'uses-long-cache-ttl',
            'total-byte-weight',
            'uses-optimized-images',
            'uses-text-compression',
            'uses-responsive-images',
            'server-response-time'
        ];

        foreach ($diagnostic_audits as $audit_id) {
            if (isset($audits[$audit_id])) {
                $audit = $audits[$audit_id];
                $diagnostics[] = [
                    'id' => $audit_id,
                    'title' => $audit['title'],
                    'description' => $audit['description'],
                    'score' => $audit['score'],
                    'display_value' => $audit['displayValue'] ?? null,
                    'details' => $audit['details'] ?? []
                ];
            }
        }

        return $diagnostics;
    }

    /**
     * Process field data (Real User Metrics)
     */
    private function process_field_data($loading_experience) {
        if (empty($loading_experience['metrics'])) {
            return null;
        }

        $field_data = [];
        foreach ($loading_experience['metrics'] as $metric_name => $metric_data) {
            $field_data[strtolower($metric_name)] = [
                'percentile' => $metric_data['percentile'] ?? null,
                'category' => $metric_data['category'] ?? null,
                'distributions' => $metric_data['distributions'] ?? []
            ];
        }

        return $field_data;
    }

    /**
     * Classify metric performance
     */
    private function classify_metric($metric, $value) {
        if (!isset($this->cwv_thresholds[$metric])) {
            return 'unknown';
        }

        $thresholds = $this->cwv_thresholds[$metric];
        
        // Convert milliseconds to seconds for time-based metrics
        if (in_array($metric, ['lcp', 'fcp', 'ttfb']) && $value > 100) {
            $value = $value / 1000;
        }

        if ($value <= $thresholds['good']) {
            return 'good';
        } elseif ($value <= $thresholds['poor']) {
            return 'needs-improvement';
        } else {
            return 'poor';
        }
    }

    /**
     * Calculate optimization impact level
     */
    private function calculate_impact($savings_ms) {
        if ($savings_ms >= 1000) {
            return 'high';
        } elseif ($savings_ms >= 500) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Bulk URL analysis
     */
    public function bulk_analysis($urls, $strategy = 'mobile') {
        $results = [];
        $errors = [];

        foreach ($urls as $url) {
            $result = $this->analyze_url($url, $strategy);
            
            if (is_wp_error($result)) {
                $errors[] = [
                    'url' => $url,
                    'error' => $result->get_error_message()
                ];
            } else {
                $results[] = $result;
            }

            // Rate limiting delay
            sleep(2);
        }

        return [
            'results' => $results,
            'errors' => $errors,
            'summary' => $this->generate_bulk_summary($results)
        ];
    }

    /**
     * Get performance trends for a URL
     */
    public function get_performance_trends($url, $days = 30) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'gsc_cwv_metrics';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_name} 
            WHERE url = %s 
            AND date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY date DESC
        ", $url, $days));

        if (!$results) {
            return [];
        }

        return [
            'url' => $url,
            'period' => $days,
            'trends' => $this->calculate_trends($results),
            'data_points' => count($results),
            'latest' => $results[0],
            'historical' => $results
        ];
    }

    /**
     * Calculate performance trends
     */
    private function calculate_trends($results) {
        if (count($results) < 2) {
            return null;
        }

        $metrics = ['lcp', 'fid', 'cls', 'performance_score'];
        $trends = [];

        foreach ($metrics as $metric) {
            $values = array_column($results, $metric);
            $values = array_filter($values, function($v) { return $v !== null; });
            
            if (count($values) >= 2) {
                $latest = reset($values);
                $oldest = end($values);
                
                $change = $latest - $oldest;
                $change_percent = $oldest > 0 ? ($change / $oldest) * 100 : 0;
                
                $trends[$metric] = [
                    'direction' => $change > 0 ? 'improving' : ($change < 0 ? 'declining' : 'stable'),
                    'change' => $change,
                    'change_percent' => round($change_percent, 2),
                    'latest' => $latest,
                    'oldest' => $oldest
                ];
            }
        }

        return $trends;
    }

    /**
     * Store analysis results in database
     */
    private function store_analysis_results($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'gsc_cwv_metrics';
        
        $wpdb->insert($table_name, [
            'url' => $data['url'],
            'strategy' => $data['strategy'],
            'date' => $data['timestamp'],
            'lcp' => $data['core_web_vitals']['lab']['lcp'],
            'fid' => $data['core_web_vitals']['field']['fid'],
            'cls' => $data['core_web_vitals']['lab']['cls'],
            'fcp' => $data['core_web_vitals']['lab']['fcp'],
            'inp' => $data['core_web_vitals']['lab']['inp'],
            'ttfb' => $data['core_web_vitals']['lab']['ttfb'],
            'performance_score' => $data['scores']['performance'],
            'accessibility_score' => $data['scores']['accessibility'],
            'seo_score' => $data['scores']['seo'],
            'best_practices_score' => $data['scores']['best_practices'],
            'pwa_score' => $data['scores']['pwa'],
            'opportunities' => json_encode($data['opportunities']),
            'diagnostics' => json_encode($data['diagnostics']),
            'field_data' => json_encode($data['field_data']),
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Generate bulk analysis summary
     */
    private function generate_bulk_summary($results) {
        if (empty($results)) {
            return null;
        }

        $summary = [
            'total_urls' => count($results),
            'average_scores' => [],
            'cwv_performance' => [],
            'top_issues' => []
        ];

        // Calculate average scores
        foreach (['performance', 'accessibility', 'seo', 'best_practices', 'pwa'] as $category) {
            $scores = [];
            foreach ($results as $result) {
                if (isset($result['scores'][$category])) {
                    $scores[] = $result['scores'][$category];
                }
            }
            $summary['average_scores'][$category] = count($scores) > 0 ? array_sum($scores) / count($scores) : 0;
        }

        // Core Web Vitals performance distribution
        $cwv_ratings = ['good' => 0, 'needs-improvement' => 0, 'poor' => 0];
        foreach ($results as $result) {
            $lcp_rating = $result['core_web_vitals']['lab']['lcp_rating'] ?? 'unknown';
            if (isset($cwv_ratings[$lcp_rating])) {
                $cwv_ratings[$lcp_rating]++;
            }
        }
        $summary['cwv_performance'] = $cwv_ratings;

        // Top optimization opportunities
        $all_opportunities = [];
        foreach ($results as $result) {
            foreach ($result['opportunities'] as $opp) {
                $all_opportunities[] = $opp;
            }
        }

        usort($all_opportunities, function($a, $b) {
            return $b['savings_ms'] - $a['savings_ms'];
        });

        $summary['top_issues'] = array_slice($all_opportunities, 0, 5);

        return $summary;
    }

    /**
     * AJAX: Analyze single URL
     */
    public function ajax_analyze_url() {
        if (!wp_verify_nonce($_POST['nonce'], 'psi_analyze')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $url = isset($_POST['url']) ? filter_var($_POST['url'], FILTER_SANITIZE_URL) : '';
        $strategy = isset($_POST['strategy']) ? sanitize_text_field($_POST['strategy']) : 'mobile';

        $result = $this->analyze_url($url, $strategy);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Get stored analysis report
     */
    public function ajax_get_report() {
        if (!wp_verify_nonce($_POST['nonce'], 'psi_report')) {
            wp_die('Security check failed');
        }

        $url = isset($_POST['url']) ? filter_var($_POST['url'], FILTER_SANITIZE_URL) : '';
        $strategy = isset($_POST['strategy']) ? sanitize_text_field($_POST['strategy']) : 'mobile';

        global $wpdb;
        $table_name = $wpdb->prefix . 'gsc_cwv_metrics';
        
        $report = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$table_name} 
            WHERE url = %s AND strategy = %s 
            ORDER BY created_at DESC 
            LIMIT 1
        ", $url, $strategy));

        if (!$report) {
            wp_send_json_error('No analysis data found for this URL');
        }

        wp_send_json_success([
            'report' => $report,
            'trends' => $this->get_performance_trends($url)
        ]);
    }

    /**
     * Utility methods
     */
    private function validate_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function get_api_key() {
        return get_option('khm_seo_psi_api_key', '');
    }

    private function check_rate_limit() {
        $daily_count = get_transient('psi_daily_requests') ?: 0;
        return $daily_count < $this->api_config['rate_limit'];
    }

    private function update_rate_limit_counter() {
        $daily_count = get_transient('psi_daily_requests') ?: 0;
        set_transient('psi_daily_requests', $daily_count + 1, DAY_IN_SECONDS);
    }

    private function extract_resource_summary($audits) {
        $summary = [];
        
        if (isset($audits['resource-summary'])) {
            $details = $audits['resource-summary']['details']['items'] ?? [];
            foreach ($details as $item) {
                $summary[$item['resourceType']] = [
                    'count' => $item['requestCount'],
                    'size' => $item['size'],
                    'transfer_size' => $item['transferSize']
                ];
            }
        }

        return $summary;
    }

    /**
     * Background analysis for automated monitoring
     */
    public function background_analysis() {
        $monitored_urls = get_option('khm_seo_monitored_urls', []);
        
        foreach ($monitored_urls as $url) {
            $this->analyze_url($url, 'mobile');
            sleep(3); // Rate limiting
        }
    }

    /**
     * Daily automated monitoring
     */
    public function daily_monitoring() {
        // Reset daily rate limit counter
        delete_transient('psi_daily_requests');
        
        // Run background analysis
        wp_schedule_single_event(time() + 60, 'psi_background_analysis');
    }
}