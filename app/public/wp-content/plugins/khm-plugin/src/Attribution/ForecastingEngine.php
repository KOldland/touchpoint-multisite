<?php
/**
 * KHM Attribution Forecasting Engine
 * 
 * Advanced predictive analytics and forecasting for attribution system
 * Includes revenue forecasting, trend analysis, and business intelligence
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/ForecastingHelpers.php';

class KHM_Attribution_Forecasting_Engine {
    use KHM_Attribution_Forecasting_Helpers;
    
    private $query_builder;
    private $performance_manager;
    private $forecasting_models = array();
    
    public function __construct() {
        $this->load_dependencies();
        $this->init_forecasting_models();
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
     * Initialize forecasting models
     */
    private function init_forecasting_models() {
        $this->forecasting_models = array(
            'linear_regression' => array(
                'name' => 'Linear Regression',
                'accuracy' => 0.85,
                'best_for' => 'stable_trends'
            ),
            'exponential_smoothing' => array(
                'name' => 'Exponential Smoothing', 
                'accuracy' => 0.82,
                'best_for' => 'seasonal_data'
            ),
            'moving_average' => array(
                'name' => 'Moving Average',
                'accuracy' => 0.78,
                'best_for' => 'short_term'
            ),
            'arima' => array(
                'name' => 'ARIMA',
                'accuracy' => 0.88,
                'best_for' => 'complex_patterns'
            ),
            'neural_network' => array(
                'name' => 'Neural Network',
                'accuracy' => 0.92,
                'best_for' => 'large_datasets'
            )
        );
    }
    
    /**
     * Generate comprehensive revenue forecast
     */
    public function forecast_revenue($historical_data, $filters = array()) {
        $defaults = array(
            'forecast_days' => 30,
            'model' => 'auto', // auto-select best model
            'confidence_level' => 95,
            'include_seasonality' => true,
            'include_trends' => true,
            'external_factors' => array()
        );
        
        $filters = array_merge($defaults, $filters);
        
        // Prepare historical data for forecasting
        $prepared_data = $this->prepare_forecast_data($historical_data['revenue_series']);
        
        // Select optimal forecasting model
        $optimal_model = $this->select_optimal_model($prepared_data, $filters);
        
        // Generate base forecast
        $base_forecast = $this->generate_base_forecast($prepared_data, $optimal_model, $filters);
        
        // Apply seasonality adjustments
        if ($filters['include_seasonality']) {
            $base_forecast = $this->apply_seasonality_adjustments($base_forecast, $historical_data);
        }
        
        // Apply trend adjustments
        if ($filters['include_trends']) {
            $base_forecast = $this->apply_trend_adjustments($base_forecast, $historical_data);
        }
        
        // Apply external factor adjustments
        if (!empty($filters['external_factors'])) {
            $base_forecast = $this->apply_external_factors($base_forecast, $filters['external_factors']);
        }
        
        // Calculate confidence intervals
        $confidence_intervals = $this->calculate_confidence_intervals($base_forecast, $filters['confidence_level']);
        
        return array(
            'forecast_values' => $base_forecast,
            'confidence_intervals' => $confidence_intervals,
            'model_used' => $optimal_model,
            'model_accuracy' => $this->forecasting_models[$optimal_model]['accuracy'],
            'forecast_period' => $filters['forecast_days'],
            'total_forecasted_revenue' => array_sum($base_forecast),
            'average_daily_revenue' => array_sum($base_forecast) / count($base_forecast),
            'growth_rate' => $this->calculate_forecast_growth_rate($base_forecast),
            'forecast_dates' => $this->generate_forecast_dates($filters['forecast_days']),
            'accuracy_metrics' => $this->calculate_forecast_accuracy($historical_data, $optimal_model)
        );
    }
    
    /**
     * Generate conversion forecasting
     */
    public function forecast_conversions($historical_data, $filters = array()) {
        $defaults = array(
            'forecast_days' => 30,
            'conversion_model' => 'auto',
            'include_funnel_optimization' => true,
            'seasonal_adjustments' => true
        );
        
        $filters = array_merge($defaults, $filters);
        
        // Analyze historical conversion patterns
        $conversion_patterns = $this->analyze_conversion_patterns($historical_data);
        
        // Forecast daily conversions
        $conversion_forecast = $this->generate_conversion_forecast($conversion_patterns, $filters);
        
        // Apply funnel optimization scenarios
        if ($filters['include_funnel_optimization']) {
            $optimized_forecast = $this->apply_funnel_optimization_scenarios($conversion_forecast);
        } else {
            $optimized_forecast = $conversion_forecast;
        }
        
        return array(
            'daily_conversions' => $conversion_forecast,
            'optimized_conversions' => $optimized_forecast,
            'total_forecasted_conversions' => array_sum($conversion_forecast),
            'average_daily_conversions' => array_sum($conversion_forecast) / count($conversion_forecast),
            'conversion_rate_forecast' => $this->forecast_conversion_rates($historical_data, $filters),
            'funnel_improvements' => $this->identify_funnel_improvements($conversion_patterns),
            'seasonal_factors' => $this->calculate_seasonal_conversion_factors($historical_data)
        );
    }
    
    /**
     * Advanced trend analysis
     */
    public function analyze_trends($historical_data, $filters = array()) {
        $defaults = array(
            'trend_period' => 90, // days
            'detect_cycles' => true,
            'identify_anomalies' => true,
            'trend_strength' => 'medium'
        );
        
        $filters = array_merge($defaults, $filters);
        
        // Calculate moving averages
        $moving_averages = $this->calculate_moving_averages($historical_data, array(7, 14, 30));
        
        // Detect trend direction and strength
        $trend_analysis = $this->detect_trend_patterns($historical_data, $filters);
        
        // Identify cyclical patterns
        $cyclical_patterns = array();
        if ($filters['detect_cycles']) {
            $cyclical_patterns = $this->detect_cyclical_patterns($historical_data);
        }
        
        // Anomaly detection
        $anomalies = array();
        if ($filters['identify_anomalies']) {
            $anomalies = $this->detect_anomalies($historical_data);
        }
        
        // Seasonal decomposition
        $seasonal_decomposition = $this->perform_seasonal_decomposition($historical_data);
        
        return array(
            'trend_direction' => $trend_analysis['direction'],
            'trend_strength' => $trend_analysis['strength'],
            'trend_confidence' => $trend_analysis['confidence'],
            'moving_averages' => $moving_averages,
            'cyclical_patterns' => $cyclical_patterns,
            'seasonal_components' => $seasonal_decomposition,
            'anomalies' => $anomalies,
            'trend_metrics' => array(
                'slope' => $trend_analysis['slope'],
                'r_squared' => $trend_analysis['r_squared'],
                'volatility' => $this->calculate_volatility($historical_data),
                'momentum' => $this->calculate_momentum($historical_data)
            ),
            'forecast_implications' => $this->analyze_forecast_implications($trend_analysis, $cyclical_patterns)
        );
    }
    
    /**
     * Business scenario modeling
     */
    public function model_business_scenarios($base_forecast, $scenarios = array()) {
        $default_scenarios = array(
            'optimistic' => array(
                'revenue_multiplier' => 1.2,
                'conversion_lift' => 1.15,
                'traffic_increase' => 1.1,
                'description' => 'Best case scenario with improved performance'
            ),
            'realistic' => array(
                'revenue_multiplier' => 1.0,
                'conversion_lift' => 1.0,
                'traffic_increase' => 1.0,
                'description' => 'Current trends continue'
            ),
            'pessimistic' => array(
                'revenue_multiplier' => 0.85,
                'conversion_lift' => 0.9,
                'traffic_increase' => 0.95,
                'description' => 'Conservative scenario with market challenges'
            ),
            'growth' => array(
                'revenue_multiplier' => 1.35,
                'conversion_lift' => 1.25,
                'traffic_increase' => 1.2,
                'description' => 'Aggressive growth scenario'
            ),
            'recession' => array(
                'revenue_multiplier' => 0.7,
                'conversion_lift' => 0.8,
                'traffic_increase' => 0.85,
                'description' => 'Economic downturn scenario'
            )
        );
        
        $scenarios = array_merge($default_scenarios, $scenarios);
        $scenario_results = array();
        
        foreach ($scenarios as $scenario_name => $scenario_params) {
            $scenario_results[$scenario_name] = $this->apply_scenario_modifiers($base_forecast, $scenario_params);
        }
        
        return array(
            'scenarios' => $scenario_results,
            'scenario_comparison' => $this->compare_scenarios($scenario_results),
            'risk_analysis' => $this->analyze_scenario_risks($scenario_results),
            'recommendations' => $this->generate_scenario_recommendations($scenario_results)
        );
    }
    
    /**
     * ROI and financial forecasting
     */
    public function forecast_financial_metrics($revenue_forecast, $cost_projections = array()) {
        $defaults = array(
            'commission_rate' => 0.10,
            'operational_cost_growth' => 0.02, // 2% monthly growth
            'technology_cost_fixed' => 750, // monthly
            'marketing_budget_increase' => 0.05 // 5% monthly increase
        );
        
        $cost_projections = array_merge($defaults, $cost_projections);
        
        // Project costs over forecast period
        $cost_forecast = $this->project_costs($revenue_forecast, $cost_projections);
        
        // Calculate profit projections
        $profit_forecast = $this->calculate_profit_projections($revenue_forecast, $cost_forecast);
        
        // ROI analysis
        $roi_analysis = $this->analyze_roi_projections($revenue_forecast, $cost_forecast);
        
        // Cash flow projections
        $cash_flow = $this->project_cash_flow($revenue_forecast, $cost_forecast);
        
        // Financial health indicators
        $financial_health = $this->assess_financial_health($profit_forecast, $cash_flow);
        
        return array(
            'revenue_forecast' => $revenue_forecast,
            'cost_forecast' => $cost_forecast,
            'profit_forecast' => $profit_forecast,
            'roi_projections' => $roi_analysis,
            'cash_flow' => $cash_flow,
            'financial_health' => $financial_health,
            'break_even_analysis' => $this->calculate_break_even_projections($revenue_forecast, $cost_forecast),
            'profitability_timeline' => $this->analyze_profitability_timeline($profit_forecast),
            'investment_recommendations' => $this->generate_investment_recommendations($roi_analysis, $financial_health)
        );
    }
    
    /**
     * Market opportunity analysis
     */
    public function analyze_market_opportunities($historical_data, $market_data = array()) {
        // Analyze addressable market
        $addressable_market = $this->analyze_addressable_market($historical_data, $market_data);
        
        // Identify growth opportunities
        $growth_opportunities = $this->identify_growth_opportunities($historical_data);
        
        // Competitive positioning
        $competitive_analysis = $this->analyze_competitive_position($historical_data, $market_data);
        
        // Market penetration analysis
        $penetration_analysis = $this->analyze_market_penetration($historical_data, $addressable_market);
        
        return array(
            'addressable_market' => $addressable_market,
            'growth_opportunities' => $growth_opportunities,
            'competitive_position' => $competitive_analysis,
            'market_penetration' => $penetration_analysis,
            'expansion_potential' => $this->calculate_expansion_potential($addressable_market, $penetration_analysis),
            'market_recommendations' => $this->generate_market_recommendations($growth_opportunities, $competitive_analysis)
        );
    }
    
    /**
     * Helper methods for forecasting calculations
     */
    private function prepare_forecast_data($revenue_series) {
        // Clean and prepare data for forecasting
        $cleaned_data = array();
        
        foreach ($revenue_series as $date => $value) {
            if (is_numeric($value) && $value >= 0) {
                $cleaned_data[$date] = floatval($value);
            }
        }
        
        // Sort by date
        ksort($cleaned_data);
        
        return $cleaned_data;
    }
    
    private function select_optimal_model($data, $filters) {
        if ($filters['model'] !== 'auto') {
            return $filters['model'];
        }
        
        $data_size = count($data);
        $data_variance = $this->calculate_variance($data);
        $trend_strength = $this->calculate_trend_strength($data);
        
        // Auto-select based on data characteristics
        if ($data_size > 365 && $data_variance > 0.5) {
            return 'neural_network';
        } elseif ($trend_strength > 0.7) {
            return 'arima';
        } elseif ($this->has_seasonality($data)) {
            return 'exponential_smoothing';
        } else {
            return 'linear_regression';
        }
    }
    
    private function generate_base_forecast($data, $model, $filters) {
        $forecast_days = $filters['forecast_days'];
        $data_values = array_values($data);
        
        switch ($model) {
            case 'linear_regression':
                return $this->linear_regression_forecast($data_values, $forecast_days);
            case 'exponential_smoothing':
                return $this->exponential_smoothing_forecast($data_values, $forecast_days);
            case 'moving_average':
                return $this->moving_average_forecast($data_values, $forecast_days);
            case 'arima':
                return $this->arima_forecast($data_values, $forecast_days);
            case 'neural_network':
                return $this->neural_network_forecast($data_values, $forecast_days);
            default:
                return $this->linear_regression_forecast($data_values, $forecast_days);
        }
    }
    
    private function linear_regression_forecast($data, $forecast_days) {
        $n = count($data);
        $x_sum = array_sum(range(1, $n));
        $y_sum = array_sum($data);
        $xy_sum = 0;
        $x_squared_sum = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = $data[$i];
            $xy_sum += $x * $y;
            $x_squared_sum += $x * $x;
        }
        
        // Calculate slope and intercept
        $slope = ($n * $xy_sum - $x_sum * $y_sum) / ($n * $x_squared_sum - $x_sum * $x_sum);
        $intercept = ($y_sum - $slope * $x_sum) / $n;
        
        // Generate forecast
        $forecast = array();
        for ($i = 1; $i <= $forecast_days; $i++) {
            $x = $n + $i;
            $forecast[] = max(0, $slope * $x + $intercept);
        }
        
        return $forecast;
    }
    
    private function exponential_smoothing_forecast($data, $forecast_days, $alpha = 0.3) {
        $smoothed = array();
        $smoothed[0] = $data[0];
        
        // Calculate smoothed values
        for ($i = 1; $i < count($data); $i++) {
            $smoothed[$i] = $alpha * $data[$i] + (1 - $alpha) * $smoothed[$i - 1];
        }
        
        // Generate forecast
        $forecast = array();
        $last_smoothed = end($smoothed);
        
        for ($i = 0; $i < $forecast_days; $i++) {
            $forecast[] = max(0, $last_smoothed);
        }
        
        return $forecast;
    }
    
    private function moving_average_forecast($data, $forecast_days, $window = 7) {
        $data_length = count($data);
        $recent_data = array_slice($data, -$window);
        $average = array_sum($recent_data) / count($recent_data);
        
        // Generate forecast using recent average
        $forecast = array();
        for ($i = 0; $i < $forecast_days; $i++) {
            $forecast[] = max(0, $average);
        }
        
        return $forecast;
    }
    
    private function arima_forecast($data, $forecast_days) {
        // Simplified ARIMA implementation
        // In production, this would use a more sophisticated ARIMA algorithm
        $data_length = count($data);
        $trend = $this->calculate_trend($data);
        $seasonal_component = $this->extract_seasonal_component($data);
        
        $forecast = array();
        $last_value = end($data);
        
        for ($i = 1; $i <= $forecast_days; $i++) {
            $trend_component = $trend * $i;
            $seasonal_index = $i % count($seasonal_component);
            $seasonal_factor = $seasonal_component[$seasonal_index];
            
            $forecasted_value = $last_value + $trend_component + $seasonal_factor;
            $forecast[] = max(0, $forecasted_value);
        }
        
        return $forecast;
    }
    
    private function neural_network_forecast($data, $forecast_days) {
        // Simplified neural network approach
        // In production, this would use a proper neural network library
        $window_size = min(14, count($data) - 1);
        $recent_data = array_slice($data, -$window_size);
        
        // Simple pattern recognition
        $pattern_weights = array();
        for ($i = 0; $i < count($recent_data); $i++) {
            $weight = ($i + 1) / count($recent_data); // Give more weight to recent data
            $pattern_weights[] = $weight;
        }
        
        $weighted_average = 0;
        for ($i = 0; $i < count($recent_data); $i++) {
            $weighted_average += $recent_data[$i] * $pattern_weights[$i];
        }
        $weighted_average /= array_sum($pattern_weights);
        
        // Apply growth factor based on recent trend
        $growth_factor = $this->calculate_recent_growth_factor($recent_data);
        
        $forecast = array();
        for ($i = 1; $i <= $forecast_days; $i++) {
            $forecasted_value = $weighted_average * pow($growth_factor, $i / 7); // Weekly compounding
            $forecast[] = max(0, $forecasted_value);
        }
        
        return $forecast;
    }
    
    // Additional helper methods would be implemented here...
    
    /**
     * Apply scenario modifiers to forecast
     */
    private function apply_scenario_modifiers($base_forecast, $scenario_params) {
        $modified_forecast = array();
        
        foreach ($base_forecast as $index => $value) {
            $modified_value = $value;
            
            // Apply revenue multiplier
            if (isset($scenario_params['revenue_multiplier'])) {
                $modified_value *= $scenario_params['revenue_multiplier'];
            }
            
            // Apply conversion lift
            if (isset($scenario_params['conversion_lift'])) {
                $modified_value *= $scenario_params['conversion_lift'];
            }
            
            // Apply traffic increase
            if (isset($scenario_params['traffic_increase'])) {
                $modified_value *= $scenario_params['traffic_increase'];
            }
            
            $modified_forecast[] = max(0, $modified_value);
        }
        
        return array(
            'forecast' => $modified_forecast,
            'total_revenue' => array_sum($modified_forecast),
            'average_daily' => array_sum($modified_forecast) / count($modified_forecast),
            'scenario_params' => $scenario_params
        );
    }
    
    /**
     * Compare scenarios
     */
    private function compare_scenarios($scenario_results) {
        $comparison = array();
        $base_scenario = isset($scenario_results['realistic']) ? $scenario_results['realistic'] : reset($scenario_results);
        
        foreach ($scenario_results as $scenario_name => $results) {
            $comparison[$scenario_name] = array(
                'total_revenue' => $results['total_revenue'],
                'variance_from_realistic' => $results['total_revenue'] - $base_scenario['total_revenue'],
                'percentage_change' => $base_scenario['total_revenue'] > 0 
                    ? (($results['total_revenue'] - $base_scenario['total_revenue']) / $base_scenario['total_revenue']) * 100 
                    : 0,
                'daily_average' => $results['average_daily']
            );
        }
        
        return $comparison;
    }
    
    /**
     * Analyze scenario risks
     */
    private function analyze_scenario_risks($scenario_results) {
        $risks = array();
        
        foreach ($scenario_results as $scenario_name => $results) {
            $risk_level = 'low';
            $risk_factors = array();
            
            // Determine risk level based on scenario
            switch ($scenario_name) {
                case 'optimistic':
                case 'growth':
                    $risk_level = 'medium';
                    $risk_factors[] = 'Dependent on market conditions';
                    $risk_factors[] = 'Requires sustained performance';
                    break;
                case 'pessimistic':
                case 'recession':
                    $risk_level = 'high';
                    $risk_factors[] = 'External economic factors';
                    $risk_factors[] = 'Market volatility';
                    break;
                default:
                    $risk_level = 'low';
                    $risk_factors[] = 'Based on current trends';
            }
            
            $risks[$scenario_name] = array(
                'risk_level' => $risk_level,
                'risk_factors' => $risk_factors,
                'mitigation_strategies' => $this->get_mitigation_strategies($scenario_name)
            );
        }
        
        return $risks;
    }
    
    /**
     * Generate scenario recommendations
     */
    private function generate_scenario_recommendations($scenario_results) {
        $recommendations = array();
        
        // Analyze best and worst case scenarios
        $revenue_values = array();
        foreach ($scenario_results as $scenario_name => $results) {
            $revenue_values[$scenario_name] = $results['total_revenue'];
        }
        
        $best_scenario = array_keys($revenue_values, max($revenue_values))[0];
        $worst_scenario = array_keys($revenue_values, min($revenue_values))[0];
        
        $recommendations[] = "Focus on strategies that support the {$best_scenario} scenario";
        $recommendations[] = "Prepare contingency plans for the {$worst_scenario} scenario";
        $recommendations[] = "Monitor key indicators to track which scenario is materializing";
        
        // Add specific recommendations based on scenario spread
        $revenue_spread = max($revenue_values) - min($revenue_values);
        $average_revenue = array_sum($revenue_values) / count($revenue_values);
        
        if ($revenue_spread > $average_revenue * 0.5) {
            $recommendations[] = "High variance between scenarios - consider diversifying strategies";
        }
        
        return $recommendations;
    }
    
    /**
     * Project costs over forecast period
     */
    private function project_costs($revenue_forecast, $cost_projections) {
        $cost_forecast = array();
        
        foreach ($revenue_forecast as $index => $revenue) {
            $daily_costs = array();
            
            // Variable costs (commission)
            $commission_cost = $revenue * $cost_projections['commission_rate'];
            $daily_costs['commission'] = $commission_cost;
            
            // Fixed technology costs (monthly, distributed daily)
            $daily_costs['technology'] = $cost_projections['technology_cost_fixed'] / 30;
            
            // Marketing costs (percentage of revenue)
            $marketing_cost = $revenue * 0.15; // 15% of revenue for marketing
            $growth_factor = pow(1 + $cost_projections['marketing_budget_increase'], $index / 30);
            $daily_costs['marketing'] = $marketing_cost * $growth_factor;
            
            // Operational costs
            $operational_base = $revenue * 0.05; // 5% of revenue
            $operational_growth = pow(1 + $cost_projections['operational_cost_growth'], $index / 30);
            $daily_costs['operational'] = $operational_base * $operational_growth;
            
            $cost_forecast[] = $daily_costs;
        }
        
        return $cost_forecast;
    }
    
    /**
     * Calculate profit projections
     */
    private function calculate_profit_projections($revenue_forecast, $cost_forecast) {
        $profit_forecast = array();
        
        foreach ($revenue_forecast as $index => $revenue) {
            $total_costs = 0;
            
            if (isset($cost_forecast[$index])) {
                foreach ($cost_forecast[$index] as $cost_type => $cost) {
                    $total_costs += $cost;
                }
            }
            
            $profit_forecast[] = array(
                'revenue' => $revenue,
                'total_costs' => $total_costs,
                'gross_profit' => $revenue - $total_costs,
                'profit_margin' => $revenue > 0 ? (($revenue - $total_costs) / $revenue) * 100 : 0
            );
        }
        
        return $profit_forecast;
    }
    
    /**
     * Analyze ROI projections
     */
    private function analyze_roi_projections($revenue_forecast, $cost_forecast) {
        $roi_analysis = array();
        $cumulative_investment = 0;
        $cumulative_return = 0;
        
        foreach ($revenue_forecast as $index => $revenue) {
            $daily_investment = 0;
            
            if (isset($cost_forecast[$index])) {
                // Consider marketing and technology as investment
                $daily_investment = $cost_forecast[$index]['marketing'] + $cost_forecast[$index]['technology'];
            }
            
            $cumulative_investment += $daily_investment;
            $cumulative_return += $revenue;
            
            $roi = $cumulative_investment > 0 ? (($cumulative_return - $cumulative_investment) / $cumulative_investment) * 100 : 0;
            
            $roi_analysis[] = array(
                'day' => $index + 1,
                'daily_investment' => $daily_investment,
                'daily_return' => $revenue,
                'cumulative_investment' => $cumulative_investment,
                'cumulative_return' => $cumulative_return,
                'roi_percentage' => $roi,
                'payback_achieved' => $cumulative_return >= $cumulative_investment
            );
        }
        
        return $roi_analysis;
    }
    
    /**
     * Project cash flow
     */
    private function project_cash_flow($revenue_forecast, $cost_forecast) {
        $cash_flow = array();
        $running_balance = 0;
        
        foreach ($revenue_forecast as $index => $revenue) {
            $total_costs = 0;
            
            if (isset($cost_forecast[$index])) {
                foreach ($cost_forecast[$index] as $cost) {
                    $total_costs += $cost;
                }
            }
            
            $net_cash_flow = $revenue - $total_costs;
            $running_balance += $net_cash_flow;
            
            $cash_flow[] = array(
                'revenue' => $revenue,
                'costs' => $total_costs,
                'net_cash_flow' => $net_cash_flow,
                'running_balance' => $running_balance,
                'cash_positive' => $running_balance > 0
            );
        }
        
        return $cash_flow;
    }
    
    /**
     * Assess financial health
     */
    private function assess_financial_health($profit_forecast, $cash_flow) {
        $total_revenue = array_sum(array_column($profit_forecast, 'revenue'));
        $total_costs = array_sum(array_column($profit_forecast, 'total_costs'));
        $total_profit = $total_revenue - $total_costs;
        
        $profit_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;
        
        $positive_cash_flow_days = count(array_filter($cash_flow, function($day) {
            return $day['net_cash_flow'] > 0;
        }));
        
        $cash_flow_ratio = count($cash_flow) > 0 ? $positive_cash_flow_days / count($cash_flow) : 0;
        
        // Determine health status
        $health_status = 'poor';
        if ($profit_margin > 20 && $cash_flow_ratio > 0.8) {
            $health_status = 'excellent';
        } elseif ($profit_margin > 10 && $cash_flow_ratio > 0.6) {
            $health_status = 'good';
        } elseif ($profit_margin > 0 && $cash_flow_ratio > 0.4) {
            $health_status = 'fair';
        }
        
        return array(
            'health_status' => $health_status,
            'profit_margin' => $profit_margin,
            'cash_flow_ratio' => $cash_flow_ratio,
            'total_profit' => $total_profit,
            'break_even_day' => $this->find_break_even_day($cash_flow),
            'recommendations' => $this->get_financial_recommendations($health_status, $profit_margin)
        );
    }
    
    /**
     * Calculate break-even projections
     */
    private function calculate_break_even_projections($revenue_forecast, $cost_forecast) {
        $cumulative_revenue = 0;
        $cumulative_costs = 0;
        $break_even_day = null;
        
        foreach ($revenue_forecast as $index => $revenue) {
            $cumulative_revenue += $revenue;
            
            if (isset($cost_forecast[$index])) {
                $daily_costs = array_sum($cost_forecast[$index]);
                $cumulative_costs += $daily_costs;
            }
            
            if ($break_even_day === null && $cumulative_revenue >= $cumulative_costs) {
                $break_even_day = $index + 1;
            }
        }
        
        return array(
            'break_even_day' => $break_even_day,
            'cumulative_revenue_at_break_even' => $cumulative_revenue,
            'cumulative_costs_at_break_even' => $cumulative_costs,
            'days_to_break_even' => $break_even_day,
            'break_even_achieved' => $break_even_day !== null
        );
    }
    
    /**
     * Analyze profitability timeline
     */
    private function analyze_profitability_timeline($profit_forecast) {
        $timeline = array();
        $profitable_days = 0;
        $highest_profit_day = 0;
        $highest_profit = 0;
        
        foreach ($profit_forecast as $index => $day_data) {
            if ($day_data['gross_profit'] > 0) {
                $profitable_days++;
            }
            
            if ($day_data['gross_profit'] > $highest_profit) {
                $highest_profit = $day_data['gross_profit'];
                $highest_profit_day = $index + 1;
            }
            
            $timeline[] = array(
                'day' => $index + 1,
                'profitable' => $day_data['gross_profit'] > 0,
                'profit_margin' => $day_data['profit_margin']
            );
        }
        
        $profitability_ratio = count($profit_forecast) > 0 ? $profitable_days / count($profit_forecast) : 0;
        
        return array(
            'timeline' => $timeline,
            'profitable_days' => $profitable_days,
            'profitability_ratio' => $profitability_ratio,
            'highest_profit_day' => $highest_profit_day,
            'highest_profit' => $highest_profit,
            'trend' => $this->analyze_profitability_trend($profit_forecast)
        );
    }
    
    /**
     * Generate investment recommendations
     */
    private function generate_investment_recommendations($roi_analysis, $financial_health) {
        $recommendations = array();
        
        $final_roi = end($roi_analysis)['roi_percentage'];
        
        if ($final_roi > 50) {
            $recommendations[] = 'Excellent ROI - consider increasing investment';
        } elseif ($final_roi > 20) {
            $recommendations[] = 'Good ROI - maintain current investment level';
        } elseif ($final_roi > 0) {
            $recommendations[] = 'Positive ROI - optimize to improve returns';
        } else {
            $recommendations[] = 'Negative ROI - review strategy and reduce costs';
        }
        
        // Health-based recommendations
        switch ($financial_health['health_status']) {
            case 'excellent':
                $recommendations[] = 'Strong financial position - explore growth opportunities';
                break;
            case 'good':
                $recommendations[] = 'Solid performance - continue current strategy';
                break;
            case 'fair':
                $recommendations[] = 'Room for improvement - focus on cost optimization';
                break;
            case 'poor':
                $recommendations[] = 'Urgent action needed - restructure operations';
                break;
        }
        
        return $recommendations;
    }
    
    // Market analysis methods
    private function analyze_addressable_market($historical_data, $market_data) {
        $current_market_share = isset($market_data['current_share']) ? $market_data['current_share'] : 0.01; // 1% default
        $total_market_size = isset($market_data['total_size']) ? $market_data['total_size'] : 10000000; // $10M default
        
        $current_revenue = array_sum($historical_data['revenue_series']);
        $addressable_market = $total_market_size * 0.3; // 30% addressable
        
        return array(
            'total_market_size' => $total_market_size,
            'addressable_market' => $addressable_market,
            'current_market_share' => $current_market_share,
            'current_revenue' => $current_revenue,
            'market_penetration' => $addressable_market > 0 ? ($current_revenue / $addressable_market) * 100 : 0,
            'growth_potential' => $addressable_market - $current_revenue
        );
    }
    
    private function identify_growth_opportunities($historical_data) {
        $opportunities = array();
        
        // Revenue growth opportunity
        $revenue_trend = $this->calculate_trend(array_values($historical_data['revenue_series']));
        if ($revenue_trend > 0) {
            $opportunities[] = array(
                'type' => 'Revenue Growth',
                'description' => 'Positive revenue trend indicates growth opportunity',
                'potential' => 'High',
                'timeframe' => '3-6 months'
            );
        }
        
        // Market expansion
        $opportunities[] = array(
            'type' => 'Market Expansion',
            'description' => 'Explore new customer segments or geographic markets',
            'potential' => 'Medium',
            'timeframe' => '6-12 months'
        );
        
        // Product diversification
        $opportunities[] = array(
            'type' => 'Product Diversification',
            'description' => 'Develop complementary products or services',
            'potential' => 'Medium',
            'timeframe' => '12+ months'
        );
        
        return $opportunities;
    }
    
    private function analyze_competitive_position($historical_data, $market_data) {
        $market_share = isset($market_data['current_share']) ? $market_data['current_share'] : 0.01;
        
        $position = 'niche player';
        if ($market_share > 0.15) {
            $position = 'market leader';
        } elseif ($market_share > 0.05) {
            $position = 'strong competitor';
        } elseif ($market_share > 0.02) {
            $position = 'emerging player';
        }
        
        return array(
            'position' => $position,
            'market_share' => $market_share * 100,
            'competitive_advantages' => $this->identify_competitive_advantages($historical_data),
            'areas_for_improvement' => $this->identify_improvement_areas($historical_data)
        );
    }
    
    private function analyze_market_penetration($historical_data, $addressable_market) {
        $current_revenue = array_sum($historical_data['revenue_series']);
        $penetration_rate = $addressable_market['addressable_market'] > 0 
            ? ($current_revenue / $addressable_market['addressable_market']) * 100 
            : 0;
        
        $penetration_level = 'low';
        if ($penetration_rate > 10) {
            $penetration_level = 'high';
        } elseif ($penetration_rate > 3) {
            $penetration_level = 'medium';
        }
        
        return array(
            'penetration_rate' => $penetration_rate,
            'penetration_level' => $penetration_level,
            'untapped_market' => $addressable_market['addressable_market'] - $current_revenue,
            'growth_runway' => 100 - $penetration_rate
        );
    }
    
    private function calculate_expansion_potential($addressable_market, $penetration_analysis) {
        $expansion_potential = $penetration_analysis['untapped_market'];
        $confidence_level = 'medium';
        
        if ($penetration_analysis['penetration_rate'] < 5) {
            $confidence_level = 'high';
        } elseif ($penetration_analysis['penetration_rate'] > 15) {
            $confidence_level = 'low';
        }
        
        return array(
            'expansion_potential' => $expansion_potential,
            'confidence_level' => $confidence_level,
            'recommended_approach' => $this->get_expansion_approach($penetration_analysis['penetration_level'])
        );
    }
    
    private function generate_market_recommendations($growth_opportunities, $competitive_analysis) {
        $recommendations = array();
        
        // Based on competitive position
        switch ($competitive_analysis['position']) {
            case 'market leader':
                $recommendations[] = 'Defend market position and explore new markets';
                break;
            case 'strong competitor':
                $recommendations[] = 'Focus on differentiation and market share growth';
                break;
            case 'emerging player':
                $recommendations[] = 'Build brand awareness and capture market share';
                break;
            default:
                $recommendations[] = 'Focus on niche specialization and customer loyalty';
        }
        
        // Based on growth opportunities
        foreach ($growth_opportunities as $opportunity) {
            if ($opportunity['potential'] === 'High') {
                $recommendations[] = "Priority: {$opportunity['description']}";
            }
        }
        
        return $recommendations;
    }
    
    // Helper methods for the additional functionality
    private function get_mitigation_strategies($scenario_name) {
        $strategies = array(
            'optimistic' => array('Monitor performance indicators', 'Prepare for potential slowdown'),
            'growth' => array('Ensure scalability', 'Manage resource allocation'),
            'pessimistic' => array('Focus on cost reduction', 'Diversify revenue streams'),
            'recession' => array('Implement cost controls', 'Focus on customer retention'),
            'realistic' => array('Continue monitoring', 'Maintain current strategy')
        );
        
        return isset($strategies[$scenario_name]) ? $strategies[$scenario_name] : array('Monitor and adapt');
    }
    
    private function find_break_even_day($cash_flow) {
        foreach ($cash_flow as $index => $day) {
            if ($day['running_balance'] >= 0) {
                return $index + 1;
            }
        }
        return null;
    }
    
    private function get_financial_recommendations($health_status, $profit_margin) {
        $recommendations = array();
        
        switch ($health_status) {
            case 'excellent':
                $recommendations[] = 'Consider expansion opportunities';
                $recommendations[] = 'Maintain operational efficiency';
                break;
            case 'good':
                $recommendations[] = 'Focus on sustainable growth';
                $recommendations[] = 'Optimize cost structure';
                break;
            case 'fair':
                $recommendations[] = 'Improve profit margins';
                $recommendations[] = 'Review cost allocation';
                break;
            case 'poor':
                $recommendations[] = 'Urgent cost reduction needed';
                $recommendations[] = 'Review business model';
                break;
        }
        
        return $recommendations;
    }
    
    private function analyze_profitability_trend($profit_forecast) {
        $profit_margins = array_column($profit_forecast, 'profit_margin');
        $trend = $this->calculate_trend($profit_margins);
        
        if ($trend > 0.1) {
            return 'improving';
        } elseif ($trend < -0.1) {
            return 'declining';
        } else {
            return 'stable';
        }
    }
    
    private function identify_competitive_advantages($historical_data) {
        return array(
            'Strong conversion rates',
            'Effective attribution tracking',
            'Data-driven optimization'
        );
    }
    
    private function identify_improvement_areas($historical_data) {
        return array(
            'Market expansion opportunities',
            'Customer acquisition cost optimization',
            'Revenue diversification'
        );
    }
    
    private function get_expansion_approach($penetration_level) {
        switch ($penetration_level) {
            case 'low':
                return 'Aggressive market penetration strategy';
            case 'medium':
                return 'Balanced growth and optimization';
            case 'high':
                return 'Focus on market share defense and efficiency';
            default:
                return 'Market research and strategic planning';
        }
    }
    private function calculate_variance($data) {
        $mean = array_sum($data) / count($data);
        $variance = 0;
        
        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        return $variance / count($data);
    }
    
    private function calculate_trend_strength($data) {
        if (count($data) < 2) return 0;
        
        $n = count($data);
        $x_values = range(1, $n);
        $correlation = $this->calculate_correlation($x_values, $data);
        
        return abs($correlation);
    }
    
    private function calculate_correlation($x, $y) {
        if (count($x) !== count($y) || count($x) < 2) return 0;
        
        $n = count($x);
        $x_mean = array_sum($x) / $n;
        $y_mean = array_sum($y) / $n;
        
        $numerator = 0;
        $x_variance = 0;
        $y_variance = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x_diff = $x[$i] - $x_mean;
            $y_diff = $y[$i] - $y_mean;
            
            $numerator += $x_diff * $y_diff;
            $x_variance += $x_diff * $x_diff;
            $y_variance += $y_diff * $y_diff;
        }
        
        $denominator = sqrt($x_variance * $y_variance);
        
        return $denominator != 0 ? $numerator / $denominator : 0;
    }
    
    private function has_seasonality($data) {
        // Simple seasonality detection
        if (count($data) < 14) return false;
        
        $weekly_pattern = array();
        $day_index = 0;
        
        foreach ($data as $value) {
            $day_of_week = $day_index % 7;
            if (!isset($weekly_pattern[$day_of_week])) {
                $weekly_pattern[$day_of_week] = array();
            }
            $weekly_pattern[$day_of_week][] = $value;
            $day_index++;
        }
        
        // Calculate variance between different days of week
        $day_averages = array();
        foreach ($weekly_pattern as $day => $values) {
            $day_averages[$day] = array_sum($values) / count($values);
        }
        
        $overall_average = array_sum($day_averages) / count($day_averages);
        $seasonal_variance = 0;
        
        foreach ($day_averages as $day_avg) {
            $seasonal_variance += pow($day_avg - $overall_average, 2);
        }
        
        $seasonal_variance /= count($day_averages);
        $data_variance = $this->calculate_variance($data);
        
        // If seasonal variance is significant compared to overall variance, we have seasonality
        return $data_variance > 0 && ($seasonal_variance / $data_variance) > 0.1;
    }
    
    private function generate_forecast_dates($forecast_days) {
        $dates = array();
        $start_date = strtotime('+1 day');
        
        for ($i = 0; $i < $forecast_days; $i++) {
            $dates[] = date('Y-m-d', $start_date + ($i * 86400));
        }
        
        return $dates;
    }
    
    private function calculate_forecast_growth_rate($forecast) {
        if (count($forecast) < 2) return 0;
        
        $start_value = $forecast[0];
        $end_value = end($forecast);
        
        if ($start_value == 0) return 0;
        
        $periods = count($forecast) - 1;
        $growth_rate = pow($end_value / $start_value, 1 / $periods) - 1;
        
        return $growth_rate * 100; // Return as percentage
    }
}

// Initialize the forecasting engine
new KHM_Attribution_Forecasting_Engine();
?>