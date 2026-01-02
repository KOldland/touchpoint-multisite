<?php
/**
 * KHM Attribution Forecasting Helper Methods
 * 
 * Support methods for the forecasting engine including seasonality,
 * trend analysis, and statistical calculations
 */

trait KHM_Attribution_Forecasting_Helpers {
    
    /**
     * Apply seasonality adjustments to forecast
     */
    private function apply_seasonality_adjustments($forecast, $historical_data) {
        $seasonal_factors = $this->calculate_seasonal_factors($historical_data);
        $adjusted_forecast = array();
        
        foreach ($forecast as $index => $value) {
            $day_of_week = ($index + date('w')) % 7;
            $seasonal_multiplier = isset($seasonal_factors[$day_of_week]) ? $seasonal_factors[$day_of_week] : 1.0;
            $adjusted_forecast[] = $value * $seasonal_multiplier;
        }
        
        return $adjusted_forecast;
    }
    
    /**
     * Apply trend adjustments to forecast
     */
    private function apply_trend_adjustments($forecast, $historical_data) {
        $trend_factor = $this->calculate_trend_factor($historical_data);
        $adjusted_forecast = array();
        
        foreach ($forecast as $index => $value) {
            $trend_adjustment = 1 + ($trend_factor * ($index + 1) / 30); // Apply trend over 30-day period
            $adjusted_forecast[] = $value * $trend_adjustment;
        }
        
        return $adjusted_forecast;
    }
    
    /**
     * Apply external factors to forecast
     */
    private function apply_external_factors($forecast, $external_factors) {
        $adjusted_forecast = array();
        
        foreach ($forecast as $index => $value) {
            $adjustment = 1.0;
            
            // Apply various external factors
            if (isset($external_factors['market_conditions'])) {
                $adjustment *= $external_factors['market_conditions'];
            }
            
            if (isset($external_factors['seasonality_override'])) {
                $adjustment *= $external_factors['seasonality_override'];
            }
            
            if (isset($external_factors['campaign_boost'])) {
                // Apply campaign boost for specific periods
                $campaign_start = isset($external_factors['campaign_start']) ? $external_factors['campaign_start'] : 0;
                $campaign_duration = isset($external_factors['campaign_duration']) ? $external_factors['campaign_duration'] : 7;
                
                if ($index >= $campaign_start && $index < $campaign_start + $campaign_duration) {
                    $adjustment *= $external_factors['campaign_boost'];
                }
            }
            
            $adjusted_forecast[] = $value * $adjustment;
        }
        
        return $adjusted_forecast;
    }
    
    /**
     * Calculate confidence intervals for forecast
     */
    private function calculate_confidence_intervals($forecast, $confidence_level) {
        $confidence_intervals = array();
        $z_score = $this->get_z_score($confidence_level);
        
        foreach ($forecast as $index => $value) {
            // Estimate forecast error based on forecast horizon
            $forecast_error = $value * 0.1 * sqrt($index + 1); // Error increases with time
            
            $lower_bound = max(0, $value - ($z_score * $forecast_error));
            $upper_bound = $value + ($z_score * $forecast_error);
            
            $confidence_intervals[] = array(
                'forecast' => $value,
                'lower_bound' => $lower_bound,
                'upper_bound' => $upper_bound,
                'margin_of_error' => $z_score * $forecast_error
            );
        }
        
        return $confidence_intervals;
    }
    
    /**
     * Calculate forecast accuracy metrics
     */
    private function calculate_forecast_accuracy($historical_data, $model) {
        // Use last 30 days for back-testing
        $test_days = min(30, count($historical_data['revenue_series']) / 2);
        $actual_data = array_slice($historical_data['revenue_series'], -$test_days * 2, $test_days);
        $training_data = array_slice($historical_data['revenue_series'], 0, -$test_days);
        
        // Generate forecast for test period
        $test_forecast = $this->generate_base_forecast($training_data, $model, array('forecast_days' => $test_days));
        
        // Calculate accuracy metrics
        $mae = $this->calculate_mean_absolute_error($actual_data, $test_forecast);
        $mape = $this->calculate_mean_absolute_percentage_error($actual_data, $test_forecast);
        $rmse = $this->calculate_root_mean_square_error($actual_data, $test_forecast);
        
        return array(
            'mae' => $mae,
            'mape' => $mape,
            'rmse' => $rmse,
            'accuracy_score' => max(0, 100 - $mape) // Convert MAPE to accuracy percentage
        );
    }
    
