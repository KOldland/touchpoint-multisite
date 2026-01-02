<?php
/**
 * KH Events Integration Manager
 *
 * Manages third-party integrations and service connections
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Integration_Manager {

    /**
     * Available integrations
     */
    private $integrations = array();

    /**
     * Initialize integrations
     */
    public function init() {
        $this->register_integrations();
        $this->load_active_integrations();
    }

    /**
     * Register available integrations
     */
    private function register_integrations() {
        $this->integrations = array(
            'mailchimp' => array(
                'id' => 'mailchimp',
                'name' => __('MailChimp', 'kh-events'),
                'description' => __('Sync event attendees with MailChimp lists', 'kh-events'),
                'icon' => 'mailchimp-icon',
                'settings_url' => admin_url('edit.php?post_type=kh_event&page=kh-events-api&tab=integrations&integration=mailchimp'),
                'class' => 'KH_Events_MailChimp_Integration',
                'active' => false
            ),
            'zapier' => array(
                'id' => 'zapier',
                'name' => __('Zapier', 'kh-events'),
                'description' => __('Connect KH Events with 2000+ apps via Zapier', 'kh-events'),
                'icon' => 'zapier-icon',
                'settings_url' => admin_url('edit.php?post_type=kh_event&page=kh-events-api&tab=integrations&integration=zapier'),
                'class' => 'KH_Events_Zapier_Integration',
                'active' => false
            ),
            'google_calendar' => array(
                'id' => 'google_calendar',
                'name' => __('Google Calendar', 'kh-events'),
                'description' => __('Sync events with Google Calendar', 'kh-events'),
                'icon' => 'google-calendar-icon',
                'settings_url' => admin_url('edit.php?post_type=kh_event&page=kh-events-api&tab=integrations&integration=google_calendar'),
                'class' => 'KH_Events_Google_Calendar_Integration',
                'active' => false
            ),
            'outlook_calendar' => array(
                'id' => 'outlook_calendar',
                'name' => __('Outlook Calendar', 'kh-events'),
                'description' => __('Sync events with Outlook Calendar', 'kh-events'),
                'icon' => 'outlook-calendar-icon',
                'settings_url' => admin_url('edit.php?post_type=kh_event&page=kh-events-api&tab=integrations&integration=outlook_calendar'),
                'class' => 'KH_Events_Outlook_Calendar_Integration',
                'active' => false
            ),
            'slack' => array(
                'id' => 'slack',
                'name' => __('Slack', 'kh-events'),
                'description' => __('Send event notifications to Slack channels', 'kh-events'),
                'icon' => 'slack-icon',
                'settings_url' => admin_url('edit.php?post_type=kh_event&page=kh-events-api&tab=integrations&integration=slack'),
                'class' => 'KH_Events_Slack_Integration',
                'active' => false
            ),
            'twilio' => array(
                'id' => 'twilio',
                'name' => __('Twilio SMS', 'kh-events'),
                'description' => __('Send SMS notifications for event updates', 'kh-events'),
                'icon' => 'twilio-icon',
                'settings_url' => admin_url('edit.php?post_type=kh_event&page=kh-events-api&tab=integrations&integration=twilio'),
                'class' => 'KH_Events_Twilio_Integration',
                'active' => false
            ),
            'stripe' => array(
                'id' => 'stripe',
                'name' => __('Stripe Payments', 'kh-events'),
                'description' => __('Process payments for paid events', 'kh-events'),
                'icon' => 'stripe-icon',
                'settings_url' => admin_url('edit.php?post_type=kh_event&page=kh-events-api&tab=integrations&integration=stripe'),
                'class' => 'KH_Events_Stripe_Integration',
                'active' => false
            ),
            'paypal' => array(
                'id' => 'paypal',
                'name' => __('PayPal Payments', 'kh-events'),
                'description' => __('Process payments via PayPal for paid events', 'kh-events'),
                'icon' => 'paypal-icon',
                'settings_url' => admin_url('edit.php?post_type=kh_event&page=kh-events-api&tab=integrations&integration=paypal'),
                'class' => 'KH_Events_PayPal_Integration',
                'active' => false
            ),
            'woocommerce' => array(
                'id' => 'woocommerce',
                'name' => __('WooCommerce', 'kh-events'),
                'description' => __('Sell event tickets through WooCommerce', 'kh-events'),
                'icon' => 'woocommerce-icon',
                'settings_url' => admin_url('edit.php?post_type=kh_event&page=kh-events-api&tab=integrations&integration=woocommerce'),
                'class' => 'KH_Events_WooCommerce_Integration',
                'active' => false
            ),
            'eventbrite' => array(
                'id' => 'eventbrite',
                'name' => __('Eventbrite', 'kh-events'),
                'description' => __('Import events from Eventbrite', 'kh-events'),
                'icon' => 'eventbrite-icon',
                'settings_url' => admin_url('edit.php?post_type=kh_event&page=kh-events-api&tab=integrations&integration=eventbrite'),
                'class' => 'KH_Events_Eventbrite_Integration',
                'active' => false
            ),
            'meetup' => array(
                'id' => 'meetup',
                'name' => __('Meetup.com', 'kh-events'),
                'description' => __('Import events from Meetup.com groups', 'kh-events'),
                'icon' => 'meetup-icon',
                'settings_url' => admin_url('edit.php?post_type=kh_event&page=kh-events-api&tab=integrations&integration=meetup'),
                'class' => 'KH_Events_Meetup_Integration',
                'active' => false
            )
        );

        // Allow other plugins to register integrations
        $this->integrations = apply_filters('kh_events_integrations', $this->integrations);
    }

    /**
     * Load active integrations
     */
    private function load_active_integrations() {
        $active_integrations = get_option('kh_events_active_integrations', array());

        foreach ($active_integrations as $integration_id) {
            if (isset($this->integrations[$integration_id])) {
                $this->load_integration($integration_id);
            }
        }
    }

    /**
     * Load single integration
     */
    private function load_integration($integration_id) {
        if (!isset($this->integrations[$integration_id])) {
            return false;
        }

        $integration = $this->integrations[$integration_id];
        $class_name = $integration['class'];

        // Check if integration class exists
        if (!class_exists($class_name)) {
            $file_path = KH_EVENTS_PATH . 'includes/integrations/class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';

            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                return false;
            }
        }

        // Initialize integration if class exists
        if (class_exists($class_name)) {
            try {
                $instance = new $class_name();
                $instance->init();

                $this->integrations[$integration_id]['instance'] = $instance;
                $this->integrations[$integration_id]['active'] = true;

                return true;
            } catch (Exception $e) {
                error_log('KH Events Integration Error (' . $integration_id . '): ' . $e->getMessage());
                return false;
            }
        }

        return false;
    }

    /**
     * Get all integrations
     */
    public function get_integrations() {
        return $this->integrations;
    }

    /**
     * Get single integration
     */
    public function get_integration($integration_id) {
        return isset($this->integrations[$integration_id]) ? $this->integrations[$integration_id] : null;
    }

    /**
     * Activate integration
     */
    public function activate_integration($integration_id) {
        if (!isset($this->integrations[$integration_id])) {
            return new WP_Error('integration_not_found', __('Integration not found', 'kh-events'));
        }

        $active_integrations = get_option('kh_events_active_integrations', array());

        if (!in_array($integration_id, $active_integrations)) {
            $active_integrations[] = $integration_id;
            update_option('kh_events_active_integrations', $active_integrations);
        }

        $success = $this->load_integration($integration_id);

        if ($success) {
            do_action('kh_events_integration_activated', $integration_id);
            return true;
        } else {
            return new WP_Error('integration_load_failed', __('Failed to load integration', 'kh-events'));
        }
    }

    /**
     * Deactivate integration
     */
    public function deactivate_integration($integration_id) {
        $active_integrations = get_option('kh_events_active_integrations', array());

        $key = array_search($integration_id, $active_integrations);
        if ($key !== false) {
            unset($active_integrations[$key]);
            update_option('kh_events_active_integrations', array_values($active_integrations));
        }

        if (isset($this->integrations[$integration_id]['instance'])) {
            $instance = $this->integrations[$integration_id]['instance'];
            if (method_exists($instance, 'deactivate')) {
                $instance->deactivate();
            }
            unset($this->integrations[$integration_id]['instance']);
        }

        $this->integrations[$integration_id]['active'] = false;

        do_action('kh_events_integration_deactivated', $integration_id);

        return true;
    }

    /**
     * Get integration instance
     */
    public function get_integration_instance($integration_id) {
        if (isset($this->integrations[$integration_id]['instance'])) {
            return $this->integrations[$integration_id]['instance'];
        }

        return null;
    }

    /**
     * Get integration settings
     */
    public function get_integration_settings($integration_id) {
        $settings = get_option('kh_events_integration_' . $integration_id, array());
        return $settings;
    }

    /**
     * Update integration settings
     */
    public function update_integration_settings($integration_id, $settings) {
        update_option('kh_events_integration_' . $integration_id, $settings);

        // Notify integration instance of settings change
        $instance = $this->get_integration_instance($integration_id);
        if ($instance && method_exists($instance, 'settings_updated')) {
            $instance->settings_updated($settings);
        }

        return true;
    }

    /**
     * Test integration connection
     */
    public function test_integration($integration_id) {
        $instance = $this->get_integration_instance($integration_id);

        if (!$instance) {
            return array(
                'success' => false,
                'message' => __('Integration not active', 'kh-events')
            );
        }

        if (!method_exists($instance, 'test_connection')) {
            return array(
                'success' => false,
                'message' => __('Integration does not support connection testing', 'kh-events')
            );
        }

        try {
            $result = $instance->test_connection();
            return $result;
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Sync data with integration
     */
    public function sync_integration($integration_id, $action = 'sync', $data = array()) {
        $instance = $this->get_integration_instance($integration_id);

        if (!$instance) {
            return new WP_Error('integration_not_active', __('Integration not active', 'kh-events'));
        }

        if (!method_exists($instance, 'sync')) {
            return new WP_Error('sync_not_supported', __('Integration does not support syncing', 'kh-events'));
        }

        try {
            $result = $instance->sync($action, $data);
            return $result;
        } catch (Exception $e) {
            return new WP_Error('sync_failed', $e->getMessage());
        }
    }

    /**
     * Handle integration webhook
     */
    public function handle_integration_webhook($integration_id, $request) {
        $instance = $this->get_integration_instance($integration_id);

        if (!$instance) {
            return new WP_Error('integration_not_active', __('Integration not active', 'kh-events'));
        }

        if (!method_exists($instance, 'handle_webhook')) {
            return new WP_Error('webhook_not_supported', __('Integration does not support webhooks', 'kh-events'));
        }

        try {
            $result = $instance->handle_webhook($request);
            return $result;
        } catch (Exception $e) {
            return new WP_Error('webhook_failed', $e->getMessage());
        }
    }

    /**
     * Get integration stats
     */
    public function get_integration_stats($integration_id) {
        $instance = $this->get_integration_instance($integration_id);

        if (!$instance) {
            return null;
        }

        if (!method_exists($instance, 'get_stats')) {
            return null;
        }

        try {
            return $instance->get_stats();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get available integration categories
     */
    public function get_integration_categories() {
        return array(
            'marketing' => __('Marketing & Email', 'kh-events'),
            'calendar' => __('Calendar Sync', 'kh-events'),
            'communication' => __('Communication', 'kh-events'),
            'payment' => __('Payments', 'kh-events'),
            'ecommerce' => __('E-commerce', 'kh-events'),
            'import' => __('Event Import', 'kh-events'),
            'automation' => __('Automation', 'kh-events')
        );
    }

    /**
     * Get integrations by category
     */
    public function get_integrations_by_category() {
        $categories = $this->get_integration_categories();
        $categorized = array();

        foreach ($categories as $category_id => $category_name) {
            $categorized[$category_id] = array(
                'name' => $category_name,
                'integrations' => array()
            );
        }

        // Define integration categories (this could be moved to integration definitions)
        $integration_categories = array(
            'mailchimp' => 'marketing',
            'zapier' => 'automation',
            'google_calendar' => 'calendar',
            'outlook_calendar' => 'calendar',
            'slack' => 'communication',
            'twilio' => 'communication',
            'stripe' => 'payment',
            'paypal' => 'payment',
            'woocommerce' => 'ecommerce',
            'eventbrite' => 'import',
            'meetup' => 'import'
        );

        foreach ($this->integrations as $integration_id => $integration) {
            $category = isset($integration_categories[$integration_id]) ? $integration_categories[$integration_id] : 'automation';

            if (isset($categorized[$category])) {
                $categorized[$category]['integrations'][] = $integration;
            }
        }

        return $categorized;
    }
}