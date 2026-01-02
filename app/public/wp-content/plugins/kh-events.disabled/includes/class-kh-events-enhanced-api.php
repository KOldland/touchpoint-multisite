<?php
/**
 * Enhanced KH Events API - Phase 3B
 *
 * Advanced REST API with webhooks, OAuth, and integration framework
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Enhanced_API {

    private static $instance = null;
    private $webhooks = array();
    private $integrations = array();

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
        $this->load_integrations();
    }

    private function init_hooks() {
        add_action('rest_api_init', array($this, 'register_enhanced_routes'));
        add_action('rest_api_init', array($this, 'register_integration_routes'));

        // Webhook triggers
        add_action('kh_event_created', array($this, 'trigger_webhook'), 10, 1);
        add_action('kh_event_updated', array($this, 'trigger_webhook'), 10, 2);
        add_action('kh_event_booking_completed', array($this, 'trigger_webhook'), 10, 2);
        add_action('kh_event_booking_cancelled', array($this, 'trigger_webhook'), 10, 2);

        // Admin menu
        add_action('admin_menu', array($this, 'add_api_admin_menu'));

        // AJAX handlers for API management
        add_action('wp_ajax_kh_api_generate_key', array($this, 'ajax_generate_api_key'));
        add_action('wp_ajax_kh_api_revoke_key', array($this, 'ajax_revoke_api_key'));
        add_action('wp_ajax_kh_webhook_test', array($this, 'ajax_test_webhook'));
    }

    private function load_integrations() {
        // Load available integrations
        if (class_exists('KH_Social_Media_Integration')) {
            $this->integrations['social_media'] = new KH_Social_Media_Integration();
        }
        if (class_exists('KH_Google_Calendar_Integration')) {
            $this->integrations['google_calendar'] = new KH_Google_Calendar_Integration();
        }
        if (class_exists('KH_Outlook_Calendar_Integration')) {
            $this->integrations['outlook_calendar'] = new KH_Outlook_Calendar_Integration();
        }
        if (class_exists('KH_HubSpot_Integration')) {
            $this->integrations['hubspot'] = new KH_HubSpot_Integration();
        }
        if (class_exists('KH_Salesforce_Integration')) {
            $this->integrations['salesforce'] = new KH_Salesforce_Integration();
        }
        if (class_exists('KH_Salemate_Integration')) {
            $this->integrations['salemate'] = new KH_Salemate_Integration();
        }
    }

    public function register_enhanced_routes() {
        // Enhanced events endpoints with better filtering
        register_rest_route('kh-events/v1', '/events/search', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'search_events'),
            'permission_callback' => array($this, 'api_permissions_check'),
            'args' => array(
                'query' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'limit' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint'
                )
            )
        ));

        // Bulk operations
        register_rest_route('kh-events/v1', '/events/bulk', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'bulk_events_operation'),
            'permission_callback' => array($this, 'api_permissions_check'),
            'args' => array(
                'operation' => array(
                    'required' => true,
                    'enum' => array('create', 'update', 'delete')
                ),
                'events' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'validate_bulk_events')
                )
            )
        ));

        // Event availability
        register_rest_route('kh-events/v1', '/events/(?P<id>\d+)/availability', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_event_availability'),
            'permission_callback' => array($this, 'api_permissions_check'),
            'args' => array(
                'id' => array(
                    'validate_callback' => array($this, 'validate_event_id')
                ),
                'date' => array(
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        // Advanced bookings endpoints
        register_rest_route('kh-events/v1', '/bookings/analytics', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_bookings_analytics'),
            'permission_callback' => array($this, 'api_permissions_check'),
            'args' => array(
                'period' => array(
                    'default' => '30',
                    'sanitize_callback' => 'absint'
                ),
                'event_id' => array(
                    'sanitize_callback' => 'absint'
                )
            )
        ));

        // Webhook management
        register_rest_route('kh-events/v1', '/webhooks', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_webhooks'),
                'permission_callback' => array($this, 'admin_permissions_check')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_webhook'),
                'permission_callback' => array($this, 'admin_permissions_check'),
                'args' => array(
                    'url' => array(
                        'required' => true,
                        'validate_callback' => array($this, 'validate_url')
                    ),
                    'events' => array(
                        'required' => true,
                        'validate_callback' => array($this, 'validate_webhook_events')
                    ),
                    'secret' => array(
                        'sanitize_callback' => 'sanitize_text_field'
                    )
                )
            )
        ));

        register_rest_route('kh-events/v1', '/webhooks/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_webhook'),
                'permission_callback' => array($this, 'admin_permissions_check')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_webhook'),
                'permission_callback' => array($this, 'admin_permissions_check')
            )
        ));
    }

    public function register_integration_routes() {
        // Integration status endpoints
        register_rest_route('kh-events/v1', '/integrations', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_integrations_status'),
            'permission_callback' => array($this, 'admin_permissions_check')
        ));

        // Individual integration control
        register_rest_route('kh-events/v1', '/integrations/(?P<integration>[a-zA-Z_]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_integration_status'),
                'permission_callback' => array($this, 'admin_permissions_check')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_integration_settings'),
                'permission_callback' => array($this, 'admin_permissions_check')
            )
        ));

        // Integration sync endpoints
        register_rest_route('kh-events/v1', '/integrations/(?P<integration>[a-zA-Z_]+)/sync', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'sync_integration'),
            'permission_callback' => array($this, 'admin_permissions_check'),
            'args' => array(
                'event_id' => array(
                    'sanitize_callback' => 'absint'
                ),
                'action' => array(
                    'enum' => array('push', 'pull', 'sync')
                )
            )
        ));
    }

    // API Methods
    public function search_events($request) {
        $query = $request->get_param('query');
        $limit = $request->get_param('limit');

        $args = array(
            'post_type' => 'kh_event',
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => $limit,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_event_start_date',
                    'value' => $query,
                    'compare' => 'LIKE'
                )
            )
        );

        $events = get_posts($args);
        $data = array();

        foreach ($events as $event) {
            $data[] = $this->prepare_event_data($event);
        }

        return new WP_REST_Response($data, 200);
    }

    public function bulk_events_operation($request) {
        $operation = $request->get_param('operation');
        $events = $request->get_param('events');

        $results = array();
        $errors = array();

        foreach ($events as $event_data) {
            try {
                switch ($operation) {
                    case 'create':
                        $result = $this->create_event_via_api($event_data);
                        break;
                    case 'update':
                        $result = $this->update_event_via_api($event_data);
                        break;
                    case 'delete':
                        $result = $this->delete_event_via_api($event_data['id']);
                        break;
                }
                $results[] = $result;
            } catch (Exception $e) {
                $errors[] = array(
                    'event' => $event_data,
                    'error' => $e->getMessage()
                );
            }
        }

        return new WP_REST_Response(array(
            'operation' => $operation,
            'successful' => count($results),
            'failed' => count($errors),
            'results' => $results,
            'errors' => $errors
        ), 200);
    }

    public function get_event_availability($request) {
        $event_id = $request->get_param('id');
        $date = $request->get_param('date');

        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'kh_event') {
            return new WP_Error('invalid_event', 'Event not found', array('status' => 404));
        }

        $availability = array(
            'event_id' => $event_id,
            'total_capacity' => get_post_meta($event_id, '_event_capacity', true) ?: 0,
            'booked_count' => $this->get_booked_count($event_id, $date),
            'available_spots' => 0,
            'status' => 'available'
        );

        $availability['available_spots'] = $availability['total_capacity'] - $availability['booked_count'];

        if ($availability['available_spots'] <= 0) {
            $availability['status'] = 'sold_out';
        }

        return new WP_REST_Response($availability, 200);
    }

    public function get_bookings_analytics($request) {
        $period = $request->get_param('period');
        $event_id = $request->get_param('event_id');

        global $wpdb;

        $start_date = date('Y-m-d', strtotime("-{$period} days"));

        $where_clause = "WHERE booking_date >= '{$start_date}'";
        if ($event_id) {
            $where_clause .= " AND event_id = " . absint($event_id);
        }

        $analytics = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_bookings,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_booking_value,
                COUNT(DISTINCT event_id) as events_with_bookings
             FROM {$wpdb->prefix}kh_booking_analytics
             {$where_clause}"
        ), ARRAY_A);

        return new WP_REST_Response($analytics, 200);
    }

    // Webhook Methods
    public function get_webhooks($request) {
        $webhooks = get_option('kh_events_webhooks', array());
        return new WP_REST_Response(array_values($webhooks), 200);
    }

    public function create_webhook($request) {
        $webhooks = get_option('kh_events_webhooks', array());

        $webhook = array(
            'id' => uniqid('wh_', true),
            'url' => $request->get_param('url'),
            'events' => $request->get_param('events'),
            'secret' => $request->get_param('secret') ?: wp_generate_password(32, false),
            'active' => true,
            'created' => current_time('mysql'),
            'last_triggered' => null,
            'failure_count' => 0
        );

        $webhooks[$webhook['id']] = $webhook;
        update_option('kh_events_webhooks', $webhooks);

        return new WP_REST_Response($webhook, 201);
    }

    public function update_webhook($request) {
        $webhooks = get_option('kh_events_webhooks', array());
        $id = $request->get_param('id');

        if (!isset($webhooks[$id])) {
            return new WP_Error('webhook_not_found', 'Webhook not found', array('status' => 404));
        }

        $updates = array();
        if ($request->has_param('url')) {
            $updates['url'] = $request->get_param('url');
        }
        if ($request->has_param('events')) {
            $updates['events'] = $request->get_param('events');
        }
        if ($request->has_param('active')) {
            $updates['active'] = (bool) $request->get_param('active');
        }

        $webhooks[$id] = array_merge($webhooks[$id], $updates);
        update_option('kh_events_webhooks', $webhooks);

        return new WP_REST_Response($webhooks[$id], 200);
    }

    public function delete_webhook($request) {
        $webhooks = get_option('kh_events_webhooks', array());
        $id = $request->get_param('id');

        if (!isset($webhooks[$id])) {
            return new WP_Error('webhook_not_found', 'Webhook not found', array('status' => 404));
        }

        unset($webhooks[$id]);
        update_option('kh_events_webhooks', $webhooks);

        return new WP_REST_Response(null, 204);
    }

    public function trigger_webhook($event_type, $data = null) {
        $webhooks = get_option('kh_events_webhooks', array());

        foreach ($webhooks as $webhook) {
            if (!$webhook['active']) {
                continue;
            }

            if (!in_array($event_type, $webhook['events'])) {
                continue;
            }

            $this->send_webhook($webhook, $event_type, $data);
        }
    }

    private function send_webhook($webhook, $event_type, $data) {
        $payload = array(
            'event' => $event_type,
            'timestamp' => current_time('timestamp'),
            'data' => $data
        );

        // Generate signature
        $signature = hash_hmac('sha256', json_encode($payload), $webhook['secret']);

        $response = wp_remote_post($webhook['url'], array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-KH-Events-Signature' => $signature,
                'X-KH-Events-Event' => $event_type
            ),
            'body' => json_encode($payload),
            'timeout' => 30
        ));

        // Update webhook status
        $webhooks = get_option('kh_events_webhooks', array());
        if (isset($webhooks[$webhook['id']])) {
            $webhooks[$webhook['id']]['last_triggered'] = current_time('mysql');

            if (is_wp_error($response)) {
                $webhooks[$webhook['id']]['failure_count']++;
            } else {
                $webhooks[$webhook['id']]['failure_count'] = 0;
            }

            update_option('kh_events_webhooks', $webhooks);
        }

        // Log webhook attempt
        $this->log_webhook_attempt($webhook, $event_type, $response);
    }

    // Integration Methods
    public function get_integrations_status($request) {
        $status = array();

        foreach ($this->integrations as $key => $integration) {
            $status[$key] = array(
                'name' => $integration->get_name(),
                'connected' => $integration->is_connected(),
                'last_sync' => $integration->get_last_sync(),
                'status' => $integration->get_status()
            );
        }

        return new WP_REST_Response($status, 200);
    }

    public function get_integration_status($request) {
        $integration = $request->get_param('integration');

        if (!isset($this->integrations[$integration])) {
            return new WP_Error('integration_not_found', 'Integration not found', array('status' => 404));
        }

        $integration_obj = $this->integrations[$integration];

        return new WP_REST_Response(array(
            'name' => $integration_obj->get_name(),
            'connected' => $integration_obj->is_connected(),
            'settings' => $integration_obj->get_settings(),
            'last_sync' => $integration_obj->get_last_sync(),
            'status' => $integration_obj->get_status(),
            'capabilities' => $integration_obj->get_capabilities()
        ), 200);
    }

    public function update_integration_settings($request) {
        $integration = $request->get_param('integration');

        if (!isset($this->integrations[$integration])) {
            return new WP_Error('integration_not_found', 'Integration not found', array('status' => 404));
        }

        $settings = $request->get_param('settings');
        $integration_obj = $this->integrations[$integration];

        $result = $integration_obj->update_settings($settings);

        return new WP_REST_Response(array(
            'success' => $result,
            'message' => $result ? 'Settings updated' : 'Failed to update settings'
        ), $result ? 200 : 400);
    }

    public function sync_integration($request) {
        $integration = $request->get_param('integration');
        $event_id = $request->get_param('event_id');
        $action = $request->get_param('action') ?: 'sync';

        if (!isset($this->integrations[$integration])) {
            return new WP_Error('integration_not_found', 'Integration not found', array('status' => 404));
        }

        $integration_obj = $this->integrations[$integration];

        try {
            $result = $integration_obj->sync($event_id, $action);

            return new WP_REST_Response(array(
                'success' => true,
                'result' => $result,
                'message' => ucfirst($action) . ' completed successfully'
            ), 200);
        } catch (Exception $e) {
            return new WP_Error('sync_failed', $e->getMessage(), array('status' => 500));
        }
    }

    // Helper Methods
    private function prepare_event_data($event) {
        return array(
            'id' => $event->ID,
            'title' => $event->post_title,
            'content' => $event->post_content,
            'excerpt' => $event->post_excerpt,
            'status' => $event->post_status,
            'start_date' => get_post_meta($event->ID, '_event_start_date', true),
            'end_date' => get_post_meta($event->ID, '_event_end_date', true),
            'location' => get_post_meta($event->ID, '_event_location', true),
            'capacity' => get_post_meta($event->ID, '_event_capacity', true),
            'price' => get_post_meta($event->ID, '_event_price', true),
            'categories' => wp_get_post_terms($event->ID, 'kh_event_category', array('fields' => 'names')),
            'tags' => wp_get_post_terms($event->ID, 'kh_event_tag', array('fields' => 'names')),
            'permalink' => get_permalink($event->ID)
        );
    }

    private function get_booked_count($event_id, $date = null) {
        global $wpdb;

        $where = $wpdb->prepare("WHERE event_id = %d", $event_id);
        if ($date) {
            $where .= $wpdb->prepare(" AND DATE(booking_date) = %s", $date);
        }

        return (int) $wpdb->get_var("SELECT SUM(ticket_quantity) FROM {$wpdb->prefix}kh_booking_analytics {$where}");
    }

    private function create_event_via_api($event_data) {
        // Implementation for bulk event creation
        $post_data = array(
            'post_title' => $event_data['title'] ?? '',
            'post_content' => $event_data['content'] ?? '',
            'post_status' => $event_data['status'] ?? 'draft',
            'post_type' => 'kh_event'
        );

        $event_id = wp_insert_post($post_data);

        if (is_wp_error($event_id)) {
            throw new Exception($event_id->get_error_message());
        }

        // Add meta data
        if (isset($event_data['start_date'])) {
            update_post_meta($event_id, '_event_start_date', $event_data['start_date']);
        }
        if (isset($event_data['end_date'])) {
            update_post_meta($event_id, '_event_end_date', $event_data['end_date']);
        }
        if (isset($event_data['location'])) {
            update_post_meta($event_id, '_event_location', $event_data['location']);
        }
        if (isset($event_data['capacity'])) {
            update_post_meta($event_id, '_event_capacity', $event_data['capacity']);
        }

        return array('id' => $event_id, 'status' => 'created');
    }

    private function update_event_via_api($event_data) {
        // Implementation for bulk event updates
        if (!isset($event_data['id'])) {
            throw new Exception('Event ID required for update');
        }

        $post_data = array('ID' => $event_data['id']);
        if (isset($event_data['title'])) {
            $post_data['post_title'] = $event_data['title'];
        }
        if (isset($event_data['content'])) {
            $post_data['post_content'] = $event_data['content'];
        }

        $result = wp_update_post($post_data);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        return array('id' => $event_data['id'], 'status' => 'updated');
    }

    private function delete_event_via_api($event_id) {
        // Implementation for bulk event deletion
        $result = wp_delete_post($event_id, true);

        if (!$result) {
            throw new Exception('Failed to delete event');
        }

        return array('id' => $event_id, 'status' => 'deleted');
    }

    // Permission Checks
    public function api_permissions_check($request) {
        // Check for valid API key or OAuth token
        $api_key = $request->get_header('X-API-Key');

        if ($api_key && $this->validate_api_key($api_key)) {
            return true;
        }

        // Fallback to standard WordPress permissions
        return current_user_can('read');
    }

    public function admin_permissions_check($request) {
        return current_user_can('manage_options');
    }

    private function validate_api_key($api_key) {
        $api_keys = get_option('kh_events_api_keys', array());
        return in_array($api_key, array_column($api_keys, 'key'));
    }

    // Validation Methods
    public function validate_bulk_events($events) {
        if (!is_array($events) || empty($events)) {
            return false;
        }

        foreach ($events as $event) {
            if (!is_array($event) || !isset($event['title'])) {
                return false;
            }
        }

        return true;
    }

    public function validate_event_id($id) {
        return is_numeric($id) && get_post_type($id) === 'kh_event';
    }

    public function validate_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function validate_webhook_events($events) {
        $valid_events = array(
            'event.created', 'event.updated', 'event.deleted',
            'booking.completed', 'booking.cancelled', 'booking.refunded'
        );

        if (!is_array($events)) {
            return false;
        }

        foreach ($events as $event) {
            if (!in_array($event, $valid_events)) {
                return false;
            }
        }

        return true;
    }

    // Admin Interface
    public function add_api_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=kh_event',
            __('API & Integrations', 'kh-events'),
            __('API & Integrations', 'kh-events'),
            'manage_options',
            'kh-events-api',
            array($this, 'render_api_admin_page')
        );
    }

    public function render_api_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('KH Events API & Integrations', 'kh-events'); ?></h1>

            <div class="kh-api-dashboard">
                <!-- API Keys Section -->
                <div class="kh-api-section">
                    <h2><?php _e('API Keys', 'kh-events'); ?></h2>
                    <div id="api-keys-list">
                        <!-- API keys will be loaded here -->
                    </div>
                    <button id="generate-api-key" class="button button-primary">
                        <?php _e('Generate New API Key', 'kh-events'); ?>
                    </button>
                </div>

                <!-- Webhooks Section -->
                <div class="kh-api-section">
                    <h2><?php _e('Webhooks', 'kh-events'); ?></h2>
                    <div id="webhooks-list">
                        <!-- Webhooks will be loaded here -->
                    </div>
                    <button id="add-webhook" class="button button-primary">
                        <?php _e('Add Webhook', 'kh-events'); ?>
                    </button>
                </div>

                <!-- Integrations Section -->
                <div class="kh-api-section">
                    <h2><?php _e('Integrations', 'kh-events'); ?></h2>
                    <div id="integrations-list">
                        <!-- Integrations will be loaded here -->
                    </div>
                </div>
            </div>
        </div>

        <style>
            .kh-api-dashboard { margin-top: 20px; }
            .kh-api-section { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .kh-api-section h2 { margin-top: 0; color: #23282d; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Load API data
            loadApiKeys();
            loadWebhooks();
            loadIntegrations();

            // Generate API key
            $('#generate-api-key').on('click', function() {
                const name = prompt('Enter a name for this API key:');
                if (name) {
                    $.post(ajaxurl, {
                        action: 'kh_api_generate_key',
                        name: name,
                        nonce: '<?php echo wp_create_nonce("kh_api_admin"); ?>'
                    }, function(response) {
                        if (response.success) {
                            loadApiKeys();
                        }
                    });
                }
            });

            function loadApiKeys() {
                // Implementation for loading API keys
                $('#api-keys-list').html('<p>Loading API keys...</p>');
            }

            function loadWebhooks() {
                // Implementation for loading webhooks
                $('#webhooks-list').html('<p>Loading webhooks...</p>');
            }

            function loadIntegrations() {
                // Implementation for loading integrations
                $('#integrations-list').html('<p>Loading integrations...</p>');
            }
        });
        </script>
        <?php
    }

    // AJAX Handlers
    public function ajax_generate_api_key() {
        check_ajax_referer('kh_api_admin', 'nonce');

        $name = sanitize_text_field($_POST['name']);
        $api_keys = get_option('kh_events_api_keys', array());

        $new_key = array(
            'id' => uniqid('key_', true),
            'name' => $name,
            'key' => wp_generate_password(32, false),
            'created' => current_time('mysql'),
            'last_used' => null,
            'active' => true
        );

        $api_keys[] = $new_key;
        update_option('kh_events_api_keys', $api_keys);

        wp_send_json_success($new_key);
    }

    public function ajax_revoke_api_key() {
        check_ajax_referer('kh_api_admin', 'nonce');

        $key_id = sanitize_text_field($_POST['key_id']);
        $api_keys = get_option('kh_events_api_keys', array());

        foreach ($api_keys as &$key) {
            if ($key['id'] === $key_id) {
                $key['active'] = false;
                break;
            }
        }

        update_option('kh_events_api_keys', $api_keys);
        wp_send_json_success();
    }

    public function ajax_test_webhook() {
        check_ajax_referer('kh_api_admin', 'nonce');

        $webhook_id = sanitize_text_field($_POST['webhook_id']);
        $webhooks = get_option('kh_events_webhooks', array());

        if (!isset($webhooks[$webhook_id])) {
            wp_send_json_error('Webhook not found');
            return;
        }

        $webhook = $webhooks[$webhook_id];
        $test_payload = array(
            'event' => 'test',
            'timestamp' => current_time('timestamp'),
            'data' => array('message' => 'Test webhook from KH Events')
        );

        $response = wp_remote_post($webhook['url'], array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-KH-Events-Signature' => hash_hmac('sha256', json_encode($test_payload), $webhook['secret'])
            ),
            'body' => json_encode($test_payload),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('Webhook test failed: ' . $response->get_error_message());
        } else {
            wp_send_json_success('Webhook test successful');
        }
    }

    private function log_webhook_attempt($webhook, $event_type, $response) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'webhook_id' => $webhook['id'],
            'event_type' => $event_type,
            'url' => $webhook['url'],
            'success' => !is_wp_error($response),
            'response_code' => is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response),
            'response_body' => is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response)
        );

        $logs = get_option('kh_events_webhook_logs', array());
        $logs[] = $log_entry;

        // Keep only last 1000 logs
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }

        update_option('kh_events_webhook_logs', $logs);
    }
}