    /**
     * Analyze conversion patterns
     */
    private function analyze_conversion_patterns($historical_data) {
        $patterns = array(
            'daily_patterns' => array(),
            'weekly_patterns' => array(),
            'monthly_patterns' => array(),
            'conversion_velocity' => array(),
            'funnel_efficiency' => array()
        );
        
        // Analyze daily conversion patterns
        $daily_conversions = array();
        foreach ($historical_data['conversions'] as $date => $conversions) {
            $day_of_week = date('w', strtotime($date));
            if (!isset($daily_conversions[$day_of_week])) {
                $daily_conversions[$day_of_week] = array();
            }
            $daily_conversions[$day_of_week][] = $conversions;
        }
        
        foreach ($daily_conversions as $day => $conversions) {
            $patterns['daily_patterns'][$day] = array(
                'average' => array_sum($conversions) / count($conversions),
                'variance' => $this->calculate_variance($conversions),
                'trend' => $this->calculate_trend($conversions)
            );
        }
        
        // Analyze conversion velocity (time to convert)
        if (isset($historical_data['conversion_times'])) {
            $patterns['conversion_velocity'] = $this->analyze_conversion_velocity($historical_data['conversion_times']);
        }
        
        return $patterns;
    }
    
    /**
     * Generate conversion forecast
     */
    private function generate_conversion_forecast($patterns, $filters) {
        $forecast_days = $filters['forecast_days'];
        $conversion_forecast = array();
        
        for ($i = 0; $i < $forecast_days; $i++) {
            $forecast_date = date('Y-m-d', strtotime("+{$i} days"));
            $day_of_week = date('w', strtotime($forecast_date));
            
            // Base conversion from daily patterns
            $base_conversions = isset($patterns['daily_patterns'][$day_of_week]) 
                ? $patterns['daily_patterns'][$day_of_week]['average'] 
                : 10; // Default fallback
            
            // Apply seasonal adjustments if enabled
            if ($filters['seasonal_adjustments']) {
                $seasonal_factor = $this->get_seasonal_conversion_factor($forecast_date);
                $base_conversions *= $seasonal_factor;
            }
            
            $conversion_forecast[] = max(0, round($base_conversions));
        }
        
        return $conversion_forecast;
    }
    
    /**
     * Apply funnel optimization scenarios
     */
    private function apply_funnel_optimization_scenarios($conversion_forecast) {
        $optimization_scenarios = array(
            'current' => $conversion_forecast,
            'landing_page_optimization' => array(),
            'email_sequence_optimization' => array(),
            'checkout_optimization' => array(),
            'full_optimization' => array()
        );
        
        foreach ($conversion_forecast as $conversions) {
            // Landing page optimization (+15% conversions)
            $optimization_scenarios['landing_page_optimization'][] = round($conversions * 1.15);
            
            // Email sequence optimization (+20% conversions)
            $optimization_scenarios['email_sequence_optimization'][] = round($conversions * 1.20);
            
            // Checkout optimization (+10% conversions)
            $optimization_scenarios['checkout_optimization'][] = round($conversions * 1.10);
            
            // Full optimization (+40% conversions)
            $optimization_scenarios['full_optimization'][] = round($conversions * 1.40);
        }
        
        return $optimization_scenarios;
    }
    
    /**
     * Forecast conversion rates
     */
    private function forecast_conversion_rates($historical_data, $filters) {
        $conversion_rates = array();
        
        // Calculate historical conversion rate trends
        $historical_rates = array();
        foreach ($historical_data['traffic'] as $date => $traffic) {
            $conversions = isset($historical_data['conversions'][$date]) ? $historical_data['conversions'][$date] : 0;
            $rate = $traffic > 0 ? ($conversions / $traffic) * 100 : 0;
            $historical_rates[] = $rate;
        }
        
        // Forecast conversion rates using trend analysis
        $rate_trend = $this->calculate_trend($historical_rates);
        $average_rate = array_sum($historical_rates) / count($historical_rates);
        
        for ($i = 0; $i < $filters['forecast_days']; $i++) {
            $forecasted_rate = $average_rate + ($rate_trend * $i);
            $conversion_rates[] = max(0, min(100, $forecasted_rate)); // Cap between 0-100%
        }
        
        return $conversion_rates;
    }
    
