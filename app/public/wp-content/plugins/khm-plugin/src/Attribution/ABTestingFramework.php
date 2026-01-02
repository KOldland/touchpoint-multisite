<?php
/**
 * KHM Attribution A/B Testing Framework
 * 
 * Comprehensive A/B testing system for creative assets with statistical
 * significance testing, multi-variate experiments, and automated optimization
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_AB_Testing_Framework {
    
    private $query_builder;
    private $performance_manager;
    private $cache_manager;
    private $asset_manager;
    private $test_types = array();
    private $statistical_methods = array();
    
    public function __construct() {
        $this->load_dependencies();
        $this->init_test_types();
        $this->init_statistical_methods();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once dirname(__FILE__) . '/QueryBuilder.php';
        require_once dirname(__FILE__) . '/PerformanceManager.php';
        require_once dirname(__FILE__) . '/CreativeAssetManager.php';
        
        $this->query_builder = new KHM_Attribution_Query_Builder();
        $this->performance_manager = new KHM_Attribution_Performance_Manager();
        $this->asset_manager = new KHM_Attribution_Creative_Asset_Manager();
    }
    
    /**
     * Initialize supported test types
     */
    private function init_test_types() {
        $this->test_types = array(
            'ab_test' => array(
                'name' => 'A/B Test',
                'description' => 'Split test between two creative variants',
                'min_variants' => 1,
                'max_variants' => 1,
                'traffic_split' => array(50, 50),
                'complexity' => 'simple'
            ),
            'abc_test' => array(
                'name' => 'A/B/C Test',
                'description' => 'Multi-variant test with up to 5 variants',
                'min_variants' => 2,
                'max_variants' => 4,
                'traffic_split' => 'equal',
                'complexity' => 'medium'
            ),
            'multivariate' => array(
                'name' => 'Multivariate Test',
                'description' => 'Test multiple elements simultaneously',
                'min_variants' => 2,
                'max_variants' => 16,
                'traffic_split' => 'factorial',
                'complexity' => 'high'
            ),
            'sequential' => array(
                'name' => 'Sequential Test',
                'description' => 'Continuous testing with early stopping',
                'min_variants' => 1,
                'max_variants' => 3,
                'traffic_split' => 'adaptive',
                'complexity' => 'high'
            ),
            'bandit' => array(
                'name' => 'Multi-Armed Bandit',
                'description' => 'Adaptive testing with automatic optimization',
                'min_variants' => 2,
                'max_variants' => 10,
                'traffic_split' => 'dynamic',
                'complexity' => 'advanced'
            )
        );
    }
    
    /**
     * Initialize statistical methods
     */
    private function init_statistical_methods() {
        $this->statistical_methods = array(
            'frequentist' => array(
                'name' => 'Frequentist Statistics',
                'methods' => array('t_test', 'chi_square', 'fisher_exact'),
                'confidence_levels' => array(0.90, 0.95, 0.99),
                'default_confidence' => 0.95
            ),
            'bayesian' => array(
                'name' => 'Bayesian Statistics',
                'methods' => array('beta_binomial', 'gamma_poisson', 'normal_gamma'),
                'confidence_levels' => array(0.90, 0.95, 0.99),
                'default_confidence' => 0.95
            ),
            'sequential' => array(
                'name' => 'Sequential Analysis',
                'methods' => array('sprt', 'group_sequential', 'adaptive'),
                'confidence_levels' => array(0.90, 0.95, 0.99),
                'default_confidence' => 0.95
            )
        );
    }
    
    /**
     * Create a new A/B test
     */
    public function create_test($test_config) {
        $defaults = array(
            'test_name' => '',
            'test_type' => 'ab_test',
            'test_description' => '',
            'hypothesis' => '',
            'control_asset_id' => '',
            'variant_asset_ids' => array(),
            'traffic_allocation' => array(),
            'channel' => '',
            'campaign_id' => '',
            'target_metric' => 'conversion_rate',
            'confidence_level' => 0.95,
            'minimum_sample_size' => 1000,
            'test_duration' => 14, // days
            'statistical_method' => 'frequentist',
            'auto_start' => false,
            'early_stopping' => false,
            'email_notifications' => true
        );
        
        $test_config = array_merge($defaults, $test_config);
        
        try {
            // Validate test configuration
            $validation_result = $this->validate_test_config($test_config);
            if (!$validation_result['valid']) {
                throw new Exception('Test configuration validation failed: ' . $validation_result['error']);
            }
            
            // Generate unique test ID
            $test_id = $this->generate_test_id($test_config);
            
            // Calculate optimal traffic allocation
            $traffic_allocation = $this->calculate_traffic_allocation($test_config);
            
            // Calculate statistical requirements
            $statistical_requirements = $this->calculate_statistical_requirements($test_config);
            
            // Create test record
            $test_record = $this->create_test_record($test_id, $test_config, $traffic_allocation, $statistical_requirements);
            
            // Initialize test tracking
            $this->initialize_test_tracking($test_id);
            
            // Auto-start if configured
            if ($test_config['auto_start']) {
                $this->start_test($test_id);
            }
            
            return array(
                'success' => true,
                'test_id' => $test_id,
                'test_record' => $test_record,
                'traffic_allocation' => $traffic_allocation,
                'statistical_requirements' => $statistical_requirements,
                'estimated_duration' => $this->estimate_test_duration($test_config, $statistical_requirements)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            );
        }
    }
    
    /**
     * Start an A/B test
     */
    public function start_test($test_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_creative_ab_tests';
        
        // Get test configuration
        $test = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE test_id = %s",
            $test_id
        ), ARRAY_A);
        
        if (!$test) {
            return array('success' => false, 'error' => 'Test not found');
        }
        
        if ($test['status'] !== 'draft') {
            return array('success' => false, 'error' => 'Test can only be started from draft status');
        }
        
        // Validate assets are still available
        $validation_result = $this->validate_test_assets($test);
        if (!$validation_result['valid']) {
            return array('success' => false, 'error' => $validation_result['error']);
        }
        
        // Calculate end date
        $start_date = current_time('mysql');
        $end_date = date('Y-m-d H:i:s', strtotime($start_date . ' +' . $test['test_duration'] . ' days'));
        
        // Update test status
        $update_result = $wpdb->update(
            $table_name,
            array(
                'status' => 'running',
                'start_date' => $start_date,
                'end_date' => $end_date,
                'updated_at' => current_time('mysql')
            ),
            array('test_id' => $test_id),
            array('%s', '%s', '%s', '%s'),
            array('%s')
        );
        
        if ($update_result === false) {
            return array('success' => false, 'error' => 'Failed to start test');
        }
        
        // Initialize real-time tracking
        $this->setup_test_tracking($test_id);
        
        // Send notifications
        if ($test['email_notifications']) {
            $this->send_test_notification($test_id, 'started');
        }
        
        return array(
            'success' => true,
            'test_id' => $test_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => 'running'
        );
    }
    
    /**
     * Stop an A/B test
     */
    public function stop_test($test_id, $reason = 'manual_stop') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_creative_ab_tests';
        
        // Get current test
        $test = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE test_id = %s",
            $test_id
        ), ARRAY_A);
        
        if (!$test) {
            return array('success' => false, 'error' => 'Test not found');
        }
        
        if ($test['status'] !== 'running') {
            return array('success' => false, 'error' => 'Test is not currently running');
        }
        
        // Calculate final results
        $final_results = $this->calculate_final_results($test_id);
        
        // Determine winner
        $winner_analysis = $this->determine_test_winner($test_id, $final_results);
        
        // Update test record
        $update_data = array(
            'status' => 'completed',
            'end_date' => current_time('mysql'),
            'winner_asset_id' => $winner_analysis['winner_asset_id'],
            'statistical_significance' => $winner_analysis['significance'],
            'lift_percentage' => $winner_analysis['lift_percentage'],
            'results_summary' => json_encode($final_results),
            'updated_at' => current_time('mysql')
        );
        
        $update_result = $wpdb->update(
            $table_name,
            $update_data,
            array('test_id' => $test_id),
            array('%s', '%s', '%s', '%f', '%f', '%s', '%s'),
            array('%s')
        );
        
        if ($update_result === false) {
            return array('success' => false, 'error' => 'Failed to stop test');
        }
        
        // Generate comprehensive report
        $test_report = $this->generate_test_report($test_id, $final_results, $winner_analysis);
        
        // Send completion notification
        $this->send_test_notification($test_id, 'completed', $test_report);
        
        // Clear test caches
        $this->clear_test_caches($test_id);
        
        return array(
            'success' => true,
            'test_id' => $test_id,
            'winner' => $winner_analysis,
            'final_results' => $final_results,
            'test_report' => $test_report,
            'stop_reason' => $reason
        );
    }
    
    /**
     * Get real-time test results
     */
    public function get_test_results($test_id, $include_detailed_stats = true) {
        $cache_key = "test_results_{$test_id}_" . ($include_detailed_stats ? 'detailed' : 'summary');
        
        // Try cache first
        if (isset($this->cache_manager)) {
            $cached_results = $this->cache_manager->get_cache($cache_key, 'ab_tests');
            if ($cached_results !== false) {
                return $cached_results;
            }
        }
        
        global $wpdb;
        
        // Get test configuration
        $test_table = $wpdb->prefix . 'khm_creative_ab_tests';
        $test = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $test_table WHERE test_id = %s",
            $test_id
        ), ARRAY_A);
        
        if (!$test) {
            return array('success' => false, 'error' => 'Test not found');
        }
        
        // Parse test configuration
        $variant_asset_ids = json_decode($test['variant_asset_ids'], true);
        $traffic_allocation = json_decode($test['traffic_allocation'], true);
        
        // Get performance data for all assets in test
        $all_asset_ids = array_merge(array($test['control_asset_id']), $variant_asset_ids);
        $performance_data = array();
        
        foreach ($all_asset_ids as $asset_id) {
            $asset_performance = $this->get_test_asset_performance($asset_id, $test);
            $performance_data[$asset_id] = $asset_performance;
        }
        
        // Calculate statistical analysis
        $statistical_analysis = $this->calculate_statistical_analysis($test, $performance_data);
        
        // Calculate current results summary
        $results_summary = $this->calculate_results_summary($test, $performance_data, $statistical_analysis);
        
        // Check for early stopping conditions
        $early_stopping_analysis = $this->check_early_stopping_conditions($test, $statistical_analysis);
        
        $result = array(
            'success' => true,
            'test_id' => $test_id,
            'test_config' => $test,
            'status' => $test['status'],
            'performance_data' => $performance_data,
            'results_summary' => $results_summary,
            'statistical_analysis' => $statistical_analysis,
            'early_stopping' => $early_stopping_analysis,
            'progress' => $this->calculate_test_progress($test, $performance_data),
            'estimated_completion' => $this->estimate_completion_time($test, $performance_data)
        );
        
        // Include detailed statistics if requested
        if ($include_detailed_stats) {
            $result['detailed_statistics'] = $this->calculate_detailed_statistics($test, $performance_data);
            $result['confidence_intervals'] = $this->calculate_confidence_intervals($test, $performance_data);
            $result['effect_sizes'] = $this->calculate_effect_sizes($test, $performance_data);
        }
        
        // Cache results for 5 minutes
        if (isset($this->cache_manager)) {
            $this->cache_manager->set_cache($cache_key, $result, 300, 'ab_tests');
        }
        
        return $result;
    }
    
    /**
     * Record test event (impression, click, conversion)
     */
    public function record_test_event($test_id, $asset_id, $event_type, $event_data = array()) {
        // Validate test is active
        if (!$this->is_test_active($test_id)) {
            return array('success' => false, 'error' => 'Test is not active');
        }
        
        // Validate asset is part of test
        if (!$this->is_asset_in_test($test_id, $asset_id)) {
            return array('success' => false, 'error' => 'Asset is not part of this test');
        }
        
        // Record the event
        $event_result = $this->record_asset_event($asset_id, $event_type, $event_data);
        
        // Update test statistics
        $this->update_test_statistics($test_id, $asset_id, $event_type, $event_data);
        
        // Check for early stopping conditions
        if ($event_type === 'conversion') {
            $this->check_and_process_early_stopping($test_id);
        }
        
        return array(
            'success' => true,
            'test_id' => $test_id,
            'asset_id' => $asset_id,
            'event_type' => $event_type,
            'recorded_at' => current_time('mysql')
        );
    }
    
    /**
     * Get assignment for visitor (which variant to show)
     */
    public function get_test_assignment($test_id, $visitor_id, $context = array()) {
        // Check if test is active
        if (!$this->is_test_active($test_id)) {
            return array('success' => false, 'error' => 'Test is not active');
        }
        
        // Get test configuration
        $test = $this->get_test_config($test_id);
        if (!$test) {
            return array('success' => false, 'error' => 'Test configuration not found');
        }
        
        // Check visitor eligibility
        $eligibility_check = $this->check_visitor_eligibility($test_id, $visitor_id, $context);
        if (!$eligibility_check['eligible']) {
            return array('success' => false, 'error' => $eligibility_check['reason']);
        }
        
        // Get or create assignment
        $assignment = $this->get_or_create_assignment($test_id, $visitor_id, $test);
        
        // Record assignment event
        $this->record_assignment_event($test_id, $visitor_id, $assignment);
        
        return array(
            'success' => true,
            'test_id' => $test_id,
            'visitor_id' => $visitor_id,
            'assigned_asset_id' => $assignment['asset_id'],
            'variant_name' => $assignment['variant_name'],
            'assignment_timestamp' => $assignment['timestamp']
        );
    }
    
    /**
     * List all tests with filtering options
     */
    public function list_tests($filters = array(), $pagination = array()) {
        $defaults = array(
            'status' => '',
            'channel' => '',
            'campaign_id' => '',
            'date_from' => '',
            'date_to' => '',
            'test_type' => '',
            'created_by' => ''
        );
        
        $pagination_defaults = array(
            'page' => 1,
            'per_page' => 20,
            'sort_by' => 'created_at',
            'sort_order' => 'DESC'
        );
        
        $filters = array_merge($defaults, $filters);
        $pagination = array_merge($pagination_defaults, $pagination);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_creative_ab_tests';
        
        // Build WHERE clause
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $filters['status'];
        }
        
        if (!empty($filters['channel'])) {
            $where_conditions[] = "channel = %s";
            $where_values[] = $filters['channel'];
        }
        
        if (!empty($filters['campaign_id'])) {
            $where_conditions[] = "campaign_id = %s";
            $where_values[] = $filters['campaign_id'];
        }
        
        if (!empty($filters['test_type'])) {
            $where_conditions[] = "test_type = %s";
            $where_values[] = $filters['test_type'];
        }
        
        if (!empty($filters['created_by'])) {
            $where_conditions[] = "created_by = %d";
            $where_values[] = $filters['created_by'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "created_at >= %s";
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "created_at <= %s";
            $where_values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Calculate pagination
        $offset = ($pagination['page'] - 1) * $pagination['per_page'];
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table_name $where_clause";
        $total_tests = $wpdb->get_var(!empty($where_values) ? $wpdb->prepare($count_sql, $where_values) : $count_sql);
        
        // Get tests
        $sort_by = in_array($pagination['sort_by'], array('test_name', 'status', 'created_at', 'start_date')) ? 
                   $pagination['sort_by'] : 'created_at';
        $sort_order = strtoupper($pagination['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql = "SELECT * FROM $table_name $where_clause ORDER BY $sort_by $sort_order LIMIT %d OFFSET %d";
        $where_values[] = $pagination['per_page'];
        $where_values[] = $offset;
        
        $tests = $wpdb->get_results($wpdb->prepare($sql, $where_values), ARRAY_A);
        
        // Enhance test data
        foreach ($tests as &$test) {
            $test['variant_asset_ids'] = json_decode($test['variant_asset_ids'], true) ?: array();
            $test['traffic_allocation'] = json_decode($test['traffic_allocation'], true) ?: array();
            
            // Add quick stats if test is running or completed
            if (in_array($test['status'], array('running', 'completed'))) {
                $quick_stats = $this->get_test_quick_stats($test['test_id']);
                $test['quick_stats'] = $quick_stats;
            }
        }
        
        return array(
            'success' => true,
            'tests' => $tests,
            'pagination' => array(
                'current_page' => $pagination['page'],
                'per_page' => $pagination['per_page'],
                'total_tests' => intval($total_tests),
                'total_pages' => ceil($total_tests / $pagination['per_page'])
            ),
            'filters_applied' => $filters
        );
    }
    
    /**
     * Delete a test and all associated data
     */
    public function delete_test($test_id, $force_delete = false) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_creative_ab_tests';
        
        // Get test information
        $test = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE test_id = %s",
            $test_id
        ), ARRAY_A);
        
        if (!$test) {
            return array('success' => false, 'error' => 'Test not found');
        }
        
        // Check if test can be deleted
        if ($test['status'] === 'running' && !$force_delete) {
            return array(
                'success' => false,
                'error' => 'Cannot delete running test. Stop the test first or use force_delete=true.'
            );
        }
        
        try {
            $wpdb->query('START TRANSACTION');
            
            // Delete test assignments
            $this->delete_test_assignments($test_id);
            
            // Delete test events
            $this->delete_test_events($test_id);
            
            // Delete main test record
            $wpdb->delete($table_name, array('test_id' => $test_id), array('%s'));
            
            $wpdb->query('COMMIT');
            
            // Clear caches
            $this->clear_test_caches($test_id);
            
            return array('success' => true, 'message' => 'Test deleted successfully');
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return array('success' => false, 'error' => 'Failed to delete test: ' . $e->getMessage());
        }
    }
    
    // Helper methods for A/B testing functionality
    
    private function validate_test_config($config) {
        // Validate required fields
        $required_fields = array('test_name', 'control_asset_id', 'channel', 'target_metric');
        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                return array('valid' => false, 'error' => "Required field '$field' is missing");
            }
        }
        
        // Validate test type
        if (!isset($this->test_types[$config['test_type']])) {
            return array('valid' => false, 'error' => 'Invalid test type');
        }
        
        // Validate variant count
        $test_type_config = $this->test_types[$config['test_type']];
        $variant_count = count($config['variant_asset_ids']);
        
        if ($variant_count < $test_type_config['min_variants']) {
            return array('valid' => false, 'error' => 'Insufficient variants for test type');
        }
        
        if ($variant_count > $test_type_config['max_variants']) {
            return array('valid' => false, 'error' => 'Too many variants for test type');
        }
        
        // Validate assets exist
        $all_assets = array_merge(array($config['control_asset_id']), $config['variant_asset_ids']);
        foreach ($all_assets as $asset_id) {
            $asset = $this->asset_manager->get_asset($asset_id, false, false);
            if (!$asset['success']) {
                return array('valid' => false, 'error' => "Asset $asset_id not found");
            }
        }
        
        // Validate confidence level
        if ($config['confidence_level'] < 0.8 || $config['confidence_level'] > 0.99) {
            return array('valid' => false, 'error' => 'Confidence level must be between 0.8 and 0.99');
        }
        
        return array('valid' => true);
    }
    
    private function generate_test_id($config) {
        $timestamp = time();
        $random = wp_generate_password(6, false);
        $type_prefix = substr($config['test_type'], 0, 2);
        
        return strtoupper($type_prefix) . '_' . $timestamp . '_' . $random;
    }
    
    private function calculate_traffic_allocation($config) {
        $test_type = $config['test_type'];
        $variant_count = count($config['variant_asset_ids']);
        $total_variants = $variant_count + 1; // +1 for control
        
        // If custom allocation provided, validate and use it
        if (!empty($config['traffic_allocation'])) {
            $custom_allocation = $config['traffic_allocation'];
            $total_percentage = array_sum($custom_allocation);
            
            if (abs($total_percentage - 100) < 0.01) { // Allow for floating point precision
                return $custom_allocation;
            }
        }
        
        // Default to equal allocation
        $equal_percentage = 100 / $total_variants;
        $allocation = array();
        
        // Control allocation
        $allocation['control'] = $equal_percentage;
        
        // Variant allocations
        for ($i = 0; $i < $variant_count; $i++) {
            $allocation['variant_' . ($i + 1)] = $equal_percentage;
        }
        
        return $allocation;
    }
    
    private function calculate_statistical_requirements($config) {
        $baseline_conversion_rate = 0.05; // 5% default
        $minimum_detectable_effect = 0.2; // 20% relative improvement
        $power = 0.8; // 80% statistical power
        $alpha = 1 - $config['confidence_level'];
        
        // Calculate sample size using simplified formula
        // In production, this would use more sophisticated statistical calculations
        $z_alpha = $this->get_z_score($config['confidence_level']);
        $z_beta = $this->get_z_score($power);
        
        $p1 = $baseline_conversion_rate;
        $p2 = $p1 * (1 + $minimum_detectable_effect);
        $p_avg = ($p1 + $p2) / 2;
        
        $sample_size_per_variant = ceil(
            (2 * $p_avg * (1 - $p_avg) * pow($z_alpha + $z_beta, 2)) / 
            pow($p2 - $p1, 2)
        );
        
        $total_sample_size = $sample_size_per_variant * (count($config['variant_asset_ids']) + 1);
        
        return array(
            'sample_size_per_variant' => max($sample_size_per_variant, $config['minimum_sample_size']),
            'total_sample_size' => max($total_sample_size, $config['minimum_sample_size'] * 2),
            'minimum_detectable_effect' => $minimum_detectable_effect,
            'statistical_power' => $power,
            'alpha_level' => $alpha,
            'baseline_conversion_rate' => $baseline_conversion_rate
        );
    }
    
    private function create_test_record($test_id, $config, $traffic_allocation, $statistical_requirements) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_creative_ab_tests';
        
        $insert_data = array(
            'test_id' => $test_id,
            'test_name' => $config['test_name'],
            'test_type' => $config['test_type'],
            'test_description' => $config['test_description'],
            'hypothesis' => $config['hypothesis'],
            'control_asset_id' => $config['control_asset_id'],
            'variant_asset_ids' => json_encode($config['variant_asset_ids']),
            'traffic_allocation' => json_encode($traffic_allocation),
            'channel' => $config['channel'],
            'campaign_id' => $config['campaign_id'],
            'target_metric' => $config['target_metric'],
            'confidence_level' => $config['confidence_level'],
            'minimum_sample_size' => $statistical_requirements['total_sample_size'],
            'test_duration' => $config['test_duration'],
            'status' => 'draft',
            'created_by' => get_current_user_id()
        );
        
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            throw new Exception('Failed to create test record');
        }
        
        return $insert_data;
    }
    
    private function initialize_test_tracking($test_id) {
        // Create tracking tables/records for the test
        // This would set up real-time event tracking
        
        global $wpdb;
        
        // Create test assignments table if it doesn't exist
        $assignments_table = $wpdb->prefix . 'khm_test_assignments';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $assignments_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            test_id varchar(255) NOT NULL,
            visitor_id varchar(255) NOT NULL,
            asset_id varchar(255) NOT NULL,
            variant_name varchar(100) NOT NULL,
            assigned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY test_id (test_id),
            KEY visitor_id (visitor_id),
            UNIQUE KEY unique_assignment (test_id, visitor_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create test events table if it doesn't exist
        $events_table = $wpdb->prefix . 'khm_test_events';
        
        $sql = "CREATE TABLE IF NOT EXISTS $events_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            test_id varchar(255) NOT NULL,
            visitor_id varchar(255) NOT NULL,
            asset_id varchar(255) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_value decimal(15,2) DEFAULT NULL,
            event_metadata longtext,
            recorded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY test_id (test_id),
            KEY visitor_id (visitor_id),
            KEY event_type (event_type),
            KEY recorded_at (recorded_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    private function get_z_score($confidence_level) {
        $z_scores = array(
            0.90 => 1.645,
            0.95 => 1.96,
            0.99 => 2.576
        );
        
        // Find closest confidence level
        $closest = 0.95;
        $min_diff = 1;
        
        foreach ($z_scores as $level => $score) {
            $diff = abs($confidence_level - $level);
            if ($diff < $min_diff) {
                $min_diff = $diff;
                $closest = $level;
            }
        }
        
        return $z_scores[$closest];
    }
    
    private function estimate_test_duration($config, $statistical_requirements) {
        // Estimate based on expected traffic and conversion rates
        $expected_daily_traffic = 1000; // Default assumption
        $daily_conversions = $expected_daily_traffic * 0.05; // 5% conversion rate
        
        $required_conversions = $statistical_requirements['sample_size_per_variant'];
        $estimated_days = ceil($required_conversions / $daily_conversions);
        
        return array(
            'estimated_days' => max($estimated_days, $config['test_duration']),
            'minimum_days' => $config['test_duration'],
            'expected_daily_traffic' => $expected_daily_traffic,
            'assumptions' => array(
                'daily_traffic' => $expected_daily_traffic,
                'conversion_rate' => 0.05
            )
        );
    }
    
    // Additional helper methods would continue here...
    
    private function validate_test_assets($test) {
        $all_assets = array_merge(
            array($test['control_asset_id']), 
            json_decode($test['variant_asset_ids'], true)
        );
        
        foreach ($all_assets as $asset_id) {
            $asset = $this->asset_manager->get_asset($asset_id, false, false);
            if (!$asset['success'] || $asset['asset']['status'] !== 'active') {
                return array(
                    'valid' => false, 
                    'error' => "Asset $asset_id is not available for testing"
                );
            }
        }
        
        return array('valid' => true);
    }
    
    private function setup_test_tracking($test_id) {
        // Initialize real-time tracking systems
        // This would set up event listeners, webhooks, etc.
        
        // For now, just mark that tracking is initialized
        return true;
    }
    
    private function send_test_notification($test_id, $event_type, $additional_data = array()) {
        // Send email notifications about test events
        // This would integrate with email system
        
        return true; // Placeholder
    }
    
    private function is_test_active($test_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_creative_ab_tests';
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table_name WHERE test_id = %s",
            $test_id
        ));
        
        return $status === 'running';
    }
    
    private function is_asset_in_test($test_id, $asset_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_creative_ab_tests';
        $test = $wpdb->get_row($wpdb->prepare(
            "SELECT control_asset_id, variant_asset_ids FROM $table_name WHERE test_id = %s",
            $test_id
        ), ARRAY_A);
        
        if (!$test) return false;
        
        $all_assets = array_merge(
            array($test['control_asset_id']),
            json_decode($test['variant_asset_ids'], true)
        );
        
        return in_array($asset_id, $all_assets);
    }
    
    private function get_test_config($test_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_creative_ab_tests';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE test_id = %s",
            $test_id
        ), ARRAY_A);
    }
    
    private function clear_test_caches($test_id) {
        if (isset($this->cache_manager)) {
            $this->cache_manager->delete_cache_pattern("test_*{$test_id}*", 'ab_tests');
        }
    }
    
    // Placeholder methods for advanced functionality
    private function calculate_final_results($test_id) { return array(); }
    private function determine_test_winner($test_id, $results) { return array('winner_asset_id' => '', 'significance' => 0, 'lift_percentage' => 0); }
    private function generate_test_report($test_id, $results, $winner) { return array(); }
    private function get_test_asset_performance($asset_id, $test) { return array(); }
    private function calculate_statistical_analysis($test, $performance) { return array(); }
    private function calculate_results_summary($test, $performance, $stats) { return array(); }
    private function check_early_stopping_conditions($test, $stats) { return array(); }
    private function calculate_test_progress($test, $performance) { return 0; }
    private function estimate_completion_time($test, $performance) { return ''; }
    private function calculate_detailed_statistics($test, $performance) { return array(); }
    private function calculate_confidence_intervals($test, $performance) { return array(); }
    private function calculate_effect_sizes($test, $performance) { return array(); }
    private function record_asset_event($asset_id, $event_type, $data) { return true; }
    private function update_test_statistics($test_id, $asset_id, $event_type, $data) { return true; }
    private function check_and_process_early_stopping($test_id) { return false; }
    private function check_visitor_eligibility($test_id, $visitor_id, $context) { return array('eligible' => true); }
    private function get_or_create_assignment($test_id, $visitor_id, $test) { return array('asset_id' => '', 'variant_name' => '', 'timestamp' => ''); }
    private function record_assignment_event($test_id, $visitor_id, $assignment) { return true; }
    private function get_test_quick_stats($test_id) { return array(); }
    private function delete_test_assignments($test_id) { return true; }
    private function delete_test_events($test_id) { return true; }
}

// Initialize the A/B testing framework
new KHM_Attribution_AB_Testing_Framework();
?>