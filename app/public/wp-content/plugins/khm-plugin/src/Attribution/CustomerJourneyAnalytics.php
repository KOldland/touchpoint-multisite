<?php
/**
 * KHM Attribution Customer Journey Analytics
 * 
 * Advanced customer journey tracking and analysis using Phase 2 OOP patterns.
 * Tracks multi-touch attribution across the complete customer lifecycle.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Customer_Journey_Analytics {
    
    private $performance_manager;
    private $database_manager;
    private $query_builder;
    private $session_manager;
    private $journey_config = array();
    private $touchpoint_weights = array();
    private $attribution_models = array();
    
    /**
     * Constructor - Initialize customer journey components
     */
    public function __construct() {
        $this->init_journey_components();
        $this->setup_journey_config();
        $this->load_attribution_models();
        $this->register_journey_hooks();
    }
    
    /**
     * Initialize journey analytics components
     */
    private function init_journey_components() {
        // Load performance manager
        if (file_exists(dirname(__FILE__) . '/PerformanceManager.php')) {
            require_once dirname(__FILE__) . '/PerformanceManager.php';
            $this->performance_manager = new KHM_Attribution_Performance_Manager();
        }
        
        // Load database manager
        if (file_exists(dirname(__FILE__) . '/DatabaseManager.php')) {
            require_once dirname(__FILE__) . '/DatabaseManager.php';
            $this->database_manager = new KHM_Attribution_Database_Manager();
        }
        
        // Load query builder
        if (file_exists(dirname(__FILE__) . '/QueryBuilder.php')) {
            require_once dirname(__FILE__) . '/QueryBuilder.php';
            $this->query_builder = new KHM_Attribution_Query_Builder();
        }
        
        // Load session manager
        if (file_exists(dirname(__FILE__) . '/SessionManager.php')) {
            require_once dirname(__FILE__) . '/SessionManager.php';
            $this->session_manager = new KHM_Attribution_Session_Manager();
        }
    }
    
    /**
     * Setup journey analytics configuration
     */
    private function setup_journey_config() {
        $this->journey_config = array(
            'max_touchpoints' => 50,
            'journey_window_days' => 90,
            'micro_conversion_tracking' => true,
            'cross_device_linking' => true,
            'attribution_decay_rate' => 0.8,
            'touchpoint_categories' => array(
                'awareness' => array('impression', 'view', 'visit'),
                'consideration' => array('engagement', 'download', 'signup'),
                'conversion' => array('purchase', 'subscribe', 'convert'),
                'retention' => array('return_visit', 'repeat_purchase', 'referral')
            )
        );
        
        // Allow configuration overrides
        $this->journey_config = apply_filters('khm_journey_config', $this->journey_config);
    }
    
    /**
     * Load attribution models
     */
    private function load_attribution_models() {
        $this->attribution_models = array(
            'first_touch' => array(
                'name' => 'First Touch Attribution',
                'weight_function' => 'first_touch_weights',
                'best_for' => 'awareness_campaigns'
            ),
            'last_touch' => array(
                'name' => 'Last Touch Attribution',
                'weight_function' => 'last_touch_weights',
                'best_for' => 'conversion_campaigns'
            ),
            'linear' => array(
                'name' => 'Linear Attribution',
                'weight_function' => 'linear_weights',
                'best_for' => 'full_funnel_analysis'
            ),
            'time_decay' => array(
                'name' => 'Time Decay Attribution',
                'weight_function' => 'time_decay_weights',
                'best_for' => 'recent_touchpoint_focus'
            ),
            'u_shaped' => array(
                'name' => 'U-Shaped Attribution',
                'weight_function' => 'u_shaped_weights',
                'best_for' => 'first_last_emphasis'
            ),
            'w_shaped' => array(
                'name' => 'W-Shaped Attribution',
                'weight_function' => 'w_shaped_weights',
                'best_for' => 'milestone_emphasis'
            ),
            'data_driven' => array(
                'name' => 'Data-Driven Attribution',
                'weight_function' => 'data_driven_weights',
                'best_for' => 'machine_learning_optimization'
            )
        );
    }
    
    /**
     * Register journey analytics hooks
     */
    private function register_journey_hooks() {
        add_action('khm_touchpoint_recorded', array($this, 'process_touchpoint'), 10, 2);
        add_action('khm_conversion_completed', array($this, 'analyze_conversion_journey'), 10, 2);
        add_action('wp_footer', array($this, 'track_page_engagement'));
        
        // AJAX hooks for real-time journey tracking
        add_action('wp_ajax_khm_track_micro_conversion', array($this, 'ajax_track_micro_conversion'));
        add_action('wp_ajax_nopriv_khm_track_micro_conversion', array($this, 'ajax_track_micro_conversion'));
        
        // Analytics hooks
        add_filter('khm_journey_analytics', array($this, 'enhance_journey_analytics'), 10, 2);
    }
    
    /**
     * Track customer touchpoint
     */
    public function track_touchpoint($touchpoint_data, $customer_id = null) {
        // Validate touchpoint data
        if (!$this->validate_touchpoint_data($touchpoint_data)) {
            return false;
        }
        
        // Get or create customer identifier
        $customer_id = $customer_id ?: $this->get_customer_identifier();
        
        // Enrich touchpoint data
        $enriched_touchpoint = $this->enrich_touchpoint_data($touchpoint_data, $customer_id);
        
        // Store touchpoint
        $touchpoint_id = $this->store_touchpoint($enriched_touchpoint);
        
        // Update customer journey
        $this->update_customer_journey($customer_id, $touchpoint_id);
        
        // Trigger analysis
        do_action('khm_touchpoint_recorded', $touchpoint_id, $enriched_touchpoint);
        
        return $touchpoint_id;
    }
    
    /**
     * Validate touchpoint data
     */
    private function validate_touchpoint_data($data) {
        $required_fields = array('type', 'timestamp', 'channel');
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get customer identifier
     */
    private function get_customer_identifier() {
        // Try user ID first
        $user_id = get_current_user_id();
        if ($user_id) {
            return 'user_' . $user_id;
        }
        
        // Try session manager
        if (isset($this->session_manager)) {
            $session = $this->session_manager->get_current_session();
            if ($session && isset($session['session_id'])) {
                return 'session_' . $session['session_id'];
            }
        }
        
        // Generate anonymous identifier
        return 'anon_' . $this->generate_anonymous_id();
    }
    
    /**
     * Generate anonymous customer ID
     */
    private function generate_anonymous_id() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $timestamp = time();
        
        return substr(hash('sha256', $ip . $user_agent . $timestamp), 0, 16);
    }
    
    /**
     * Enrich touchpoint data
     */
    private function enrich_touchpoint_data($touchpoint_data, $customer_id) {
        // Add system data
        $enriched = array_merge($touchpoint_data, array(
            'customer_id' => $customer_id,
            'touchpoint_id' => $this->generate_touchpoint_id(),
            'session_id' => session_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'landing_page' => $this->get_current_url(),
            'created_at' => current_time('mysql')
        ));
        
        // Add UTM parameters
        $enriched['utm_data'] = $this->extract_utm_parameters();
        
        // Add engagement metrics
        $enriched['engagement_metrics'] = $this->calculate_engagement_metrics($touchpoint_data);
        
        // Categorize touchpoint
        $enriched['category'] = $this->categorize_touchpoint($touchpoint_data['type']);
        
        return $enriched;
    }
    
    /**
     * Generate unique touchpoint ID
     */
    private function generate_touchpoint_id() {
        return 'tp_' . uniqid() . '_' . time();
    }
    
    /**
     * Extract UTM parameters
     */
    private function extract_utm_parameters() {
        return array(
            'utm_source' => $_GET['utm_source'] ?? '',
            'utm_medium' => $_GET['utm_medium'] ?? '',
            'utm_campaign' => $_GET['utm_campaign'] ?? '',
            'utm_content' => $_GET['utm_content'] ?? '',
            'utm_term' => $_GET['utm_term'] ?? ''
        );
    }
    
    /**
     * Calculate engagement metrics
     */
    private function calculate_engagement_metrics($touchpoint_data) {
        $metrics = array(
            'time_on_page' => $touchpoint_data['time_on_page'] ?? 0,
            'scroll_depth' => $touchpoint_data['scroll_depth'] ?? 0,
            'click_count' => $touchpoint_data['click_count'] ?? 0,
            'interaction_type' => $touchpoint_data['interaction_type'] ?? 'passive'
        );
        
        // Calculate engagement score (0-100)
        $engagement_score = $this->calculate_engagement_score($metrics);
        $metrics['engagement_score'] = $engagement_score;
        
        return $metrics;
    }
    
    /**
     * Calculate engagement score
     */
    private function calculate_engagement_score($metrics) {
        $score = 0;
        
        // Time on page (max 30 points)
        $time_score = min(30, ($metrics['time_on_page'] / 60) * 10);
        $score += $time_score;
        
        // Scroll depth (max 25 points)
        $scroll_score = ($metrics['scroll_depth'] / 100) * 25;
        $score += $scroll_score;
        
        // Click interactions (max 25 points)
        $click_score = min(25, $metrics['click_count'] * 5);
        $score += $click_score;
        
        // Interaction type bonus (max 20 points)
        $interaction_bonus = $metrics['interaction_type'] === 'active' ? 20 : 10;
        $score += $interaction_bonus;
        
        return round($score);
    }
    
    /**
     * Categorize touchpoint
     */
    private function categorize_touchpoint($touchpoint_type) {
        foreach ($this->journey_config['touchpoint_categories'] as $category => $types) {
            if (in_array($touchpoint_type, $types)) {
                return $category;
            }
        }
        
        return 'other';
    }
    
    /**
     * Store touchpoint in database
     */
    private function store_touchpoint($touchpoint_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_customer_touchpoints';
        
        // Create table if not exists
        $this->maybe_create_touchpoints_table();
        
        $wpdb->insert($table_name, array(
            'touchpoint_id' => $touchpoint_data['touchpoint_id'],
            'customer_id' => $touchpoint_data['customer_id'],
            'session_id' => $touchpoint_data['session_id'],
            'touchpoint_type' => $touchpoint_data['type'],
            'channel' => $touchpoint_data['channel'],
            'category' => $touchpoint_data['category'],
            'utm_data' => json_encode($touchpoint_data['utm_data']),
            'engagement_metrics' => json_encode($touchpoint_data['engagement_metrics']),
            'touchpoint_value' => $touchpoint_data['value'] ?? 0,
            'referrer_url' => $touchpoint_data['referrer'],
            'landing_page' => $touchpoint_data['landing_page'],
            'ip_address' => $touchpoint_data['ip_address'],
            'user_agent' => $touchpoint_data['user_agent'],
            'created_at' => $touchpoint_data['created_at']
        ));
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update customer journey
     */
    private function update_customer_journey($customer_id, $touchpoint_id) {
        // Get current journey
        $journey = $this->get_customer_journey($customer_id);
        
        // Add new touchpoint
        $journey['touchpoints'][] = $touchpoint_id;
        $journey['last_touchpoint'] = $touchpoint_id;
        $journey['touchpoint_count'] = count($journey['touchpoints']);
        $journey['updated_at'] = current_time('mysql');
        
        // Limit touchpoints
        if (count($journey['touchpoints']) > $this->journey_config['max_touchpoints']) {
            $journey['touchpoints'] = array_slice($journey['touchpoints'], -$this->journey_config['max_touchpoints']);
        }
        
        // Store updated journey
        $this->store_customer_journey($customer_id, $journey);
    }
    
    /**
     * Get customer journey
     */
    public function get_customer_journey($customer_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_customer_journeys';
        $this->maybe_create_journeys_table();
        
        $journey = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE customer_id = %s",
            $customer_id
        ), ARRAY_A);
        
        if ($journey) {
            $journey['touchpoints'] = json_decode($journey['touchpoints'], true) ?: array();
            $journey['journey_data'] = json_decode($journey['journey_data'], true) ?: array();
        } else {
            $journey = $this->create_new_journey($customer_id);
        }
        
        return $journey;
    }
    
    /**
     * Create new customer journey
     */
    private function create_new_journey($customer_id) {
        return array(
            'customer_id' => $customer_id,
            'touchpoints' => array(),
            'journey_data' => array(),
            'first_touchpoint' => null,
            'last_touchpoint' => null,
            'touchpoint_count' => 0,
            'total_value' => 0,
            'conversion_count' => 0,
            'journey_stage' => 'awareness',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
    }
    
    /**
     * Store customer journey
     */
    private function store_customer_journey($customer_id, $journey_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_customer_journeys';
        
        $data = array(
            'customer_id' => $customer_id,
            'touchpoints' => json_encode($journey_data['touchpoints']),
            'journey_data' => json_encode($journey_data['journey_data']),
            'first_touchpoint' => $journey_data['first_touchpoint'],
            'last_touchpoint' => $journey_data['last_touchpoint'],
            'touchpoint_count' => $journey_data['touchpoint_count'],
            'total_value' => $journey_data['total_value'] ?? 0,
            'conversion_count' => $journey_data['conversion_count'] ?? 0,
            'journey_stage' => $journey_data['journey_stage'] ?? 'awareness',
            'updated_at' => current_time('mysql')
        );
        
        // Check if journey exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT customer_id FROM {$table_name} WHERE customer_id = %s",
            $customer_id
        ));
        
        if ($existing) {
            $wpdb->update($table_name, $data, array('customer_id' => $customer_id));
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table_name, $data);
        }
    }
    
    /**
     * Analyze conversion journey
     */
    public function analyze_conversion_journey($conversion_id, $conversion_data) {
        $customer_id = $conversion_data['customer_id'] ?? $this->get_customer_identifier();
        $journey = $this->get_customer_journey($customer_id);
        
        if (empty($journey['touchpoints'])) {
            return false;
        }
        
        // Get touchpoint details
        $touchpoints = $this->get_touchpoint_details($journey['touchpoints']);
        
        // Apply attribution models
        $attribution_results = array();
        foreach ($this->attribution_models as $model_key => $model_config) {
            $attribution_results[$model_key] = $this->apply_attribution_model($touchpoints, $model_key, $conversion_data);
        }
        
        // Store attribution analysis
        $this->store_attribution_analysis($conversion_id, $customer_id, $attribution_results);
        
        // Update journey with conversion
        $this->update_journey_conversion($customer_id, $conversion_data);
        
        return $attribution_results;
    }
    
    /**
     * Apply attribution model
     */
    private function apply_attribution_model($touchpoints, $model_key, $conversion_data) {
        if (!isset($this->attribution_models[$model_key])) {
            return false;
        }
        
        $model = $this->attribution_models[$model_key];
        $weight_function = $model['weight_function'];
        
        // Calculate weights for each touchpoint
        $weights = $this->$weight_function($touchpoints, $conversion_data);
        
        // Apply weights to conversion value
        $conversion_value = $conversion_data['value'] ?? 0;
        $attributed_values = array();
        
        foreach ($touchpoints as $index => $touchpoint) {
            $weight = $weights[$index] ?? 0;
            $attributed_values[] = array(
                'touchpoint_id' => $touchpoint['touchpoint_id'],
                'weight' => $weight,
                'attributed_value' => $conversion_value * $weight,
                'channel' => $touchpoint['channel'],
                'category' => $touchpoint['category']
            );
        }
        
        return array(
            'model' => $model_key,
            'total_value' => $conversion_value,
            'touchpoint_attributions' => $attributed_values
        );
    }
    
    /**
     * Attribution weight calculation methods
     */
    private function first_touch_weights($touchpoints, $conversion_data) {
        $weights = array_fill(0, count($touchpoints), 0);
        if (!empty($touchpoints)) {
            $weights[0] = 1.0;
        }
        return $weights;
    }
    
    private function last_touch_weights($touchpoints, $conversion_data) {
        $weights = array_fill(0, count($touchpoints), 0);
        if (!empty($touchpoints)) {
            $weights[count($touchpoints) - 1] = 1.0;
        }
        return $weights;
    }
    
    private function linear_weights($touchpoints, $conversion_data) {
        $count = count($touchpoints);
        return $count > 0 ? array_fill(0, $count, 1.0 / $count) : array();
    }
    
    private function time_decay_weights($touchpoints, $conversion_data) {
        $weights = array();
        $decay_rate = $this->journey_config['attribution_decay_rate'];
        $total_weight = 0;
        
        for ($i = 0; $i < count($touchpoints); $i++) {
            $weight = pow($decay_rate, count($touchpoints) - $i - 1);
            $weights[] = $weight;
            $total_weight += $weight;
        }
        
        // Normalize weights
        if ($total_weight > 0) {
            $weights = array_map(function($w) use ($total_weight) {
                return $w / $total_weight;
            }, $weights);
        }
        
        return $weights;
    }
    
    private function u_shaped_weights($touchpoints, $conversion_data) {
        $count = count($touchpoints);
        $weights = array_fill(0, $count, 0);
        
        if ($count === 1) {
            $weights[0] = 1.0;
        } elseif ($count === 2) {
            $weights[0] = 0.5;
            $weights[1] = 0.5;
        } else {
            $weights[0] = 0.4; // First touch
            $weights[$count - 1] = 0.4; // Last touch
            $middle_weight = 0.2 / ($count - 2);
            for ($i = 1; $i < $count - 1; $i++) {
                $weights[$i] = $middle_weight;
            }
        }
        
        return $weights;
    }
    
    private function w_shaped_weights($touchpoints, $conversion_data) {
        $count = count($touchpoints);
        $weights = array_fill(0, $count, 0);
        
        if ($count <= 3) {
            return $this->linear_weights($touchpoints, $conversion_data);
        }
        
        $first_conversion_index = $this->find_first_conversion_touchpoint($touchpoints);
        
        $weights[0] = 0.3; // First touch
        $weights[$first_conversion_index] = 0.3; // First conversion
        $weights[$count - 1] = 0.3; // Last touch
        
        // Distribute remaining 10% to other touchpoints
        $remaining_touchpoints = $count - 3;
        if ($remaining_touchpoints > 0) {
            $remaining_weight = 0.1 / $remaining_touchpoints;
            for ($i = 1; $i < $count - 1; $i++) {
                if ($i !== $first_conversion_index) {
                    $weights[$i] = $remaining_weight;
                }
            }
        }
        
        return $weights;
    }
    
    private function data_driven_weights($touchpoints, $conversion_data) {
        // Simplified data-driven model - in production this would use ML algorithms
        // For now, combine time decay with engagement scores
        
        $weights = array();
        $total_weight = 0;
        
        foreach ($touchpoints as $index => $touchpoint) {
            $engagement_score = $touchpoint['engagement_metrics']['engagement_score'] ?? 50;
            $time_decay = pow(0.8, count($touchpoints) - $index - 1);
            $channel_multiplier = $this->get_channel_multiplier($touchpoint['channel']);
            
            $weight = ($engagement_score / 100) * $time_decay * $channel_multiplier;
            $weights[] = $weight;
            $total_weight += $weight;
        }
        
        // Normalize weights
        if ($total_weight > 0) {
            $weights = array_map(function($w) use ($total_weight) {
                return $w / $total_weight;
            }, $weights);
        }
        
        return $weights;
    }
    
    /**
     * Utility methods
     */
    private function find_first_conversion_touchpoint($touchpoints) {
        foreach ($touchpoints as $index => $touchpoint) {
            if ($touchpoint['category'] === 'conversion') {
                return $index;
            }
        }
        return floor(count($touchpoints) / 2); // Default to middle if no conversion found
    }
    
    private function get_channel_multiplier($channel) {
        $multipliers = array(
            'organic_search' => 1.2,
            'paid_search' => 1.0,
            'social_media' => 0.8,
            'email' => 1.1,
            'direct' => 1.3,
            'referral' => 0.9
        );
        
        return $multipliers[$channel] ?? 1.0;
    }
    
    private function get_current_url() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return $protocol . '://' . $host . $uri;
    }
    
    /**
     * Database table creation methods
     */
    private function maybe_create_touchpoints_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_customer_touchpoints';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            touchpoint_id VARCHAR(100) NOT NULL,
            customer_id VARCHAR(100) NOT NULL,
            session_id VARCHAR(100),
            touchpoint_type VARCHAR(50) NOT NULL,
            channel VARCHAR(50) NOT NULL,
            category VARCHAR(50) NOT NULL,
            utm_data TEXT,
            engagement_metrics TEXT,
            touchpoint_value DECIMAL(10,2) DEFAULT 0,
            referrer_url TEXT,
            landing_page TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at DATETIME NOT NULL,
            
            UNIQUE KEY unique_touchpoint (touchpoint_id),
            INDEX idx_customer_touchpoints (customer_id, created_at),
            INDEX idx_session_tracking (session_id),
            INDEX idx_channel_analysis (channel, category),
            INDEX idx_journey_timeline (customer_id, touchpoint_type, created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function maybe_create_journeys_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_customer_journeys';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            customer_id VARCHAR(100) NOT NULL,
            touchpoints TEXT,
            journey_data TEXT,
            first_touchpoint VARCHAR(100),
            last_touchpoint VARCHAR(100),
            touchpoint_count INT DEFAULT 0,
            total_value DECIMAL(10,2) DEFAULT 0,
            conversion_count INT DEFAULT 0,
            journey_stage VARCHAR(50) DEFAULT 'awareness',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            
            UNIQUE KEY unique_customer (customer_id),
            INDEX idx_journey_stage (journey_stage),
            INDEX idx_conversion_analysis (conversion_count, total_value),
            INDEX idx_journey_timeline (created_at, updated_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get touchpoint details
     */
    private function get_touchpoint_details($touchpoint_ids) {
        if (empty($touchpoint_ids)) {
            return array();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_customer_touchpoints';
        
        $placeholders = implode(',', array_fill(0, count($touchpoint_ids), '%s'));
        $sql = "SELECT * FROM {$table_name} WHERE touchpoint_id IN ({$placeholders}) ORDER BY created_at ASC";
        
        $touchpoints = $wpdb->get_results($wpdb->prepare($sql, $touchpoint_ids), ARRAY_A);
        
        // Decode JSON fields
        foreach ($touchpoints as &$touchpoint) {
            $touchpoint['utm_data'] = json_decode($touchpoint['utm_data'], true) ?: array();
            $touchpoint['engagement_metrics'] = json_decode($touchpoint['engagement_metrics'], true) ?: array();
        }
        
        return $touchpoints;
    }
    
    /**
     * Store attribution analysis
     */
    private function store_attribution_analysis($conversion_id, $customer_id, $attribution_results) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_attribution_analysis';
        $this->maybe_create_attribution_analysis_table();
        
        $wpdb->insert($table_name, array(
            'conversion_id' => $conversion_id,
            'customer_id' => $customer_id,
            'attribution_results' => json_encode($attribution_results),
            'created_at' => current_time('mysql')
        ));
    }
    
    /**
     * Update journey with conversion
     */
    private function update_journey_conversion($customer_id, $conversion_data) {
        $journey = $this->get_customer_journey($customer_id);
        
        $journey['conversion_count']++;
        $journey['total_value'] += $conversion_data['value'] ?? 0;
        $journey['journey_stage'] = 'conversion';
        
        $this->store_customer_journey($customer_id, $journey);
    }
    
    /**
     * Create attribution analysis table
     */
    private function maybe_create_attribution_analysis_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_attribution_analysis';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            conversion_id VARCHAR(100) NOT NULL,
            customer_id VARCHAR(100) NOT NULL,
            attribution_results TEXT,
            created_at DATETIME NOT NULL,
            
            INDEX idx_conversion_analysis (conversion_id),
            INDEX idx_customer_analysis (customer_id),
            INDEX idx_analysis_timeline (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_track_micro_conversion() {
        check_ajax_referer('khm_journey_nonce', 'nonce');
        
        $touchpoint_data = array(
            'type' => sanitize_text_field($_POST['type'] ?? ''),
            'channel' => sanitize_text_field($_POST['channel'] ?? ''),
            'value' => floatval($_POST['value'] ?? 0),
            'timestamp' => current_time('mysql')
        );
        
        $touchpoint_id = $this->track_touchpoint($touchpoint_data);
        
        wp_send_json_success(array(
            'touchpoint_id' => $touchpoint_id,
            'message' => 'Micro-conversion tracked successfully'
        ));
    }
    
    /**
     * Track page engagement
     */
    public function track_page_engagement() {
        // This would typically be enhanced with JavaScript for real-time tracking
        $touchpoint_data = array(
            'type' => 'page_view',
            'channel' => 'website',
            'timestamp' => current_time('mysql'),
            'time_on_page' => 0, // Would be updated via AJAX
            'scroll_depth' => 0, // Would be updated via AJAX
            'interaction_type' => 'passive'
        );
        
        $this->track_touchpoint($touchpoint_data);
    }
    
    /**
     * Enhance journey analytics
     */
    public function enhance_journey_analytics($analytics_data, $customer_id) {
        $journey = $this->get_customer_journey($customer_id);
        
        $analytics_data['journey_metrics'] = array(
            'touchpoint_count' => $journey['touchpoint_count'],
            'journey_duration' => $this->calculate_journey_duration($journey),
            'conversion_rate' => $this->calculate_conversion_rate($journey),
            'average_touchpoint_value' => $this->calculate_avg_touchpoint_value($journey)
        );
        
        return $analytics_data;
    }
    
    /**
     * Calculate journey duration
     */
    private function calculate_journey_duration($journey) {
        if (empty($journey['touchpoints'])) {
            return 0;
        }
        
        $first_touchpoint = $this->get_touchpoint_details(array($journey['touchpoints'][0]))[0] ?? null;
        $last_touchpoint = $this->get_touchpoint_details(array($journey['last_touchpoint']))[0] ?? null;
        
        if (!$first_touchpoint || !$last_touchpoint) {
            return 0;
        }
        
        $start_time = strtotime($first_touchpoint['created_at']);
        $end_time = strtotime($last_touchpoint['created_at']);
        
        return $end_time - $start_time;
    }
    
    /**
     * Calculate conversion rate
     */
    private function calculate_conversion_rate($journey) {
        if ($journey['touchpoint_count'] === 0) {
            return 0;
        }
        
        return ($journey['conversion_count'] / $journey['touchpoint_count']) * 100;
    }
    
    /**
     * Calculate average touchpoint value
     */
    private function calculate_avg_touchpoint_value($journey) {
        if ($journey['touchpoint_count'] === 0) {
            return 0;
        }
        
        return $journey['total_value'] / $journey['touchpoint_count'];
    }
}
?>