    /**
     * Identify funnel improvements
     */
    private function identify_funnel_improvements($patterns) {
        $improvements = array();
        
        // Analyze daily patterns for optimization opportunities
        foreach ($patterns['daily_patterns'] as $day => $data) {
            if ($data['average'] < 15) { // Below threshold
                $improvements[] = array(
                    'area' => 'Daily Performance',
                    'issue' => "Low conversions on " . date('l', strtotime("Sunday +{$day} days")),
                    'recommendation' => 'Consider targeted campaigns for this day',
                    'potential_lift' => '25%'
                );
            }
        }
        
        // Generic funnel improvements
        $improvements[] = array(
            'area' => 'Landing Page',
            'issue' => 'Potential for optimization',
            'recommendation' => 'A/B test headlines and CTAs',
            'potential_lift' => '15%'
        );
        
        $improvements[] = array(
            'area' => 'Email Sequence',
            'issue' => 'Follow-up optimization',
            'recommendation' => 'Improve email sequence timing and content',
            'potential_lift' => '20%'
        );
        
        return $improvements;
    }
    
    /**
     * Calculate seasonal conversion factors
     */
    private function calculate_seasonal_conversion_factors($historical_data) {
        $seasonal_factors = array();
        
        // Monthly seasonality
        $monthly_data = array();
        foreach ($historical_data['conversions'] as $date => $conversions) {
            $month = date('n', strtotime($date));
            if (!isset($monthly_data[$month])) {
                $monthly_data[$month] = array();
            }
            $monthly_data[$month][] = $conversions;
        }
        
        $overall_average = 0;
        $total_conversions = 0;
        foreach ($monthly_data as $conversions) {
            $total_conversions += array_sum($conversions);
        }
        $overall_average = $total_conversions / array_sum(array_map('count', $monthly_data));
        
        foreach ($monthly_data as $month => $conversions) {
            $month_average = array_sum($conversions) / count($conversions);
            $seasonal_factors[$month] = $overall_average > 0 ? $month_average / $overall_average : 1.0;
        }
        
        return $seasonal_factors;
    }
    
    /**
     * Calculate moving averages
     */
    private function calculate_moving_averages($historical_data, $periods) {
        $moving_averages = array();
        $data = array_values($historical_data['revenue_series']);
        
        foreach ($periods as $period) {
            $moving_averages[$period] = array();
            
            for ($i = $period - 1; $i < count($data); $i++) {
                $window = array_slice($data, $i - $period + 1, $period);
                $moving_averages[$period][] = array_sum($window) / count($window);
            }
        }
        
        return $moving_averages;
    }
    
    /**
     * Detect trend patterns
     */
    private function detect_trend_patterns($historical_data, $filters) {
        $data = array_values($historical_data['revenue_series']);
        $n = count($data);
        
        if ($n < 2) {
            return array(
                'direction' => 'neutral',
                'strength' => 0,
                'confidence' => 0,
                'slope' => 0,
                'r_squared' => 0
            );
        }
        
        // Calculate linear regression
        $x_values = range(1, $n);
        $correlation = $this->calculate_correlation($x_values, $data);
        $slope = $this->calculate_slope($x_values, $data);
        $r_squared = $correlation * $correlation;
        
        // Determine trend direction
        $direction = 'neutral';
        if ($slope > 0.1) {
            $direction = 'upward';
        } elseif ($slope < -0.1) {
            $direction = 'downward';
        }
        
        // Calculate trend strength
        $strength = abs($correlation);
        
        // Calculate confidence
        $confidence = $r_squared * 100;
        
        return array(
            'direction' => $direction,
            'strength' => $strength,
            'confidence' => $confidence,
            'slope' => $slope,
            'r_squared' => $r_squared
        );
    }
    
