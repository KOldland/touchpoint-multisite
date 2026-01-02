<?php
/**
 * User Synchronization Module
 *
 * @package TouchPoint_MailChimp
 */

defined('ABSPATH') or exit;

class TouchPoint_MailChimp_User_Sync {
    
    private static $instance = null;
    private $settings;
    private $api;
    private $logger;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->settings = TouchPoint_MailChimp_Settings::instance();
        $this->api = TouchPoint_MailChimp_API::instance();
        $this->logger = TouchPoint_MailChimp_Logger::instance();
        
        $this->init();
    }
    
    private function init() {
        // User registration hook
        add_action('user_register', array($this, 'sync_user_on_registration'));
        
        // Profile update hook
        add_action('profile_update', array($this, 'sync_user_on_update'), 10, 2);
        
        // User deletion hook
        add_action('delete_user', array($this, 'handle_user_deletion'));
        
        // Admin hooks
        add_action('wp_ajax_tmc_sync_users', array($this, 'ajax_sync_users'));
        add_action('wp_ajax_tmc_sync_single_user', array($this, 'ajax_sync_single_user'));
        
        // Bulk user actions
        add_filter('bulk_actions-users', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-users', array($this, 'handle_bulk_actions'), 10, 3);
        
        // User list column
        add_filter('manage_users_columns', array($this, 'add_user_column'));
        add_filter('manage_users_custom_column', array($this, 'render_user_column'), 10, 3);
    }
    
    /**
     * Sync user to MailChimp on registration
     */
    public function sync_user_on_registration($user_id) {
        if (!$this->is_sync_enabled()) {
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        // Only sync if user has valid email
        if (!is_email($user->user_email)) {
            return;
        }
        
        $this->sync_user_to_mailchimp($user_id);
    }
    
    /**
     * Sync user to MailChimp on profile update
     */
    public function sync_user_on_update($user_id, $old_user_data) {
        if (!$this->is_sync_enabled()) {
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        // Check if email changed
        if ($old_user_data->user_email !== $user->user_email) {
            $this->handle_email_change($user_id, $old_user_data->user_email, $user->user_email);
        } else {
            $this->sync_user_to_mailchimp($user_id);
        }
    }
    
    /**
     * Handle user deletion
     */
    public function handle_user_deletion($user_id) {
        if (!$this->is_sync_enabled()) {
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        $list_id = $this->settings->get_sync_list();
        if (!$list_id) {
            return;
        }
        
        $result = $this->api->unsubscribe_from_list($list_id, $user->user_email);
        
        if ($result['success']) {
            $this->logger->log("User {$user->user_email} unsubscribed from MailChimp due to deletion", 'info');
        } else {
            $this->logger->log("Failed to unsubscribe deleted user {$user->user_email}: " . $result['error'], 'error');
        }
    }
    
    /**
     * Sync individual user to MailChimp
     */
    public function sync_user_to_mailchimp($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $list_id = $this->settings->get_sync_list();
        if (!$list_id) {
            $this->logger->log('No sync list configured for user synchronization', 'warning');
            return false;
        }
        
        // Prepare subscriber data
        $subscriber_data = array(
            'email_address' => $user->user_email,
            'status' => 'subscribed',
            'merge_fields' => $this->get_user_merge_fields($user)
        );
        
        // Add tags
        $tags = $this->get_user_tags($user);
        if (!empty($tags)) {
            $subscriber_data['tags'] = $tags;
        }
        
        // Subscribe to list
        $result = $this->api->subscribe_to_list($list_id, $subscriber_data);
        
        if ($result['success']) {
            update_user_meta($user_id, 'tmc_synced', time());
            update_user_meta($user_id, 'tmc_sync_status', 'synced');
            
            $this->logger->log("User {$user->user_email} synced to MailChimp successfully", 'info');
            return true;
        } else {
            update_user_meta($user_id, 'tmc_sync_status', 'error');
            update_user_meta($user_id, 'tmc_sync_error', $result['error']);
            
            $this->logger->log("Failed to sync user {$user->user_email}: " . $result['error'], 'error');
            return false;
        }
    }
    
    /**
     * Handle email address change
     */
    private function handle_email_change($user_id, $old_email, $new_email) {
        $list_id = $this->settings->get_sync_list();
        if (!$list_id) {
            return;
        }
        
        // Check if old email exists in MailChimp
        $member_info = $this->api->get_list_member($list_id, $old_email);
        
        if ($member_info['success']) {
            // Update email address in MailChimp
            $result = $this->api->update_list_member($list_id, $old_email, array(
                'email_address' => $new_email
            ));
            
            if ($result['success']) {
                $this->logger->log("Updated email from {$old_email} to {$new_email} in MailChimp", 'info');
            } else {
                $this->logger->log("Failed to update email from {$old_email} to {$new_email}: " . $result['error'], 'error');
            }
        } else {
            // Old email not found, sync new email
            $this->sync_user_to_mailchimp($user_id);
        }
    }
    
    /**
     * Get user merge fields for MailChimp
     */
    private function get_user_merge_fields($user) {
        $merge_fields = array();
        
        // Basic fields
        if (!empty($user->first_name)) {
            $merge_fields['FNAME'] = $user->first_name;
        }
        
        if (!empty($user->last_name)) {
            $merge_fields['LNAME'] = $user->last_name;
        }
        
        // Custom field mappings
        $field_mappings = $this->settings->get_field_mappings();
        foreach ($field_mappings as $mailchimp_field => $wp_field) {
            $value = get_user_meta($user->ID, $wp_field, true);
            if (!empty($value)) {
                $merge_fields[$mailchimp_field] = $value;
            }
        }
        
        return apply_filters('tmc_user_merge_fields', $merge_fields, $user);
    }
    
    /**
     * Get user tags for MailChimp
     */
    private function get_user_tags($user) {
        $tags = array();
        
        // Add role as tag
        $roles = $user->roles;
        foreach ($roles as $role) {
            $tags[] = 'wp_role_' . $role;
        }
        
        // Add custom tags
        $custom_tags = get_user_meta($user->ID, 'tmc_tags', true);
        if (is_array($custom_tags)) {
            $tags = array_merge($tags, $custom_tags);
        }
        
        return apply_filters('tmc_user_tags', $tags, $user);
    }
    
    /**
     * Check if user sync is enabled
     */
    private function is_sync_enabled() {
        return $this->settings->get('enable_user_sync', false);
    }
    
    /**
     * AJAX handler for bulk user sync
     */
    public function ajax_sync_users() {
        check_ajax_referer('tmc_sync_users', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        $users = get_users(array(
            'number' => $per_page,
            'offset' => $offset,
            'fields' => 'ID'
        ));
        
        $synced = 0;
        $errors = 0;
        
        foreach ($users as $user_id) {
            if ($this->sync_user_to_mailchimp($user_id)) {
                $synced++;
            } else {
                $errors++;
            }
        }
        
        wp_send_json_success(array(
            'synced' => $synced,
            'errors' => $errors,
            'has_more' => count($users) === $per_page
        ));
    }
    
    /**
     * AJAX handler for single user sync
     */
    public function ajax_sync_single_user() {
        check_ajax_referer('tmc_sync_user', 'nonce');
        
        if (!current_user_can('edit_users')) {
            wp_die('Unauthorized');
        }
        
        $user_id = intval($_POST['user_id']);
        
        if ($this->sync_user_to_mailchimp($user_id)) {
            wp_send_json_success(array('message' => 'User synced successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to sync user'));
        }
    }
    
    /**
     * Add bulk actions to users list
     */
    public function add_bulk_actions($actions) {
        $actions['tmc_sync_to_mailchimp'] = __('Sync to MailChimp', 'touchpoint-mailchimp');
        return $actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $action, $user_ids) {
        if ($action !== 'tmc_sync_to_mailchimp') {
            return $redirect_to;
        }
        
        $synced = 0;
        foreach ($user_ids as $user_id) {
            if ($this->sync_user_to_mailchimp($user_id)) {
                $synced++;
            }
        }
        
        $redirect_to = add_query_arg('tmc_synced', $synced, $redirect_to);
        return $redirect_to;
    }
    
    /**
     * Add sync status column to users list
     */
    public function add_user_column($columns) {
        $columns['tmc_sync_status'] = __('MailChimp Sync', 'touchpoint-mailchimp');
        return $columns;
    }
    
    /**
     * Render sync status column
     */
    public function render_user_column($value, $column_name, $user_id) {
        if ($column_name !== 'tmc_sync_status') {
            return $value;
        }
        
        $status = get_user_meta($user_id, 'tmc_sync_status', true);
        $sync_time = get_user_meta($user_id, 'tmc_synced', true);
        
        switch ($status) {
            case 'synced':
                $value = '<span style="color: green;">✓ Synced</span>';
                if ($sync_time) {
                    $value .= '<br><small>' . human_time_diff($sync_time) . ' ago</small>';
                }
                break;
            case 'error':
                $error = get_user_meta($user_id, 'tmc_sync_error', true);
                $value = '<span style="color: red;">✗ Error</span>';
                if ($error) {
                    $value .= '<br><small title="' . esc_attr($error) . '">Hover for details</small>';
                }
                break;
            default:
                $value = '<span style="color: gray;">Not synced</span>';
                break;
        }
        
        // Add sync button
        $nonce = wp_create_nonce('tmc_sync_user');
        $value .= '<br><a href="#" class="tmc-sync-user" data-user-id="' . $user_id . '" data-nonce="' . $nonce . '">Sync Now</a>';
        
        return $value;
    }
}

// Initialize the module
TouchPoint_MailChimp_User_Sync::instance();