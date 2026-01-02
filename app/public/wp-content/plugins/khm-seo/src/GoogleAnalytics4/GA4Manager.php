<?php
/**
 * Google Analytics 4 Integration for SEO Measurement Module
 * 
 * This class provides comprehensive GA4 API integration including:
 * - Property and data stream management
 * - Real-time and historical data retrieval
 * - Audience insights and behavior analysis
 * - Conversion tracking and goal monitoring
 * - Custom event tracking and metrics
 * - E-commerce performance analysis
 * - Cross-platform attribution
 * - Data correlation with GSC metrics
 * 
 * @package KHM_SEO
 * @subpackage GoogleAnalytics4
 * @since 9.0.0
 */

namespace KHM_SEO\GoogleAnalytics4;

use KHM_SEO\OAuth\OAuthManager;

class GA4Manager {
    
    private $oauth_manager;
    private $api_base_url = 'https://analyticsdata.googleapis.com/v1beta';
    private $admin_api_url = 'https://analyticsadmin.googleapis.com/v1beta';
    private $realtime_api_url = 'https://analyticsdata.googleapis.com/v1beta';
    
    // GA4 API endpoints
    const ENDPOINTS = [
        'properties' => '/properties',
        'run_report' => '/properties/{property_id}:runReport',
        'run_realtime_report' => '/properties/{property_id}:runRealtimeReport',
        'run_pivot_report' => '/properties/{property_id}:runPivotReport',
        'batch_run_reports' => '/properties/{property_id}:batchRunReports',
        'metadata' => '/properties/{property_id}/metadata',
        'data_streams' => '/properties/{property_id}/dataStreams',
        'conversion_events' => '/properties/{property_id}/conversionEvents',
        'custom_dimensions' => '/properties/{property_id}/customDimensions',
        'custom_metrics' => '/properties/{property_id}/customMetrics'
    ];
    
    // GA4 standard dimensions
    const STANDARD_DIMENSIONS = [
        'date' => 'Date',
        'year' => 'Year',
        'month' => 'Month',
        'week' => 'Week',
        'day' => 'Day',
        'hour' => 'Hour',
        'country' => 'Country',
        'region' => 'Region',
        'city' => 'City',
        'continent' => 'Continent',
        'subContinent' => 'Sub Continent',
        'deviceCategory' => 'Device Category',
        'operatingSystem' => 'Operating System',
        'browser' => 'Browser',
        'screenResolution' => 'Screen Resolution',
        'pagePath' => 'Page Path',
        'pageTitle' => 'Page Title',
        'landingPage' => 'Landing Page',
        'exitPage' => 'Exit Page',
        'source' => 'Source',
        'medium' => 'Medium',
        'campaign' => 'Campaign',
        'sessionSource' => 'Session Source',
        'sessionMedium' => 'Session Medium',
        'sessionCampaign' => 'Session Campaign',
        'eventName' => 'Event Name',
        'linkDomain' => 'Link Domain',
        'outbound' => 'Outbound',
        'searchTerm' => 'Search Term',
        'fileExtension' => 'File Extension',
        'fileName' => 'File Name',
        'videoTitle' => 'Video Title',
        'firstUserSource' => 'First User Source',
        'firstUserMedium' => 'First User Medium',
        'firstUserCampaign' => 'First User Campaign'
    ];
    
    // GA4 standard metrics
    const STANDARD_METRICS = [
        'activeUsers' => 'Active Users',
        'newUsers' => 'New Users',
        'sessions' => 'Sessions',
        'sessionsPerUser' => 'Sessions per User',
        'avgSessionDuration' => 'Average Session Duration',
        'bounceRate' => 'Bounce Rate',
        'screenPageViews' => 'Page Views',
        'screenPageViewsPerSession' => 'Page Views per Session',
        'userEngagementDuration' => 'Engagement Duration',
        'engagedSessions' => 'Engaged Sessions',
        'engagementRate' => 'Engagement Rate',
        'eventCount' => 'Events',
        'conversions' => 'Conversions',
        'totalRevenue' => 'Total Revenue',
        'purchaseRevenue' => 'Purchase Revenue',
        'transactions' => 'Transactions',
        'firstTimePurchaserConversions' => 'First Time Purchasers',
        'totalPurchasers' => 'Total Purchasers',
        'addToCarts' => 'Add to Carts',
        'checkouts' => 'Checkouts',
        'cartToViewRate' => 'Cart to View Rate',
        'checkoutRate' => 'Checkout Rate',
        'purchaseToViewRate' => 'Purchase to View Rate',
        'newUserConversions' => 'New User Conversions',
        'returnOnAdSpend' => 'Return on Ad Spend',
        'advertiserAdCostPerClick' => 'Cost Per Click',
        'advertiserAdImpressions' => 'Ad Impressions',
        'advertiserAdClicks' => 'Ad Clicks',
        'publisherAdImpressions' => 'Publisher Impressions',
        'publisherAdClicks' => 'Publisher Clicks'
    ];
    