    /**
     * Detect cyclical patterns
     */
    private function detect_cyclical_patterns($historical_data) {
        $data = array_values($historical_data['revenue_series']);
        $patterns = array();
        
        // Look for weekly cycles
        $weekly_pattern = $this->detect_weekly_cycle($data);
        if ($weekly_pattern['significance'] > 0.3) {
            $patterns['weekly'] = $weekly_pattern;
        }
        
        // Look for monthly cycles
        $monthly_pattern = $this->detect_monthly_cycle($data);
        if ($monthly_pattern['significance'] > 0.3) {
            $patterns['monthly'] = $monthly_pattern;
        }
        
        return $patterns;
    }
    
    /**
     * Detect anomalies in data
     */
    private function detect_anomalies($historical_data) {
        $data = array_values($historical_data['revenue_series']);
        $anomalies = array();
        
        $mean = array_sum($data) / count($data);
        $std_dev = sqrt($this->calculate_variance($data));
        
        $threshold = 2.5; // Standard deviations for anomaly detection
        
        foreach ($data as $index => $value) {
            $z_score = abs(($value - $mean) / $std_dev);
            
            if ($z_score > $threshold) {
                $anomalies[] = array(
                    'index' => $index,
                    'value' => $value,
                    'z_score' => $z_score,
                    'type' => $value > $mean ? 'spike' : 'dip',
                    'date' => array_keys($historical_data['revenue_series'])[$index]
                );
            }
        }
        
        return $anomalies;
    }
    
    /**
     * Perform seasonal decomposition
     */
    private function perform_seasonal_decomposition($historical_data) {
        $data = array_values($historical_data['revenue_series']);
        
        // Simple seasonal decomposition
        $trend = $this->extract_trend_component($data);
        $seasonal = $this->extract_seasonal_component($data);
        $residual = $this->calculate_residual_component($data, $trend, $seasonal);
        
        return array(
            'trend' => $trend,
            'seasonal' => $seasonal,
            'residual' => $residual,
            'trend_strength' => $this->calculate_component_strength($trend),
            'seasonal_strength' => $this->calculate_component_strength($seasonal)
        );
    }
    
    /**
     * Calculate volatility
     */
    private function calculate_volatility($historical_data) {
        $data = array_values($historical_data['revenue_series']);
        
        if (count($data) < 2) return 0;
        
        $returns = array();
        for ($i = 1; $i < count($data); $i++) {
            if ($data[$i - 1] != 0) {
                $returns[] = ($data[$i] - $data[$i - 1]) / $data[$i - 1];
            }
        }
        
        return sqrt($this->calculate_variance($returns)) * 100; // Return as percentage
    }
    
    /**
     * Calculate momentum
     */
    private function calculate_momentum($historical_data) {
        $data = array_values($historical_data['revenue_series']);
        
        if (count($data) < 10) return 0;
        
        // Compare recent performance to historical average
        $recent_data = array_slice($data, -7); // Last 7 days
        $historical_data_slice = array_slice($data, 0, -7); // Everything before last 7 days
        
        $recent_average = array_sum($recent_data) / count($recent_data);
        $historical_average = array_sum($historical_data_slice) / count($historical_data_slice);
        
        if ($historical_average == 0) return 0;
        
        $momentum = (($recent_average - $historical_average) / $historical_average) * 100;
        
        return $momentum;
    }
    
    /**
     * Analyze forecast implications
     */
    private function analyze_forecast_implications($trend_analysis, $cyclical_patterns) {
        $implications = array();
        
        // Trend implications
        if ($trend_analysis['direction'] === 'upward' && $trend_analysis['confidence'] > 70) {
            $implications[] = 'Strong upward trend suggests continued growth opportunity';
        } elseif ($trend_analysis['direction'] === 'downward' && $trend_analysis['confidence'] > 70) {
            $implications[] = 'Downward trend indicates need for intervention';
        }
        
        // Cyclical implications
        if (isset($cyclical_patterns['weekly'])) {
            $implications[] = 'Weekly patterns detected - optimize campaigns for peak days';
        }
        
        if (isset($cyclical_patterns['monthly'])) {
            $implications[] = 'Monthly cycles present - plan budget allocation accordingly';
        }
        
        return $implications;
    }
    
    // Additional helper methods...
    
