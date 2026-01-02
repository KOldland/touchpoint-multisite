<?php

defined('ABSPATH') or exit;

/**
 * TouchPoint MailChimp Admin
 * 
 * Handles all admin interface functionality
 */
class TouchPoint_MailChimp_Admin {
    
    private static $instance = null;
    private $settings;
    
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
        $this->settings = TouchPoint_MailChimp_Settings::instance();
        $this->init();
    }
    
    /**
     * Initialize admin
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_tmc_test_api_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_tmc_get_lists', array($this, 'get_lists'));
        add_action('wp_ajax_tmc_sync_users', array($this, 'sync_users'));
        add_action('wp_ajax_tmc_clear_logs', array($this, 'clear_logs'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('TouchPoint MailChimp', 'touchpoint-mailchimp'),
            __('MailChimp', 'touchpoint-mailchimp'),
            'manage_options',
            'touchpoint-mailchimp',
            array($this, 'render_main_page'),
            'dashicons-email-alt',
            30
        );
        
        add_submenu_page(
            'touchpoint-mailchimp',
            __('Settings', 'touchpoint-mailchimp'),
            __('Settings', 'touchpoint-mailchimp'),
            'manage_options',
            'touchpoint-mailchimp',
            array($this, 'render_main_page')
        );
        
        add_submenu_page(
            'touchpoint-mailchimp',
            __('User Sync', 'touchpoint-mailchimp'),
            __('User Sync', 'touchpoint-mailchimp'),
            'manage_options',
            'touchpoint-mailchimp-user-sync',
            array($this, 'render_user_sync_page')
        );
        
        add_submenu_page(
            'touchpoint-mailchimp',
            __('Forms', 'touchpoint-mailchimp'),
            __('Forms', 'touchpoint-mailchimp'),
            'manage_options',
            'touchpoint-mailchimp-forms',
            array($this, 'render_forms_page')
        );
        
        add_submenu_page(
            'touchpoint-mailchimp',
            __('Logs', 'touchpoint-mailchimp'),
            __('Logs', 'touchpoint-mailchimp'),
            'manage_options',
            'touchpoint-mailchimp-logs',
            array($this, 'render_logs_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'tmc_settings_group',
            'tmc_settings',
            array($this, 'validate_settings')
        );
        
        // API Settings Section
        add_settings_section(
            'tmc_api_settings',
            __('API Settings', 'touchpoint-mailchimp'),
            array($this, 'render_api_settings_section'),
            'touchpoint-mailchimp'
        );
        
        add_settings_field(
            'api_key',
            __('API Key', 'touchpoint-mailchimp'),
            array($this, 'render_api_key_field'),
            'touchpoint-mailchimp',
            'tmc_api_settings'
        );
        
        add_settings_field(
            'default_list',
            __('Default List', 'touchpoint-mailchimp'),
            array($this, 'render_default_list_field'),
            'touchpoint-mailchimp',
            'tmc_api_settings'
        );
        
        // Subscription Settings Section
        add_settings_section(
            'tmc_subscription_settings',
            __('Subscription Settings', 'touchpoint-mailchimp'),
            array($this, 'render_subscription_settings_section'),
            'touchpoint-mailchimp'
        );
        
        add_settings_field(
            'double_optin',
            __('Double Opt-in', 'touchpoint-mailchimp'),
            array($this, 'render_double_optin_field'),
            'touchpoint-mailchimp',
            'tmc_subscription_settings'
        );
        
        // User Sync Settings Section
        add_settings_section(
            'tmc_user_sync_settings',
            __('User Sync Settings', 'touchpoint-mailchimp'),
            array($this, 'render_user_sync_settings_section'),
            'touchpoint-mailchimp'
        );
        
        add_settings_field(
            'user_sync_enabled',
            __('Enable User Sync', 'touchpoint-mailchimp'),
            array($this, 'render_user_sync_enabled_field'),
            'touchpoint-mailchimp',
            'tmc_user_sync_settings'
        );
        
        // E-commerce Settings Section
        add_settings_section(
            'tmc_ecommerce_settings',
            __('E-commerce Settings', 'touchpoint-mailchimp'),
            array($this, 'render_ecommerce_settings_section'),
            'touchpoint-mailchimp'
        );
        
        add_settings_field(
            'ecommerce_enabled',
            __('Enable E-commerce Tracking', 'touchpoint-mailchimp'),
            array($this, 'render_ecommerce_enabled_field'),
            'touchpoint-mailchimp',
            'tmc_ecommerce_settings'
        );
    }
    
    /**
     * Validate settings
     */
    public function validate_settings($input) {
        return $this->settings->validate($input);
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'touchpoint-mailchimp') === false) {
            return;
        }
        
        wp_enqueue_script('tmc-admin', TMC_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), TMC_VERSION, true);
        wp_enqueue_style('tmc-admin', TMC_PLUGIN_URL . 'assets/css/admin.css', array(), TMC_VERSION);
        
        wp_localize_script('tmc-admin', 'tmc_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tmc_admin_nonce'),
            'strings' => array(
                'testing_connection' => __('Testing connection...', 'touchpoint-mailchimp'),
                'connection_successful' => __('Connection successful!', 'touchpoint-mailchimp'),
                'connection_failed' => __('Connection failed:', 'touchpoint-mailchimp'),
                'syncing_users' => __('Syncing users...', 'touchpoint-mailchimp'),
                'sync_complete' => __('Sync complete!', 'touchpoint-mailchimp'),
                'sync_failed' => __('Sync failed:', 'touchpoint-mailchimp')
            )
        ));
    }
    
    /**
     * Render main settings page
     */
    public function render_main_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors('tmc_settings'); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('tmc_settings_group');
                do_settings_sections('touchpoint-mailchimp');
                submit_button();
                ?>
            </form>
            
            <div class="tmc-api-test">
                <h3><?php _e('Test API Connection', 'touchpoint-mailchimp'); ?></h3>
                <button type="button" id="tmc-test-connection" class="button button-secondary">
                    <?php _e('Test Connection', 'touchpoint-mailchimp'); ?>
                </button>
                <div id="tmc-connection-result"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render user sync page
     */
    public function render_user_sync_page() {
        $queue_count = $this->get_sync_queue_count();
        $last_sync = get_option('tmc_last_sync', null);
        
        ?>
        <div class="wrap">
            <h1><?php _e('User Sync', 'touchpoint-mailchimp'); ?></h1>
            
            <div class="tmc-sync-stats">
                <div class="tmc-stat-box">
                    <h3><?php _e('Queue Status', 'touchpoint-mailchimp'); ?></h3>
                    <p><?php printf(__('%d users in sync queue', 'touchpoint-mailchimp'), $queue_count); ?></p>
                </div>
                
                <?php if ($last_sync): ?>
                <div class="tmc-stat-box">
                    <h3><?php _e('Last Sync', 'touchpoint-mailchimp'); ?></h3>
                    <p><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync))); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="tmc-sync-actions">
                <button type="button" id="tmc-sync-users" class="button button-primary">
                    <?php _e('Sync All Users Now', 'touchpoint-mailchimp'); ?>
                </button>
                <p class="description">
                    <?php _e('This will add all WordPress users to the sync queue for processing.', 'touchpoint-mailchimp'); ?>
                </p>
            </div>
            
            <div id="tmc-sync-result"></div>
        </div>
        <?php
    }
    
    /**
     * Render forms page
     */
    public function render_forms_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Subscription Forms', 'touchpoint-mailchimp'); ?></h1>
            
            <div class="tmc-forms-help">
                <h3><?php _e('How to Use', 'touchpoint-mailchimp'); ?></h3>
                <p><?php _e('Add subscription forms to your site using these shortcodes:', 'touchpoint-mailchimp'); ?></p>
                
                <h4><?php _e('Basic Form', 'touchpoint-mailchimp'); ?></h4>
                <code>[tmc_form]</code>
                
                <h4><?php _e('Form with Specific List', 'touchpoint-mailchimp'); ?></h4>
                <code>[tmc_form list_id="your_list_id"]</code>
                
                <h4><?php _e('Inline Form', 'touchpoint-mailchimp'); ?></h4>
                <code>[tmc_form style="inline"]</code>
                
                <h4><?php _e('Form with Interest Groups', 'touchpoint-mailchimp'); ?></h4>
                <code>[tmc_form show_interests="true"]</code>
            </div>
            
            <div class="tmc-form-preview">
                <h3><?php _e('Form Preview', 'touchpoint-mailchimp'); ?></h3>
                <?php echo do_shortcode('[tmc_form]'); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render logs page
     */
    public function render_logs_page() {
        $logger = TouchPoint_MailChimp_Logger::instance();
        $logs = $logger->get_logs(50);
        $log_size = $logger->get_log_size();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Debug Logs', 'touchpoint-mailchimp'); ?></h1>
            
            <div class="tmc-logs-header">
                <p><?php printf(__('Log file size: %s', 'touchpoint-mailchimp'), size_format($log_size)); ?></p>
                <button type="button" id="tmc-clear-logs" class="button button-secondary">
                    <?php _e('Clear Logs', 'touchpoint-mailchimp'); ?>
                </button>
            </div>
            
            <div class="tmc-logs-container">
                <?php if (empty($logs)): ?>
                    <p><?php _e('No logs found.', 'touchpoint-mailchimp'); ?></p>
                <?php else: ?>
                    <pre class="tmc-logs"><?php echo esc_html(implode("\n", $logs)); ?></pre>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    // Field render methods
    public function render_api_settings_section() {
        echo '<p>' . __('Configure your MailChimp API connection.', 'touchpoint-mailchimp') . '</p>';
    }
    
    public function render_api_key_field() {
        $value = $this->settings->get('api_key');
        echo '<input type="password" name="tmc_settings[api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Enter your MailChimp API key.', 'touchpoint-mailchimp') . '</p>';
    }
    
    public function render_default_list_field() {
        $value = $this->settings->get('default_list');
        echo '<select name="tmc_settings[default_list]" id="tmc-default-list">';
        echo '<option value="">' . __('Select a list...', 'touchpoint-mailchimp') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Default list for new subscriptions.', 'touchpoint-mailchimp') . '</p>';
    }
    
    public function render_subscription_settings_section() {
        echo '<p>' . __('Configure subscription behavior.', 'touchpoint-mailchimp') . '</p>';
    }
    
    public function render_double_optin_field() {
        $value = $this->settings->get('double_optin', true);
        echo '<input type="checkbox" name="tmc_settings[double_optin]" value="1" ' . checked(1, $value, false) . ' />';
        echo ' ' . __('Require double opt-in for new subscriptions', 'touchpoint-mailchimp');
    }
    
    public function render_user_sync_settings_section() {
        echo '<p>' . __('Automatically sync WordPress users to MailChimp.', 'touchpoint-mailchimp') . '</p>';
    }
    
    public function render_user_sync_enabled_field() {
        $value = $this->settings->get('user_sync_enabled', false);
        echo '<input type="checkbox" name="tmc_settings[user_sync_enabled]" value="1" ' . checked(1, $value, false) . ' />';
        echo ' ' . __('Enable automatic user synchronization', 'touchpoint-mailchimp');
    }
    
    public function render_ecommerce_settings_section() {
        echo '<p>' . __('Track e-commerce events in MailChimp.', 'touchpoint-mailchimp') . '</p>';
    }
    
    public function render_ecommerce_enabled_field() {
        $value = $this->settings->get('ecommerce_enabled', false);
        echo '<input type="checkbox" name="tmc_settings[ecommerce_enabled]" value="1" ' . checked(1, $value, false) . ' />';
        echo ' ' . __('Enable e-commerce tracking', 'touchpoint-mailchimp');
    }
    
    // AJAX handlers
    public function test_api_connection() {
        check_ajax_referer('tmc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $api = TouchPoint_MailChimp_API::instance();
        $result = $api->test_connection();
        
        wp_send_json($result);
    }
    
    public function get_lists() {
        check_ajax_referer('tmc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $api = TouchPoint_MailChimp_API::instance();
        $result = $api->get_lists();
        
        wp_send_json($result);
    }
    
    public function sync_users() {
        check_ajax_referer('tmc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Add all users to sync queue
        $users = get_users(array('fields' => 'ID'));
        $count = 0;
        
        foreach ($users as $user_id) {
            if ($this->add_user_to_sync_queue($user_id)) {
                $count++;
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d users added to sync queue', 'touchpoint-mailchimp'), $count)
        ));
    }
    
    public function clear_logs() {
        check_ajax_referer('tmc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $logger = TouchPoint_MailChimp_Logger::instance();
        $result = $logger->clear_logs();
        
        if ($result) {
            wp_send_json_success(array('message' => __('Logs cleared successfully', 'touchpoint-mailchimp')));
        } else {
            wp_send_json_error(array('message' => __('Failed to clear logs', 'touchpoint-mailchimp')));
        }
    }
    
    // Helper methods
    private function get_sync_queue_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tmc_sync_queue';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
    }
    
    private function add_user_to_sync_queue($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tmc_sync_queue';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND status = 'pending'",
            $user_id
        ));
        
        if (!$existing) {
            return $wpdb->insert($table_name, array(
                'user_id' => $user_id,
                'action' => 'sync',
                'list_id' => $this->settings->get('user_sync_list'),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ));
        }
        
        return false;
    }
}