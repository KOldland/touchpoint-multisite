<?php
/**
 * KHM Attribution Session Manager
 * 
 * Handles session tracking, management, and attribution data persistence
 * across user sessions. Implements Phase 2 OOP patterns and architecture.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Session_Manager {
    
    private $performance_manager;
    private $database_manager;
    private $query_builder;
    private $session_config = array();
    private $current_session = null;
    private $attribution_data = array();
    private $fingerprint_data = array();
    
    /**
     * Constructor - Initialize session components
     */
    public function __construct() {
        $this->init_session_components();
        $this->setup_session_config();
        $this->load_current_session();
        $this->register_session_hooks();
    }
    
    /**
     * Initialize session performance components
     */
    private function init_session_components() {
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
    }
    
    /**
     * Setup session configuration
     */
    private function setup_session_config() {
        $this->session_config = array(
            'session_timeout' => 1800, // 30 minutes
            'attribution_window' => 30 * 24 * 3600, // 30 days
            'enable_fingerprinting' => true,
            'enable_cross_device' => true,
            'cookie_domain' => '',
            'cookie_secure' => is_ssl(),
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'session_regenerate_interval' => 300, // 5 minutes
            'max_attribution_events' => 50
        );
        
        // Allow configuration overrides
        $this->session_config = apply_filters('khm_session_config', $this->session_config);
    }
    
    /**
     * Register session hooks
     */
    private function register_session_hooks() {
        add_action('init', array($this, 'start_session_tracking'));
        add_action('wp_footer', array($this, 'update_session_data'));
        add_action('wp_logout', array($this, 'end_session'));
        
        // AJAX hooks for session management
        add_action('wp_ajax_khm_update_session', array($this, 'ajax_update_session'));
        add_action('wp_ajax_nopriv_khm_update_session', array($this, 'ajax_update_session'));
        
        // Performance hooks
        add_filter('khm_session_optimization', array($this, 'optimize_session_data'), 10, 2);
    }
    
    /**
     * Start session tracking
     */
    public function start_session_tracking() {
        if (!$this->should_track_session()) {
            return false;
        }
        
        $session_id = $this->get_or_create_session_id();
        $this->current_session = $this->load_session_data($session_id);
        
        if (!$this->current_session) {
            $this->current_session = $this->create_new_session($session_id);
        } else {
            $this->update_session_activity();
        }
        
        // Initialize attribution tracking
        $this->init_attribution_tracking();
        
        return $this->current_session;
    }
    
    /**
     * Check if session should be tracked
     */
    private function should_track_session() {
        // Don't track admin pages
        if (is_admin() && !wp_doing_ajax()) {
            return false;
        }
        
        // Don't track bots/crawlers
        if ($this->is_bot_request()) {
            return false;
        }
        
        // Don't track excluded user roles
        if ($this->is_excluded_user()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get or create session ID
     */
    private function get_or_create_session_id() {
        // Try to get session ID from cookie
        $session_id = isset($_COOKIE['khm_session_id']) ? sanitize_text_field($_COOKIE['khm_session_id']) : null;
        
        // Validate existing session ID
        if ($session_id && !$this->is_valid_session_id($session_id)) {
            $session_id = null;
        }
        
        // Create new session ID if needed
        if (!$session_id) {
            $session_id = $this->generate_session_id();
            $this->set_session_cookie($session_id);
        }
        
        return $session_id;
    }
    
    /**
     * Generate unique session ID
     */
    private function generate_session_id() {
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $timestamp = time();
        
        // Create unique hash
        $session_data = $user_id . '|' . $ip_address . '|' . $user_agent . '|' . $timestamp . '|' . wp_generate_password(12, false);
        $session_id = 'khm_' . hash('sha256', $session_data);
        
        return substr($session_id, 0, 32);
    }
    
    /**
     * Set session cookie
     */
    private function set_session_cookie($session_id) {
        $domain = $this->session_config['cookie_domain'] ?: '';
        $secure = $this->session_config['cookie_secure'];
        $httponly = $this->session_config['cookie_httponly'];
        $samesite = $this->session_config['cookie_samesite'];
        
        $cookie_options = array(
            'expires' => time() + $this->session_config['attribution_window'],
            'path' => '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite
        );
        
        setcookie('khm_session_id', $session_id, $cookie_options);
    }
    
    /**
     * Load session data from database
     */
    private function load_session_data($session_id) {
        if (!isset($this->query_builder)) {
            return null;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_session_tracking';
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE session_id = %s",
            $session_id
        ), ARRAY_A);
        
        if ($session) {
            // Decode attribution data
            $session['attribution_data'] = json_decode($session['attribution_data'], true) ?: array();
        }
        
        return $session;
    }
    
    /**
     * Create new session
     */
    private function create_new_session($session_id) {
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $current_url = $this->get_current_url();
        
        // Create fingerprint data
        $this->fingerprint_data = $this->generate_fingerprint_data();
        
        $session_data = array(
            'session_id' => $session_id,
            'user_id' => $user_id ?: null,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'first_visit' => current_time('mysql'),
            'last_activity' => current_time('mysql'),
            'page_views' => 1,
            'total_time_spent' => 0,
            'referrer_url' => $referrer,
            'entry_page' => $current_url,
            'exit_page' => $current_url,
            'attribution_data' => json_encode(array()),
            'created_at' => current_time('mysql')
        );
        
        // Insert into database
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_session_tracking';
        
        $wpdb->insert($table_name, $session_data);
        
        // Return session data with decoded attribution
        $session_data['attribution_data'] = array();
        
        return $session_data;
    }
    
    /**
     * Update session activity
     */
    private function update_session_activity() {
        if (!$this->current_session) {
            return false;
        }
        
        $session_id = $this->current_session['session_id'];
        $current_url = $this->get_current_url();
        
        // Calculate time spent
        $last_activity = strtotime($this->current_session['last_activity']);
        $time_diff = time() - $last_activity;
        
        // Only count reasonable time differences (not browser idle)
        if ($time_diff > 0 && $time_diff < 3600) {
            $this->current_session['total_time_spent'] += $time_diff;
        }
        
        // Update session data
        $update_data = array(
            'last_activity' => current_time('mysql'),
            'page_views' => $this->current_session['page_views'] + 1,
            'total_time_spent' => $this->current_session['total_time_spent'],
            'exit_page' => $current_url
        );
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_session_tracking';
        
        $wpdb->update(
            $table_name,
            $update_data,
            array('session_id' => $session_id)
        );
        
        // Update current session data
        $this->current_session = array_merge($this->current_session, $update_data);
        
        return true;
    }
    
    /**
     * Initialize attribution tracking for session
     */
    private function init_attribution_tracking() {
        if (!$this->current_session) {
            return;
        }
        
        // Parse UTM parameters
        $utm_data = $this->parse_utm_parameters();
        
        // Get attribution data from various sources
        $attribution_sources = array(
            'utm' => $utm_data,
            'referrer' => $this->parse_referrer_data(),
            'fingerprint' => $this->fingerprint_data,
            'session' => $this->current_session
        );
        
        // Store attribution data
        $this->store_attribution_data($attribution_sources);
    }
    
    /**
     * Parse UTM parameters
     */
    private function parse_utm_parameters() {
        $utm_params = array(
            'utm_source' => $_GET['utm_source'] ?? '',
            'utm_medium' => $_GET['utm_medium'] ?? '',
            'utm_campaign' => $_GET['utm_campaign'] ?? '',
            'utm_content' => $_GET['utm_content'] ?? '',
            'utm_term' => $_GET['utm_term'] ?? ''
        );
        
        // Clean and validate UTM parameters
        foreach ($utm_params as $key => $value) {
            $utm_params[$key] = sanitize_text_field($value);
        }
        
        return array_filter($utm_params);
    }
    
    /**
     * Parse referrer data
     */
    private function parse_referrer_data() {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        
        if (!$referrer) {
            return array('type' => 'direct');
        }
        
        $parsed_url = parse_url($referrer);
        $domain = $parsed_url['host'] ?? '';
        
        // Categorize referrer
        $referrer_data = array(
            'url' => $referrer,
            'domain' => $domain,
            'type' => $this->categorize_referrer($domain)
        );
        
        return $referrer_data;
    }
    
    /**
     * Categorize referrer domain
     */
    private function categorize_referrer($domain) {
        $search_engines = array('google', 'bing', 'yahoo', 'duckduckgo', 'baidu');
        $social_networks = array('facebook', 'twitter', 'linkedin', 'instagram', 'pinterest');
        
        foreach ($search_engines as $engine) {
            if (strpos($domain, $engine) !== false) {
                return 'search';
            }
        }
        
        foreach ($social_networks as $network) {
            if (strpos($domain, $network) !== false) {
                return 'social';
            }
        }
        
        return 'referral';
    }
    
    /**
     * Generate fingerprint data
     */
    private function generate_fingerprint_data() {
        if (!$this->session_config['enable_fingerprinting']) {
            return array();
        }
        
        $fingerprint_data = array(
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            'screen_resolution' => '', // Will be populated via JavaScript
            'timezone' => '', // Will be populated via JavaScript
            'timestamp' => time()
        );
        
        // Generate fingerprint hash
        $fingerprint_string = implode('|', $fingerprint_data);
        $fingerprint_data['hash'] = hash('sha256', $fingerprint_string);
        
        return $fingerprint_data;
    }
    
    /**
     * Store attribution data
     */
    private function store_attribution_data($attribution_sources) {
        // Merge with existing attribution data
        $current_attribution = $this->current_session['attribution_data'] ?: array();
        
        // Add new attribution event
        $attribution_event = array(
            'timestamp' => time(),
            'sources' => $attribution_sources,
            'page_url' => $this->get_current_url()
        );
        
        $current_attribution[] = $attribution_event;
        
        // Limit attribution events
        if (count($current_attribution) > $this->session_config['max_attribution_events']) {
            $current_attribution = array_slice($current_attribution, -$this->session_config['max_attribution_events']);
        }
        
        // Update session with new attribution data
        $this->update_session_attribution($current_attribution);
    }
    
    /**
     * Update session attribution data
     */
    private function update_session_attribution($attribution_data) {
        if (!$this->current_session) {
            return false;
        }
        
        $session_id = $this->current_session['session_id'];
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_session_tracking';
        
        $wpdb->update(
            $table_name,
            array('attribution_data' => json_encode($attribution_data)),
            array('session_id' => $session_id)
        );
        
        $this->current_session['attribution_data'] = $attribution_data;
        $this->attribution_data = $attribution_data;
        
        return true;
    }
    
    /**
     * Get current session data
     */
    public function get_current_session() {
        return $this->current_session;
    }
    
    /**
     * Get attribution data for current session
     */
    public function get_attribution_data() {
        return $this->attribution_data;
    }
    
    /**
     * Update session data via AJAX
     */
    public function ajax_update_session() {
        check_ajax_referer('khm_session_nonce', 'nonce');
        
        $session_data = $_POST['session_data'] ?? array();
        $fingerprint_data = $_POST['fingerprint_data'] ?? array();
        
        // Update fingerprint data if provided
        if (!empty($fingerprint_data)) {
            $this->update_fingerprint_data($fingerprint_data);
        }
        
        // Update session activity
        $this->update_session_activity();
        
        wp_send_json_success(array(
            'session_id' => $this->current_session['session_id'] ?? '',
            'attribution_data' => $this->attribution_data
        ));
    }
    
    /**
     * Update fingerprint data
     */
    private function update_fingerprint_data($fingerprint_data) {
        // Sanitize fingerprint data
        $clean_data = array(
            'screen_resolution' => sanitize_text_field($fingerprint_data['screen_resolution'] ?? ''),
            'timezone' => sanitize_text_field($fingerprint_data['timezone'] ?? ''),
            'browser_plugins' => sanitize_text_field($fingerprint_data['browser_plugins'] ?? ''),
            'webgl_vendor' => sanitize_text_field($fingerprint_data['webgl_vendor'] ?? '')
        );
        
        // Merge with existing fingerprint data
        $this->fingerprint_data = array_merge($this->fingerprint_data, $clean_data);
        
        // Update attribution data with enhanced fingerprint
        if (!empty($this->attribution_data)) {
            $last_attribution = end($this->attribution_data);
            $last_attribution['sources']['fingerprint'] = $this->fingerprint_data;
            $this->attribution_data[count($this->attribution_data) - 1] = $last_attribution;
            
            $this->update_session_attribution($this->attribution_data);
        }
    }
    
    /**
     * End session
     */
    public function end_session() {
        if (!$this->current_session) {
            return;
        }
        
        // Final session update
        $this->update_session_activity();
        
        // Clear session cookie
        setcookie('khm_session_id', '', time() - 3600, '/');
        
        $this->current_session = null;
        $this->attribution_data = array();
    }
    
    /**
     * Utility methods
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    private function get_current_url() {
        $protocol = is_ssl() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return $protocol . '://' . $host . $uri;
    }
    
    private function is_valid_session_id($session_id) {
        return preg_match('/^khm_[a-f0-9]{28}$/', $session_id);
    }
    
    private function is_bot_request() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $bot_patterns = array('bot', 'crawler', 'spider', 'scraper');
        
        foreach ($bot_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function is_excluded_user() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $excluded_roles = array('administrator', 'editor');
        $user = wp_get_current_user();
        
        foreach ($excluded_roles as $role) {
            if (in_array($role, $user->roles)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Optimize session data
     */
    public function optimize_session_data($data, $context) {
        // Remove old attribution events
        if (isset($data['attribution_data']) && is_array($data['attribution_data'])) {
            $cutoff_time = time() - $this->session_config['attribution_window'];
            
            $data['attribution_data'] = array_filter($data['attribution_data'], function($event) use ($cutoff_time) {
                return isset($event['timestamp']) && $event['timestamp'] > $cutoff_time;
            });
        }
        
        return $data;
    }
    
    /**
     * Get session statistics
     */
    public function get_session_statistics() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_session_tracking';
        
        $stats = array(
            'total_sessions' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"),
            'active_sessions' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)"),
            'avg_session_duration' => $wpdb->get_var("SELECT AVG(total_time_spent) FROM {$table_name}"),
            'avg_page_views' => $wpdb->get_var("SELECT AVG(page_views) FROM {$table_name}")
        );
        
        return $stats;
    }
}
?>