    private function calculate_seasonal_factors($historical_data) {
        $daily_data = array();
        
        foreach ($historical_data['revenue_series'] as $date => $revenue) {
            $day_of_week = date('w', strtotime($date));
            if (!isset($daily_data[$day_of_week])) {
                $daily_data[$day_of_week] = array();
            }
            $daily_data[$day_of_week][] = $revenue;
        }
        
        $overall_average = array_sum($historical_data['revenue_series']) / count($historical_data['revenue_series']);
        $seasonal_factors = array();
        
        foreach ($daily_data as $day => $revenues) {
            $day_average = array_sum($revenues) / count($revenues);
            $seasonal_factors[$day] = $overall_average > 0 ? $day_average / $overall_average : 1.0;
        }
        
        return $seasonal_factors;
    }
    
    private function calculate_trend_factor($historical_data) {
        $data = array_values($historical_data['revenue_series']);
        return $this->calculate_trend($data);
    }
    
    private function calculate_trend($data) {
        if (count($data) < 2) return 0;
        
        $n = count($data);
        $x_values = range(1, $n);
        
        return $this->calculate_slope($x_values, $data);
    }
    
    private function calculate_slope($x_values, $y_values) {
        $n = count($x_values);
        
        if ($n < 2) return 0;
        
        $x_mean = array_sum($x_values) / $n;
        $y_mean = array_sum($y_values) / $n;
        
        $numerator = 0;
        $denominator = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x_diff = $x_values[$i] - $x_mean;
            $y_diff = $y_values[$i] - $y_mean;
            
            $numerator += $x_diff * $y_diff;
            $denominator += $x_diff * $x_diff;
        }
        