    // Predefined report configurations
    const REPORT_PRESETS = [
        'overview' => [
            'name' => 'Overview Report',
            'dimensions' => ['date'],
            'metrics' => ['activeUsers', 'sessions', 'bounceRate', 'avgSessionDuration'],
            'date_ranges' => [['start_date' => '30daysAgo', 'end_date' => 'today']]
        ],
        'pages' => [
            'name' => 'Pages Report',
            'dimensions' => ['pagePath', 'pageTitle'],
            'metrics' => ['screenPageViews', 'uniquePageviews', 'avgTimeOnPage', 'bounceRate'],
            'order_bys' => [['metric' => 'screenPageViews', 'desc' => true]]
        ],
        'traffic_sources' => [
            'name' => 'Traffic Sources',
            'dimensions' => ['source', 'medium', 'campaign'],
            'metrics' => ['activeUsers', 'newUsers', 'sessions', 'conversions'],
            'order_bys' => [['metric' => 'sessions', 'desc' => true]]
        ],
        'audience' => [
            'name' => 'Audience Report',
            'dimensions' => ['country', 'deviceCategory', 'browser'],
            'metrics' => ['activeUsers', 'newUsers', 'sessions', 'engagementRate'],
            'order_bys' => [['metric' => 'activeUsers', 'desc' => true]]
        ],
        'engagement' => [
            'name' => 'Engagement Report',
            'dimensions' => ['eventName', 'pagePath'],
            'metrics' => ['eventCount', 'engagedSessions', 'userEngagementDuration'],
            'order_bys' => [['metric' => 'eventCount', 'desc' => true]]
        ],
        'ecommerce' => [
            'name' => 'E-commerce Report',
            'dimensions' => ['date', 'source', 'medium'],
            'metrics' => ['totalRevenue', 'transactions', 'purchaseRevenue', 'addToCarts'],
            'order_bys' => [['metric' => 'totalRevenue', 'desc' => true]]
        ]
    ];
    
    public function __construct() {
        $this->oauth_manager = new OAuthManager();
        
        // Hook into WordPress
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_ga4_run_report', [$this, 'ajax_run_report']);
        add_action('wp_ajax_ga4_get_properties', [$this, 'ajax_get_properties']);
        add_action('wp_ajax_ga4_realtime_data', [$this, 'ajax_realtime_data']);
        add_action('wp_ajax_ga4_custom_report', [$this, 'ajax_custom_report']);
        
        // Schedule automated data sync
        add_action('khm_seo_ga4_hourly_sync', [$this, 'hourly_sync']);
        add_action('khm_seo_ga4_daily_sync', [$this, 'daily_sync']);
    }
    
    public function init() {
        // Register scheduled events
        if (!wp_next_scheduled('khm_seo_ga4_hourly_sync')) {
            wp_schedule_event(time(), 'hourly', 'khm_seo_ga4_hourly_sync');
        }
        
        if (!wp_next_scheduled('khm_seo_ga4_daily_sync')) {
            wp_schedule_event(time(), 'daily', 'khm_seo_ga4_daily_sync');
        }
    }
    
