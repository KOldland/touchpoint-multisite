<?php
/**
 * KHM Attribution Business Intelligence Engine
 * 
 * Advanced business intelligence, reporting, and insights generation
 * using Phase 2 OOP architectural patterns.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Business_Intelligence_Engine {
    
    private $performance_manager;
    private $database_manager;
    private $query_builder;
    private $roi_engine;
    private $journey_analytics;
    private $intelligence_config = array();
    private $kpi_definitions = array();
    private $dashboard_widgets = array();
    
    /**
     * Constructor - Initialize business intelligence components
     */
    public function __construct() {
        $this->init_intelligence_components();
        $this->setup_intelligence_config();
        $this->load_kpi_definitions();
        $this->register_intelligence_hooks();
    }
    
    /**
     * Initialize business intelligence components
     */
    private function init_intelligence_components() {
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
        
        // Load related analytics components
        if (file_exists(dirname(__FILE__) . '/ROIOptimizationEngine.php')) {
            require_once dirname(__FILE__) . '/ROIOptimizationEngine.php';
            $this->roi_engine = new KHM_Attribution_ROI_Optimization_Engine();
        }
        
        if (file_exists(dirname(__FILE__) . '/CustomerJourneyAnalytics.php')) {
            require_once dirname(__FILE__) . '/CustomerJourneyAnalytics.php';
            $this->journey_analytics = new KHM_Attribution_Customer_Journey_Analytics();
        }
    }
    
    /**
     * Setup business intelligence configuration
     */
    private function setup_intelligence_config() {
        $this->intelligence_config = array(
            'reporting_timezone' => 'UTC',
            'data_retention_days' => 730, // 2 years
            'real_time_updates' => true,
            'cache_duration' => 3600, // 1 hour
            'enable_predictive_analytics' => true,
            'enable_automated_insights' => true,
            'dashboard_refresh_interval' => 300, // 5 minutes
            'alert_thresholds' => array(
                'conversion_rate_drop' => 20, // percentage
                'revenue_decline' => 15, // percentage
                'traffic_spike' => 200 // percentage increase
            )
        );
        
        // Allow configuration overrides
        $this->intelligence_config = apply_filters('khm_business_intelligence_config', $this->intelligence_config);
    }
    
    /**
     * Load KPI definitions
     */
    private function load_kpi_definitions() {
        $this->kpi_definitions = array(
            'revenue_metrics' => array(
                'total_revenue' => array(
                    'name' => 'Total Revenue',
                    'calculation' => 'sum_conversions_value',
                    'format' => 'currency',
                    'target_increase' => 10 // percentage per month
                ),
                'revenue_per_visitor' => array(
                    'name' => 'Revenue per Visitor',
                    'calculation' => 'revenue_divided_by_visitors',
                    'format' => 'currency',
                    'target_increase' => 5
                ),
                'average_order_value' => array(
                    'name' => 'Average Order Value',
                    'calculation' => 'avg_conversion_value',
                    'format' => 'currency',
                    'target_increase' => 8
                )
            ),
            'conversion_metrics' => array(
                'conversion_rate' => array(
                    'name' => 'Conversion Rate',
                    'calculation' => 'conversions_divided_by_visitors',
                    'format' => 'percentage',
                    'target_increase' => 15
                ),
                'click_to_conversion_rate' => array(
                    'name' => 'Click-to-Conversion Rate',
                    'calculation' => 'conversions_divided_by_clicks',
                    'format' => 'percentage',
                    'target_increase' => 12
                ),
                'time_to_conversion' => array(
                    'name' => 'Average Time to Conversion',
                    'calculation' => 'avg_conversion_time',
                    'format' => 'duration',
                    'target_decrease' => 10
                )
            ),
            'attribution_metrics' => array(
                'attribution_accuracy' => array(
                    'name' => 'Attribution Accuracy',
                    'calculation' => 'attributed_vs_total_conversions',
                    'format' => 'percentage',
                    'target_increase' => 5
                ),
                'multi_touch_percentage' => array(
                    'name' => 'Multi-Touch Attribution %',
                    'calculation' => 'multi_touch_conversions_percentage',
                    'format' => 'percentage',
                    'target_increase' => 20
                ),
                'touchpoint_efficiency' => array(
                    'name' => 'Touchpoint Efficiency',
                    'calculation' => 'conversion_per_touchpoint',
                    'format' => 'ratio',
                    'target_increase' => 15
                )
            ),
            'channel_metrics' => array(
                'channel_roi' => array(
                    'name' => 'Channel ROI',
                    'calculation' => 'channel_revenue_vs_cost',
                    'format' => 'percentage',
                    'target_increase' => 25
                ),
                'channel_attribution_share' => array(
                    'name' => 'Channel Attribution Share',
                    'calculation' => 'channel_attribution_percentage',
                    'format' => 'percentage',
                    'target_tracking' => true
                ),
                'cross_channel_synergy' => array(
                    'name' => 'Cross-Channel Synergy Score',
                    'calculation' => 'cross_channel_lift_calculation',
                    'format' => 'score',
                    'target_increase' => 30
                )
            )
        );
    }
    
    /**
     * Register business intelligence hooks
     */
    private function register_intelligence_hooks() {
        add_action('khm_generate_daily_insights', array($this, 'generate_daily_insights'));
        add_action('khm_update_kpi_dashboard', array($this, 'update_kpi_dashboard'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        
        // AJAX hooks for real-time dashboard updates
        add_action('wp_ajax_khm_get_kpi_data', array($this, 'ajax_get_kpi_data'));
        add_action('wp_ajax_khm_generate_report', array($this, 'ajax_generate_report'));
        add_action('wp_ajax_khm_get_insights', array($this, 'ajax_get_insights'));
        
        // Scheduled hooks
        if (!wp_next_scheduled('khm_generate_daily_insights')) {
            wp_schedule_event(time(), 'daily', 'khm_generate_daily_insights');
        }
    }
    
    /**
     * Generate comprehensive business insights
     */
    public function generate_business_insights($date_range = array(), $filters = array()) {
        // Set default date range (last 30 days)
        if (empty($date_range)) {
            $date_range = array(
                'start' => date('Y-m-d', strtotime('-30 days')),
                'end' => date('Y-m-d')
            );
        }
        
        // Calculate all KPIs
        $kpi_data = $this->calculate_all_kpis($date_range, $filters);
        
        // Generate automated insights
        $automated_insights = $this->generate_automated_insights($kpi_data, $date_range);
        
        // Perform trend analysis
        $trend_analysis = $this->analyze_trends($kpi_data, $date_range);
        
        // Generate recommendations
        $recommendations = $this->generate_recommendations($kpi_data, $trend_analysis);
        
        // Compile comprehensive insights
        $insights = array(
            'summary' => $this->generate_executive_summary($kpi_data, $trend_analysis),
            'kpi_performance' => $kpi_data,
            'trends' => $trend_analysis,
            'automated_insights' => $automated_insights,
            'recommendations' => $recommendations,
            'alerts' => $this->check_performance_alerts($kpi_data),
            'generated_at' => current_time('mysql'),
            'date_range' => $date_range
        );
        
        // Store insights for caching
        $this->store_insights($insights);
        
        return $insights;
    }
    
    /**
     * Calculate all KPIs
     */
    private function calculate_all_kpis($date_range, $filters = array()) {
        $kpi_data = array();
        
        foreach ($this->kpi_definitions as $category => $kpis) {
            $kpi_data[$category] = array();
            
            foreach ($kpis as $kpi_key => $kpi_config) {
                $calculation_method = $kpi_config['calculation'];
                $value = $this->calculate_kpi_value($calculation_method, $date_range, $filters);
                
                $kpi_data[$category][$kpi_key] = array(
                    'name' => $kpi_config['name'],
                    'value' => $value,
                    'formatted_value' => $this->format_kpi_value($value, $kpi_config['format']),
                    'target' => $kpi_config['target_increase'] ?? null,
                    'performance_vs_target' => $this->calculate_performance_vs_target($value, $kpi_config, $date_range),
                    'trend' => $this->calculate_kpi_trend($calculation_method, $date_range, $filters)
                );
            }
        }
        
        return $kpi_data;
    }
    
    /**
     * Calculate individual KPI value
     */
    private function calculate_kpi_value($calculation_method, $date_range, $filters) {
        global $wpdb;
        
        $start_date = $date_range['start'];
        $end_date = $date_range['end'];
        
        switch ($calculation_method) {
            case 'sum_conversions_value':
                return $this->calculate_total_revenue($start_date, $end_date, $filters);
                
            case 'revenue_divided_by_visitors':
                $revenue = $this->calculate_total_revenue($start_date, $end_date, $filters);
                $visitors = $this->calculate_unique_visitors($start_date, $end_date, $filters);
                return $visitors > 0 ? $revenue / $visitors : 0;
                
            case 'avg_conversion_value':
                return $this->calculate_average_order_value($start_date, $end_date, $filters);
                
            case 'conversions_divided_by_visitors':
                $conversions = $this->calculate_total_conversions($start_date, $end_date, $filters);
                $visitors = $this->calculate_unique_visitors($start_date, $end_date, $filters);
                return $visitors > 0 ? ($conversions / $visitors) * 100 : 0;
                
            case 'conversions_divided_by_clicks':
                $conversions = $this->calculate_total_conversions($start_date, $end_date, $filters);
                $clicks = $this->calculate_total_clicks($start_date, $end_date, $filters);
                return $clicks > 0 ? ($conversions / $clicks) * 100 : 0;
                
            case 'avg_conversion_time':
                return $this->calculate_average_conversion_time($start_date, $end_date, $filters);
                
            case 'attributed_vs_total_conversions':
                $attributed = $this->calculate_attributed_conversions($start_date, $end_date, $filters);
                $total = $this->calculate_total_conversions($start_date, $end_date, $filters);
                return $total > 0 ? ($attributed / $total) * 100 : 0;
                
            case 'multi_touch_conversions_percentage':
                return $this->calculate_multi_touch_percentage($start_date, $end_date, $filters);
                
            case 'conversion_per_touchpoint':
                $conversions = $this->calculate_total_conversions($start_date, $end_date, $filters);
                $touchpoints = $this->calculate_total_touchpoints($start_date, $end_date, $filters);
                return $touchpoints > 0 ? $conversions / $touchpoints : 0;
                
            case 'channel_revenue_vs_cost':
                return $this->calculate_channel_roi($start_date, $end_date, $filters);
                
            case 'channel_attribution_percentage':
                return $this->calculate_channel_attribution_share($start_date, $end_date, $filters);
                
            case 'cross_channel_lift_calculation':
                return $this->calculate_cross_channel_synergy($start_date, $end_date, $filters);
                
            default:
                return 0;
        }
    }
    
    /**
     * KPI calculation helper methods
     */
    private function calculate_total_revenue($start_date, $end_date, $filters) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_conversion_tracking';
        
        $sql = "SELECT SUM(value) FROM {$table_name} 
                WHERE created_at BETWEEN %s AND %s 
                AND status = 'attributed'";
        
        return floatval($wpdb->get_var($wpdb->prepare($sql, $start_date, $end_date)));
    }
    
    private function calculate_unique_visitors($start_date, $end_date, $filters) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_session_tracking';
        
        $sql = "SELECT COUNT(DISTINCT COALESCE(user_id, ip_address)) FROM {$table_name}
                WHERE created_at BETWEEN %s AND %s";
        
        return intval($wpdb->get_var($wpdb->prepare($sql, $start_date, $end_date)));
    }
    
    private function calculate_average_order_value($start_date, $end_date, $filters) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_conversion_tracking';
        
        $sql = "SELECT AVG(value) FROM {$table_name} 
                WHERE created_at BETWEEN %s AND %s 
                AND status = 'attributed'";
        
        return floatval($wpdb->get_var($wpdb->prepare($sql, $start_date, $end_date)));
    }
    
    private function calculate_total_conversions($start_date, $end_date, $filters) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_conversion_tracking';
        
        $sql = "SELECT COUNT(*) FROM {$table_name} 
                WHERE created_at BETWEEN %s AND %s 
                AND status = 'attributed'";
        
        return intval($wpdb->get_var($wpdb->prepare($sql, $start_date, $end_date)));
    }
    
    private function calculate_total_clicks($start_date, $end_date, $filters) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_attribution_events';
        
        $sql = "SELECT COUNT(*) FROM {$table_name} 
                WHERE created_at BETWEEN %s AND %s";
        
        return intval($wpdb->get_var($wpdb->prepare($sql, $start_date, $end_date)));
    }
    
    private function calculate_average_conversion_time($start_date, $end_date, $filters) {
        global $wpdb;
        
        $conversions_table = $wpdb->prefix . 'khm_conversion_tracking';
        $events_table = $wpdb->prefix . 'khm_attribution_events';
        
        $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, e.created_at, c.created_at)) 
                FROM {$conversions_table} c
                JOIN {$events_table} e ON c.click_id = e.click_id
                WHERE c.created_at BETWEEN %s AND %s 
                AND c.status = 'attributed'";
        
        $avg_seconds = floatval($wpdb->get_var($wpdb->prepare($sql, $start_date, $end_date)));
        return $avg_seconds / 3600; // Convert to hours
    }
    
    private function calculate_attributed_conversions($start_date, $end_date, $filters) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_conversion_tracking';
        
        $sql = "SELECT COUNT(*) FROM {$table_name} 
                WHERE created_at BETWEEN %s AND %s 
                AND status = 'attributed' 
                AND attribution_method IS NOT NULL";
        
        return intval($wpdb->get_var($wpdb->prepare($sql, $start_date, $end_date)));
    }
    
    private function calculate_multi_touch_percentage($start_date, $end_date, $filters) {
        // This would require customer journey data
        if (!isset($this->journey_analytics)) {
            return 0;
        }
        
        global $wpdb;
        
        $journeys_table = $wpdb->prefix . 'khm_customer_journeys';
        
        $multi_touch_sql = "SELECT COUNT(*) FROM {$journeys_table} 
                           WHERE updated_at BETWEEN %s AND %s 
                           AND touchpoint_count > 1 
                           AND conversion_count > 0";
        
        $total_conversions_sql = "SELECT COUNT(*) FROM {$journeys_table} 
                                 WHERE updated_at BETWEEN %s AND %s 
                                 AND conversion_count > 0";
        
        $multi_touch = intval($wpdb->get_var($wpdb->prepare($multi_touch_sql, $start_date, $end_date)));
        $total = intval($wpdb->get_var($wpdb->prepare($total_conversions_sql, $start_date, $end_date)));
        
        return $total > 0 ? ($multi_touch / $total) * 100 : 0;
    }
    
    private function calculate_total_touchpoints($start_date, $end_date, $filters) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_customer_touchpoints';
        
        $sql = "SELECT COUNT(*) FROM {$table_name} 
                WHERE created_at BETWEEN %s AND %s";
        
        return intval($wpdb->get_var($wpdb->prepare($sql, $start_date, $end_date)));
    }
    
    private function calculate_channel_roi($start_date, $end_date, $filters) {
        // Simplified ROI calculation - would need cost data integration
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'khm_attribution_analytics';
        
        $sql = "SELECT 
                    utm_medium as channel,
                    SUM(commission_total) as revenue,
                    COUNT(*) as volume
                FROM {$analytics_table} 
                WHERE date BETWEEN %s AND %s 
                GROUP BY utm_medium";
        
        $channel_data = $wpdb->get_results($wpdb->prepare($sql, $start_date, $end_date), ARRAY_A);
        
        // Calculate weighted average ROI (assuming cost = 20% of revenue for demo)
        $total_revenue = 0;
        $total_cost = 0;
        
        foreach ($channel_data as $channel) {
            $revenue = floatval($channel['revenue']);
            $cost = $revenue * 0.2; // Assumption
            
            $total_revenue += $revenue;
            $total_cost += $cost;
        }
        
        return $total_cost > 0 ? (($total_revenue - $total_cost) / $total_cost) * 100 : 0;
    }
    
    private function calculate_channel_attribution_share($start_date, $end_date, $filters) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'khm_attribution_analytics';
        
        $sql = "SELECT utm_medium, SUM(commission_total) as revenue
                FROM {$analytics_table} 
                WHERE date BETWEEN %s AND %s 
                GROUP BY utm_medium";
        
        $channel_data = $wpdb->get_results($wpdb->prepare($sql, $start_date, $end_date), ARRAY_A);
        
        $total_revenue = array_sum(array_column($channel_data, 'revenue'));
        
        // Return distribution as array
        $distribution = array();
        foreach ($channel_data as $channel) {
            $share = $total_revenue > 0 ? (floatval($channel['revenue']) / $total_revenue) * 100 : 0;
            $distribution[$channel['utm_medium']] = $share;
        }
        
        return $distribution;
    }
    
    private function calculate_cross_channel_synergy($start_date, $end_date, $filters) {
        // Simplified cross-channel synergy calculation
        if (!isset($this->journey_analytics)) {
            return 0;
        }
        
        global $wpdb;
        
        $touchpoints_table = $wpdb->prefix . 'khm_customer_touchpoints';
        
        // Get multi-channel journeys
        $sql = "SELECT customer_id, COUNT(DISTINCT channel) as channel_count
                FROM {$touchpoints_table} 
                WHERE created_at BETWEEN %s AND %s 
                GROUP BY customer_id
                HAVING channel_count > 1";
        
        $multi_channel_customers = $wpdb->get_results($wpdb->prepare($sql, $start_date, $end_date), ARRAY_A);
        
        // Calculate synergy score based on channel diversity
        $total_customers = count($multi_channel_customers);
        if ($total_customers === 0) {
            return 0;
        }
        
        $avg_channels = array_sum(array_column($multi_channel_customers, 'channel_count')) / $total_customers;
        
        // Score: higher channel diversity = higher synergy
        return min(100, $avg_channels * 25); // Max score of 100
    }
    
    /**
     * Format KPI value based on type
     */
    private function format_kpi_value($value, $format) {
        switch ($format) {
            case 'currency':
                return '$' . number_format($value, 2);
                
            case 'percentage':
                return number_format($value, 2) . '%';
                
            case 'duration':
                if ($value < 1) {
                    return number_format($value * 60, 0) . ' minutes';
                } elseif ($value < 24) {
                    return number_format($value, 1) . ' hours';
                } else {
                    return number_format($value / 24, 1) . ' days';
                }
                
            case 'ratio':
                return number_format($value, 3) . ':1';
                
            case 'score':
                return number_format($value, 0) . '/100';
                
            default:
                return number_format($value, 2);
        }
    }
    
    /**
     * Calculate performance vs target
     */
    private function calculate_performance_vs_target($current_value, $kpi_config, $date_range) {
        if (!isset($kpi_config['target_increase'])) {
            return null;
        }
        
        // Get previous period for comparison
        $days_diff = (strtotime($date_range['end']) - strtotime($date_range['start'])) / (24 * 3600);
        $previous_start = date('Y-m-d', strtotime($date_range['start'] . ' -' . $days_diff . ' days'));
        $previous_end = date('Y-m-d', strtotime($date_range['end'] . ' -' . $days_diff . ' days'));
        
        $previous_value = $this->calculate_kpi_value(
            $kpi_config['calculation'], 
            array('start' => $previous_start, 'end' => $previous_end),
            array()
        );
        
        if ($previous_value == 0) {
            return null;
        }
        
        $actual_change = (($current_value - $previous_value) / $previous_value) * 100;
        $target_change = $kpi_config['target_increase'];
        
        return array(
            'actual_change' => $actual_change,
            'target_change' => $target_change,
            'vs_target' => $actual_change - $target_change,
            'performance_rating' => $this->get_performance_rating($actual_change, $target_change)
        );
    }
    
    /**
     * Get performance rating
     */
    private function get_performance_rating($actual, $target) {
        $ratio = $target != 0 ? $actual / $target : 0;
        
        if ($ratio >= 1.2) return 'excellent';
        if ($ratio >= 1.0) return 'good';
        if ($ratio >= 0.8) return 'fair';
        return 'poor';
    }
    
    /**
     * Calculate KPI trend
     */
    private function calculate_kpi_trend($calculation_method, $date_range, $filters) {
        // Calculate trend over the last 7 data points
        $trends = array();
        $days = 7;
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $point_date = date('Y-m-d', strtotime($date_range['end'] . ' -' . $i . ' days'));
            $single_day_range = array('start' => $point_date, 'end' => $point_date);
            
            $value = $this->calculate_kpi_value($calculation_method, $single_day_range, $filters);
            $trends[] = $value;
        }
        
        // Calculate trend direction
        $trend_direction = $this->calculate_trend_direction($trends);
        
        return array(
            'values' => $trends,
            'direction' => $trend_direction,
            'volatility' => $this->calculate_volatility($trends)
        );
    }
    
    /**
     * Calculate trend direction
     */
    private function calculate_trend_direction($values) {
        if (count($values) < 2) {
            return 'stable';
        }
        
        // Simple linear regression to determine trend
        $n = count($values);
        $sum_x = 0;
        $sum_y = 0;
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i;
            $y = $values[$i];
            
            $sum_x += $x;
            $sum_y += $y;
            $sum_xy += $x * $y;
            $sum_x2 += $x * $x;
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        
        if ($slope > 0.1) return 'increasing';
        if ($slope < -0.1) return 'decreasing';
        return 'stable';
    }
    
    /**
     * Calculate volatility
     */
    private function calculate_volatility($values) {
        if (count($values) < 2) {
            return 0;
        }
        
        $mean = array_sum($values) / count($values);
        $variance = 0;
        
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        $variance /= count($values);
        $std_dev = sqrt($variance);
        
        // Return coefficient of variation as volatility percentage
        return $mean != 0 ? ($std_dev / $mean) * 100 : 0;
    }
    
    /**
     * Generate automated insights
     */
    private function generate_automated_insights($kpi_data, $date_range) {
        $insights = array();
        
        // Performance insights
        $insights['performance'] = $this->generate_performance_insights($kpi_data);
        
        // Trend insights
        $insights['trends'] = $this->generate_trend_insights($kpi_data);
        
        // Opportunity insights
        $insights['opportunities'] = $this->generate_opportunity_insights($kpi_data);
        
        // Risk insights
        $insights['risks'] = $this->generate_risk_insights($kpi_data);
        
        return $insights;
    }
    
    /**
     * Generate performance insights
     */
    private function generate_performance_insights($kpi_data) {
        $insights = array();
        
        foreach ($kpi_data as $category => $kpis) {
            foreach ($kpis as $kpi_key => $kpi) {
                if (isset($kpi['performance_vs_target'])) {
                    $performance = $kpi['performance_vs_target'];
                    
                    if ($performance['performance_rating'] === 'excellent') {
                        $insights[] = array(
                            'type' => 'success',
                            'message' => "{$kpi['name']} is performing excellently, exceeding target by " . 
                                        number_format($performance['vs_target'], 1) . " percentage points.",
                            'kpi' => $kpi_key,
                            'category' => $category
                        );
                    } elseif ($performance['performance_rating'] === 'poor') {
                        $insights[] = array(
                            'type' => 'warning',
                            'message' => "{$kpi['name']} is underperforming, missing target by " . 
                                        number_format(abs($performance['vs_target']), 1) . " percentage points.",
                            'kpi' => $kpi_key,
                            'category' => $category
                        );
                    }
                }
            }
        }
        
        return $insights;
    }
    
    /**
     * Generate trend insights
     */
    private function generate_trend_insights($kpi_data) {
        $insights = array();
        
        foreach ($kpi_data as $category => $kpis) {
            foreach ($kpis as $kpi_key => $kpi) {
                if (isset($kpi['trend'])) {
                    $trend = $kpi['trend'];
                    
                    if ($trend['direction'] === 'increasing' && $trend['volatility'] < 20) {
                        $insights[] = array(
                            'type' => 'positive_trend',
                            'message' => "{$kpi['name']} shows a stable upward trend with low volatility.",
                            'kpi' => $kpi_key,
                            'category' => $category
                        );
                    } elseif ($trend['direction'] === 'decreasing' && $trend['volatility'] > 30) {
                        $insights[] = array(
                            'type' => 'negative_trend',
                            'message' => "{$kpi['name']} is declining with high volatility, requiring immediate attention.",
                            'kpi' => $kpi_key,
                            'category' => $category
                        );
                    }
                }
            }
        }
        
        return $insights;
    }
    
    /**
     * Generate opportunity insights
     */
    private function generate_opportunity_insights($kpi_data) {
        $insights = array();
        
        // Multi-touch attribution opportunity
        if (isset($kpi_data['attribution_metrics']['multi_touch_percentage'])) {
            $multi_touch = $kpi_data['attribution_metrics']['multi_touch_percentage']['value'];
            
            if ($multi_touch < 30) {
                $insights[] = array(
                    'type' => 'opportunity',
                    'message' => 'Low multi-touch attribution suggests potential for improved customer journey tracking.',
                    'action' => 'Enhance touchpoint tracking and attribution modeling.',
                    'impact' => 'high'
                );
            }
        }
        
        // Cross-channel synergy opportunity
        if (isset($kpi_data['channel_metrics']['cross_channel_synergy'])) {
            $synergy = $kpi_data['channel_metrics']['cross_channel_synergy']['value'];
            
            if ($synergy < 50) {
                $insights[] = array(
                    'type' => 'opportunity',
                    'message' => 'Cross-channel synergy score indicates room for better channel coordination.',
                    'action' => 'Implement integrated marketing campaigns across channels.',
                    'impact' => 'medium'
                );
            }
        }
        
        return $insights;
    }
    
    /**
     * Generate risk insights
     */
    private function generate_risk_insights($kpi_data) {
        $insights = array();
        
        // Conversion rate risk
        if (isset($kpi_data['conversion_metrics']['conversion_rate'])) {
            $conversion_rate = $kpi_data['conversion_metrics']['conversion_rate'];
            
            if (isset($conversion_rate['trend']) && $conversion_rate['trend']['direction'] === 'decreasing') {
                $insights[] = array(
                    'type' => 'risk',
                    'message' => 'Conversion rate is trending downward, potentially impacting revenue.',
                    'severity' => 'high',
                    'recommended_action' => 'Analyze conversion funnel and optimize key touchpoints.'
                );
            }
        }
        
        // Attribution accuracy risk
        if (isset($kpi_data['attribution_metrics']['attribution_accuracy'])) {
            $accuracy = $kpi_data['attribution_metrics']['attribution_accuracy']['value'];
            
            if ($accuracy < 80) {
                $insights[] = array(
                    'type' => 'risk',
                    'message' => 'Attribution accuracy below 80% may lead to misallocated marketing spend.',
                    'severity' => 'medium',
                    'recommended_action' => 'Review attribution models and data collection methods.'
                );
            }
        }
        
        return $insights;
    }
    
    /**
     * Analyze trends
     */
    private function analyze_trends($kpi_data, $date_range) {
        $trend_analysis = array(
            'summary' => array(),
            'detailed_trends' => array(),
            'seasonal_patterns' => array(),
            'correlation_analysis' => array()
        );
        
        // Summarize overall trends
        $positive_trends = 0;
        $negative_trends = 0;
        $stable_trends = 0;
        
        foreach ($kpi_data as $category => $kpis) {
            foreach ($kpis as $kpi_key => $kpi) {
                if (isset($kpi['trend'])) {
                    switch ($kpi['trend']['direction']) {
                        case 'increasing':
                            $positive_trends++;
                            break;
                        case 'decreasing':
                            $negative_trends++;
                            break;
                        case 'stable':
                            $stable_trends++;
                            break;
                    }
                }
            }
        }
        
        $trend_analysis['summary'] = array(
            'positive_trends' => $positive_trends,
            'negative_trends' => $negative_trends,
            'stable_trends' => $stable_trends,
            'overall_momentum' => $this->calculate_overall_momentum($positive_trends, $negative_trends, $stable_trends)
        );
        
        return $trend_analysis;
    }
    
    /**
     * Calculate overall momentum
     */
    private function calculate_overall_momentum($positive, $negative, $stable) {
        $total = $positive + $negative + $stable;
        
        if ($total === 0) {
            return 'neutral';
        }
        
        $positive_ratio = $positive / $total;
        $negative_ratio = $negative / $total;
        
        if ($positive_ratio > 0.6) return 'strong_positive';
        if ($positive_ratio > 0.4) return 'positive';
        if ($negative_ratio > 0.6) return 'strong_negative';
        if ($negative_ratio > 0.4) return 'negative';
        
        return 'neutral';
    }
    
    /**
     * Generate recommendations
     */
    private function generate_recommendations($kpi_data, $trend_analysis) {
        $recommendations = array(
            'immediate_actions' => array(),
            'strategic_initiatives' => array(),
            'optimization_opportunities' => array()
        );
        
        // Based on overall momentum
        $momentum = $trend_analysis['summary']['overall_momentum'];
        
        switch ($momentum) {
            case 'strong_positive':
                $recommendations['strategic_initiatives'][] = array(
                    'title' => 'Scale Successful Campaigns',
                    'description' => 'Strong positive momentum indicates successful strategies. Consider increasing budget allocation to top-performing channels.',
                    'priority' => 'high',
                    'timeframe' => 'immediate'
                );
                break;
                
            case 'strong_negative':
                $recommendations['immediate_actions'][] = array(
                    'title' => 'Emergency Performance Review',
                    'description' => 'Multiple KPIs trending negatively. Conduct immediate review of attribution setup and marketing campaigns.',
                    'priority' => 'critical',
                    'timeframe' => 'immediate'
                );
                break;
                
            case 'neutral':
                $recommendations['optimization_opportunities'][] = array(
                    'title' => 'Attribution Model Testing',
                    'description' => 'Stable performance suggests opportunity for testing new attribution models and optimization strategies.',
                    'priority' => 'medium',
                    'timeframe' => 'short_term'
                );
                break;
        }
        
        return $recommendations;
    }
    
    /**
     * Generate executive summary
     */
    private function generate_executive_summary($kpi_data, $trend_analysis) {
        // Get key metrics for summary
        $revenue = $kpi_data['revenue_metrics']['total_revenue']['value'] ?? 0;
        $conversion_rate = $kpi_data['conversion_metrics']['conversion_rate']['value'] ?? 0;
        $attribution_accuracy = $kpi_data['attribution_metrics']['attribution_accuracy']['value'] ?? 0;
        
        $momentum = $trend_analysis['summary']['overall_momentum'];
        
        $summary = array(
            'headline' => $this->generate_headline($revenue, $conversion_rate, $momentum),
            'key_metrics' => array(
                'total_revenue' => $kpi_data['revenue_metrics']['total_revenue']['formatted_value'] ?? '$0',
                'conversion_rate' => $kpi_data['conversion_metrics']['conversion_rate']['formatted_value'] ?? '0%',
                'attribution_accuracy' => $kpi_data['attribution_metrics']['attribution_accuracy']['formatted_value'] ?? '0%'
            ),
            'momentum_description' => $this->get_momentum_description($momentum),
            'top_insight' => $this->get_top_insight($kpi_data, $trend_analysis)
        );
        
        return $summary;
    }
    
    /**
     * Generate headline
     */
    private function generate_headline($revenue, $conversion_rate, $momentum) {
        $headlines = array(
            'strong_positive' => 'Excellent Performance: Revenue and conversions trending strongly upward',
            'positive' => 'Good Performance: Most metrics showing positive trends',
            'neutral' => 'Stable Performance: Metrics holding steady with optimization opportunities',
            'negative' => 'Declining Performance: Several metrics trending downward',
            'strong_negative' => 'Critical Performance Issues: Immediate attention required'
        );
        
        return $headlines[$momentum] ?? 'Performance Analysis Complete';
    }
    
    /**
     * Get momentum description
     */
    private function get_momentum_description($momentum) {
        $descriptions = array(
            'strong_positive' => 'Strong upward momentum across multiple KPIs indicates highly effective attribution and marketing strategies.',
            'positive' => 'Generally positive trends suggest good attribution performance with room for optimization.',
            'neutral' => 'Stable performance provides a solid foundation for testing new attribution models and strategies.',
            'negative' => 'Declining trends across several metrics require investigation and strategy adjustment.',
            'strong_negative' => 'Multiple critical metrics declining rapidly - immediate intervention required.'
        );
        
        return $descriptions[$momentum] ?? 'Performance analysis completed.';
    }
    
    /**
     * Get top insight
     */
    private function get_top_insight($kpi_data, $trend_analysis) {
        // Find the most significant insight based on performance gaps
        $top_insight = 'Continue monitoring attribution performance for optimization opportunities.';
        
        foreach ($kpi_data as $category => $kpis) {
            foreach ($kpis as $kpi_key => $kpi) {
                if (isset($kpi['performance_vs_target'])) {
                    $vs_target = $kpi['performance_vs_target']['vs_target'];
                    
                    if ($vs_target > 20) {
                        $top_insight = "{$kpi['name']} is significantly exceeding targets - consider reallocating resources to scale this success.";
                        break 2;
                    } elseif ($vs_target < -20) {
                        $top_insight = "{$kpi['name']} is significantly underperforming - immediate optimization required.";
                        break 2;
                    }
                }
            }
        }
        
        return $top_insight;
    }
    
    /**
     * Check performance alerts
     */
    private function check_performance_alerts($kpi_data) {
        $alerts = array();
        $thresholds = $this->intelligence_config['alert_thresholds'];
        
        // Check conversion rate drop
        if (isset($kpi_data['conversion_metrics']['conversion_rate']['performance_vs_target'])) {
            $performance = $kpi_data['conversion_metrics']['conversion_rate']['performance_vs_target'];
            
            if ($performance['vs_target'] < -$thresholds['conversion_rate_drop']) {
                $alerts[] = array(
                    'type' => 'critical',
                    'metric' => 'Conversion Rate',
                    'message' => 'Conversion rate dropped significantly below target',
                    'value' => $performance['vs_target'],
                    'threshold' => -$thresholds['conversion_rate_drop']
                );
            }
        }
        
        // Check revenue decline
        if (isset($kpi_data['revenue_metrics']['total_revenue']['performance_vs_target'])) {
            $performance = $kpi_data['revenue_metrics']['total_revenue']['performance_vs_target'];
            
            if ($performance['vs_target'] < -$thresholds['revenue_decline']) {
                $alerts[] = array(
                    'type' => 'warning',
                    'metric' => 'Total Revenue',
                    'message' => 'Revenue declined significantly compared to target',
                    'value' => $performance['vs_target'],
                    'threshold' => -$thresholds['revenue_decline']
                );
            }
        }
        
        return $alerts;
    }
    
    /**
     * Store insights for caching
     */
    private function store_insights($insights) {
        // Store in transient for quick access
        $cache_key = 'khm_business_insights_' . md5(serialize($insights['date_range']));
        set_transient($cache_key, $insights, $this->intelligence_config['cache_duration']);
        
        // Also store in database for historical analysis
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_business_insights';
        $this->maybe_create_insights_table();
        
        $wpdb->insert($table_name, array(
            'insights_data' => json_encode($insights),
            'date_range_start' => $insights['date_range']['start'],
            'date_range_end' => $insights['date_range']['end'],
            'created_at' => current_time('mysql')
        ));
    }
    
    /**
     * Create insights table
     */
    private function maybe_create_insights_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_business_insights';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            insights_data LONGTEXT,
            date_range_start DATE,
            date_range_end DATE,
            created_at DATETIME NOT NULL,
            
            INDEX idx_date_range (date_range_start, date_range_end),
            INDEX idx_created_timeline (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_get_kpi_data() {
        check_ajax_referer('khm_business_intelligence_nonce', 'nonce');
        
        $date_range = array(
            'start' => sanitize_text_field($_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'))),
            'end' => sanitize_text_field($_POST['end_date'] ?? date('Y-m-d'))
        );
        
        $kpi_data = $this->calculate_all_kpis($date_range);
        
        wp_send_json_success($kpi_data);
    }
    
    public function ajax_generate_report() {
        check_ajax_referer('khm_business_intelligence_nonce', 'nonce');
        
        $date_range = array(
            'start' => sanitize_text_field($_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'))),
            'end' => sanitize_text_field($_POST['end_date'] ?? date('Y-m-d'))
        );
        
        $insights = $this->generate_business_insights($date_range);
        
        wp_send_json_success($insights);
    }
    
    public function ajax_get_insights() {
        check_ajax_referer('khm_business_intelligence_nonce', 'nonce');
        
        // Try to get cached insights first
        $date_range = array(
            'start' => sanitize_text_field($_POST['start_date'] ?? date('Y-m-d', strtotime('-7 days'))),
            'end' => sanitize_text_field($_POST['end_date'] ?? date('Y-m-d'))
        );
        
        $cache_key = 'khm_business_insights_' . md5(serialize($date_range));
        $insights = get_transient($cache_key);
        
        if (!$insights) {
            $insights = $this->generate_business_insights($date_range);
        }
        
        wp_send_json_success($insights);
    }
    
    /**
     * Generate daily insights
     */
    public function generate_daily_insights() {
        $yesterday = array(
            'start' => date('Y-m-d', strtotime('-1 day')),
            'end' => date('Y-m-d', strtotime('-1 day'))
        );
        
        $insights = $this->generate_business_insights($yesterday);
        
        // Send notifications if critical alerts
        $this->process_insight_notifications($insights);
    }
    
    /**
     * Process insight notifications
     */
    private function process_insight_notifications($insights) {
        if (!empty($insights['alerts'])) {
            $critical_alerts = array_filter($insights['alerts'], function($alert) {
                return $alert['type'] === 'critical';
            });
            
            if (!empty($critical_alerts)) {
                // Send notification to administrators
                $this->send_critical_alert_notification($critical_alerts);
            }
        }
    }
    
    /**
     * Send critical alert notification
     */
    private function send_critical_alert_notification($alerts) {
        $admin_email = get_option('admin_email');
        $subject = 'KHM Attribution: Critical Performance Alerts';
        
        $message = "Critical performance alerts detected:\n\n";
        
        foreach ($alerts as $alert) {
            $message .= "• {$alert['metric']}: {$alert['message']}\n";
        }
        
        $message .= "\nPlease review your attribution system performance immediately.";
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Update KPI dashboard
     */
    public function update_kpi_dashboard() {
        // This would update dashboard widgets with latest KPI data
        // Implementation would depend on specific dashboard framework
    }
    
    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'khm_kpi_summary',
            'Attribution KPI Summary',
            array($this, 'render_kpi_dashboard_widget')
        );
    }
    
    /**
     * Render KPI dashboard widget
     */
    public function render_kpi_dashboard_widget() {
        $kpi_data = $this->calculate_all_kpis(array(
            'start' => date('Y-m-d', strtotime('-7 days')),
            'end' => date('Y-m-d')
        ));
        
        echo '<div class="khm-kpi-widget">';
        echo '<h4>Last 7 Days Performance</h4>';
        
        if (isset($kpi_data['revenue_metrics']['total_revenue'])) {
            echo '<p><strong>Revenue:</strong> ' . $kpi_data['revenue_metrics']['total_revenue']['formatted_value'] . '</p>';
        }
        
        if (isset($kpi_data['conversion_metrics']['conversion_rate'])) {
            echo '<p><strong>Conversion Rate:</strong> ' . $kpi_data['conversion_metrics']['conversion_rate']['formatted_value'] . '</p>';
        }
        
        if (isset($kpi_data['attribution_metrics']['attribution_accuracy'])) {
            echo '<p><strong>Attribution Accuracy:</strong> ' . $kpi_data['attribution_metrics']['attribution_accuracy']['formatted_value'] . '</p>';
        }
        
        echo '<p><a href="' . admin_url('admin.php?page=khm-attribution-insights') . '">View Full Report</a></p>';
        echo '</div>';
    }
}
?>