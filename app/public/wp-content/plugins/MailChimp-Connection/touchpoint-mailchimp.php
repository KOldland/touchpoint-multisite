<?php
/*
Plugin Name: TouchPoint MailChimp Integration
Plugin URI: https://touchpoint.marketing/
Description: Complete MailChimp integration for TouchPoint Marketing Suite. Provides promotional email management separate from transactional emails.
Version: 1.0.0
Author: TouchPoint Marketing
Author URI: https://touchpoint.marketing/
License: GPL v3
Text Domain: touchpoint-mailchimp

TouchPoint MailChimp Integration
Copyright (C) 2025, TouchPoint Marketing

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// Prevent direct file access
defined('ABSPATH') or exit;

// Define constants
define('TMC_VERSION', '1.0.0');
define('TMC_PLUGIN_FILE', __FILE__);
define('TMC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TMC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main TouchPoint MailChimp Integration class
 */
class TouchPoint_MailChimp {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the plugin
     */
    private function init() {
        // Check dependencies
        add_action('plugins_loaded', array($this, 'check_dependencies'));
        
        // Load plugin
        add_action('plugins_loaded', array($this, 'load_plugin'), 20);
        
        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Check if required dependencies are available
     */
    public function check_dependencies() {
        $dependencies_met = true;
        
        // Check if KHM plugin is active (for integration)
        if (!function_exists('khm_call_service')) {
            add_action('admin_notices', array($this, 'khm_missing_notice'));
            $dependencies_met = false;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            $dependencies_met = false;
        }
        
        return $dependencies_met;
    }
    
    /**
     * Load the plugin components
     */
    public function load_plugin() {
        // Load autoloader
        require_once TMC_PLUGIN_DIR . 'includes/class-autoloader.php';
        TouchPoint_MailChimp_Autoloader::register();
        
        // Load core classes
        $this->load_core_classes();
        
        // Load modules
        $this->load_modules();
        
        // Initialize admin if in admin
        if (is_admin()) {
            $this->load_admin();
        }
        
        // Load frontend
        if (!is_admin()) {
            $this->load_frontend();
        }
        
        // Hook into WordPress
        $this->add_hooks();
        
        // Plugin loaded action
        do_action('touchpoint_mailchimp_loaded');
    }
    
    /**
     * Load core classes
     */
    private function load_core_classes() {
        // Load API class
        require_once TMC_PLUGIN_DIR . 'includes/class-api.php';
        
        // Load settings class
        require_once TMC_PLUGIN_DIR . 'includes/class-settings.php';
        
        // Load logger
        require_once TMC_PLUGIN_DIR . 'includes/class-logger.php';
        
        // Initialize core services
        TouchPoint_MailChimp_Settings::instance();
        TouchPoint_MailChimp_Logger::instance();
    }
    
    /**
     * Load modules (similar to MC4WP structure)
     */
    private function load_modules() {
        $modules = array(
            'ajax-forms',
            'user-sync', 
            'ecommerce-tracking',
            'email-notifications',
            'subscription-forms'
        );
        
        foreach ($modules as $module) {
            $module_file = TMC_PLUGIN_DIR . 'modules/' . $module . '/' . $module . '.php';
            if (file_exists($module_file)) {
                require_once $module_file;
            }
        }
    }
    
    /**
     * Load admin components
     */
    private function load_admin() {
        require_once TMC_PLUGIN_DIR . 'includes/admin/class-admin.php';
        TouchPoint_MailChimp_Admin::instance();
    }
    
    /**
     * Load frontend components
     */
    private function load_frontend() {
        require_once TMC_PLUGIN_DIR . 'includes/frontend/class-frontend.php';
        TouchPoint_MailChimp_Frontend::instance();
    }
    
    /**
     * Add WordPress hooks
     */
    private function add_hooks() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));
        
        // Add shortcodes
        add_shortcode('tmc_form', array($this, 'render_subscription_form'));
        add_shortcode('tmc_mailchimp_form', array($this, 'render_subscription_form')); // Alternative
        
        // AJAX handlers
        add_action('wp_ajax_tmc_subscribe', array($this, 'handle_ajax_subscription'));
        add_action('wp_ajax_nopriv_tmc_subscribe', array($this, 'handle_ajax_subscription'));
        
        // Integration hooks
        add_action('init', array($this, 'init_integrations'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style('tmc-frontend', TMC_PLUGIN_URL . 'assets/css/frontend.css', array(), TMC_VERSION);
        wp_enqueue_script('tmc-frontend', TMC_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), TMC_VERSION, true);
        
        // Localize script
        wp_localize_script('tmc-frontend', 'tmc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tmc_ajax_nonce'),
            'loading_text' => __('Subscribing...', 'touchpoint-mailchimp'),
            'success_text' => __('Successfully subscribed!', 'touchpoint-mailchimp'),
            'error_text' => __('Subscription failed. Please try again.', 'touchpoint-mailchimp')
        ));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'touchpoint-mailchimp') === false) {
            return;
        }
        
        wp_enqueue_style('tmc-admin', TMC_PLUGIN_URL . 'assets/css/admin.css', array(), TMC_VERSION);
        wp_enqueue_script('tmc-admin', TMC_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), TMC_VERSION, true);
        
        wp_localize_script('tmc-admin', 'tmc_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tmc_admin_nonce')
        ));
    }
    
    /**
     * Render subscription form shortcode
     */
    public function render_subscription_form($atts) {
        $atts = shortcode_atts(array(
            'list_id' => '',
            'style' => 'default',
            'show_interests' => false,
            'success_message' => __('Thank you for subscribing!', 'touchpoint-mailchimp'),
            'error_message' => __('Subscription failed. Please try again.', 'touchpoint-mailchimp')
        ), $atts);
        
        ob_start();
        include TMC_PLUGIN_DIR . 'templates/subscription-form.php';
        return ob_get_clean();
    }
    
    /**
     * Handle AJAX subscription
     */
    public function handle_ajax_subscription() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tmc_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $email = sanitize_email($_POST['email']);
        $list_id = sanitize_text_field($_POST['list_id']);
        $interests = isset($_POST['interests']) ? array_map('sanitize_text_field', $_POST['interests']) : array();
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address', 'touchpoint-mailchimp')));
        }
        
        // Use API to subscribe
        $api = TouchPoint_MailChimp_API::instance();
        $result = $api->subscribe_to_list($list_id, $email, array(), $interests);
        
        if ($result['success']) {
            // Log successful subscription
            TouchPoint_MailChimp_Logger::log('Successful subscription: ' . $email . ' to list ' . $list_id);
            
            // Hook for integrations
            do_action('tmc_user_subscribed', $email, $list_id, $interests);
            
            wp_send_json_success(array('message' => __('Successfully subscribed!', 'touchpoint-mailchimp')));
        } else {
            // Log error
            TouchPoint_MailChimp_Logger::log('Subscription failed: ' . $email . ' - ' . $result['error']);
            
            wp_send_json_error(array('message' => $result['error']));
        }
    }

    /**
     * Register Elementor widget for subscription form.
     */
    public function register_elementor_widget($widgets_manager) {
        if (! class_exists('\Elementor\Widget_Base')) {
            return;
        }

        $widget_file = TMC_PLUGIN_DIR . 'includes/Elementor/FormWidget.php';
        if (file_exists($widget_file)) {
            require_once $widget_file;
        }

        if (class_exists('\TMC_Form_Widget')) {
            $widgets_manager->register(new \TMC_Form_Widget());
        }
    }
    
    /**
     * Initialize integrations with other systems
     */
    public function init_integrations() {
        // KHM Integration
        if (function_exists('khm_call_service')) {
            // Integration stub unavailable; skip loading missing file.
        }
        
        // WooCommerce Integration
        if (class_exists('WooCommerce')) {
            require_once TMC_PLUGIN_DIR . 'includes/integrations/class-woocommerce-integration.php';
            TouchPoint_MailChimp_WooCommerce_Integration::instance();
        }
        
        // Easy Digital Downloads Integration
        if (class_exists('Easy_Digital_Downloads')) {
            require_once TMC_PLUGIN_DIR . 'includes/integrations/class-edd-integration.php';
            TouchPoint_MailChimp_EDD_Integration::instance();
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron jobs
        $this->schedule_cron_jobs();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('tmc_sync_users');
        wp_clear_scheduled_hook('tmc_process_queue');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Subscription log table
        $table_name = $wpdb->prefix . 'tmc_subscription_log';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            list_id varchar(100) NOT NULL,
            status varchar(50) NOT NULL,
            interests text,
            user_id bigint(20) unsigned,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY list_id (list_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Sync queue table
        $table_name_queue = $wpdb->prefix . 'tmc_sync_queue';
        $sql_queue = "CREATE TABLE $table_name_queue (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            action varchar(50) NOT NULL,
            list_id varchar(100) NOT NULL,
            data text,
            attempts int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // E-commerce events table
        $table_name_ecommerce = $wpdb->prefix . 'tmc_ecommerce_events';
        $sql_ecommerce = "CREATE TABLE $table_name_ecommerce (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            order_id varchar(100),
            customer_email varchar(255),
            list_id varchar(100),
            product_data text,
            revenue decimal(10,2),
            currency varchar(10),
            processed boolean DEFAULT FALSE,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY customer_email (customer_email),
            KEY processed (processed),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql_queue);
        dbDelta($sql_ecommerce);
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'tmc_api_key' => '',
            'tmc_default_list' => '',
            'tmc_double_optin' => true,
            'tmc_replace_interests' => false,
            'tmc_user_sync_enabled' => false,
            'tmc_user_sync_role' => 'subscriber',
            'tmc_ecommerce_enabled' => false,
            'tmc_debug_mode' => false
        );
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Schedule cron jobs
     */
    private function schedule_cron_jobs() {
        // Schedule user sync
        if (!wp_next_scheduled('tmc_sync_users')) {
            wp_schedule_event(time(), 'daily', 'tmc_sync_users');
        }
        
        // Schedule queue processing
        if (!wp_next_scheduled('tmc_process_queue')) {
            wp_schedule_event(time(), 'hourly', 'tmc_process_queue');
        }
    }
    
    /**
     * KHM missing notice
     */
    public function khm_missing_notice() {
        echo '<div class="notice notice-warning"><p>';
        echo __('TouchPoint MailChimp Integration works best with the KHM plugin for enhanced membership integration.', 'touchpoint-mailchimp');
        echo '</p></div>';
    }
    
    /**
     * PHP version notice
     */
    public function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo sprintf(__('TouchPoint MailChimp Integration requires PHP 7.4 or higher. You are running PHP %s.', 'touchpoint-mailchimp'), PHP_VERSION);
        echo '</p></div>';
    }
}

/**
 * Initialize the plugin
 */
function touchpoint_mailchimp() {
    return TouchPoint_MailChimp::instance();
}

// Start the plugin
touchpoint_mailchimp();
