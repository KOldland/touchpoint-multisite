<?php
/**
 * API Enhancement Service Provider for KH Events
 *
 * Provides comprehensive API functionality for external integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_API_Provider extends KH_Events_Service_Provider {

    /**
     * API controllers
     */
    private $controllers = array();

    /**
     * Webhook manager
     */
    private $webhook_manager;

    /**
     * Integration manager
     */
    private $integration_manager;

    /**
     * Register API services
     */
    public function register() {
        // Bind API services
        $this->bind('kh_events_api_controller', 'KH_Events_API_Controller', true);
        $this->bind('kh_events_webhook_manager', 'KH_Events_Webhook_Manager', true);
        $this->bind('kh_events_integration_manager', 'KH_Events_Integration_Manager', true);
        $this->bind('kh_events_api_auth', 'KH_Events_API_Auth', true);
        $this->bind('kh_events_feed_generator', 'KH_Events_Feed_Generator', true);
    }

    /**
     * Boot the API provider
     */
    public function boot() {
        // Initialize REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Initialize webhook system
        add_action('init', array($this, 'init_webhooks'));

        // Initialize integrations
        add_action('init', array($this, 'init_integrations'));

        // Add API settings to admin
        add_action('kh_events_admin_settings_tabs', array($this, 'add_api_settings_tab'));
        add_action('kh_events_admin_settings_content', array($this, 'render_api_settings'));

        // Enqueue admin assets for API management
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Add API endpoints to admin menu
        add_action('admin_menu', array($this, 'add_api_menu'));

        // Register AJAX handlers for API management
        add_action('wp_ajax_kh_events_api_test', array($this, 'ajax_test_api'));
        add_action('wp_ajax_kh_events_webhook_test', array($this, 'ajax_test_webhook'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        $api_controller = $this->get('kh_events_api_controller');

        // Events endpoints
        register_rest_route('kh-events/v1', '/events', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($api_controller, 'get_events'),
                'permission_callback' => array($this, 'check_api_permissions'),
                'args' => $this->get_events_args()
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($api_controller, 'create_event'),
                'permission_callback' => array($this, 'check_api_permissions'),
                'args' => $this->get_create_event_args()
            )
        ));

        register_rest_route('kh-events/v1', '/events/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($api_controller, 'get_event'),
                'permission_callback' => array($this, 'check_api_permissions')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($api_controller, 'update_event'),
                'permission_callback' => array($this, 'check_api_permissions'),
                'args' => $this->get_update_event_args()
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($api_controller, 'delete_event'),
                'permission_callback' => array($this, 'check_api_permissions')
            )
        ));

        // Bookings endpoints
        register_rest_route('kh-events/v1', '/bookings', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($api_controller, 'get_bookings'),
                'permission_callback' => array($this, 'check_api_permissions')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($api_controller, 'create_booking'),
                'permission_callback' => array($this, 'check_api_permissions'),
                'args' => $this->get_create_booking_args()
            )
        ));

        // Locations endpoints
        register_rest_route('kh-events/v1', '/locations', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($api_controller, 'get_locations'),
                'permission_callback' => array($this, 'check_api_permissions')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($api_controller, 'create_location'),
                'permission_callback' => array($this, 'check_api_permissions'),
                'args' => $this->get_create_location_args()
            )
        ));

        // Categories endpoints
        register_rest_route('kh-events/v1', '/categories', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($api_controller, 'get_categories'),
                'permission_callback' => array($this, 'check_api_permissions')
            )
        ));

        // Feed endpoints
        register_rest_route('kh-events/v1', '/feed/(?P<format>(?:ical|json|rss))', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($api_controller, 'get_feed'),
            'permission_callback' => '__return_true' // Public access for feeds
        ));

        // Webhook endpoints
        register_rest_route('kh-events/v1', '/webhooks/(?P<id>[a-zA-Z0-9-_]+)', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * Initialize webhook system
     */
    public function init_webhooks() {
        $this->webhook_manager = $this->get('kh_events_webhook_manager');
        $this->webhook_manager->init();
    }

    /**
     * Initialize integrations
     */
    public function init_integrations() {
        $this->integration_manager = $this->get('kh_events_integration_manager');
        $this->integration_manager->init();
    }

    /**
     * Check API permissions
     */
    public function check_api_permissions($request) {
        $auth = $this->get('kh_events_api_auth');
        return $auth->check_permissions($request);
    }

    /**
     * Handle webhook requests
     */
    public function handle_webhook($request) {
        $webhook_id = $request->get_param('id');
        return $this->webhook_manager->process_webhook($webhook_id, $request);
    }

    /**
     * Add API settings tab
     */
    public function add_api_settings_tab($tabs) {
        $tabs['api'] = __('API & Integrations', 'kh-events');
        return $tabs;
    }

    /**
     * Render API settings content
     */
    public function render_api_settings($active_tab) {
        if ($active_tab !== 'api') {
            return;
        }

        include KH_EVENTS_PATH . 'includes/admin/views/api-settings.php';
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'kh-events') === false) {
            return;
        }

        wp_enqueue_script(
            'kh-events-api-admin',
            KH_EVENTS_URL . 'assets/js/api-admin.js',
            array('jquery'),
            KH_EVENTS_VERSION,
            true
        );

        wp_localize_script('kh-events-api-admin', 'kh_events_api', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kh_events_api_nonce'),
            'strings' => array(
                'testing' => __('Testing...', 'kh-events'),
                'test_success' => __('Test successful!', 'kh-events'),
                'test_failed' => __('Test failed!', 'kh-events'),
            )
        ));
    }

    /**
     * Add API menu to admin
     */
    public function add_api_menu() {
        add_submenu_page(
            'edit.php?post_type=kh_event',
            __('API & Webhooks', 'kh-events'),
            __('API & Webhooks', 'kh-events'),
            'manage_options',
            'kh-events-api',
            array($this, 'render_api_page')
        );
    }

    /**
     * Render API admin page
     */
    public function render_api_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('KH Events API & Webhooks', 'kh-events'); ?></h1>

            <div class="kh-events-api-container">
                <div class="kh-events-api-section">
                    <h2><?php _e('API Endpoints', 'kh-events'); ?></h2>
                    <div class="kh-events-api-endpoints">
                        <div class="kh-events-api-endpoint">
                            <strong>GET</strong> /wp-json/kh-events/v1/events
                            <span class="kh-events-api-desc"><?php _e('Get events with filtering', 'kh-events'); ?></span>
                        </div>
                        <div class="kh-events-api-endpoint">
                            <strong>POST</strong> /wp-json/kh-events/v1/events
                            <span class="kh-events-api-desc"><?php _e('Create new event', 'kh-events'); ?></span>
                        </div>
                        <div class="kh-events-api-endpoint">
                            <strong>GET</strong> /wp-json/kh-events/v1/events/{id}
                            <span class="kh-events-api-desc"><?php _e('Get single event', 'kh-events'); ?></span>
                        </div>
                        <div class="kh-events-api-endpoint">
                            <strong>PUT</strong> /wp-json/kh-events/v1/events/{id}
                            <span class="kh-events-api-desc"><?php _e('Update event', 'kh-events'); ?></span>
                        </div>
                        <div class="kh-events-api-endpoint">
                            <strong>DELETE</strong> /wp-json/kh-events/v1/events/{id}
                            <span class="kh-events-api-desc"><?php _e('Delete event', 'kh-events'); ?></span>
                        </div>
                        <div class="kh-events-api-endpoint">
                            <strong>GET</strong> /wp-json/kh-events/v1/feed/{format}
                            <span class="kh-events-api-desc"><?php _e('Get event feeds (ical/json/rss)', 'kh-events'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="kh-events-api-section">
                    <h2><?php _e('Webhooks', 'kh-events'); ?></h2>
                    <?php $this->render_webhook_manager(); ?>
                </div>

                <div class="kh-events-api-section">
                    <h2><?php _e('Integrations', 'kh-events'); ?></h2>
                    <?php $this->render_integration_manager(); ?>
                </div>

                <div class="kh-events-api-section">
                    <h2><?php _e('API Testing', 'kh-events'); ?></h2>
                    <button id="kh-events-api-test" class="button button-primary">
                        <?php _e('Test API Connection', 'kh-events'); ?>
                    </button>
                    <div id="kh-events-api-test-result"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render webhook manager
     */
    private function render_webhook_manager() {
        $webhooks = $this->webhook_manager->get_webhooks();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Name', 'kh-events'); ?></th>
                    <th><?php _e('URL', 'kh-events'); ?></th>
                    <th><?php _e('Events', 'kh-events'); ?></th>
                    <th><?php _e('Status', 'kh-events'); ?></th>
                    <th><?php _e('Actions', 'kh-events'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($webhooks)): ?>
                <tr>
                    <td colspan="5"><?php _e('No webhooks configured.', 'kh-events'); ?></td>
                </tr>
                <?php else: ?>
                    <?php foreach ($webhooks as $webhook): ?>
                    <tr>
                        <td><?php echo esc_html($webhook['name']); ?></td>
                        <td><?php echo esc_url($webhook['url']); ?></td>
                        <td><?php echo esc_html(implode(', ', $webhook['events'])); ?></td>
                        <td>
                            <span class="kh-events-status kh-events-status-<?php echo esc_attr($webhook['status']); ?>">
                                <?php echo esc_html(ucfirst($webhook['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <button class="button button-small kh-events-webhook-test" data-id="<?php echo esc_attr($webhook['id']); ?>">
                                <?php _e('Test', 'kh-events'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render integration manager
     */
    private function render_integration_manager() {
        $integrations = $this->integration_manager->get_integrations();
        ?>
        <div class="kh-events-integrations">
            <?php foreach ($integrations as $integration): ?>
            <div class="kh-events-integration <?php echo $integration['active'] ? 'active' : 'inactive'; ?>">
                <div class="kh-events-integration-header">
                    <h4><?php echo esc_html($integration['name']); ?></h4>
                    <label class="kh-events-integration-toggle">
                        <input type="checkbox"
                               <?php checked($integration['active']); ?>
                               data-integration="<?php echo esc_attr($integration['id']); ?>">
                        <span class="slider"></span>
                    </label>
                </div>
                <p><?php echo esc_html($integration['description']); ?></p>
                <?php if ($integration['settings_url']): ?>
                <a href="<?php echo esc_url($integration['settings_url']); ?>" class="button button-small">
                    <?php _e('Settings', 'kh-events'); ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * AJAX test API
     */
    public function ajax_test_api() {
        check_ajax_referer('kh_events_api_nonce', 'nonce');

        $response = wp_remote_get(rest_url('kh-events/v1/events'), array(
            'timeout' => 10,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_api_key()
            )
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => $response->get_error_message()
            ));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array(
                'message' => __('Invalid JSON response', 'kh-events')
            ));
        }

        wp_send_json_success(array(
            'message' => __('API connection successful', 'kh-events'),
            'data' => $data
        ));
    }

    /**
     * AJAX test webhook
     */
    public function ajax_test_webhook() {
        check_ajax_referer('kh_events_api_nonce', 'nonce');

        $webhook_id = sanitize_text_field($_POST['webhook_id']);
        $result = $this->webhook_manager->test_webhook($webhook_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Get API key for authentication
     */
    private function get_api_key() {
        $settings = get_option('kh_events_api_settings', array());
        return $settings['api_key'] ?? '';
    }

    /**
     * Get events endpoint args
     */
    private function get_events_args() {
        return array(
            'page' => array(
                'description' => __('Current page of results', 'kh-events'),
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1
            ),
            'per_page' => array(
                'description' => __('Number of events per page', 'kh-events'),
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100
            ),
            'start_date' => array(
                'description' => __('Filter events starting from this date', 'kh-events'),
                'type' => 'string',
                'format' => 'date'
            ),
            'end_date' => array(
                'description' => __('Filter events ending before this date', 'kh-events'),
                'type' => 'string',
                'format' => 'date'
            ),
            'categories' => array(
                'description' => __('Filter by category IDs', 'kh-events'),
                'type' => 'array',
                'items' => array('type' => 'integer')
            ),
            'locations' => array(
                'description' => __('Filter by location IDs', 'kh-events'),
                'type' => 'array',
                'items' => array('type' => 'integer')
            ),
            'status' => array(
                'description' => __('Filter by event status', 'kh-events'),
                'type' => 'string',
                'enum' => array('scheduled', 'cancelled', 'postponed')
            )
        );
    }

    /**
     * Get create event args
     */
    private function get_create_event_args() {
        return array(
            'title' => array(
                'required' => true,
                'description' => __('Event title', 'kh-events'),
                'type' => 'string',
                'maxLength' => 255
            ),
            'description' => array(
                'description' => __('Event description', 'kh-events'),
                'type' => 'string'
            ),
            'start_date' => array(
                'required' => true,
                'description' => __('Event start date', 'kh-events'),
                'type' => 'string',
                'format' => 'date'
            ),
            'end_date' => array(
                'description' => __('Event end date', 'kh-events'),
                'type' => 'string',
                'format' => 'date'
            ),
            'start_time' => array(
                'description' => __('Event start time', 'kh-events'),
                'type' => 'string',
                'format' => 'time'
            ),
            'end_time' => array(
                'description' => __('Event end time', 'kh-events'),
                'type' => 'string',
                'format' => 'time'
            ),
            'max_capacity' => array(
                'description' => __('Maximum number of attendees', 'kh-events'),
                'type' => 'integer',
                'minimum' => 0
            ),
            'price' => array(
                'description' => __('Event price', 'kh-events'),
                'type' => 'number',
                'minimum' => 0
            ),
            'currency' => array(
                'description' => __('Currency code', 'kh-events'),
                'type' => 'string',
                'default' => 'USD',
                'maxLength' => 3
            ),
            'location_id' => array(
                'description' => __('Location ID', 'kh-events'),
                'type' => 'integer'
            ),
            'categories' => array(
                'description' => __('Category IDs', 'kh-events'),
                'type' => 'array',
                'items' => array('type' => 'integer')
            )
        );
    }

    /**
     * Get update event args
     */
    private function get_update_event_args() {
        return $this->get_create_event_args(); // Same as create
    }

    /**
     * Get create booking args
     */
    private function get_create_booking_args() {
        return array(
            'event_id' => array(
                'required' => true,
                'description' => __('Event ID', 'kh-events'),
                'type' => 'integer'
            ),
            'attendee_name' => array(
                'required' => true,
                'description' => __('Attendee full name', 'kh-events'),
                'type' => 'string',
                'maxLength' => 255
            ),
            'attendee_email' => array(
                'required' => true,
                'description' => __('Attendee email address', 'kh-events'),
                'type' => 'string',
                'format' => 'email'
            ),
            'quantity' => array(
                'description' => __('Number of tickets', 'kh-events'),
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1
            ),
            'notes' => array(
                'description' => __('Additional notes', 'kh-events'),
                'type' => 'string'
            )
        );
    }

    /**
     * Get create location args
     */
    private function get_create_location_args() {
        return array(
            'name' => array(
                'required' => true,
                'description' => __('Location name', 'kh-events'),
                'type' => 'string',
                'maxLength' => 255
            ),
            'address' => array(
                'description' => __('Street address', 'kh-events'),
                'type' => 'string'
            ),
            'city' => array(
                'description' => __('City', 'kh-events'),
                'type' => 'string',
                'maxLength' => 100
            ),
            'state' => array(
                'description' => __('State/Province', 'kh-events'),
                'type' => 'string',
                'maxLength' => 100
            ),
            'zip_code' => array(
                'description' => __('ZIP/Postal code', 'kh-events'),
                'type' => 'string',
                'maxLength' => 20
            ),
            'country' => array(
                'description' => __('Country', 'kh-events'),
                'type' => 'string',
                'maxLength' => 100
            ),
            'latitude' => array(
                'description' => __('Latitude coordinate', 'kh-events'),
                'type' => 'number',
                'minimum' => -90,
                'maximum' => 90
            ),
            'longitude' => array(
                'description' => __('Longitude coordinate', 'kh-events'),
                'type' => 'number',
                'minimum' => -180,
                'maximum' => 180
            )
        );
    }
}