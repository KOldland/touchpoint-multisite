<?php
/**
 * KH Events Email Marketing Integration
 *
 * Integrates with email marketing services like Mailchimp
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Email_Marketing {

    private static $instance = null;
    private $providers = array();

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_providers();
        $this->init_hooks();
    }

    private function load_providers() {
        // Load available email marketing providers
        $this->providers['mailchimp'] = new KH_Email_Mailchimp_Provider();
        // Future: Add more providers like ConvertKit, Klaviyo, etc.
    }

    private function init_hooks() {
        // Hook into booking completion
        add_action('kh_event_booking_completed', array($this, 'handle_booking_completion'), 10, 2);

        // Hook into event creation for automated sequences
        add_action('kh_event_created', array($this, 'setup_event_sequences'), 10, 1);

        // Admin settings
        add_filter('kh_events_settings_tabs', array($this, 'add_settings_tab'));
        add_action('kh_events_settings_tab_email', array($this, 'render_settings_tab'));
        add_action('kh_events_save_settings', array($this, 'save_settings'));
    }

    public function handle_booking_completion($booking_id, $booking_data) {
        $settings = get_option('kh_events_email_marketing_settings', array());

        if (empty($settings['enabled'])) {
            return;
        }

        $provider = $settings['provider'] ?? 'mailchimp';
        if (!isset($this->providers[$provider])) {
            return;
        }

        // Get attendee information
        $attendee_email = $booking_data['email'] ?? '';
        $attendee_name = $booking_data['name'] ?? '';
        $event_id = $booking_data['event_id'] ?? 0;

        if (empty($attendee_email) || !$event_id) {
            return;
        }

        // Get event details
        $event = get_post($event_id);
        if (!$event) {
            return;
        }

        // Prepare subscriber data
        $subscriber_data = array(
            'email' => $attendee_email,
            'name' => $attendee_name,
            'event_id' => $event_id,
            'event_title' => $event->post_title,
            'booking_date' => current_time('mysql'),
            'tags' => array('event-attendee', 'event-' . $event_id)
        );

        // Add to email list
        $this->providers[$provider]->add_subscriber($subscriber_data);

        // Trigger welcome sequence
        $this->trigger_welcome_sequence($provider, $subscriber_data);
    }

    public function setup_event_sequences($event_id) {
        $settings = get_option('kh_events_email_marketing_settings', array());

        if (empty($settings['auto_sequences'])) {
            return;
        }

        $provider = $settings['provider'] ?? 'mailchimp';
        if (!isset($this->providers[$provider])) {
            return;
        }

        // Create automated email sequences for the event
        $event = get_post($event_id);
        if (!$event) {
            return;
        }

        $event_date = get_post_meta($event_id, '_event_start_date', true);
        if (!$event_date) {
            return;
        }

        // Setup reminder sequence (7 days before, 1 day before)
        $this->create_reminder_sequence($provider, $event_id, $event_date);
    }

    private function create_reminder_sequence($provider, $event_id, $event_date) {
        $event_timestamp = strtotime($event_date);
        $now = current_time('timestamp');

        // 7-day reminder
        $reminder_7d = $event_timestamp - (7 * 24 * 60 * 60);
        if ($reminder_7d > $now) {
            $this->schedule_sequence_email($provider, $event_id, $reminder_7d, 'reminder_7d');
        }

        // 1-day reminder
        $reminder_1d = $event_timestamp - (24 * 60 * 60);
        if ($reminder_1d > $now) {
            $this->schedule_sequence_email($provider, $event_id, $reminder_1d, 'reminder_1d');
        }

        // Follow-up (1 day after)
        $followup = $event_timestamp + (24 * 60 * 60);
        $this->schedule_sequence_email($provider, $event_id, $followup, 'followup');
    }

    private function schedule_sequence_email($provider, $event_id, $timestamp, $sequence_type) {
        // Schedule the email using WordPress cron or provider's automation
        wp_schedule_single_event($timestamp, 'kh_email_sequence_' . $sequence_type, array($event_id, $provider));
    }

    private function trigger_welcome_sequence($provider, $subscriber_data) {
        // Trigger immediate welcome email
        $this->providers[$provider]->send_welcome_email($subscriber_data);
    }

    public function add_settings_tab($tabs) {
        $tabs['email'] = __('Email Marketing', 'kh-events');
        return $tabs;
    }

    public function render_settings_tab() {
        $settings = get_option('kh_events_email_marketing_settings', array());
        ?>
        <div class="kh-settings-section">
            <h3><?php _e('Email Marketing Integration', 'kh-events'); ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Email Marketing', 'kh-events'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="kh_events_email_marketing[enabled]"
                                   value="1" <?php checked($settings['enabled'] ?? 0, 1); ?>>
                            <?php _e('Enable email marketing integration', 'kh-events'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Email Provider', 'kh-events'); ?></th>
                    <td>
                        <select name="kh_events_email_marketing[provider]">
                            <option value="mailchimp" <?php selected($settings['provider'] ?? 'mailchimp', 'mailchimp'); ?>>
                                Mailchimp
                            </option>
                            <!-- Future: Add more providers -->
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Mailchimp API Key', 'kh-events'); ?></th>
                    <td>
                        <input type="password" name="kh_events_email_marketing[mailchimp_api_key]"
                               value="<?php echo esc_attr($settings['mailchimp_api_key'] ?? ''); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php _e('Enter your Mailchimp API key. Get it from your Mailchimp account settings.', 'kh-events'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Mailchimp Audience ID', 'kh-events'); ?></th>
                    <td>
                        <input type="text" name="kh_events_email_marketing[mailchimp_audience_id]"
                               value="<?php echo esc_attr($settings['mailchimp_audience_id'] ?? ''); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php _e('Enter your Mailchimp audience/list ID where event attendees will be added.', 'kh-events'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Auto Sequences', 'kh-events'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="kh_events_email_marketing[auto_sequences]"
                                   value="1" <?php checked($settings['auto_sequences'] ?? 0, 1); ?>>
                            <?php _e('Automatically create email sequences for new events', 'kh-events'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    public function save_settings($settings) {
        if (isset($_POST['kh_events_email_marketing'])) {
            $email_settings = $_POST['kh_events_email_marketing'];
            update_option('kh_events_email_marketing_settings', $email_settings);
        }
    }

    public function get_available_providers() {
        return array_keys($this->providers);
    }

    public function get_provider($provider_id) {
        return $this->providers[$provider_id] ?? null;
    }
}

// Base provider class
abstract class KH_Email_Provider_Base {
    abstract public function add_subscriber($subscriber_data);
    abstract public function send_welcome_email($subscriber_data);
    abstract public function create_sequence($event_id, $sequence_data);
}

// Mailchimp implementation
class KH_Email_Mailchimp_Provider extends KH_Email_Provider_Base {

    private $api_key;
    private $audience_id;
    private $api_url = 'https://api.mailchimp.com/3.0';

    public function __construct() {
        $settings = get_option('kh_events_email_marketing_settings', array());
        $this->api_key = $settings['mailchimp_api_key'] ?? '';
        $this->audience_id = $settings['mailchimp_audience_id'] ?? '';

        if ($this->api_key) {
            $this->api_url = str_replace('api.mailchimp.com', $this->get_datacenter() . '.api.mailchimp.com', $this->api_url);
        }
    }

    private function get_datacenter() {
        $parts = explode('-', $this->api_key);
        return end($parts);
    }

    public function add_subscriber($subscriber_data) {
        if (!$this->api_key || !$this->audience_id) {
            return false;
        }

        $url = $this->api_url . "/lists/{$this->audience_id}/members";

        $data = array(
            'email_address' => $subscriber_data['email'],
            'status' => 'subscribed',
            'merge_fields' => array(
                'FNAME' => $subscriber_data['name'],
                'EVENT_ID' => $subscriber_data['event_id'],
                'EVENT' => $subscriber_data['event_title']
            ),
            'tags' => $subscriber_data['tags']
        );

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $this->api_key),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data)
        ));

        if (is_wp_error($response)) {
            error_log('Mailchimp API Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['id']);
    }

    public function send_welcome_email($subscriber_data) {
        // For now, we'll use Mailchimp's automation features
        // In a full implementation, you might create specific welcome campaigns
        return true;
    }

    public function create_sequence($event_id, $sequence_data) {
        // Create automated email sequences in Mailchimp
        // This would require the Mailchimp Marketing API
        return true;
    }
}