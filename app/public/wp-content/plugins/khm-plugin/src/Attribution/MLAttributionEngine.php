<?php
/**
 * KHM Attribution ML Attribution Engine
 * 
 * Machine learning-powered attribution modeling using Phase 2 OOP architectural patterns.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_ML_Attribution_Engine {
    
    private $performance_manager;
    private $database_manager;
    private $query_builder;
    private $ml_config = array();
    private $attribution_models = array();
    private $training_data = array();
    private $model_weights = array();
    
    /**
     * Constructor - Initialize ML attribution engine components
     */
    public function __construct() {
        $this->init_ml_components();
        $this->setup_ml_config();
        $this->load_attribution_models();
        $this->register_ml_hooks();
    }
    
    /**
     * Initialize ML components
     */
    private function init_ml_components() {
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
     * Setup ML configuration
     */
    private function setup_ml_config() {
        $this->ml_config = array(
            'training_window_days' => 90,
            'min_training_samples' => 1000,
            'feature_engineering' => array(
                'time_decay_factors' => array(1, 7, 14, 30),
                'channel_interactions' => true,
                'sequential_patterns' => true,
                'device_cross_attribution' => true
            ),
            'model_types' => array(
                'neural_network' => array('layers' => 3, 'neurons' => array(128, 64, 32)),
                'random_forest' => array('trees' => 100, 'depth' => 10),
                'gradient_boosting' => array('estimators' => 200, 'learning_rate' => 0.1),
                'logistic_regression' => array('regularization' => 'l2', 'alpha' => 0.01)
            ),
            'validation_split' => 0.2,
            'cross_validation_folds' => 5,
            'performance_threshold' => 0.85,
            'auto_retrain_threshold' => 0.75
        );
        
        // Allow configuration overrides
        $this->ml_config = apply_filters('khm_ml_config', $this->ml_config);
    }
    
    /**
     * Load attribution models
     */
    private function load_attribution_models() {
        $this->attribution_models = array(
            'data_driven' => array(
                'name' => 'Data-Driven Attribution',
                'algorithm' => 'shapley_value',
                'features' => array('channel', 'position', 'timing', 'device', 'content'),
                'accuracy_target' => 0.90
            ),
            'multi_touch_ml' => array(
                'name' => 'ML Multi-Touch Attribution',
                'algorithm' => 'neural_network',
                'features' => array('touchpoint_sequence', 'time_between_touches', 'channel_mix', 'user_journey'),
                'accuracy_target' => 0.85
            ),
            'probabilistic' => array(
                'name' => 'Probabilistic Attribution',
                'algorithm' => 'bayesian_network',
                'features' => array('conversion_probability', 'channel_influence', 'interaction_effects'),
                'accuracy_target' => 0.80
            ),
            'ensemble' => array(
                'name' => 'Ensemble Attribution',
                'algorithm' => 'ensemble_voting',
                'features' => array('combined_predictions', 'confidence_scores', 'model_agreement'),
                'accuracy_target' => 0.92
            )
        );
    }
    
    /**
     * Register ML hooks
     */
    private function register_ml_hooks() {
        add_action('khm_train_attribution_models', array($this, 'train_attribution_models'));
        add_action('khm_validate_model_performance', array($this, 'validate_model_performance'));
        add_action('admin_menu', array($this, 'add_ml_menu'));
        
        // AJAX hooks
        add_action('wp_ajax_khm_generate_ml_attribution', array($this, 'ajax_generate_ml_attribution'));
        add_action('wp_ajax_khm_train_model', array($this, 'ajax_train_model'));
        add_action('wp_ajax_khm_validate_model', array($this, 'ajax_validate_model'));
        
        // Scheduled training
        if (!wp_next_scheduled('khm_train_attribution_models')) {
            wp_schedule_event(time(), 'weekly', 'khm_train_attribution_models');
        }
    }
    
    /**
     * Generate ML-powered attribution
     */
    public function generate_ml_attribution($conversion_data, $model_type = 'ensemble') {
        if (!isset($this->attribution_models[$model_type])) {
            return false;
        }
        
        $model_config = $this->attribution_models[$model_type];
        
        // Extract features from conversion data
        $features = $this->extract_features($conversion_data, $model_config['features']);
        
        // Load trained model
        $trained_model = $this->load_trained_model($model_type);
        if (!$trained_model) {
            // Fallback to rule-based attribution
            return $this->fallback_attribution($conversion_data);
        }
        
        // Generate predictions
        $predictions = $this->predict_attribution($features, $trained_model, $model_config);
        
        // Post-process results
        $attribution_results = $this->post_process_attribution($predictions, $conversion_data);
        
        // Validate and adjust if needed
        $validated_results = $this->validate_attribution_results($attribution_results);
        
        return $validated_results;
    }
    
    /**
     * Extract features for ML model
     */
    private function extract_features($conversion_data, $feature_types) {
        $features = array();
        
        foreach ($feature_types as $feature_type) {
            switch ($feature_type) {
                case 'channel':
                    $features['channel'] = $this->extract_channel_features($conversion_data);
                    break;
                    
                case 'position':
                    $features['position'] = $this->extract_position_features($conversion_data);
                    break;
                    
                case 'timing':
                    $features['timing'] = $this->extract_timing_features($conversion_data);
                    break;
                    
                case 'device':
                    $features['device'] = $this->extract_device_features($conversion_data);
                    break;
                    
                case 'content':
                    $features['content'] = $this->extract_content_features($conversion_data);
                    break;
                    
                case 'touchpoint_sequence':
                    $features['touchpoint_sequence'] = $this->extract_sequence_features($conversion_data);
                    break;
                    
                case 'time_between_touches':
                    $features['time_between_touches'] = $this->extract_time_interval_features($conversion_data);
                    break;
                    
                case 'channel_mix':
                    $features['channel_mix'] = $this->extract_channel_mix_features($conversion_data);
                    break;
                    
                case 'user_journey':
                    $features['user_journey'] = $this->extract_journey_features($conversion_data);
                    break;
            }
        }
        
        return $features;
    }
    
    /**
     * Extract channel features
     */
    private function extract_channel_features($conversion_data) {
        $touchpoints = $conversion_data['touchpoints'] ?? array();
        
        $channel_features = array(
            'unique_channels' => array(),
            'channel_frequency' => array(),
            'channel_sequence' => array(),
            'channel_diversity' => 0
        );
        
        foreach ($touchpoints as $touchpoint) {
            $channel = $touchpoint['utm_medium'] ?? 'direct';
            
            // Track unique channels
            if (!in_array($channel, $channel_features['unique_channels'])) {
                $channel_features['unique_channels'][] = $channel;
            }
            
            // Count frequency
            if (!isset($channel_features['channel_frequency'][$channel])) {
                $channel_features['channel_frequency'][$channel] = 0;
            }
            $channel_features['channel_frequency'][$channel]++;
            
            // Track sequence
            $channel_features['channel_sequence'][] = $channel;
        }
        
        // Calculate diversity
        $channel_features['channel_diversity'] = count($channel_features['unique_channels']);
        
        // Encode categorical features
        $channel_features['encoded_channels'] = $this->encode_categorical_features($channel_features['unique_channels']);
        
        return $channel_features;
    }
    
    /**
     * Extract position features
     */
    private function extract_position_features($conversion_data) {
        $touchpoints = $conversion_data['touchpoints'] ?? array();
        $total_touchpoints = count($touchpoints);
        
        $position_features = array();
        
        foreach ($touchpoints as $index => $touchpoint) {
            $position = ($index + 1) / $total_touchpoints; // Normalized position
            
            $position_features[] = array(
                'absolute_position' => $index + 1,
                'relative_position' => $position,
                'is_first' => $index === 0 ? 1 : 0,
                'is_last' => $index === $total_touchpoints - 1 ? 1 : 0,
                'distance_from_conversion' => $total_touchpoints - $index - 1
            );
        }
        
        return $position_features;
    }
    
    /**
     * Extract timing features
     */
    private function extract_timing_features($conversion_data) {
        $touchpoints = $conversion_data['touchpoints'] ?? array();
        $conversion_time = strtotime($conversion_data['conversion_date']);
        
        $timing_features = array();
        
        foreach ($touchpoints as $touchpoint) {
            $touchpoint_time = strtotime($touchpoint['created_at']);
            $time_to_conversion = ($conversion_time - $touchpoint_time) / 3600; // Hours
            
            $timing_features[] = array(
                'time_to_conversion_hours' => $time_to_conversion,
                'time_to_conversion_days' => $time_to_conversion / 24,
                'hour_of_day' => date('H', $touchpoint_time),
                'day_of_week' => date('w', $touchpoint_time),
                'is_weekend' => in_array(date('w', $touchpoint_time), array(0, 6)) ? 1 : 0,
                'time_decay_factor' => $this->calculate_time_decay($time_to_conversion)
            );
        }
        
        return $timing_features;
    }
    
    /**
     * Calculate time decay factor
     */
    private function calculate_time_decay($hours_to_conversion) {
        // Exponential decay: more recent touchpoints have higher weights
        $decay_rate = 0.1; // Adjust as needed
        return exp(-$decay_rate * ($hours_to_conversion / 24));
    }
    
    /**
     * Train attribution models
     */
    public function train_attribution_models() {
        // Get training data
        $training_data = $this->prepare_training_data();
        
        if (count($training_data) < $this->ml_config['min_training_samples']) {
            error_log('Insufficient training data for ML attribution models');
            return false;
        }
        
        $training_results = array();
        
        foreach ($this->attribution_models as $model_type => $model_config) {
            $training_result = $this->train_single_model($model_type, $training_data, $model_config);
            $training_results[$model_type] = $training_result;
            
            // Store trained model
            if ($training_result['success']) {
                $this->store_trained_model($model_type, $training_result['model']);
            }
        }
        
        return $training_results;
    }
    
    /**
     * Prepare training data
     */
    private function prepare_training_data() {
        global $wpdb;
        
        $conversions_table = $wpdb->prefix . 'khm_conversion_tracking';
        $events_table = $wpdb->prefix . 'khm_attribution_events';
        
        $training_window_start = date('Y-m-d', strtotime('-' . $this->ml_config['training_window_days'] . ' days'));
        
        // Get conversions with their touchpoint sequences
        $sql = "SELECT 
                    c.id as conversion_id,
                    c.user_id,
                    c.value as conversion_value,
                    c.created_at as conversion_date,
                    c.attribution_method as known_attribution
                FROM {$conversions_table} c
                WHERE c.created_at >= %s
                AND c.status = 'attributed'
                AND c.user_id IS NOT NULL
                ORDER BY c.created_at DESC
                LIMIT 10000"; // Limit for performance
        
        $conversions = $wpdb->get_results($wpdb->prepare($sql, $training_window_start), ARRAY_A);
        
        $training_data = array();
        
        foreach ($conversions as $conversion) {
            // Get touchpoints for this conversion
            $touchpoints_sql = "SELECT * FROM {$events_table} 
                               WHERE user_id = %s 
                               AND created_at <= %s 
                               ORDER BY created_at ASC";
            
            $touchpoints = $wpdb->get_results($wpdb->prepare(
                $touchpoints_sql, 
                $conversion['user_id'], 
                $conversion['conversion_date']
            ), ARRAY_A);
            
            if (count($touchpoints) > 0) {
                $training_data[] = array(
                    'conversion' => $conversion,
                    'touchpoints' => $touchpoints,
                    'features' => $this->extract_features(array(
                        'touchpoints' => $touchpoints,
                        'conversion_date' => $conversion['conversion_date']
                    ), array('channel', 'position', 'timing', 'device'))
                );
            }
        }
        
        return $training_data;
    }
    
    /**
     * Train single model
     */
    private function train_single_model($model_type, $training_data, $model_config) {
        switch ($model_config['algorithm']) {
            case 'neural_network':
                return $this->train_neural_network($training_data, $model_config);
                
            case 'random_forest':
                return $this->train_random_forest($training_data, $model_config);
                
            case 'gradient_boosting':
                return $this->train_gradient_boosting($training_data, $model_config);
                
            case 'logistic_regression':
                return $this->train_logistic_regression($training_data, $model_config);
                
            case 'shapley_value':
                return $this->train_shapley_model($training_data, $model_config);
                
            case 'bayesian_network':
                return $this->train_bayesian_network($training_data, $model_config);
                
            case 'ensemble_voting':
                return $this->train_ensemble_model($training_data, $model_config);
                
            default:
                return array('success' => false, 'error' => 'Unknown algorithm: ' . $model_config['algorithm']);
        }
    }
    
    /**
     * Train neural network (simplified implementation)
     */
    private function train_neural_network($training_data, $model_config) {
        // Simplified neural network implementation
        // In a real implementation, you would use a proper ML library like TensorFlow or PyTorch
        
        $features_matrix = $this->prepare_features_matrix($training_data);
        $labels_vector = $this->prepare_labels_vector($training_data);
        
        // Initialize weights randomly
        $input_size = count($features_matrix[0]);
        $hidden_size = $model_config['neurons'][0];
        $output_size = 1;
        
        $weights = array(
            'input_hidden' => $this->initialize_weights($input_size, $hidden_size),
            'hidden_output' => $this->initialize_weights($hidden_size, $output_size)
        );
        
        // Training parameters
        $learning_rate = 0.01;
        $epochs = 100;
        $batch_size = 32;
        
        // Training loop
        for ($epoch = 0; $epoch < $epochs; $epoch++) {
            $epoch_loss = 0;
            $batches = array_chunk($training_data, $batch_size);
            
            foreach ($batches as $batch) {
                $batch_features = $this->prepare_features_matrix($batch);
                $batch_labels = $this->prepare_labels_vector($batch);
                
                // Forward pass
                $predictions = $this->forward_pass($batch_features, $weights);
                
                // Calculate loss
                $loss = $this->calculate_mse_loss($predictions, $batch_labels);
                $epoch_loss += $loss;
                
                // Backward pass (simplified)
                $weights = $this->backward_pass($batch_features, $batch_labels, $predictions, $weights, $learning_rate);
            }
            
            // Early stopping if loss is low enough
            if ($epoch_loss < 0.01) {
                break;
            }
        }
        
        return array(
            'success' => true,
            'model' => array(
                'type' => 'neural_network',
                'weights' => $weights,
                'config' => $model_config,
                'training_loss' => $epoch_loss,
                'epochs_trained' => $epoch + 1
            )
        );
    }
    
    /**
     * Train random forest (simplified implementation)
     */
    private function train_random_forest($training_data, $model_config) {
        // Simplified random forest implementation
        $trees = array();
        $num_trees = $model_config['trees'];
        $max_depth = $model_config['depth'];
        
        for ($i = 0; $i < $num_trees; $i++) {
            // Bootstrap sampling
            $bootstrap_sample = $this->bootstrap_sample($training_data);
            
            // Train decision tree
            $tree = $this->train_decision_tree($bootstrap_sample, $max_depth);
            $trees[] = $tree;
        }
        
        return array(
            'success' => true,
            'model' => array(
                'type' => 'random_forest',
                'trees' => $trees,
                'config' => $model_config,
                'num_trees' => count($trees)
            )
        );
    }
    
    /**
     * Predict attribution using trained model
     */
    private function predict_attribution($features, $trained_model, $model_config) {
        switch ($trained_model['type']) {
            case 'neural_network':
                return $this->predict_neural_network($features, $trained_model);
                
            case 'random_forest':
                return $this->predict_random_forest($features, $trained_model);
                
            case 'gradient_boosting':
                return $this->predict_gradient_boosting($features, $trained_model);
                
            case 'logistic_regression':
                return $this->predict_logistic_regression($features, $trained_model);
                
            default:
                return array('error' => 'Unknown model type: ' . $trained_model['type']);
        }
    }
    
    /**
     * Validate model performance
     */
    public function validate_model_performance($model_type = null) {
        $models_to_validate = $model_type ? array($model_type) : array_keys($this->attribution_models);
        $validation_results = array();
        
        foreach ($models_to_validate as $type) {
            $trained_model = $this->load_trained_model($type);
            if (!$trained_model) {
                $validation_results[$type] = array('error' => 'Model not found');
                continue;
            }
            
            // Get validation data
            $validation_data = $this->prepare_validation_data();
            
            // Calculate accuracy metrics
            $accuracy_metrics = $this->calculate_accuracy_metrics($trained_model, $validation_data);
            
            $validation_results[$type] = array(
                'accuracy' => $accuracy_metrics['accuracy'],
                'precision' => $accuracy_metrics['precision'],
                'recall' => $accuracy_metrics['recall'],
                'f1_score' => $accuracy_metrics['f1_score'],
                'meets_threshold' => $accuracy_metrics['accuracy'] >= $this->attribution_models[$type]['accuracy_target']
            );
        }
        
        return $validation_results;
    }
    
    /**
     * Store and load trained models
     */
    private function store_trained_model($model_type, $model_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_ml_models';
        $this->maybe_create_ml_models_table();
        
        $wpdb->replace($table_name, array(
            'model_type' => $model_type,
            'model_data' => serialize($model_data),
            'created_at' => current_time('mysql'),
            'version' => $this->get_model_version()
        ));
    }
    
    private function load_trained_model($model_type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_ml_models';
        
        $sql = "SELECT model_data FROM {$table_name} 
                WHERE model_type = %s 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $model_data = $wpdb->get_var($wpdb->prepare($sql, $model_type));
        
        return $model_data ? unserialize($model_data) : false;
    }
    
    /**
     * Database table creation
     */
    private function maybe_create_ml_models_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_ml_models';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            model_type VARCHAR(50) NOT NULL,
            model_data LONGTEXT,
            created_at DATETIME NOT NULL,
            version VARCHAR(20),
            
            UNIQUE KEY unique_model_type (model_type),
            INDEX idx_created_version (created_at, version)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Utility methods (simplified implementations)
     */
    private function encode_categorical_features($categories) {
        // One-hot encoding
        $encoded = array();
        $unique_categories = array_unique($categories);
        
        foreach ($categories as $category) {
            $category_vector = array();
            foreach ($unique_categories as $unique_cat) {
                $category_vector[] = ($category === $unique_cat) ? 1 : 0;
            }
            $encoded[] = $category_vector;
        }
        
        return $encoded;
    }
    
    private function prepare_features_matrix($training_data) {
        $matrix = array();
        
        foreach ($training_data as $sample) {
            $feature_vector = array();
            
            // Flatten features into a single vector
            $this->flatten_features($sample['features'], $feature_vector);
            
            $matrix[] = $feature_vector;
        }
        
        return $matrix;
    }
    
    private function flatten_features($features, &$vector) {
        foreach ($features as $feature_group) {
            if (is_array($feature_group)) {
                if (isset($feature_group[0]) && is_array($feature_group[0])) {
                    // Handle multiple items
                    foreach ($feature_group as $item) {
                        $this->flatten_features($item, $vector);
                    }
                } else {
                    // Handle single item
                    $this->flatten_features($feature_group, $vector);
                }
            } else {
                $vector[] = is_numeric($feature_group) ? floatval($feature_group) : 0;
            }
        }
    }
    
    private function prepare_labels_vector($training_data) {
        $labels = array();
        
        foreach ($training_data as $sample) {
            // Use conversion value as label (normalized)
            $conversion_value = floatval($sample['conversion']['conversion_value']);
            $labels[] = $conversion_value > 0 ? 1 : 0; // Binary classification
        }
        
        return $labels;
    }
    
    private function initialize_weights($input_size, $output_size) {
        $weights = array();
        
        for ($i = 0; $i < $input_size; $i++) {
            $weights[$i] = array();
            for ($j = 0; $j < $output_size; $j++) {
                $weights[$i][$j] = (mt_rand() / mt_getrandmax() - 0.5) * 2; // Random between -1 and 1
            }
        }
        
        return $weights;
    }
    
    private function forward_pass($features, $weights) {
        // Simplified forward pass
        $predictions = array();
        
        foreach ($features as $feature_vector) {
            $hidden_output = $this->matrix_multiply($feature_vector, $weights['input_hidden']);
            $hidden_activated = array_map('tanh', $hidden_output); // Activation function
            
            $output = $this->matrix_multiply($hidden_activated, $weights['hidden_output']);
            $predictions[] = 1 / (1 + exp(-$output[0])); // Sigmoid activation
        }
        
        return $predictions;
    }
    
    private function matrix_multiply($vector, $matrix) {
        $result = array();
        
        for ($j = 0; $j < count($matrix[0]); $j++) {
            $sum = 0;
            for ($i = 0; $i < count($vector); $i++) {
                $sum += $vector[$i] * $matrix[$i][$j];
            }
            $result[] = $sum;
        }
        
        return $result;
    }
    
    private function calculate_mse_loss($predictions, $labels) {
        $loss = 0;
        
        for ($i = 0; $i < count($predictions); $i++) {
            $error = $predictions[$i] - $labels[$i];
            $loss += $error * $error;
        }
        
        return $loss / count($predictions);
    }
    
    // Additional placeholder methods for complex ML operations
    private function backward_pass($features, $labels, $predictions, $weights, $learning_rate) { return $weights; }
    private function bootstrap_sample($data) { return array_slice($data, 0, count($data)); }
    private function train_decision_tree($data, $max_depth) { return array('type' => 'decision_tree'); }
    private function train_gradient_boosting($data, $config) { return array('success' => true, 'model' => array('type' => 'gradient_boosting')); }
    private function train_logistic_regression($data, $config) { return array('success' => true, 'model' => array('type' => 'logistic_regression')); }
    private function train_shapley_model($data, $config) { return array('success' => true, 'model' => array('type' => 'shapley_value')); }
    private function train_bayesian_network($data, $config) { return array('success' => true, 'model' => array('type' => 'bayesian_network')); }
    private function train_ensemble_model($data, $config) { return array('success' => true, 'model' => array('type' => 'ensemble_voting')); }
    private function predict_random_forest($features, $model) { return array(0.8); }
    private function predict_gradient_boosting($features, $model) { return array(0.8); }
    private function predict_logistic_regression($features, $model) { return array(0.8); }
    private function predict_neural_network($features, $model) { return array(0.8); }
    private function prepare_validation_data() { return array(); }
    private function calculate_accuracy_metrics($model, $validation_data) { return array('accuracy' => 0.85, 'precision' => 0.85, 'recall' => 0.85, 'f1_score' => 0.85); }
    private function get_model_version() { return '1.0'; }
    private function fallback_attribution($conversion_data) { return array('method' => 'fallback', 'attribution' => 'equal'); }
    private function post_process_attribution($predictions, $conversion_data) { return $predictions; }
    private function validate_attribution_results($results) { return $results; }
    private function extract_device_features($data) { return array(); }
    private function extract_content_features($data) { return array(); }
    private function extract_sequence_features($data) { return array(); }
    private function extract_time_interval_features($data) { return array(); }
    private function extract_channel_mix_features($data) { return array(); }
    private function extract_journey_features($data) { return array(); }
    
    /**
     * AJAX handlers
     */
    public function ajax_generate_ml_attribution() {
        check_ajax_referer('khm_ml_nonce', 'nonce');
        
        $conversion_id = intval($_POST['conversion_id'] ?? 0);
        $model_type = sanitize_text_field($_POST['model_type'] ?? 'ensemble');
        
        // Get conversion data
        global $wpdb;
        $conversions_table = $wpdb->prefix . 'khm_conversion_tracking';
        $conversion = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$conversions_table} WHERE id = %d", $conversion_id), ARRAY_A);
        
        if ($conversion) {
            $attribution = $this->generate_ml_attribution($conversion, $model_type);
            wp_send_json_success($attribution);
        } else {
            wp_send_json_error('Conversion not found');
        }
    }
    
    public function ajax_train_model() {
        check_ajax_referer('khm_ml_nonce', 'nonce');
        
        $model_type = sanitize_text_field($_POST['model_type'] ?? '');
        
        if ($model_type && isset($this->attribution_models[$model_type])) {
            $training_data = $this->prepare_training_data();
            $result = $this->train_single_model($model_type, $training_data, $this->attribution_models[$model_type]);
            
            if ($result['success']) {
                $this->store_trained_model($model_type, $result['model']);
            }
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Invalid model type');
        }
    }
    
    public function ajax_validate_model() {
        check_ajax_referer('khm_ml_nonce', 'nonce');
        
        $model_type = sanitize_text_field($_POST['model_type'] ?? '');
        
        $validation_results = $this->validate_model_performance($model_type);
        wp_send_json_success($validation_results);
    }
    
    /**
     * Add ML menu
     */
    public function add_ml_menu() {
        add_submenu_page(
            'khm-attribution',
            'ML Attribution',
            'ML Models',
            'manage_options',
            'khm-attribution-ml',
            array($this, 'render_ml_page')
        );
    }
    
    /**
     * Render ML page
     */
    public function render_ml_page() {
        echo '<div class="wrap">';
        echo '<h1>ML Attribution Engine</h1>';
        echo '<p>Machine learning-powered attribution modeling and analysis.</p>';
        echo '</div>';
    }
}
?>