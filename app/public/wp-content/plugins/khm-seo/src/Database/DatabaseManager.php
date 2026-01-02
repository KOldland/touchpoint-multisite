<?php
/**
 * Database Manager for SEO Measurement Module
 * 
 * This is a comprehensive database architecture for the SEO intelligence platform
 * that creates and manages 10 specialized tables for storing SEO measurement data.
 * 
 * @package KHM_SEO
 * @subpackage Database
 * @since 9.0.0
 */

namespace KHM_SEO\Database;

class DatabaseManager {
    
    /**
     * Database version for migrations
     */
    const DB_VERSION = '9.0.0';
    
    /**
     * Option key for storing database version
     */
    const VERSION_OPTION = 'khm_seo_measurement_db_version';
    
    /**
     * WordPress database object
     * @var \wpdb
     */
    private $wpdb;
    
    /**
     * Table prefix including WordPress prefix
     * @var string
     */
    private $table_prefix;
    
    /**
     * All table names
     * @var array
     */
    private $table_names;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'seo_measurement_';
        
        $this->table_names = [
            'gsc_stats' => $this->table_prefix . 'gsc_stats',
            'crawl_data' => $this->table_prefix . 'crawl_data', 
            'link_graph' => $this->table_prefix . 'link_graph',
            'engagement' => $this->table_prefix . 'engagement',
            'trends' => $this->table_prefix . 'trends',
            'schema_validation' => $this->table_prefix . 'schema_validation',
            'cwv_metrics' => $this->table_prefix . 'cwv_metrics',
            'sitemap_status' => $this->table_prefix . 'sitemap_status',
            'alerts' => $this->table_prefix . 'alerts',
            'scores' => $this->table_prefix . 'scores'
        ];
    }
    
    /**
     * Initialize database schema
     * Called on plugin activation and version updates
     */
    public function initialize() {
        $current_version = get_option(self::VERSION_OPTION, '0.0.0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->create_all_tables();
            $this->schedule_cleanup_jobs();
            update_option(self::VERSION_OPTION, self::DB_VERSION);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Create all required tables
     */
    private function create_all_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create each table
        $this->create_gsc_stats_table();
        $this->create_crawl_data_table();
        $this->create_link_graph_table();
        $this->create_engagement_table();
        $this->create_trends_table();
        $this->create_schema_validation_table();
        $this->create_cwv_metrics_table();
        $this->create_sitemap_status_table();
        $this->create_alerts_table();
        $this->create_scores_table();
    }
    
    /**
     * Google Search Console Statistics Table
     * Primary purpose: Store GSC API performance data
     */
    private function create_gsc_stats_table() {
        $table_name = $this->table_names['gsc_stats'];
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            fetch_date DATE NOT NULL,
            page_url VARCHAR(2048) NOT NULL,
            search_query VARCHAR(2048) NOT NULL,
            device ENUM('DESKTOP', 'MOBILE', 'TABLET') NOT NULL,
            country CHAR(3) NOT NULL DEFAULT 'USA',
            clicks INT UNSIGNED NOT NULL DEFAULT 0,
            impressions INT UNSIGNED NOT NULL DEFAULT 0,
            ctr DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
            average_position DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_date_url_query_device (fetch_date, page_url(255), search_query(255), device, country),
            KEY idx_page_url (page_url(255)),
            KEY idx_search_query (search_query(255)),
            KEY idx_fetch_date (fetch_date),
            KEY idx_clicks_desc (clicks DESC),
            KEY idx_impressions_desc (impressions DESC),
            KEY idx_position_asc (average_position ASC),
            KEY idx_device (device),
            KEY idx_country (country)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Google Search Console performance statistics'";
        
        dbDelta($sql);
    }
    
    /**
     * Internal Crawler Data Table
     * Primary purpose: Store page-level crawl analysis results
     */
    private function create_crawl_data_table() {
        $table_name = $this->table_names['crawl_data'];
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url VARCHAR(2048) NOT NULL,
            url_hash CHAR(64) NOT NULL,
            status_code SMALLINT UNSIGNED NOT NULL,
            canonical_url VARCHAR(2048),
            page_title VARCHAR(1024),
            title_length SMALLINT UNSIGNED,
            meta_description TEXT,
            meta_description_length SMALLINT UNSIGNED,
            h1_count TINYINT UNSIGNED DEFAULT 0,
            h1_text VARCHAR(1024),
            meta_robots VARCHAR(255),
            content_length INT UNSIGNED DEFAULT 0,
            page_weight_bytes INT UNSIGNED DEFAULT 0,
            internal_links_count SMALLINT UNSIGNED DEFAULT 0,
            external_links_count SMALLINT UNSIGNED DEFAULT 0,
            is_orphaned BOOLEAN DEFAULT FALSE,
            has_noindex BOOLEAN DEFAULT FALSE,
            redirect_chain_length TINYINT UNSIGNED DEFAULT 0,
            redirect_target VARCHAR(2048),
            crawl_errors TEXT,
            last_crawled DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            first_discovered DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            crawl_duration_ms SMALLINT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_url_hash (url_hash),
            KEY idx_url (url(255)),
            KEY idx_status_code (status_code),
            KEY idx_last_crawled (last_crawled),
            KEY idx_is_orphaned (is_orphaned),
            KEY idx_has_noindex (has_noindex),
            KEY idx_title_length (title_length),
            KEY idx_meta_desc_length (meta_description_length),
            KEY idx_h1_count (h1_count)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Internal crawler page analysis data'";
        
        dbDelta($sql);
    }
    
    /**
     * Link Graph Table
     * Primary purpose: Store internal link relationships and anchor text analysis
     */
    private function create_link_graph_table() {
        $table_name = $this->table_names['link_graph'];
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_url VARCHAR(2048) NOT NULL,
            source_url_hash CHAR(64) NOT NULL,
            target_url VARCHAR(2048) NOT NULL,
            target_url_hash CHAR(64) NOT NULL,
            anchor_text VARCHAR(1024),
            link_type ENUM('internal', 'external', 'mailto', 'tel') NOT NULL DEFAULT 'internal',
            rel_attributes VARCHAR(255),
            is_followed BOOLEAN DEFAULT TRUE,
            link_position SMALLINT UNSIGNED DEFAULT 0,
            context_before VARCHAR(512),
            context_after VARCHAR(512),
            discovered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_verified DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_source_target_anchor (source_url_hash, target_url_hash, anchor_text(255)),
            KEY idx_source_url_hash (source_url_hash),
            KEY idx_target_url_hash (target_url_hash),
            KEY idx_link_type (link_type),
            KEY idx_is_followed (is_followed),
            KEY idx_is_active (is_active),
            KEY idx_anchor_text (anchor_text(255))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Internal and external link graph relationships'";
        
        dbDelta($sql);
    }
    
    /**
     * GA4 Engagement Data Table
     * Primary purpose: Store Google Analytics 4 engagement metrics
     */
    private function create_engagement_table() {
        $table_name = $this->table_names['engagement'];
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            date_recorded DATE NOT NULL,
            page_url VARCHAR(2048) NOT NULL,
            url_hash CHAR(64) NOT NULL,
            page_views INT UNSIGNED DEFAULT 0,
            unique_page_views INT UNSIGNED DEFAULT 0,
            avg_session_duration DECIMAL(8,2) DEFAULT 0.00,
            bounce_rate DECIMAL(5,4) DEFAULT 0.0000,
            entrances INT UNSIGNED DEFAULT 0,
            exits INT UNSIGNED DEFAULT 0,
            page_value DECIMAL(10,2) DEFAULT 0.00,
            goal_completions INT UNSIGNED DEFAULT 0,
            goal_value DECIMAL(10,2) DEFAULT 0.00,
            time_on_page DECIMAL(8,2) DEFAULT 0.00,
            source_medium VARCHAR(255),
            device_category ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_date_url_device (date_recorded, url_hash, device_category),
            KEY idx_url_hash (url_hash),
            KEY idx_date_recorded (date_recorded),
            KEY idx_page_views_desc (page_views DESC),
            KEY idx_avg_session_duration (avg_session_duration DESC),
            KEY idx_bounce_rate (bounce_rate ASC),
            KEY idx_device_category (device_category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='GA4 engagement metrics per page and device'";
        
        dbDelta($sql);
    }
    
    /**
     * Trends Analysis Table
     * Primary purpose: Store computed trends and change detection analysis
     */
    private function create_trends_table() {
        $table_name = $this->table_names['trends'];
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_url VARCHAR(2048) NOT NULL,
            url_hash CHAR(64) NOT NULL,
            metric_type ENUM('clicks', 'impressions', 'position', 'ctr', 'engagement', 'cwv') NOT NULL,
            time_window ENUM('7d', '28d', '90d') NOT NULL,
            current_value DECIMAL(12,4) NOT NULL,
            previous_value DECIMAL(12,4) NOT NULL,
            change_percentage DECIMAL(8,4) NOT NULL,
            change_absolute DECIMAL(12,4) NOT NULL,
            trend_direction ENUM('up', 'down', 'stable') NOT NULL,
            is_significant BOOLEAN DEFAULT FALSE,
            confidence_score DECIMAL(5,4) DEFAULT 0.0000,
            analysis_date DATE NOT NULL,
            new_keywords_count INT UNSIGNED DEFAULT 0,
            lost_keywords_count INT UNSIGNED DEFAULT 0,
            decay_detected BOOLEAN DEFAULT FALSE,
            decay_explanation TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_url_metric_window_date (url_hash, metric_type, time_window, analysis_date),
            KEY idx_url_hash (url_hash),
            KEY idx_analysis_date (analysis_date),
            KEY idx_metric_type (metric_type),
            KEY idx_trend_direction (trend_direction),
            KEY idx_is_significant (is_significant),
            KEY idx_decay_detected (decay_detected),
            KEY idx_change_percentage (change_percentage DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Trend analysis and change detection for SEO metrics'";
        
        dbDelta($sql);
    }
    
    /**
     * Schema Validation Table
     * Primary purpose: Store JSON-LD schema validation results and issues
     */
    private function create_schema_validation_table() {
        $table_name = $this->table_names['schema_validation'];
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_url VARCHAR(2048) NOT NULL,
            url_hash CHAR(64) NOT NULL,
            post_id BIGINT UNSIGNED,
            schema_type VARCHAR(100) NOT NULL,
            schema_index TINYINT UNSIGNED DEFAULT 0,
            is_valid BOOLEAN DEFAULT FALSE,
            validation_score TINYINT UNSIGNED DEFAULT 0,
            validation_errors TEXT,
            validation_warnings TEXT,
            schema_raw_data LONGTEXT,
            missing_required_fields TEXT,
            missing_recommended_fields TEXT,
            duplicate_entity_ids TEXT,
            entity_count TINYINT UNSIGNED DEFAULT 0,
            validation_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            validation_hash CHAR(32),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_url_schema_index (url_hash, schema_type, schema_index),
            KEY idx_post_id (post_id),
            KEY idx_schema_type (schema_type),
            KEY idx_is_valid (is_valid),
            KEY idx_validation_score (validation_score DESC),
            KEY idx_validation_date (validation_date),
            KEY idx_validation_hash (validation_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='JSON-LD schema markup validation results'";
        
        dbDelta($sql);
    }
    
    /**
     * Core Web Vitals Metrics Table
     * Primary purpose: Store PageSpeed Insights CrUX and Lab performance data
     */
    private function create_cwv_metrics_table() {
        $table_name = $this->table_names['cwv_metrics'];
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_url VARCHAR(2048) NOT NULL,
            url_hash CHAR(64) NOT NULL,
            test_date DATE NOT NULL,
            data_source ENUM('field', 'lab') NOT NULL DEFAULT 'field',
            device_type ENUM('desktop', 'mobile') NOT NULL DEFAULT 'mobile',
            lcp_value DECIMAL(8,3),
            lcp_score ENUM('good', 'needs-improvement', 'poor'),
            inp_value DECIMAL(8,3),
            inp_score ENUM('good', 'needs-improvement', 'poor'),
            cls_value DECIMAL(6,4),
            cls_score ENUM('good', 'needs-improvement', 'poor'),
            fcp_value DECIMAL(8,3),
            fcp_score ENUM('good', 'needs-improvement', 'poor'),
            ttfb_value DECIMAL(8,3),
            ttfb_score ENUM('good', 'needs-improvement', 'poor'),
            overall_assessment ENUM('good', 'needs-improvement', 'poor'),
            performance_score TINYINT UNSIGNED,
            accessibility_score TINYINT UNSIGNED,
            best_practices_score TINYINT UNSIGNED,
            seo_score TINYINT UNSIGNED,
            total_page_size_kb INT UNSIGNED,
            total_resources SMALLINT UNSIGNED,
            api_response_time_ms SMALLINT UNSIGNED,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_url_date_device_source (url_hash, test_date, device_type, data_source),
            KEY idx_url_hash (url_hash),
            KEY idx_test_date (test_date),
            KEY idx_overall_assessment (overall_assessment),
            KEY idx_lcp_score (lcp_score),
            KEY idx_inp_score (inp_score),
            KEY idx_cls_score (cls_score),
            KEY idx_performance_score (performance_score DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Core Web Vitals and performance metrics from PSI'";
        
        dbDelta($sql);
    }
    
    /**
     * Sitemap Status Table
     * Primary purpose: Track XML sitemap generation, pinging, and health status
     */
    private function create_sitemap_status_table() {
        $table_name = $this->table_names['sitemap_status'];
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sitemap_type ENUM('main', 'posts', 'pages', 'categories', 'tags', 'custom') NOT NULL,
            sitemap_url VARCHAR(2048) NOT NULL,
            total_urls INT UNSIGNED DEFAULT 0,
            last_generated DATETIME,
            last_modified DATETIME,
            last_pinged DATETIME,
            ping_status ENUM('success', 'failed', 'pending', 'not_sent') DEFAULT 'not_sent',
            ping_response_data TEXT,
            file_size_bytes INT UNSIGNED DEFAULT 0,
            compression_enabled BOOLEAN DEFAULT TRUE,
            auto_generation_enabled BOOLEAN DEFAULT TRUE,
            priority_override DECIMAL(2,1),
            changefreq_override ENUM('always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'),
            lastmod_drift_hours SMALLINT UNSIGNED DEFAULT 0,
            generation_errors TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_sitemap_type (sitemap_type),
            KEY idx_last_generated (last_generated),
            KEY idx_last_pinged (last_pinged),
            KEY idx_ping_status (ping_status),
            KEY idx_lastmod_drift (lastmod_drift_hours DESC),
            KEY idx_auto_generation (auto_generation_enabled)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='XML sitemap generation and ping status tracking'";
        
        dbDelta($sql);
    }
    
    /**
     * Alerts and Notifications Table
     * Primary purpose: Store generated SEO alerts and notification delivery tracking
     */
    private function create_alerts_table() {
        $table_name = $this->table_names['alerts'];
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            alert_type ENUM('index_drop', 'cwv_poor', '404_spike', 'decay_flag', 'schema_error', 'crawl_error', 'keyword_loss', 'ranking_drop') NOT NULL,
            severity_level ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
            alert_title VARCHAR(255) NOT NULL,
            alert_message TEXT NOT NULL,
            affected_url VARCHAR(2048),
            url_hash CHAR(64),
            alert_data JSON,
            threshold_value DECIMAL(12,4),
            actual_value DECIMAL(12,4),
            is_acknowledged BOOLEAN DEFAULT FALSE,
            is_resolved BOOLEAN DEFAULT FALSE,
            is_email_sent BOOLEAN DEFAULT FALSE,
            email_sent_at DATETIME,
            resolution_action ENUM('none', 'investigating', 'fixing', 'resolved', 'false_positive') DEFAULT 'none',
            resolution_notes TEXT,
            resolved_at DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_alert_type (alert_type),
            KEY idx_severity_level (severity_level),
            KEY idx_url_hash (url_hash),
            KEY idx_is_acknowledged (is_acknowledged),
            KEY idx_is_resolved (is_resolved),
            KEY idx_created_desc (created_at DESC),
            KEY idx_resolution_action (resolution_action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='SEO alerts, notifications and resolution tracking'";
        
        dbDelta($sql);
    }
    
    /**
     * SEO Scores Table
     * Primary purpose: Store the 5 explainable SEO scores and priority rankings
     */
    private function create_scores_table() {
        $table_name = $this->table_names['scores'];
        
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_url VARCHAR(2048) NOT NULL,
            url_hash CHAR(64) NOT NULL,
            post_id BIGINT UNSIGNED,
            calculation_date DATE NOT NULL,
            discoverability_score TINYINT UNSIGNED DEFAULT 0,
            experience_score TINYINT UNSIGNED DEFAULT 0,
            semantic_score TINYINT UNSIGNED DEFAULT 0,
            coverage_score TINYINT UNSIGNED DEFAULT 0,
            outcome_score TINYINT UNSIGNED DEFAULT 0,
            overall_seo_score TINYINT UNSIGNED DEFAULT 0,
            traffic_potential DECIMAL(8,2) DEFAULT 0.00,
            optimization_effort TINYINT UNSIGNED DEFAULT 5,
            priority_ranking DECIMAL(6,2) DEFAULT 0.00,
            click_potential INT UNSIGNED DEFAULT 0,
            impression_gap INT UNSIGNED DEFAULT 0,
            ctr_opportunity DECIMAL(5,4) DEFAULT 0.0000,
            total_issues_found SMALLINT UNSIGNED DEFAULT 0,
            fix_recommendations TEXT,
            score_calculation_breakdown JSON,
            last_calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_url_calculation_date (url_hash, calculation_date),
            KEY idx_post_id (post_id),
            KEY idx_overall_seo_score (overall_seo_score DESC),
            KEY idx_priority_ranking (priority_ranking DESC),
            KEY idx_discoverability_score (discoverability_score DESC),
            KEY idx_experience_score (experience_score DESC),
            KEY idx_semantic_score (semantic_score DESC),
            KEY idx_coverage_score (coverage_score DESC),
            KEY idx_outcome_score (outcome_score DESC),
            KEY idx_calculation_date (calculation_date DESC),
            KEY idx_last_calculated (last_calculated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='SEO scores, rankings and optimization priorities'";
        
        dbDelta($sql);
    }
    
    /**
     * Schedule cleanup jobs for data retention
     */
    private function schedule_cleanup_jobs() {
        // Schedule daily cleanup if not already scheduled
        if (!wp_next_scheduled('khm_seo_measurement_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'khm_seo_measurement_daily_cleanup');
        }
        
        // Schedule weekly maintenance if not already scheduled
        if (!wp_next_scheduled('khm_seo_measurement_weekly_maintenance')) {
            wp_schedule_event(time(), 'weekly', 'khm_seo_measurement_weekly_maintenance');
        }
    }
    
    /**
     * Clean up old data according to retention policies
     */
    public function perform_cleanup() {
        // GSC data: retain 18 months
        $this->wpdb->query("DELETE FROM {$this->table_names['gsc_stats']} WHERE fetch_date < DATE_SUB(CURDATE(), INTERVAL 18 MONTH)");
        
        // Crawl data: remove pages not seen in 30 days
        $this->wpdb->query("DELETE FROM {$this->table_names['crawl_data']} WHERE last_crawled < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        // Engagement data: retain 12 months
        $this->wpdb->query("DELETE FROM {$this->table_names['engagement']} WHERE date_recorded < DATE_SUB(CURDATE(), INTERVAL 12 MONTH)");
        
        // CWV data: retain 6 months
        $this->wpdb->query("DELETE FROM {$this->table_names['cwv_metrics']} WHERE test_date < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)");
        
        // Trends data: retain 3 months
        $this->wpdb->query("DELETE FROM {$this->table_names['trends']} WHERE analysis_date < DATE_SUB(CURDATE(), INTERVAL 3 MONTH)");
        
        // Resolved alerts: retain 90 days
        $this->wpdb->query("DELETE FROM {$this->table_names['alerts']} WHERE resolution_action = 'resolved' AND resolved_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        
        // Old schema validations: retain 1 month
        $this->wpdb->query("DELETE FROM {$this->table_names['schema_validation']} WHERE validation_date < DATE_SUB(NOW(), INTERVAL 1 MONTH)");
        
        // Scores: retain 6 months
        $this->wpdb->query("DELETE FROM {$this->table_names['scores']} WHERE calculation_date < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)");
    }
    
    /**
     * Get all table names
     */
    public function get_table_names() {
        return $this->table_names;
    }
    
    /**
     * Drop all tables (used during uninstallation)
     */
    public function drop_all_tables() {
        foreach ($this->table_names as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        delete_option(self::VERSION_OPTION);
        
        // Clear scheduled events
        wp_clear_scheduled_hook('khm_seo_measurement_daily_cleanup');
        wp_clear_scheduled_hook('khm_seo_measurement_weekly_maintenance');
    }
    
    /**
     * Get database usage statistics
     */
    public function get_database_statistics() {
        $stats = [];
        
        foreach ($this->table_names as $key => $table) {
            $row_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            
            // Get table size from information_schema if available
            $table_size_query = "
                SELECT ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = '{$table}'
            ";
            $table_size = $this->wpdb->get_var($table_size_query);
            
            $stats[$key] = [
                'table_name' => $table,
                'row_count' => (int) $row_count,
                'size_mb' => (float) ($table_size ?: 0)
            ];
        }
        
        return $stats;
    }
    
    /**
     * Verify database integrity
     */
    public function verify_integrity() {
        $results = [];
        
        foreach ($this->table_names as $key => $table) {
            $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            $results[$key] = [
                'table' => $table,
                'exists' => !empty($table_exists),
                'status' => !empty($table_exists) ? 'OK' : 'MISSING'
            ];
        }
        
        return $results;
    }
}