        return $denominator != 0 ? $numerator / $denominator : 0;
    }
    
    private function get_z_score($confidence_level) {
        // Common z-scores for confidence levels
        $z_scores = array(
            90 => 1.645,
            95 => 1.96,
            99 => 2.576
        );
        
        return isset($z_scores[$confidence_level]) ? $z_scores[$confidence_level] : 1.96;
    }
    
    private function calculate_mean_absolute_error($actual, $forecast) {
        $errors = array();
        $count = min(count($actual), count($forecast));
        
        for ($i = 0; $i < $count; $i++) {
            $errors[] = abs($actual[$i] - $forecast[$i]);
        }
        
        return array_sum($errors) / count($errors);
    }
    
    private function calculate_mean_absolute_percentage_error($actual, $forecast) {
        $errors = array();
        $count = min(count($actual), count($forecast));
        
        for ($i = 0; $i < $count; $i++) {
            if ($actual[$i] != 0) {
                $errors[] = abs(($actual[$i] - $forecast[$i]) / $actual[$i]) * 100;
            }
        }
        
        return count($errors) > 0 ? array_sum($errors) / count($errors) : 0;
    }
    
    private function calculate_root_mean_square_error($actual, $forecast) {
        $squared_errors = array();
        $count = min(count($actual), count($forecast));
        
        for ($i = 0; $i < $count; $i++) {
            $squared_errors[] = pow($actual[$i] - $forecast[$i], 2);
        }
        
        return sqrt(array_sum($squared_errors) / count($squared_errors));
    }
    
    private function analyze_conversion_velocity($conversion_times) {
        $velocities = array();
        
        foreach ($conversion_times as $time_to_convert) {
            $velocities[] = $time_to_convert;
        }
        
        return array(
            'average_time' => array_sum($velocities) / count($velocities),
            'median_time' => $this->calculate_median($velocities),
            'fastest_conversion' => min($velocities),
            'slowest_conversion' => max($velocities)
        );
    }
    
    private function calculate_median($array) {
        sort($array);
        $count = count($array);
        
        if ($count % 2 == 0) {
            return ($array[$count / 2 - 1] + $array[$count / 2]) / 2;
        } else {
            return $array[floor($count / 2)];
        }
    }
    
    private function get_seasonal_conversion_factor($date) {
        // Simple seasonal factor based on month
        $month = date('n', strtotime($date));
        
        $seasonal_factors = array(
            1 => 0.9,   // January
            2 => 0.95,  // February
            3 => 1.1,   // March
            4 => 1.05,  // April
            5 => 1.0,   // May
            6 => 0.95,  // June
            7 => 0.85,  // July
            8 => 0.9,   // August
            9 => 1.1,   // September
            10 => 1.15, // October
            11 => 1.2,  // November
            12 => 1.3   // December
        );
        
        return isset($seasonal_factors[$month]) ? $seasonal_factors[$month] : 1.0;
    }
    
    private function detect_weekly_cycle($data) {
        // Simple weekly cycle detection
        $weekly_averages = array();
        
        for ($i = 0; $i < count($data); $i++) {
            $day_of_week = $i % 7;
            if (!isset($weekly_averages[$day_of_week])) {
                $weekly_averages[$day_of_week] = array();
            }
            $weekly_averages[$day_of_week][] = $data[$i];
        }
        
        $day_averages = array();
        foreach ($weekly_averages as $day => $values) {
            $day_averages[] = array_sum($values) / count($values);
        }
        
        $overall_average = array_sum($day_averages) / count($day_averages);
        $variance = 0;
        
        foreach ($day_averages as $avg) {
            $variance += pow($avg - $overall_average, 2);
        }
        
        $variance /= count($day_averages);
        $data_variance = $this->calculate_variance($data);
        
        return array(
            'significance' => $data_variance > 0 ? $variance / $data_variance : 0,
            'pattern' => $day_averages
        );
    }
    
    private function detect_monthly_cycle($data) {
        // Simple monthly cycle detection
        if (count($data) < 30) {
            return array('significance' => 0, 'pattern' => array());
        }
        
        $monthly_chunks = array_chunk($data, 30);
        $monthly_averages = array();
        
        foreach ($monthly_chunks as $chunk) {
            $monthly_averages[] = array_sum($chunk) / count($chunk);
        }
        
        if (count($monthly_averages) < 2) {
            return array('significance' => 0, 'pattern' => array());
        }
        
        $overall_average = array_sum($monthly_averages) / count($monthly_averages);
        $variance = 0;
        
        foreach ($monthly_averages as $avg) {
            $variance += pow($avg - $overall_average, 2);
        }
        
        $variance /= count($monthly_averages);
        $data_variance = $this->calculate_variance($data);
        
        return array(
            'significance' => $data_variance > 0 ? $variance / $data_variance : 0,
            'pattern' => $monthly_averages
        );
    }
    
    private function extract_trend_component($data) {
        // Simple trend extraction using moving average
        $window_size = max(7, count($data) / 10);
        $trend = array();
        
        for ($i = 0; $i < count($data); $i++) {
            $start = max(0, $i - floor($window_size / 2));
            $end = min(count($data) - 1, $i + floor($window_size / 2));
            
            $window = array_slice($data, $start, $end - $start + 1);
            $trend[] = array_sum($window) / count($window);
        }
        
        return $trend;
    }
    
    private function extract_seasonal_component($data) {
        // Simple seasonal component extraction
        $period = 7; // Assume weekly seasonality
        $seasonal = array();
        
        for ($i = 0; $i < $period; $i++) {
            $seasonal[$i] = 0;
        }
        
        // Calculate average for each day of the period
        $counts = array_fill(0, $period, 0);
        
        for ($i = 0; $i < count($data); $i++) {
            $period_index = $i % $period;
            $seasonal[$period_index] += $data[$i];
            $counts[$period_index]++;
        }
        
        for ($i = 0; $i < $period; $i++) {
            if ($counts[$i] > 0) {
                $seasonal[$i] /= $counts[$i];
            }
        }
        
        return $seasonal;
    }
    
    private function calculate_residual_component($data, $trend, $seasonal) {
        $residual = array();
        $period = count($seasonal);
        
        for ($i = 0; $i < count($data); $i++) {
            $seasonal_value = $seasonal[$i % $period];
            $trend_value = isset($trend[$i]) ? $trend[$i] : 0;
            
            $residual[] = $data[$i] - $trend_value - $seasonal_value;
        }
        
        return $residual;
    }
    
    private function calculate_component_strength($component) {
        if (empty($component)) return 0;
        
        $variance = $this->calculate_variance($component);
        $mean = array_sum($component) / count($component);
        
        return $mean != 0 ? sqrt($variance) / abs($mean) : 0;
    }
    
    private function calculate_recent_growth_factor($data) {
        if (count($data) < 2) return 1.0;
        
        $recent_trend = $this->calculate_trend($data);
        $average_value = array_sum($data) / count($data);
        
        if ($average_value == 0) return 1.0;
        
        // Convert trend to growth factor
        return 1 + ($recent_trend / $average_value);
    }
}
?>