<?php
/**
 * KHM Attribution Predictive Analytics
 * 
 * Advanced predictive analytics for attribution forecasting using Phase 2 OOP patterns.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Predictive_Analytics {
    
    private $performance_manager;
    private $database_manager;
    private $ml_attribution_engine;
    private $prediction_config = array();
    private $forecasting_models = array();
    private $prediction_cache = array();
    
    /**
     * Constructor - Initialize predictive analytics components
     */
    public function __construct() {
        $this->init_predictive_components();
        $this->setup_prediction_config();
        $this->load_forecasting_models();
        $this->register_prediction_hooks();
    }
    
    /**
     * Initialize predictive components
     */
    private function init_predictive_components() {
        if (file_exists(dirname(__FILE__) . '/PerformanceManager.php')) {
            require_once dirname(__FILE__) . '/PerformanceManager.php';
            $this->performance_manager = new KHM_Attribution_Performance_Manager();
        }
        
        if (file_exists(dirname(__FILE__) . '/DatabaseManager.php')) {
            require_once dirname(__FILE__) . '/DatabaseManager.php';
            $this->database_manager = new KHM_Attribution_Database_Manager();
        }
        
        if (file_exists(dirname(__FILE__) . '/MLAttributionEngine.php')) {
            require_once dirname(__FILE__) . '/MLAttributionEngine.php';
            $this->ml_attribution_engine = new KHM_Attribution_ML_Attribution_Engine();
        }
    }
    
    /**
     * Setup prediction configuration
     */
    private function setup_prediction_config() {
        $this->prediction_config = array(
            'forecast_horizons' => array(7, 14, 30, 60, 90),
            'confidence_intervals' => array(80, 90, 95),
            'seasonality_detection' => true,
            'trend_analysis' => true,
            'anomaly_detection' => true,
            'prediction_models' => array('arima', 'prophet', 'linear_regression', 'lstm'),
            'min_historical_data_points' => 30,
            'update_frequency' => 'daily',
            'cache_duration' => 3600
        );
        
        $this->prediction_config = apply_filters('khm_predictive_config', $this->prediction_config);
    }
    
    /**
     * Load forecasting models
     */
    private function load_forecasting_models() {
        $this->forecasting_models = array(
            'revenue_forecasting' => array(
                'name' => 'Revenue Forecasting',
                'target_metric' => 'revenue',
                'features' => array('historical_revenue', 'seasonality', 'trends', 'external_factors'),
                'models' => array('arima', 'prophet', 'linear_regression')
            ),
            'conversion_prediction' => array(
                'name' => 'Conversion Prediction',
                'target_metric' => 'conversions',
                'features' => array('traffic_patterns', 'user_behavior', 'channel_performance', 'time_factors'),
                'models' => array('logistic_regression', 'random_forest', 'neural_network')
            ),
            'ltv_forecasting' => array(
                'name' => 'Customer LTV Forecasting',
                'target_metric' => 'customer_ltv',
                'features' => array('purchase_history', 'engagement_metrics', 'demographic_data', 'behavioral_patterns'),
                'models' => array('survival_analysis', 'clustering', 'regression')
            ),
            'churn_prediction' => array(
                'name' => 'Customer Churn Prediction',
                'target_metric' => 'churn_probability',
                'features' => array('engagement_decline', 'purchase_frequency', 'support_interactions', 'usage_patterns'),
                'models' => array('gradient_boosting', 'neural_network', 'ensemble')
            ),
            'channel_optimization' => array(
                'name' => 'Channel Performance Optimization',
                'target_metric' => 'channel_roi',
                'features' => array('spend_allocation', 'market_conditions', 'competitor_activity', 'seasonal_factors'),
                'models' => array('multi_objective_optimization', 'reinforcement_learning')
            )
        );
    }
    
    /**
     * Register prediction hooks
     */
    private function register_prediction_hooks() {
        add_action('khm_update_predictions', array($this, 'update_all_predictions'));
        add_action('admin_menu', array($this, 'add_prediction_menu'));
        
        // AJAX hooks
        add_action('wp_ajax_khm_generate_forecast', array($this, 'ajax_generate_forecast'));
        add_action('wp_ajax_khm_predict_conversions', array($this, 'ajax_predict_conversions'));
        add_action('wp_ajax_khm_analyze_trends', array($this, 'ajax_analyze_trends'));
        
        // Scheduled updates
        if (!wp_next_scheduled('khm_update_predictions')) {
            wp_schedule_event(time(), 'daily', 'khm_update_predictions');
        }
    }
    
    /**
     * Generate comprehensive forecast
     */
    public function generate_forecast($forecast_type, $options = array()) {
        if (!isset($this->forecasting_models[$forecast_type])) {
            return false;
        }
        
        $model_config = $this->forecasting_models[$forecast_type];
        
        $default_options = array(
            'horizon_days' => 30,
            'confidence_level' => 95,
            'include_seasonality' => true,
            'include_trends' => true,
            'historical_periods' => 90
        );
        
        $options = array_merge($default_options, $options);
        
        // Check cache
        $cache_key = $this->generate_cache_key($forecast_type, $options);
        if (isset($this->prediction_cache[$cache_key])) {
            return $this->prediction_cache[$cache_key];
        }
        
        // Prepare historical data
        $historical_data = $this->prepare_historical_data($model_config, $options);
        
        if (count($historical_data) < $this->prediction_config['min_historical_data_points']) {
            return array('error' => 'Insufficient historical data');
        }
        
        // Generate predictions using multiple models
        $model_predictions = array();
        foreach ($model_config['models'] as $model_type) {
            $prediction = $this->generate_model_prediction($historical_data, $model_type, $options);
            $model_predictions[$model_type] = $prediction;
        }
        
        // Ensemble predictions
        $ensemble_prediction = $this->ensemble_predictions($model_predictions, $options);
        
        // Add confidence intervals
        $forecast_with_intervals = $this->calculate_confidence_intervals($ensemble_prediction, $options);
        
        // Detect anomalies and trends
        $analysis = array(
            'trends' => $this->analyze_trends($historical_data),
            'seasonality' => $this->detect_seasonality($historical_data),
            'anomalies' => $this->detect_anomalies($historical_data),
            'forecast_quality' => $this->assess_forecast_quality($model_predictions)
        );
        
        $forecast_result = array(
            'forecast_type' => $forecast_type,
            'target_metric' => $model_config['target_metric'],
            'options' => $options,
            'historical_data' => $historical_data,
            'model_predictions' => $model_predictions,
            'ensemble_forecast' => $forecast_with_intervals,
            'analysis' => $analysis,
            'metadata' => array(
                'generated_at' => current_time('mysql'),
                'forecast_horizon' => $options['horizon_days'],
                'historical_periods' => count($historical_data),
                'models_used' => count($model_predictions)
            )
        );
        
        // Cache result
        $this->prediction_cache[$cache_key] = $forecast_result;
        
        return $forecast_result;
    }
    
    /**
     * Prepare historical data for forecasting
     */
    private function prepare_historical_data($model_config, $options) {
        global $wpdb;
        
        $target_metric = $model_config['target_metric'];
        $historical_periods = $options['historical_periods'];
        
        switch ($target_metric) {
            case 'revenue':
                return $this->prepare_revenue_data($historical_periods);
                
            case 'conversions':
                return $this->prepare_conversion_data($historical_periods);
                
            case 'customer_ltv':
                return $this->prepare_ltv_data($historical_periods);
                
            case 'churn_probability':
                return $this->prepare_churn_data($historical_periods);
                
            case 'channel_roi':
                return $this->prepare_channel_roi_data($historical_periods);
                
            default:
                return array();
        }
    }
    
    /**
     * Prepare revenue data
     */
    private function prepare_revenue_data($periods) {
        global $wpdb;
        
        $conversions_table = $wpdb->prefix . 'khm_conversion_tracking';
        $start_date = date('Y-m-d', strtotime("-{$periods} days"));
        
        $sql = "SELECT 
                    DATE(created_at) as date,
                    SUM(value) as revenue,
                    COUNT(*) as conversions,
                    COUNT(DISTINCT user_id) as unique_customers
                FROM {$conversions_table}
                WHERE created_at >= %s
                AND status = 'attributed'
                GROUP BY DATE(created_at)
                ORDER BY date ASC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $start_date), ARRAY_A);
    }
    
    /**
     * Generate model prediction
     */
    private function generate_model_prediction($historical_data, $model_type, $options) {
        switch ($model_type) {
            case 'arima':
                return $this->generate_arima_prediction($historical_data, $options);
                
            case 'prophet':
                return $this->generate_prophet_prediction($historical_data, $options);
                
            case 'linear_regression':
                return $this->generate_linear_regression_prediction($historical_data, $options);
                
            case 'lstm':
                return $this->generate_lstm_prediction($historical_data, $options);
                
            case 'logistic_regression':
                return $this->generate_logistic_regression_prediction($historical_data, $options);
                
            case 'random_forest':
                return $this->generate_random_forest_prediction($historical_data, $options);
                
            case 'neural_network':
                return $this->generate_neural_network_prediction($historical_data, $options);
                
            default:
                return array('error' => 'Unknown model type: ' . $model_type);
        }
    }
    
    /**
     * Generate ARIMA prediction (simplified)
     */
    private function generate_arima_prediction($historical_data, $options) {
        $values = array_column($historical_data, 'revenue');
        $forecast_days = $options['horizon_days'];
        
        // Simplified ARIMA implementation
        // Calculate moving averages and trends
        $ma_period = min(7, count($values));
        $moving_averages = $this->calculate_moving_average($values, $ma_period);
        
        // Simple trend calculation
        $recent_values = array_slice($values, -14); // Last 14 days
        $trend = $this->calculate_linear_trend($recent_values);
        
        // Generate forecast
        $forecast = array();
        $last_value = end($values);
        
        for ($i = 1; $i <= $forecast_days; $i++) {
            $predicted_value = $last_value + ($trend * $i);
            
            // Add some noise based on historical variance
            $variance = $this->calculate_variance($values);
            $noise = (mt_rand() / mt_getrandmax() - 0.5) * sqrt($variance) * 0.1;
            
            $forecast[] = array(
                'date' => date('Y-m-d', strtotime("+{$i} days")),
                'predicted_value' => max(0, $predicted_value + $noise),
                'trend_component' => $trend * $i,
                'confidence' => max(0.5, 1 - ($i / $forecast_days) * 0.3) // Decreasing confidence
            );
        }
        
        return array(
            'model_type' => 'arima',
            'forecast' => $forecast,
            'model_params' => array(
                'ma_period' => $ma_period,
                'trend' => $trend,
                'variance' => $variance
            )
        );
    }
    
    /**
     * Generate Prophet prediction (simplified)
     */
    private function generate_prophet_prediction($historical_data, $options) {
        $values = array_column($historical_data, 'revenue');
        $dates = array_column($historical_data, 'date');
        $forecast_days = $options['horizon_days'];
        
        // Simplified Prophet-like implementation
        // Decompose into trend, seasonality, and remainder
        $trend = $this->extract_trend($values);
        $seasonality = $this->extract_seasonality($values, 7); // Weekly seasonality
        
        $forecast = array();
        
        for ($i = 1; $i <= $forecast_days; $i++) {
            $future_date = date('Y-m-d', strtotime("+{$i} days"));
            $trend_value = $this->project_trend($trend, $i);
            $seasonal_component = $this->get_seasonal_component($seasonality, $i, 7);
            
            $predicted_value = $trend_value + $seasonal_component;
            
            $forecast[] = array(
                'date' => $future_date,
                'predicted_value' => max(0, $predicted_value),
                'trend_component' => $trend_value,
                'seasonal_component' => $seasonal_component,
                'confidence' => max(0.6, 1 - ($i / $forecast_days) * 0.2)
            );
        }
        
        return array(
            'model_type' => 'prophet',
            'forecast' => $forecast,
            'model_params' => array(
                'trend' => $trend,
                'seasonality' => $seasonality
            )
        );
    }
    
    /**
     * Generate linear regression prediction
     */
    private function generate_linear_regression_prediction($historical_data, $options) {
        $values = array_column($historical_data, 'revenue');
        $n = count($values);
        $forecast_days = $options['horizon_days'];
        
        // Calculate linear regression parameters
        $x_values = range(1, $n);
        $slope = $this->calculate_slope($x_values, $values);
        $intercept = $this->calculate_intercept($x_values, $values, $slope);
        $r_squared = $this->calculate_r_squared($x_values, $values, $slope, $intercept);
        
        $forecast = array();
        
        for ($i = 1; $i <= $forecast_days; $i++) {
            $x = $n + $i;
            $predicted_value = $intercept + ($slope * $x);
            
            $forecast[] = array(
                'date' => date('Y-m-d', strtotime("+{$i} days")),
                'predicted_value' => max(0, $predicted_value),
                'confidence' => $r_squared * max(0.7, 1 - ($i / $forecast_days) * 0.2)
            );
        }
        
        return array(
            'model_type' => 'linear_regression',
            'forecast' => $forecast,
            'model_params' => array(
                'slope' => $slope,
                'intercept' => $intercept,
                'r_squared' => $r_squared
            )
        );
    }
    
    /**
     * Ensemble predictions from multiple models
     */
    private function ensemble_predictions($model_predictions, $options) {
        $forecast_days = $options['horizon_days'];
        $ensemble_forecast = array();
        
        for ($i = 0; $i < $forecast_days; $i++) {
            $day_predictions = array();
            $day_confidences = array();
            $forecast_date = date('Y-m-d', strtotime('+' . ($i + 1) . ' days'));
            
            foreach ($model_predictions as $model_type => $prediction) {
                if (isset($prediction['forecast'][$i])) {
                    $day_predictions[] = $prediction['forecast'][$i]['predicted_value'];
                    $day_confidences[] = $prediction['forecast'][$i]['confidence'];
                }
            }
            
            if (!empty($day_predictions)) {
                // Weighted average based on confidence
                $weights = array_map(function($conf) { return $conf * $conf; }, $day_confidences); // Square for more weight to confident predictions
                $weighted_prediction = $this->weighted_average($day_predictions, $weights);
                $ensemble_confidence = array_sum($day_confidences) / count($day_confidences);
                
                $ensemble_forecast[] = array(
                    'date' => $forecast_date,
                    'predicted_value' => $weighted_prediction,
                    'confidence' => $ensemble_confidence,
                    'model_agreement' => $this->calculate_model_agreement($day_predictions)
                );
            }
        }
        
        return $ensemble_forecast;
    }
    
    /**
     * Calculate confidence intervals
     */
    private function calculate_confidence_intervals($forecast, $options) {
        $confidence_level = $options['confidence_level'];
        $z_score = $this->get_z_score($confidence_level);
        
        foreach ($forecast as &$day_forecast) {
            $predicted_value = $day_forecast['predicted_value'];
            $confidence = $day_forecast['confidence'];
            
            // Estimate standard error based on confidence
            $standard_error = $predicted_value * (1 - $confidence) * 0.2;
            
            $margin_of_error = $z_score * $standard_error;
            
            $day_forecast['confidence_interval'] = array(
                'lower' => max(0, $predicted_value - $margin_of_error),
                'upper' => $predicted_value + $margin_of_error,
                'margin_of_error' => $margin_of_error
            );
        }
        
        return $forecast;
    }
    
    /**
     * Analyze trends in historical data
     */
    private function analyze_trends($historical_data) {
        $values = array_column($historical_data, 'revenue');
        
        return array(
            'overall_trend' => $this->calculate_linear_trend($values),
            'recent_trend' => $this->calculate_linear_trend(array_slice($values, -14)),
            'trend_strength' => $this->calculate_trend_strength($values),
            'trend_direction' => $this->determine_trend_direction($values),
            'volatility' => $this->calculate_volatility($values)
        );
    }
    
    /**
     * Detect seasonality patterns
     */
    private function detect_seasonality($historical_data) {
        $values = array_column($historical_data, 'revenue');
        
        return array(
            'weekly_seasonality' => $this->extract_seasonality($values, 7),
            'monthly_seasonality' => $this->extract_seasonality($values, 30),
            'seasonality_strength' => $this->calculate_seasonality_strength($values),
            'dominant_cycle' => $this->find_dominant_cycle($values)
        );
    }
    
    /**
     * Detect anomalies in historical data
     */
    private function detect_anomalies($historical_data) {
        $values = array_column($historical_data, 'revenue');
        $dates = array_column($historical_data, 'date');
        
        $mean = array_sum($values) / count($values);
        $std_dev = sqrt($this->calculate_variance($values));
        
        $anomalies = array();
        $threshold = 2.5; // Z-score threshold
        
        foreach ($values as $index => $value) {
            $z_score = abs(($value - $mean) / $std_dev);
            
            if ($z_score > $threshold) {
                $anomalies[] = array(
                    'date' => $dates[$index],
                    'value' => $value,
                    'z_score' => $z_score,
                    'type' => $value > $mean ? 'spike' : 'dip'
                );
            }
        }
        
        return array(
            'anomalies' => $anomalies,
            'anomaly_rate' => count($anomalies) / count($values),
            'detection_threshold' => $threshold
        );
    }
    
    /**
     * Utility calculation methods
     */
    private function calculate_moving_average($values, $period) {
        $moving_averages = array();
        
        for ($i = $period - 1; $i < count($values); $i++) {
            $sum = array_sum(array_slice($values, $i - $period + 1, $period));
            $moving_averages[] = $sum / $period;
        }
        
        return $moving_averages;
    }
    
    private function calculate_linear_trend($values) {
        $n = count($values);
        $x_values = range(1, $n);
        
        return $this->calculate_slope($x_values, $values);
    }
    
    private function calculate_slope($x_values, $y_values) {
        $n = count($x_values);
        $sum_x = array_sum($x_values);
        $sum_y = array_sum($y_values);
        $sum_xy = 0;
        $sum_xx = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x_values[$i] * $y_values[$i];
            $sum_xx += $x_values[$i] * $x_values[$i];
        }
        
        return ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_xx - $sum_x * $sum_x);
    }
    
    private function calculate_intercept($x_values, $y_values, $slope) {
        $mean_x = array_sum($x_values) / count($x_values);
        $mean_y = array_sum($y_values) / count($y_values);
        
        return $mean_y - $slope * $mean_x;
    }
    
    private function calculate_variance($values) {
        $mean = array_sum($values) / count($values);
        $sum_squares = 0;
        
        foreach ($values as $value) {
            $sum_squares += pow($value - $mean, 2);
        }
        
        return $sum_squares / (count($values) - 1);
    }
    
    private function weighted_average($values, $weights) {
        $weighted_sum = 0;
        $weight_sum = array_sum($weights);
        
        for ($i = 0; $i < count($values); $i++) {
            $weighted_sum += $values[$i] * $weights[$i];
        }
        
        return $weight_sum > 0 ? $weighted_sum / $weight_sum : 0;
    }
    
    private function calculate_model_agreement($predictions) {
        if (count($predictions) < 2) return 1.0;
        
        $mean = array_sum($predictions) / count($predictions);
        $variance = $this->calculate_variance($predictions);
        $coefficient_of_variation = $mean > 0 ? sqrt($variance) / $mean : 0;
        
        return max(0, 1 - $coefficient_of_variation);
    }
    
    private function get_z_score($confidence_level) {
        // Common z-scores for confidence intervals
        $z_scores = array(
            80 => 1.28,
            90 => 1.64,
            95 => 1.96,
            99 => 2.58
        );
        
        return $z_scores[$confidence_level] ?? 1.96;
    }
    
    // Placeholder methods for complex calculations
    private function prepare_conversion_data($periods) { return array(); }
    private function prepare_ltv_data($periods) { return array(); }
    private function prepare_churn_data($periods) { return array(); }
    private function prepare_channel_roi_data($periods) { return array(); }
    private function generate_lstm_prediction($data, $options) { return array('model_type' => 'lstm', 'forecast' => array()); }
    private function generate_logistic_regression_prediction($data, $options) { return array('model_type' => 'logistic_regression', 'forecast' => array()); }
    private function generate_random_forest_prediction($data, $options) { return array('model_type' => 'random_forest', 'forecast' => array()); }
    private function generate_neural_network_prediction($data, $options) { return array('model_type' => 'neural_network', 'forecast' => array()); }
    private function extract_trend($values) { return array(); }
    private function extract_seasonality($values, $period) { return array(); }
    private function project_trend($trend, $periods) { return 0; }
    private function get_seasonal_component($seasonality, $period, $cycle) { return 0; }
    private function calculate_r_squared($x, $y, $slope, $intercept) { return 0.8; }
    private function calculate_trend_strength($values) { return 0.5; }
    private function determine_trend_direction($values) { return 'increasing'; }
    private function calculate_volatility($values) { return 0.1; }
    private function calculate_seasonality_strength($values) { return 0.3; }
    private function find_dominant_cycle($values) { return 7; }
    private function assess_forecast_quality($predictions) { return array('quality_score' => 0.8); }
    private function generate_cache_key($type, $options) { return 'pred_' . $type . '_' . md5(serialize($options)); }
    
    /**
     * Update all predictions
     */
    public function update_all_predictions() {
        foreach (array_keys($this->forecasting_models) as $forecast_type) {
            $this->generate_forecast($forecast_type);
        }
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_generate_forecast() {
        check_ajax_referer('khm_prediction_nonce', 'nonce');
        
        $forecast_type = sanitize_text_field($_POST['forecast_type'] ?? 'revenue_forecasting');
        $horizon_days = intval($_POST['horizon_days'] ?? 30);
        
        $options = array(
            'horizon_days' => $horizon_days,
            'confidence_level' => intval($_POST['confidence_level'] ?? 95)
        );
        
        $forecast = $this->generate_forecast($forecast_type, $options);
        
        if ($forecast) {
            wp_send_json_success($forecast);
        } else {
            wp_send_json_error('Failed to generate forecast');
        }
    }
    
    public function ajax_predict_conversions() {
        check_ajax_referer('khm_prediction_nonce', 'nonce');
        
        $prediction = $this->generate_forecast('conversion_prediction', array(
            'horizon_days' => intval($_POST['horizon_days'] ?? 14)
        ));
        
        wp_send_json_success($prediction);
    }
    
    public function ajax_analyze_trends() {
        check_ajax_referer('khm_prediction_nonce', 'nonce');
        
        $historical_data = $this->prepare_revenue_data(90);
        $trends = $this->analyze_trends($historical_data);
        
        wp_send_json_success($trends);
    }
    
    /**
     * Add prediction menu
     */
    public function add_prediction_menu() {
        add_submenu_page(
            'khm-attribution',
            'Predictive Analytics',
            'Predictions',
            'manage_options',
            'khm-attribution-predictions',
            array($this, 'render_prediction_page')
        );
    }
    
    /**
     * Render prediction page
     */
    public function render_prediction_page() {
        echo '<div class="wrap">';
        echo '<h1>Predictive Analytics</h1>';
        echo '<p>Advanced forecasting and predictive modeling for attribution data.</p>';
        echo '</div>';
    }
}
?>