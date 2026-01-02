<?php
/**
 * Advanced Analytics Engine for comprehensive SEO performance tracking
 * Part of Phase 8: Final Integration & Advanced Features
 *
 * This class provides enterprise-grade SEO analytics with data visualization,
 * comprehensive reporting, and actionable insights for SEO optimization.
 *
 * @package KHM_SEO\Analytics
 * @version 1.0.0
 * @since Phase 8
 */

namespace KHM_SEO\Analytics;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Advanced Analytics Engine Class
 * 
 * Provides comprehensive SEO analytics including:
 * - Multi-dimensional SEO performance tracking
 * - Advanced data visualization and reporting
 * - Competitive analysis and benchmarking
 * - ROI tracking and conversion attribution
 * - Automated insights and recommendations
 */
class AdvancedAnalyticsEngine {
    
    /**
     * Analytics configuration
     *
     * @var array
     */
    private $config = [];
    
    /**
     * Database table names
     *
     * @var array
     */
    private $tables = [];
    
    /**
     * Data aggregation periods
     *
     * @var array
     */
    private $periods = [];
    
    /**
     * Analytics metrics definitions
     *
     * @var array
     */
    private $metrics = [];
    
    /**
     * Chart.js configuration
     *
     * @var array
     */
    private $chart_config = [];
    
    /**
     * Initialize the analytics engine
     */
    public function __construct() {
        $this->init_config();
        $this->setup_database_tables();
        $this->define_metrics();
        $this->setup_periods();
        $this->init_chart_config();
        $this->init_hooks();
    }
    
    /**
     * Initialize analytics configuration
     */
    private function init_config() {
        $this->config = [
            'data_retention_days' => get_option('khm_analytics_retention', 365),
            'real_time_tracking' => get_option('khm_analytics_realtime', true),
            'competitor_tracking' => get_option('khm_analytics_competitors', false),
            'advanced_attribution' => get_option('khm_analytics_attribution', true),
            'automated_insights' => get_option('khm_analytics_insights', true),
            'export_formats' => ['pdf', 'excel', 'csv', 'json'],
            'dashboard_refresh_rate' => 30, // seconds
            'batch_processing_size' => 1000,
            'cache_duration' => 300 // 5 minutes
        ];
    }
    
    /**
     * Setup database table structure
     */
    private function setup_database_tables() {
        global $wpdb;
        
        $this->tables = [
            'seo_metrics' => $wpdb->prefix . 'khm_seo_metrics',
            'keyword_rankings' => $wpdb->prefix . 'khm_keyword_rankings', 
            'traffic_analytics' => $wpdb->prefix . 'khm_traffic_analytics',
            'conversion_tracking' => $wpdb->prefix . 'khm_conversion_tracking',
            'competitor_data' => $wpdb->prefix . 'khm_competitor_data',
            'seo_insights' => $wpdb->prefix . 'khm_seo_insights',
            'report_cache' => $wpdb->prefix . 'khm_report_cache'
        ];
        
        $this->create_analytics_tables();
    }
    
    /**
     * Create analytics database tables
     */
    private function create_analytics_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // SEO Metrics table - comprehensive SEO tracking
        $seo_metrics_sql = "CREATE TABLE IF NOT EXISTS {$this->tables['seo_metrics']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            post_type varchar(50) NOT NULL DEFAULT 'post',
            date_recorded datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            
            -- SEO Performance Metrics
            seo_score decimal(5,2) NOT NULL DEFAULT 0.00,
            keyword_density decimal(5,2) NOT NULL DEFAULT 0.00,
            readability_score decimal(5,2) NOT NULL DEFAULT 0.00,
            content_score decimal(5,2) NOT NULL DEFAULT 0.00,
            technical_score decimal(5,2) NOT NULL DEFAULT 0.00,
            
            -- Content Analysis
            word_count int(11) NOT NULL DEFAULT 0,
            heading_count int(11) NOT NULL DEFAULT 0,
            image_count int(11) NOT NULL DEFAULT 0,
            link_count int(11) NOT NULL DEFAULT 0,
            
            -- Schema & Structure
            schema_types text,
            meta_title_length int(11) NOT NULL DEFAULT 0,
            meta_description_length int(11) NOT NULL DEFAULT 0,
            
            -- Performance Data
            page_load_time decimal(8,3) NOT NULL DEFAULT 0.000,
            core_web_vitals_score decimal(5,2) NOT NULL DEFAULT 0.00,
            mobile_score decimal(5,2) NOT NULL DEFAULT 0.00,
            
            -- Social Metrics
            social_shares int(11) NOT NULL DEFAULT 0,
            social_engagement decimal(8,2) NOT NULL DEFAULT 0.00,
            
            -- Additional metadata
            target_keywords text,
            issues_detected text,
            recommendations text,
            
