<?php
/**
 * KH Events Webhook Manager
 *
 * Handles webhook registration, delivery, and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Webhook_Manager {

    /**
     * Webhook storage option key
     */
    const WEBHOOKS_OPTION = 'kh_events_webhooks';

    /**
     * Webhook log storage option key
     */
    const WEBHOOK_LOG_OPTION = 'kh_events_webhook_logs';

    /**
     * Initialize webhook system
     */
    public function init() {
        // Hook into event actions to trigger webhooks
        add_action('kh_events_event_created', array($this, 'trigger_event_created'), 10, 2);
        add_action('kh_events_event_updated', array($this, 'trigger_event_updated'), 10, 2);
        add_action('kh_events_event_deleted', array($this, 'trigger_event_deleted'), 10, 1);
        add_action('kh_events_booking_created', array($this, 'trigger_booking_created'), 10, 2);
        add_action('kh_events_booking_status_changed', array($this, 'trigger_booking_status_changed'), 10, 3);

        // Clean up old logs periodically
        add_action('kh_events_daily_cleanup', array($this, 'cleanup_old_logs'));
    }

    /**
     * Get all webhooks
     */
    public function get_webhooks() {
        $webhooks = get_option(self::WEBHOOKS_OPTION, array());

        // Ensure each webhook has required fields
        foreach ($webhooks as $id => &$webhook) {
            $webhook = wp_parse_args($webhook, array(
                'id' => $id,
                'name' => '',
                'url' => '',
                'events' => array(),
                'secret' => '',
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'last_triggered' => null,
                'failure_count' => 0
            ));
        }

        return $webhooks;
    }

    /**
     * Get single webhook
     */
    public function get_webhook($webhook_id) {
        $webhooks = $this->get_webhooks();
        return isset($webhooks[$webhook_id]) ? $webhooks[$webhook_id] : null;
    }

    /**
     * Create webhook
     */
    public function create_webhook($data) {
        $webhooks = $this->get_webhooks();

        // Generate unique ID
        $webhook_id = 'webhook_' . wp_generate_password(12, false);

        $webhook = wp_parse_args($data, array(
            'id' => $webhook_id,
            'name' => '',
            'url' => '',
            'events' => array(),
            'secret' => wp_generate_password(32, false),
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'last_triggered' => null,
            'failure_count' => 0
        ));

        // Validate required fields
        if (empty($webhook['name']) || empty($webhook['url'])) {
            return new WP_Error('missing_required_fields', __('Name and URL are required', 'kh-events'));
        }

        // Validate URL
        if (!filter_var($webhook['url'], FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Invalid webhook URL', 'kh-events'));
        }

        $webhooks[$webhook_id] = $webhook;
        update_option(self::WEBHOOKS_OPTION, $webhooks);

        return $webhook_id;
    }

    /**
     * Update webhook
     */
    public function update_webhook($webhook_id, $data) {
        $webhooks = $this->get_webhooks();

        if (!isset($webhooks[$webhook_id])) {
            return new WP_Error('webhook_not_found', __('Webhook not found', 'kh-events'));
        }

        $webhook = wp_parse_args($data, $webhooks[$webhook_id]);

        // Validate URL if provided
        if (!empty($data['url']) && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Invalid webhook URL', 'kh-events'));
        }

        $webhooks[$webhook_id] = $webhook;
        update_option(self::WEBHOOKS_OPTION, $webhooks);

        return true;
    }

    /**
     * Delete webhook
     */
    public function delete_webhook($webhook_id) {
        $webhooks = $this->get_webhooks();

        if (!isset($webhooks[$webhook_id])) {
            return new WP_Error('webhook_not_found', __('Webhook not found', 'kh-events'));
        }

        unset($webhooks[$webhook_id]);
        update_option(self::WEBHOOKS_OPTION, $webhooks);

        return true;
    }

    /**
     * Test webhook
     */
    public function test_webhook($webhook_id) {
        $webhook = $this->get_webhook($webhook_id);

        if (!$webhook) {
            return array(
                'success' => false,
                'message' => __('Webhook not found', 'kh-events')
            );
        }

        $test_payload = array(
            'event' => 'webhook.test',
            'timestamp' => current_time('timestamp'),
            'data' => array(
                'message' => 'This is a test webhook from KH Events',
                'webhook_id' => $webhook_id,
                'webhook_name' => $webhook['name']
            )
        );

        $result = $this->deliver_webhook($webhook, $test_payload);

        if ($result['success']) {
            return array(
                'success' => true,
                'message' => __('Webhook test successful', 'kh-events'),
                'response_code' => $result['response_code']
            );
        } else {
            return array(
                'success' => false,
                'message' => $result['error'],
                'response_code' => $result['response_code']
            );
        }
    }

    /**
     * Trigger webhooks for event created
     */
    public function trigger_event_created($event_id, $event_data) {
        $this->trigger_webhooks('event.created', array(
            'event_id' => $event_id,
            'event' => $event_data
        ));
    }

    /**
     * Trigger webhooks for event updated
     */
    public function trigger_event_updated($event_id, $event_data) {
        $this->trigger_webhooks('event.updated', array(
            'event_id' => $event_id,
            'event' => $event_data
        ));
    }

    /**
     * Trigger webhooks for event deleted
     */
    public function trigger_event_deleted($event_id) {
        $this->trigger_webhooks('event.deleted', array(
            'event_id' => $event_id
        ));
    }

    /**
     * Trigger webhooks for booking created
     */
    public function trigger_booking_created($booking_id, $booking_data) {
        $this->trigger_webhooks('booking.created', array(
            'booking_id' => $booking_id,
            'booking' => $booking_data
        ));
    }

    /**
     * Trigger webhooks for booking status changed
     */
    public function trigger_booking_status_changed($booking_id, $old_status, $new_status) {
        $this->trigger_webhooks('booking.status_changed', array(
            'booking_id' => $booking_id,
            'old_status' => $old_status,
            'new_status' => $new_status
        ));
    }

    /**
     * Trigger webhooks for specific event
     */
    private function trigger_webhooks($event_type, $data) {
        $webhooks = $this->get_webhooks();

        foreach ($webhooks as $webhook) {
            if ($webhook['status'] !== 'active') {
                continue;
            }

            if (!in_array($event_type, $webhook['events'])) {
                continue;
            }

            $payload = array(
                'event' => $event_type,
                'timestamp' => current_time('timestamp'),
                'data' => $data
            );

            $result = $this->deliver_webhook($webhook, $payload);

            // Update webhook status based on delivery result
            if (!$result['success']) {
                $webhook['failure_count']++;
                if ($webhook['failure_count'] >= 5) {
                    $webhook['status'] = 'disabled';
                }
            } else {
                $webhook['failure_count'] = 0;
                $webhook['last_triggered'] = current_time('mysql');
            }

            $this->update_webhook($webhook['id'], $webhook);

            // Log the webhook delivery
            $this->log_webhook_delivery($webhook['id'], $event_type, $result);
        }
    }

    /**
     * Deliver webhook to endpoint
     */
    private function deliver_webhook($webhook, $payload) {
        $url = $webhook['url'];
        $secret = $webhook['secret'];

        // Generate signature
        $signature = $this->generate_signature($payload, $secret);

        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-KH-Events-Signature' => $signature,
                'X-KH-Events-Webhook-ID' => $webhook['id'],
                'User-Agent' => 'KH Events Webhook/' . KH_EVENTS_VERSION
            ),
            'body' => wp_json_encode($payload),
            'sslverify' => true
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
                'response_code' => 0
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Consider 2xx status codes as success
        $success = ($response_code >= 200 && $response_code < 300);

        return array(
            'success' => $success,
            'response_code' => $response_code,
            'response_body' => $response_body,
            'error' => $success ? null : $response_body
        );
    }

    /**
     * Generate webhook signature
     */
    private function generate_signature($payload, $secret) {
        $payload_json = wp_json_encode($payload);
        return 'sha256=' . hash_hmac('sha256', $payload_json, $secret);
    }

    /**
     * Verify webhook signature
     */
    public function verify_signature($payload, $signature, $secret) {
        $expected_signature = $this->generate_signature($payload, $secret);
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Process incoming webhook
     */
    public function process_webhook($webhook_id, $request) {
        $webhook = $this->get_webhook($webhook_id);

        if (!$webhook) {
            return new WP_Error('webhook_not_found', __('Webhook not found', 'kh-events'), array('status' => 404));
        }

        if ($webhook['status'] !== 'active') {
            return new WP_Error('webhook_inactive', __('Webhook is not active', 'kh-events'), array('status' => 403));
        }

        // Get raw payload
        $payload = $request->get_json_params();

        // Verify signature if secret is set
        if (!empty($webhook['secret'])) {
            $signature = $request->get_header('x_kh_events_signature');

            if (empty($signature) || !$this->verify_signature($payload, $signature, $webhook['secret'])) {
                return new WP_Error('invalid_signature', __('Invalid webhook signature', 'kh-events'), array('status' => 401));
            }
        }

        // Process the webhook payload
        $result = $this->handle_webhook_payload($webhook, $payload);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(array(
            'status' => 'processed',
            'webhook_id' => $webhook_id
        ), 200);
    }

    /**
     * Handle webhook payload
     */
    private function handle_webhook_payload($webhook, $payload) {
        // This is where you would implement custom webhook processing logic
        // For now, just log the payload

        $this->log_webhook_delivery($webhook['id'], $payload['event'] ?? 'unknown', array(
            'success' => true,
            'payload' => $payload
        ));

        // Trigger action for custom processing
        do_action('kh_events_webhook_received', $webhook, $payload);

        return true;
    }

    /**
     * Log webhook delivery
     */
    private function log_webhook_delivery($webhook_id, $event_type, $result) {
        $logs = get_option(self::WEBHOOK_LOG_OPTION, array());

        $log_entry = array(
            'webhook_id' => $webhook_id,
            'event_type' => $event_type,
            'timestamp' => current_time('mysql'),
            'success' => $result['success'],
            'response_code' => $result['response_code'] ?? null,
            'error' => $result['error'] ?? null,
            'payload' => isset($result['payload']) ? wp_json_encode($result['payload']) : null
        );

        $logs[] = $log_entry;

        // Keep only last 1000 logs
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }

        update_option(self::WEBHOOK_LOG_OPTION, $logs);
    }

    /**
     * Get webhook logs
     */
    public function get_webhook_logs($webhook_id = null, $limit = 50) {
        $logs = get_option(self::WEBHOOK_LOG_OPTION, array());

        // Filter by webhook ID if specified
        if ($webhook_id) {
            $logs = array_filter($logs, function($log) use ($webhook_id) {
                return $log['webhook_id'] === $webhook_id;
            });
        }

        // Sort by timestamp descending
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($logs, 0, $limit);
    }

    /**
     * Clean up old logs
     */
    public function cleanup_old_logs() {
        $logs = get_option(self::WEBHOOK_LOG_OPTION, array());
        $cutoff_date = strtotime('-30 days');

        $logs = array_filter($logs, function($log) use ($cutoff_date) {
            return strtotime($log['timestamp']) > $cutoff_date;
        });

        update_option(self::WEBHOOK_LOG_OPTION, array_values($logs));
    }

    /**
     * Get available webhook events
     */
    public function get_available_events() {
        return array(
            'event.created' => __('Event Created', 'kh-events'),
            'event.updated' => __('Event Updated', 'kh-events'),
            'event.deleted' => __('Event Deleted', 'kh-events'),
            'booking.created' => __('Booking Created', 'kh-events'),
            'booking.status_changed' => __('Booking Status Changed', 'kh-events')
        );
    }
}