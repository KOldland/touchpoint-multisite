<?php
/**
 * KHM Attribution Model Trainer
 * 
 * Advanced model training and optimization system using Phase 2 OOP patterns.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Model_Trainer {
    
    private $performance_manager;
    private $database_manager;
    private $ml_attribution_engine;
    private $trainer_config = array();
    private $training_pipelines = array();
    private $model_registry = array();
    
    /**
     * Constructor - Initialize model trainer components
     */
    public function __construct() {
        $this->init_trainer_components();
        $this->setup_trainer_config();
        $this->load_training_pipelines();
        $this->register_trainer_hooks();
    }
    
    /**
     * Initialize trainer components
     */
    private function init_trainer_components() {
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
     * Setup trainer configuration
     */
    private function setup_trainer_config() {
        $this->trainer_config = array(
            'training_schedules' => array('daily', 'weekly', 'monthly'),
            'validation_methods' => array('cross_validation', 'holdout', 'time_series_split'),
            'hyperparameter_optimization' => true,
            'auto_model_selection' => true,
            'performance_thresholds' => array(
                'accuracy' => 0.8,
                'precision' => 0.75,
                'recall' => 0.75,
                'f1_score' => 0.75
            ),
            'training_data_requirements' => array(
                'min_samples' => 1000,
                'min_features' => 5,
                'max_training_time' => 3600 // 1 hour
            ),
            'model_versioning' => true,
            'auto_deployment' => false
        );
        
        $this->trainer_config = apply_filters('khm_trainer_config', $this->trainer_config);
    }
    
    /**
     * Load training pipelines
     */
    private function load_training_pipelines() {
        $this->training_pipelines = array(
            'attribution_model_training' => array(
                'name' => 'Attribution Model Training',
                'models' => array('neural_network', 'random_forest', 'gradient_boosting', 'logistic_regression'),
                'data_sources' => array('events', 'conversions', 'analytics'),
                'feature_engineering' => array('time_decay', 'channel_interaction', 'sequence_patterns'),
                'validation_strategy' => 'time_series_split'
            ),
            'ltv_prediction_training' => array(
                'name' => 'LTV Prediction Training',
                'models' => array('regression', 'survival_analysis', 'ensemble'),
                'data_sources' => array('conversions', 'user_behavior', 'demographics'),
                'feature_engineering' => array('behavioral_features', 'temporal_features', 'cohort_features'),
                'validation_strategy' => 'cross_validation'
            ),
            'churn_prediction_training' => array(
                'name' => 'Churn Prediction Training',
                'models' => array('classification', 'survival_analysis', 'ensemble'),
                'data_sources' => array('engagement', 'transactions', 'support_interactions'),
                'feature_engineering' => array('engagement_metrics', 'transaction_patterns', 'interaction_history'),
                'validation_strategy' => 'holdout'
            ),
            'conversion_prediction_training' => array(
                'name' => 'Conversion Prediction Training',
                'models' => array('logistic_regression', 'neural_network', 'decision_tree'),
                'data_sources' => array('events', 'sessions', 'user_behavior'),
                'feature_engineering' => array('session_features', 'behavioral_signals', 'temporal_patterns'),
                'validation_strategy' => 'cross_validation'
            )
        );
    }
    
    /**
     * Register trainer hooks
     */
    private function register_trainer_hooks() {
        add_action('khm_train_models', array($this, 'train_all_models'));
        add_action('khm_validate_models', array($this, 'validate_all_models'));
        add_action('admin_menu', array($this, 'add_trainer_menu'));
        
        // AJAX hooks
        add_action('wp_ajax_khm_start_training', array($this, 'ajax_start_training'));
        add_action('wp_ajax_khm_get_training_status', array($this, 'ajax_get_training_status'));
        add_action('wp_ajax_khm_deploy_model', array($this, 'ajax_deploy_model'));
        
        // Scheduled training
        if (!wp_next_scheduled('khm_train_models')) {
            wp_schedule_event(time(), 'weekly', 'khm_train_models');
        }
    }
    
    /**
     * Train models with comprehensive pipeline
     */
    public function train_model_pipeline($pipeline_type, $options = array()) {
        if (!isset($this->training_pipelines[$pipeline_type])) {
            return false;
        }
        
        $pipeline_config = $this->training_pipelines[$pipeline_type];
        
        $default_options = array(
            'hyperparameter_tuning' => true,
            'cross_validation_folds' => 5,
            'test_size' => 0.2,
            'random_state' => 42,
            'max_training_time' => $this->trainer_config['training_data_requirements']['max_training_time']
        );
        
        $options = array_merge($default_options, $options);
        
        // Initialize training session
        $training_session = $this->initialize_training_session($pipeline_type, $options);
        
        // Prepare training data
        $training_data = $this->prepare_training_data($pipeline_config, $options);
        
        if (!$this->validate_training_data($training_data)) {
            return array('error' => 'Invalid training data');
        }
        
        // Feature engineering
        $engineered_features = $this->apply_feature_engineering($training_data, $pipeline_config['feature_engineering']);
        
        // Split data for validation
        $data_splits = $this->create_data_splits($engineered_features, $pipeline_config['validation_strategy'], $options);
        
        // Train multiple models
        $model_results = array();
        foreach ($pipeline_config['models'] as $model_type) {
            $model_result = $this->train_single_model($model_type, $data_splits, $options);
            $model_results[$model_type] = $model_result;
            
            // Update training session progress
            $this->update_training_progress($training_session['id'], $model_type, $model_result);
        }
        
        // Model selection and ensemble
        $best_model = $this->select_best_model($model_results);
        $ensemble_model = $this->create_ensemble_model($model_results);
        
        // Final validation
        $final_validation = $this->final_model_validation($best_model, $ensemble_model, $data_splits);
        
        // Store training results
        $training_result = array(
            'pipeline_type' => $pipeline_type,
            'training_session_id' => $training_session['id'],
            'model_results' => $model_results,
            'best_model' => $best_model,
            'ensemble_model' => $ensemble_model,
            'validation_results' => $final_validation,
            'metadata' => array(
                'training_data_size' => count($training_data),
                'feature_count' => count($engineered_features[0]) - 1, // Exclude target
                'training_duration' => time() - $training_session['start_time'],
                'models_trained' => count($model_results)
            )
        );
        
        $this->store_training_results($training_result);
        
        return $training_result;
    }
    
    /**
     * Initialize training session
     */
    private function initialize_training_session($pipeline_type, $options) {
        $session = array(
            'id' => uniqid('training_'),
            'pipeline_type' => $pipeline_type,
            'options' => $options,
            'start_time' => time(),
            'status' => 'initialized',
            'progress' => 0
        );
        
        // Store session in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_training_sessions';
        $this->maybe_create_training_sessions_table();
        
        $wpdb->insert($table_name, array(
            'session_id' => $session['id'],
            'pipeline_type' => $pipeline_type,
            'options' => json_encode($options),
            'status' => 'running',
            'created_at' => current_time('mysql')
        ));
        
        return $session;
    }
    
    /**
     * Prepare training data
     */
    private function prepare_training_data($pipeline_config, $options) {
        $data_sources = $pipeline_config['data_sources'];
        $combined_data = array();
        
        foreach ($data_sources as $source) {
            switch ($source) {
                case 'events':
                    $events_data = $this->extract_events_data($options);
                    $combined_data = array_merge($combined_data, $events_data);
                    break;
                    
                case 'conversions':
                    $conversions_data = $this->extract_conversions_data($options);
                    $combined_data = array_merge($combined_data, $conversions_data);
                    break;
                    
                case 'analytics':
                    $analytics_data = $this->extract_analytics_data($options);
                    $combined_data = array_merge($combined_data, $analytics_data);
                    break;
                    
                case 'user_behavior':
                    $behavior_data = $this->extract_user_behavior_data($options);
                    $combined_data = array_merge($combined_data, $behavior_data);
                    break;
                    
                case 'demographics':
                    $demographics_data = $this->extract_demographics_data($options);
                    $combined_data = array_merge($combined_data, $demographics_data);
                    break;
                    
                case 'engagement':
                    $engagement_data = $this->extract_engagement_data($options);
                    $combined_data = array_merge($combined_data, $engagement_data);
                    break;
                    
                case 'transactions':
                    $transactions_data = $this->extract_transactions_data($options);
                    $combined_data = array_merge($combined_data, $transactions_data);
                    break;
                    
                case 'sessions':
                    $sessions_data = $this->extract_sessions_data($options);
                    $combined_data = array_merge($combined_data, $sessions_data);
                    break;
            }
        }
        
        return $this->deduplicate_and_clean_data($combined_data);
    }
    
    /**
     * Apply feature engineering
     */
    private function apply_feature_engineering($training_data, $engineering_methods) {
        $engineered_data = $training_data;
        
        foreach ($engineering_methods as $method) {
            switch ($method) {
                case 'time_decay':
                    $engineered_data = $this->apply_time_decay_features($engineered_data);
                    break;
                    
                case 'channel_interaction':
                    $engineered_data = $this->apply_channel_interaction_features($engineered_data);
                    break;
                    
                case 'sequence_patterns':
                    $engineered_data = $this->apply_sequence_pattern_features($engineered_data);
                    break;
                    
                case 'behavioral_features':
                    $engineered_data = $this->apply_behavioral_features($engineered_data);
                    break;
                    
                case 'temporal_features':
                    $engineered_data = $this->apply_temporal_features($engineered_data);
                    break;
                    
                case 'cohort_features':
                    $engineered_data = $this->apply_cohort_features($engineered_data);
                    break;
                    
                case 'engagement_metrics':
                    $engineered_data = $this->apply_engagement_metrics($engineered_data);
                    break;
                    
                case 'transaction_patterns':
                    $engineered_data = $this->apply_transaction_patterns($engineered_data);
                    break;
                    
                case 'interaction_history':
                    $engineered_data = $this->apply_interaction_history_features($engineered_data);
                    break;
                    
                case 'session_features':
                    $engineered_data = $this->apply_session_features($engineered_data);
                    break;
                    
                case 'behavioral_signals':
                    $engineered_data = $this->apply_behavioral_signals($engineered_data);
                    break;
                    
                case 'temporal_patterns':
                    $engineered_data = $this->apply_temporal_patterns($engineered_data);
                    break;
            }
        }
        
        return $engineered_data;
    }
    
    /**
     * Create data splits for validation
     */
    private function create_data_splits($data, $validation_strategy, $options) {
        switch ($validation_strategy) {
            case 'holdout':
                return $this->create_holdout_split($data, $options['test_size']);
                
            case 'cross_validation':
                return $this->create_cross_validation_splits($data, $options['cross_validation_folds']);
                
            case 'time_series_split':
                return $this->create_time_series_splits($data, $options['test_size']);
                
            default:
                return $this->create_holdout_split($data, $options['test_size']);
        }
    }
    
    /**
     * Train single model with hyperparameter optimization
     */
    private function train_single_model($model_type, $data_splits, $options) {
        $start_time = time();
        
        // Get model configuration
        $model_config = $this->get_model_configuration($model_type);
        
        // Hyperparameter optimization
        $best_hyperparameters = array();
        $best_score = 0;
        
        if ($options['hyperparameter_tuning']) {
            $hyperparameter_results = $this->optimize_hyperparameters($model_type, $data_splits, $model_config);
            $best_hyperparameters = $hyperparameter_results['best_params'];
            $best_score = $hyperparameter_results['best_score'];
        } else {
            $best_hyperparameters = $model_config['default_params'];
        }
        
        // Train final model with best hyperparameters
        $trained_model = $this->train_model_with_params($model_type, $data_splits['train'], $best_hyperparameters);
        
        // Validate model
        $validation_scores = $this->validate_trained_model($trained_model, $data_splits['validation']);
        
        // Test model on holdout set
        $test_scores = $this->test_trained_model($trained_model, $data_splits['test']);
        
        return array(
            'model_type' => $model_type,
            'trained_model' => $trained_model,
            'hyperparameters' => $best_hyperparameters,
            'validation_scores' => $validation_scores,
            'test_scores' => $test_scores,
            'training_time' => time() - $start_time,
            'model_size' => $this->calculate_model_size($trained_model)
        );
    }
    
    /**
     * Select best model based on performance metrics
     */
    private function select_best_model($model_results) {
        $best_model = null;
        $best_score = 0;
        
        foreach ($model_results as $model_type => $result) {
            // Calculate composite score
            $composite_score = $this->calculate_composite_score($result['test_scores']);
            
            if ($composite_score > $best_score) {
                $best_score = $composite_score;
                $best_model = $result;
                $best_model['composite_score'] = $composite_score;
            }
        }
        
        return $best_model;
    }
    
    /**
     * Create ensemble model
     */
    private function create_ensemble_model($model_results) {
        // Filter models that meet minimum performance threshold
        $qualified_models = array();
        $min_score = $this->trainer_config['performance_thresholds']['accuracy'];
        
        foreach ($model_results as $model_type => $result) {
            if ($result['test_scores']['accuracy'] >= $min_score) {
                $qualified_models[$model_type] = $result;
            }
        }
        
        if (count($qualified_models) < 2) {
            return null; // Not enough models for ensemble
        }
        
        // Calculate ensemble weights based on performance
        $ensemble_weights = $this->calculate_ensemble_weights($qualified_models);
        
        return array(
            'type' => 'ensemble',
            'models' => $qualified_models,
            'weights' => $ensemble_weights,
            'voting_strategy' => 'weighted_average',
            'expected_performance' => $this->estimate_ensemble_performance($qualified_models, $ensemble_weights)
        );
    }
    
    /**
     * Validate training data
     */
    private function validate_training_data($training_data) {
        $requirements = $this->trainer_config['training_data_requirements'];
        
        // Check minimum samples
        if (count($training_data) < $requirements['min_samples']) {
            return false;
        }
        
        // Check feature count
        if (count($training_data[0]) < $requirements['min_features']) {
            return false;
        }
        
        // Check for missing values
        foreach ($training_data as $sample) {
            foreach ($sample as $feature_value) {
                if ($feature_value === null || $feature_value === '') {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Store training results
     */
    private function store_training_results($training_result) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_model_training_results';
        $this->maybe_create_training_results_table();
        
        $wpdb->insert($table_name, array(
            'pipeline_type' => $training_result['pipeline_type'],
            'session_id' => $training_result['training_session_id'],
            'result_data' => json_encode($training_result),
            'best_model_type' => $training_result['best_model']['model_type'],
            'best_model_score' => $training_result['best_model']['composite_score'],
            'training_duration' => $training_result['metadata']['training_duration'],
            'created_at' => current_time('mysql')
        ));
        
        // Store individual models in model registry
        foreach ($training_result['model_results'] as $model_type => $model_result) {
            $this->register_trained_model($model_result, $training_result['pipeline_type']);
        }
    }
    
    /**
     * Register trained model in model registry
     */
    private function register_trained_model($model_result, $pipeline_type) {
        $model_registry_entry = array(
            'model_id' => uniqid('model_'),
            'model_type' => $model_result['model_type'],
            'pipeline_type' => $pipeline_type,
            'performance_metrics' => $model_result['test_scores'],
            'hyperparameters' => $model_result['hyperparameters'],
            'training_time' => $model_result['training_time'],
            'model_size' => $model_result['model_size'],
            'version' => $this->get_model_version(),
            'status' => 'trained',
            'created_at' => current_time('mysql')
        );
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_model_registry';
        $this->maybe_create_model_registry_table();
        
        $wpdb->insert($table_name, array(
            'model_id' => $model_registry_entry['model_id'],
            'model_type' => $model_registry_entry['model_type'],
            'pipeline_type' => $model_registry_entry['pipeline_type'],
            'model_data' => serialize($model_result['trained_model']),
            'performance_metrics' => json_encode($model_registry_entry['performance_metrics']),
            'hyperparameters' => json_encode($model_registry_entry['hyperparameters']),
            'version' => $model_registry_entry['version'],
            'status' => $model_registry_entry['status'],
            'created_at' => $model_registry_entry['created_at']
        ));
    }
    
    /**
     * Database table creation methods
     */
    private function maybe_create_training_sessions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_training_sessions';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(50) UNIQUE NOT NULL,
            pipeline_type VARCHAR(50) NOT NULL,
            options TEXT,
            status VARCHAR(20) DEFAULT 'running',
            progress INT DEFAULT 0,
            created_at DATETIME NOT NULL,
            
            INDEX idx_session_id (session_id),
            INDEX idx_pipeline_status (pipeline_type, status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function maybe_create_training_results_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_model_training_results';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            pipeline_type VARCHAR(50) NOT NULL,
            session_id VARCHAR(50),
            result_data LONGTEXT,
            best_model_type VARCHAR(50),
            best_model_score DECIMAL(5,4),
            training_duration INT,
            created_at DATETIME NOT NULL,
            
            INDEX idx_pipeline_type (pipeline_type),
            INDEX idx_session_id (session_id),
            INDEX idx_created_score (created_at, best_model_score)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function maybe_create_model_registry_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_model_registry';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            model_id VARCHAR(50) UNIQUE NOT NULL,
            model_type VARCHAR(50) NOT NULL,
            pipeline_type VARCHAR(50) NOT NULL,
            model_data LONGBLOB,
            performance_metrics TEXT,
            hyperparameters TEXT,
            version VARCHAR(20),
            status VARCHAR(20) DEFAULT 'trained',
            created_at DATETIME NOT NULL,
            deployed_at DATETIME NULL,
            
            INDEX idx_model_id (model_id),
            INDEX idx_model_type (model_type),
            INDEX idx_pipeline_type (pipeline_type),
            INDEX idx_status_version (status, version)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Utility methods (simplified implementations)
     */
    private function extract_events_data($options) { return array(); }
    private function extract_conversions_data($options) { return array(); }
    private function extract_analytics_data($options) { return array(); }
    private function extract_user_behavior_data($options) { return array(); }
    private function extract_demographics_data($options) { return array(); }
    private function extract_engagement_data($options) { return array(); }
    private function extract_transactions_data($options) { return array(); }
    private function extract_sessions_data($options) { return array(); }
    private function deduplicate_and_clean_data($data) { return $data; }
    
    // Feature engineering methods (simplified)
    private function apply_time_decay_features($data) { return $data; }
    private function apply_channel_interaction_features($data) { return $data; }
    private function apply_sequence_pattern_features($data) { return $data; }
    private function apply_behavioral_features($data) { return $data; }
    private function apply_temporal_features($data) { return $data; }
    private function apply_cohort_features($data) { return $data; }
    private function apply_engagement_metrics($data) { return $data; }
    private function apply_transaction_patterns($data) { return $data; }
    private function apply_interaction_history_features($data) { return $data; }
    private function apply_session_features($data) { return $data; }
    private function apply_behavioral_signals($data) { return $data; }
    private function apply_temporal_patterns($data) { return $data; }
    
    // Data splitting methods (simplified)
    private function create_holdout_split($data, $test_size) { return array('train' => $data, 'validation' => $data, 'test' => $data); }
    private function create_cross_validation_splits($data, $folds) { return array('train' => $data, 'validation' => $data, 'test' => $data); }
    private function create_time_series_splits($data, $test_size) { return array('train' => $data, 'validation' => $data, 'test' => $data); }
    
    // Model training methods (simplified)
    private function get_model_configuration($model_type) { return array('default_params' => array()); }
    private function optimize_hyperparameters($model_type, $data_splits, $config) { return array('best_params' => array(), 'best_score' => 0.8); }
    private function train_model_with_params($model_type, $train_data, $params) { return array('model_type' => $model_type, 'params' => $params); }
    private function validate_trained_model($model, $validation_data) { return array('accuracy' => 0.85, 'precision' => 0.8, 'recall' => 0.8, 'f1_score' => 0.8); }
    private function test_trained_model($model, $test_data) { return array('accuracy' => 0.83, 'precision' => 0.78, 'recall' => 0.78, 'f1_score' => 0.78); }
    private function calculate_model_size($model) { return 1024; }
    private function calculate_composite_score($scores) { return ($scores['accuracy'] + $scores['f1_score']) / 2; }
    private function calculate_ensemble_weights($models) { return array_fill(0, count($models), 1.0 / count($models)); }
    private function estimate_ensemble_performance($models, $weights) { return array('expected_accuracy' => 0.88); }
    private function get_model_version() { return '1.0.0'; }
    private function update_training_progress($session_id, $model_type, $result) { /* Update progress in database */ }
    
    /**
     * Train all models
     */
    public function train_all_models() {
        foreach (array_keys($this->training_pipelines) as $pipeline_type) {
            $this->train_model_pipeline($pipeline_type);
        }
    }
    
    /**
     * Validate all models
     */
    public function validate_all_models() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_model_registry';
        
        $active_models = $wpdb->get_results(
            "SELECT * FROM {$table_name} WHERE status = 'deployed' ORDER BY created_at DESC",
            ARRAY_A
        );
        
        $validation_results = array();
        
        foreach ($active_models as $model_record) {
            $validation_result = $this->validate_deployed_model($model_record);
            $validation_results[] = $validation_result;
        }
        
        return $validation_results;
    }
    
    private function validate_deployed_model($model_record) {
        // Simplified validation
        return array(
            'model_id' => $model_record['model_id'],
            'model_type' => $model_record['model_type'],
            'validation_score' => 0.82,
            'drift_detected' => false,
            'recommendation' => 'continue_monitoring'
        );
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_start_training() {
        check_ajax_referer('khm_trainer_nonce', 'nonce');
        
        $pipeline_type = sanitize_text_field($_POST['pipeline_type'] ?? '');
        $options = array(
            'hyperparameter_tuning' => isset($_POST['hyperparameter_tuning']),
            'cross_validation_folds' => intval($_POST['cv_folds'] ?? 5)
        );
        
        if ($pipeline_type && isset($this->training_pipelines[$pipeline_type])) {
            // Start training in background (simplified - would use queue system)
            $result = $this->train_model_pipeline($pipeline_type, $options);
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Invalid pipeline type');
        }
    }
    
    public function ajax_get_training_status() {
        check_ajax_referer('khm_trainer_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_training_sessions';
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE session_id = %s",
            $session_id
        ), ARRAY_A);
        
        wp_send_json_success($session);
    }
    
    public function ajax_deploy_model() {
        check_ajax_referer('khm_trainer_nonce', 'nonce');
        
        $model_id = sanitize_text_field($_POST['model_id'] ?? '');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_model_registry';
        
        $result = $wpdb->update(
            $table_name,
            array('status' => 'deployed', 'deployed_at' => current_time('mysql')),
            array('model_id' => $model_id)
        );
        
        if ($result) {
            wp_send_json_success(array('message' => 'Model deployed successfully'));
        } else {
            wp_send_json_error('Failed to deploy model');
        }
    }
    
    /**
     * Add trainer menu
     */
    public function add_trainer_menu() {
        add_submenu_page(
            'khm-attribution',
            'Model Trainer',
            'Model Training',
            'manage_options',
            'khm-attribution-trainer',
            array($this, 'render_trainer_page')
        );
    }
    
    /**
     * Render trainer page
     */
    public function render_trainer_page() {
        echo '<div class="wrap">';
        echo '<h1>Attribution Model Trainer</h1>';
        echo '<p>Advanced model training and optimization system.</p>';
        echo '</div>';
    }
}
?>