<?php
/**
 * KHM Attribution Business Analytics Engine
 * 
 * Advanced business intelligence features for attribution system
 * Provides P&L calculations, funnel analysis, forecasting, and ROI optimization
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Business_Analytics {
    
    private $query_builder;
    private $performance_manager;
    private $analytics_cache_prefix = 'khm_analytics_';
    private $analytics_cache_ttl = 3600; // 1 hour
    
    public function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once dirname(__FILE__) . '/QueryBuilder.php';
        require_once dirname(__FILE__) . '/PerformanceManager.php';
        
        $this->query_builder = new KHM_Attribution_Query_Builder();
        $this->performance_manager = new KHM_Attribution_Performance_Manager();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_khm_analytics_pnl', array($this, 'ajax_get_pnl_analysis'));
        add_action('wp_ajax_khm_analytics_funnel', array($this, 'ajax_get_funnel_analysis'));
        add_action('wp_ajax_khm_analytics_forecast', array($this, 'ajax_get_forecast_analysis'));
        add_action('wp_ajax_khm_analytics_roi_optimization', array($this, 'ajax_get_roi_optimization'));
        add_action('wp_ajax_khm_analytics_cohort', array($this, 'ajax_get_cohort_analysis'));
        add_action('wp_ajax_khm_analytics_attribution_comparison', array($this, 'ajax_get_attribution_comparison'));
        
        // Scheduled analytics updates
        add_action('khm_analytics_daily_calculation', array($this, 'calculate_daily_analytics'));
        add_action('khm_analytics_weekly_calculation', array($this, 'calculate_weekly_analytics'));
        
        // Schedule analytics calculations
        if (!wp_next_scheduled('khm_analytics_daily_calculation')) {
            wp_schedule_event(time(), 'daily', 'khm_analytics_daily_calculation');
        }
        
        if (!wp_next_scheduled('khm_analytics_weekly_calculation')) {
            wp_schedule_event(time(), 'weekly', 'khm_analytics_weekly_calculation');
        }
    }
    
    /**
     * Get comprehensive P&L analysis
     */
    public function get_pnl_analysis($filters = array()) {
        $cache_key = $this->analytics_cache_prefix . 'pnl_' . md5(serialize($filters));
        $cached_result = $this->performance_manager->get_cache($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $start_time = microtime(true);
        
        // Default filters
        $defaults = array(
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'affiliate_ids' => array(),
            'campaign_ids' => array(),
            'product_categories' => array(),
            'attribution_model' => 'last_touch',
            'currency' => 'USD'
        );
        
        $filters = array_merge($defaults, $filters);
        
        // Get revenue data
        $revenue_data = $this->calculate_revenue_metrics($filters);
        
        // Get cost data
        $cost_data = $this->calculate_cost_metrics($filters);
        
        // Calculate profit metrics
        $profit_data = $this->calculate_profit_metrics($revenue_data, $cost_data);
        
        // Get efficiency metrics
        $efficiency_data = $this->calculate_efficiency_metrics($revenue_data, $cost_data);
        
        // Get trend analysis
        $trend_data = $this->calculate_trend_analysis($filters);
        
        // Compile comprehensive P&L report
        $pnl_analysis = array(
            'period' => array(
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'days' => (strtotime($filters['date_to']) - strtotime($filters['date_from'])) / 86400 + 1
            ),
            'revenue' => $revenue_data,
            'costs' => $cost_data,
            'profit' => $profit_data,
            'efficiency' => $efficiency_data,
            'trends' => $trend_data,
            'breakdown' => array(
                'by_affiliate' => $this->get_pnl_by_affiliate($filters),
                'by_campaign' => $this->get_pnl_by_campaign($filters),
                'by_product' => $this->get_pnl_by_product($filters),
                'by_channel' => $this->get_pnl_by_channel($filters)
            ),
            'calculated_at' => time(),
            'calculation_time' => microtime(true) - $start_time
        );
        
        // Cache the result
        $this->performance_manager->set_cache($cache_key, $pnl_analysis, $this->analytics_cache_ttl);
        
        return $pnl_analysis;
    }
    
    /**
     * Calculate revenue metrics
     */
    private function calculate_revenue_metrics($filters) {
        $conversions = $this->query_builder->get_conversions_with_attribution($filters);
        
        $revenue_metrics = array(
            'total_revenue' => 0,
            'attributed_revenue' => 0,
            'commission_revenue' => 0,
            'average_order_value' => 0,
            'total_conversions' => count($conversions),
            'attributed_conversions' => 0,
            'conversion_rate' => 0,
            'revenue_by_day' => array(),
            'revenue_by_source' => array()
        );
        
        $daily_revenue = array();
        $source_revenue = array();
        $total_clicks = 0;
        
        foreach ($conversions as $conversion) {
            $revenue = floatval($conversion['value']);
            $day = date('Y-m-d', strtotime($conversion['created_at']));
            
            // Total revenue
            $revenue_metrics['total_revenue'] += $revenue;
            
            // Daily revenue tracking
            if (!isset($daily_revenue[$day])) {
                $daily_revenue[$day] = 0;
            }
            $daily_revenue[$day] += $revenue;
            
            // If conversion has attribution
            if (!empty($conversion['attribution_data'])) {
                $revenue_metrics['attributed_revenue'] += $revenue;
                $revenue_metrics['attributed_conversions']++;
                
                // Calculate commission revenue
                $commission_rate = floatval($conversion['commission_rate'] ?? 0.1);
                $revenue_metrics['commission_revenue'] += $revenue * $commission_rate;
                
                // Track by source
                $attribution = json_decode($conversion['attribution_data'], true);
                if (!empty($attribution['primary_source'])) {
                    $source = $attribution['primary_source'];
                    if (!isset($source_revenue[$source])) {
                        $source_revenue[$source] = 0;
                    }
                    $source_revenue[$source] += $revenue;
                }
            }
        }
        
        // Calculate averages and rates
        if ($revenue_metrics['total_conversions'] > 0) {
            $revenue_metrics['average_order_value'] = $revenue_metrics['total_revenue'] / $revenue_metrics['total_conversions'];
        }
        
        // Get total clicks for conversion rate
        $click_data = $this->query_builder->get_attribution_events(array(
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'event_type' => 'click'
        ));
        
        $total_clicks = count($click_data);
        if ($total_clicks > 0) {
            $revenue_metrics['conversion_rate'] = ($revenue_metrics['total_conversions'] / $total_clicks) * 100;
        }
        
        $revenue_metrics['revenue_by_day'] = $daily_revenue;
        $revenue_metrics['revenue_by_source'] = $source_revenue;
        
        return $revenue_metrics;
    }
    
    /**
     * Calculate cost metrics
     */
    private function calculate_cost_metrics($filters) {
        // Get affiliate commission costs
        $commission_costs = $this->calculate_commission_costs($filters);
        
        // Get advertising costs (if available)
        $advertising_costs = $this->calculate_advertising_costs($filters);
        
        // Get operational costs
        $operational_costs = $this->calculate_operational_costs($filters);
        
        // Get technology costs
        $technology_costs = $this->calculate_technology_costs($filters);
        
        $total_costs = $commission_costs + $advertising_costs + $operational_costs + $technology_costs;
        
        return array(
            'total_costs' => $total_costs,
            'commission_costs' => $commission_costs,
            'advertising_costs' => $advertising_costs,
            'operational_costs' => $operational_costs,
            'technology_costs' => $technology_costs,
            'cost_breakdown' => array(
                'commission_percentage' => $total_costs > 0 ? ($commission_costs / $total_costs) * 100 : 0,
                'advertising_percentage' => $total_costs > 0 ? ($advertising_costs / $total_costs) * 100 : 0,
                'operational_percentage' => $total_costs > 0 ? ($operational_costs / $total_costs) * 100 : 0,
                'technology_percentage' => $total_costs > 0 ? ($technology_costs / $total_costs) * 100 : 0
            ),
            'cost_per_conversion' => 0,
            'cost_per_click' => 0
        );
    }
    
    /**
     * Calculate profit metrics
     */
    private function calculate_profit_metrics($revenue_data, $cost_data) {
        $gross_profit = $revenue_data['total_revenue'] - $cost_data['total_costs'];
        $net_profit = $revenue_data['attributed_revenue'] - $cost_data['commission_costs'];
        
        $profit_margin = $revenue_data['total_revenue'] > 0 ? 
            ($gross_profit / $revenue_data['total_revenue']) * 100 : 0;
        
        $net_profit_margin = $revenue_data['attributed_revenue'] > 0 ? 
            ($net_profit / $revenue_data['attributed_revenue']) * 100 : 0;
        
        return array(
            'gross_profit' => $gross_profit,
            'net_profit' => $net_profit,
            'profit_margin' => $profit_margin,
            'net_profit_margin' => $net_profit_margin,
            'break_even_point' => $this->calculate_break_even_point($revenue_data, $cost_data),
            'profit_by_day' => $this->calculate_daily_profit($revenue_data, $cost_data),
            'profitability_score' => $this->calculate_profitability_score($revenue_data, $cost_data)
        );
    }
    
    /**
     * Get funnel analysis
     */
    public function get_funnel_analysis($filters = array()) {
        $cache_key = $this->analytics_cache_prefix . 'funnel_' . md5(serialize($filters));
        $cached_result = $this->performance_manager->get_cache($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $start_time = microtime(true);
        
        // Default filters
        $defaults = array(
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'funnel_steps' => array('impression', 'click', 'visit', 'add_to_cart', 'checkout', 'conversion'),
            'attribution_window' => 30, // days
            'include_cross_device' => true
        );
        
        $filters = array_merge($defaults, $filters);
        
        // Calculate funnel metrics for each step
        $funnel_data = array();
        $previous_count = 0;
        
        foreach ($filters['funnel_steps'] as $index => $step) {
            $step_data = $this->calculate_funnel_step($step, $filters);
            
            $funnel_data[$step] = array(
                'step_number' => $index + 1,
                'step_name' => ucfirst(str_replace('_', ' ', $step)),
                'count' => $step_data['count'],
                'unique_users' => $step_data['unique_users'],
                'conversion_rate' => $previous_count > 0 ? ($step_data['count'] / $previous_count) * 100 : 100,
                'drop_off_rate' => $previous_count > 0 ? (($previous_count - $step_data['count']) / $previous_count) * 100 : 0,
                'average_time_to_next_step' => $step_data['avg_time_to_next'],
                'revenue_attributed' => $step_data['revenue'],
                'top_drop_off_reasons' => $step_data['drop_off_reasons']
            );
            
            $previous_count = $step_data['count'];
        }
        
        // Calculate overall funnel metrics
        $first_step = reset($funnel_data);
        $last_step = end($funnel_data);
        
        $overall_conversion_rate = $first_step['count'] > 0 ? 
            ($last_step['count'] / $first_step['count']) * 100 : 0;
        
        $funnel_analysis = array(
            'period' => array(
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to']
            ),
            'overall_metrics' => array(
                'total_entries' => $first_step['count'],
                'total_conversions' => $last_step['count'],
                'overall_conversion_rate' => $overall_conversion_rate,
                'average_time_to_convert' => $this->calculate_average_conversion_time($filters),
                'total_revenue' => array_sum(array_column($funnel_data, 'revenue_attributed'))
            ),
            'funnel_steps' => $funnel_data,
            'optimization_opportunities' => $this->identify_funnel_optimization_opportunities($funnel_data),
            'cohort_analysis' => $this->get_funnel_cohort_analysis($filters),
            'calculated_at' => time(),
            'calculation_time' => microtime(true) - $start_time
        );
        
        // Cache the result
        $this->performance_manager->set_cache($cache_key, $funnel_analysis, $this->analytics_cache_ttl);
        
        return $funnel_analysis;
    }
    
    /**
     * Get forecasting analysis
     */
    public function get_forecasting_analysis($filters = array()) {
        $cache_key = $this->analytics_cache_prefix . 'forecast_' . md5(serialize($filters));
        $cached_result = $this->performance_manager->get_cache($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $start_time = microtime(true);
        
        // Default filters
        $defaults = array(
            'historical_days' => 90,
            'forecast_days' => 30,
            'confidence_level' => 95,
            'seasonality_adjustment' => true,
            'trend_analysis' => true,
            'external_factors' => array()
        );
        
        $filters = array_merge($defaults, $filters);
        
        // Get historical data
        $historical_data = $this->get_historical_performance_data($filters);
        
        // Apply forecasting algorithms
        $revenue_forecast = $this->forecast_revenue($historical_data, $filters);
        $conversion_forecast = $this->forecast_conversions($historical_data, $filters);
        $traffic_forecast = $this->forecast_traffic($historical_data, $filters);
        
        // Calculate confidence intervals
        $confidence_intervals = $this->calculate_forecast_confidence($historical_data, $filters);
        
        // Generate recommendations
        $recommendations = $this->generate_forecast_recommendations($revenue_forecast, $conversion_forecast, $traffic_forecast);
        
        $forecasting_analysis = array(
            'forecast_period' => array(
                'start_date' => date('Y-m-d', strtotime('+1 day')),
                'end_date' => date('Y-m-d', strtotime('+' . $filters['forecast_days'] . ' days')),
                'days' => $filters['forecast_days']
            ),
            'historical_baseline' => array(
                'period_days' => $filters['historical_days'],
                'average_daily_revenue' => $historical_data['avg_daily_revenue'],
                'average_daily_conversions' => $historical_data['avg_daily_conversions'],
                'average_daily_traffic' => $historical_data['avg_daily_traffic'],
                'trend_direction' => $historical_data['trend_direction'],
                'seasonality_detected' => $historical_data['seasonality_detected']
            ),
            'forecasts' => array(
                'revenue' => $revenue_forecast,
                'conversions' => $conversion_forecast,
                'traffic' => $traffic_forecast
            ),
            'confidence_intervals' => $confidence_intervals,
            'scenario_analysis' => array(
                'optimistic' => $this->calculate_optimistic_scenario($revenue_forecast, $conversion_forecast),
                'realistic' => $this->calculate_realistic_scenario($revenue_forecast, $conversion_forecast),
                'pessimistic' => $this->calculate_pessimistic_scenario($revenue_forecast, $conversion_forecast)
            ),
            'recommendations' => $recommendations,
            'model_accuracy' => $this->calculate_model_accuracy($historical_data),
            'calculated_at' => time(),
            'calculation_time' => microtime(true) - $start_time
        );
        
        // Cache the result
        $this->performance_manager->set_cache($cache_key, $forecasting_analysis, $this->analytics_cache_ttl);
        
        return $forecasting_analysis;
    }
    
    /**
     * Get ROI optimization analysis
     */
    public function get_roi_optimization($filters = array()) {
        $cache_key = $this->analytics_cache_prefix . 'roi_opt_' . md5(serialize($filters));
        $cached_result = $this->performance_manager->get_cache($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $start_time = microtime(true);
        
        // Get current ROI metrics
        $current_roi = $this->calculate_current_roi_metrics($filters);
        
        // Identify optimization opportunities
        $optimization_opportunities = $this->identify_roi_optimization_opportunities($filters);
        
        // Calculate potential improvements
        $improvement_scenarios = $this->calculate_roi_improvement_scenarios($current_roi, $optimization_opportunities);
        
        // Generate actionable recommendations
        $actionable_recommendations = $this->generate_roi_recommendations($optimization_opportunities, $improvement_scenarios);
        
        $roi_optimization = array(
            'current_performance' => $current_roi,
            'optimization_opportunities' => $optimization_opportunities,
            'improvement_scenarios' => $improvement_scenarios,
            'recommendations' => $actionable_recommendations,
            'priority_actions' => $this->prioritize_roi_actions($actionable_recommendations),
            'estimated_impact' => $this->estimate_roi_impact($improvement_scenarios),
            'calculated_at' => time(),
            'calculation_time' => microtime(true) - $start_time
        );
        
        // Cache the result
        $this->performance_manager->set_cache($cache_key, $roi_optimization, $this->analytics_cache_ttl);
        
        return $roi_optimization;
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_get_pnl_analysis() {
        check_ajax_referer('khm_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $filters = array();
        if (isset($_POST['filters'])) {
            $filters = json_decode(stripslashes($_POST['filters']), true);
        }
        
        $pnl_analysis = $this->get_pnl_analysis($filters);
        wp_send_json_success($pnl_analysis);
    }
    
    public function ajax_get_funnel_analysis() {
        check_ajax_referer('khm_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $filters = array();
        if (isset($_POST['filters'])) {
            $filters = json_decode(stripslashes($_POST['filters']), true);
        }
        
        $funnel_analysis = $this->get_funnel_analysis($filters);
        wp_send_json_success($funnel_analysis);
    }
    
    public function ajax_get_forecast_analysis() {
        check_ajax_referer('khm_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $filters = array();
        if (isset($_POST['filters'])) {
            $filters = json_decode(stripslashes($_POST['filters']), true);
        }
        
        $forecast_analysis = $this->get_forecasting_analysis($filters);
        wp_send_json_success($forecast_analysis);
    }
    
    public function ajax_get_roi_optimization() {
        check_ajax_referer('khm_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $filters = array();
        if (isset($_POST['filters'])) {
            $filters = json_decode(stripslashes($_POST['filters']), true);
        }
        
        $roi_optimization = $this->get_roi_optimization($filters);
        wp_send_json_success($roi_optimization);
    }
    
    /**
     * Helper methods for calculations
     */
    private function calculate_commission_costs($filters) {
        $conversions = $this->query_builder->get_conversions_with_attribution($filters);
        $total_commission = 0;
        
        foreach ($conversions as $conversion) {
            if (!empty($conversion['attribution_data'])) {
                $revenue = floatval($conversion['value']);
                $commission_rate = floatval($conversion['commission_rate'] ?? 0.1);
                $total_commission += $revenue * $commission_rate;
            }
        }
        
        return $total_commission;
    }
    
    private function calculate_advertising_costs($filters) {
        // Placeholder for advertising cost calculation
        // In a real implementation, this would integrate with ad platforms
        return 0;
    }
    
    private function calculate_operational_costs($filters) {
        // Calculate operational costs based on volume and time period
        $days = (strtotime($filters['date_to']) - strtotime($filters['date_from'])) / 86400 + 1;
        $daily_operational_cost = 50; // $50/day baseline
        
        return $days * $daily_operational_cost;
    }
    
    private function calculate_technology_costs($filters) {
        // Calculate technology stack costs
        $days = (strtotime($filters['date_to']) - strtotime($filters['date_from'])) / 86400 + 1;
        $daily_tech_cost = 25; // $25/day for hosting, tools, etc.
        
        return $days * $daily_tech_cost;
    }
    
    private function calculate_break_even_point($revenue_data, $cost_data) {
        if ($cost_data['total_costs'] == 0) {
            return array('days' => 0, 'revenue_needed' => 0);
        }
        
        $daily_profit = ($revenue_data['total_revenue'] - $cost_data['total_costs']) / 30; // Assuming 30 day period
        
        if ($daily_profit <= 0) {
            return array('days' => -1, 'revenue_needed' => $cost_data['total_costs']);
        }
        
        return array(
            'days' => $cost_data['total_costs'] / $daily_profit,
            'revenue_needed' => $cost_data['total_costs']
        );
    }
    
    private function calculate_profitability_score($revenue_data, $cost_data) {
        // Calculate a 0-100 profitability score
        if ($cost_data['total_costs'] == 0) {
            return 100;
        }
        
        $profit_margin = (($revenue_data['total_revenue'] - $cost_data['total_costs']) / $revenue_data['total_revenue']) * 100;
        $efficiency_score = ($revenue_data['conversion_rate'] / 5) * 20; // Normalize conversion rate
        $volume_score = min(20, ($revenue_data['total_conversions'] / 100) * 20); // Normalize volume
        
        return min(100, max(0, $profit_margin + $efficiency_score + $volume_score));
    }
    
    // Additional helper methods would be implemented here...
    private function get_pnl_by_affiliate($filters) {
        // Implementation for affiliate-specific P&L breakdown
        return array();
    }
    
    private function get_pnl_by_campaign($filters) {
        // Implementation for campaign-specific P&L breakdown
        return array();
    }
    
    private function get_pnl_by_product($filters) {
        // Implementation for product-specific P&L breakdown
        return array();
    }
    
    private function get_pnl_by_channel($filters) {
        // Implementation for channel-specific P&L breakdown
        return array();
    }
    
    /**
     * Scheduled analytics calculations
     */
    public function calculate_daily_analytics() {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $filters = array(
            'date_from' => $yesterday,
            'date_to' => $yesterday
        );
        
        // Calculate and cache daily metrics
        $this->get_pnl_analysis($filters);
        $this->get_funnel_analysis($filters);
        
        // Log calculation
        error_log('KHM Analytics: Daily calculation completed for ' . $yesterday);
    }
    
    public function calculate_weekly_analytics() {
        $week_start = date('Y-m-d', strtotime('-7 days'));
        $week_end = date('Y-m-d', strtotime('-1 day'));
        
        $filters = array(
            'date_from' => $week_start,
            'date_to' => $week_end
        );
        
        // Calculate and cache weekly metrics
        $this->get_pnl_analysis($filters);
        $this->get_funnel_analysis($filters);
        $this->get_forecasting_analysis($filters);
        $this->get_roi_optimization($filters);
        
        // Log calculation
        error_log('KHM Analytics: Weekly calculation completed for ' . $week_start . ' to ' . $week_end);
    }
}

// Initialize the business analytics engine
if (is_admin()) {
    new KHM_Attribution_Business_Analytics();
}
?>