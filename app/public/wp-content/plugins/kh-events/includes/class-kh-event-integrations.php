<?php
/**
 * KH Events Third-Party Integrations
 * Zoom, Eventbrite, and Facebook integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Event_Integrations {

    private static $instance = null;

    // Integration types
    const INTEGRATION_ZOOM = 'zoom';
    const INTEGRATION_EVENTBRITE = 'eventbrite';
    const INTEGRATION_FACEBOOK = 'facebook';

    // Zoom API endpoints
    const ZOOM_API_BASE = 'https://api.zoom.us/v2';
    const ZOOM_AUTH_URL = 'https://zoom.us/oauth/authorize';
    const ZOOM_TOKEN_URL = 'https://zoom.us/oauth/token';

    // Eventbrite API endpoints
    const EVENTBRITE_API_BASE = 'https://www.eventbriteapi.com/v3';
    const EVENTBRITE_AUTH_URL = 'https://www.eventbrite.com/oauth/authorize';
    const EVENTBRITE_TOKEN_URL = 'https://www.eventbrite.com/oauth/token';

    // Facebook API endpoints
    const FACEBOOK_GRAPH_API = 'https://graph.facebook.com/v18.0';

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
        add_action('init', array($this, 'init_integrations'));
        add_action('admin_init', array($this, 'handle_oauth_callbacks'));
        add_action('wp_ajax_kh_sync_event_to_zoom', array($this, 'ajax_sync_event_to_zoom'));
        add_action('wp_ajax_kh_sync_event_to_eventbrite', array($this, 'ajax_sync_event_to_eventbrite'));
        add_action('wp_ajax_kh_sync_event_to_facebook', array($this, 'ajax_sync_event_to_facebook'));
        add_action('wp_ajax_kh_refresh_integration_tokens', array($this, 'ajax_refresh_integration_tokens'));

        // Meta boxes for integrations
        add_action('add_meta_boxes', array($this, 'add_integration_meta_boxes'));

        // Save integration data
        add_action('save_post_kh_event', array($this, 'save_integration_data'), 10, 2);

        // Event status changes
        add_action('kh_event_status_changed', array($this, 'handle_event_status_change'), 10, 3);

        // Cron jobs for sync
        add_action('kh_events_sync_zoom_meetings', array($this, 'sync_zoom_meetings'));
        add_action('kh_events_sync_eventbrite_events', array($this, 'sync_eventbrite_events'));
        add_action('kh_events_sync_facebook_events', array($this, 'sync_facebook_events'));
    }

    /**
     * Initialize integrations
     */
    public function init_integrations() {
        // Schedule cron jobs
        if (!wp_next_scheduled('kh_events_sync_zoom_meetings')) {
            wp_schedule_event(time(), 'hourly', 'kh_events_sync_zoom_meetings');
        }
        if (!wp_next_scheduled('kh_events_sync_eventbrite_events')) {
            wp_schedule_event(time(), 'hourly', 'kh_events_sync_eventbrite_events');
        }
        if (!wp_next_scheduled('kh_events_sync_facebook_events')) {
            wp_schedule_event(time(), 'hourly', 'kh_events_sync_facebook_events');
        }
    }

    /**
     * Handle OAuth callbacks
     */
    public function handle_oauth_callbacks() {
        if (isset($_GET['kh_integration']) && isset($_GET['code'])) {
            $integration = sanitize_text_field($_GET['kh_integration']);
            $code = sanitize_text_field($_GET['code']);

            switch ($integration) {
                case self::INTEGRATION_ZOOM:
                    $this->handle_zoom_oauth_callback($code);
                    break;
                case self::INTEGRATION_EVENTBRITE:
                    $this->handle_eventbrite_oauth_callback($code);
                    break;
                case self::INTEGRATION_FACEBOOK:
                    $this->handle_facebook_oauth_callback($code);
                    break;
            }

            // Redirect back to settings
            wp_redirect(admin_url('admin.php?page=kh-events-settings&tab=integrations&connected=' . $integration));
            exit;
        }
    }

    /**
     * Add integration meta boxes to event edit screen
     */
    public function add_integration_meta_boxes() {
        add_meta_box(
            'kh_event_integrations',
            __('Third-Party Integrations', 'kh-events'),
            array($this, 'render_integration_meta_box'),
            'kh_event',
            'side',
            'default'
        );
    }

    /**
     * Render integration meta box
     */
    public function render_integration_meta_box($post) {
        $zoom_meeting_id = get_post_meta($post->ID, '_kh_zoom_meeting_id', true);
        $eventbrite_id = get_post_meta($post->ID, '_kh_eventbrite_id', true);
        $facebook_event_id = get_post_meta($post->ID, '_kh_facebook_event_id', true);

        wp_nonce_field('kh_event_integrations', 'kh_event_integrations_nonce');
        ?>
        <div class="kh-event-integrations">
            <!-- Zoom Integration -->
            <div class="kh-integration-section">
                <h4><?php _e('Zoom Meeting', 'kh-events'); ?></h4>
                <?php if ($this->is_zoom_connected()): ?>
                    <?php if ($zoom_meeting_id): ?>
                        <p><?php _e('Meeting ID:', 'kh-events'); ?> <code><?php echo esc_html($zoom_meeting_id); ?></code></p>
                        <p><a href="#" class="button" id="kh-sync-zoom"><?php _e('Update Meeting', 'kh-events'); ?></a></p>
                    <?php else: ?>
                        <p><a href="#" class="button" id="kh-create-zoom-meeting"><?php _e('Create Zoom Meeting', 'kh-events'); ?></a></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><?php _e('Zoom not connected.', 'kh-events'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Eventbrite Integration -->
            <div class="kh-integration-section">
                <h4><?php _e('Eventbrite', 'kh-events'); ?></h4>
                <?php if ($this->is_eventbrite_connected()): ?>
                    <?php if ($eventbrite_id): ?>
                        <p><?php _e('Eventbrite ID:', 'kh-events'); ?> <code><?php echo esc_html($eventbrite_id); ?></code></p>
                        <p><a href="#" class="button" id="kh-sync-eventbrite"><?php _e('Update Event', 'kh-events'); ?></a></p>
                    <?php else: ?>
                        <p><a href="#" class="button" id="kh-create-eventbrite-event"><?php _e('Create Eventbrite Event', 'kh-events'); ?></a></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><?php _e('Eventbrite not connected.', 'kh-events'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Facebook Integration -->
            <div class="kh-integration-section">
                <h4><?php _e('Facebook Events', 'kh-events'); ?></h4>
                <?php if ($this->is_facebook_connected()): ?>
                    <?php if ($facebook_event_id): ?>
                        <p><?php _e('Facebook Event ID:', 'kh-events'); ?> <code><?php echo esc_html($facebook_event_id); ?></code></p>
                        <p><a href="#" class="button" id="kh-sync-facebook"><?php _e('Update Facebook Event', 'kh-events'); ?></a></p>
                    <?php else: ?>
                        <p><a href="#" class="button" id="kh-create-facebook-event"><?php _e('Create Facebook Event', 'kh-events'); ?></a></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><?php _e('Facebook not connected.', 'kh-events'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var postId = <?php echo intval($post->ID); ?>;

            $('#kh-create-zoom-meeting, #kh-sync-zoom').on('click', function(e) {
                e.preventDefault();
                $(this).prop('disabled', true).text('<?php _e('Processing...', 'kh-events'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'kh_sync_event_to_zoom',
                        event_id: postId,
                        nonce: '<?php echo wp_create_nonce('kh_sync_zoom'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php _e('Error syncing with Zoom', 'kh-events'); ?>');
                            $('#kh-create-zoom-meeting, #kh-sync-zoom').prop('disabled', false).text('<?php _e('Create/Update Meeting', 'kh-events'); ?>');
                        }
                    }
                });
            });

            $('#kh-create-eventbrite-event, #kh-sync-eventbrite').on('click', function(e) {
                e.preventDefault();
                $(this).prop('disabled', true).text('<?php _e('Processing...', 'kh-events'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'kh_sync_event_to_eventbrite',
                        event_id: postId,
                        nonce: '<?php echo wp_create_nonce('kh_sync_eventbrite'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php _e('Error syncing with Eventbrite', 'kh-events'); ?>');
                            $('#kh-create-eventbrite-event, #kh-sync-eventbrite').prop('disabled', false).text('<?php _e('Create/Update Event', 'kh-events'); ?>');
                        }
                    }
                });
            });

            $('#kh-create-facebook-event, #kh-sync-facebook').on('click', function(e) {
                e.preventDefault();
                $(this).prop('disabled', true).text('<?php _e('Processing...', 'kh-events'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'kh_sync_event_to_facebook',
                        event_id: postId,
                        nonce: '<?php echo wp_create_nonce('kh_sync_facebook'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php _e('Error syncing with Facebook', 'kh-events'); ?>');
                            $('#kh-create-facebook-event, #kh-sync-facebook').prop('disabled', false).text('<?php _e('Create/Update Event', 'kh-events'); ?>');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Save integration data
     */
    public function save_integration_data($post_id, $post) {
        if (!isset($_POST['kh_event_integrations_nonce']) ||
            !wp_verify_nonce($_POST['kh_event_integrations_nonce'], 'kh_event_integrations')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save integration settings
        if (isset($_POST['kh_enable_zoom_sync'])) {
            update_post_meta($post_id, '_kh_enable_zoom_sync', 1);
        } else {
            delete_post_meta($post_id, '_kh_enable_zoom_sync');
        }

        if (isset($_POST['kh_enable_eventbrite_sync'])) {
            update_post_meta($post_id, '_kh_enable_eventbrite_sync', 1);
        } else {
            delete_post_meta($post_id, '_kh_enable_eventbrite_sync');
        }

        if (isset($_POST['kh_enable_facebook_sync'])) {
            update_post_meta($post_id, '_kh_enable_facebook_sync', 1);
        } else {
            delete_post_meta($post_id, '_kh_enable_facebook_sync');
        }
    }

    /**
     * Handle event status changes
     */
    public function handle_event_status_change($event_id, $old_status, $new_status) {
        // Sync when event is published
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->sync_event_on_publish($event_id);
        }

        // Cancel integrations when event is cancelled
        if ($new_status === 'cancelled') {
            $this->cancel_integrations($event_id);
        }
    }

    /**
     * Sync event when published
     */
    private function sync_event_on_publish($event_id) {
        if (get_post_meta($event_id, '_kh_enable_zoom_sync', true)) {
            $this->sync_to_zoom($event_id);
        }

        if (get_post_meta($event_id, '_kh_enable_eventbrite_sync', true)) {
            $this->sync_to_eventbrite($event_id);
        }

        if (get_post_meta($event_id, '_kh_enable_facebook_sync', true)) {
            $this->sync_to_facebook($event_id);
        }
    }

    /**
     * Cancel integrations
     */
    private function cancel_integrations($event_id) {
        $zoom_meeting_id = get_post_meta($event_id, '_kh_zoom_meeting_id', true);
        if ($zoom_meeting_id) {
            $this->cancel_zoom_meeting($zoom_meeting_id);
        }

        $eventbrite_id = get_post_meta($event_id, '_kh_eventbrite_id', true);
        if ($eventbrite_id) {
            $this->cancel_eventbrite_event($eventbrite_id);
        }

        $facebook_event_id = get_post_meta($event_id, '_kh_facebook_event_id', true);
        if ($facebook_event_id) {
            $this->cancel_facebook_event($facebook_event_id);
        }
    }

    // ==================== ZOOM INTEGRATION ====================

    /**
     * Check if Zoom is connected
     */
    public function is_zoom_connected() {
        $settings = get_option('kh_events_integrations_settings', array());
        return !empty($settings['zoom']['access_token']) &&
               !empty($settings['zoom']['refresh_token']);
    }

    /**
     * Get Zoom authorization URL
     */
    public function get_zoom_auth_url() {
        $settings = get_option('kh_events_integrations_settings', array());
        $client_id = $settings['zoom']['client_id'] ?? '';

        if (!$client_id) {
            return false;
        }

        $redirect_uri = admin_url('admin.php?kh_integration=zoom');
        $state = wp_create_nonce('kh_zoom_oauth');

        $params = array(
            'response_type' => 'code',
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'state' => $state,
        );

        return self::ZOOM_AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Handle Zoom OAuth callback
     */
    private function handle_zoom_oauth_callback($code) {
        $settings = get_option('kh_events_integrations_settings', array());
        $client_id = $settings['zoom']['client_id'] ?? '';
        $client_secret = $settings['zoom']['client_secret'] ?? '';

        if (!$client_id || !$client_secret) {
            return;
        }

        $redirect_uri = admin_url('admin.php?kh_integration=zoom');

        $response = wp_remote_post(self::ZOOM_TOKEN_URL, array(
            'body' => array(
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri,
            ),
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
        ));

        if (is_wp_error($response)) {
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            $settings['zoom']['access_token'] = $body['access_token'];
            $settings['zoom']['refresh_token'] = $body['refresh_token'] ?? '';
            $settings['zoom']['token_expires'] = time() + ($body['expires_in'] ?? 3600);

            update_option('kh_events_integrations_settings', $settings);
        }
    }

    /**
     * Sync event to Zoom
     */
    public function sync_to_zoom($event_id) {
        if (!$this->is_zoom_connected()) {
            return new WP_Error('zoom_not_connected', __('Zoom is not connected', 'kh-events'));
        }

        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'kh_event') {
            return new WP_Error('invalid_event', __('Invalid event', 'kh-events'));
        }

        $start_date = get_post_meta($event_id, '_kh_event_start_date', true);
        $start_time = get_post_meta($event_id, '_kh_event_start_time', true);
        $end_date = get_post_meta($event_id, '_kh_event_end_date', true);
        $end_time = get_post_meta($event_id, '_kh_event_end_time', true);

        if (!$start_date || !$start_time) {
            return new WP_Error('missing_datetime', __('Event date and time are required', 'kh-events'));
        }

        // Combine date and time
        $start_datetime = $start_date . ' ' . $start_time;
        $end_datetime = $end_date && $end_time ? $end_date . ' ' . $end_time : null;

        // Convert to UTC
        $timezone = get_post_meta($event_id, '_kh_event_timezone', true) ?: 'UTC';
        $start_utc = $this->convert_to_utc($start_datetime, $timezone);

        $meeting_data = array(
            'topic' => $event->post_title,
            'agenda' => wp_strip_all_tags($event->post_content),
            'start_time' => $start_utc,
            'duration' => $end_datetime ? $this->calculate_duration($start_datetime, $end_datetime) : 60,
            'timezone' => $timezone,
            'settings' => array(
                'host_video' => true,
                'participant_video' => true,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'watermark' => false,
                'use_pmi' => false,
                'approval_type' => 0, // Automatically approve
                'audio' => 'both', // Both telephone and computer audio
                'auto_recording' => 'none'
            )
        );

        $existing_meeting_id = get_post_meta($event_id, '_kh_zoom_meeting_id', true);

        if ($existing_meeting_id) {
            // Update existing meeting
            $result = $this->update_zoom_meeting($existing_meeting_id, $meeting_data);
        } else {
            // Create new meeting
            $result = $this->create_zoom_meeting($meeting_data);

            if (!is_wp_error($result) && isset($result['id'])) {
                update_post_meta($event_id, '_kh_zoom_meeting_id', $result['id']);
                update_post_meta($event_id, '_kh_zoom_join_url', $result['join_url']);
                update_post_meta($event_id, '_kh_zoom_start_url', $result['start_url']);
            }
        }

        return $result;
    }

    /**
     * Create Zoom meeting
     */
    private function create_zoom_meeting($meeting_data) {
        $settings = get_option('kh_events_integrations_settings', array());
        $access_token = $settings['zoom']['access_token'] ?? '';

        $response = wp_remote_post(self::ZOOM_API_BASE . '/users/me/meetings', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($meeting_data),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) !== 201) {
            return new WP_Error('zoom_api_error', $body['message'] ?? __('Zoom API error', 'kh-events'));
        }

        return $body;
    }

    /**
     * Update Zoom meeting
     */
    private function update_zoom_meeting($meeting_id, $meeting_data) {
        $settings = get_option('kh_events_integrations_settings', array());
        $access_token = $settings['zoom']['access_token'] ?? '';

        $response = wp_remote_request(self::ZOOM_API_BASE . '/meetings/' . $meeting_id, array(
            'method' => 'PATCH',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($meeting_data),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        if (wp_remote_retrieve_response_code($response) !== 204) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return new WP_Error('zoom_api_error', $body['message'] ?? __('Zoom API error', 'kh-events'));
        }

        return true;
    }

    /**
     * Cancel Zoom meeting
     */
    private function cancel_zoom_meeting($meeting_id) {
        $settings = get_option('kh_events_integrations_settings', array());
        $access_token = $settings['zoom']['access_token'] ?? '';

        wp_remote_request(self::ZOOM_API_BASE . '/meetings/' . $meeting_id, array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));
    }

    // ==================== EVENTBRITE INTEGRATION ====================

    /**
     * Check if Eventbrite is connected
     */
    public function is_eventbrite_connected() {
        $settings = get_option('kh_events_integrations_settings', array());
        return !empty($settings['eventbrite']['access_token']);
    }

    /**
     * Get Eventbrite authorization URL
     */
    public function get_eventbrite_auth_url() {
        $settings = get_option('kh_events_integrations_settings', array());
        $client_id = $settings['eventbrite']['client_id'] ?? '';

        if (!$client_id) {
            return false;
        }

        $redirect_uri = admin_url('admin.php?kh_integration=eventbrite');

        $params = array(
            'response_type' => 'code',
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
        );

        return self::EVENTBRITE_AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Handle Eventbrite OAuth callback
     */
    private function handle_eventbrite_oauth_callback($code) {
        $settings = get_option('kh_events_integrations_settings', array());
        $client_id = $settings['eventbrite']['client_id'] ?? '';
        $client_secret = $settings['eventbrite']['client_secret'] ?? '';

        if (!$client_id || !$client_secret) {
            return;
        }

        $redirect_uri = admin_url('admin.php?kh_integration=eventbrite');

        $response = wp_remote_post(self::EVENTBRITE_TOKEN_URL, array(
            'body' => array(
                'grant_type' => 'authorization_code',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'code' => $code,
                'redirect_uri' => $redirect_uri,
            ),
        ));

        if (is_wp_error($response)) {
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            $settings['eventbrite']['access_token'] = $body['access_token'];
            $settings['eventbrite']['token_type'] = $body['token_type'] ?? 'bearer';

            update_option('kh_events_integrations_settings', $settings);
        }
    }

    /**
     * Sync event to Eventbrite
     */
    public function sync_to_eventbrite($event_id) {
        if (!$this->is_eventbrite_connected()) {
            return new WP_Error('eventbrite_not_connected', __('Eventbrite is not connected', 'kh-events'));
        }

        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'kh_event') {
            return new WP_Error('invalid_event', __('Invalid event', 'kh-events'));
        }

        $start_date = get_post_meta($event_id, '_kh_event_start_date', true);
        $start_time = get_post_meta($event_id, '_kh_event_start_time', true);
        $end_date = get_post_meta($event_id, '_kh_event_end_date', true);
        $end_time = get_post_meta($event_id, '_kh_event_end_time', true);
        $location = get_post_meta($event_id, '_kh_event_location', true);

        if (!$start_date || !$start_time) {
            return new WP_Error('missing_datetime', __('Event date and time are required', 'kh-events'));
        }

        // Get Eventbrite user info
        $user_info = $this->get_eventbrite_user_info();
        if (is_wp_error($user_info)) {
            return $user_info;
        }

        $event_data = array(
            'event.name.html' => $event->post_title,
            'event.description.html' => wp_kses_post($event->post_content),
            'event.start.utc' => $this->format_datetime_for_eventbrite($start_date . ' ' . $start_time),
            'event.end.utc' => $this->format_datetime_for_eventbrite(($end_date ?: $start_date) . ' ' . ($end_time ?: $start_time)),
            'event.currency' => 'USD',
            'event.organizer_id' => $user_info['id'],
        );

        if ($location) {
            $event_data['event.venue_id'] = $this->create_or_get_eventbrite_venue($location);
        }

        $existing_event_id = get_post_meta($event_id, '_kh_eventbrite_id', true);

        if ($existing_event_id) {
            // Update existing event
            $result = $this->update_eventbrite_event($existing_event_id, $event_data);
        } else {
            // Create new event
            $result = $this->create_eventbrite_event($event_data);

            if (!is_wp_error($result) && isset($result['id'])) {
                update_post_meta($event_id, '_kh_eventbrite_id', $result['id']);
                update_post_meta($event_id, '_kh_eventbrite_url', $result['url']);
            }
        }

        return $result;
    }

    /**
     * Create Eventbrite event
     */
    private function create_eventbrite_event($event_data) {
        $settings = get_option('kh_events_integrations_settings', array());
        $access_token = $settings['eventbrite']['access_token'] ?? '';

        $response = wp_remote_post(self::EVENTBRITE_API_BASE . '/events/', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($event_data),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('eventbrite_api_error', $body['error_description'] ?? __('Eventbrite API error', 'kh-events'));
        }

        return $body;
    }

    /**
     * Update Eventbrite event
     */
    private function update_eventbrite_event($event_id, $event_data) {
        $settings = get_option('kh_events_integrations_settings', array());
        $access_token = $settings['eventbrite']['access_token'] ?? '';

        $response = wp_remote_post(self::EVENTBRITE_API_BASE . '/events/' . $event_id . '/', array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($event_data),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('eventbrite_api_error', $body['error_description'] ?? __('Eventbrite API error', 'kh-events'));
        }

        return $body;
    }

    /**
     * Cancel Eventbrite event
     */
    private function cancel_eventbrite_event($event_id) {
        $settings = get_option('kh_events_integrations_settings', array());
        $access_token = $settings['eventbrite']['access_token'] ?? '';

        wp_remote_post(self::EVENTBRITE_API_BASE . '/events/' . $event_id . '/cancel/', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));
    }

    /**
     * Get Eventbrite user info
     */
    private function get_eventbrite_user_info() {
        $settings = get_option('kh_events_integrations_settings', array());
        $access_token = $settings['eventbrite']['access_token'] ?? '';

        $response = wp_remote_get(self::EVENTBRITE_API_BASE . '/users/me/', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('eventbrite_api_error', __('Failed to get user info', 'kh-events'));
        }

        return $body;
    }

    // ==================== FACEBOOK INTEGRATION ====================

    /**
     * Check if Facebook is connected
     */
    public function is_facebook_connected() {
        $settings = get_option('kh_events_integrations_settings', array());
        return !empty($settings['facebook']['access_token']) &&
               !empty($settings['facebook']['page_id']);
    }

    /**
     * Get Facebook authorization URL
     */
    public function get_facebook_auth_url() {
        $settings = get_option('kh_events_integrations_settings', array());
        $app_id = $settings['facebook']['app_id'] ?? '';

        if (!$app_id) {
            return false;
        }

        $redirect_uri = admin_url('admin.php?kh_integration=facebook');

        $params = array(
            'client_id' => $app_id,
            'redirect_uri' => $redirect_uri,
            'scope' => 'pages_manage_posts,pages_read_engagement,pages_show_list,events',
            'response_type' => 'code',
            'state' => wp_create_nonce('kh_facebook_oauth'),
        );

        return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);
    }

    /**
     * Handle Facebook OAuth callback
     */
    private function handle_facebook_oauth_callback($code) {
        $settings = get_option('kh_events_integrations_settings', array());
        $app_id = $settings['facebook']['app_id'] ?? '';
        $app_secret = $settings['facebook']['app_secret'] ?? '';

        if (!$app_id || !$app_secret) {
            return;
        }

        $redirect_uri = admin_url('admin.php?kh_integration=facebook');

        // Exchange code for access token
        $token_url = self::FACEBOOK_GRAPH_API . '/oauth/access_token?' . http_build_query(array(
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'redirect_uri' => $redirect_uri,
            'code' => $code,
        ));

        $response = wp_remote_get($token_url);

        if (is_wp_error($response)) {
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            $settings['facebook']['access_token'] = $body['access_token'];

            // Get user's pages
            $pages = $this->get_facebook_pages($body['access_token']);
            if (!is_wp_error($pages) && !empty($pages)) {
                $settings['facebook']['pages'] = $pages;
                // Set first page as default if not set
                if (empty($settings['facebook']['page_id'])) {
                    $settings['facebook']['page_id'] = $pages[0]['id'];
                }
            }

            update_option('kh_events_integrations_settings', $settings);
        }
    }

    /**
     * Get Facebook pages
     */
    private function get_facebook_pages($access_token) {
        $response = wp_remote_get(self::FACEBOOK_GRAPH_API . '/me/accounts', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['data'])) {
            return $body['data'];
        }

        return array();
    }

    /**
     * Sync event to Facebook
     */
    public function sync_to_facebook($event_id) {
        if (!$this->is_facebook_connected()) {
            return new WP_Error('facebook_not_connected', __('Facebook is not connected', 'kh-events'));
        }

        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'kh_event') {
            return new WP_Error('invalid_event', __('Invalid event', 'kh-events'));
        }

        $settings = get_option('kh_events_integrations_settings', array());
        $page_id = $settings['facebook']['page_id'] ?? '';
        $access_token = $settings['facebook']['access_token'] ?? '';

        if (!$page_id) {
            return new WP_Error('no_page_selected', __('No Facebook page selected', 'kh-events'));
        }

        $start_date = get_post_meta($event_id, '_kh_event_start_date', true);
        $start_time = get_post_meta($event_id, '_kh_event_start_time', true);
        $location = get_post_meta($event_id, '_kh_event_location', true);

        if (!$start_date || !$start_time) {
            return new WP_Error('missing_datetime', __('Event date and time are required', 'kh-events'));
        }

        $start_datetime = $start_date . ' ' . $start_time;

        $event_data = array(
            'name' => $event->post_title,
            'description' => wp_strip_all_tags($event->post_content),
            'start_time' => $this->format_datetime_for_facebook($start_datetime),
            'event_times' => array(
                array(
                    'start_time' => $this->format_datetime_for_facebook($start_datetime),
                    'end_time' => $this->format_datetime_for_facebook($start_datetime, '+1 hour'), // Default 1 hour
                )
            ),
        );

        if ($location) {
            $event_data['place'] = array(
                'name' => $location,
            );
        }

        $existing_event_id = get_post_meta($event_id, '_kh_facebook_event_id', true);

        if ($existing_event_id) {
            // Update existing event
            $result = $this->update_facebook_event($page_id, $existing_event_id, $event_data, $access_token);
        } else {
            // Create new event
            $result = $this->create_facebook_event($page_id, $event_data, $access_token);

            if (!is_wp_error($result) && isset($result['id'])) {
                update_post_meta($event_id, '_kh_facebook_event_id', $result['id']);
            }
        }

        return $result;
    }

    /**
     * Create Facebook event
     */
    private function create_facebook_event($page_id, $event_data, $access_token) {
        $response = wp_remote_post(self::FACEBOOK_GRAPH_API . '/' . $page_id . '/events', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($event_data),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('facebook_api_error', $body['error']['message'] ?? __('Facebook API error', 'kh-events'));
        }

        return $body;
    }

    /**
     * Update Facebook event
     */
    private function update_facebook_event($page_id, $event_id, $event_data, $access_token) {
        $response = wp_remote_post(self::FACEBOOK_GRAPH_API . '/' . $event_id, array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($event_data),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('facebook_api_error', $body['error']['message'] ?? __('Facebook API error', 'kh-events'));
        }

        return $body;
    }

    /**
     * Cancel Facebook event
     */
    private function cancel_facebook_event($event_id) {
        $settings = get_option('kh_events_integrations_settings', array());
        $access_token = $settings['facebook']['access_token'] ?? '';

        wp_remote_post(self::FACEBOOK_GRAPH_API . '/' . $event_id, array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));
    }

    // ==================== AJAX HANDLERS ====================

    /**
     * AJAX: Sync event to Zoom
     */
    public function ajax_sync_event_to_zoom() {
        check_ajax_referer('kh_sync_zoom', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'kh-events')));
        }

        $event_id = intval($_POST['event_id']);

        $result = $this->sync_to_zoom($event_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array('message' => __('Event synced to Zoom successfully', 'kh-events')));
        }
    }

    /**
     * AJAX: Sync event to Eventbrite
     */
    public function ajax_sync_event_to_eventbrite() {
        check_ajax_referer('kh_sync_eventbrite', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'kh-events')));
        }

        $event_id = intval($_POST['event_id']);

        $result = $this->sync_to_eventbrite($event_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array('message' => __('Event synced to Eventbrite successfully', 'kh-events')));
        }
    }

    /**
     * AJAX: Sync event to Facebook
     */
    public function ajax_sync_event_to_facebook() {
        check_ajax_referer('kh_sync_facebook', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'kh-events')));
        }

        $event_id = intval($_POST['event_id']);

        $result = $this->sync_to_facebook($event_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array('message' => __('Event synced to Facebook successfully', 'kh-events')));
        }
    }

    /**
     * AJAX: Refresh integration tokens
     */
    public function ajax_refresh_integration_tokens() {
        check_ajax_referer('kh_refresh_tokens', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'kh-events')));
        }

        $integration = sanitize_text_field($_POST['integration']);
        $result = $this->refresh_token($integration);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array('message' => __('Token refreshed successfully', 'kh-events')));
        }
    }

    // ==================== UTILITY METHODS ====================

    /**
     * Convert datetime to UTC
     */
    private function convert_to_utc($datetime, $timezone) {
        $dt = new DateTime($datetime, new DateTimeZone($timezone));
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Calculate duration in minutes
     */
    private function calculate_duration($start, $end) {
        $start_dt = new DateTime($start);
        $end_dt = new DateTime($end);
        $interval = $start_dt->diff($end_dt);
        return ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
    }

    /**
     * Format datetime for Eventbrite
     */
    private function format_datetime_for_eventbrite($datetime) {
        $dt = new DateTime($datetime);
        return $dt->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Format datetime for Facebook
     */
    private function format_datetime_for_facebook($datetime, $modifier = '') {
        $dt = new DateTime($datetime);
        if ($modifier) {
            $dt->modify($modifier);
        }
        return $dt->format('Y-m-d\TH:i:s');
    }

    /**
     * Create or get Eventbrite venue
     */
    private function create_or_get_eventbrite_venue($location) {
        // This is a simplified implementation
        // In a real implementation, you'd search for existing venues first
        return null;
    }

    /**
     * Refresh access token
     */
    private function refresh_token($integration) {
        $settings = get_option('kh_events_integrations_settings', array());

        switch ($integration) {
            case self::INTEGRATION_ZOOM:
                if (empty($settings['zoom']['refresh_token'])) {
                    return new WP_Error('no_refresh_token', __('No refresh token available', 'kh-events'));
                }

                $client_id = $settings['zoom']['client_id'] ?? '';
                $client_secret = $settings['zoom']['client_secret'] ?? '';
                $refresh_token = $settings['zoom']['refresh_token'];

                $response = wp_remote_post(self::ZOOM_TOKEN_URL, array(
                    'body' => array(
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $refresh_token,
                    ),
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ),
                ));

                if (is_wp_error($response)) {
                    return $response;
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);

                if (isset($body['access_token'])) {
                    $settings['zoom']['access_token'] = $body['access_token'];
                    $settings['zoom']['refresh_token'] = $body['refresh_token'] ?? $refresh_token;
                    $settings['zoom']['token_expires'] = time() + ($body['expires_in'] ?? 3600);

                    update_option('kh_events_integrations_settings', $settings);
                    return true;
                }

                return new WP_Error('token_refresh_failed', __('Failed to refresh Zoom token', 'kh-events'));

            default:
                return new WP_Error('unsupported_integration', __('Token refresh not supported for this integration', 'kh-events'));
        }
    }

    // ==================== CRON SYNC METHODS ====================

    /**
     * Sync Zoom meetings (cron)
     */
    public function sync_zoom_meetings() {
        // Implementation for syncing meeting updates from Zoom
        // This would check for meeting changes and update KH Events accordingly
    }

    /**
     * Sync Eventbrite events (cron)
     */
    public function sync_eventbrite_events() {
        // Implementation for syncing event updates from Eventbrite
        // This would check for event changes and update KH Events accordingly
    }

    /**
     * Sync Facebook events (cron)
     */
    public function sync_facebook_events() {
        // Implementation for syncing event updates from Facebook
        // This would check for event changes and update KH Events accordingly
    }
}