            PRIMARY KEY (id),
            KEY post_id_date (post_id, date_recorded),
            KEY post_type_date (post_type, date_recorded),
            KEY seo_score (seo_score),
            KEY date_recorded (date_recorded)
        ) $charset_collate;";
        
        // Keyword Rankings table - track keyword positions
        $keyword_rankings_sql = "CREATE TABLE IF NOT EXISTS {$this->tables['keyword_rankings']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            post_id bigint(20) unsigned,
            url varchar(500) NOT NULL,
            search_engine varchar(50) NOT NULL DEFAULT 'google',
            country_code varchar(5) NOT NULL DEFAULT 'US',
            device_type varchar(20) NOT NULL DEFAULT 'desktop',
            
            -- Ranking Data
            current_position int(11),
            previous_position int(11),
            best_position int(11),
            worst_position int(11),
            average_position decimal(8,2),
            
            -- Metrics
            search_volume int(11),
            competition_score decimal(5,2),
            cpc decimal(8,2),
            traffic_potential int(11),
            
            -- Tracking
            first_tracked datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            tracking_frequency varchar(20) NOT NULL DEFAULT 'daily',
            
            -- Additional data
            serp_features text,
            click_through_rate decimal(5,2),
            impressions int(11),
            clicks int(11),
            
            PRIMARY KEY (id),
            UNIQUE KEY keyword_url_engine (keyword, url, search_engine, country_code, device_type),
            KEY post_id (post_id),
            KEY search_engine (search_engine),
            KEY current_position (current_position),
            KEY last_updated (last_updated),
            KEY keyword_index (keyword)
        ) $charset_collate;";
        
        // Traffic Analytics table - comprehensive traffic tracking
        $traffic_analytics_sql = "CREATE TABLE IF NOT EXISTS {$this->tables['traffic_analytics']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            date_recorded date NOT NULL,
            post_id bigint(20) unsigned,
            post_type varchar(50) NOT NULL DEFAULT 'post',
            
            -- Traffic Metrics
            organic_sessions int(11) NOT NULL DEFAULT 0,
            organic_users int(11) NOT NULL DEFAULT 0,
            organic_pageviews int(11) NOT NULL DEFAULT 0,
            direct_traffic int(11) NOT NULL DEFAULT 0,
            referral_traffic int(11) NOT NULL DEFAULT 0,
            social_traffic int(11) NOT NULL DEFAULT 0,
            email_traffic int(11) NOT NULL DEFAULT 0,
            paid_traffic int(11) NOT NULL DEFAULT 0,
            
            -- Engagement Metrics
            bounce_rate decimal(5,2) NOT NULL DEFAULT 0.00,
            average_session_duration decimal(8,2) NOT NULL DEFAULT 0.00,
            pages_per_session decimal(5,2) NOT NULL DEFAULT 0.00,
            conversion_rate decimal(5,2) NOT NULL DEFAULT 0.00,
            
            -- Geographic Data
            top_countries text,
            top_cities text,
            top_regions text,
            
            -- Technology Data
            top_browsers text,
            top_devices text,
            top_operating_systems text,
            
            -- Search Data
            top_keywords text,
            top_landing_pages text,
            search_impressions int(11) NOT NULL DEFAULT 0,
            search_clicks int(11) NOT NULL DEFAULT 0,
            
            PRIMARY KEY (id),
            UNIQUE KEY date_post (date_recorded, post_id),
            KEY post_id (post_id),
            KEY post_type (post_type),
            KEY date_recorded (date_recorded),
            KEY organic_sessions (organic_sessions)
        ) $charset_collate;";
        
        // Conversion Tracking table - track conversions and ROI
        $conversion_tracking_sql = "CREATE TABLE IF NOT EXISTS {$this->tables['conversion_tracking']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned,
            session_id varchar(255) NOT NULL,
            post_id bigint(20) unsigned,
            
            -- Conversion Data
            conversion_type varchar(100) NOT NULL,
            conversion_value decimal(10,2) NOT NULL DEFAULT 0.00,
            conversion_currency varchar(3) NOT NULL DEFAULT 'USD',
            conversion_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            
            -- Attribution Data
            traffic_source varchar(100),
            campaign_name varchar(255),
            keyword varchar(255),
            landing_page varchar(500),
            referrer_url varchar(500),
            
            -- User Journey
            pages_visited int(11) NOT NULL DEFAULT 1,
            session_duration int(11) NOT NULL DEFAULT 0,
            touch_points text,
            
            -- Additional Data
            user_agent text,
            ip_address varchar(45),
            device_type varchar(50),
            browser varchar(100),
            
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY post_id (post_id),
            KEY conversion_type (conversion_type),
            KEY conversion_time (conversion_time),
            KEY session_id (session_id),
            KEY traffic_source (traffic_source)
        ) $charset_collate;";
        
        // Competitor Data table - competitive analysis
        $competitor_data_sql = "CREATE TABLE IF NOT EXISTS {$this->tables['competitor_data']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            competitor_domain varchar(255) NOT NULL,
            competitor_name varchar(255),
            keyword varchar(255) NOT NULL,
            
            -- Ranking Data
            position int(11),
            url varchar(500),
            title text,
            meta_description text,
            
            -- Content Analysis
            content_length int(11),
            content_score decimal(5,2),
            social_shares int(11),
            backlinks int(11),
            domain_authority decimal(5,2),
            
            -- Performance
            page_load_time decimal(8,3),
            mobile_score decimal(5,2),
            
            -- Tracking
            date_analyzed datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            analysis_type varchar(50) NOT NULL DEFAULT 'keyword',
            
            PRIMARY KEY (id),
            KEY competitor_domain (competitor_domain),
            KEY keyword (keyword),
            KEY position (position),
            KEY date_analyzed (date_analyzed),
            UNIQUE KEY competitor_keyword_date (competitor_domain, keyword, date_analyzed)
        ) $charset_collate;";
        
        // SEO Insights table - automated insights and recommendations
        $seo_insights_sql = "CREATE TABLE IF NOT EXISTS {$this->tables['seo_insights']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            insight_type varchar(100) NOT NULL,
            post_id bigint(20) unsigned,
            priority varchar(20) NOT NULL DEFAULT 'medium',
            
            -- Insight Data
            title varchar(255) NOT NULL,
            description text NOT NULL,
            recommendations text,
            impact_score decimal(5,2) NOT NULL DEFAULT 0.00,
            difficulty_score decimal(5,2) NOT NULL DEFAULT 0.00,
            
            -- Implementation
            status varchar(50) NOT NULL DEFAULT 'active',
            implemented boolean NOT NULL DEFAULT false,
            implementation_date datetime NULL,
            
            -- Tracking
            created_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_date datetime NULL,
            
            -- Metrics
            views int(11) NOT NULL DEFAULT 0,
            clicks int(11) NOT NULL DEFAULT 0,
            dismissals int(11) NOT NULL DEFAULT 0,
            
            PRIMARY KEY (id),
            KEY insight_type (insight_type),
            KEY post_id (post_id),
            KEY priority (priority),
            KEY status (status),
            KEY created_date (created_date),
            KEY impact_score (impact_score)
        ) $charset_collate;";
        
        // Report Cache table - cache expensive reports
        $report_cache_sql = "CREATE TABLE IF NOT EXISTS {$this->tables['report_cache']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            report_type varchar(100) NOT NULL,
            parameters text,
            
            -- Cache Data
            data longtext NOT NULL,
            data_format varchar(20) NOT NULL DEFAULT 'json',
            compressed boolean NOT NULL DEFAULT false,
            
            -- Cache Management
            created_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_date datetime NOT NULL,
            access_count int(11) NOT NULL DEFAULT 0,
            last_accessed datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            
            -- Metadata
            file_size bigint(20) NOT NULL DEFAULT 0,
            generation_time decimal(8,3) NOT NULL DEFAULT 0.000,
            user_id bigint(20) unsigned,
            
            PRIMARY KEY (id),
            UNIQUE KEY cache_key (cache_key),
            KEY report_type (report_type),
            KEY expires_date (expires_date),
            KEY user_id (user_id),
            KEY created_date (created_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($seo_metrics_sql);
        dbDelta($keyword_rankings_sql);
        dbDelta($traffic_analytics_sql);
        dbDelta($conversion_tracking_sql);
        dbDelta($competitor_data_sql);
        dbDelta($seo_insights_sql);
        dbDelta($report_cache_sql);
    }
    
    /**
     * Define analytics metrics and calculations
     */
    private function define_metrics() {
        $this->metrics = [
            // Primary SEO Metrics
            'seo_score' => [
                'name' => 'Overall SEO Score',
                'description' => 'Comprehensive SEO performance score',
                'calculation' => 'weighted_average',
                'components' => ['content', 'technical', 'performance', 'social'],
                'weights' => [40, 30, 20, 10],
                'range' => [0, 100],
                'format' => 'percentage'
            ],
            
            'keyword_performance' => [
                'name' => 'Keyword Performance',
                'description' => 'Overall keyword ranking performance',
                'calculation' => 'position_weighted_score',
                'components' => ['average_position', 'ranking_distribution', 'search_volume_weighted'],
                'range' => [0, 100],
                'format' => 'percentage'
            ],
            
            'content_optimization' => [
                'name' => 'Content Optimization Score',
                'description' => 'Content quality and optimization score',
                'calculation' => 'multi_factor',
                'factors' => [
                    'keyword_density' => 25,
                    'content_length' => 20,
                    'readability' => 20,
                    'structure' => 20,
                    'uniqueness' => 15
                ],
                'range' => [0, 100],
                'format' => 'percentage'
            ],
            
            'technical_seo' => [
                'name' => 'Technical SEO Score',
                'description' => 'Technical implementation quality',
                'calculation' => 'checklist_based',
                'factors' => [
                    'meta_tags' => 20,
                    'schema_markup' => 15,
                    'url_structure' => 15,
                    'internal_linking' => 15,
                    'image_optimization' => 10,
                    'mobile_optimization' => 15,
                    'site_speed' => 10
                ],
                'range' => [0, 100],
                'format' => 'percentage'
            ],
            
            // Traffic Metrics
            'organic_growth' => [
                'name' => 'Organic Traffic Growth',
                'description' => 'Period-over-period organic traffic growth',
                'calculation' => 'percentage_change',
                'baseline' => 'previous_period',
                'format' => 'percentage_change'
            ],
            
            'traffic_quality' => [
                'name' => 'Traffic Quality Score',
                'description' => 'Quality of organic traffic based on engagement',
                'calculation' => 'engagement_weighted',
                'factors' => [
                    'bounce_rate' => -30,
                    'session_duration' => 25,
                    'pages_per_session' => 25,
                    'conversion_rate' => 20
                ],
                'range' => [0, 100],
                'format' => 'percentage'
            ],
            
            // Competitive Metrics
            'competitive_visibility' => [
                'name' => 'Competitive Visibility',
                'description' => 'Visibility compared to competitors',
                'calculation' => 'relative_comparison',
                'baseline' => 'competitor_average',
                'format' => 'relative_percentage'
            ],
            
            'market_share' => [
                'name' => 'SEO Market Share',
                'description' => 'Estimated market share based on rankings',
                'calculation' => 'traffic_potential_based',
                'factors' => ['search_volume', 'click_through_rate', 'position'],
                'format' => 'percentage'
            ],
            
            // ROI Metrics
            'seo_roi' => [
                'name' => 'SEO Return on Investment',
                'description' => 'ROI from SEO efforts',
                'calculation' => 'revenue_attribution',
                'factors' => ['conversion_value', 'organic_attribution', 'time_investment'],
                'format' => 'currency_percentage'
            ],
            
            'conversion_attribution' => [
                'name' => 'SEO Conversion Attribution',
                'description' => 'Conversions attributed to SEO efforts',
                'calculation' => 'multi_touch_attribution',
                'attribution_model' => 'time_decay',
                'format' => 'number'
            ]
        ];
    }
    
    /**
     * Setup time periods for analytics
     */
    private function setup_periods() {
        $this->periods = [
            'today' => [
                'label' => 'Today',
                'sql_condition' => "DATE(date_recorded) = CURDATE()",
                'comparison_period' => 'yesterday',
                'granularity' => 'hour'
            ],
            'yesterday' => [
                'label' => 'Yesterday', 
                'sql_condition' => "DATE(date_recorded) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
                'comparison_period' => 'day_before_yesterday',
                'granularity' => 'hour'
            ],
            'last_7_days' => [
                'label' => 'Last 7 Days',
                'sql_condition' => "date_recorded >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                'comparison_period' => 'previous_7_days',
                'granularity' => 'day'
            ],
            'last_30_days' => [
                'label' => 'Last 30 Days',
                'sql_condition' => "date_recorded >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", 
                'comparison_period' => 'previous_30_days',
                'granularity' => 'day'
            ],
            'last_90_days' => [
                'label' => 'Last 90 Days',
                'sql_condition' => "date_recorded >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)",
                'comparison_period' => 'previous_90_days',
                'granularity' => 'week'
            ],
            'last_12_months' => [
                'label' => 'Last 12 Months',
                'sql_condition' => "date_recorded >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)",
                'comparison_period' => 'previous_12_months',
                'granularity' => 'month'
            ],
            'this_month' => [
                'label' => 'This Month',
                'sql_condition' => "MONTH(date_recorded) = MONTH(CURDATE()) AND YEAR(date_recorded) = YEAR(CURDATE())",
                'comparison_period' => 'last_month',
                'granularity' => 'day'
            ],
            'last_month' => [
                'label' => 'Last Month', 
                'sql_condition' => "date_recorded >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH) AND date_recorded < DATE_FORMAT(CURDATE(), '%Y-%m-01')",
                'comparison_period' => 'month_before_last',
                'granularity' => 'day'
            ],
            'this_year' => [
                'label' => 'This Year',
                'sql_condition' => "YEAR(date_recorded) = YEAR(CURDATE())",
                'comparison_period' => 'last_year',
                'granularity' => 'month'
            ],
            'last_year' => [
                'label' => 'Last Year',
                'sql_condition' => "YEAR(date_recorded) = YEAR(CURDATE()) - 1",
                'comparison_period' => 'year_before_last',
                'granularity' => 'month'
            ],
            'custom' => [
                'label' => 'Custom Range',
                'sql_condition' => null, // Set dynamically
                'comparison_period' => 'custom_comparison',
                'granularity' => 'auto'
            ]
        ];
    }
    
    /**
     * Initialize Chart.js configuration
     */
    private function init_chart_config() {
        $this->chart_config = [
            'default_options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => [
                        'position' => 'top',
                        'labels' => [
                            'usePointStyle' => true,
                            'padding' => 20
                        ]
                    ],
                    'tooltip' => [
                        'mode' => 'index',
                        'intersect' => false,
                        'backgroundColor' => 'rgba(0, 0, 0, 0.8)',
                        'titleColor' => '#fff',
                        'bodyColor' => '#fff',
                        'borderColor' => '#ddd',
                        'borderWidth' => 1
                    ]
                ],
                'scales' => [
                    'x' => [
                        'display' => true,
                        'grid' => [
                            'display' => false
                        ]
                    ],
                    'y' => [
                        'display' => true,
                        'grid' => [
                            'color' => 'rgba(0, 0, 0, 0.1)'
                        ]
                    ]
                ],
                'interaction' => [
                    'mode' => 'nearest',
                    'axis' => 'x',
                    'intersect' => false
                ]
            ],
            
            'color_scheme' => [
                'primary' => '#0073aa',
                'secondary' => '#005a87', 
                'success' => '#28a745',
                'warning' => '#ffc107',
                'danger' => '#dc3545',
                'info' => '#17a2b8',
                'light' => '#f8f9fa',
                'dark' => '#343a40',
                'gradient_start' => '#0073aa',
                'gradient_end' => '#00a0d2'
            ],
            
            'chart_types' => [
                'line' => [
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'borderWidth' => 3
                ],
                'bar' => [
                    'borderRadius' => 4,
                    'borderSkipped' => false
                ],
                'doughnut' => [
                    'cutout' => '70%',
                    'borderWidth' => 2
                ],
                'radar' => [
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'borderWidth' => 2
                ]
            ]
        ];
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin hooks
        add_action('admin_menu', [$this, 'add_analytics_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_analytics_assets']);
        
        // AJAX handlers for analytics
        add_action('wp_ajax_khm_get_analytics_data', [$this, 'ajax_get_analytics_data']);
        add_action('wp_ajax_khm_generate_report', [$this, 'ajax_generate_report']);
        add_action('wp_ajax_khm_export_report', [$this, 'ajax_export_report']);
        add_action('wp_ajax_khm_save_analytics_settings', [$this, 'ajax_save_analytics_settings']);
        add_action('wp_ajax_khm_get_insights', [$this, 'ajax_get_insights']);
        add_action('wp_ajax_khm_dismiss_insight', [$this, 'ajax_dismiss_insight']);
        
        // Data collection hooks
        add_action('wp_footer', [$this, 'inject_tracking_script']);
        add_action('wp', [$this, 'track_page_view']);
        add_action('wp_ajax_khm_track_event', [$this, 'ajax_track_event']);
        add_action('wp_ajax_nopriv_khm_track_event', [$this, 'ajax_track_event']);
        
        // Scheduled analytics processing
        add_action('khm_analytics_daily_processing', [$this, 'process_daily_analytics']);
        add_action('khm_analytics_weekly_processing', [$this, 'process_weekly_analytics']);
        add_action('khm_analytics_monthly_processing', [$this, 'process_monthly_analytics']);
        
        // Schedule events if not already scheduled
        if (!wp_next_scheduled('khm_analytics_daily_processing')) {
            wp_schedule_event(time(), 'daily', 'khm_analytics_daily_processing');
        }
        
        if (!wp_next_scheduled('khm_analytics_weekly_processing')) {
            wp_schedule_event(time(), 'weekly', 'khm_analytics_weekly_processing');
        }
        
        if (!wp_next_scheduled('khm_analytics_monthly_processing')) {
            wp_schedule_event(time(), 'monthly', 'khm_analytics_monthly_processing');
        }
        
        // Cache management
        add_action('khm_analytics_cache_cleanup', [$this, 'cleanup_expired_cache']);
        if (!wp_next_scheduled('khm_analytics_cache_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'khm_analytics_cache_cleanup');
        }
    }

    /**
     * Enqueue admin assets for analytics pages (placeholder to avoid missing callback fatal).
     */
    public function enqueue_analytics_assets() {
        // Only enqueue when on our analytics screens.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && strpos($screen->id, 'khm-analytics') === false) {
            return;
        }

        // Future: enqueue scripts/styles. For now, noop to satisfy hook.
    }
    
    /**
     * Add analytics menu to WordPress admin
     */
    public function add_analytics_menu() {
        add_submenu_page(
            'khm-seo',
            __('SEO Analytics', 'khm-seo'),
            __('Analytics', 'khm-seo'),
            'manage_options',
            'khm-seo-analytics',
            [$this, 'render_analytics_dashboard']
        );
    }
    
    /**
     * Render the analytics dashboard
     */
    public function render_analytics_dashboard() {
        // Enqueue dashboard assets
        $this->enqueue_dashboard_assets();
        
        // Get current user preferences
        $user_id = get_current_user_id();
        $dashboard_config = get_user_meta($user_id, 'khm_analytics_dashboard_config', true);
        
        if (!$dashboard_config) {
            $dashboard_config = $this->get_default_dashboard_config();
        }
        
        // Include the analytics dashboard template
        include plugin_dir_path(__FILE__) . 'templates/analytics-dashboard.php';
    }
    
    /**
     * Get default dashboard configuration
     */
    private function get_default_dashboard_config() {
        return [
            'widgets' => [
                'seo_overview' => ['position' => 1, 'enabled' => true],
                'keyword_rankings' => ['position' => 2, 'enabled' => true],
                'traffic_analytics' => ['position' => 3, 'enabled' => true],
                'performance_metrics' => ['position' => 4, 'enabled' => true],
                'competitor_analysis' => ['position' => 5, 'enabled' => false],
                'conversion_tracking' => ['position' => 6, 'enabled' => true],
                'seo_insights' => ['position' => 7, 'enabled' => true]
            ],
            'default_period' => 'last_30_days',
            'auto_refresh' => true,
            'refresh_interval' => 300,
            'export_format' => 'pdf'
        ];
    }
    
    /**
     * Enqueue analytics dashboard assets
     */
    private function enqueue_dashboard_assets() {
        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1',
            true
        );
        
        // Chart.js plugins
        wp_enqueue_script(
            'chartjs-adapter-date-fns',
            'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
            ['chartjs'],
            '2.0.0',
            true
        );
        
        // Analytics dashboard CSS
        wp_enqueue_style(
            'khm-analytics-dashboard',
            plugin_dir_url(__FILE__) . 'assets/css/analytics-dashboard.css',
            [],
            KHM_SEO_VERSION
        );
        
        // Analytics dashboard JavaScript
        wp_enqueue_script(
            'khm-analytics-dashboard',
            plugin_dir_url(__FILE__) . 'assets/js/analytics-dashboard.js',
            ['jquery', 'chartjs', 'chartjs-adapter-date-fns'],
            KHM_SEO_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('khm-analytics-dashboard', 'khmAnalytics', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_analytics_nonce'),
            'chart_config' => $this->chart_config,
            'periods' => $this->periods,
            'metrics' => $this->metrics,
            'strings' => [
                'loading' => __('Loading analytics data...', 'khm-seo'),
                'no_data' => __('No data available for the selected period', 'khm-seo'),
                'error' => __('Error loading analytics data', 'khm-seo'),
                'export_success' => __('Report exported successfully', 'khm-seo'),
                'export_error' => __('Error exporting report', 'khm-seo'),
                'insight_dismissed' => __('Insight dismissed', 'khm-seo'),
                'settings_saved' => __('Analytics settings saved', 'khm-seo')
            ]
        ]);
    }
    
    /**
     * Get analytics data for dashboard
     */
    public function ajax_get_analytics_data() {
        check_ajax_referer('khm_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'khm-seo'));
        }
        
        $period = sanitize_text_field($_POST['period'] ?? 'last_30_days');
        $metric = sanitize_text_field($_POST['metric'] ?? 'seo_score');
        $post_id = intval($_POST['post_id'] ?? 0);
        
        $data = $this->get_analytics_data($metric, $period, $post_id);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get analytics data for specific metric and period
     */
    public function get_analytics_data($metric, $period, $post_id = 0) {
        $cache_key = "analytics_{$metric}_{$period}_{$post_id}";
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $data = [];
        
        switch ($metric) {
            case 'seo_score':
                $data = $this->get_seo_score_data($period, $post_id);
                break;
            case 'keyword_performance':
                $data = $this->get_keyword_performance_data($period, $post_id);
                break;
            case 'traffic_analytics':
                $data = $this->get_traffic_analytics_data($period, $post_id);
                break;
            case 'conversion_tracking':
                $data = $this->get_conversion_tracking_data($period, $post_id);
                break;
            default:
                $data = ['error' => 'Unknown metric'];
        }
        
        // Cache the data
        $this->cache_data($cache_key, $data, $this->config['cache_duration']);
        
        return $data;
    }
    
    /**
     * Generate comprehensive SEO insights
     */
    public function generate_seo_insights($post_id = 0) {
        $insights = [];
        
        // Get recent performance data
        $seo_data = $this->get_seo_score_data('last_30_days', $post_id);
        $keyword_data = $this->get_keyword_performance_data('last_30_days', $post_id);
        $traffic_data = $this->get_traffic_analytics_data('last_30_days', $post_id);
        
        // Generate insights based on data patterns
        $insights = array_merge($insights, $this->analyze_seo_trends($seo_data));
        $insights = array_merge($insights, $this->analyze_keyword_opportunities($keyword_data));
        $insights = array_merge($insights, $this->analyze_traffic_patterns($traffic_data));
        
        // Store insights in database
        foreach ($insights as $insight) {
            $this->store_insight($insight);
        }
        
        return $insights;
    }
    
    /**
     * Export analytics report in various formats
     */
    public function export_report($format, $period, $post_id = 0) {
        $report_data = $this->generate_comprehensive_report($period, $post_id);
        
        switch ($format) {
            case 'pdf':
                return $this->export_pdf_report($report_data);
            case 'excel':
                return $this->export_excel_report($report_data);
            case 'csv':
                return $this->export_csv_report($report_data);
            case 'json':
                return $this->export_json_report($report_data);
            default:
                return false;
        }
    }
    
    /**
     * Get SEO score data for chart visualization
     */
    private function get_seo_score_data($period, $post_id = 0) {
        global $wpdb;
        
        $where_clause = $this->build_where_clause($period, $post_id);
        
        $sql = "SELECT 
                    DATE(date_recorded) as date,
                    AVG(seo_score) as avg_seo_score,
                    AVG(content_score) as avg_content_score,
                    AVG(technical_score) as avg_technical_score,
                    COUNT(*) as data_points
                FROM {$this->tables['seo_metrics']} 
                WHERE {$where_clause}
                GROUP BY DATE(date_recorded)
                ORDER BY date ASC";
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        return [
            'labels' => array_column($results, 'date'),
            'datasets' => [
                [
                    'label' => 'Overall SEO Score',
                    'data' => array_column($results, 'avg_seo_score'),
                    'borderColor' => $this->chart_config['color_scheme']['primary'],
                    'backgroundColor' => $this->hex_to_rgba($this->chart_config['color_scheme']['primary'], 0.1)
                ],
                [
                    'label' => 'Content Score',
                    'data' => array_column($results, 'avg_content_score'),
                    'borderColor' => $this->chart_config['color_scheme']['success'],
                    'backgroundColor' => $this->hex_to_rgba($this->chart_config['color_scheme']['success'], 0.1)
                ],
                [
                    'label' => 'Technical Score',
                    'data' => array_column($results, 'avg_technical_score'),
                    'borderColor' => $this->chart_config['color_scheme']['info'],
                    'backgroundColor' => $this->hex_to_rgba($this->chart_config['color_scheme']['info'], 0.1)
                ]
            ]
        ];
    }
    
    /**
     * Build WHERE clause for database queries
     */
    private function build_where_clause($period, $post_id = 0) {
        $conditions = [];
        
        if ($post_id > 0) {
            $conditions[] = "post_id = {$post_id}";
        }
        
        if (isset($this->periods[$period])) {
            $conditions[] = $this->periods[$period]['sql_condition'];
        }
        
        return implode(' AND ', $conditions) ?: '1=1';
    }
    
    /**
     * Convert hex color to RGBA
     */
    private function hex_to_rgba($hex, $alpha = 1) {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
    
    /**
     * Cache data for performance
     */
    private function cache_data($key, $data, $duration) {
        global $wpdb;
        
        $compressed_data = gzcompress(json_encode($data));
        $expires_date = date('Y-m-d H:i:s', time() + $duration);
        
        $wpdb->replace(
            $this->tables['report_cache'],
            [
                'cache_key' => $key,
                'report_type' => 'analytics',
                'data' => $compressed_data,
                'data_format' => 'json',
                'compressed' => 1,
                'expires_date' => $expires_date,
                'file_size' => strlen($compressed_data),
                'user_id' => get_current_user_id()
            ]
        );
    }
    
    /**
     * Get cached data
     */
    private function get_cached_data($key) {
        global $wpdb;
        
        $sql = "SELECT data, compressed FROM {$this->tables['report_cache']} 
                WHERE cache_key = %s AND expires_date > NOW()";
        
        $result = $wpdb->get_row($wpdb->prepare($sql, $key));
        
        if ($result) {
            $data = $result->compressed ? gzuncompress($result->data) : $result->data;
            return json_decode($data, true);
        }
        
        return false;
    }
    
    /**
     * Cleanup expired cache entries
     */
    public function cleanup_expired_cache() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$this->tables['report_cache']} WHERE expires_date < NOW()");
    }
    
    /**
     * Track page view for analytics
     */
    public function track_page_view() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        
        global $post;
        
        if (!$post) {
            return;
        }
        
        // Store page view data
        $this->store_page_view_data([
            'post_id' => $post->ID,
            'post_type' => $post->post_type,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->get_client_ip(),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Store page view data
     */
    private function store_page_view_data($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'khm_traffic_analytics';
        
        $wpdb->insert(
            $table,
            [
                'page_id' => $data['page_id'],
                'page_url' => $data['page_url'],
                'user_agent' => $data['user_agent'],
                'referrer' => $data['referrer'],
                'ip_address' => $data['ip_address'],
                'session_id' => $data['session_id'],
                'date' => current_time('Y-m-d'),
                'timestamp' => current_time('Y-m-d H:i:s'),
                'sessions' => 1,
                'pageviews' => 1,
                'bounce_rate' => 0,
                'avg_session_duration' => 0
            ],
            [
                '%d', '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%d', '%d', '%f', '%f'
            ]
        );
    }
    
    /**
     * Get SEO metrics
     */
    public function get_seo_metrics($date_range = '30days') {
        global $wpdb;
        
        $date_filter = $this->get_date_filter($date_range);
        $table = $wpdb->prefix . 'khm_seo_metrics';
        
        $metrics = $wpdb->get_row($wpdb->prepare("
            SELECT 
                AVG(overall_score) as avg_score,
                AVG(content_score) as content_score,
                AVG(technical_score) as technical_score,
                AVG(keyword_score) as keyword_score,
                AVG(performance_score) as performance_score,
                COUNT(*) as total_pages,
                SUM(CASE WHEN overall_score >= 90 THEN 1 ELSE 0 END) as excellent_pages,
                SUM(CASE WHEN overall_score >= 70 AND overall_score < 90 THEN 1 ELSE 0 END) as good_pages,
                SUM(CASE WHEN overall_score >= 50 AND overall_score < 70 THEN 1 ELSE 0 END) as fair_pages,
                SUM(CASE WHEN overall_score < 50 THEN 1 ELSE 0 END) as poor_pages
            FROM {$table} 
            WHERE created_at >= %s
        ", $date_filter));
        
        if (!$metrics) {
            return $this->get_default_metrics();
        }
        
        // Calculate score change
        $previous_period = $this->get_previous_period_metrics($date_range);
        $score_change = $metrics->avg_score - $previous_period['avg_score'];
        
        return [
            'overall_score' => round($metrics->avg_score, 1),
            'score_change' => round($score_change, 1),
            'breakdown' => [
                'content' => round($metrics->content_score, 1),
                'technical' => round($metrics->technical_score, 1),
                'keywords' => round($metrics->keyword_score, 1),
                'performance' => round($metrics->performance_score, 1)
            ],
            'distribution' => [
                'excellent' => (int)$metrics->excellent_pages,
                'good' => (int)$metrics->good_pages,
                'fair' => (int)$metrics->fair_pages,
                'poor' => (int)$metrics->poor_pages
            ],
            'total_pages' => (int)$metrics->total_pages
        ];
    }
    
    /**
     * Get traffic analytics data
     */
    public function get_traffic_analytics($date_range = '30days') {
        global $wpdb;
        
        $date_filter = $this->get_date_filter($date_range);
        $table = $wpdb->prefix . 'khm_traffic_analytics';
        
        $traffic = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(sessions) as total_sessions,
                SUM(pageviews) as total_pageviews,
                AVG(avg_session_duration) as avg_duration,
                AVG(bounce_rate) as avg_bounce_rate,
                SUM(organic_sessions) as organic_sessions,
                SUM(direct_sessions) as direct_sessions,
                SUM(referral_sessions) as referral_sessions,
                SUM(social_sessions) as social_sessions
            FROM {$table} 
            WHERE date >= %s
        ", $date_filter));
        
        if (!$traffic) {
            return $this->get_default_traffic();
        }
        
        $previous = $this->get_previous_period_traffic($date_range);
        
        return [
            'sessions' => (int)$traffic->total_sessions,
            'sessions_change' => $this->calculate_change($traffic->total_sessions, $previous['sessions']),
            'pageviews' => (int)$traffic->total_pageviews,
            'pageviews_change' => $this->calculate_change($traffic->total_pageviews, $previous['pageviews']),
            'avg_duration' => round($traffic->avg_duration, 2),
            'duration_change' => $this->calculate_change($traffic->avg_duration, $previous['avg_duration']),
            'bounce_rate' => round($traffic->avg_bounce_rate, 2),
            'bounce_change' => $this->calculate_change($traffic->avg_bounce_rate, $previous['bounce_rate'], true),
            'sources' => [
                'organic' => (int)$traffic->organic_sessions,
                'direct' => (int)$traffic->direct_sessions,
                'referral' => (int)$traffic->referral_sessions,
                'social' => (int)$traffic->social_sessions
            ],
            'geographic' => $this->get_geographic_data($date_range)
        ];
    }
    
    /**
     * Get keyword rankings data
     */
    public function get_keyword_data($date_range = '30days') {
        global $wpdb;
        
        $date_filter = $this->get_date_filter($date_range);
        $table = $wpdb->prefix . 'khm_keyword_rankings';
        
        $keywords = $wpdb->get_results($wpdb->prepare("
            SELECT 
                keyword,
                current_position,
                previous_position,
                search_volume,
                difficulty,
                url,
                DATE(created_at) as date
            FROM {$table} 
            WHERE created_at >= %s
            ORDER BY search_volume DESC, current_position ASC
        ", $date_filter));
        
        if (empty($keywords)) {
            return $this->get_default_keywords();
        }
        
        $total_keywords = count($keywords);
        $top_10 = array_filter($keywords, function($k) { return $k->current_position <= 10; });
        $top_100 = array_filter($keywords, function($k) { return $k->current_position <= 100; });
        
        $improved = array_filter($keywords, function($k) { 
            return $k->previous_position > $k->current_position; 
        });
        $declined = array_filter($keywords, function($k) { 
            return $k->previous_position < $k->current_position; 
        });
        
        return [
            'total_keywords' => $total_keywords,
            'top_10_count' => count($top_10),
            'top_100_count' => count($top_100),
            'improved_count' => count($improved),
            'declined_count' => count($declined),
            'avg_position' => $total_keywords > 0 ? round(array_sum(array_column($keywords, 'current_position')) / $total_keywords, 1) : 0,
            'distribution' => [
                'positions_1_10' => count($top_10),
                'positions_11_50' => count(array_filter($keywords, function($k) { 
                    return $k->current_position > 10 && $k->current_position <= 50; 
                })),
                'positions_51_100' => count(array_filter($keywords, function($k) { 
                    return $k->current_position > 50 && $k->current_position <= 100; 
                })),
                'positions_100_plus' => count(array_filter($keywords, function($k) { 
                    return $k->current_position > 100; 
                }))
            ],
            'top_keywords' => array_slice($keywords, 0, 20)
        ];
    }
    
    /**
     * Get conversion tracking data
     */
    public function get_conversion_data($date_range = '30days') {
        global $wpdb;
        
        $date_filter = $this->get_date_filter($date_range);
        $table = $wpdb->prefix . 'khm_conversion_tracking';
        
        $conversions = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(conversions) as total_conversions,
                SUM(conversion_value) as total_value,
                AVG(conversion_rate) as avg_rate,
                SUM(assisted_conversions) as assisted_conversions
            FROM {$table} 
            WHERE date >= %s
        ", $date_filter));
        
        if (!$conversions) {
            return $this->get_default_conversions();
        }
        
        $previous = $this->get_previous_period_conversions($date_range);
        
        return [
            'total_conversions' => (int)$conversions->total_conversions,
            'conversions_change' => $this->calculate_change($conversions->total_conversions, $previous['conversions']),
            'conversion_rate' => round($conversions->avg_rate, 2),
            'rate_change' => $this->calculate_change($conversions->avg_rate, $previous['rate']),
            'total_value' => round($conversions->total_value, 2),
            'value_change' => $this->calculate_change($conversions->total_value, $previous['value']),
            'assisted_conversions' => (int)$conversions->assisted_conversions,
            'goal_breakdown' => $this->get_goal_breakdown($date_range)
        ];
    }
    
    /**
     * Get content analysis data
     */
    public function get_content_data($date_range = '30days') {
        global $wpdb;
        
        $date_filter = $this->get_date_filter($date_range);
        
        $posts = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.ID,
                p.post_title,
                p.post_date,
                m.overall_score,
                m.content_score,
                m.keyword_score,
                t.sessions,
                t.pageviews,
                t.avg_session_duration,
                t.bounce_rate
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}khm_seo_metrics m ON p.ID = m.post_id
            LEFT JOIN {$wpdb->prefix}khm_traffic_analytics t ON p.ID = t.page_id
            WHERE p.post_status = 'publish' 
            AND p.post_type IN ('post', 'page')
            AND p.post_date >= %s
            ORDER BY m.overall_score DESC, t.sessions DESC
            LIMIT 50
        ", $date_filter));
        
        $content_performance = [];
        foreach ($posts as $post) {
            $content_performance[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'date' => $post->post_date,
                'seo_score' => round($post->overall_score ?: 0, 1),
                'sessions' => (int)($post->sessions ?: 0),
                'pageviews' => (int)($post->pageviews ?: 0),
                'avg_duration' => round($post->avg_session_duration ?: 0, 2),
                'bounce_rate' => round($post->bounce_rate ?: 0, 2),
                'score_class' => $this->get_score_class($post->overall_score ?: 0)
            ];
        }
        
        return [
            'content_performance' => $content_performance,
            'top_performing' => array_slice($content_performance, 0, 10),
            'needs_optimization' => array_filter($content_performance, function($content) {
                return $content['seo_score'] < 70;
            })
        ];
    }
    
    /**
     * Get competitor analysis data
     */
    public function get_competitor_data($date_range = '30days') {
        global $wpdb;
        
        $date_filter = $this->get_date_filter($date_range);
        $table = $wpdb->prefix . 'khm_competitor_data';
        
        $competitors = $wpdb->get_results($wpdb->prepare("
            SELECT 
                competitor_domain,
                AVG(visibility_score) as avg_visibility,
                AVG(keyword_overlap) as avg_overlap,
                AVG(traffic_estimate) as avg_traffic,
                COUNT(DISTINCT keyword) as shared_keywords
            FROM {$table} 
            WHERE created_at >= %s
            GROUP BY competitor_domain
            ORDER BY avg_visibility DESC
            LIMIT 10
        ", $date_filter));
        
        if (empty($competitors)) {
            return $this->get_default_competitors();
        }
        
        return [
            'competitors' => array_map(function($comp) {
                return [
                    'domain' => $comp->competitor_domain,
                    'visibility' => round($comp->avg_visibility, 1),
                    'overlap' => round($comp->avg_overlap, 1),
                    'traffic' => (int)$comp->avg_traffic,
                    'shared_keywords' => (int)$comp->shared_keywords
                ];
            }, $competitors),
            'market_share' => $this->calculate_market_share($competitors),
            'opportunity_keywords' => $this->get_opportunity_keywords($date_range)
        ];
    }
    
    /**
     * Calculate percentage change between two values
     */
    private function calculate_change($current, $previous, $reverse = false) {
        if (!$previous || $previous == 0) return 0;
        
        $change = (($current - $previous) / $previous) * 100;
        return $reverse ? -$change : $change;
    }
    
    /**
     * Get date filter for SQL queries
     */
    private function get_date_filter($date_range) {
        switch ($date_range) {
            case '7days':
                return date('Y-m-d', strtotime('-7 days'));
            case '30days':
                return date('Y-m-d', strtotime('-30 days'));
            case '90days':
                return date('Y-m-d', strtotime('-90 days'));
            case '12months':
                return date('Y-m-d', strtotime('-12 months'));
            default:
                return date('Y-m-d', strtotime('-30 days'));
        }
    }
    
    /**
     * Get previous period metrics for comparison
     */
    private function get_previous_period_metrics($date_range) {
        global $wpdb;
        
        $periods = $this->get_comparison_periods($date_range);
        $table = $wpdb->prefix . 'khm_seo_metrics';
        
        $metrics = $wpdb->get_row($wpdb->prepare("
            SELECT AVG(overall_score) as avg_score
            FROM {$table} 
            WHERE created_at >= %s AND created_at < %s
        ", $periods['start'], $periods['end']));
        
        return ['avg_score' => $metrics ? $metrics->avg_score : 0];
    }
    
    /**
     * Get previous period traffic for comparison
     */
    private function get_previous_period_traffic($date_range) {
        global $wpdb;
        
        $periods = $this->get_comparison_periods($date_range);
        $table = $wpdb->prefix . 'khm_traffic_analytics';
        
        $traffic = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(sessions) as sessions,
                SUM(pageviews) as pageviews,
                AVG(avg_session_duration) as avg_duration,
                AVG(bounce_rate) as bounce_rate
            FROM {$table} 
            WHERE date >= %s AND date < %s
        ", $periods['start'], $periods['end']));
        
        return [
            'sessions' => $traffic ? $traffic->sessions : 0,
            'pageviews' => $traffic ? $traffic->pageviews : 0,
            'avg_duration' => $traffic ? $traffic->avg_duration : 0,
            'bounce_rate' => $traffic ? $traffic->bounce_rate : 0
        ];
    }
    
    /**
     * Get previous period conversions for comparison
     */
    private function get_previous_period_conversions($date_range) {
        global $wpdb;
        
        $periods = $this->get_comparison_periods($date_range);
        $table = $wpdb->prefix . 'khm_conversion_tracking';
        
        $conversions = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(conversions) as conversions,
                AVG(conversion_rate) as rate,
                SUM(conversion_value) as value
            FROM {$table} 
            WHERE date >= %s AND date < %s
        ", $periods['start'], $periods['end']));
        
        return [
            'conversions' => $conversions ? $conversions->conversions : 0,
            'rate' => $conversions ? $conversions->rate : 0,
            'value' => $conversions ? $conversions->value : 0
        ];
    }
    
    /**
     * Get comparison periods for trend analysis
     */
    private function get_comparison_periods($date_range) {
        switch ($date_range) {
            case '7days':
                return [
                    'start' => date('Y-m-d', strtotime('-14 days')),
                    'end' => date('Y-m-d', strtotime('-7 days'))
                ];
            case '30days':
                return [
                    'start' => date('Y-m-d', strtotime('-60 days')),
                    'end' => date('Y-m-d', strtotime('-30 days'))
                ];
            case '90days':
                return [
                    'start' => date('Y-m-d', strtotime('-180 days')),
                    'end' => date('Y-m-d', strtotime('-90 days'))
                ];
            default:
                return [
                    'start' => date('Y-m-d', strtotime('-60 days')),
                    'end' => date('Y-m-d', strtotime('-30 days'))
                ];
        }
    }
    
    /**
     * Get geographic traffic data
     */
    private function get_geographic_data($date_range) {
        global $wpdb;
        
        $date_filter = $this->get_date_filter($date_range);
        $table = $wpdb->prefix . 'khm_traffic_analytics';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                country,
                SUM(sessions) as sessions,
                AVG(bounce_rate) as bounce_rate
            FROM {$table} 
            WHERE date >= %s AND country IS NOT NULL
            GROUP BY country
            ORDER BY sessions DESC
            LIMIT 10
        ", $date_filter));
    }
    
    /**
     * Get goal breakdown data
     */
    private function get_goal_breakdown($date_range) {
        global $wpdb;
        
        $date_filter = $this->get_date_filter($date_range);
        $table = $wpdb->prefix . 'khm_conversion_tracking';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                goal_type,
                SUM(conversions) as conversions,
                AVG(conversion_rate) as rate,
                SUM(conversion_value) as value
            FROM {$table} 
            WHERE date >= %s
            GROUP BY goal_type
            ORDER BY conversions DESC
        ", $date_filter));
    }
    
    /**
     * Calculate market share from competitor data
     */
    private function calculate_market_share($competitors) {
        if (empty($competitors)) {
            return 0;
        }
        
        $total_visibility = array_sum(array_column($competitors, 'avg_visibility'));
        return $total_visibility > 0 ? round((100 / count($competitors)), 1) : 0;
    }
    
    /**
     * Get opportunity keywords from competitor analysis
     */
    private function get_opportunity_keywords($date_range) {
        global $wpdb;
        
        $date_filter = $this->get_date_filter($date_range);
        $table = $wpdb->prefix . 'khm_competitor_data';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                keyword,
                competitor_domain,
                competitor_position,
                search_volume,
                difficulty,
                opportunity_score
            FROM {$table} 
            WHERE created_at >= %s 
            AND competitor_position <= 10
            AND keyword NOT IN (
                SELECT keyword FROM {$wpdb->prefix}khm_keyword_rankings 
                WHERE current_position <= 20
            )
            ORDER BY opportunity_score DESC, search_volume DESC
            LIMIT 20
        ", $date_filter));
    }
    
    /**
     * Get score classification
     */
    private function get_score_class($score) {
        if ($score >= 90) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 50) return 'fair';
        return 'poor';
    }
    
    /**
     * Get default metrics when no data exists
     */
    private function get_default_metrics() {
        return [
            'overall_score' => 0,
            'score_change' => 0,
            'breakdown' => [
                'content' => 0,
                'technical' => 0,
                'keywords' => 0,
                'performance' => 0
            ],
            'distribution' => [
                'excellent' => 0,
                'good' => 0,
                'fair' => 0,
                'poor' => 0
            ],
            'total_pages' => 0
        ];
    }
    
    /**
     * Get default traffic data
     */
    private function get_default_traffic() {
        return [
            'sessions' => 0,
            'sessions_change' => 0,
            'pageviews' => 0,
            'pageviews_change' => 0,
            'avg_duration' => 0,
            'duration_change' => 0,
            'bounce_rate' => 0,
            'bounce_change' => 0,
            'sources' => [
                'organic' => 0,
                'direct' => 0,
                'referral' => 0,
                'social' => 0
            ],
            'geographic' => []
        ];
    }
    
    /**
     * Get default keyword data
     */
    private function get_default_keywords() {
        return [
            'total_keywords' => 0,
            'top_10_count' => 0,
            'top_100_count' => 0,
            'improved_count' => 0,
            'declined_count' => 0,
            'avg_position' => 0,
            'distribution' => [
                'positions_1_10' => 0,
                'positions_11_50' => 0,
                'positions_51_100' => 0,
                'positions_100_plus' => 0
            ],
            'top_keywords' => []
        ];
    }
    
    /**
     * Get default conversion data
     */
    private function get_default_conversions() {
        return [
            'total_conversions' => 0,
            'conversions_change' => 0,
            'conversion_rate' => 0,
            'rate_change' => 0,
            'total_value' => 0,
            'value_change' => 0,
            'assisted_conversions' => 0,
            'goal_breakdown' => []
        ];
    }
    
    /**
     * Get default competitor data
     */
    private function get_default_competitors() {
        return [
            'competitors' => [],
            'market_share' => 0,
            'opportunity_keywords' => []
        ];
    }
}
