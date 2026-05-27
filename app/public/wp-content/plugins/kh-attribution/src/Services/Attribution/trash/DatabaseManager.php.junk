<?php
/**
 * KHM Attribution Database Manager
 * 
 * Handles all database operations, schema management, and data integrity
 * for the attribution system. Implements the same OOP patterns as Phase 2.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Database_Manager {
    
    private $wpdb;
    private $performance_manager;
    private $query_builder;
    private $table_prefix;
    private $schema_version = '1.2.0';
    private $tables = array();
    private $optimization_config = array();
    
    /**
     * Constructor - Initialize database components
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'khm_';
        
        $this->init_database_components();
        $this->setup_table_definitions();
        $this->load_optimization_config();
        $this->register_database_hooks();
    }
    
    /**
     * Initialize database performance components
     */
    private function init_database_components() {
        // Load performance manager
        if (file_exists(dirname(__FILE__) . '/PerformanceManager.php')) {
            require_once dirname(__FILE__) . '/PerformanceManager.php';
            $this->performance_manager = new KHM_Attribution_Performance_Manager();
        }
        
        // Load query builder
        if (file_exists(dirname(__FILE__) . '/QueryBuilder.php')) {
            require_once dirname(__FILE__) . '/QueryBuilder.php';
            $this->query_builder = new KHM_Attribution_Query_Builder();
        }
    }
    
    /**
     * Setup table definitions
     */
    private function setup_table_definitions() {
        $this->tables = array(
            'attribution_events' => array(
                'name' => $this->table_prefix . 'attribution_events',
                'version' => '1.2.0',
                'primary_key' => 'id'
            ),
            'conversion_tracking' => array(
                'name' => $this->table_prefix . 'conversion_tracking',
                'version' => '1.2.0',
                'primary_key' => 'id'
            ),
            'attribution_analytics' => array(
                'name' => $this->table_prefix . 'attribution_analytics',
                'version' => '1.1.0',
                'primary_key' => 'id'
            ),
            'session_tracking' => array(
                'name' => $this->table_prefix . 'session_tracking',
                'version' => '1.0.0',
                'primary_key' => 'session_id'
            )
        );
    }
    
    /**
     * Load optimization configuration
     */
    private function load_optimization_config() {
        $this->optimization_config = array(
            'enable_query_cache' => true,
            'cache_ttl' => 3600,
            'enable_index_hints' => true,
            'batch_insert_size' => 100,
            'connection_timeout' => 30,
            'query_timeout' => 60
        );
    }
    
    /**
     * Register database hooks
     */
    private function register_database_hooks() {
        add_action('init', array($this, 'maybe_update_schema'));
        add_action('wp_footer', array($this, 'optimize_database_queries'));
        
        // Performance hooks
        add_filter('khm_database_query_optimization', array($this, 'apply_query_optimizations'), 10, 2);
        add_action('khm_database_maintenance', array($this, 'run_maintenance_tasks'));
    }
    
    /**
     * Create all required database tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($this->tables as $table_key => $table_config) {
            $this->create_table($table_key);
        }
        
        // Update schema version
        update_option('khm_attribution_schema_version', $this->schema_version);
        
        return true;
    }
    
    /**
     * Create individual table
     */
    public function create_table($table_key) {
        if (!isset($this->tables[$table_key])) {
            return false;
        }
        
        $table_name = $this->tables[$table_key]['name'];
        $charset_collate = $this->wpdb->get_charset_collate();
        
        switch ($table_key) {
            case 'attribution_events':
                $sql = $this->get_attribution_events_schema($table_name, $charset_collate);
                break;
                
            case 'conversion_tracking':
                $sql = $this->get_conversion_tracking_schema($table_name, $charset_collate);
                break;
                
            case 'attribution_analytics':
                $sql = $this->get_attribution_analytics_schema($table_name, $charset_collate);
                break;
                
            case 'session_tracking':
                $sql = $this->get_session_tracking_schema($table_name, $charset_collate);
                break;
                
            default:
                return false;
        }
        
        dbDelta($sql);
        return true;
    }
    
    /**
     * Get attribution events table schema
     */
    private function get_attribution_events_schema($table_name, $charset_collate) {
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            click_id VARCHAR(100) NOT NULL,
            session_id VARCHAR(100) NOT NULL,
            user_id BIGINT NULL,
            affiliate_id BIGINT NOT NULL,
            utm_source VARCHAR(100),
            utm_medium VARCHAR(100),
            utm_campaign VARCHAR(200),
            utm_content VARCHAR(200),
            utm_term VARCHAR(100),
            referrer_url TEXT,
            landing_page TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            screen_resolution VARCHAR(20),
            browser_language VARCHAR(10),
            timezone VARCHAR(50),
            fingerprint_hash VARCHAR(64),
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            attribution_method VARCHAR(50) DEFAULT 'server_side_event',
            
            UNIQUE KEY unique_click_id (click_id),
            INDEX idx_session_affiliate (session_id, affiliate_id),
            INDEX idx_created_expires (created_at, expires_at),
            INDEX idx_attribution_lookup (affiliate_id, created_at),
            INDEX idx_utm_tracking (utm_source, utm_medium, utm_campaign),
            INDEX idx_fingerprint (fingerprint_hash),
            INDEX idx_user_tracking (user_id, created_at)
        ) $charset_collate;";
    }
    
    /**
     * Get conversion tracking table schema
     */
    private function get_conversion_tracking_schema($table_name, $charset_collate) {
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            conversion_id VARCHAR(100) NOT NULL,
            order_id VARCHAR(100),
            click_id VARCHAR(100),
            affiliate_id BIGINT,
            value DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'USD',
            status VARCHAR(20) DEFAULT 'pending',
            attribution_method VARCHAR(50),
            confidence_score DECIMAL(3,2) DEFAULT 0.00,
            created_at DATETIME NOT NULL,
            attributed_at DATETIME NULL,
            
            UNIQUE KEY unique_conversion_id (conversion_id),
            INDEX idx_click_attribution (click_id, affiliate_id),
            INDEX idx_order_lookup (order_id),
            INDEX idx_status_date (status, created_at),
            INDEX idx_attribution_confidence (attribution_method, confidence_score),
            INDEX idx_value_currency (value, currency)
        ) $charset_collate;";
    }
    
    /**
     * Get attribution analytics table schema
     */
    private function get_attribution_analytics_schema($table_name, $charset_collate) {
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            affiliate_id BIGINT NOT NULL,
            utm_source VARCHAR(100),
            utm_medium VARCHAR(100),
            utm_campaign VARCHAR(200),
            clicks INT DEFAULT 0,
            conversions INT DEFAULT 0,
            commission_total DECIMAL(10,2) DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            
            UNIQUE KEY unique_daily_attribution (date, affiliate_id, utm_source, utm_medium, utm_campaign),
            INDEX idx_date_affiliate (date, affiliate_id),
            INDEX idx_performance_lookup (affiliate_id, date),
            INDEX idx_utm_analytics (utm_source, utm_medium),
            INDEX idx_commission_tracking (commission_total, date)
        ) $charset_collate;";
    }
    
    /**
     * Get session tracking table schema
     */
    private function get_session_tracking_schema($table_name, $charset_collate) {
        return "CREATE TABLE IF NOT EXISTS {$table_name} (
            session_id VARCHAR(100) PRIMARY KEY,
            user_id BIGINT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            first_visit DATETIME NOT NULL,
            last_activity DATETIME NOT NULL,
            page_views INT DEFAULT 1,
            total_time_spent INT DEFAULT 0,
            referrer_url TEXT,
            entry_page TEXT,
            exit_page TEXT,
            attribution_data TEXT,
            created_at DATETIME NOT NULL,
            
            INDEX idx_user_sessions (user_id, last_activity),
            INDEX idx_activity_tracking (last_activity),
            INDEX idx_ip_tracking (ip_address, created_at),
            INDEX idx_referrer_analysis (referrer_url(255))
        ) $charset_collate;";
    }
    
    /**
     * Check if schema update is needed
     */
    public function maybe_update_schema() {
        $current_version = get_option('khm_attribution_schema_version', '0.0.0');
        
        if (version_compare($current_version, $this->schema_version, '<')) {
            $this->update_schema($current_version);
        }
    }
    
    /**
     * Update database schema
     */
    private function update_schema($from_version) {
        // Version-specific updates
        if (version_compare($from_version, '1.1.0', '<')) {
            $this->upgrade_to_v1_1_0();
        }
        
        if (version_compare($from_version, '1.2.0', '<')) {
            $this->upgrade_to_v1_2_0();
        }
        
        // Update version
        update_option('khm_attribution_schema_version', $this->schema_version);
    }
    
    /**
     * Upgrade to version 1.1.0
     */
    private function upgrade_to_v1_1_0() {
        // Add new columns for enhanced tracking
        $this->wpdb->query("ALTER TABLE {$this->tables['attribution_events']['name']} 
                           ADD COLUMN IF NOT EXISTS fingerprint_hash VARCHAR(64) AFTER timezone");
        
        $this->wpdb->query("ALTER TABLE {$this->tables['attribution_events']['name']} 
                           ADD INDEX IF NOT EXISTS idx_fingerprint (fingerprint_hash)");
    }
    
    /**
     * Upgrade to version 1.2.0
     */
    private function upgrade_to_v1_2_0() {
        // Create session tracking table
        $this->create_table('session_tracking');
        
        // Add confidence scoring to conversions
        $this->wpdb->query("ALTER TABLE {$this->tables['conversion_tracking']['name']} 
                           ADD COLUMN IF NOT EXISTS confidence_score DECIMAL(3,2) DEFAULT 0.00 AFTER attribution_method");
    }
    
    /**
     * Optimize database queries
     */
    public function optimize_database_queries() {
        if (!$this->optimization_config['enable_query_cache']) {
            return;
        }
        
        // Run optimization tasks
        $this->analyze_slow_queries();
        $this->optimize_table_indexes();
        $this->cleanup_expired_data();
    }
    
    /**
     * Analyze slow queries
     */
    private function analyze_slow_queries() {
        if (!isset($this->performance_manager)) {
            return;
        }
        
        $slow_queries = $this->performance_manager->get_slow_queries();
        
        foreach ($slow_queries as $query) {
            $this->performance_manager->log_performance_issue('slow_query', $query);
        }
    }
    
    /**
     * Optimize table indexes
     */
    private function optimize_table_indexes() {
        foreach ($this->tables as $table_config) {
            $table_name = $table_config['name'];
            
            // Run OPTIMIZE TABLE periodically
            if (rand(1, 100) <= 5) { // 5% chance
                $this->wpdb->query("OPTIMIZE TABLE {$table_name}");
            }
        }
    }
    
    /**
     * Cleanup expired data
     */
    private function cleanup_expired_data() {
        // Clean up expired attribution events
        $events_table = $this->tables['attribution_events']['name'];
        $this->wpdb->query("DELETE FROM {$events_table} WHERE expires_at < NOW()");
        
        // Clean up old analytics data (keep 2 years)
        $analytics_table = $this->tables['attribution_analytics']['name'];
        $this->wpdb->query("DELETE FROM {$analytics_table} WHERE date < DATE_SUB(NOW(), INTERVAL 2 YEAR)");
        
        // Clean up old session data (keep 90 days)
        $sessions_table = $this->tables['session_tracking']['name'];
        $this->wpdb->query("DELETE FROM {$sessions_table} WHERE last_activity < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    }
    
    /**
     * Run maintenance tasks
     */
    public function run_maintenance_tasks() {
        $this->optimize_database_queries();
        $this->cleanup_expired_data();
        $this->analyze_table_performance();
    }
    
    /**
     * Analyze table performance
     */
    private function analyze_table_performance() {
        foreach ($this->tables as $table_key => $table_config) {
            $table_name = $table_config['name'];
            
            // Get table status
            $status = $this->wpdb->get_row("SHOW TABLE STATUS LIKE '{$table_name}'");
            
            if ($status && isset($this->performance_manager)) {
                $this->performance_manager->track_table_performance($table_key, array(
                    'rows' => $status->Rows,
                    'data_length' => $status->Data_length,
                    'index_length' => $status->Index_length,
                    'data_free' => $status->Data_free
                ));
            }
        }
    }
    
    /**
     * Apply query optimizations
     */
    public function apply_query_optimizations($query, $context) {
        if (!$this->optimization_config['enable_index_hints']) {
            return $query;
        }
        
        // Add index hints for common query patterns
        if (strpos($query, 'khm_attribution_events') !== false) {
            if (strpos($query, 'WHERE affiliate_id') !== false) {
                $query = str_replace('khm_attribution_events', 'khm_attribution_events USE INDEX (idx_attribution_lookup)', $query);
            }
        }
        
        return $query;
    }
    
    /**
     * Get table statistics
     */
    public function get_table_statistics() {
        $stats = array();
        
        foreach ($this->tables as $table_key => $table_config) {
            $table_name = $table_config['name'];
            
            $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $size = $this->wpdb->get_var("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'DB Size in MB' FROM information_schema.tables WHERE table_name='{$table_name}'");
            
            $stats[$table_key] = array(
                'rows' => intval($count),
                'size_mb' => floatval($size),
                'table_name' => $table_name
            );
        }
        
        return $stats;
    }
    
    /**
     * Backup attribution data
     */
    public function backup_attribution_data($backup_path = null) {
        if (!$backup_path) {
            $backup_path = WP_CONTENT_DIR . '/khm-attribution-backup-' . date('Y-m-d-H-i-s') . '.sql';
        }
        
        $backup_content = "-- KHM Attribution System Backup\n";
        $backup_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($this->tables as $table_key => $table_config) {
            $table_name = $table_config['name'];
            
            // Export table structure
            $create_table = $this->wpdb->get_row("SHOW CREATE TABLE {$table_name}", ARRAY_N);
            $backup_content .= $create_table[1] . ";\n\n";
            
            // Export table data
            $rows = $this->wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
            
            foreach ($rows as $row) {
                $values = array_map(array($this->wpdb, 'prepare'), array_fill(0, count($row), '%s'));
                $backup_content .= $this->wpdb->prepare("INSERT INTO {$table_name} VALUES (" . implode(',', $values) . ");\n", array_values($row));
            }
            
            $backup_content .= "\n";
        }
        
        file_put_contents($backup_path, $backup_content);
        return $backup_path;
    }
}
?>