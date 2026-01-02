<?php

defined('ABSPATH') or exit;

/**
 * TouchPoint MailChimp Settings
 * 
 * Handles plugin settings and configuration
 */
class TouchPoint_MailChimp_Settings {
    
    private static $instance = null;
    private $settings = array();
    private $option_name = 'tmc_settings';
    
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
        $this->load_settings();
    }
    
    /**
     * Load settings from database
     */
    private function load_settings() {
        $defaults = array(
            'api_key' => '',
            'default_list' => '',
            'double_optin' => true,
            'replace_interests' => false,
            'user_sync_enabled' => false,
            'user_sync_role' => 'subscriber',
            'user_sync_list' => '',
            'ecommerce_enabled' => false,
            'store_id' => '',
            'store_name' => get_bloginfo('name'),
            'store_currency' => 'USD',
            'debug_mode' => false,
            'field_mappings' => array(
                'FNAME' => 'first_name',
                'LNAME' => 'last_name'
            )
        );
        
        $saved_settings = get_option($this->option_name, array());
        $this->settings = wp_parse_args($saved_settings, $defaults);
    }
    
    /**
     * Get a setting value
     */
    public function get($key, $default = null) {
        if (isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        return $default;
    }
    
    /**
     * Set a setting value
     */
    public function set($key, $value) {
        $this->settings[$key] = $value;
        return $this->save();
    }
    
    /**
     * Get all settings
     */
    public function get_all() {
        return $this->settings;
    }
    
    /**
     * Update multiple settings
     */
    public function update($new_settings) {
        $this->settings = wp_parse_args($new_settings, $this->settings);
        return $this->save();
    }
    
    /**
     * Save settings to database
     */
    public function save() {
        return update_option($this->option_name, $this->settings);
    }
    
    /**
     * Reset settings to defaults
     */
    public function reset() {
        delete_option($this->option_name);
        $this->load_settings();
        return true;
    }
    
    /**
     * Get API key
     */
    public function get_api_key() {
        return $this->get('api_key');
    }
    
    /**
     * Check if API key is configured
     */
    public function has_api_key() {
        return !empty($this->get('api_key'));
    }
    
    /**
     * Get default list ID
     */
    public function get_default_list() {
        return $this->get('default_list');
    }
    
    /**
     * Check if double opt-in is enabled
     */
    public function is_double_optin_enabled() {
        return (bool) $this->get('double_optin', true);
    }
    
    /**
     * Check if user sync is enabled
     */
    public function is_user_sync_enabled() {
        return (bool) $this->get('user_sync_enabled', false);
    }
    
    /**
     * Get user sync role
     */
    public function get_user_sync_role() {
        return $this->get('user_sync_role', 'subscriber');
    }
    
    /**
     * Get user sync list ID
     */
    public function get_user_sync_list() {
        return $this->get('user_sync_list');
    }
    
    /**
     * Check if e-commerce tracking is enabled
     */
    public function is_ecommerce_enabled() {
        return (bool) $this->get('ecommerce_enabled', false);
    }
    
    /**
     * Get store ID for e-commerce
     */
    public function get_store_id() {
        return $this->get('store_id');
    }
    
    /**
     * Get store name
     */
    public function get_store_name() {
        return $this->get('store_name', get_bloginfo('name'));
    }
    
    /**
     * Get store currency
     */
    public function get_store_currency() {
        return $this->get('store_currency', 'USD');
    }
    
    /**
     * Check if debug mode is enabled
     */
    public function is_debug_mode_enabled() {
        return (bool) $this->get('debug_mode', false);
    }
    
    /**
     * Get field mappings
     */
    public function get_field_mappings() {
        return $this->get('field_mappings', array());
    }
    
    /**
     * Get WordPress user fields for mapping
     */
    public function get_wp_user_fields() {
        return array(
            'user_login' => __('Username', 'touchpoint-mailchimp'),
            'user_email' => __('Email', 'touchpoint-mailchimp'),
            'user_nicename' => __('Nice name', 'touchpoint-mailchimp'),
            'user_url' => __('Website', 'touchpoint-mailchimp'),
            'display_name' => __('Display name', 'touchpoint-mailchimp'),
            'first_name' => __('First name', 'touchpoint-mailchimp'),
            'last_name' => __('Last name', 'touchpoint-mailchimp'),
            'description' => __('Biography', 'touchpoint-mailchimp'),
            'user_registered' => __('Registration date', 'touchpoint-mailchimp')
        );
    }
    
    /**
     * Validate settings before saving
     */
    public function validate($settings) {
        $validated = array();
        
        // API Key validation
        if (isset($settings['api_key'])) {
            $api_key = sanitize_text_field($settings['api_key']);
            if (!empty($api_key) && !preg_match('/^[a-f0-9]{32}-[a-z0-9]{2,4}$/', $api_key)) {
                add_settings_error('tmc_settings', 'invalid_api_key', __('Invalid API key format', 'touchpoint-mailchimp'));
            } else {
                $validated['api_key'] = $api_key;
            }
        }
        
        // List ID validation
        if (isset($settings['default_list'])) {
            $validated['default_list'] = sanitize_text_field($settings['default_list']);
        }
        
        // Boolean settings
        $boolean_settings = array('double_optin', 'replace_interests', 'user_sync_enabled', 'ecommerce_enabled', 'debug_mode');
        foreach ($boolean_settings as $setting) {
            if (isset($settings[$setting])) {
                $validated[$setting] = (bool) $settings[$setting];
            }
        }
        
        // Text settings
        $text_settings = array('user_sync_role', 'user_sync_list', 'store_id', 'store_name', 'store_currency');
        foreach ($text_settings as $setting) {
            if (isset($settings[$setting])) {
                $validated[$setting] = sanitize_text_field($settings[$setting]);
            }
        }
        
        // Field mappings
        if (isset($settings['field_mappings']) && is_array($settings['field_mappings'])) {
            $validated['field_mappings'] = array();
            foreach ($settings['field_mappings'] as $mc_field => $wp_field) {
                $validated['field_mappings'][sanitize_text_field($mc_field)] = sanitize_text_field($wp_field);
            }
        }
        
        return $validated;
    }
}