    /**
     * Get GA4 properties
     */
    public function get_properties($refresh_cache = false) {
        try {
            // Check cache first
            $cache_key = 'khm_seo_ga4_properties';
            if (!$refresh_cache) {
                $cached = get_transient($cache_key);
                if ($cached !== false) {
                    return $cached;
                }
            }
            
            // Check if we have a valid token
            $token = $this->oauth_manager->get_access_token('ga4');
            if (!$token) {
                throw new \Exception('No valid GA4 token available. Please connect to Google Analytics first.');
            }
            
            // Check rate limit
            if (!$this->oauth_manager->check_rate_limit('ga4')) {
                throw new \Exception('GA4 API rate limit exceeded. Please try again later.');
            }
            
            $url = $this->admin_api_url . self::ENDPOINTS['properties'];
            
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
            
            if (empty($data['properties'])) {
                return [];
            }
            
            $properties = [];
            foreach ($data['properties'] as $property) {
                $properties[] = [
                    'property_id' => $property['name'],
                    'display_name' => $property['displayName'],
                    'create_time' => $property['createTime'],
                    'update_time' => $property['updateTime'],
                    'industry_category' => $property['industryCategory'] ?? 'UNSPECIFIED',
                    'time_zone' => $property['timeZone'] ?? 'UTC',
                    'currency_code' => $property['currencyCode'] ?? 'USD',
                    'data_retention_settings' => $property['dataRetentionSettings'] ?? []
                ];
            }
            
            // Record API usage
            $this->oauth_manager->record_api_usage('ga4', 'properties.list', true, 200);
            
            // Cache for 1 hour
            set_transient($cache_key, $properties, HOUR_IN_SECONDS);
            
            return $properties;
            
        } catch (\Exception $e) {
            $this->oauth_manager->record_api_usage('ga4', 'properties.list', false, 400);
            error_log('GA4 Properties Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Run a GA4 report
     */
    public function run_report($property_id, $options = []) {
        try {
            $defaults = [
                'date_ranges' => [
                    ['start_date' => '30daysAgo', 'end_date' => 'today']
                ],
                'dimensions' => ['date'],
                'metrics' => ['activeUsers', 'sessions'],
                'limit' => 1000,
                'offset' => 0,
                'order_bys' => [],
                'dimension_filter' => null,
                'metric_filter' => null,
                'keep_empty_rows' => false,
                'return_property_quota' => false
            ];
            
            $options = array_merge($defaults, $options);
            
            // Validate dimensions and metrics
            $this->validate_dimensions_and_metrics($options['dimensions'], $options['metrics']);
            
            // Check rate limit
            if (!$this->oauth_manager->check_rate_limit('ga4')) {
                throw new \Exception('GA4 API rate limit exceeded. Please try again later.');
            }
            
            $token = $this->oauth_manager->get_access_token('ga4');
            if (!$token) {
                throw new \Exception('No valid GA4 token available.');
            }
            
            $url = str_replace('{property_id}', $property_id, 
                              $this->api_base_url . self::ENDPOINTS['run_report']);
            
            $request_body = [
                'dateRanges' => $options['date_ranges'],
                'dimensions' => array_map(function($dim) {
                    return ['name' => $dim];
                }, $options['dimensions']),
                'metrics' => array_map(function($metric) {
                    return ['name' => $metric];
                }, $options['metrics']),
                'limit' => $options['limit'],
                'offset' => $options['offset'],
                'keepEmptyRows' => $options['keep_empty_rows'],
                'returnPropertyQuota' => $options['return_property_quota']
            ];
            
            // Add optional filters and ordering
            if (!empty($options['order_bys'])) {
                $request_body['orderBys'] = $this->format_order_bys($options['order_bys']);
            }
            
            if ($options['dimension_filter']) {
                $request_body['dimensionFilter'] = $options['dimension_filter'];
            }
            
            if ($options['metric_filter']) {
                $request_body['metricFilter'] = $options['metric_filter'];
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
                throw new \Exception("GA4 API Error ({$response_code}): {$error_message}");
            }
            
            // Record successful API usage
            $this->oauth_manager->record_api_usage('ga4', 'reports.run', true, $response_code);
            
            return $this->parse_report_response($data);
            
        } catch (\Exception $e) {
            $this->oauth_manager->record_api_usage('ga4', 'reports.run', false, 400);
            error_log('GA4 Report Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get real-time data
     */
    public function get_realtime_data($property_id, $options = []) {
        try {
            $defaults = [
                'dimensions' => ['country'],
                'metrics' => ['activeUsers'],
                'limit' => 100,
                'order_bys' => [],
                'dimension_filter' => null,
                'metric_filter' => null,
                'return_property_quota' => false
            ];
            
            $options = array_merge($defaults, $options);
            
            // Check rate limit
            if (!$this->oauth_manager->check_rate_limit('ga4')) {
                throw new \Exception('GA4 API rate limit exceeded. Please try again later.');
            }
            
            $token = $this->oauth_manager->get_access_token('ga4');
            if (!$token) {
                throw new \Exception('No valid GA4 token available.');
            }
            
            $url = str_replace('{property_id}', $property_id, 
                              $this->realtime_api_url . self::ENDPOINTS['run_realtime_report']);
            
            $request_body = [
                'dimensions' => array_map(function($dim) {
                    return ['name' => $dim];
                }, $options['dimensions']),
                'metrics' => array_map(function($metric) {
                    return ['name' => $metric];
                }, $options['metrics']),
                'limit' => $options['limit'],
                'returnPropertyQuota' => $options['return_property_quota']
            ];
            
            if (!empty($options['order_bys'])) {
                $request_body['orderBys'] = $this->format_order_bys($options['order_bys']);
            }
            
            if ($options['dimension_filter']) {
                $request_body['dimensionFilter'] = $options['dimension_filter'];
            }
            
            if ($options['metric_filter']) {
                $request_body['metricFilter'] = $options['metric_filter'];
            }
            
            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token['access_token'],
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($request_body),
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception('Realtime API request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($response_code !== 200) {
                $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
                throw new \Exception("GA4 Realtime API Error ({$response_code}): {$error_message}");
            }
            
            // Record successful API usage
            $this->oauth_manager->record_api_usage('ga4', 'realtime.run', true, $response_code);
            
            return $this->parse_realtime_response($data);
            
        } catch (\Exception $e) {
            $this->oauth_manager->record_api_usage('ga4', 'realtime.run', false, 400);
            error_log('GA4 Realtime Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get conversion events
     */
    public function get_conversion_events($property_id) {
        try {
            // Check rate limit
            if (!$this->oauth_manager->check_rate_limit('ga4')) {
                throw new \Exception('GA4 API rate limit exceeded. Please try again later.');
            }
            
            $token = $this->oauth_manager->get_access_token('ga4');
            if (!$token) {
                throw new \Exception('No valid GA4 token available.');
            }
            
            $url = str_replace('{property_id}', $property_id, 
                              $this->admin_api_url . self::ENDPOINTS['conversion_events']);
            
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token['access_token'],
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception('Failed to fetch conversion events: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($response_code !== 200) {
                $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
                throw new \Exception("GA4 Conversion Events Error ({$response_code}): {$error_message}");
            }
            
            // Record successful API usage
            $this->oauth_manager->record_api_usage('ga4', 'conversion_events.list', true, $response_code);
            
            return $data['conversionEvents'] ?? [];
            
        } catch (\Exception $e) {
            $this->oauth_manager->record_api_usage('ga4', 'conversion_events.list', false, 400);
            error_log('GA4 Conversion Events Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Run predefined report
     */
    public function run_preset_report($property_id, $preset_name, $date_range = null) {
        if (!isset(self::REPORT_PRESETS[$preset_name])) {
            throw new \Exception("Unknown report preset: {$preset_name}");
        }
        
        $preset = self::REPORT_PRESETS[$preset_name];
        
        $options = [
            'dimensions' => $preset['dimensions'],
            'metrics' => $preset['metrics']
        ];
        
        if ($date_range) {
            $options['date_ranges'] = [$date_range];
        } elseif (isset($preset['date_ranges'])) {
            $options['date_ranges'] = $preset['date_ranges'];
        }
        
        if (isset($preset['order_bys'])) {
            $options['order_bys'] = $preset['order_bys'];
        }
        
        return $this->run_report($property_id, $options);
    }
    
    /**
     * Sync data to local database
     */
    public function sync_analytics_data($property_id, $date_range = 7) {
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'khm_seo_engagement';
            $start_date = date('Y-m-d', strtotime("-{$date_range} days"));
            $end_date = date('Y-m-d', strtotime('-1 day'));
            
            // Run multiple preset reports for comprehensive data
            $presets_to_sync = ['overview', 'pages', 'traffic_sources', 'audience'];
            
            foreach ($presets_to_sync as $preset) {
                $data = $this->run_preset_report($property_id, $preset, [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ]);
                
                if (!empty($data['rows'])) {
                    $this->store_analytics_data($data, $preset, $property_id);
                }
                
                // Add delay between requests
                usleep(500000); // 500ms
            }
            
            return true;
            
        } catch (\Exception $e) {
            error_log('GA4 Data Sync Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store analytics data in database
     */
    private function store_analytics_data($data, $report_type, $property_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_engagement';
        
        foreach ($data['rows'] as $row) {
            $dimension_values = [];
            foreach ($data['dimension_headers'] as $i => $header) {
                $dimension_values[$header] = $row['dimension_values'][$i] ?? '';
            }
            
            $metric_values = [];
            foreach ($data['metric_headers'] as $i => $header) {
                $metric_values[$header] = $row['metric_values'][$i] ?? 0;
            }
            
            $record_data = [
                'property_id' => $property_id,
                'report_type' => $report_type,
                'date_recorded' => current_time('mysql'),
                'dimensions' => json_encode($dimension_values),
                'metrics' => json_encode($metric_values),
                'page_url' => $dimension_values['pagePath'] ?? null,
                'source' => $dimension_values['source'] ?? null,
                'medium' => $dimension_values['medium'] ?? null,
                'campaign' => $dimension_values['campaign'] ?? null,
                'country' => $dimension_values['country'] ?? null,
                'device_category' => $dimension_values['deviceCategory'] ?? null,
                'active_users' => intval($metric_values['activeUsers'] ?? 0),
                'new_users' => intval($metric_values['newUsers'] ?? 0),
                'sessions' => intval($metric_values['sessions'] ?? 0),
                'bounce_rate' => floatval($metric_values['bounceRate'] ?? 0),
                'avg_session_duration' => floatval($metric_values['avgSessionDuration'] ?? 0),
                'page_views' => intval($metric_values['screenPageViews'] ?? 0),
                'events' => intval($metric_values['eventCount'] ?? 0),
                'conversions' => intval($metric_values['conversions'] ?? 0),
                'revenue' => floatval($metric_values['totalRevenue'] ?? 0),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            // Check if record already exists
            $existing = $wpdb->get_var($wpdb->prepare("
                SELECT id FROM {$table_name} 
                WHERE property_id = %s 
                AND report_type = %s 
                AND dimensions = %s 
                AND DATE(date_recorded) = %s
            ", $property_id, $report_type, json_encode($dimension_values), 
               date('Y-m-d')));
            
            if ($existing) {
                // Update existing record
                $wpdb->update($table_name, array_merge($record_data, ['updated_at' => current_time('mysql')]), 
                             ['id' => $existing]);
            } else {
                // Insert new record
                $wpdb->insert($table_name, $record_data);
            }
        }
    }
    
    /**
     * Parse report response
     */
    private function parse_report_response($data) {
        $parsed = [
            'dimension_headers' => [],
            'metric_headers' => [],
            'rows' => [],
            'row_count' => $data['rowCount'] ?? 0,
            'metadata' => $data['metadata'] ?? [],
            'property_quota' => $data['propertyQuota'] ?? null
        ];
        
        // Extract dimension headers
        if (!empty($data['dimensionHeaders'])) {
            foreach ($data['dimensionHeaders'] as $header) {
                $parsed['dimension_headers'][] = $header['name'];
            }
        }
        
        // Extract metric headers
        if (!empty($data['metricHeaders'])) {
            foreach ($data['metricHeaders'] as $header) {
                $parsed['metric_headers'][] = $header['name'];
            }
        }
        
        // Parse rows
        if (!empty($data['rows'])) {
            foreach ($data['rows'] as $row) {
                $parsed_row = [
                    'dimension_values' => $row['dimensionValues'] ? array_map(function($val) {
                        return $val['value'] ?? '';
                    }, $row['dimensionValues']) : [],
                    'metric_values' => $row['metricValues'] ? array_map(function($val) {
                        return $val['value'] ?? '0';
                    }, $row['metricValues']) : []
                ];
                
                $parsed['rows'][] = $parsed_row;
            }
        }
        
        return $parsed;
    }
    
    /**
     * Parse realtime response
     */
    private function parse_realtime_response($data) {
        return $this->parse_report_response($data); // Same structure
    }
    
    /**
     * Validate dimensions and metrics
     */
    private function validate_dimensions_and_metrics($dimensions, $metrics) {
        foreach ($dimensions as $dimension) {
            if (!array_key_exists($dimension, self::STANDARD_DIMENSIONS)) {
                throw new \Exception("Invalid dimension: {$dimension}");
            }
        }
        
        foreach ($metrics as $metric) {
            if (!array_key_exists($metric, self::STANDARD_METRICS)) {
                throw new \Exception("Invalid metric: {$metric}");
            }
        }
    }
    
    /**
     * Format order by clauses
     */
    private function format_order_bys($order_bys) {
        $formatted = [];
        
        foreach ($order_bys as $order_by) {
            if (isset($order_by['dimension'])) {
                $formatted[] = [
                    'dimension' => ['dimensionName' => $order_by['dimension']],
                    'desc' => $order_by['desc'] ?? false
                ];
            } elseif (isset($order_by['metric'])) {
                $formatted[] = [
                    'metric' => ['metricName' => $order_by['metric']],
                    'desc' => $order_by['desc'] ?? false
                ];
            }
        }
        
        return $formatted;
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_run_report() {
        try {
            if (!current_user_can('manage_options')) {
                throw new \Exception('Insufficient permissions');
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_ga4_report')) {
                throw new \Exception('Security check failed');
            }
            
            $property_id = sanitize_text_field($_POST['property_id'] ?? '');
            $preset = sanitize_text_field($_POST['preset'] ?? '');
            $date_range = $_POST['date_range'] ?? null;
            
            if ($preset && isset(self::REPORT_PRESETS[$preset])) {
                $result = $this->run_preset_report($property_id, $preset, $date_range);
            } else {
                // Custom report
                $dimensions = array_map('sanitize_text_field', $_POST['dimensions'] ?? []);
                $metrics = array_map('sanitize_text_field', $_POST['metrics'] ?? []);
                
                $result = $this->run_report($property_id, [
                    'dimensions' => $dimensions,
                    'metrics' => $metrics,
                    'date_ranges' => $date_range ? [$date_range] : [['start_date' => '30daysAgo', 'end_date' => 'today']]
                ]);
            }
            
            wp_send_json_success([
                'report_data' => $result
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
            
            if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_ga4_properties')) {
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
    
    public function ajax_realtime_data() {
        try {
            if (!current_user_can('manage_options')) {
                throw new \Exception('Insufficient permissions');
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_ga4_realtime')) {
                throw new \Exception('Security check failed');
            }
            
            $property_id = sanitize_text_field($_POST['property_id'] ?? '');
            $dimensions = array_map('sanitize_text_field', $_POST['dimensions'] ?? ['country']);
            $metrics = array_map('sanitize_text_field', $_POST['metrics'] ?? ['activeUsers']);
            
            $result = $this->get_realtime_data($property_id, [
                'dimensions' => $dimensions,
                'metrics' => $metrics
            ]);
            
            wp_send_json_success([
                'realtime_data' => $result
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
    public function hourly_sync() {
        $properties = $this->get_properties();
        foreach ($properties as $property) {
            try {
                // Sync realtime data and recent metrics
                $this->get_realtime_data($property['property_id']);
                sleep(1);
            } catch (\Exception $e) {
                error_log('GA4 Hourly sync error for ' . $property['property_id'] . ': ' . $e->getMessage());
            }
        }
    }
    
    public function daily_sync() {
        $properties = $this->get_properties();
        foreach ($properties as $property) {
            try {
                $this->sync_analytics_data($property['property_id'], 1); // Yesterday's data
                sleep(2);
            } catch (\Exception $e) {
                error_log('GA4 Daily sync error for ' . $property['property_id'] . ': ' . $e->getMessage());
            }
        }
    }
}