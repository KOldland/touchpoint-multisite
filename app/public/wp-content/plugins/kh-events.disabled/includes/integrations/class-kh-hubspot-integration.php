<?php
/**
 * KH Events HubSpot CRM Integration
 *
 * Sync event attendees and data with HubSpot CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_HubSpot_Integration {

    private $api_key;
    private $settings;

    public function __construct() {
        $this->settings = get_option('kh_events_hubspot_settings', array());
        $this->api_key = $this->settings['api_key'] ?? '';
    }

    public function get_name() {
        return 'HubSpot CRM';
    }

    public function is_connected() {
        return !empty($this->api_key) && $this->test_connection();
    }

    public function get_last_sync() {
        return get_option('kh_events_hubspot_last_sync', null);
    }

    public function get_status() {
        if (!$this->api_key) {
            return 'not_configured';
        }

        return $this->is_connected() ? 'connected' : 'error';
    }

    public function get_settings() {
        return $this->settings;
    }

    public function update_settings($new_settings) {
        $this->settings = array_merge($this->settings, $new_settings);
        $this->api_key = $this->settings['api_key'] ?? '';
        update_option('kh_events_hubspot_settings', $this->settings);
        return true;
    }

    public function get_capabilities() {
        return array(
            'contact_sync' => true,
            'deal_creation' => true,
            'event_tracking' => true,
            'email_integration' => true,
            'automation_workflows' => true
        );
    }

    public function sync($event_id = null, $action = 'sync') {
        if (!$this->is_connected()) {
            throw new Exception('HubSpot not connected');
        }

        switch ($action) {
            case 'push_contacts':
                return $this->sync_contacts_to_hubspot($event_id);
            case 'pull_contacts':
                return $this->sync_contacts_from_hubspot();
            case 'create_deals':
                return $this->create_deals_for_event($event_id);
            default:
                return $this->full_sync();
        }
    }

    private function test_connection() {
        if (!$this->api_key) {
            return false;
        }

        $response = wp_remote_get('https://api.hubapi.com/contacts/v1/lists/all/contacts/all', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code === 200;
    }

    private function sync_contacts_to_hubspot($event_id = null) {
        global $wpdb;

        // Get bookings to sync
        $where_clause = "WHERE status = 'completed'";
        if ($event_id) {
            $where_clause .= $wpdb->prepare(" AND event_id = %d", $event_id);
        }

        $bookings = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}kh_booking_analytics {$where_clause}",
            ARRAY_A
        );

        $results = array('synced' => 0, 'errors' => 0);

        foreach ($bookings as $booking) {
            try {
                $this->create_or_update_contact($booking);
                $results['synced']++;
            } catch (Exception $e) {
                $results['errors']++;
                error_log('HubSpot sync error: ' . $e->getMessage());
            }
        }

        update_option('kh_events_hubspot_last_sync', current_time('mysql'));
        return $results;
    }

    private function create_or_update_contact($booking_data) {
        // Prepare contact data
        $contact_data = array(
            'properties' => array(
                array(
                    'property' => 'email',
                    'value' => $booking_data['email'] ?? ''
                ),
                array(
                    'property' => 'firstname',
                    'value' => $booking_data['first_name'] ?? ''
                ),
                array(
                    'property' => 'lastname',
                    'value' => $booking_data['last_name'] ?? ''
                ),
                array(
                    'property' => 'event_attendee',
                    'value' => 'true'
                ),
                array(
                    'property' => 'last_event_booked',
                    'value' => $booking_data['event_title'] ?? ''
                ),
                array(
                    'property' => 'total_event_spend',
                    'value' => $booking_data['total_amount'] ?? 0
                )
            )
        );

        // Check if contact exists
        $existing_contact = $this->find_contact_by_email($booking_data['email']);

        if ($existing_contact) {
            // Update existing contact
            $response = wp_remote_post(
                'https://api.hubapi.com/contacts/v1/contact/vid/' . $existing_contact['vid'] . '/profile',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $this->api_key,
                        'Content-Type' => 'application/json'
                    ),
                    'body' => json_encode($contact_data),
                    'method' => 'POST',
                    'timeout' => 30
                )
            );
        } else {
            // Create new contact
            $response = wp_remote_post('https://api.hubapi.com/contacts/v1/contact', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($contact_data),
                'timeout' => 30
            ));
        }

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200 && $code !== 201) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            throw new Exception('HubSpot API error: ' . ($body['message'] ?? 'Unknown error'));
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function find_contact_by_email($email) {
        $response = wp_remote_get(
            'https://api.hubapi.com/contacts/v1/contact/email/' . urlencode($email) . '/profile',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key
                ),
                'timeout' => 30
            )
        );

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 404) {
            return null; // Contact not found
        }

        if ($code !== 200) {
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function create_deals_for_event($event_id) {
        if (!$event_id) {
            throw new Exception('Event ID required for deal creation');
        }

        $event = get_post($event_id);
        if (!$event) {
            throw new Exception('Event not found');
        }

        global $wpdb;
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kh_booking_analytics WHERE event_id = %d AND status = 'completed'",
            $event_id
        ), ARRAY_A);

        $results = array('created' => 0, 'errors' => 0);

        foreach ($bookings as $booking) {
            try {
                $this->create_deal_for_booking($booking, $event);
                $results['created']++;
            } catch (Exception $e) {
                $results['errors']++;
                error_log('HubSpot deal creation error: ' . $e->getMessage());
            }
        }

        return $results;
    }

    private function create_deal_for_booking($booking, $event) {
        // Find the contact
        $contact = $this->find_contact_by_email($booking['email']);
        if (!$contact) {
            throw new Exception('Contact not found for deal creation');
        }

        $deal_data = array(
            'properties' => array(
                array(
                    'name' => 'dealname',
                    'value' => $event->post_title . ' - ' . $booking['email']
                ),
                array(
                    'name' => 'dealstage',
                    'value' => 'closedwon'
                ),
                array(
                    'name' => 'amount',
                    'value' => $booking['total_amount']
                ),
                array(
                    'name' => 'closedate',
                    'value' => strtotime($booking['booking_date']) * 1000 // HubSpot expects milliseconds
                ),
                array(
                    'name' => 'dealtype',
                    'value' => 'event_booking'
                )
            ),
            'associations' => array(
                'associatedVids' => array($contact['vid'])
            )
        );

        $response = wp_remote_post('https://api.hubapi.com/deals/v1/deal', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($deal_data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            throw new Exception('Deal creation failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200 && $code !== 201) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            throw new Exception('HubSpot deal API error: ' . ($body['message'] ?? 'Unknown error'));
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function full_sync() {
        // Perform a complete sync of all data
        $results = array(
            'contacts_synced' => $this->sync_contacts_to_hubspot(),
            'deals_created' => 0
        );

        // Create deals for recent events
        $recent_events = get_posts(array(
            'post_type' => 'kh_event',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        foreach ($recent_events as $event) {
            try {
                $deal_results = $this->create_deals_for_event($event->ID);
                $results['deals_created'] += $deal_results['created'];
            } catch (Exception $e) {
                error_log('HubSpot deal sync error for event ' . $event->ID . ': ' . $e->getMessage());
            }
        }

        return $results;
    }

    // Integration with KH Events
    public function handle_booking_completed($booking_id, $booking_data) {
        if (!$this->is_connected() || empty($this->settings['auto_sync'])) {
            return;
        }

        try {
            $this->create_or_update_contact($booking_data);
        } catch (Exception $e) {
            error_log('HubSpot auto-sync error: ' . $e->getMessage());
        }
    }

    public function handle_event_created($event_id) {
        if (!$this->is_connected() || empty($this->settings['auto_create_deals'])) {
            return;
        }

        // Schedule deal creation for after bookings start coming in
        wp_schedule_single_event(time() + 3600, 'kh_hubspot_create_deals', array($event_id));
    }
}

// Hook into KH Events - only register if WordPress functions are available
if (function_exists('add_action')) {
    add_action('kh_event_booking_completed', array(new KH_HubSpot_Integration(), 'handle_booking_completed'), 10, 2);
    add_action('kh_event_created', array(new KH_HubSpot_Integration(), 'handle_event_created'), 10, 1);

    // Scheduled action for deal creation
    add_action('kh_hubspot_create_deals', function($event_id) {
        $hubspot = new KH_HubSpot_Integration();
        if ($hubspot->is_connected()) {
            $hubspot->create_deals_for_event($event_id);
        }
    });
}