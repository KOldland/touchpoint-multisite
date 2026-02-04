<?php
/**
 * KH Events Multi-Timezone Support
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Event_Timezone {

    private static $instance = null;

    // Common timezone identifiers
    private static $common_timezones = array(
        'America/New_York' => 'Eastern Time',
        'America/Chicago' => 'Central Time',
        'America/Denver' => 'Mountain Time',
        'America/Los_Angeles' => 'Pacific Time',
        'America/Anchorage' => 'Alaska Time',
        'Pacific/Honolulu' => 'Hawaii Time',
        'Europe/London' => 'GMT/BST',
        'Europe/Paris' => 'CET/CEST',
        'Europe/Berlin' => 'CET/CEST',
        'Europe/Rome' => 'CET/CEST',
        'Europe/Madrid' => 'CET/CEST',
        'Europe/Amsterdam' => 'CET/CEST',
        'Europe/Zurich' => 'CET/CEST',
        'Europe/Moscow' => 'MSK',
        'Asia/Dubai' => 'GST',
        'Asia/Kolkata' => 'IST',
        'Asia/Shanghai' => 'CST',
        'Asia/Tokyo' => 'JST',
        'Asia/Seoul' => 'KST',
        'Asia/Singapore' => 'SGT',
        'Australia/Sydney' => 'AEST/AEDT',
        'Australia/Melbourne' => 'AEST/AEDT',
        'Australia/Perth' => 'AWST',
        'Pacific/Auckland' => 'NZST/NZDT',
    );

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
        add_action('init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));

        // Meta boxes
        add_action('add_meta_boxes', array($this, 'add_timezone_meta_box'));

        // Save meta
        add_action('save_post_kh_event', array($this, 'save_event_timezone_meta'));

        // Admin settings
        add_action('admin_init', array($this, 'register_timezone_settings'));

        // AJAX handlers
        add_action('wp_ajax_kh_get_timezone_info', array($this, 'ajax_get_timezone_info'));
        add_action('wp_ajax_nopriv_kh_get_timezone_info', array($this, 'ajax_get_timezone_info'));
        add_action('wp_ajax_kh_convert_timezone', array($this, 'ajax_convert_timezone'));
        add_action('wp_ajax_nopriv_kh_convert_timezone', array($this, 'ajax_convert_timezone'));

        // Filters for displaying times
        add_filter('kh_event_display_time', array($this, 'filter_display_time'), 10, 3);
        add_filter('kh_event_datetime_format', array($this, 'filter_datetime_format'), 10, 2);

        // REST API integration
        add_filter('rest_prepare_kh_event', array($this, 'add_timezone_to_rest_response'), 10, 3);
    }

    public function init() {
        // Set default timezone if not set
        if (!get_option('kh_events_default_timezone')) {
            update_option('kh_events_default_timezone', wp_timezone_string());
        }
    }

    /**
     * Get user's preferred timezone
     */
    public function get_user_timezone($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if ($user_id) {
            $user_timezone = get_user_meta($user_id, 'kh_events_timezone', true);
            if ($user_timezone) {
                return $user_timezone;
            }
        }

        // Fallback to site default
        return get_option('kh_events_default_timezone', wp_timezone_string());
    }

    /**
     * Set user's preferred timezone
     */
    public function set_user_timezone($timezone, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if ($user_id) {
            update_user_meta($user_id, 'kh_events_timezone', $timezone);
            return true;
        }

        return false;
    }

    /**
     * Get event timezone
     */
    public function get_event_timezone($event_id) {
        $timezone = get_post_meta($event_id, '_kh_event_timezone', true);
        return $timezone ?: get_option('kh_events_default_timezone', wp_timezone_string());
    }

    /**
     * Set event timezone
     */
    public function set_event_timezone($event_id, $timezone) {
        if ($this->is_valid_timezone($timezone)) {
            update_post_meta($event_id, '_kh_event_timezone', $timezone);
            return true;
        }
        return false;
    }

    /**
     * Validate timezone identifier
     */
    public function is_valid_timezone($timezone) {
        try {
            new DateTimeZone($timezone);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Convert datetime between timezones
     */
    public function convert_datetime($datetime, $from_timezone, $to_timezone) {
        try {
            $dt = new DateTime($datetime, new DateTimeZone($from_timezone));
            $dt->setTimezone(new DateTimeZone($to_timezone));
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return $datetime; // Return original on error
        }
    }

    /**
     * Format datetime for display in user's timezone
     */
    public function format_datetime_for_user($datetime, $event_timezone, $user_id = null, $format = null) {
        $user_timezone = $this->get_user_timezone($user_id);

        if ($event_timezone === $user_timezone) {
            // No conversion needed
            $dt = new DateTime($datetime);
        } else {
            // Convert to user's timezone
            $dt = new DateTime($datetime, new DateTimeZone($event_timezone));
            $dt->setTimezone(new DateTimeZone($user_timezone));
        }

        $format = $format ?: get_option('date_format') . ' ' . get_option('time_format');
        return $dt->format($format);
    }

    /**
     * Get timezone offset in hours
     */
    public function get_timezone_offset($timezone, $datetime = null) {
        try {
            $dt = new DateTime($datetime ?: 'now', new DateTimeZone($timezone));
            return $dt->getOffset() / 3600;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get timezone abbreviation
     */
    public function get_timezone_abbr($timezone, $datetime = null) {
        try {
            $dt = new DateTime($datetime ?: 'now', new DateTimeZone($timezone));
            return $dt->format('T');
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Get all available timezones
     */
    public function get_available_timezones() {
        $timezones = array();

        // Add common timezones first
        foreach (self::$common_timezones as $tz => $name) {
            $timezones[$tz] = $name;
        }

        // Add all PHP timezones
        $all_timezones = DateTimeZone::listIdentifiers();
        foreach ($all_timezones as $tz) {
            if (!isset($timezones[$tz])) {
                $timezones[$tz] = str_replace('_', ' ', $tz);
            }
        }

        return $timezones;
    }

    /**
     * Detect user's timezone from browser
     */
    public function detect_user_timezone() {
        // This would be enhanced with JavaScript geolocation
        // For now, return WordPress timezone
        return wp_timezone_string();
    }

    /**
     * Add timezone meta box to event edit screen
     */
    public function add_timezone_meta_box() {
        add_meta_box(
            'kh-event-timezone',
            __('Event Timezone', 'kh-events'),
            array($this, 'render_timezone_meta_box'),
            'kh_event',
            'side',
            'default'
        );
    }

    /**
     * Render timezone meta box
     */
    public function render_timezone_meta_box($post) {
        wp_nonce_field('kh_event_timezone_meta', 'kh_event_timezone_nonce');

        $current_timezone = $this->get_event_timezone($post->ID);
        $available_timezones = $this->get_available_timezones();

        echo '<p>';
        echo '<label for="kh_event_timezone">' . __('Timezone:', 'kh-events') . '</label><br>';
        echo '<select name="kh_event_timezone" id="kh_event_timezone" style="width: 100%;">';

        foreach ($available_timezones as $tz => $name) {
            $selected = ($tz === $current_timezone) ? ' selected="selected"' : '';
            $offset = $this->get_timezone_offset($tz);
            $offset_str = sprintf(' (UTC%+d)', $offset);
            echo '<option value="' . esc_attr($tz) . '"' . $selected . '>' . esc_html($name . $offset_str) . '</option>';
        }

        echo '</select>';
        echo '</p>';

        // Show current time in selected timezone
        echo '<div id="timezone-preview" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">';
        echo '<strong>' . __('Preview:', 'kh-events') . '</strong><br>';
        echo '<span id="timezone-current-time"></span>';
        echo '</div>';

        // JavaScript for live preview
        ?>
        <script>
        jQuery(document).ready(function($) {
            function updateTimezonePreview() {
                var timezone = $('#kh_event_timezone').val();
                if (timezone) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kh_get_timezone_info',
                            timezone: timezone,
                            nonce: '<?php echo wp_create_nonce('kh_timezone_info'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#timezone-current-time').text(response.data.current_time + ' ' + response.data.abbr);
                            }
                        }
                    });
                }
            }

            $('#kh_event_timezone').on('change', updateTimezonePreview);
            updateTimezonePreview(); // Initial load
        });
        </script>
        <?php
    }

    /**
     * Save event timezone meta
     */
    public function save_event_timezone_meta($post_id) {
        if (!isset($_POST['kh_event_timezone_nonce']) ||
            !wp_verify_nonce($_POST['kh_event_timezone_nonce'], 'kh_event_timezone_meta')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['kh_event_timezone'])) {
            $timezone = sanitize_text_field($_POST['kh_event_timezone']);
            if ($this->is_valid_timezone($timezone)) {
                $this->set_event_timezone($post_id, $timezone);
            }
        }
    }

    /**
     * Register timezone settings
     */
    public function register_timezone_settings() {
        register_setting('kh_events_settings', 'kh_events_default_timezone');
        register_setting('kh_events_settings', 'kh_events_enable_user_timezones');
        register_setting('kh_events_settings', 'kh_events_show_timezone_labels');

        add_settings_section(
            'kh_timezone_settings',
            __('Timezone Settings', 'kh-events'),
            array($this, 'timezone_settings_section_callback'),
            'kh-events-settings'
        );

        add_settings_field(
            'kh_events_default_timezone',
            __('Default Event Timezone', 'kh-events'),
            array($this, 'default_timezone_field_callback'),
            'kh-events-settings',
            'kh_timezone_settings'
        );

        add_settings_field(
            'kh_events_enable_user_timezones',
            __('Enable User Timezone Preferences', 'kh-events'),
            array($this, 'enable_user_timezones_field_callback'),
            'kh-events-settings',
            'kh_timezone_settings'
        );

        add_settings_field(
            'kh_events_show_timezone_labels',
            __('Show Timezone Labels', 'kh-events'),
            array($this, 'show_timezone_labels_field_callback'),
            'kh-events-settings',
            'kh_timezone_settings'
        );
    }

    /**
     * Settings section callback
     */
    public function timezone_settings_section_callback() {
        echo '<p>' . __('Configure timezone settings for events and users.', 'kh-events') . '</p>';
    }

    /**
     * Default timezone field
     */
    public function default_timezone_field_callback() {
        $timezone = get_option('kh_events_default_timezone', wp_timezone_string());
        $available_timezones = $this->get_available_timezones();

        echo '<select name="kh_events_default_timezone" style="width: 300px;">';
        foreach ($available_timezones as $tz => $name) {
            $selected = ($tz === $timezone) ? ' selected="selected"' : '';
            $offset = $this->get_timezone_offset($tz);
            $offset_str = sprintf(' (UTC%+d)', $offset);
            echo '<option value="' . esc_attr($tz) . '"' . $selected . '>' . esc_html($name . $offset_str) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Default timezone for new events.', 'kh-events') . '</p>';
    }

    /**
     * Enable user timezones field
     */
    public function enable_user_timezones_field_callback() {
        $enabled = get_option('kh_events_enable_user_timezones', '1');
        echo '<input type="checkbox" name="kh_events_enable_user_timezones" value="1"' . checked(1, $enabled, false) . ' />';
        echo '<span class="description">' . __('Allow users to set their preferred timezone.', 'kh-events') . '</span>';
    }

    /**
     * Show timezone labels field
     */
    public function show_timezone_labels_field_callback() {
        $enabled = get_option('kh_events_show_timezone_labels', '1');
        echo '<input type="checkbox" name="kh_events_show_timezone_labels" value="1"' . checked(1, $enabled, false) . ' />';
        echo '<span class="description">' . __('Display timezone information on event pages.', 'kh-events') . '</span>';
    }

    /**
     * AJAX get timezone info
     */
    public function ajax_get_timezone_info() {
        check_ajax_referer('kh_timezone_info', 'nonce');

        $timezone = sanitize_text_field($_POST['timezone']);

        if (!$this->is_valid_timezone($timezone)) {
            wp_send_json_error('Invalid timezone');
        }

        $current_time = new DateTime('now', new DateTimeZone($timezone));
        $abbr = $current_time->format('T');

        wp_send_json_success(array(
            'current_time' => $current_time->format('Y-m-d H:i:s'),
            'abbr' => $abbr,
            'offset' => $this->get_timezone_offset($timezone)
        ));
    }

    /**
     * AJAX convert timezone
     */
    public function ajax_convert_timezone() {
        $datetime = sanitize_text_field($_POST['datetime']);
        $from_timezone = sanitize_text_field($_POST['from_timezone']);
        $to_timezone = sanitize_text_field($_POST['to_timezone']);

        $converted = $this->convert_datetime($datetime, $from_timezone, $to_timezone);

        wp_send_json_success(array(
            'original' => $datetime,
            'converted' => $converted,
            'from_timezone' => $from_timezone,
            'to_timezone' => $to_timezone
        ));
    }

    /**
     * Filter display time
     */
    public function filter_display_time($time_string, $event_id, $user_id = null) {
        $event_timezone = $this->get_event_timezone($event_id);
        return $this->format_datetime_for_user($time_string, $event_timezone, $user_id);
    }

    /**
     * Filter datetime format
     */
    public function filter_datetime_format($format, $event_id) {
        if (get_option('kh_events_show_timezone_labels')) {
            $event_timezone = $this->get_event_timezone($event_id);
            $abbr = $this->get_timezone_abbr($event_timezone);
            $format .= ' (' . $abbr . ')';
        }
        return $format;
    }

    /**
     * Add timezone to REST response
     */
    public function add_timezone_to_rest_response($response, $post, $request) {
        if ($post->post_type === 'kh_event') {
            $data = $response->get_data();
            $data['timezone'] = $this->get_event_timezone($post->ID);
            $data['timezone_offset'] = $this->get_timezone_offset($data['timezone']);
            $data['timezone_abbr'] = $this->get_timezone_abbr($data['timezone']);
            $response->set_data($data);
        }
        return $response;
    }

    /**
     * Admin enqueue scripts
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'kh_event') !== false || strpos($hook, 'kh-events') !== false) {
            wp_enqueue_script('kh-timezone-admin', KH_EVENTS_URL . 'assets/js/timezone-admin.js', array('jquery'), KH_EVENTS_VERSION, true);
            wp_localize_script('kh-timezone-admin', 'kh_timezone_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kh_timezone_info')
            ));
        }
    }

    /**
     * Frontend enqueue scripts
     */
    public function frontend_enqueue_scripts() {
        if ( function_exists( 'kh_events_is_builder_preview' ) && kh_events_is_builder_preview() ) {
            return;
        }
        if (is_singular('kh_event') || is_post_type_archive('kh_event')) {
            wp_enqueue_script('kh-timezone-frontend', KH_EVENTS_URL . 'assets/js/timezone-frontend.js', array('jquery'), KH_EVENTS_VERSION, true);
            wp_localize_script('kh-timezone-frontend', 'kh_timezone_vars', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'user_timezone' => $this->get_user_timezone(),
                'default_timezone' => get_option('kh_events_default_timezone'),
                'enable_user_timezones' => get_option('kh_events_enable_user_timezones'),
                'nonce' => wp_create_nonce('kh_timezone_convert')
            ));
        }
    }

    /**
     * Get timezone select HTML
     */
    public function get_timezone_select_html($selected = '', $name = 'timezone', $id = '') {
        $available_timezones = $this->get_available_timezones();
        $selected = $selected ?: $this->get_user_timezone();

        $html = '<select name="' . esc_attr($name) . '"';
        if ($id) {
            $html .= ' id="' . esc_attr($id) . '"';
        }
        $html .= ' style="width: 100%;">';

        foreach ($available_timezones as $tz => $label) {
            $offset = $this->get_timezone_offset($tz);
            $offset_str = sprintf(' (UTC%+d)', $offset);
            $is_selected = ($tz === $selected) ? ' selected="selected"' : '';
            $html .= '<option value="' . esc_attr($tz) . '"' . $is_selected . '>' . esc_html($label . $offset_str) . '</option>';
        }

        $html .= '</select>';
        return $html;
    }
}
