<?php
/**
 * Phase 2.6 Analytics Database Schema
 * 
 * Database schema and management for SEO analytics, scoring, and reporting.
 * Creates and manages tables for comprehensive SEO data storage and retrieval.
 * 
 * Tables Created:
 * - khm_seo_scores: Historical SEO scores and analysis data
 * - khm_seo_metrics: Performance metrics and measurements
 * - khm_seo_reports: Generated report records and metadata
 * - khm_seo_recommendations: Actionable recommendations tracking
 * - khm_seo_audit_log: Audit trail for changes and improvements
 * 
 * @package KHM_SEO\Analytics
 * @since 2.6.0
 * @version 2.6.0
 */

namespace KHM_SEO\Analytics;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Analytics Database Schema Class
 * Manages database structure for analytics and reporting
 */
class AnalyticsDatabase {
    
    /**
     * @var string Database version for schema management
     */
    private $db_version = '2.6.0';
    
    /**
     * @var string Option name for storing database version
     */
    private $version_option = 'khm_seo_analytics_db_version';
    
    /**
     * @var array Table definitions
     */
    private $tables;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->define_tables();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'check_database_version']);
        register_activation_hook(__FILE__, [$this, 'create_tables']);
        register_deactivation_hook(__FILE__, [$this, 'cleanup_scheduled_events']);
        register_uninstall_hook(__FILE__, [$this, 'remove_tables']);
    }
    
    /**
     * Define database tables structure
     */
    private function define_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $this->tables = [
            'seo_scores' => [
                'name' => $wpdb->prefix . 'khm_seo_scores',
                'sql' => "CREATE TABLE {$wpdb->prefix}khm_seo_scores (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    post_id bigint(20) unsigned NOT NULL,
                    overall_score tinyint(3) unsigned NOT NULL DEFAULT 0,
                    content_quality_score tinyint(3) unsigned NOT NULL DEFAULT 0,
                    technical_seo_score tinyint(3) unsigned NOT NULL DEFAULT 0,
                    social_optimization_score tinyint(3) unsigned NOT NULL DEFAULT 0,
                    user_experience_score tinyint(3) unsigned NOT NULL DEFAULT 0,
                    grade varchar(2) NOT NULL DEFAULT 'F',
                    category_scores longtext DEFAULT NULL,
                    detailed_analysis longtext DEFAULT NULL,
                    recommendations longtext DEFAULT NULL,
                    critical_issues longtext DEFAULT NULL,
                    improvement_opportunities longtext DEFAULT NULL,
                    analysis_metadata longtext DEFAULT NULL,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY post_id (post_id),
                    KEY overall_score (overall_score),
                    KEY created_at (created_at),
                    KEY score_post_date (post_id, created_at)
                ) $charset_collate;"
            ],
            'metrics' => [
                'name' => $wpdb->prefix . 'khm_seo_metrics',
                'sql' => "CREATE TABLE {$wpdb->prefix}khm_seo_metrics (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    post_id bigint(20) unsigned DEFAULT NULL,
                    metric_type varchar(50) NOT NULL,
                    metric_name varchar(100) NOT NULL,
                    metric_value text NOT NULL,
                    metric_unit varchar(20) DEFAULT NULL,
                    measurement_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    metadata longtext DEFAULT NULL,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY post_metric (post_id, metric_type),
                    KEY metric_type (metric_type),
                    KEY metric_name (metric_name),
                    KEY measurement_date (measurement_date),
                    KEY post_date_type (post_id, measurement_date, metric_type)
                ) $charset_collate;"
            ],
            'reports' => [
                'name' => $wpdb->prefix . 'khm_seo_reports',
                'sql' => "CREATE TABLE {$wpdb->prefix}khm_seo_reports (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    template_id varchar(50) NOT NULL,
                    template_name varchar(255) NOT NULL,
                    report_format varchar(20) NOT NULL DEFAULT 'pdf',
                    file_path varchar(500) DEFAULT NULL,
                    file_size bigint(20) unsigned DEFAULT 0,
                    download_url varchar(500) DEFAULT NULL,
                    generation_status varchar(20) NOT NULL DEFAULT 'pending',
                    date_range_start date NOT NULL,
                    date_range_end date NOT NULL,
                    report_data longtext DEFAULT NULL,
                    generation_options longtext DEFAULT NULL,
                    generated_by bigint(20) unsigned NOT NULL,
                    email_recipients longtext DEFAULT NULL,
                    email_sent_at datetime DEFAULT NULL,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    completed_at datetime DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY template_id (template_id),
                    KEY generation_status (generation_status),
                    KEY date_range (date_range_start, date_range_end),
                    KEY generated_by (generated_by),
                    KEY created_at (created_at)
                ) $charset_collate;"
            ],
            'recommendations' => [
                'name' => $wpdb->prefix . 'khm_seo_recommendations',
                'sql' => "CREATE TABLE {$wpdb->prefix}khm_seo_recommendations (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    post_id bigint(20) unsigned DEFAULT NULL,
                    recommendation_type varchar(50) NOT NULL,
                    category varchar(50) NOT NULL,
                    criterion varchar(100) DEFAULT NULL,
                    priority enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
                    title varchar(255) NOT NULL,
                    description text NOT NULL,
                    recommendation_text text NOT NULL,
                    expected_impact varchar(20) DEFAULT NULL,
                    effort_level varchar(20) DEFAULT NULL,
                    status enum('pending','in_progress','completed','dismissed') NOT NULL DEFAULT 'pending',
                    assigned_to bigint(20) unsigned DEFAULT NULL,
                    due_date date DEFAULT NULL,
                    completed_at datetime DEFAULT NULL,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY post_id (post_id),
                    KEY recommendation_type (recommendation_type),
                    KEY category (category),
                    KEY priority (priority),
                    KEY status (status),
                    KEY assigned_to (assigned_to),
                    KEY created_at (created_at),
                    KEY post_priority_status (post_id, priority, status)
                ) $charset_collate;"
            ],
            'audit_log' => [
                'name' => $wpdb->prefix . 'khm_seo_audit_log',
                'sql' => "CREATE TABLE {$wpdb->prefix}khm_seo_audit_log (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    post_id bigint(20) unsigned DEFAULT NULL,
                    action varchar(50) NOT NULL,
                    category varchar(50) DEFAULT NULL,
                    description text NOT NULL,
                    old_value text DEFAULT NULL,
                    new_value text DEFAULT NULL,
                    impact_score tinyint(3) unsigned DEFAULT NULL,
                    user_id bigint(20) unsigned NOT NULL,
                    ip_address varchar(45) DEFAULT NULL,
                    user_agent text DEFAULT NULL,
                    metadata longtext DEFAULT NULL,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY post_id (post_id),
                    KEY action (action),
                    KEY category (category),
                    KEY user_id (user_id),
                    KEY created_at (created_at),
                    KEY post_action_date (post_id, action, created_at)
                ) $charset_collate;"
            ],
            'competitive_data' => [
                'name' => $wpdb->prefix . 'khm_seo_competitive_data',
                'sql' => "CREATE TABLE {$wpdb->prefix}khm_seo_competitive_data (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    competitor_domain varchar(255) NOT NULL,
                    metric_type varchar(50) NOT NULL,
                    metric_value text NOT NULL,
                    keyword varchar(255) DEFAULT NULL,
                    ranking_position smallint(5) unsigned DEFAULT NULL,
                    search_volume bigint(20) unsigned DEFAULT NULL,
                    collection_date date NOT NULL,
                    data_source varchar(50) DEFAULT NULL,
                    metadata longtext DEFAULT NULL,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY competitor_domain (competitor_domain),
                    KEY metric_type (metric_type),
                    KEY keyword (keyword),
                    KEY collection_date (collection_date),
                    KEY domain_metric_date (competitor_domain, metric_type, collection_date)
                ) $charset_collate;"
            ]
        ];
    }
    
    /**
     * Check and update database version
     */
    public function check_database_version() {
        $installed_version = get_option($this->version_option, '0.0.0');
        
        if (version_compare($installed_version, $this->db_version, '<')) {
            $this->create_tables();
            $this->migrate_data($installed_version);
            update_option($this->version_option, $this->db_version);
        }
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($this->tables as $table_key => $table_info) {
            $result = dbDelta($table_info['sql']);
            
            // Log table creation
            if ($result) {
                error_log("KHM SEO: Created/Updated table {$table_info['name']}");
            }
        }
        
        // Create indexes for better performance
        $this->create_custom_indexes();
        
        // Insert default data
        $this->insert_default_data();
    }
    
    /**
     * Create custom indexes for performance optimization
     */
    private function create_custom_indexes() {
        global $wpdb;
        
        // Additional composite indexes for common queries
        $indexes = [
            // SEO Scores table indexes
            "ALTER TABLE {$wpdb->prefix}khm_seo_scores 
             ADD INDEX score_trend (post_id, overall_score, created_at)",
            
            // Metrics table indexes  
            "ALTER TABLE {$wpdb->prefix}khm_seo_metrics 
             ADD INDEX metric_analysis (metric_type, measurement_date, metric_value(50))",
            
            // Recommendations table indexes
            "ALTER TABLE {$wpdb->prefix}khm_seo_recommendations 
             ADD INDEX priority_status_date (priority, status, created_at)",
            
            // Audit log indexes
            "ALTER TABLE {$wpdb->prefix}khm_seo_audit_log 
             ADD INDEX user_action_date (user_id, action, created_at)"
        ];
        
        foreach ($indexes as $sql) {
            $wpdb->query($sql);
        }
    }
    
    /**
     * Insert default data
     */
    private function insert_default_data() {
        global $wpdb;
        
        // Insert default metric types
        $default_metrics = [
            ['metric_type' => 'content_quality', 'metric_name' => 'overall_score', 'metric_unit' => 'percentage'],
            ['metric_type' => 'technical_seo', 'metric_name' => 'page_speed', 'metric_unit' => 'seconds'],
            ['metric_type' => 'technical_seo', 'metric_name' => 'mobile_score', 'metric_unit' => 'percentage'],
            ['metric_type' => 'user_experience', 'metric_name' => 'bounce_rate', 'metric_unit' => 'percentage'],
            ['metric_type' => 'social_optimization', 'metric_name' => 'social_score', 'metric_unit' => 'percentage']
        ];
        
        $metrics_table = $wpdb->prefix . 'khm_seo_metrics';
        
        foreach ($default_metrics as $metric) {
            // Check if metric already exists
            $exists = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$metrics_table} 
                WHERE metric_type = %s AND metric_name = %s AND post_id IS NULL
            ", $metric['metric_type'], $metric['metric_name']));
            
            if (!$exists) {
                $wpdb->insert($metrics_table, array_merge($metric, [
                    'metric_value' => '0',
                    'measurement_date' => current_time('mysql')
                ]));
            }
        }
    }
    
    /**
     * Migrate data from previous versions
     *
     * @param string $from_version Previous version
     */
    private function migrate_data($from_version) {
        // Migration logic for different versions
        if (version_compare($from_version, '2.5.0', '<')) {
            $this->migrate_from_2_5();
        }
        
        if (version_compare($from_version, '2.6.0', '<')) {
            $this->migrate_to_2_6();
        }
    }
    
    /**
     * Migrate from version 2.5
     */
    private function migrate_from_2_5() {
        global $wpdb;
        
        // Migrate existing SEO data to new analytics structure
        $old_meta_data = $wpdb->get_results("
            SELECT post_id, meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '_khm_seo_%'
        ");
        
        $scores_table = $wpdb->prefix . 'khm_seo_scores';
        
        foreach ($old_meta_data as $meta) {
            // Convert old meta data to new analytics format
            if ($meta->meta_key === '_khm_seo_score') {
                $existing_score = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM {$scores_table} WHERE post_id = %d
                ", $meta->post_id));
                
                if (!$existing_score) {
                    $wpdb->insert($scores_table, [
                        'post_id' => $meta->post_id,
                        'overall_score' => intval($meta->meta_value),
                        'created_at' => current_time('mysql')
                    ]);
                }
            }
        }
    }
    
    /**
     * Migrate to version 2.6
     */
    private function migrate_to_2_6() {
        // Any specific migrations for 2.6
        $this->update_score_calculations();
        $this->migrate_recommendations();
    }
    
    /**
     * Update score calculations for existing data
     */
    private function update_score_calculations() {
        global $wpdb;
        
        $scores_table = $wpdb->prefix . 'khm_seo_scores';
        
        // Update grade calculations for existing scores
        $wpdb->query("
            UPDATE {$scores_table} 
            SET grade = CASE 
                WHEN overall_score >= 90 THEN 'A+'
                WHEN overall_score >= 80 THEN 'A'
                WHEN overall_score >= 70 THEN 'B'
                WHEN overall_score >= 60 THEN 'C'
                WHEN overall_score >= 50 THEN 'D'
                ELSE 'F'
            END
            WHERE grade = 'F' AND overall_score > 0
        ");
    }
    
    /**
     * Migrate recommendations data
     */
    private function migrate_recommendations() {
        // Migrate any existing recommendations to new structure
        global $wpdb;
        
        $recommendations_table = $wpdb->prefix . 'khm_seo_recommendations';
        
        // Create default recommendations for low-scoring content
        $low_scores = $wpdb->get_results("
            SELECT post_id, overall_score 
            FROM {$wpdb->prefix}khm_seo_scores 
            WHERE overall_score < 60
        ");
        
        foreach ($low_scores as $score) {
            $existing_rec = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$recommendations_table} 
                WHERE post_id = %d AND recommendation_type = 'score_improvement'
            ", $score->post_id));
            
            if (!$existing_rec) {
                $wpdb->insert($recommendations_table, [
                    'post_id' => $score->post_id,
                    'recommendation_type' => 'score_improvement',
                    'category' => 'general',
                    'priority' => $score->overall_score < 40 ? 'high' : 'medium',
                    'title' => 'Improve Overall SEO Score',
                    'description' => 'This content has a low SEO score and needs optimization.',
                    'recommendation_text' => 'Review and optimize content, meta tags, and technical elements.',
                    'expected_impact' => 'high'
                ]);
            }
        }
    }
    
    /**
     * Get table name with prefix
     *
     * @param string $table_key Table identifier
     * @return string Full table name
     */
    public function get_table_name($table_key) {
        return isset($this->tables[$table_key]) ? $this->tables[$table_key]['name'] : null;
    }
    
    /**
     * Store SEO score data
     *
     * @param int $post_id Post ID
     * @param array $score_data Score analysis data
     * @return int|false Insert ID or false on failure
     */
    public function store_seo_score($post_id, $score_data) {
        global $wpdb;
        
        $table_name = $this->get_table_name('seo_scores');
        
        $data = [
            'post_id' => $post_id,
            'overall_score' => $score_data['overall_score'] ?? 0,
            'content_quality_score' => $score_data['category_scores']['content_quality']['score'] ?? 0,
            'technical_seo_score' => $score_data['category_scores']['technical_seo']['score'] ?? 0,
            'social_optimization_score' => $score_data['category_scores']['social_optimization']['score'] ?? 0,
            'user_experience_score' => $score_data['category_scores']['user_experience']['score'] ?? 0,
            'grade' => $score_data['grade'] ?? 'F',
            'category_scores' => json_encode($score_data['category_scores'] ?? []),
            'detailed_analysis' => json_encode($score_data['detailed_analysis'] ?? []),
            'recommendations' => json_encode($score_data['recommendations'] ?? []),
            'critical_issues' => json_encode($score_data['critical_issues'] ?? []),
            'improvement_opportunities' => json_encode($score_data['improvement_opportunities'] ?? []),
            'analysis_metadata' => json_encode([
                'analysis_version' => '2.6.0',
                'analysis_timestamp' => current_time('c'),
                'analysis_duration' => $score_data['analysis_duration'] ?? 0
            ])
        ];
        
        return $wpdb->insert($table_name, $data);
    }
    
    /**
     * Store metric data
     *
     * @param array $metric_data Metric information
     * @return int|false Insert ID or false on failure
     */
    public function store_metric($metric_data) {
        global $wpdb;
        
        $table_name = $this->get_table_name('metrics');
        
        return $wpdb->insert($table_name, [
            'post_id' => $metric_data['post_id'] ?? null,
            'metric_type' => $metric_data['metric_type'],
            'metric_name' => $metric_data['metric_name'],
            'metric_value' => $metric_data['metric_value'],
            'metric_unit' => $metric_data['metric_unit'] ?? null,
            'measurement_date' => $metric_data['measurement_date'] ?? current_time('mysql'),
            'metadata' => json_encode($metric_data['metadata'] ?? [])
        ]);
    }
    
    /**
     * Get SEO scores for post
     *
     * @param int $post_id Post ID
     * @param int $limit Number of records to return
     * @return array Score history
     */
    public function get_seo_scores($post_id, $limit = 10) {
        global $wpdb;
        
        $table_name = $this->get_table_name('seo_scores');
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_name} 
            WHERE post_id = %d 
            ORDER BY created_at DESC 
            LIMIT %d
        ", $post_id, $limit));
    }
    
    /**
     * Get metrics data
     *
     * @param array $filters Query filters
     * @return array Metrics data
     */
    public function get_metrics($filters = []) {
        global $wpdb;
        
        $table_name = $this->get_table_name('metrics');
        $where_conditions = ['1=1'];
        $params = [];
        
        if (!empty($filters['post_id'])) {
            $where_conditions[] = 'post_id = %d';
            $params[] = $filters['post_id'];
        }
        
        if (!empty($filters['metric_type'])) {
            $where_conditions[] = 'metric_type = %s';
            $params[] = $filters['metric_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'measurement_date >= %s';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'measurement_date <= %s';
            $params[] = $filters['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 100;
        
        $sql = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY measurement_date DESC LIMIT {$limit}";
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            return $wpdb->get_results($sql);
        }
    }
    
    /**
     * Clean up old data
     *
     * @param int $days Number of days to retain
     */
    public function cleanup_old_data($days = 365) {
        global $wpdb;
        
        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Clean up old metrics (keep latest for each post/metric type)
        $metrics_table = $this->get_table_name('metrics');
        $wpdb->query($wpdb->prepare("
            DELETE m1 FROM {$metrics_table} m1
            INNER JOIN {$metrics_table} m2 
            WHERE m1.post_id = m2.post_id 
            AND m1.metric_type = m2.metric_type 
            AND m1.metric_name = m2.metric_name
            AND m1.measurement_date < m2.measurement_date
            AND m1.created_at < %s
        ", $date_threshold));
        
        // Clean up old audit logs
        $audit_table = $this->get_table_name('audit_log');
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$audit_table} WHERE created_at < %s
        ", $date_threshold));
    }
    
    /**
     * Cleanup scheduled events on deactivation
     */
    public function cleanup_scheduled_events() {
        wp_clear_scheduled_hook('khm_seo_daily_analytics');
        wp_clear_scheduled_hook('khm_seo_weekly_report');
        wp_clear_scheduled_hook('khm_seo_monthly_report');
        wp_clear_scheduled_hook('khm_seo_cleanup_old_data');
    }
    
    /**
     * Remove tables on uninstall (optional)
     */
    public function remove_tables() {
        global $wpdb;
        
        foreach ($this->tables as $table_info) {
            $wpdb->query("DROP TABLE IF EXISTS {$table_info['name']}");
        }
        
        delete_option($this->version_option);
    }
    
    /**
     * Get database statistics
     *
     * @return array Database statistics
     */
    public function get_database_stats() {
        global $wpdb;
        
        $stats = [];
        
        foreach ($this->tables as $table_key => $table_info) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_info['name']}");
            $size = $wpdb->get_var("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                                   FROM information_schema.TABLES 
                                   WHERE table_schema = DATABASE() 
                                   AND table_name = '{$table_info['name']}'");
            
            $stats[$table_key] = [
                'name' => $table_info['name'],
                'rows' => intval($count),
                'size_mb' => floatval($size)
            ];
        }
        
        return $stats;
    }
}