<?php
/**
 * KHM Attribution Async Processing Manager
 * 
 * Handles background processing, queue management, and async operations
 * for high-volume attribution tracking scenarios.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Async_Manager {
    
    private $queue_table;
    private $max_processing_time = 25; // seconds
    private $batch_size = 50;
    private $retry_limit = 3;
    private $processing_lock_key = 'khm_async_processing_lock';
    
    public function __construct() {
        global $wpdb;
        $this->queue_table = $wpdb->prefix . 'khm_async_queue';
        
        // Initialize async processing
        $this->setup_async_hooks();
        $this->maybe_create_queue_table();
    }
    
    /**
     * Set up WordPress hooks for async processing
     */
    private function setup_async_hooks() {
        // Background processing hook
        add_action('khm_process_async_queue', array($this, 'process_queue'));
        
        // Schedule regular queue processing
        if (!wp_next_scheduled('khm_process_async_queue')) {
            wp_schedule_event(time(), 'khm_thirty_seconds', 'khm_process_async_queue');
        }
        
        // AJAX handlers for async operations
        add_action('wp_ajax_nopriv_khm_async_track', array($this, 'handle_async_tracking'));
        add_action('wp_ajax_khm_async_track', array($this, 'handle_async_tracking'));
        
        // Shutdown hook for processing remaining items
        add_action('shutdown', array($this, 'process_immediate_queue'));
        
        // Cleanup hook
        add_action('khm_cleanup_async_queue', array($this, 'cleanup_old_queue_items'));
        if (!wp_next_scheduled('khm_cleanup_async_queue')) {
            wp_schedule_event(time(), 'daily', 'khm_cleanup_async_queue');
        }
    }
    
    /**
     * Create async queue table if it doesn't exist
     */
    private function maybe_create_queue_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->queue_table} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            job_type VARCHAR(50) NOT NULL,
            payload LONGTEXT NOT NULL,
            priority TINYINT DEFAULT 5,
            status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            attempts TINYINT DEFAULT 0,
            max_attempts TINYINT DEFAULT 3,
            scheduled_at DATETIME NOT NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            error_message TEXT NULL,
            
            INDEX idx_processing (status, priority, scheduled_at),
            INDEX idx_cleanup (status, completed_at),
            INDEX idx_job_type (job_type, status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Queue a job for async processing
     */
    public function queue_job($job_type, $payload, $priority = 5, $delay = 0) {
        global $wpdb;
        
        $scheduled_at = current_time('mysql');
        if ($delay > 0) {
            $scheduled_at = date('Y-m-d H:i:s', strtotime($scheduled_at) + $delay);
        }
        
        $result = $wpdb->insert(
            $this->queue_table,
            array(
                'job_type' => $job_type,
                'payload' => json_encode($payload),
                'priority' => $priority,
                'scheduled_at' => $scheduled_at,
                'status' => 'pending'
            ),
            array('%s', '%s', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('KHM Attribution: Failed to queue async job - ' . $wpdb->last_error);
            return false;
        }
        
        // Trigger immediate processing for high-priority jobs
        if ($priority <= 2) {
            $this->trigger_immediate_processing();
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Process the async queue
     */
    public function process_queue() {
        // Prevent concurrent processing
        if (!$this->acquire_processing_lock()) {
            return;
        }
        
        $start_time = time();
        $processed_count = 0;
        
        try {
            while ((time() - $start_time) < $this->max_processing_time) {
                $jobs = $this->get_pending_jobs($this->batch_size);
                
                if (empty($jobs)) {
                    break;
                }
                
                foreach ($jobs as $job) {
                    if ((time() - $start_time) >= $this->max_processing_time) {
                        break 2; // Break out of both loops
                    }
                    
                    $this->process_job($job);
                    $processed_count++;
                }
            }
        } finally {
            $this->release_processing_lock();
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG && $processed_count > 0) {
            error_log("KHM Attribution: Processed {$processed_count} async jobs in " . (time() - $start_time) . " seconds");
        }
    }
    
    /**
     * Get pending jobs from queue
     */
    private function get_pending_jobs($limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$this->queue_table}
            WHERE status = 'pending' 
            AND scheduled_at <= NOW()
            ORDER BY priority ASC, scheduled_at ASC
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Process individual job
     */
    private function process_job($job) {
        global $wpdb;
        
        // Mark job as processing
        $wpdb->update(
            $this->queue_table,
            array(
                'status' => 'processing',
                'started_at' => current_time('mysql'),
                'attempts' => $job->attempts + 1
            ),
            array('id' => $job->id),
            array('%s', '%s', '%d'),
            array('%d')
        );
        
        try {
            $payload = json_decode($job->payload, true);
            $result = $this->execute_job($job->job_type, $payload);
            
            if ($result) {
                // Mark as completed
                $wpdb->update(
                    $this->queue_table,
                    array(
                        'status' => 'completed',
                        'completed_at' => current_time('mysql')
                    ),
                    array('id' => $job->id),
                    array('%s', '%s'),
                    array('%d')
                );
            } else {
                $this->handle_job_failure($job, 'Job execution returned false');
            }
            
        } catch (Exception $e) {
            $this->handle_job_failure($job, $e->getMessage());
        }
    }
    
    /**
     * Execute specific job types
     */
    private function execute_job($job_type, $payload) {
        switch ($job_type) {
            case 'attribution_tracking':
                return $this->process_attribution_tracking($payload);
                
            case 'conversion_processing':
                return $this->process_conversion($payload);
                
            case 'analytics_aggregation':
                return $this->process_analytics_aggregation($payload);
                
            case 'cache_warming':
                return $this->process_cache_warming($payload);
                
            case 'report_generation':
                return $this->process_report_generation($payload);
                
            case 'data_cleanup':
                return $this->process_data_cleanup($payload);
                
            default:
                error_log("KHM Attribution: Unknown job type: {$job_type}");
                return false;
        }
    }
    
    /**
     * Process attribution tracking job
     */
    private function process_attribution_tracking($payload) {
        if (!isset($payload['click_data'])) {
            return false;
        }
        
        // Load attribution manager
        require_once dirname(__FILE__) . '/AttributionManager.php';
        $attribution_manager = new KHM_Advanced_Attribution_Manager();
        
        // Process the attribution data
        return $attribution_manager->store_attribution_event($payload['click_data']);
    }
    
    /**
     * Process conversion tracking job
     */
    private function process_conversion($payload) {
        if (!isset($payload['conversion_data'])) {
            return false;
        }
        
        // Load attribution manager
        require_once dirname(__FILE__) . '/AttributionManager.php';
        $attribution_manager = new KHM_Advanced_Attribution_Manager();
        
        // Process the conversion
        return $attribution_manager->process_conversion($payload['conversion_data']);
    }
    
    /**
     * Process analytics aggregation job
     */
    private function process_analytics_aggregation($payload) {
        global $wpdb;
        
        $date_range = $payload['date_range'] ?? array(
            'start' => date('Y-m-d', strtotime('-1 day')),
            'end' => date('Y-m-d')
        );
        
        // Aggregate attribution data for reporting
        $table_events = $wpdb->prefix . 'khm_attribution_events';
        $table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
        $table_analytics = $wpdb->prefix . 'khm_attribution_analytics';
        
        // Create analytics aggregation
        $sql = "INSERT INTO {$table_analytics} 
                (date, affiliate_id, utm_source, utm_medium, clicks, conversions, commission_total, created_at)
                SELECT 
                    DATE(e.created_at) as date,
                    e.affiliate_id,
                    e.utm_source,
                    e.utm_medium,
                    COUNT(DISTINCT e.click_id) as clicks,
                    COUNT(DISTINCT c.id) as conversions,
                    COALESCE(SUM(c.commission_amount), 0) as commission_total,
                    NOW() as created_at
                FROM {$table_events} e
                LEFT JOIN {$table_conversions} c ON e.click_id = c.click_id
                WHERE DATE(e.created_at) BETWEEN %s AND %s
                GROUP BY DATE(e.created_at), e.affiliate_id, e.utm_source, e.utm_medium
                ON DUPLICATE KEY UPDATE
                    clicks = VALUES(clicks),
                    conversions = VALUES(conversions),
                    commission_total = VALUES(commission_total),
                    updated_at = NOW()";
        
        return $wpdb->query($wpdb->prepare($sql, $date_range['start'], $date_range['end']));
    }
    
    /**
     * Process cache warming job
     */
    private function process_cache_warming($payload) {
        $cache_keys = $payload['cache_keys'] ?? array();
        
        // Load performance manager
        require_once dirname(__FILE__) . '/PerformanceManager.php';
        $performance_manager = new KHM_Attribution_Performance_Manager();
        
        foreach ($cache_keys as $cache_config) {
            $key = $cache_config['key'];
            $sql = $cache_config['sql'];
            $ttl = $cache_config['ttl'] ?? 3600;
            
            // Execute query and cache result
            $performance_manager->execute_cached_query($sql, $key, $ttl);
        }
        
        return true;
    }
    
    /**
     * Process report generation job
     */
    private function process_report_generation($payload) {
        $report_type = $payload['report_type'] ?? 'daily_summary';
        $recipient = $payload['recipient'] ?? null;
        $date_range = $payload['date_range'] ?? array();
        
        // Generate report based on type
        switch ($report_type) {
            case 'daily_summary':
                return $this->generate_daily_summary_report($date_range, $recipient);
                
            case 'affiliate_performance':
                return $this->generate_affiliate_performance_report($date_range, $recipient);
                
            case 'attribution_analysis':
                return $this->generate_attribution_analysis_report($date_range, $recipient);
                
            default:
                return false;
        }
    }
    
    /**
     * Process data cleanup job
     */
    private function process_data_cleanup($payload) {
        global $wpdb;
        
        $cleanup_type = $payload['cleanup_type'] ?? 'expired_events';
        
        switch ($cleanup_type) {
            case 'expired_events':
                $table_events = $wpdb->prefix . 'khm_attribution_events';
                return $wpdb->query("DELETE FROM {$table_events} WHERE expires_at < NOW()");
                
            case 'old_conversions':
                $table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
                $days = $payload['days'] ?? 90;
                return $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$table_conversions} WHERE status = 'attributed' AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $days
                ));
                
            case 'optimize_tables':
                $table_events = $wpdb->prefix . 'khm_attribution_events';
                $table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
                $wpdb->query("OPTIMIZE TABLE {$table_events}");
                $wpdb->query("OPTIMIZE TABLE {$table_conversions}");
                return true;
                
            default:
                return false;
        }
    }
    
    /**
     * Handle job failure
     */
    private function handle_job_failure($job, $error_message) {
        global $wpdb;
        
        $attempts = $job->attempts + 1;
        
        if ($attempts >= $job->max_attempts) {
            // Mark as permanently failed
            $wpdb->update(
                $this->queue_table,
                array(
                    'status' => 'failed',
                    'error_message' => $error_message,
                    'completed_at' => current_time('mysql')
                ),
                array('id' => $job->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            error_log("KHM Attribution: Job {$job->id} permanently failed after {$attempts} attempts: {$error_message}");
        } else {
            // Retry with exponential backoff
            $delay = pow(2, $attempts) * 60; // 2, 4, 8 minutes
            $retry_time = date('Y-m-d H:i:s', time() + $delay);
            
            $wpdb->update(
                $this->queue_table,
                array(
                    'status' => 'pending',
                    'scheduled_at' => $retry_time,
                    'error_message' => $error_message
                ),
                array('id' => $job->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Acquire processing lock to prevent concurrent processing
     */
    private function acquire_processing_lock() {
        $lock_time = get_transient($this->processing_lock_key);
        
        if ($lock_time && (time() - $lock_time) < 60) {
            return false; // Lock is still active
        }
        
        set_transient($this->processing_lock_key, time(), 60);
        return true;
    }
    
    /**
     * Release processing lock
     */
    private function release_processing_lock() {
        delete_transient($this->processing_lock_key);
    }
    
    /**
     * Trigger immediate processing for high-priority jobs
     */
    private function trigger_immediate_processing() {
        if (!wp_doing_ajax() && !wp_doing_cron()) {
            wp_schedule_single_event(time(), 'khm_process_async_queue');
        }
    }
    
    /**
     * Process immediate queue items on shutdown
     */
    public function process_immediate_queue() {
        global $wpdb;
        
        // Get high-priority pending jobs
        $urgent_jobs = $wpdb->get_results("
            SELECT * FROM {$this->queue_table}
            WHERE status = 'pending' 
            AND priority <= 2
            AND scheduled_at <= NOW()
            ORDER BY priority ASC, scheduled_at ASC
            LIMIT 5
        ");
        
        foreach ($urgent_jobs as $job) {
            $this->process_job($job);
        }
    }
    
    /**
     * Handle async tracking AJAX request
     */
    public function handle_async_tracking() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'khm_async_track')) {
            wp_die('Security check failed');
        }
        
        $tracking_data = $_POST['tracking_data'] ?? array();
        
        if (empty($tracking_data)) {
            wp_send_json_error('No tracking data provided');
        }
        
        // Queue the tracking job
        $job_id = $this->queue_job('attribution_tracking', array(
            'click_data' => $tracking_data
        ), 3); // Medium priority
        
        if ($job_id) {
            wp_send_json_success(array('job_id' => $job_id));
        } else {
            wp_send_json_error('Failed to queue tracking job');
        }
    }
    
    /**
     * Clean up old queue items
     */
    public function cleanup_old_queue_items() {
        global $wpdb;
        
        // Remove completed jobs older than 7 days
        $wpdb->query("
            DELETE FROM {$this->queue_table} 
            WHERE status IN ('completed', 'failed') 
            AND completed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        // Remove very old pending jobs (30 days)
        $wpdb->query("
            DELETE FROM {$this->queue_table} 
            WHERE status = 'pending' 
            AND scheduled_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        // Optimize the queue table
        $wpdb->query("OPTIMIZE TABLE {$this->queue_table}");
    }
    
    /**
     * Get queue statistics
     */
    public function get_queue_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_row("
            SELECT 
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                COUNT(*) as total
            FROM {$this->queue_table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ", ARRAY_A);
        
        return $stats ?: array(
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'total' => 0
        );
    }
    
    /**
     * Generate daily summary report
     */
    private function generate_daily_summary_report($date_range, $recipient) {
        // Implementation would generate and potentially email daily summary
        // This is a placeholder for the actual report generation logic
        return true;
    }
    
    /**
     * Generate affiliate performance report
     */
    private function generate_affiliate_performance_report($date_range, $recipient) {
        // Implementation would generate affiliate performance analysis
        return true;
    }
    
    /**
     * Generate attribution analysis report
     */
    private function generate_attribution_analysis_report($date_range, $recipient) {
        // Implementation would generate attribution method analysis
        return true;
    }
}
?>