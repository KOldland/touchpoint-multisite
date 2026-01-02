<?php
/**
 * KHM Attribution Cohort Analysis
 * 
 * User cohort analysis and retention tracking using Phase 2 OOP architectural patterns.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Cohort_Analysis {
    
    private $performance_manager;
    private $database_manager;
    private $query_builder;
    private $cohort_config = array();
    private $cohort_types = array();
    private $analysis_cache = array();
    
    /**
     * Constructor - Initialize cohort analysis components
     */
    public function __construct() {
        $this->init_cohort_components();
        $this->setup_cohort_config();
        $this->define_cohort_types();
        $this->register_cohort_hooks();
    }
    
    /**
     * Initialize cohort analysis components
     */
    private function init_cohort_components() {
        // Load core components
        if (file_exists(dirname(__FILE__) . '/PerformanceManager.php')) {
            require_once dirname(__FILE__) . '/PerformanceManager.php';
            $this->performance_manager = new KHM_Attribution_Performance_Manager();
        }
        
        if (file_exists(dirname(__FILE__) . '/DatabaseManager.php')) {
            require_once dirname(__FILE__) . '/DatabaseManager.php';
            $this->database_manager = new KHM_Attribution_Database_Manager();
        }
        
        if (file_exists(dirname(__FILE__) . '/QueryBuilder.php')) {
            require_once dirname(__FILE__) . '/QueryBuilder.php';
            $this->query_builder = new KHM_Attribution_Query_Builder();
        }
    }
    
    /**
     * Setup cohort configuration
     */
    private function setup_cohort_config() {
        $this->cohort_config = array(
            'default_cohort_size' => 'monthly',
            'max_cohort_periods' => 12,
            'retention_periods' => array(1, 3, 7, 14, 30, 60, 90, 180, 365),
            'revenue_cohort_tiers' => array(
                'low' => array('min' => 0, 'max' => 50),
                'medium' => array('min' => 50, 'max' => 200),
                'high' => array('min' => 200, 'max' => 9999999)
            ),
            'channel_cohorts' => array('organic', 'paid', 'email', 'social', 'direct'),
            'cache_duration' => 3600, // 1 hour
            'enable_real_time_updates' => false
        );
        
        // Allow configuration overrides
        $this->cohort_config = apply_filters('khm_cohort_config', $this->cohort_config);
    }
    
    /**
     * Define cohort types
     */
    private function define_cohort_types() {
        $this->cohort_types = array(
            'acquisition' => array(
                'name' => 'Acquisition Cohorts',
                'description' => 'Users grouped by first interaction date',
                'grouping_field' => 'first_touch_date',
                'analysis_methods' => array('retention', 'revenue', 'ltv')
            ),
            'conversion' => array(
                'name' => 'Conversion Cohorts',
                'description' => 'Users grouped by first conversion date',
                'grouping_field' => 'first_conversion_date',
                'analysis_methods' => array('repeat_purchase', 'revenue_growth', 'churn')
            ),
            'channel' => array(
                'name' => 'Channel Cohorts',
                'description' => 'Users grouped by acquisition channel',
                'grouping_field' => 'acquisition_channel',
                'analysis_methods' => array('retention', 'revenue', 'cross_channel')
            ),
            'revenue' => array(
                'name' => 'Revenue Cohorts',
                'description' => 'Users grouped by initial purchase value',
                'grouping_field' => 'first_purchase_value',
                'analysis_methods' => array('ltv', 'repeat_rate', 'upsell')
            ),
            'behavior' => array(
                'name' => 'Behavioral Cohorts',
                'description' => 'Users grouped by engagement patterns',
                'grouping_field' => 'engagement_level',
                'analysis_methods' => array('retention', 'activation', 'engagement')
            )
        );
    }
    
    /**
     * Register cohort hooks
     */
    private function register_cohort_hooks() {
        add_action('khm_update_cohort_analysis', array($this, 'update_cohort_analysis'));
        add_action('admin_menu', array($this, 'add_cohort_menu'));
        
        // AJAX hooks
        add_action('wp_ajax_khm_generate_cohort_analysis', array($this, 'ajax_generate_cohort_analysis'));
        add_action('wp_ajax_khm_export_cohort_data', array($this, 'ajax_export_cohort_data'));
        add_action('wp_ajax_khm_get_cohort_insights', array($this, 'ajax_get_cohort_insights'));
        
        // Scheduled updates
        if (!wp_next_scheduled('khm_update_cohort_analysis')) {
            wp_schedule_event(time(), 'daily', 'khm_update_cohort_analysis');
        }
    }
    
    /**
     * Generate comprehensive cohort analysis
     */
    public function generate_cohort_analysis($cohort_type, $options = array()) {
        if (!isset($this->cohort_types[$cohort_type])) {
            return false;
        }
        
        $cohort_definition = $this->cohort_types[$cohort_type];
        
        // Set default options
        $default_options = array(
            'start_date' => date('Y-m-d', strtotime('-12 months')),
            'end_date' => date('Y-m-d'),
            'cohort_size' => $this->cohort_config['default_cohort_size'],
            'analysis_methods' => $cohort_definition['analysis_methods'],
            'include_segments' => true,
            'calculate_ltv' => true
        );
        
        $options = array_merge($default_options, $options);
        
        // Check cache first
        $cache_key = $this->generate_cache_key($cohort_type, $options);
        if (isset($this->analysis_cache[$cache_key])) {
            return $this->analysis_cache[$cache_key];
        }
        
        // Generate cohort groups
        $cohort_groups = $this->generate_cohort_groups($cohort_type, $options);
        
        // Perform analysis for each method
        $analysis_results = array();
        foreach ($options['analysis_methods'] as $method) {
            $analysis_results[$method] = $this->perform_cohort_analysis($cohort_groups, $method, $options);
        }
        
        // Generate insights
        $insights = $this->generate_cohort_insights($cohort_groups, $analysis_results);
        
        $cohort_analysis = array(
            'cohort_type' => $cohort_type,
            'cohort_definition' => $cohort_definition,
            'options' => $options,
            'cohort_groups' => $cohort_groups,
            'analysis_results' => $analysis_results,
            'insights' => $insights,
            'metadata' => array(
                'generated_at' => current_time('mysql'),
                'total_cohorts' => count($cohort_groups),
                'total_users' => array_sum(array_column($cohort_groups, 'user_count')),
                'analysis_period' => $this->calculate_analysis_period($options)
            )
        );
        
        // Cache results
        $this->analysis_cache[$cache_key] = $cohort_analysis;
        
        return $cohort_analysis;
    }
    
    /**
     * Generate cohort groups
     */
    private function generate_cohort_groups($cohort_type, $options) {
        $cohort_definition = $this->cohort_types[$cohort_type];
        $grouping_field = $cohort_definition['grouping_field'];
        
        switch ($cohort_type) {
            case 'acquisition':
                return $this->generate_acquisition_cohorts($options);
                
            case 'conversion':
                return $this->generate_conversion_cohorts($options);
                
            case 'channel':
                return $this->generate_channel_cohorts($options);
                
            case 'revenue':
                return $this->generate_revenue_cohorts($options);
                
            case 'behavior':
                return $this->generate_behavior_cohorts($options);
                
            default:
                return array();
        }
    }
    
    /**
     * Generate acquisition cohorts
     */
    private function generate_acquisition_cohorts($options) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'khm_attribution_events';
        $cohort_size = $options['cohort_size'];
        
        // Determine date grouping based on cohort size
        $date_format = $this->get_date_format($cohort_size);
        
        $sql = "SELECT 
                    DATE_FORMAT(MIN(created_at), %s) as cohort_period,
                    COUNT(DISTINCT user_id) as user_count,
                    MIN(created_at) as cohort_start_date,
                    MAX(created_at) as cohort_end_date
                FROM {$events_table} 
                WHERE created_at BETWEEN %s AND %s
                AND user_id IS NOT NULL
                GROUP BY DATE_FORMAT(MIN(created_at), %s)
                ORDER BY cohort_period";
        
        $results = $wpdb->get_results($wpdb->prepare(
            $sql,
            $date_format,
            $options['start_date'],
            $options['end_date'],
            $date_format
        ), ARRAY_A);
        
        // Enhance with user lists
        foreach ($results as &$cohort) {
            $cohort['users'] = $this->get_cohort_users($cohort['cohort_start_date'], $cohort['cohort_end_date']);
            $cohort['cohort_type'] = 'acquisition';
        }
        
        return $results;
    }
    
    /**
     * Generate conversion cohorts
     */
    private function generate_conversion_cohorts($options) {
        global $wpdb;
        
        $conversions_table = $wpdb->prefix . 'khm_conversion_tracking';
        $cohort_size = $options['cohort_size'];
        $date_format = $this->get_date_format($cohort_size);
        
        $sql = "SELECT 
                    DATE_FORMAT(MIN(created_at), %s) as cohort_period,
                    COUNT(DISTINCT user_id) as user_count,
                    MIN(created_at) as cohort_start_date,
                    MAX(created_at) as cohort_end_date,
                    SUM(value) as total_revenue,
                    AVG(value) as avg_order_value
                FROM {$conversions_table} 
                WHERE created_at BETWEEN %s AND %s
                AND user_id IS NOT NULL
                AND status = 'attributed'
                GROUP BY DATE_FORMAT(MIN(created_at), %s)
                ORDER BY cohort_period";
        
        $results = $wpdb->get_results($wpdb->prepare(
            $sql,
            $date_format,
            $options['start_date'],
            $options['end_date'],
            $date_format
        ), ARRAY_A);
        
        foreach ($results as &$cohort) {
            $cohort['users'] = $this->get_conversion_cohort_users($cohort['cohort_start_date'], $cohort['cohort_end_date']);
            $cohort['cohort_type'] = 'conversion';
        }
        
        return $results;
    }
    
    /**
     * Generate channel cohorts
     */
    private function generate_channel_cohorts($options) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'khm_attribution_events';
        
        $sql = "SELECT 
                    utm_medium as cohort_period,
                    COUNT(DISTINCT user_id) as user_count,
                    MIN(created_at) as cohort_start_date,
                    MAX(created_at) as cohort_end_date,
                    utm_medium as acquisition_channel
                FROM {$events_table} 
                WHERE created_at BETWEEN %s AND %s
                AND user_id IS NOT NULL
                AND utm_medium IS NOT NULL
                GROUP BY utm_medium
                ORDER BY user_count DESC";
        
        $results = $wpdb->get_results($wpdb->prepare(
            $sql,
            $options['start_date'],
            $options['end_date']
        ), ARRAY_A);
        
        foreach ($results as &$cohort) {
            $cohort['users'] = $this->get_channel_cohort_users($cohort['acquisition_channel'], $options);
            $cohort['cohort_type'] = 'channel';
        }
        
        return $results;
    }
    
    /**
     * Generate revenue cohorts
     */
    private function generate_revenue_cohorts($options) {
        global $wpdb;
        
        $conversions_table = $wpdb->prefix . 'khm_conversion_tracking';
        $tiers = $this->cohort_config['revenue_cohort_tiers'];
        
        $cohorts = array();
        foreach ($tiers as $tier_name => $tier_range) {
            $sql = "SELECT 
                        %s as cohort_period,
                        COUNT(DISTINCT user_id) as user_count,
                        MIN(created_at) as cohort_start_date,
                        MAX(created_at) as cohort_end_date,
                        AVG(value) as avg_order_value,
                        SUM(value) as total_revenue
                    FROM {$conversions_table} 
                    WHERE created_at BETWEEN %s AND %s
                    AND value BETWEEN %f AND %f
                    AND user_id IS NOT NULL
                    AND status = 'attributed'";
            
            $result = $wpdb->get_row($wpdb->prepare(
                $sql,
                $tier_name,
                $options['start_date'],
                $options['end_date'],
                $tier_range['min'],
                $tier_range['max']
            ), ARRAY_A);
            
            if ($result && $result['user_count'] > 0) {
                $result['users'] = $this->get_revenue_cohort_users($tier_range, $options);
                $result['cohort_type'] = 'revenue';
                $result['tier_range'] = $tier_range;
                $cohorts[] = $result;
            }
        }
        
        return $cohorts;
    }
    
    /**
     * Generate behavior cohorts
     */
    private function generate_behavior_cohorts($options) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'khm_attribution_events';
        
        // Define engagement levels based on event counts
        $engagement_levels = array(
            'low' => array('min' => 1, 'max' => 3),
            'medium' => array('min' => 4, 'max' => 10),
            'high' => array('min' => 11, 'max' => 9999)
        );
        
        $cohorts = array();
        foreach ($engagement_levels as $level_name => $level_range) {
            $sql = "SELECT 
                        %s as cohort_period,
                        COUNT(DISTINCT user_id) as user_count,
                        MIN(created_at) as cohort_start_date,
                        MAX(created_at) as cohort_end_date,
                        AVG(event_count) as avg_engagement
                    FROM (
                        SELECT 
                            user_id,
                            COUNT(*) as event_count,
                            MIN(created_at) as created_at
                        FROM {$events_table}
                        WHERE created_at BETWEEN %s AND %s
                        AND user_id IS NOT NULL
                        GROUP BY user_id
                        HAVING event_count BETWEEN %d AND %d
                    ) user_engagement";
            
            $result = $wpdb->get_row($wpdb->prepare(
                $sql,
                $level_name,
                $options['start_date'],
                $options['end_date'],
                $level_range['min'],
                $level_range['max']
            ), ARRAY_A);
            
            if ($result && $result['user_count'] > 0) {
                $result['users'] = $this->get_behavior_cohort_users($level_range, $options);
                $result['cohort_type'] = 'behavior';
                $result['engagement_range'] = $level_range;
                $cohorts[] = $result;
            }
        }
        
        return $cohorts;
    }
    
    /**
     * Perform cohort analysis
     */
    private function perform_cohort_analysis($cohort_groups, $method, $options) {
        switch ($method) {
            case 'retention':
                return $this->analyze_retention($cohort_groups, $options);
                
            case 'revenue':
                return $this->analyze_revenue($cohort_groups, $options);
                
            case 'ltv':
                return $this->analyze_lifetime_value($cohort_groups, $options);
                
            case 'repeat_purchase':
                return $this->analyze_repeat_purchase($cohort_groups, $options);
                
            case 'revenue_growth':
                return $this->analyze_revenue_growth($cohort_groups, $options);
                
            case 'churn':
                return $this->analyze_churn($cohort_groups, $options);
                
            case 'cross_channel':
                return $this->analyze_cross_channel($cohort_groups, $options);
                
            case 'upsell':
                return $this->analyze_upsell($cohort_groups, $options);
                
            case 'activation':
                return $this->analyze_activation($cohort_groups, $options);
                
            case 'engagement':
                return $this->analyze_engagement($cohort_groups, $options);
                
            default:
                return array('error' => 'Unknown analysis method: ' . $method);
        }
    }
    
    /**
     * Analyze retention
     */
    private function analyze_retention($cohort_groups, $options) {
        $retention_data = array();
        $retention_periods = $this->cohort_config['retention_periods'];
        
        foreach ($cohort_groups as $cohort) {
            $cohort_retention = array(
                'cohort_period' => $cohort['cohort_period'],
                'initial_users' => $cohort['user_count'],
                'retention_rates' => array()
            );
            
            foreach ($retention_periods as $period_days) {
                $retained_users = $this->calculate_retained_users($cohort, $period_days);
                $retention_rate = $cohort['user_count'] > 0 ? ($retained_users / $cohort['user_count']) * 100 : 0;
                
                $cohort_retention['retention_rates'][$period_days] = array(
                    'period_days' => $period_days,
                    'retained_users' => $retained_users,
                    'retention_rate' => $retention_rate
                );
            }
            
            $retention_data[] = $cohort_retention;
        }
        
        return array(
            'method' => 'retention',
            'data' => $retention_data,
            'summary' => $this->calculate_retention_summary($retention_data)
        );
    }
    
    /**
     * Analyze lifetime value
     */
    private function analyze_lifetime_value($cohort_groups, $options) {
        $ltv_data = array();
        
        foreach ($cohort_groups as $cohort) {
            $ltv_analysis = array(
                'cohort_period' => $cohort['cohort_period'],
                'user_count' => $cohort['user_count'],
                'ltv_metrics' => array()
            );
            
            // Calculate LTV for different time periods
            $ltv_periods = array(30, 60, 90, 180, 365);
            foreach ($ltv_periods as $period_days) {
                $ltv_metrics = $this->calculate_cohort_ltv($cohort, $period_days);
                $ltv_analysis['ltv_metrics'][$period_days] = $ltv_metrics;
            }
            
            $ltv_data[] = $ltv_analysis;
        }
        
        return array(
            'method' => 'ltv',
            'data' => $ltv_data,
            'summary' => $this->calculate_ltv_summary($ltv_data)
        );
    }
    
    /**
     * Analyze revenue
     */
    private function analyze_revenue($cohort_groups, $options) {
        $revenue_data = array();
        
        foreach ($cohort_groups as $cohort) {
            $revenue_metrics = $this->calculate_cohort_revenue_metrics($cohort);
            $revenue_data[] = array_merge($cohort, $revenue_metrics);
        }
        
        return array(
            'method' => 'revenue',
            'data' => $revenue_data,
            'summary' => $this->calculate_revenue_summary($revenue_data)
        );
    }
    
    /**
     * Generate cohort insights
     */
    private function generate_cohort_insights($cohort_groups, $analysis_results) {
        $insights = array(
            'key_findings' => array(),
            'trends' => array(),
            'recommendations' => array(),
            'alerts' => array()
        );
        
        // Analyze retention insights
        if (isset($analysis_results['retention'])) {
            $retention_insights = $this->analyze_retention_insights($analysis_results['retention']);
            $insights['key_findings'] = array_merge($insights['key_findings'], $retention_insights['findings']);
            $insights['trends'] = array_merge($insights['trends'], $retention_insights['trends']);
        }
        
        // Analyze LTV insights
        if (isset($analysis_results['ltv'])) {
            $ltv_insights = $this->analyze_ltv_insights($analysis_results['ltv']);
            $insights['key_findings'] = array_merge($insights['key_findings'], $ltv_insights['findings']);
            $insights['recommendations'] = array_merge($insights['recommendations'], $ltv_insights['recommendations']);
        }
        
        // Analyze revenue insights
        if (isset($analysis_results['revenue'])) {
            $revenue_insights = $this->analyze_revenue_insights($analysis_results['revenue']);
            $insights['key_findings'] = array_merge($insights['key_findings'], $revenue_insights['findings']);
            $insights['alerts'] = array_merge($insights['alerts'], $revenue_insights['alerts']);
        }
        
        return $insights;
    }
    
    /**
     * Utility methods
     */
    private function get_date_format($cohort_size) {
        switch ($cohort_size) {
            case 'daily':
                return '%Y-%m-%d';
            case 'weekly':
                return '%Y-%u';
            case 'monthly':
                return '%Y-%m';
            case 'quarterly':
                return '%Y-Q%q';
            case 'yearly':
                return '%Y';
            default:
                return '%Y-%m';
        }
    }
    
    private function generate_cache_key($cohort_type, $options) {
        return 'cohort_' . $cohort_type . '_' . md5(serialize($options));
    }
    
    private function calculate_analysis_period($options) {
        $start = strtotime($options['start_date']);
        $end = strtotime($options['end_date']);
        $days = ($end - $start) / (24 * 60 * 60);
        
        return array(
            'start_date' => $options['start_date'],
            'end_date' => $options['end_date'],
            'total_days' => $days,
            'total_weeks' => ceil($days / 7),
            'total_months' => ceil($days / 30)
        );
    }
    
    /**
     * User retrieval methods
     */
    private function get_cohort_users($start_date, $end_date) {
        global $wpdb;
        $events_table = $wpdb->prefix . 'khm_attribution_events';
        
        $sql = "SELECT DISTINCT user_id 
                FROM {$events_table} 
                WHERE created_at BETWEEN %s AND %s 
                AND user_id IS NOT NULL";
        
        return $wpdb->get_col($wpdb->prepare($sql, $start_date, $end_date));
    }
    
    private function get_conversion_cohort_users($start_date, $end_date) {
        global $wpdb;
        $conversions_table = $wpdb->prefix . 'khm_conversion_tracking';
        
        $sql = "SELECT DISTINCT user_id 
                FROM {$conversions_table} 
                WHERE created_at BETWEEN %s AND %s 
                AND user_id IS NOT NULL 
                AND status = 'attributed'";
        
        return $wpdb->get_col($wpdb->prepare($sql, $start_date, $end_date));
    }
    
    private function get_channel_cohort_users($channel, $options) {
        global $wpdb;
        $events_table = $wpdb->prefix . 'khm_attribution_events';
        
        $sql = "SELECT DISTINCT user_id 
                FROM {$events_table} 
                WHERE created_at BETWEEN %s AND %s 
                AND utm_medium = %s 
                AND user_id IS NOT NULL";
        
        return $wpdb->get_col($wpdb->prepare($sql, $options['start_date'], $options['end_date'], $channel));
    }
    
    private function get_revenue_cohort_users($tier_range, $options) {
        global $wpdb;
        $conversions_table = $wpdb->prefix . 'khm_conversion_tracking';
        
        $sql = "SELECT DISTINCT user_id 
                FROM {$conversions_table} 
                WHERE created_at BETWEEN %s AND %s 
                AND value BETWEEN %f AND %f 
                AND user_id IS NOT NULL 
                AND status = 'attributed'";
        
        return $wpdb->get_col($wpdb->prepare(
            $sql, 
            $options['start_date'], 
            $options['end_date'], 
            $tier_range['min'], 
            $tier_range['max']
        ));
    }
    
    private function get_behavior_cohort_users($engagement_range, $options) {
        global $wpdb;
        $events_table = $wpdb->prefix . 'khm_attribution_events';
        
        $sql = "SELECT user_id 
                FROM (
                    SELECT 
                        user_id,
                        COUNT(*) as event_count
                    FROM {$events_table}
                    WHERE created_at BETWEEN %s AND %s
                    AND user_id IS NOT NULL
                    GROUP BY user_id
                    HAVING event_count BETWEEN %d AND %d
                ) user_engagement";
        
        return $wpdb->get_col($wpdb->prepare(
            $sql,
            $options['start_date'],
            $options['end_date'],
            $engagement_range['min'],
            $engagement_range['max']
        ));
    }
    
    /**
     * Calculation methods
     */
    private function calculate_retained_users($cohort, $period_days) {
        global $wpdb;
        $events_table = $wpdb->prefix . 'khm_attribution_events';
        
        $cohort_start = strtotime($cohort['cohort_start_date']);
        $retention_date = date('Y-m-d', $cohort_start + ($period_days * 24 * 60 * 60));
        
        $user_list = "'" . implode("','", $cohort['users']) . "'";
        
        $sql = "SELECT COUNT(DISTINCT user_id) 
                FROM {$events_table} 
                WHERE user_id IN ({$user_list}) 
                AND DATE(created_at) = %s";
        
        return intval($wpdb->get_var($wpdb->prepare($sql, $retention_date)));
    }
    
    private function calculate_cohort_ltv($cohort, $period_days) {
        global $wpdb;
        $conversions_table = $wpdb->prefix . 'khm_conversion_tracking';
        
        $cohort_start = strtotime($cohort['cohort_start_date']);
        $ltv_end_date = date('Y-m-d', $cohort_start + ($period_days * 24 * 60 * 60));
        
        $user_list = "'" . implode("','", $cohort['users']) . "'";
        
        $sql = "SELECT 
                    COUNT(DISTINCT user_id) as active_users,
                    SUM(value) as total_revenue,
                    AVG(value) as avg_order_value,
                    COUNT(*) as total_orders
                FROM {$conversions_table} 
                WHERE user_id IN ({$user_list}) 
                AND created_at BETWEEN %s AND %s 
                AND status = 'attributed'";
        
        $ltv_data = $wpdb->get_row($wpdb->prepare(
            $sql, 
            $cohort['cohort_start_date'], 
            $ltv_end_date
        ), ARRAY_A);
        
        $ltv_data['ltv_per_user'] = $ltv_data['active_users'] > 0 ? $ltv_data['total_revenue'] / $ltv_data['active_users'] : 0;
        $ltv_data['orders_per_user'] = $ltv_data['active_users'] > 0 ? $ltv_data['total_orders'] / $ltv_data['active_users'] : 0;
        
        return $ltv_data;
    }
    
    private function calculate_cohort_revenue_metrics($cohort) {
        global $wpdb;
        $conversions_table = $wpdb->prefix . 'khm_conversion_tracking';
        
        $user_list = "'" . implode("','", $cohort['users']) . "'";
        
        $sql = "SELECT 
                    SUM(value) as total_revenue,
                    AVG(value) as avg_order_value,
                    COUNT(*) as total_orders,
                    COUNT(DISTINCT user_id) as purchasing_users
                FROM {$conversions_table} 
                WHERE user_id IN ({$user_list}) 
                AND status = 'attributed'";
        
        $revenue_data = $wpdb->get_row($sql, ARRAY_A);
        
        $revenue_data['revenue_per_user'] = $cohort['user_count'] > 0 ? $revenue_data['total_revenue'] / $cohort['user_count'] : 0;
        $revenue_data['conversion_rate'] = $cohort['user_count'] > 0 ? ($revenue_data['purchasing_users'] / $cohort['user_count']) * 100 : 0;
        
        return $revenue_data;
    }
    
    /**
     * Summary calculation methods
     */
    private function calculate_retention_summary($retention_data) {
        $summary = array(
            'avg_30_day_retention' => 0,
            'avg_90_day_retention' => 0,
            'best_performing_cohort' => null,
            'retention_trend' => 'stable'
        );
        
        $retention_30_values = array();
        $retention_90_values = array();
        
        foreach ($retention_data as $cohort) {
            if (isset($cohort['retention_rates'][30])) {
                $retention_30_values[] = $cohort['retention_rates'][30]['retention_rate'];
            }
            if (isset($cohort['retention_rates'][90])) {
                $retention_90_values[] = $cohort['retention_rates'][90]['retention_rate'];
            }
        }
        
        $summary['avg_30_day_retention'] = !empty($retention_30_values) ? array_sum($retention_30_values) / count($retention_30_values) : 0;
        $summary['avg_90_day_retention'] = !empty($retention_90_values) ? array_sum($retention_90_values) / count($retention_90_values) : 0;
        
        return $summary;
    }
    
    private function calculate_ltv_summary($ltv_data) {
        $summary = array(
            'avg_90_day_ltv' => 0,
            'avg_365_day_ltv' => 0,
            'highest_ltv_cohort' => null,
            'ltv_growth_rate' => 0
        );
        
        $ltv_90_values = array();
        $ltv_365_values = array();
        
        foreach ($ltv_data as $cohort) {
            if (isset($cohort['ltv_metrics'][90])) {
                $ltv_90_values[] = $cohort['ltv_metrics'][90]['ltv_per_user'];
            }
            if (isset($cohort['ltv_metrics'][365])) {
                $ltv_365_values[] = $cohort['ltv_metrics'][365]['ltv_per_user'];
            }
        }
        
        $summary['avg_90_day_ltv'] = !empty($ltv_90_values) ? array_sum($ltv_90_values) / count($ltv_90_values) : 0;
        $summary['avg_365_day_ltv'] = !empty($ltv_365_values) ? array_sum($ltv_365_values) / count($ltv_365_values) : 0;
        
        return $summary;
    }
    
    private function calculate_revenue_summary($revenue_data) {
        $total_revenue = array_sum(array_column($revenue_data, 'total_revenue'));
        $total_users = array_sum(array_column($revenue_data, 'user_count'));
        
        return array(
            'total_revenue' => $total_revenue,
            'total_users' => $total_users,
            'revenue_per_user' => $total_users > 0 ? $total_revenue / $total_users : 0,
            'avg_conversion_rate' => array_sum(array_column($revenue_data, 'conversion_rate')) / count($revenue_data)
        );
    }
    
    /**
     * Insight analysis methods - placeholders for complex analysis
     */
    private function analyze_retention_insights($retention_results) {
        return array(
            'findings' => array('Retention analysis completed'),
            'trends' => array('Stable retention patterns observed')
        );
    }
    
    private function analyze_ltv_insights($ltv_results) {
        return array(
            'findings' => array('LTV analysis completed'),
            'recommendations' => array('Focus on high-value customer segments')
        );
    }
    
    private function analyze_revenue_insights($revenue_results) {
        return array(
            'findings' => array('Revenue analysis completed'),
            'alerts' => array('Monitor conversion rate trends')
        );
    }
    
    /**
     * Placeholder methods for additional analysis types
     */
    private function analyze_repeat_purchase($cohort_groups, $options) { return array('method' => 'repeat_purchase', 'data' => array()); }
    private function analyze_revenue_growth($cohort_groups, $options) { return array('method' => 'revenue_growth', 'data' => array()); }
    private function analyze_churn($cohort_groups, $options) { return array('method' => 'churn', 'data' => array()); }
    private function analyze_cross_channel($cohort_groups, $options) { return array('method' => 'cross_channel', 'data' => array()); }
    private function analyze_upsell($cohort_groups, $options) { return array('method' => 'upsell', 'data' => array()); }
    private function analyze_activation($cohort_groups, $options) { return array('method' => 'activation', 'data' => array()); }
    private function analyze_engagement($cohort_groups, $options) { return array('method' => 'engagement', 'data' => array()); }
    
    /**
     * AJAX handlers
     */
    public function ajax_generate_cohort_analysis() {
        check_ajax_referer('khm_cohort_nonce', 'nonce');
        
        $cohort_type = sanitize_text_field($_POST['cohort_type'] ?? 'acquisition');
        $options = array(
            'start_date' => sanitize_text_field($_POST['start_date'] ?? date('Y-m-d', strtotime('-12 months'))),
            'end_date' => sanitize_text_field($_POST['end_date'] ?? date('Y-m-d')),
            'cohort_size' => sanitize_text_field($_POST['cohort_size'] ?? 'monthly')
        );
        
        $analysis = $this->generate_cohort_analysis($cohort_type, $options);
        
        if ($analysis) {
            wp_send_json_success($analysis);
        } else {
            wp_send_json_error('Failed to generate cohort analysis');
        }
    }
    
    /**
     * Update cohort analysis (scheduled)
     */
    public function update_cohort_analysis() {
        // Run daily cohort updates for all types
        foreach (array_keys($this->cohort_types) as $cohort_type) {
            $this->generate_cohort_analysis($cohort_type);
        }
    }
    
    /**
     * Add cohort menu
     */
    public function add_cohort_menu() {
        add_submenu_page(
            'khm-attribution',
            'Cohort Analysis',
            'Cohorts',
            'manage_options',
            'khm-attribution-cohorts',
            array($this, 'render_cohort_page')
        );
    }
    
    /**
     * Render cohort page
     */
    public function render_cohort_page() {
        echo '<div class="wrap">';
        echo '<h1>Cohort Analysis</h1>';
        echo '<p>Analyze user behavior and retention patterns across different cohorts.</p>';
        echo '</div>';
    }
    
    // Additional placeholder AJAX methods
    public function ajax_export_cohort_data() { wp_send_json_success(array('message' => 'Export functionality placeholder')); }
    public function ajax_get_cohort_insights() { wp_send_json_success(array('message' => 'Insights functionality placeholder')); }
}
?>