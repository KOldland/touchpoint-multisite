<?php
/**
 * Database Schema Manager for SEO Measurement Module
 * 
 * Handles all database table creation, updates, and maintenance
 * for the comprehensive SEO intelligence platform.
 * 
 * @package KHM_SEO
 * @subpackage Database
 * @since 9.0.0
 */

namespace KHM_SEO\Database;

// Import WordPress functions that we need
if (!function_exists('dbDelta')) {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
}

class DatabaseSchema {
    
    /**
     * Database version for migrations
     */
    const DB_VERSION = '9.0.0';
    
    /**
     * Option key for storing database version
     */
    const VERSION_OPTION = 'khm_seo_db_version';
    
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
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'seo_';
    }
    
    /**
     * Initialize database schema
     * Called on plugin activation and version updates
     */
    public function initialize() {
        $current_version = get_option(self::VERSION_OPTION, '0.0.0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->create_all_tables();
            $this->create_indexes();
            update_option(self::VERSION_OPTION, self::DB_VERSION);
            
            // Schedule initial cleanup jobs
            $this->schedule_cleanup_jobs();
        }
    }
    
    /**
     * Create all required tables
     */
    private function create_all_tables() {
        $this->create_gsc_stats_table();
        $this->create_crawl_table();
        $this->create_link_table();
        $this->create_engagement_table();
        $this->create_trends_table();
        $this->create_schema_validation_table();
        $this->create_sitemap_status_table();
        $this->create_cwv_metrics_table();
        $this->create_alerts_table();
        $this->create_scores_table();
    }
    
    /**
     * Google Search Console Statistics Table
     * Stores GSC API data with composite primary key
     */
    private function create_gsc_stats_table() {
        $table_name = $this->table_prefix . 'gsc_stats';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            date DATE NOT NULL,
            page VARCHAR(2048) NOT NULL,
            query VARCHAR(2048) NOT NULL,
            device ENUM('DESKTOP', 'MOBILE', 'TABLET') NOT NULL,
            country CHAR(3) NOT NULL DEFAULT 'USA',
            clicks INT UNSIGNED NOT NULL DEFAULT 0,
            impressions INT UNSIGNED NOT NULL DEFAULT 0,
            ctr DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
            position DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (date, page(255), query(255), device, country),
            INDEX idx_page_date (page(255), date),
            INDEX idx_query_date (query(255), date),
            INDEX idx_clicks_desc (clicks DESC),
            INDEX idx_impressions_desc (impressions DESC),
            INDEX idx_position_asc (position ASC),
            INDEX idx_date_desc (date DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Google Search Console performance data'";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Internal Crawler Results Table
     * Stores page-level crawl analysis data
     */
    private function create_crawl_table() {
        $table_name = $this->table_prefix . 'crawl';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url VARCHAR(2048) NOT NULL,
            url_hash CHAR(64) NOT NULL,
            status_code SMALLINT UNSIGNED NOT NULL,
            canonical_url VARCHAR(2048),
            title VARCHAR(1024),
            title_length SMALLINT UNSIGNED,
            meta_description TEXT,
            meta_description_length SMALLINT UNSIGNED,
            h1_count TINYINT UNSIGNED DEFAULT 0,
            h1_text VARCHAR(1024),
            meta_robots VARCHAR(255),
            content_length INT UNSIGNED DEFAULT 0,
            page_weight INT UNSIGNED DEFAULT 0,
            internal_links_out SMALLINT UNSIGNED DEFAULT 0,
            external_links_out SMALLINT UNSIGNED DEFAULT 0,
            is_orphaned BOOLEAN DEFAULT FALSE,
            has_noindex BOOLEAN DEFAULT FALSE,
            redirect_chain_length TINYINT UNSIGNED DEFAULT 0,
            redirect_target VARCHAR(2048),
            last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            first_crawled DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            crawl_duration_ms SMALLINT UNSIGNED DEFAULT 0,
            errors TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_url_hash (url_hash),
            INDEX idx_url (url(255)),
            INDEX idx_status_code (status_code),
            INDEX idx_last_seen (last_seen),
            INDEX idx_orphaned (is_orphaned),
            INDEX idx_noindex (has_noindex),
            INDEX idx_title_length (title_length),
            INDEX idx_meta_desc_length (meta_description_length)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Internal crawler page analysis results'";
        
        dbDelta($sql);
    }
    
    /**
     * Internal Link Graph Table
     * Stores link relationships between pages
     */
    private function create_link_table() {
        $table_name = $this->table_prefix . 'link';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            from_url VARCHAR(2048) NOT NULL,
            from_url_hash CHAR(64) NOT NULL,
            to_url VARCHAR(2048) NOT NULL,
            to_url_hash CHAR(64) NOT NULL,
            anchor_text VARCHAR(1024),
            link_type ENUM('internal', 'external', 'mailto', 'tel') NOT NULL DEFAULT 'internal',
            rel_attributes VARCHAR(255),
            is_follow BOOLEAN DEFAULT TRUE,
            position_on_page SMALLINT UNSIGNED DEFAULT 0,
            context_before VARCHAR(512),
            context_after VARCHAR(512),
            discovered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_verified DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_link (from_url_hash, to_url_hash, anchor_text(255)),
            INDEX idx_from_url (from_url_hash),
            INDEX idx_to_url (to_url_hash),
            INDEX idx_link_type (link_type),
            INDEX idx_is_follow (is_follow),
            INDEX idx_is_active (is_active),
            INDEX idx_discovered (discovered_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Internal link graph and relationship data'";
        
        dbDelta($sql);
    }
    
    /**
     * GA4 Engagement Metrics Table
     * Stores Google Analytics 4 engagement data
     */
    private function create_engagement_table() {
        $table_name = $this->table_prefix . 'engagement';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            date DATE NOT NULL,
            url VARCHAR(2048) NOT NULL,
            url_hash CHAR(64) NOT NULL,
            screen_page_views INT UNSIGNED DEFAULT 0,
            unique_page_views INT UNSIGNED DEFAULT 0,
            average_session_duration DECIMAL(8,2) DEFAULT 0.00,
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
            UNIQUE KEY uk_date_url_device (date, url_hash, device_category),
            INDEX idx_url_date (url_hash, date),
            INDEX idx_page_views (screen_page_views DESC),
            INDEX idx_session_duration (average_session_duration DESC),
            INDEX idx_bounce_rate (bounce_rate ASC),
            INDEX idx_date_desc (date DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='GA4 engagement metrics per page'";
        
        dbDelta($sql);
    }
    
    /**
     * Trend Analysis and Historical Data Table
     * Stores computed trends and change detection
     */
    private function create_trends_table() {
        $table_name = $this->table_prefix . 'trends';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url VARCHAR(2048) NOT NULL,
            url_hash CHAR(64) NOT NULL,
            metric_type ENUM('clicks', 'impressions', 'position', 'ctr', 'engagement', 'cwv') NOT NULL,
            timeframe ENUM('7d', '28d', '90d') NOT NULL,
            current_value DECIMAL(12,4) NOT NULL,
            previous_value DECIMAL(12,4) NOT NULL,
            change_percent DECIMAL(8,4) NOT NULL,
            change_absolute DECIMAL(12,4) NOT NULL,
            trend_direction ENUM('up', 'down', 'stable') NOT NULL,
            is_significant BOOLEAN DEFAULT FALSE,
            confidence_score DECIMAL(5,4) DEFAULT 0.0000,
            analysis_date DATE NOT NULL,
            new_queries_count INT UNSIGNED DEFAULT 0,
            lost_queries_count INT UNSIGNED DEFAULT 0,
            decay_flag BOOLEAN DEFAULT FALSE,
            decay_reason TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_url_metric_timeframe_date (url_hash, metric_type, timeframe, analysis_date),
            INDEX idx_url_date (url_hash, analysis_date),
            INDEX idx_metric_type (metric_type),
            INDEX idx_trend_direction (trend_direction),
            INDEX idx_significant (is_significant),
            INDEX idx_decay_flag (decay_flag),
            INDEX idx_change_percent (change_percent DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Trend analysis and change detection data'";
        
        dbDelta($sql);
    }
    
    /**
     * Schema Validation Results Table
     * Stores JSON-LD schema validation results
     */
    private function create_schema_validation_table() {
        $table_name = $this->table_prefix . 'schema_validation';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url VARCHAR(2048) NOT NULL,
            url_hash CHAR(64) NOT NULL,
            post_id BIGINT UNSIGNED,
            schema_type VARCHAR(100) NOT NULL,
            schema_index TINYINT UNSIGNED DEFAULT 0,
            is_valid BOOLEAN DEFAULT FALSE,
            validation_score TINYINT UNSIGNED DEFAULT 0,
            errors TEXT,
            warnings TEXT,
            schema_data LONGTEXT,
            required_fields_missing TEXT,
            recommended_fields_missing TEXT,
            duplicate_ids TEXT,
            entity_count TINYINT UNSIGNED DEFAULT 0,
            last_validated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            validation_hash CHAR(32),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_post_id (post_id),
            INDEX idx_schema_type (schema_type),
            INDEX idx_is_valid (is_valid),
            INDEX idx_validation_score (validation_score DESC),
            INDEX idx_last_validated (last_validated)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='JSON-LD schema validation results'";
        
        dbDelta($sql);
    }
    
    /**
     * Core Web Vitals Metrics Table
     * Stores PageSpeed Insights CrUX and lab data
     */
    private function create_cwv_metrics_table() {
        $table_name = $this->table_prefix . 'cwv_metrics';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url VARCHAR(2048) NOT NULL,
            url_hash CHAR(64) NOT NULL,
            date DATE NOT NULL,
            data_source ENUM('field', 'lab') NOT NULL DEFAULT 'field',
            device_type ENUM('desktop', 'mobile') NOT NULL DEFAULT 'mobile',
            lcp_value DECIMAL(8,3),
            lcp_category ENUM('good', 'needs-improvement', 'poor'),
            inp_value DECIMAL(8,3),
            inp_category ENUM('good', 'needs-improvement', 'poor'),
            cls_value DECIMAL(6,4),
            cls_category ENUM('good', 'needs-improvement', 'poor'),
            fcp_value DECIMAL(8,3),
            fcp_category ENUM('good', 'needs-improvement', 'poor'),
            ttfb_value DECIMAL(8,3),
            ttfb_category ENUM('good', 'needs-improvement', 'poor'),
            overall_category ENUM('good', 'needs-improvement', 'poor'),
            performance_score TINYINT UNSIGNED,
            accessibility_score TINYINT UNSIGNED,
            best_practices_score TINYINT UNSIGNED,
            seo_score TINYINT UNSIGNED,
            page_weight_kb INT UNSIGNED,
            resources_count SMALLINT UNSIGNED,
            api_response_time_ms SMALLINT UNSIGNED,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_url_date_device_source (url_hash, date, device_type, data_source),
            INDEX idx_url_date (url_hash, date),
            INDEX idx_overall_category (overall_category),
            INDEX idx_lcp_category (lcp_category),
            INDEX idx_inp_category (inp_category),
            INDEX idx_cls_category (cls_category),
            INDEX idx_date_desc (date DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Core Web Vitals metrics from PageSpeed Insights'";
        
        dbDelta($sql);
    }
    
    /**
     * Sitemap Status and Management Table
     * Tracks sitemap generation and ping status
     */
    private function create_sitemap_status_table() {
        $table_name = $this->table_prefix . 'sitemap_status';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sitemap_type ENUM('main', 'posts', 'pages', 'categories', 'tags', 'custom') NOT NULL,
            url VARCHAR(2048) NOT NULL,
            url_count INT UNSIGNED DEFAULT 0,
            last_generated DATETIME,
            last_modified DATETIME,
            last_pinged DATETIME,
            ping_status ENUM('success', 'failed', 'pending', 'not_sent') DEFAULT 'not_sent',
            ping_response TEXT,
            file_size INT UNSIGNED DEFAULT 0,
            compression_enabled BOOLEAN DEFAULT TRUE,
            auto_generation BOOLEAN DEFAULT TRUE,
            priority_override DECIMAL(2,1),
            changefreq_override ENUM('always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'),
            lastmod_lag_hours SMALLINT UNSIGNED DEFAULT 0,
            errors TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_sitemap_type (sitemap_type),
            INDEX idx_last_generated (last_generated),
            INDEX idx_last_pinged (last_pinged),
            INDEX idx_ping_status (ping_status),
            INDEX idx_lastmod_lag (lastmod_lag_hours DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='Sitemap generation and ping tracking'";
        
        dbDelta($sql);
    }
    
    /**
     * Alerts and Notifications Table
     * Stores generated alerts and notification history
     */
    private function create_alerts_table() {
        $table_name = $this->table_prefix . 'alerts';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            alert_type ENUM('index_drop', 'cwv_poor', '404_spike', 'decay_flag', 'schema_error', 'crawl_error', 'trend_alert') NOT NULL,
            severity ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            url VARCHAR(2048),
            url_hash CHAR(64),
            data JSON,
            threshold_value DECIMAL(12,4),
            actual_value DECIMAL(12,4),
            is_read BOOLEAN DEFAULT FALSE,
            is_dismissed BOOLEAN DEFAULT FALSE,
            is_emailed BOOLEAN DEFAULT FALSE,
            email_sent_at DATETIME,
            action_taken ENUM('none', 'investigating', 'fixing', 'resolved', 'false_positive') DEFAULT 'none',
            action_notes TEXT,
            resolved_at DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_alert_type (alert_type),
            INDEX idx_severity (severity),
            INDEX idx_url_hash (url_hash),
            INDEX idx_is_read (is_read),
            INDEX idx_is_dismissed (is_dismissed),
            INDEX idx_created_desc (created_at DESC),
            INDEX idx_action_taken (action_taken)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='SEO alerts and notifications'";
        
        dbDelta($sql);
    }
    
    /**
     * SEO Scores and Rankings Table
     * Stores the 5 explainable scores and ranking data
     */
    private function create_scores_table() {
        $table_name = $this->table_prefix . 'scores';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url VARCHAR(2048) NOT NULL,
            url_hash CHAR(64) NOT NULL,
            post_id BIGINT UNSIGNED,
            calculation_date DATE NOT NULL,
            discoverability_score TINYINT UNSIGNED DEFAULT 0,
            experience_score TINYINT UNSIGNED DEFAULT 0,
            semantic_score TINYINT UNSIGNED DEFAULT 0,
            coverage_score TINYINT UNSIGNED DEFAULT 0,
            outcome_score TINYINT UNSIGNED DEFAULT 0,
            overall_score TINYINT UNSIGNED DEFAULT 0,
            impact_potential DECIMAL(8,2) DEFAULT 0.00,
            effort_estimate TINYINT UNSIGNED DEFAULT 5,
            priority_rank DECIMAL(6,2) DEFAULT 0.00,
            clicks_potential INT UNSIGNED DEFAULT 0,
            impressions_gap INT UNSIGNED DEFAULT 0,
            ctr_gap DECIMAL(5,4) DEFAULT 0.0000,
            issue_count SMALLINT UNSIGNED DEFAULT 0,
            fix_recommendations TEXT,
            score_breakdown JSON,
            last_calculated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_post_id_fk (post_id),
            INDEX idx_overall_score (overall_score DESC),
            INDEX idx_priority_rank (priority_rank DESC),
            INDEX idx_discoverability (discoverability_score DESC),
            INDEX idx_experience (experience_score DESC),
            INDEX idx_semantic (semantic_score DESC),
            INDEX idx_coverage (coverage_score DESC),
            INDEX idx_outcome (outcome_score DESC),
            INDEX idx_calculation_date (calculation_date DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
        COMMENT='SEO scores and priority rankings'";
        
        dbDelta($sql);
    }
    
    /**
     * Create additional indexes for performance optimization
     */
    private function create_indexes() {
        // Add any additional composite indexes that weren't created with tables
        
        // GSC Stats performance indexes
        $gsc_table = $this->table_prefix . 'gsc_stats';
        $this->wpdb->query("CREATE INDEX IF NOT EXISTS idx_gsc_page_query_date ON {$gsc_table} (page(255), query(255), date)");
        $this->wpdb->query("CREATE INDEX IF NOT EXISTS idx_gsc_query_position ON {$gsc_table} (query(255), position)");
        
        // Crawl table performance indexes
        $crawl_table = $this->table_prefix . 'crawl';
        $this->wpdb->query("CREATE INDEX IF NOT EXISTS idx_crawl_status_seen ON {$crawl_table} (status_code, last_seen)");
        $this->wpdb->query("CREATE INDEX IF NOT EXISTS idx_crawl_issues ON {$crawl_table} (is_orphaned, has_noindex, status_code)");
        
        // Link table performance indexes
        $link_table = $this->table_prefix . 'link';
        $this->wpdb->query("CREATE INDEX IF NOT EXISTS idx_link_active_type ON {$link_table} (is_active, link_type)");
        
        // Trends table performance indexes
        $trends_table = $this->table_prefix . 'trends';
        $this->wpdb->query("CREATE INDEX IF NOT EXISTS idx_trends_significant ON {$trends_table} (is_significant, trend_direction, analysis_date)");
    }
    
    /**
     * Schedule cleanup jobs for data retention
     */
    private function schedule_cleanup_jobs() {
        // Schedule daily cleanup
        if (!wp_next_scheduled('khm_seo_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'khm_seo_daily_cleanup');
        }
        
        // Schedule weekly cleanup
        if (!wp_next_scheduled('khm_seo_weekly_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'khm_seo_weekly_cleanup');
        }
    }
    
    /**
     * Clean up old data according to retention policies
     */
    public function cleanup_old_data() {
        // GSC data: keep 18 months
        $gsc_table = $this->table_prefix . 'gsc_stats';
        $this->wpdb->query("DELETE FROM {$gsc_table} WHERE date < DATE_SUB(CURDATE(), INTERVAL 18 MONTH)");
        
        // Crawl data: remove stale pages (not seen in 30 days)
        $crawl_table = $this->table_prefix . 'crawl';
        $this->wpdb->query("DELETE FROM {$crawl_table} WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        // Engagement data: keep 12 months
        $engagement_table = $this->table_prefix . 'engagement';
        $this->wpdb->query("DELETE FROM {$engagement_table} WHERE date < DATE_SUB(CURDATE(), INTERVAL 12 MONTH)");
        
        // CWV data: keep 6 months
        $cwv_table = $this->table_prefix . 'cwv_metrics';
        $this->wpdb->query("DELETE FROM {$cwv_table} WHERE date < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)");
        
        // Trends data: keep 3 months
        $trends_table = $this->table_prefix . 'trends';
        $this->wpdb->query("DELETE FROM {$trends_table} WHERE analysis_date < DATE_SUB(CURDATE(), INTERVAL 3 MONTH)");
        
        // Alerts: keep resolved alerts for 90 days
        $alerts_table = $this->table_prefix . 'alerts';
        $this->wpdb->query("DELETE FROM {$alerts_table} WHERE action_taken = 'resolved' AND resolved_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        
        // Schema validation: keep 1 month of old validations
        $schema_table = $this->table_prefix . 'schema_validation';
        $this->wpdb->query("DELETE FROM {$schema_table} WHERE last_validated < DATE_SUB(NOW(), INTERVAL 1 MONTH)");
        
        // Scores: keep 6 months
        $scores_table = $this->table_prefix . 'scores';
        $this->wpdb->query("DELETE FROM {$scores_table} WHERE calculation_date < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)");
    }
    
    /**
     * Get table names for external use
     */
    public function get_table_names() {
        return [
            'gsc_stats' => $this->table_prefix . 'gsc_stats',
            'crawl' => $this->table_prefix . 'crawl', 
            'link' => $this->table_prefix . 'link',
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
     * Drop all tables (for uninstallation)
     */
    public function drop_all_tables() {
        $tables = $this->get_table_names();
        
        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        \delete_option(self::VERSION_OPTION);
    }
    
    /**
     * Get database statistics
     */
    public function get_database_stats() {
        $tables = $this->get_table_names();
        $stats = [];
        
        foreach ($tables as $key => $table) {
            $row_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $table_size = $this->wpdb->get_var("
                SELECT ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'MB'
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = '{$table}'
            ");
            
            $stats[$key] = [
                'rows' => (int) $row_count,
                'size_mb' => (float) $table_size
            ];
        }
        
        return $stats;
    }
}