<?php
/**
 * Google Search Console Integration for SEO Measurement Module
 * 
 * This class provides comprehensive GSC API integration including:
 * - Site verification and property management
 * - Search analytics data retrieval
 * - URL inspection and indexing requests
 * - Sitemap submission and monitoring
 * - Real-time data synchronization
 * - Error handling and retry logic
 * 
 * @package KHM_SEO
 * @subpackage GoogleSearchConsole
 * @since 9.0.0
 */

namespace KHM_SEO\GoogleSearchConsole;

use KHM_SEO\OAuth\OAuthManager;

class GSCManager {
    
    private $oauth_manager;
    private $api_base_url = 'https://www.googleapis.com/webmasters/v3';
    private $search_analytics_base = 'https://www.googleapis.com/webmasters/v3/sites';
    private $url_inspection_base = 'https://searchconsole.googleapis.com/v1/urlInspection';
    
    // GSC API endpoints
    const ENDPOINTS = [
        'sites' => '/sites',
        'sitemaps' => '/sites/{siteUrl}/sitemaps',
        'search_analytics' => '/sites/{siteUrl}/searchAnalytics/query',
        'url_inspection' => '/urlInspection/index:inspect',
        'index_request' => '/urlInspection/index:inspect'
    ];
    
    // Search dimensions available in GSC API
    const SEARCH_DIMENSIONS = [
        'query' => 'Search Query',
        'page' => 'Page',
        'country' => 'Country',
        'device' => 'Device',
        'searchAppearance' => 'Search Appearance',
        'date' => 'Date'
    ];
    
    // Search metrics available
    const SEARCH_METRICS = [
        'impressions' => 'Impressions',
        'clicks' => 'Clicks',
        'ctr' => 'Click-through Rate',
        'position' => 'Average Position'
    ];
    
    // Device types
    const DEVICE_TYPES = [
        'desktop' => 'Desktop',
        'mobile' => 'Mobile',
        'tablet' => 'Tablet'
    ];
    
    // Search appearance types
    const SEARCH_APPEARANCE = [
        'searchResultsAppearance' => 'Search Results',
        'richSnippets' => 'Rich Results',
        'ampArticles' => 'AMP Articles',
        'jobPosting' => 'Job Listings',
        'recipe' => 'Recipes',
        'eventListing' => 'Events',
        'videoCarousel' => 'Video Carousel'
    ];
    
    public function __construct() {
        $this->oauth_manager = new OAuthManager();
        
        // Hook into WordPress
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_gsc_sync_data', [$this, 'ajax_sync_data']);
        add_action('wp_ajax_gsc_get_properties', [$this, 'ajax_get_properties']);
        add_action('wp_ajax_gsc_inspect_url', [$this, 'ajax_inspect_url']);
        add_action('wp_ajax_gsc_request_indexing', [$this, 'ajax_request_indexing']);
        
        // Schedule automated data sync
        add_action('khm_seo_gsc_daily_sync', [$this, 'daily_sync']);
        add_action('khm_seo_gsc_hourly_sync', [$this, 'hourly_sync']);
    }
    
    public function init() {
        // Register scheduled events if not already scheduled
        if (!wp_next_scheduled('khm_seo_gsc_daily_sync')) {
            wp_schedule_event(time(), 'daily', 'khm_seo_gsc_daily_sync');
        }
        
        if (!wp_next_scheduled('khm_seo_gsc_hourly_sync')) {
            wp_schedule_event(time(), 'hourly', 'khm_seo_gsc_hourly_sync');
        }
    }
    
