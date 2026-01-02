<?php
/**
 * KH Events GDPR Compliance
 *
 * Handles GDPR compliance features including data export, erasure, and consent management
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Event_GDPR {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Register data exporters and erasers
        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_exporters'), 10);
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_erasers'), 10);

        // Add consent checkboxes to forms
        add_action('kh_events_booking_form_before_submit', array($this, 'add_consent_checkboxes'));
        add_action('kh_events_submit_form_before_submit', array($this, 'add_consent_checkboxes'));

        // Handle consent storage
        add_action('kh_events_booking_created', array($this, 'store_consent_data'), 10, 2);
        add_action('kh_events_event_submitted', array($this, 'store_consent_data'), 10, 2);

        // Add privacy policy content
        add_action('admin_init', array($this, 'add_privacy_policy_content'));
    }

    /**
     * Register data exporters for WordPress Privacy Tools
     */
    public function register_exporters($exporters) {
        $exporters['kh-events-bookings'] = array(
            'exporter_friendly_name' => __('KH Events Bookings', 'kh-events'),
            'callback' => array($this, 'export_bookings_data'),
        );

        $exporters['kh-events-events'] = array(
            'exporter_friendly_name' => __('KH Events Submitted Events', 'kh-events'),
            'callback' => array($this, 'export_events_data'),
        );

        return $exporters;
    }

    /**
     * Register data erasers for WordPress Privacy Tools
     */
    public function register_erasers($erasers) {
        $erasers['kh-events-bookings'] = array(
            'eraser_friendly_name' => __('KH Events Bookings', 'kh-events'),
            'callback' => array($this, 'erase_bookings_data'),
        );

        $erasers['kh-events-events'] = array(
            'eraser_friendly_name' => __('KH Events Submitted Events', 'kh-events'),
            'callback' => array($this, 'erase_events_data'),
        );

        return $erasers;
    }

    /**
     * Export bookings data for a user
     */
    public function export_bookings_data($email_address, $page = 1) {
        $user = get_user_by('email', $email_address);
        if (!$user) {
            return array(
                'data' => array(),
                'done' => true,
            );
        }

        $bookings = get_posts(array(
            'post_type' => 'kh_booking',
            'author' => $user->ID,
            'posts_per_page' => 100,
            'paged' => $page,
        ));

        $export_data = array();

        foreach ($bookings as $booking) {
            $event_id = get_post_meta($booking->ID, '_kh_booking_event_id', true);
            $event = get_post($event_id);

            $booking_data = array(
                array(
                    'name' => __('Booking ID', 'kh-events'),
                    'value' => $booking->ID,
                ),
                array(
                    'name' => __('Event Name', 'kh-events'),
                    'value' => $event ? $event->post_title : __('Event not found', 'kh-events'),
                ),
                array(
                    'name' => __('Booking Date', 'kh-events'),
                    'value' => $booking->post_date,
                ),
                array(
                    'name' => __('Booking Status', 'kh-events'),
                    'value' => get_post_meta($booking->ID, '_kh_booking_status', true),
                ),
                array(
                    'name' => __('Tickets', 'kh-events'),
                    'value' => get_post_meta($booking->ID, '_kh_booking_tickets', true),
                ),
                array(
                    'name' => __('Total Amount', 'kh-events'),
                    'value' => get_post_meta($booking->ID, '_kh_booking_total', true),
                ),
                array(
                    'name' => __('Payment Method', 'kh-events'),
                    'value' => get_post_meta($booking->ID, '_kh_booking_payment_method', true),
                ),
            );

            // Add consent data if available
            $consent_data = get_post_meta($booking->ID, '_kh_consent_data', true);
            if ($consent_data) {
                $booking_data[] = array(
                    'name' => __('Consent Given', 'kh-events'),
                    'value' => date('Y-m-d H:i:s', $consent_data['timestamp']),
                );
                $booking_data[] = array(
                    'name' => __('Consent IP', 'kh-events'),
                    'value' => $consent_data['ip_address'],
                );
            }

            $export_data[] = array(
                'group_id' => 'kh-events-bookings',
                'group_label' => __('KH Events Bookings', 'kh-events'),
                'item_id' => 'booking-' . $booking->ID,
                'data' => $booking_data,
            );
        }

        return array(
            'data' => $export_data,
            'done' => count($bookings) < 100,
        );
    }

    /**
     * Export events data for a user
     */
    public function export_events_data($email_address, $page = 1) {
        $user = get_user_by('email', $email_address);
        if (!$user) {
            return array(
                'data' => array(),
                'done' => true,
            );
        }

        $events = get_posts(array(
            'post_type' => 'kh_event',
            'author' => $user->ID,
            'posts_per_page' => 100,
            'paged' => $page,
        ));

        $export_data = array();

        foreach ($events as $event) {
            $event_data = array(
                array(
                    'name' => __('Event ID', 'kh-events'),
                    'value' => $event->ID,
                ),
                array(
                    'name' => __('Event Title', 'kh-events'),
                    'value' => $event->post_title,
                ),
                array(
                    'name' => __('Event Description', 'kh-events'),
                    'value' => $event->post_content,
                ),
                array(
                    'name' => __('Start Date', 'kh-events'),
                    'value' => get_post_meta($event->ID, '_kh_event_start_date', true),
                ),
                array(
                    'name' => __('Start Time', 'kh-events'),
                    'value' => get_post_meta($event->ID, '_kh_event_start_time', true),
                ),
                array(
                    'name' => __('Location', 'kh-events'),
                    'value' => $this->get_event_location($event->ID),
                ),
                array(
                    'name' => __('Categories', 'kh-events'),
                    'value' => $this->get_event_terms($event->ID, 'kh_event_category'),
                ),
                array(
                    'name' => __('Tags', 'kh-events'),
                    'value' => $this->get_event_terms($event->ID, 'kh_event_tag'),
                ),
            );

            // Add consent data if available
            $consent_data = get_post_meta($event->ID, '_kh_consent_data', true);
            if ($consent_data) {
                $event_data[] = array(
                    'name' => __('Consent Given', 'kh-events'),
                    'value' => date('Y-m-d H:i:s', $consent_data['timestamp']),
                );
                $event_data[] = array(
                    'name' => __('Consent IP', 'kh-events'),
                    'value' => $consent_data['ip_address'],
                );
            }

            $export_data[] = array(
                'group_id' => 'kh-events-events',
                'group_label' => __('KH Events Submitted Events', 'kh-events'),
                'item_id' => 'event-' . $event->ID,
                'data' => $event_data,
            );
        }

        return array(
            'data' => $export_data,
            'done' => count($events) < 100,
        );
    }

    /**
     * Erase bookings data for a user
     */
    public function erase_bookings_data($email_address, $page = 1) {
        $user = get_user_by('email', $email_address);
        if (!$user) {
            return array(
                'items_removed' => false,
                'items_retained' => false,
                'messages' => array(),
                'done' => true,
            );
        }

        $bookings = get_posts(array(
            'post_type' => 'kh_booking',
            'author' => $user->ID,
            'posts_per_page' => 100,
            'paged' => $page,
        ));

        $items_removed = false;
        $items_retained = false;
        $messages = array();

        foreach ($bookings as $booking) {
            $booking_status = get_post_meta($booking->ID, '_kh_booking_status', true);

            // Only erase completed/cancelled bookings, retain active ones
            if (in_array($booking_status, array('completed', 'cancelled', 'refunded'))) {
                // Anonymize the booking data instead of deleting
                $this->anonymize_booking($booking->ID);
                $items_removed = true;
                $messages[] = sprintf(__('Booking #%d has been anonymized.', 'kh-events'), $booking->ID);
            } else {
                $items_retained = true;
                $messages[] = sprintf(__('Booking #%d retained due to active status.', 'kh-events'), $booking->ID);
            }
        }

        return array(
            'items_removed' => $items_removed,
            'items_retained' => $items_retained,
            'messages' => $messages,
            'done' => count($bookings) < 100,
        );
    }

    /**
     * Erase events data for a user
     */
    public function erase_events_data($email_address, $page = 1) {
        $user = get_user_by('email', $email_address);
        if (!$user) {
            return array(
                'items_removed' => false,
                'items_retained' => false,
                'messages' => array(),
                'done' => true,
            );
        }

        $events = get_posts(array(
            'post_type' => 'kh_event',
            'author' => $user->ID,
            'posts_per_page' => 100,
            'paged' => $page,
        ));

        $items_removed = false;
        $items_retained = false;
        $messages = array();

        foreach ($events as $event) {
            $event_status = get_post_meta($event->ID, '_kh_event_status', true);

            // Only erase cancelled/rejected events, retain published ones
            if (in_array($event_status, array('cancelled', 'rejected'))) {
                // Delete the event completely
                wp_delete_post($event->ID, true);
                $items_removed = true;
                $messages[] = sprintf(__('Event "%s" has been deleted.', 'kh-events'), $event->post_title);
            } else {
                // Anonymize published events
                $this->anonymize_event($event->ID);
                $items_retained = true;
                $messages[] = sprintf(__('Event "%s" has been anonymized.', 'kh-events'), $event->post_title);
            }
        }

        return array(
            'items_removed' => $items_removed,
            'items_retained' => $items_retained,
            'messages' => $messages,
            'done' => count($events) < 100,
        );
    }

    /**
     * Add consent checkboxes to forms
     */
    public function add_consent_checkboxes() {
        $consent_settings = get_option('kh_events_gdpr_settings', array());
        $require_consent = $consent_settings['require_consent'] ?? 'yes';

        if ($require_consent !== 'yes') {
            return;
        }

        $privacy_policy_url = get_privacy_policy_url();
        $consent_text = $consent_settings['consent_text'] ?? __('I agree to the processing of my personal data according to the %s.', 'kh-events');

        if ($privacy_policy_url) {
            $consent_text = sprintf($consent_text, '<a href="' . esc_url($privacy_policy_url) . '" target="_blank">' . __('Privacy Policy', 'kh-events') . '</a>');
        } else {
            $consent_text = sprintf($consent_text, __('Privacy Policy', 'kh-events'));
        }

        ?>
        <div class="kh-consent-section">
            <label class="kh-consent-checkbox">
                <input type="checkbox" name="kh_consent" value="1" required>
                <span class="kh-consent-text"><?php echo wp_kses_post($consent_text); ?></span>
            </label>
            <?php if (!empty($consent_settings['additional_consent_text'])): ?>
                <div class="kh-additional-consent">
                    <?php echo wp_kses_post($consent_settings['additional_consent_text']); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Store consent data when forms are submitted
     */
    public function store_consent_data($post_id, $data) {
        if (!isset($_POST['kh_consent']) || $_POST['kh_consent'] !== '1') {
            return;
        }

        $consent_data = array(
            'timestamp' => current_time('timestamp'),
            'ip_address' => $this->get_user_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'consent_given' => true,
        );

        update_post_meta($post_id, '_kh_consent_data', $consent_data);
    }

    /**
     * Add privacy policy content suggestion
     */
    public function add_privacy_policy_content() {
        if (function_exists('wp_add_privacy_policy_content')) {
            $content = $this->get_privacy_policy_content();
            wp_add_privacy_policy_content(__('KH Events', 'kh-events'), $content);
        }
    }

    /**
     * Get suggested privacy policy content
     */
    private function get_privacy_policy_content() {
        return '
            <h3>' . __('KH Events', 'kh-events') . '</h3>
            <p>' . __('When you book events or submit events through our website, we collect personal information such as your name, email address, and payment information (if applicable).', 'kh-events') . '</p>
            <p>' . __('We use this information to:', 'kh-events') . '</p>
            <ul>
                <li>' . __('Process your bookings and payments', 'kh-events') . '</li>
                <li>' . __('Send you booking confirmations and event updates', 'kh-events') . '</li>
                <li>' . __('Communicate with you about your submitted events', 'kh-events') . '</li>
                <li>' . __('Improve our services and comply with legal obligations', 'kh-events') . '</li>
            </ul>
            <p>' . __('Your data is stored securely and is only accessible to authorized personnel. You can request to view, export, or delete your personal data at any time using the privacy tools available in your account dashboard.', 'kh-events') . '</p>
            <p>' . __('We retain your booking data for 7 years for accounting and legal purposes, after which it is securely deleted or anonymized.', 'kh-events') . '</p>
        ';
    }

    /**
     * Anonymize booking data
     */
    private function anonymize_booking($booking_id) {
        // Remove personal identifiable information
        update_post_meta($booking_id, '_kh_booking_customer_name', __('Anonymized', 'kh-events'));
        update_post_meta($booking_id, '_kh_booking_customer_email', 'anonymized@example.com');
        update_post_meta($booking_id, '_kh_booking_customer_phone', '');

        // Keep essential data for accounting but remove PII
        // Payment data is already tokenized by payment gateways
    }

    /**
     * Anonymize event data
     */
    private function anonymize_event($event_id) {
        // Update post author to anonymous user if possible
        // Remove or anonymize contact information in event meta
        update_post_meta($event_id, '_kh_event_contact_name', __('Anonymized', 'kh-events'));
        update_post_meta($event_id, '_kh_event_contact_email', 'anonymized@example.com');
        update_post_meta($event_id, '_kh_event_contact_phone', '');
    }

    /**
     * Get user IP address
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '';
        }
    }

    /**
     * Get event location as string
     */
    private function get_event_location($event_id) {
        $location_id = get_post_meta($event_id, '_kh_event_location', true);
        if (!$location_id) {
            return '';
        }

        $location = get_post($location_id);
        return $location ? $location->post_title : '';
    }

    /**
     * Get event terms as comma-separated string
     */
    private function get_event_terms($event_id, $taxonomy) {
        $terms = wp_get_post_terms($event_id, $taxonomy, array('fields' => 'names'));
        return implode(', ', $terms);
    }
}