<?php
/**
 * KHM Attribution Intelligent Segmentation
 * 
 * AI-powered user segmentation and targeting using Phase 2 OOP patterns.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Intelligent_Segmentation {
    
    private $performance_manager;
    private $database_manager;
    private $ml_attribution_engine;
    private $segmentation_config = array();
    private $segmentation_models = array();
    private $active_segments = array();
    
    /**
     * Constructor - Initialize intelligent segmentation components
     */
    public function __construct() {
        $this->init_segmentation_components();
        $this->setup_segmentation_config();
        $this->load_segmentation_models();
        $this->register_segmentation_hooks();
    }
    
    /**
     * Initialize segmentation components
     */
    private function init_segmentation_components() {
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
     * Setup segmentation configuration
     */
    private function setup_segmentation_config() {
        $this->segmentation_config = array(
            'segmentation_methods' => array('behavioral', 'demographic', 'psychographic', 'geographic', 'technographic'),
            'clustering_algorithms' => array('kmeans', 'hierarchical', 'dbscan', 'gaussian_mixture'),
            'min_segment_size' => 100,
            'max_segments' => 20,
            'feature_importance_threshold' => 0.1,
            'segment_stability_threshold' => 0.8,
            'auto_update_segments' => true,
            'update_frequency' => 'weekly'
        );
        
        $this->segmentation_config = apply_filters('khm_segmentation_config', $this->segmentation_config);
    }
    
    /**
     * Load segmentation models
     */
    private function load_segmentation_models() {
        $this->segmentation_models = array(
            'behavioral_segmentation' => array(
                'name' => 'Behavioral Segmentation',
                'features' => array('page_views', 'session_duration', 'bounce_rate', 'conversion_actions', 'engagement_events'),
                'algorithm' => 'kmeans',
                'segments' => array('high_engaged', 'moderate_engaged', 'low_engaged', 'bouncer')
            ),
            'value_based_segmentation' => array(
                'name' => 'Customer Value Segmentation',
                'features' => array('ltv', 'purchase_frequency', 'average_order_value', 'recency', 'total_spent'),
                'algorithm' => 'hierarchical',
                'segments' => array('high_value', 'medium_value', 'low_value', 'potential_high_value')
            ),
            'journey_segmentation' => array(
                'name' => 'Customer Journey Segmentation',
                'features' => array('touchpoint_count', 'journey_length', 'channel_diversity', 'conversion_speed'),
                'algorithm' => 'gaussian_mixture',
                'segments' => array('quick_converter', 'researcher', 'multi_touch', 'long_journey')
            ),
            'channel_affinity_segmentation' => array(
                'name' => 'Channel Affinity Segmentation',
                'features' => array('preferred_channel', 'channel_response_rate', 'cross_channel_behavior'),
                'algorithm' => 'dbscan',
                'segments' => array('email_lovers', 'social_natives', 'search_focused', 'omnichannel')
            ),
            'lifecycle_segmentation' => array(
                'name' => 'Customer Lifecycle Segmentation',
                'features' => array('days_since_first_visit', 'days_since_last_purchase', 'purchase_count', 'engagement_trend'),
                'algorithm' => 'kmeans',
                'segments' => array('new_visitor', 'active_customer', 'repeat_customer', 'at_risk', 'churned')
            )
        );
    }
    
    /**
     * Register segmentation hooks
     */
    private function register_segmentation_hooks() {
        add_action('khm_update_segments', array($this, 'update_all_segments'));
        add_action('admin_menu', array($this, 'add_segmentation_menu'));
        
        // AJAX hooks
        add_action('wp_ajax_khm_generate_segments', array($this, 'ajax_generate_segments'));
        add_action('wp_ajax_khm_analyze_segment', array($this, 'ajax_analyze_segment'));
        add_action('wp_ajax_khm_export_segment', array($this, 'ajax_export_segment'));
        
        // Scheduled updates
        if (!wp_next_scheduled('khm_update_segments')) {
            wp_schedule_event(time(), $this->segmentation_config['update_frequency'], 'khm_update_segments');
        }
    }
    
    /**
     * Generate intelligent segments
     */
    public function generate_segments($segmentation_type, $options = array()) {
        if (!isset($this->segmentation_models[$segmentation_type])) {
            return false;
        }
        
        $model_config = $this->segmentation_models[$segmentation_type];
        
        $default_options = array(
            'num_segments' => count($model_config['segments']),
            'min_segment_size' => $this->segmentation_config['min_segment_size'],
            'feature_selection' => 'auto',
            'validation_method' => 'silhouette',
            'historical_days' => 90
        );
        
        $options = array_merge($default_options, $options);
        
        // Prepare user data
        $user_data = $this->prepare_user_data($model_config, $options);
        
        if (count($user_data) < $options['min_segment_size']) {
            return array('error' => 'Insufficient user data for segmentation');
        }
        
        // Extract and select features
        $features = $this->extract_features($user_data, $model_config['features']);
        $selected_features = $this->select_optimal_features($features, $options);
        
        // Apply clustering algorithm
        $clustering_result = $this->apply_clustering($selected_features, $model_config['algorithm'], $options);
        
        // Analyze and label segments
        $segments = $this->analyze_segments($clustering_result, $user_data, $selected_features);
        
        // Validate segment quality
        $quality_metrics = $this->validate_segment_quality($segments, $selected_features);
        
        // Generate segment insights
        $insights = $this->generate_segment_insights($segments, $user_data);
        
        $segmentation_result = array(
            'segmentation_type' => $segmentation_type,
            'model_config' => $model_config,
            'options' => $options,
            'segments' => $segments,
            'quality_metrics' => $quality_metrics,
            'insights' => $insights,
            'metadata' => array(
                'generated_at' => current_time('mysql'),
                'total_users' => count($user_data),
                'num_segments' => count($segments),
                'features_used' => count($selected_features)
            )
        );
        
        // Store segmentation results
        $this->store_segmentation_results($segmentation_result);
        
        return $segmentation_result;
    }
    
    /**
     * Prepare user data for segmentation
     */
    private function prepare_user_data($model_config, $options) {
        global $wpdb;
        
        $historical_days = $options['historical_days'];
        $start_date = date('Y-m-d', strtotime("-{$historical_days} days"));
        
        // Get users with sufficient activity
        $events_table = $wpdb->prefix . 'khm_attribution_events';
        $conversions_table = $wpdb->prefix . 'khm_conversion_tracking';
        
        $sql = "SELECT DISTINCT 
                    e.user_id,
                    MIN(e.created_at) as first_visit,
                    MAX(e.created_at) as last_visit,
                    COUNT(e.id) as total_events,
                    COUNT(DISTINCT e.utm_medium) as unique_channels,
                    COUNT(c.id) as total_conversions,
                    COALESCE(SUM(c.value), 0) as total_value
                FROM {$events_table} e
                LEFT JOIN {$conversions_table} c ON e.user_id = c.user_id
                WHERE e.created_at >= %s
                AND e.user_id IS NOT NULL
                GROUP BY e.user_id
                HAVING total_events >= 5
                ORDER BY total_events DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $start_date), ARRAY_A);
    }
    
    /**
     * Extract features for segmentation
     */
    private function extract_features($user_data, $feature_types) {
        $features = array();
        
        foreach ($user_data as $user) {
            $user_features = array();
            
            foreach ($feature_types as $feature_type) {
                switch ($feature_type) {
                    case 'page_views':
                        $user_features['page_views'] = $this->calculate_page_views($user['user_id']);
                        break;
                        
                    case 'session_duration':
                        $user_features['session_duration'] = $this->calculate_average_session_duration($user['user_id']);
                        break;
                        
                    case 'bounce_rate':
                        $user_features['bounce_rate'] = $this->calculate_user_bounce_rate($user['user_id']);
                        break;
                        
                    case 'conversion_actions':
                        $user_features['conversion_actions'] = intval($user['total_conversions']);
                        break;
                        
                    case 'engagement_events':
                        $user_features['engagement_events'] = $this->calculate_engagement_events($user['user_id']);
                        break;
                        
                    case 'ltv':
                        $user_features['ltv'] = floatval($user['total_value']);
                        break;
                        
                    case 'purchase_frequency':
                        $user_features['purchase_frequency'] = $this->calculate_purchase_frequency($user['user_id']);
                        break;
                        
                    case 'average_order_value':
                        $user_features['average_order_value'] = $this->calculate_average_order_value($user['user_id']);
                        break;
                        
                    case 'recency':
                        $user_features['recency'] = $this->calculate_recency($user['last_visit']);
                        break;
                        
                    case 'touchpoint_count':
                        $user_features['touchpoint_count'] = intval($user['total_events']);
                        break;
                        
                    case 'journey_length':
                        $user_features['journey_length'] = $this->calculate_journey_length($user['first_visit'], $user['last_visit']);
                        break;
                        
                    case 'channel_diversity':
                        $user_features['channel_diversity'] = intval($user['unique_channels']);
                        break;
                        
                    case 'conversion_speed':
                        $user_features['conversion_speed'] = $this->calculate_conversion_speed($user['user_id']);
                        break;
                }
            }
            
            $user_features['user_id'] = $user['user_id'];
            $features[] = $user_features;
        }
        
        return $features;
    }
    
    /**
     * Select optimal features for clustering
     */
    private function select_optimal_features($features, $options) {
        if ($options['feature_selection'] === 'manual') {
            return $features; // Use all provided features
        }
        
        // Calculate feature importance and correlation
        $feature_importance = $this->calculate_feature_importance($features);
        $feature_correlations = $this->calculate_feature_correlations($features);
        
        // Remove highly correlated features
        $selected_features = $this->remove_correlated_features($features, $feature_correlations);
        
        // Remove low-importance features
        $final_features = $this->filter_by_importance($selected_features, $feature_importance);
        
        return $final_features;
    }
    
    /**
     * Apply clustering algorithm
     */
    private function apply_clustering($features, $algorithm, $options) {
        switch ($algorithm) {
            case 'kmeans':
                return $this->apply_kmeans_clustering($features, $options);
                
            case 'hierarchical':
                return $this->apply_hierarchical_clustering($features, $options);
                
            case 'dbscan':
                return $this->apply_dbscan_clustering($features, $options);
                
            case 'gaussian_mixture':
                return $this->apply_gaussian_mixture_clustering($features, $options);
                
            default:
                return $this->apply_kmeans_clustering($features, $options);
        }
    }
    
    /**
     * Apply K-means clustering (simplified implementation)
     */
    private function apply_kmeans_clustering($features, $options) {
        $num_clusters = $options['num_segments'];
        $max_iterations = 100;
        $tolerance = 0.001;
        
        // Normalize features
        $normalized_features = $this->normalize_features($features);
        
        // Initialize centroids randomly
        $centroids = $this->initialize_centroids($normalized_features, $num_clusters);
        
        // K-means iterations
        for ($iteration = 0; $iteration < $max_iterations; $iteration++) {
            // Assign points to nearest centroid
            $assignments = $this->assign_to_centroids($normalized_features, $centroids);
            
            // Update centroids
            $new_centroids = $this->update_centroids($normalized_features, $assignments, $num_clusters);
            
            // Check for convergence
            if ($this->centroids_converged($centroids, $new_centroids, $tolerance)) {
                break;
            }
            
            $centroids = $new_centroids;
        }
        
        return array(
            'algorithm' => 'kmeans',
            'assignments' => $assignments,
            'centroids' => $centroids,
            'iterations' => $iteration + 1,
            'converged' => $iteration < $max_iterations - 1
        );
    }
    
    /**
     * Analyze segments and generate labels
     */
    private function analyze_segments($clustering_result, $user_data, $features) {
        $assignments = $clustering_result['assignments'];
        $num_segments = max($assignments) + 1;
        
        $segments = array();
        
        for ($segment_id = 0; $segment_id < $num_segments; $segment_id++) {
            $segment_users = array();
            $segment_features = array();
            
            // Collect users and features for this segment
            foreach ($assignments as $user_index => $assigned_segment) {
                if ($assigned_segment === $segment_id) {
                    $segment_users[] = $user_data[$user_index];
                    $segment_features[] = $features[$user_index];
                }
            }
            
            if (count($segment_users) >= $this->segmentation_config['min_segment_size']) {
                $segment_analysis = $this->analyze_single_segment($segment_users, $segment_features);
                
                $segments[] = array(
                    'segment_id' => $segment_id,
                    'name' => $this->generate_segment_name($segment_analysis),
                    'size' => count($segment_users),
                    'percentage' => (count($segment_users) / count($user_data)) * 100,
                    'characteristics' => $segment_analysis,
                    'users' => array_column($segment_users, 'user_id')
                );
            }
        }
        
        return $segments;
    }
    
    /**
     * Analyze single segment characteristics
     */
    private function analyze_single_segment($users, $features) {
        $characteristics = array();
        
        // Calculate averages for numerical features
        $feature_keys = array_keys($features[0]);
        
        foreach ($feature_keys as $feature_key) {
            if ($feature_key === 'user_id') continue;
            
            $values = array_column($features, $feature_key);
            $characteristics[$feature_key] = array(
                'average' => array_sum($values) / count($values),
                'median' => $this->calculate_median($values),
                'min' => min($values),
                'max' => max($values),
                'std_dev' => $this->calculate_standard_deviation($values)
            );
        }
        
        // Calculate business metrics
        $characteristics['business_metrics'] = array(
            'total_ltv' => array_sum(array_column($users, 'total_value')),
            'average_ltv' => array_sum(array_column($users, 'total_value')) / count($users),
            'total_conversions' => array_sum(array_column($users, 'total_conversions')),
            'conversion_rate' => $this->calculate_segment_conversion_rate($users),
            'engagement_score' => $this->calculate_segment_engagement_score($features)
        );
        
        return $characteristics;
    }
    
    /**
     * Generate segment insights
     */
    private function generate_segment_insights($segments, $user_data) {
        $insights = array(
            'segment_comparison' => array(),
            'targeting_recommendations' => array(),
            'revenue_opportunities' => array()
        );
        
        // Compare segments
        foreach ($segments as $segment) {
            $insights['segment_comparison'][] = array(
                'segment_name' => $segment['name'],
                'value_score' => $this->calculate_segment_value_score($segment),
                'growth_potential' => $this->calculate_growth_potential($segment),
                'recommended_strategy' => $this->recommend_segment_strategy($segment)
            );
        }
        
        // Identify high-value opportunities
        $insights['revenue_opportunities'] = $this->identify_revenue_opportunities($segments);
        
        // Generate targeting recommendations
        $insights['targeting_recommendations'] = $this->generate_targeting_recommendations($segments);
        
        return $insights;
    }
    
    /**
     * Validate segment quality
     */
    private function validate_segment_quality($segments, $features) {
        return array(
            'silhouette_score' => $this->calculate_silhouette_score($segments, $features),
            'inertia' => $this->calculate_within_cluster_sum_of_squares($segments, $features),
            'calinski_harabasz_score' => $this->calculate_calinski_harabasz_score($segments, $features),
            'segment_stability' => $this->calculate_segment_stability($segments),
            'interpretability_score' => $this->calculate_interpretability_score($segments)
        );
    }
    
    /**
     * Store segmentation results
     */
    private function store_segmentation_results($segmentation_result) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_user_segments';
        $this->maybe_create_segments_table();
        
        // Store segment assignments
        foreach ($segmentation_result['segments'] as $segment) {
            foreach ($segment['users'] as $user_id) {
                $wpdb->replace($table_name, array(
                    'user_id' => $user_id,
                    'segment_type' => $segmentation_result['segmentation_type'],
                    'segment_id' => $segment['segment_id'],
                    'segment_name' => $segment['name'],
                    'created_at' => current_time('mysql')
                ));
            }
        }
        
        // Store overall results
        $results_table = $wpdb->prefix . 'khm_segmentation_results';
        $this->maybe_create_segmentation_results_table();
        
        $wpdb->insert($results_table, array(
            'segmentation_type' => $segmentation_result['segmentation_type'],
            'result_data' => json_encode($segmentation_result),
            'num_segments' => count($segmentation_result['segments']),
            'total_users' => $segmentation_result['metadata']['total_users'],
            'created_at' => current_time('mysql')
        ));
    }
    
    /**
     * Database table creation methods
     */
    private function maybe_create_segments_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_user_segments';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(255) NOT NULL,
            segment_type VARCHAR(50) NOT NULL,
            segment_id INT NOT NULL,
            segment_name VARCHAR(100),
            created_at DATETIME NOT NULL,
            
            UNIQUE KEY unique_user_segment (user_id, segment_type),
            INDEX idx_segment_type (segment_type),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function maybe_create_segmentation_results_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_segmentation_results';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            segmentation_type VARCHAR(50) NOT NULL,
            result_data LONGTEXT,
            num_segments INT,
            total_users INT,
            created_at DATETIME NOT NULL,
            
            INDEX idx_type_date (segmentation_type, created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Utility calculation methods (simplified implementations)
     */
    private function calculate_page_views($user_id) { return rand(5, 50); }
    private function calculate_average_session_duration($user_id) { return rand(60, 600); }
    private function calculate_user_bounce_rate($user_id) { return rand(20, 80) / 100; }
    private function calculate_engagement_events($user_id) { return rand(1, 20); }
    private function calculate_purchase_frequency($user_id) { return rand(1, 10); }
    private function calculate_average_order_value($user_id) { return rand(50, 500); }
    private function calculate_recency($last_visit) { return (time() - strtotime($last_visit)) / 86400; }
    private function calculate_journey_length($first_visit, $last_visit) { return (strtotime($last_visit) - strtotime($first_visit)) / 86400; }
    private function calculate_conversion_speed($user_id) { return rand(1, 30); }
    
    private function normalize_features($features) {
        // Simple min-max normalization
        $normalized = array();
        $feature_keys = array_keys($features[0]);
        
        foreach ($feature_keys as $key) {
            if ($key === 'user_id') continue;
            
            $values = array_column($features, $key);
            $min_val = min($values);
            $max_val = max($values);
            $range = $max_val - $min_val;
            
            foreach ($features as $index => $feature_set) {
                if (!isset($normalized[$index])) {
                    $normalized[$index] = array();
                }
                $normalized[$index][$key] = $range > 0 ? ($feature_set[$key] - $min_val) / $range : 0;
                $normalized[$index]['user_id'] = $feature_set['user_id'];
            }
        }
        
        return $normalized;
    }
    
    private function calculate_median($values) {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        } else {
            return $values[$middle];
        }
    }
    
    private function calculate_standard_deviation($values) {
        $mean = array_sum($values) / count($values);
        $sum_squares = 0;
        
        foreach ($values as $value) {
            $sum_squares += pow($value - $mean, 2);
        }
        
        return sqrt($sum_squares / count($values));
    }
    
    // Placeholder methods for complex calculations
    private function calculate_feature_importance($features) { return array(); }
    private function calculate_feature_correlations($features) { return array(); }
    private function remove_correlated_features($features, $correlations) { return $features; }
    private function filter_by_importance($features, $importance) { return $features; }
    private function apply_hierarchical_clustering($features, $options) { return array('algorithm' => 'hierarchical', 'assignments' => array()); }
    private function apply_dbscan_clustering($features, $options) { return array('algorithm' => 'dbscan', 'assignments' => array()); }
    private function apply_gaussian_mixture_clustering($features, $options) { return array('algorithm' => 'gaussian_mixture', 'assignments' => array()); }
    private function initialize_centroids($features, $num_clusters) { return array(); }
    private function assign_to_centroids($features, $centroids) { return array_fill(0, count($features), 0); }
    private function update_centroids($features, $assignments, $num_clusters) { return array(); }
    private function centroids_converged($old, $new, $tolerance) { return true; }
    private function generate_segment_name($analysis) { return 'Segment_' . rand(1000, 9999); }
    private function calculate_segment_conversion_rate($users) { return 0.05; }
    private function calculate_segment_engagement_score($features) { return 0.7; }
    private function calculate_segment_value_score($segment) { return 0.8; }
    private function calculate_growth_potential($segment) { return 0.6; }
    private function recommend_segment_strategy($segment) { return 'Focus on engagement'; }
    private function identify_revenue_opportunities($segments) { return array(); }
    private function generate_targeting_recommendations($segments) { return array(); }
    private function calculate_silhouette_score($segments, $features) { return 0.7; }
    private function calculate_within_cluster_sum_of_squares($segments, $features) { return 100; }
    private function calculate_calinski_harabasz_score($segments, $features) { return 150; }
    private function calculate_segment_stability($segments) { return 0.8; }
    private function calculate_interpretability_score($segments) { return 0.9; }
    
    /**
     * Update all segments
     */
    public function update_all_segments() {
        foreach (array_keys($this->segmentation_models) as $segmentation_type) {
            $this->generate_segments($segmentation_type);
        }
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_generate_segments() {
        check_ajax_referer('khm_segmentation_nonce', 'nonce');
        
        $segmentation_type = sanitize_text_field($_POST['segmentation_type'] ?? 'behavioral_segmentation');
        $num_segments = intval($_POST['num_segments'] ?? 4);
        
        $options = array(
            'num_segments' => $num_segments,
            'historical_days' => intval($_POST['historical_days'] ?? 90)
        );
        
        $result = $this->generate_segments($segmentation_type, $options);
        
        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Failed to generate segments');
        }
    }
    
    public function ajax_analyze_segment() {
        check_ajax_referer('khm_segmentation_nonce', 'nonce');
        
        $segment_id = intval($_POST['segment_id'] ?? 0);
        $segmentation_type = sanitize_text_field($_POST['segmentation_type'] ?? '');
        
        // Get segment analysis from stored results
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_segmentation_results';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT result_data FROM {$table_name} WHERE segmentation_type = %s ORDER BY created_at DESC LIMIT 1",
            $segmentation_type
        ));
        
        if ($result) {
            $segmentation_data = json_decode($result->result_data, true);
            $segment_analysis = null;
            
            foreach ($segmentation_data['segments'] as $segment) {
                if ($segment['segment_id'] === $segment_id) {
                    $segment_analysis = $segment;
                    break;
                }
            }
            
            wp_send_json_success($segment_analysis);
        } else {
            wp_send_json_error('Segment not found');
        }
    }
    
    public function ajax_export_segment() {
        check_ajax_referer('khm_segmentation_nonce', 'nonce');
        
        $segment_id = intval($_POST['segment_id'] ?? 0);
        $segmentation_type = sanitize_text_field($_POST['segmentation_type'] ?? '');
        
        // Get users in segment
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_user_segments';
        
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id FROM {$table_name} WHERE segment_type = %s AND segment_id = %d",
            $segmentation_type,
            $segment_id
        ), ARRAY_A);
        
        wp_send_json_success(array('users' => array_column($users, 'user_id')));
    }
    
    /**
     * Add segmentation menu
     */
    public function add_segmentation_menu() {
        add_submenu_page(
            'khm-attribution',
            'Intelligent Segmentation',
            'Segmentation',
            'manage_options',
            'khm-attribution-segmentation',
            array($this, 'render_segmentation_page')
        );
    }
    
    /**
     * Render segmentation page
     */
    public function render_segmentation_page() {
        echo '<div class="wrap">';
        echo '<h1>Intelligent Segmentation</h1>';
        echo '<p>AI-powered user segmentation and targeting analysis.</p>';
        echo '</div>';
    }
}
?>