    /**
     * Get all verified properties from GSC
     */
    public function get_properties($refresh_cache = false) {
        try {
            // Check cache first
            $cache_key = 'khm_seo_gsc_properties';
            if (!$refresh_cache) {
                $cached = get_transient($cache_key);
                if ($cached !== false) {
                    return $cached;
                }
            }
            
            // Check if we have a valid token
            $token = $this->oauth_manager->get_access_token('gsc');
            if (!$token) {
                throw new \Exception('No valid GSC token available. Please connect to Google Search Console first.');
            }
            
            // Check rate limit
            if (!$this->oauth_manager->check_rate_limit('gsc')) {
                throw new \Exception('GSC API rate limit exceeded. Please try again later.');
            }
            
            $url = $this->api_base_url . self::ENDPOINTS['sites'];
            
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token['access_token'],
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception('Failed to fetch properties: ' . $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (empty($data['siteEntry'])) {
                return [];
            }
            
            $properties = [];
            foreach ($data['siteEntry'] as $site) {
                $properties[] = [
                    'site_url' => $site['siteUrl'],
                    'permission_level' => $site['permissionLevel'],
                    'site_type' => $this->detect_site_type($site['siteUrl'])
                ];
            }
            
            // Record API usage
            $this->oauth_manager->record_api_usage('gsc', 'sites.list', true, 200);
            
            // Cache for 1 hour
            set_transient($cache_key, $properties, HOUR_IN_SECONDS);
            
            return $properties;
            
        } catch (\Exception $e) {
            $this->oauth_manager->record_api_usage('gsc', 'sites.list', false, 400);
            error_log('GSC Properties Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get search analytics data
     */
    public function get_search_analytics($site_url, $options = []) {
        try {
            $defaults = [
                'start_date' => date('Y-m-d', strtotime('-7 days')),
                'end_date' => date('Y-m-d', strtotime('-1 day')),
                'dimensions' => ['query'],
                'metrics' => ['impressions', 'clicks', 'ctr', 'position'],
                'row_limit' => 1000,
                'start_row' => 0,
                'dimension_filter_groups' => [],
                'aggregation_type' => 'auto',
                'data_state' => 'final'
            ];
            
            $options = array_merge($defaults, $options);
            
            // Validate dimensions and metrics
            foreach ($options['dimensions'] as $dimension) {
                if (!array_key_exists($dimension, self::SEARCH_DIMENSIONS)) {
                    throw new \Exception("Invalid dimension: {$dimension}");
                }
            }
            
            // Check rate limit
            if (!$this->oauth_manager->check_rate_limit('gsc')) {
                throw new \Exception('GSC API rate limit exceeded. Please try again later.');
            }
            
            $token = $this->oauth_manager->get_access_token('gsc');
            if (!$token) {
                throw new \Exception('No valid GSC token available.');
            }
            
            $url = str_replace('{siteUrl}', urlencode($site_url), 
                              $this->api_base_url . self::ENDPOINTS['search_analytics']);
            
            $request_body = [
                'startDate' => $options['start_date'],
                'endDate' => $options['end_date'],
                'dimensions' => $options['dimensions'],
                'rowLimit' => min($options['row_limit'], 25000), // GSC max limit
                'startRow' => $options['start_row'],
                'aggregationType' => $options['aggregation_type'],
                'dataState' => $options['data_state']
            ];
            
            // Add dimension filters if provided
            if (!empty($options['dimension_filter_groups'])) {
                $request_body['dimensionFilterGroups'] = $options['dimension_filter_groups'];
            }
            
            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token['access_token'],
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($request_body),
                'timeout' => 60
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception('API request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($response_code !== 200) {
                $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
                throw new \Exception("GSC API Error ({$response_code}): {$error_message}");
            }
            
            // Record successful API usage
            $this->oauth_manager->record_api_usage('gsc', 'searchanalytics.query', true, $response_code);
            
            return [
                'rows' => $data['rows'] ?? [],
                'response_aggregation_type' => $data['responseAggregationType'] ?? 'auto'
            ];
            
        } catch (\Exception $e) {
            $this->oauth_manager->record_api_usage('gsc', 'searchanalytics.query', false, 400);
            error_log('GSC Search Analytics Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Inspect URL indexing status
     */
    public function inspect_url($inspection_url, $site_url = null) {
        try {
            if (!$site_url) {
                $site_url = home_url();
            }
            
            // Check rate limit
            if (!$this->oauth_manager->check_rate_limit('gsc')) {
                throw new \Exception('GSC API rate limit exceeded. Please try again later.');
            }
            
            $token = $this->oauth_manager->get_access_token('gsc');
            if (!$token) {
                throw new \Exception('No valid GSC token available.');
            }
            
            $url = $this->url_inspection_base . self::ENDPOINTS['url_inspection'];
            
            $request_body = [
                'inspectionUrl' => $inspection_url,
                'siteUrl' => $site_url
            ];
            
            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token['access_token'],
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($request_body),
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception('URL inspection failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($response_code !== 200) {
                $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
                throw new \Exception("URL Inspection Error ({$response_code}): {$error_message}");
            }
            
            // Record successful API usage
            $this->oauth_manager->record_api_usage('gsc', 'urlinspection.index', true, $response_code);
            
            return $this->parse_inspection_result($data);
            
        } catch (\Exception $e) {
            $this->oauth_manager->record_api_usage('gsc', 'urlinspection.index', false, 400);
            error_log('GSC URL Inspection Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Request indexing for a URL
     */
    public function request_indexing($url, $site_url = null) {
        try {
            if (!$site_url) {
                $site_url = home_url();
            }
            
            // Check rate limit (indexing has stricter limits)
            if (!$this->oauth_manager->check_rate_limit('gsc')) {
                throw new \Exception('GSC API rate limit exceeded. Please try again later.');
            }
            
            $token = $this->oauth_manager->get_access_token('gsc');
            if (!$token) {
                throw new \Exception('No valid GSC token available.');
            }
            
            // First inspect the URL to get current status
            $inspection_result = $this->inspect_url($url, $site_url);
            
            // Check if URL can be indexed
            if (!$inspection_result['can_be_indexed']) {
                throw new \Exception('URL cannot be indexed: ' . $inspection_result['indexing_status_reason']);
            }
            
            // Request indexing via Index API (if available)
            $indexing_url = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
            
            $request_body = [
                'url' => $url,
                'type' => 'URL_UPDATED'
            ];
            
            $response = wp_remote_post($indexing_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token['access_token'],
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($request_body),
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception('Indexing request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($response_code !== 200) {
                $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
                throw new \Exception("Indexing Request Error ({$response_code}): {$error_message}");
            }
            
            // Record successful API usage
            $this->oauth_manager->record_api_usage('gsc', 'indexing.request', true, $response_code);
            
            return [
                'success' => true,
                'notification_metadata' => $data['urlNotificationMetadata'] ?? []
            ];
            
        } catch (\Exception $e) {
            $this->oauth_manager->record_api_usage('gsc', 'indexing.request', false, 400);
            error_log('GSC Indexing Request Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get sitemap status and submit sitemaps
     */
    public function get_sitemaps($site_url, $include_details = true) {
        try {
            // Check rate limit
            if (!$this->oauth_manager->check_rate_limit('gsc')) {
                throw new \Exception('GSC API rate limit exceeded. Please try again later.');
            }
            
            $token = $this->oauth_manager->get_access_token('gsc');
            if (!$token) {
                throw new \Exception('No valid GSC token available.');
            }
            
            $url = str_replace('{siteUrl}', urlencode($site_url), 
                              $this->api_base_url . self::ENDPOINTS['sitemaps']);
            
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token['access_token'],
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception('Failed to fetch sitemaps: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($response_code !== 200) {
                $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
                throw new \Exception("Sitemaps Error ({$response_code}): {$error_message}");
            }
            
            // Record successful API usage
            $this->oauth_manager->record_api_usage('gsc', 'sitemaps.list', true, $response_code);
            
            return $data['sitemap'] ?? [];
            
        } catch (\Exception $e) {
            $this->oauth_manager->record_api_usage('gsc', 'sitemaps.list', false, 400);
            error_log('GSC Sitemaps Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Submit a sitemap to GSC
     */
    public function submit_sitemap($site_url, $sitemap_url) {
        try {
            // Check rate limit
            if (!$this->oauth_manager->check_rate_limit('gsc')) {
                throw new \Exception('GSC API rate limit exceeded. Please try again later.');
            }
            
            $token = $this->oauth_manager->get_access_token('gsc');
            if (!$token) {
                throw new \Exception('No valid GSC token available.');
            }
            
            $url = str_replace('{siteUrl}', urlencode($site_url), 
                              $this->api_base_url . self::ENDPOINTS['sitemaps']) . '/' . urlencode($sitemap_url);
            
            $response = wp_remote_request($url, [
                'method' => 'PUT',
                'headers' => [
                    'Authorization' => 'Bearer ' . $token['access_token'],
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception('Failed to submit sitemap: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code !== 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
                throw new \Exception("Sitemap Submission Error ({$response_code}): {$error_message}");
            }
            
            // Record successful API usage
            $this->oauth_manager->record_api_usage('gsc', 'sitemaps.submit', true, $response_code);
            
            return true;
            
        } catch (\Exception $e) {
            $this->oauth_manager->record_api_usage('gsc', 'sitemaps.submit', false, 400);
            error_log('GSC Sitemap Submission Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Sync data to local database
     */
    public function sync_search_data($site_url, $date_range = 7) {
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'khm_seo_gsc_stats';
            $start_date = date('Y-m-d', strtotime("-{$date_range} days"));
            $end_date = date('Y-m-d', strtotime('-1 day'));
            
            // Get search analytics data with multiple dimension combinations
            $dimension_sets = [
                ['query'],
                ['page'],
                ['country'],
                ['device'],
                ['query', 'page'],
                ['query', 'device'],
                ['page', 'device']
            ];
            
            foreach ($dimension_sets as $dimensions) {
                $data = $this->get_search_analytics($site_url, [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'dimensions' => $dimensions,
                    'row_limit' => 1000
                ]);
                
                if (!empty($data['rows'])) {
                    $this->store_search_data($data['rows'], $dimensions, $site_url);
                }
                
                // Add small delay between requests
                usleep(250000); // 250ms
            }
            
            return true;
            
        } catch (\Exception $e) {
            error_log('GSC Data Sync Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store search analytics data in database
     */
    private function store_search_data($rows, $dimensions, $site_url) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_gsc_stats';
        $dimension_combo = implode(',', $dimensions);
        
        foreach ($rows as $row) {
            $dimension_values = [];
            foreach ($dimensions as $i => $dimension) {
                $dimension_values[$dimension] = $row['keys'][$i] ?? '';
            }
            
            $data = [
                'site_url' => $site_url,
                'date_recorded' => current_time('mysql'),
                'dimension_combination' => $dimension_combo,
                'query_text' => $dimension_values['query'] ?? null,
                'page_url' => $dimension_values['page'] ?? null,
                'country' => $dimension_values['country'] ?? null,
                'device_type' => $dimension_values['device'] ?? null,
                'impressions' => $row['impressions'] ?? 0,
                'clicks' => $row['clicks'] ?? 0,
                'ctr' => $row['ctr'] ?? 0.0,
                'average_position' => $row['position'] ?? 0.0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            // Check if record already exists
            $existing = $wpdb->get_var($wpdb->prepare("
                SELECT id FROM {$table_name} 
                WHERE site_url = %s 
                AND dimension_combination = %s 
                AND query_text = %s 
                AND page_url = %s 
                AND country = %s 
                AND device_type = %s
                AND DATE(date_recorded) = %s
            ", $site_url, $dimension_combo, $data['query_text'], 
               $data['page_url'], $data['country'], $data['device_type'], 
               date('Y-m-d')));
            
            if ($existing) {
                // Update existing record
                $wpdb->update($table_name, array_merge($data, ['updated_at' => current_time('mysql')]), 
                             ['id' => $existing]);
            } else {
                // Insert new record
                $wpdb->insert($table_name, $data);
            }
        }
    }
    
    /**
     * Parse URL inspection result
     */
    private function parse_inspection_result($data) {
        $index_status = $data['inspectionResult']['indexStatusResult'] ?? [];
        $mobile_usability = $data['inspectionResult']['mobileUsabilityResult'] ?? [];
        $rich_results = $data['inspectionResult']['richResultsResult'] ?? [];
        
        return [
            'inspection_url' => $data['inspectionResult']['inspectionResultLink'] ?? '',
            'index_status' => $index_status['verdict'] ?? 'UNKNOWN',
            'can_be_indexed' => ($index_status['verdict'] ?? '') === 'PASS',
            'indexing_status_reason' => $index_status['pageFetchState'] ?? '',
            'google_canonical' => $index_status['googleCanonical'] ?? '',
            'user_canonical' => $index_status['userCanonical'] ?? '',
            'mobile_friendly' => ($mobile_usability['verdict'] ?? '') === 'PASS',
            'mobile_issues' => $mobile_usability['issues'] ?? [],
            'rich_results_status' => ($rich_results['verdict'] ?? '') === 'PASS',
            'rich_results_items' => $rich_results['detectedItems'] ?? [],
            'last_crawl_time' => $index_status['lastCrawlTime'] ?? '',
            'coverage_state' => $index_status['coverageState'] ?? ''
        ];
    }
    
    /**
     * Detect site type from URL
     */
    private function detect_site_type($site_url) {
        if (strpos($site_url, 'sc-domain:') === 0) {
            return 'domain_property';
        } else {
            return 'url_prefix';
        }
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_sync_data() {
        try {
            if (!current_user_can('manage_options')) {
                throw new \Exception('Insufficient permissions');
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_gsc_sync')) {
                throw new \Exception('Security check failed');
            }
            
            $site_url = sanitize_text_field($_POST['site_url'] ?? '');
            $date_range = intval($_POST['date_range'] ?? 7);
            
            $result = $this->sync_search_data($site_url, $date_range);
            
            wp_send_json_success([
                'message' => 'Data synchronized successfully',
                'synced' => $result
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function ajax_get_properties() {
        try {
            if (!current_user_can('manage_options')) {
                throw new \Exception('Insufficient permissions');
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_gsc_properties')) {
                throw new \Exception('Security check failed');
            }
            
            $refresh = isset($_POST['refresh']) && $_POST['refresh'] === 'true';
            $properties = $this->get_properties($refresh);
            
            wp_send_json_success([
                'properties' => $properties
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function ajax_inspect_url() {
        try {
            if (!current_user_can('manage_options')) {
                throw new \Exception('Insufficient permissions');
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_gsc_inspect')) {
                throw new \Exception('Security check failed');
            }
            
            $url = sanitize_text_field($_POST['url'] ?? '');
            $site_url = sanitize_text_field($_POST['site_url'] ?? '');
            
            $result = $this->inspect_url($url, $site_url);
            
            wp_send_json_success([
                'inspection_result' => $result
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function ajax_request_indexing() {
        try {
            if (!current_user_can('manage_options')) {
                throw new \Exception('Insufficient permissions');
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_gsc_index')) {
                throw new \Exception('Security check failed');
            }
            
            $url = sanitize_text_field($_POST['url'] ?? '');
            $site_url = sanitize_text_field($_POST['site_url'] ?? '');
            
            $result = $this->request_indexing($url, $site_url);
            
            wp_send_json_success([
                'indexing_result' => $result
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Scheduled sync methods
     */
    public function daily_sync() {
        $properties = $this->get_properties();
        foreach ($properties as $property) {
            if ($property['permission_level'] === 'siteOwner' || $property['permission_level'] === 'siteFullUser') {
                $this->sync_search_data($property['site_url'], 1); // Daily data
                sleep(1); // Rate limiting
            }
        }
    }
    
    public function hourly_sync() {
        // Sync recent URL inspection requests and sitemap status
        $properties = $this->get_properties();
        foreach ($properties as $property) {
            try {
                $this->get_sitemaps($property['site_url']);
                sleep(2);
            } catch (\Exception $e) {
                error_log('Hourly sync error for ' . $property['site_url'] . ': ' . $e->getMessage());
            }
        }
    }
}