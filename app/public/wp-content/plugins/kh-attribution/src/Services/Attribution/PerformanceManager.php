<?php
/**
 * KHM Advanced Attribution Performance Manager
 * 
 * Handles caching, query optimization, and performance monitoring for the attribution system.
 * Designed to achieve enterprise SLO targets: p95 < 2s dashboards, < 300ms tracking endpoints.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Performance_Manager {
    
    private $cache_prefix = 'khm_attr_';
    private $cache_method = 'transient';
    private $cache_ttl = 3600; // 1 hour default
    private $performance_metrics = array();
    private $query_cache = array();
    private $batch_queue = array();
    private $max_batch_size = 100;
    
    public function __construct() {
        // Initialize performance monitoring
        $this->init_performance_monitoring();
        
        // Set up caching configuration
        $this->configure_caching();
        
        // Register batch processing hooks
        $this->setup_batch_processing();
        
        // Database optimization hooks
        add_action('wp_loaded', array($this, 'optimize_database_indexes'));
    }
    
    /**
     * Initialize performance monitoring
     */
    private function init_performance_monitoring() {
        $this->performance_metrics = array(
            'query_times' => array(),
            'cache_hits' => 0,
            'cache_misses' => 0,
            'api_response_times' => array(),
            'memory_usage' => array(),
            'batch_processing_times' => array()
        );
        
        // Start memory tracking
        $this->track_memory('init');
    }
    
    /**
     * Configure caching based on available systems
     */
    private function configure_caching() {
        // Check for object cache availability
        if (function_exists('wp_cache_get') && wp_using_ext_object_cache()) {
            $this->cache_method = 'object_cache';
        } elseif (class_exists('Redis') && defined('WP_REDIS_HOST')) {
            $this->cache_method = 'redis';
        } elseif (class_exists('Memcached') && defined('WP_MEMCACHED_HOST')) {
            $this->cache_method = 'memcached';
        } else {
            $this->cache_method = 'transient';
        }
        
        // Set TTL based on cache method
        switch ($this->cache_method) {
            case 'redis':
            case 'memcached':
                $this->cache_ttl = 7200; // 2 hours for external cache
                break;
            case 'object_cache':
                $this->cache_ttl = 3600; // 1 hour for WP object cache
                break;
            default:
                $this->cache_ttl = 900; // 15 minutes for transients
                break;
        }
    }
    
    /**
     * Set up batch processing for high-volume operations
     */
    private function setup_batch_processing() {
        // Process batches every 5 minutes
        add_action('khm_process_attribution_batch', array($this, 'process_attribution_batch'));
        
        if (!wp_next_scheduled('khm_process_attribution_batch')) {
            wp_schedule_event(time(), 'khm_five_minutes', 'khm_process_attribution_batch');
        }
        
        // Custom cron interval
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals($schedules) {
        $schedules['khm_five_minutes'] = array(
            'interval' => 300,
            'display' => 'Every 5 Minutes'
        );
        
        $schedules['khm_thirty_seconds'] = array(
            'interval' => 30,
            'display' => 'Every 30 Seconds'
        );
        
        return $schedules;
    }
    
    /**
     * Optimized query execution with caching
     */
    public function execute_cached_query($sql, $cache_key = null, $ttl = null) {
        $start_time = microtime(true);
        
        // Generate cache key if not provided
        if (!$cache_key) {
            $cache_key = $this->cache_prefix . md5($sql);
        }
        
        // Try to get from cache first
        $cached_result = $this->get_cache($cache_key);
        if ($cached_result !== false) {
            $this->performance_metrics['cache_hits']++;
            $this->track_query_time($start_time, 'cache_hit');
            return $cached_result;
        }
        
        $this->performance_metrics['cache_misses']++;
        
        // Execute query
        global $wpdb;
        $result = $wpdb->get_results($sql);
        
        // Cache the result
        $this->set_cache($cache_key, $result, $ttl ?: $this->cache_ttl);
        
        $this->track_query_time($start_time, 'database');
        return $result;
    }
    
    /**
     * Optimized attribution lookup with aggressive caching
     */
    public function get_attribution_data($session_id, $user_id = null, $lookback_days = 30) {
        $cache_key = $this->cache_prefix . "attribution_{$session_id}_{$user_id}_{$lookback_days}";
        
        // Try cache first
        $cached_data = $this->get_cache($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Build optimized query
        global $wpdb;
        $table_events = $wpdb->prefix . 'khm_attribution_events';
        
        $sql = $wpdb->prepare("
            SELECT 
                click_id, 
                affiliate_id, 
                utm_source, 
                utm_medium, 
                utm_campaign, 
                attribution_method,
                created_at,
                expires_at
            FROM {$table_events} 
            WHERE (session_id = %s OR user_id = %d)
                AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                AND expires_at > NOW()
            ORDER BY created_at DESC
            LIMIT 50
        ", $session_id, $user_id ?: 0, $lookback_days);
        
        $result = $this->execute_cached_query($sql, $cache_key, 1800); // 30 minute cache
        
        return $result;
    }
    
    /**
     * Batch process attribution events for performance
     */
    public function queue_attribution_event($event_data) {
        // Add to batch queue
        $this->batch_queue[] = $event_data;
        
        // Process immediately if queue is full or if this is a high-priority event
        if (count($this->batch_queue) >= $this->max_batch_size || 
            (isset($event_data['priority']) && $event_data['priority'] === 'high')) {
            $this->process_attribution_batch();
        }
    }
    
    /**
     * Process batched attribution events
     */
    public function process_attribution_batch() {
        if (empty($this->batch_queue)) {
            return;
        }
        
        $start_time = microtime(true);
        
        global $wpdb;
        $table_events = $wpdb->prefix . 'khm_attribution_events';
        
        // Prepare batch insert
        $values = array();
        $placeholders = array();
        
        foreach ($this->batch_queue as $event) {
            $values[] = $event['click_id'];
            $values[] = $event['affiliate_id'];
            $values[] = $event['session_id'];
            $values[] = $event['user_id'] ?: null;
            $values[] = $event['utm_source'] ?: null;
            $values[] = $event['utm_medium'] ?: null;
            $values[] = $event['utm_campaign'] ?: null;
            $values[] = $event['utm_content'] ?: null;
            $values[] = $event['utm_term'] ?: null;
            $values[] = $event['ip_address'];
            $values[] = $event['user_agent'];
            $values[] = $event['referrer_url'] ?: null;
            $values[] = $event['landing_page'] ?: null;
            $values[] = $event['screen_resolution'] ?: null;
            $values[] = $event['browser_language'] ?: null;
            $values[] = $event['timezone'] ?: null;
            $values[] = $event['fingerprint_hash'] ?: null;
            $values[] = $event['created_at'];
            $values[] = $event['expires_at'];
            $values[] = $event['attribution_method'];
            
            $placeholders[] = '(%s, %d, %s, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)';
        }
        
        $sql = "INSERT INTO {$table_events} 
                (click_id, affiliate_id, session_id, user_id, utm_source, utm_medium, utm_campaign, 
                 utm_content, utm_term, ip_address, user_agent, referrer_url, landing_page, 
                 screen_resolution, browser_language, timezone, fingerprint_hash, created_at, expires_at, attribution_method) 
                VALUES " . implode(', ', $placeholders);
        
        $result = $wpdb->query($wpdb->prepare($sql, $values));
        
        // Clear the queue
        $batch_size = count($this->batch_queue);
        $this->batch_queue = array();
        
        // Track performance
        $processing_time = microtime(true) - $start_time;
        $this->performance_metrics['batch_processing_times'][] = $processing_time;
        
        // Log successful batch processing
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("KHM Attribution: Processed batch of {$batch_size} events in {$processing_time}s");
        }
        
        return $result;
    }
    
    /**
     * Optimize database indexes for attribution tables
     */
    public function optimize_database_indexes() {
        global $wpdb;
        
        $table_events = $wpdb->prefix . 'khm_attribution_events';
        $table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
        
        // Check if indexes exist and create if missing
        $this->ensure_index($table_events, 'idx_session_user_lookup', '(session_id, user_id, created_at)');
        $this->ensure_index($table_events, 'idx_affiliate_performance', '(affiliate_id, created_at)');
        $this->ensure_index($table_events, 'idx_utm_analysis', '(utm_source, utm_medium, created_at)');
        $this->ensure_index($table_events, 'idx_expiration_cleanup', '(expires_at)');
        
        $this->ensure_index($table_conversions, 'idx_attribution_lookup', '(click_id, status)');
        $this->ensure_index($table_conversions, 'idx_affiliate_commissions', '(affiliate_id, created_at, status)');
        $this->ensure_index($table_conversions, 'idx_order_tracking', '(order_id, processed_at)');
    }
    
    /**
     * Ensure database index exists
     */
    private function ensure_index($table, $index_name, $columns) {
        global $wpdb;
        
        // Check if index exists
        $index_exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE table_schema = %s 
            AND table_name = %s 
            AND index_name = %s
        ", DB_NAME, $table, $index_name));
        
        if (!$index_exists) {
            $sql = "ALTER TABLE {$table} ADD INDEX {$index_name} {$columns}";
            $wpdb->query($sql);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("KHM Attribution: Created index {$index_name} on {$table}");
            }
        }
    }
    
    /**
     * Cache methods with fallback support
     */
    public function get_cache($key) {
        switch ($this->cache_method) {
            case 'redis':
                return $this->get_redis_cache($key);
            case 'memcached':
                return $this->get_memcached_cache($key);
            case 'object_cache':
                return wp_cache_get($key, 'khm_attribution');
            default:
                return get_transient($key);
        }
    }
    
    public function set_cache($key, $value, $ttl = null) {
        $ttl = $ttl ?: $this->cache_ttl;
        
        switch ($this->cache_method) {
            case 'redis':
                return $this->set_redis_cache($key, $value, $ttl);
            case 'memcached':
                return $this->set_memcached_cache($key, $value, $ttl);
            case 'object_cache':
                return wp_cache_set($key, $value, 'khm_attribution', $ttl);
            default:
                return set_transient($key, $value, $ttl);
        }
    }
    
    public function delete_cache($key) {
        switch ($this->cache_method) {
            case 'redis':
                return $this->delete_redis_cache($key);
            case 'memcached':
                return $this->delete_memcached_cache($key);
            case 'object_cache':
                return wp_cache_delete($key, 'khm_attribution');
            default:
                return delete_transient($key);
        }
    }
    
    /**
     * Redis cache implementation
     */
    private function get_redis_cache($key) {
        if (!class_exists('Redis')) {
            return false;
        }
        
        try {
            $redis = new Redis();
            $redis->connect(defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1', 
                           defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379);
            
            $value = $redis->get($this->cache_prefix . $key);
            $redis->close();
            
            return $value ? unserialize($value) : false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function set_redis_cache($key, $value, $ttl) {
        if (!class_exists('Redis')) {
            return false;
        }
        
        try {
            $redis = new Redis();
            $redis->connect(defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1', 
                           defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379);
            
            $result = $redis->setex($this->cache_prefix . $key, $ttl, serialize($value));
            $redis->close();
            
            return $result;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function delete_redis_cache($key) {
        if (!class_exists('Redis')) {
            return false;
        }
        
        try {
            $redis = new Redis();
            $redis->connect(defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1', 
                           defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379);
            
            $result = $redis->del($this->cache_prefix . $key);
            $redis->close();
            
            return $result;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Memcached cache implementation
     */
    private function get_memcached_cache($key) {
        if (!class_exists('Memcached')) {
            return false;
        }
        
        try {
            $memcached = new Memcached();
            $memcached->addServer(defined('WP_MEMCACHED_HOST') ? WP_MEMCACHED_HOST : '127.0.0.1', 
                                 defined('WP_MEMCACHED_PORT') ? WP_MEMCACHED_PORT : 11211);
            
            $value = $memcached->get($this->cache_prefix . $key);
            return $memcached->getResultCode() === Memcached::RES_SUCCESS ? $value : false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function set_memcached_cache($key, $value, $ttl) {
        if (!class_exists('Memcached')) {
            return false;
        }
        
        try {
            $memcached = new Memcached();
            $memcached->addServer(defined('WP_MEMCACHED_HOST') ? WP_MEMCACHED_HOST : '127.0.0.1', 
                                 defined('WP_MEMCACHED_PORT') ? WP_MEMCACHED_PORT : 11211);
            
            return $memcached->set($this->cache_prefix . $key, $value, $ttl);
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function delete_memcached_cache($key) {
        if (!class_exists('Memcached')) {
            return false;
        }
        
        try {
            $memcached = new Memcached();
            $memcached->addServer(defined('WP_MEMCACHED_HOST') ? WP_MEMCACHED_HOST : '127.0.0.1', 
                                 defined('WP_MEMCACHED_PORT') ? WP_MEMCACHED_PORT : 11211);
            
            return $memcached->delete($this->cache_prefix . $key);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Performance tracking methods
     */
    private function track_query_time($start_time, $type = 'query') {
        $execution_time = microtime(true) - $start_time;
        $this->performance_metrics['query_times'][] = array(
            'time' => $execution_time,
            'type' => $type,
            'timestamp' => time()
        );
        
        // Keep only last 100 measurements
        if (count($this->performance_metrics['query_times']) > 100) {
            $this->performance_metrics['query_times'] = array_slice(
                $this->performance_metrics['query_times'], -100
            );
        }
    }
    
    private function track_memory($checkpoint) {
        $this->performance_metrics['memory_usage'][$checkpoint] = array(
            'memory' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'timestamp' => time()
        );
    }
    
    public function track_api_response_time($endpoint, $start_time) {
        $response_time = microtime(true) - $start_time;
        
        if (!isset($this->performance_metrics['api_response_times'][$endpoint])) {
            $this->performance_metrics['api_response_times'][$endpoint] = array();
        }
        
        $this->performance_metrics['api_response_times'][$endpoint][] = array(
            'time' => $response_time,
            'timestamp' => time()
        );
        
        // Keep only last 50 measurements per endpoint
        if (count($this->performance_metrics['api_response_times'][$endpoint]) > 50) {
            $this->performance_metrics['api_response_times'][$endpoint] = array_slice(
                $this->performance_metrics['api_response_times'][$endpoint], -50
            );
        }
        
        // Alert if response time exceeds SLO
        if ($response_time > 0.3) { // 300ms SLO
            $this->log_performance_alert($endpoint, $response_time);
        }
    }
    
    /**
     * Get performance metrics
     */
    public function get_performance_metrics() {
        // Calculate statistics
        $query_times = array_column($this->performance_metrics['query_times'], 'time');
        $cache_total = $this->performance_metrics['cache_hits'] + $this->performance_metrics['cache_misses'];
        
        return array(
            'query_performance' => array(
                'avg_time' => !empty($query_times) ? array_sum($query_times) / count($query_times) : 0,
                'p95_time' => $this->calculate_percentile($query_times, 95),
                'total_queries' => count($query_times)
            ),
            'cache_performance' => array(
                'hit_rate' => $cache_total > 0 ? ($this->performance_metrics['cache_hits'] / $cache_total) * 100 : 0,
                'total_hits' => $this->performance_metrics['cache_hits'],
                'total_misses' => $this->performance_metrics['cache_misses'],
                'cache_method' => $this->cache_method
            ),
            'api_performance' => $this->calculate_api_stats(),
            'memory_usage' => $this->performance_metrics['memory_usage'],
            'batch_performance' => array(
                'avg_time' => !empty($this->performance_metrics['batch_processing_times']) ? 
                             array_sum($this->performance_metrics['batch_processing_times']) / 
                             count($this->performance_metrics['batch_processing_times']) : 0,
                'total_batches' => count($this->performance_metrics['batch_processing_times'])
            )
        );
    }
    
    /**
     * Calculate percentile
     */
    private function calculate_percentile($array, $percentile) {
        if (empty($array)) {
            return 0;
        }
        
        sort($array);
        $index = ($percentile / 100) * (count($array) - 1);
        
        if (floor($index) == $index) {
            return $array[$index];
        } else {
            $lower = $array[floor($index)];
            $upper = $array[ceil($index)];
            return $lower + ($upper - $lower) * ($index - floor($index));
        }
    }
    
    /**
     * Calculate API statistics
     */
    private function calculate_api_stats() {
        $stats = array();
        
        foreach ($this->performance_metrics['api_response_times'] as $endpoint => $times) {
            $response_times = array_column($times, 'time');
            
            $stats[$endpoint] = array(
                'avg_time' => array_sum($response_times) / count($response_times),
                'p95_time' => $this->calculate_percentile($response_times, 95),
                'total_requests' => count($response_times),
                'slo_violations' => count(array_filter($response_times, function($time) {
                    return $time > 0.3; // 300ms SLO
                }))
            );
        }
        
        return $stats;
    }
    
    /**
     * Log performance alerts
     */
    private function log_performance_alert($endpoint, $response_time) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("KHM Attribution Performance Alert: {$endpoint} took {$response_time}s (SLO: 300ms)");
        }
        
        // Could integrate with monitoring services here
    }
    
    /**
     * Clean up old performance data
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        $table_events = $wpdb->prefix . 'khm_attribution_events';
        $table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
        
        // Clean up expired attribution events
        $wpdb->query("DELETE FROM {$table_events} WHERE expires_at < NOW()");
        
        // Clean up old processed conversions (keep for 90 days)
        $wpdb->query("DELETE FROM {$table_conversions} WHERE status = 'attributed' AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        
        // Optimize tables after cleanup
        $wpdb->query("OPTIMIZE TABLE {$table_events}");
        $wpdb->query("OPTIMIZE TABLE {$table_conversions}");
        
        $this->track_memory('cleanup_complete');
    }
}
?>
