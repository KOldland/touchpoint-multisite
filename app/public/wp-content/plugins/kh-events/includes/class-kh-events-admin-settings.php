<?php
/**
 * KH Events Admin Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Admin_Settings {

    private static $instance = null;
    private $settings_page = 'kh-events-settings';

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function add_settings_page() {
        add_submenu_page(
            'kh-events',
            __('Settings', 'kh-events'),
            __('Settings', 'kh-events'),
            'manage_options',
            $this->settings_page,
            array($this, 'settings_page_callback')
        );
    }

    public function register_settings() {
        // General Settings
        register_setting('kh_events_general', 'kh_events_general_settings', array($this, 'sanitize_general_settings'));

        add_settings_section(
            'kh_events_general_section',
            __('General Settings', 'kh-events'),
            array($this, 'general_section_callback'),
            'kh_events_general'
        );

        add_settings_field(
            'kh_events_currency',
            __('Currency', 'kh-events'),
            array($this, 'currency_field_callback'),
            'kh_events_general',
            'kh_events_general_section'
        );

        add_settings_field(
            'kh_events_date_format',
            __('Date Format', 'kh-events'),
            array($this, 'date_format_field_callback'),
            'kh_events_general',
            'kh_events_general_section'
        );

        add_settings_field(
            'kh_events_time_format',
            __('Time Format', 'kh-events'),
            array($this, 'time_format_field_callback'),
            'kh_events_general',
            'kh_events_general_section'
        );

        // Google Maps Settings
        register_setting('kh_events_maps', 'kh_events_maps_settings', array($this, 'sanitize_maps_settings'));

        add_settings_section(
            'kh_events_maps_section',
            __('Google Maps Settings', 'kh-events'),
            array($this, 'maps_section_callback'),
            'kh_events_maps'
        );

        add_settings_field(
            'kh_events_google_maps_api_key',
            __('Google Maps API Key', 'kh-events'),
            array($this, 'google_maps_api_key_field_callback'),
            'kh_events_maps',
            'kh_events_maps_section'
        );

        add_settings_field(
            'kh_events_default_map_zoom',
            __('Default Map Zoom', 'kh-events'),
            array($this, 'default_map_zoom_field_callback'),
            'kh_events_maps',
            'kh_events_maps_section'
        );

        // Email Settings
        register_setting('kh_events_email', 'kh_events_email_settings', array($this, 'sanitize_email_settings'));

        add_settings_section(
            'kh_events_email_section',
            __('Email Settings', 'kh-events'),
            array($this, 'email_section_callback'),
            'kh_events_email'
        );

        add_settings_field(
            'kh_events_from_email',
            __('From Email', 'kh-events'),
            array($this, 'from_email_field_callback'),
            'kh_events_email',
            'kh_events_email_section'
        );

        add_settings_field(
            'kh_events_from_name',
            __('From Name', 'kh-events'),
            array($this, 'from_name_field_callback'),
            'kh_events_email',
            'kh_events_email_section'
        );

        add_settings_field(
            'kh_events_booking_confirmation',
            __('Booking Confirmation Email', 'kh-events'),
            array($this, 'booking_confirmation_field_callback'),
            'kh_events_email',
            'kh_events_email_section'
        );

        // Booking Settings
        register_setting('kh_events_booking', 'kh_events_booking_settings', array($this, 'sanitize_booking_settings'));

        add_settings_section(
            'kh_events_booking_section',
            __('Booking Settings', 'kh-events'),
            array($this, 'booking_section_callback'),
            'kh_events_booking'
        );

        add_settings_field(
            'kh_events_allow_guest_bookings',
            __('Allow Guest Bookings', 'kh-events'),
            array($this, 'allow_guest_bookings_field_callback'),
            'kh_events_booking',
            'kh_events_booking_section'
        );

        add_settings_field(
            'kh_events_booking_cutoff_hours',
            __('Booking Cutoff (hours before event)', 'kh-events'),
            array($this, 'booking_cutoff_field_callback'),
            'kh_events_booking',
            'kh_events_booking_section'
        );

        // Display Settings
        register_setting('kh_events_display', 'kh_events_display_settings', array($this, 'sanitize_display_settings'));

        add_settings_section(
            'kh_events_display_section',
            __('Display Settings', 'kh-events'),
            array($this, 'display_section_callback'),
            'kh_events_display'
        );

        add_settings_field(
            'kh_events_events_per_page',
            __('Events Per Page (List View)', 'kh-events'),
            array($this, 'events_per_page_field_callback'),
            'kh_events_display',
            'kh_events_display_section'
        );

        // Payment Settings
        register_setting('kh_events_payment', 'kh_events_payment_settings', array($this, 'sanitize_payment_settings'));

        add_settings_section(
            'kh_events_payment_section',
            __('Payment Settings', 'kh-events'),
            array($this, 'payment_section_callback'),
            'kh_events_payment'
        );

        add_settings_field(
            'kh_events_enable_payments',
            __('Enable Payments', 'kh-events'),
            array($this, 'enable_payments_field_callback'),
            'kh_events_payment',
            'kh_events_payment_section'
        );

        add_settings_field(
            'kh_events_default_gateway',
            __('Default Gateway', 'kh-events'),
            array($this, 'default_gateway_field_callback'),
            'kh_events_payment',
            'kh_events_payment_section'
        );

        add_settings_field(
            'kh_events_payment_description',
            __('Payment Description', 'kh-events'),
            array($this, 'payment_description_field_callback'),
            'kh_events_payment',
            'kh_events_payment_section'
        );

        // Permissions Settings
        register_setting('kh_events_permissions', 'kh_events_permissions_settings', array($this, 'sanitize_permissions_settings'));

        add_settings_section(
            'kh_events_permissions_section',
            __('Advanced Permissions Settings', 'kh-events'),
            array($this, 'permissions_section_callback'),
            'kh_events_permissions'
        );

        add_settings_field(
            'kh_events_enable_advanced_permissions',
            __('Enable Advanced Permissions', 'kh-events'),
            array($this, 'enable_advanced_permissions_field_callback'),
            'kh_events_permissions',
            'kh_events_permissions_section'
        );

        add_settings_field(
            'kh_events_default_user_group',
            __('Default User Group', 'kh-events'),
            array($this, 'default_user_group_field_callback'),
            'kh_events_permissions',
            'kh_events_permissions_section'
        );

        add_settings_field(
            'kh_events_group_permissions',
            __('Group Permissions', 'kh-events'),
            array($this, 'group_permissions_field_callback'),
            'kh_events_permissions',
            'kh_events_permissions_section'
        );

        // Integrations Settings
        register_setting('kh_events_integrations', 'kh_events_integrations_settings', array($this, 'sanitize_integrations_settings'));

        add_settings_section(
            'kh_events_integrations_zoom_section',
            __('Zoom Integration', 'kh-events'),
            array($this, 'zoom_section_callback'),
            'kh_events_integrations'
        );

        add_settings_field(
            'kh_events_zoom_client_id',
            __('Zoom Client ID', 'kh-events'),
            array($this, 'zoom_client_id_field_callback'),
            'kh_events_integrations',
            'kh_events_integrations_zoom_section'
        );

        add_settings_field(
            'kh_events_zoom_client_secret',
            __('Zoom Client Secret', 'kh-events'),
            array($this, 'zoom_client_secret_field_callback'),
            'kh_events_integrations',
            'kh_events_integrations_zoom_section'
        );

        add_settings_field(
            'kh_events_zoom_connection',
            __('Zoom Connection', 'kh-events'),
            array($this, 'zoom_connection_field_callback'),
            'kh_events_integrations',
            'kh_events_integrations_zoom_section'
        );

        add_settings_section(
            'kh_events_integrations_eventbrite_section',
            __('Eventbrite Integration', 'kh-events'),
            array($this, 'eventbrite_section_callback'),
            'kh_events_integrations'
        );

        add_settings_field(
            'kh_events_eventbrite_client_id',
            __('Eventbrite Client ID', 'kh-events'),
            array($this, 'eventbrite_client_id_field_callback'),
            'kh_events_integrations',
            'kh_events_integrations_eventbrite_section'
        );

        add_settings_field(
            'kh_events_eventbrite_client_secret',
            __('Eventbrite Client Secret', 'kh-events'),
            array($this, 'eventbrite_client_secret_field_callback'),
            'kh_events_integrations',
            'kh_events_integrations_eventbrite_section'
        );

        add_settings_field(
            'kh_events_eventbrite_connection',
            __('Eventbrite Connection', 'kh-events'),
            array($this, 'eventbrite_connection_field_callback'),
            'kh_events_integrations',
            'kh_events_integrations_eventbrite_section'
        );

        add_settings_section(
            'kh_events_integrations_facebook_section',
            __('Facebook Integration', 'kh-events'),
            array($this, 'facebook_section_callback'),
            'kh_events_integrations'
        );

        add_settings_field(
            'kh_events_facebook_app_id',
            __('Facebook App ID', 'kh-events'),
            array($this, 'facebook_app_id_field_callback'),
            'kh_events_integrations',
            'kh_events_integrations_facebook_section'
        );

        add_settings_field(
            'kh_events_facebook_app_secret',
            __('Facebook App Secret', 'kh-events'),
            array($this, 'facebook_app_secret_field_callback'),
            'kh_events_integrations',
            'kh_events_integrations_facebook_section'
        );

        add_settings_field(
            'kh_events_facebook_connection',
            __('Facebook Connection', 'kh-events'),
            array($this, 'facebook_connection_field_callback'),
            'kh_events_integrations',
            'kh_events_integrations_facebook_section'
        );

        // GDPR Settings
        register_setting('kh_events_gdpr', 'kh_events_gdpr_settings', array($this, 'sanitize_gdpr_settings'));

        add_settings_section(
            'kh_events_gdpr_section',
            __('GDPR Compliance Settings', 'kh-events'),
            array($this, 'gdpr_section_callback'),
            'kh_events_gdpr'
        );

        // Analytics Settings
        register_setting('kh_events_analytics', 'kh_events_analytics_settings', array($this, 'sanitize_analytics_settings'));

        add_settings_section(
            'kh_events_analytics_general_section',
            __('Analytics Configuration', 'kh-events'),
            array($this, 'analytics_section_callback'),
            'kh_events_analytics'
        );

        add_settings_field(
            'kh_events_enable_analytics',
            __('Enable Analytics', 'kh-events'),
            array($this, 'enable_analytics_field_callback'),
            'kh_events_analytics',
            'kh_events_analytics_general_section'
        );

        add_settings_field(
            'kh_events_analytics_retention',
            __('Data Retention (Days)', 'kh-events'),
            array($this, 'analytics_retention_field_callback'),
            'kh_events_analytics',
            'kh_events_analytics_general_section'
        );

        add_settings_field(
            'kh_events_analytics_dashboard',
            __('Dashboard Widgets', 'kh-events'),
            array($this, 'analytics_dashboard_field_callback'),
            'kh_events_analytics',
            'kh_events_analytics_general_section'
        );

        add_settings_field(
            'kh_events_require_consent',
            __('Require Consent', 'kh-events'),
            array($this, 'require_consent_field_callback'),
            'kh_events_gdpr',
            'kh_events_gdpr_section'
        );

        add_settings_field(
            'kh_events_consent_text',
            __('Consent Text', 'kh-events'),
            array($this, 'consent_text_field_callback'),
            'kh_events_gdpr',
            'kh_events_gdpr_section'
        );

        add_settings_field(
            'kh_events_additional_consent_text',
            __('Additional Consent Text', 'kh-events'),
            array($this, 'additional_consent_text_field_callback'),
            'kh_events_gdpr',
            'kh_events_gdpr_section'
        );

        add_settings_field(
            'kh_events_data_retention',
            __('Data Retention Period', 'kh-events'),
            array($this, 'data_retention_field_callback'),
            'kh_events_gdpr',
            'kh_events_gdpr_section'
        );
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, $this->settings_page) !== false) {
            wp_enqueue_script('kh-events-admin', KH_EVENTS_URL . 'assets/js/kh-events-admin.js', array('jquery'), KH_EVENTS_VERSION, true);
            wp_enqueue_style('kh-events-admin', KH_EVENTS_URL . 'assets/css/kh-events-admin.css', array(), KH_EVENTS_VERSION);
        }
    }

    public function settings_page_callback() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        $tabs = array(
            'general' => __('General', 'kh-events'),
            'maps' => __('Google Maps', 'kh-events'),
            'email' => __('Email', 'kh-events'),
            'booking' => __('Booking', 'kh-events'),
            'display' => __('Display', 'kh-events'),
            'payment' => __('Payment', 'kh-events'),
            'permissions' => __('Permissions', 'kh-events'),
            'integrations' => __('Integrations', 'kh-events'),
            'analytics' => __('Analytics', 'kh-events'),
            'gdpr' => __('GDPR', 'kh-events'),
        );

        ?>
        <div class="wrap">
            <h1><?php _e('KH Events Settings', 'kh-events'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_key => $tab_caption) : ?>
                    <a href="?page=<?php echo $this->settings_page; ?>&tab=<?php echo $tab_key; ?>" class="nav-tab <?php echo $active_tab == $tab_key ? 'nav-tab-active' : ''; ?>"><?php echo $tab_caption; ?></a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="options.php">
                <?php
                switch ($active_tab) {
                    case 'maps':
                        settings_fields('kh_events_maps');
                        do_settings_sections('kh_events_maps');
                        break;
                    case 'email':
                        settings_fields('kh_events_email');
                        do_settings_sections('kh_events_email');
                        break;
                    case 'booking':
                        settings_fields('kh_events_booking');
                        do_settings_sections('kh_events_booking');
                        break;
                    case 'display':
                        settings_fields('kh_events_display');
                        do_settings_sections('kh_events_display');
                        break;
                    case 'payment':
                        settings_fields('kh_events_payment');
                        do_settings_sections('kh_events_payment');
                        break;
                    case 'permissions':
                        settings_fields('kh_events_permissions');
                        do_settings_sections('kh_events_permissions');
                        break;
                    case 'integrations':
                        settings_fields('kh_events_integrations');
                        do_settings_sections('kh_events_integrations');
                        break;
                    case 'analytics':
                        settings_fields('kh_events_analytics');
                        do_settings_sections('kh_events_analytics');
                        break;
                    case 'gdpr':
                        settings_fields('kh_events_gdpr');
                        do_settings_sections('kh_events_gdpr');
                        break;
                    default:
                        settings_fields('kh_events_general');
                        do_settings_sections('kh_events_general');
                        break;
                }
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    // Section Callbacks
    public function general_section_callback() {
        echo '<p>' . __('Configure general settings for the KH Events plugin.', 'kh-events') . '</p>';
    }

    public function maps_section_callback() {
        echo '<p>' . __('Configure Google Maps integration settings.', 'kh-events') . '</p>';
        echo '<p>' . __('Get your API key from the <a href="https://console.developers.google.com/" target="_blank">Google Cloud Console</a>.', 'kh-events') . '</p>';
    }

    public function email_section_callback() {
        echo '<p>' . __('Configure email settings for notifications and confirmations.', 'kh-events') . '</p>';
    }

    public function booking_section_callback() {
        echo '<p>' . __('Configure booking and registration settings.', 'kh-events') . '</p>';
    }

    public function display_section_callback() {
        echo '<p>' . __('Configure how events are displayed on your site.', 'kh-events') . '</p>';
    }

    // Field Callbacks
    public function currency_field_callback() {
        $options = get_option('kh_events_general_settings');
        $currency = isset($options['currency']) ? $options['currency'] : 'USD';
        $currencies = array(
            'USD' => __('US Dollar ($)', 'kh-events'),
            'EUR' => __('Euro (€)', 'kh-events'),
            'GBP' => __('British Pound (£)', 'kh-events'),
            'JPY' => __('Japanese Yen (¥)', 'kh-events'),
            'CAD' => __('Canadian Dollar (C$)', 'kh-events'),
            'AUD' => __('Australian Dollar (A$)', 'kh-events'),
        );
        echo '<select name="kh_events_general_settings[currency]" id="kh_events_currency">';
        foreach ($currencies as $code => $name) {
            echo '<option value="' . $code . '" ' . selected($currency, $code, false) . '>' . $name . '</option>';
        }
        echo '</select>';
    }

    public function date_format_field_callback() {
        $options = get_option('kh_events_general_settings');
        $date_format = isset($options['date_format']) ? $options['date_format'] : 'Y-m-d';
        $formats = array(
            'Y-m-d' => '2025-11-15',
            'm/d/Y' => '11/15/2025',
            'd/m/Y' => '15/11/2025',
            'F j, Y' => 'November 15, 2025',
        );
        echo '<select name="kh_events_general_settings[date_format]" id="kh_events_date_format">';
        foreach ($formats as $format => $example) {
            echo '<option value="' . $format . '" ' . selected($date_format, $format, false) . '>' . $example . '</option>';
        }
        echo '</select>';
    }

    public function time_format_field_callback() {
        $options = get_option('kh_events_general_settings');
        $time_format = isset($options['time_format']) ? $options['time_format'] : 'H:i';
        $formats = array(
            'H:i' => '14:30',
            'h:i A' => '2:30 PM',
            'h:i a' => '2:30 pm',
            'g:i A' => '2:30 PM',
        );
        echo '<select name="kh_events_general_settings[time_format]" id="kh_events_time_format">';
        foreach ($formats as $format => $example) {
            echo '<option value="' . $format . '" ' . selected($time_format, $format, false) . '>' . $example . '</option>';
        }
        echo '</select>';
    }

    public function google_maps_api_key_field_callback() {
        $options = get_option('kh_events_maps_settings');
        $api_key = isset($options['google_maps_api_key']) ? $options['google_maps_api_key'] : '';
        echo '<input type="password" name="kh_events_maps_settings[google_maps_api_key]" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your Google Maps API key. Required for location maps.', 'kh-events') . '</p>';
    }

    public function default_map_zoom_field_callback() {
        $options = get_option('kh_events_maps_settings');
        $zoom = isset($options['default_map_zoom']) ? $options['default_map_zoom'] : '14';
        echo '<input type="number" name="kh_events_maps_settings[default_map_zoom]" value="' . esc_attr($zoom) . '" min="1" max="20" class="small-text" />';
        echo '<p class="description">' . __('Default zoom level for location maps (1-20).', 'kh-events') . '</p>';
    }

    public function from_email_field_callback() {
        $options = get_option('kh_events_email_settings');
        $from_email = isset($options['from_email']) ? $options['from_email'] : get_option('admin_email');
        echo '<input type="email" name="kh_events_email_settings[from_email]" value="' . esc_attr($from_email) . '" class="regular-text" />';
        echo '<p class="description">' . __('Email address for sending notifications.', 'kh-events') . '</p>';
    }

    public function from_name_field_callback() {
        $options = get_option('kh_events_email_settings');
        $from_name = isset($options['from_name']) ? $options['from_name'] : get_option('blogname');
        echo '<input type="text" name="kh_events_email_settings[from_name]" value="' . esc_attr($from_name) . '" class="regular-text" />';
        echo '<p class="description">' . __('Name for sending notifications.', 'kh-events') . '</p>';
    }

    public function booking_confirmation_field_callback() {
        $options = get_option('kh_events_email_settings');
        $enabled = isset($options['booking_confirmation']) ? $options['booking_confirmation'] : '1';
        echo '<input type="checkbox" name="kh_events_email_settings[booking_confirmation]" value="1" ' . checked($enabled, '1', false) . ' />';
        echo '<label>' . __('Send booking confirmation emails to attendees.', 'kh-events') . '</label>';
    }

    public function allow_guest_bookings_field_callback() {
        $options = get_option('kh_events_booking_settings');
        $enabled = isset($options['allow_guest_bookings']) ? $options['allow_guest_bookings'] : '0';
        echo '<input type="checkbox" name="kh_events_booking_settings[allow_guest_bookings]" value="1" ' . checked($enabled, '1', false) . ' />';
        echo '<label>' . __('Allow non-registered users to make bookings.', 'kh-events') . '</label>';
    }

    public function booking_cutoff_field_callback() {
        $options = get_option('kh_events_booking_settings');
        $cutoff = isset($options['booking_cutoff_hours']) ? $options['booking_cutoff_hours'] : '24';
        echo '<input type="number" name="kh_events_booking_settings[booking_cutoff_hours]" value="' . esc_attr($cutoff) . '" min="0" class="small-text" />';
        echo '<p class="description">' . __('Hours before event when booking closes. Set to 0 for no cutoff.', 'kh-events') . '</p>';
    }

    public function events_per_page_field_callback() {
        $options = get_option('kh_events_display_settings');
        $per_page = isset($options['events_per_page']) ? $options['events_per_page'] : '10';
        echo '<input type="number" name="kh_events_display_settings[events_per_page]" value="' . esc_attr($per_page) . '" min="1" max="100" class="small-text" />';
        echo '<p class="description">' . __('Number of events to show per page in list view.', 'kh-events') . '</p>';
    }

    public function show_past_events_field_callback() {
        $options = get_option('kh_events_display_settings');
        $enabled = isset($options['show_past_events']) ? $options['show_past_events'] : '0';
        echo '<input type="checkbox" name="kh_events_display_settings[show_past_events]" value="1" ' . checked($enabled, '1', false) . ' />';
        echo '<label>' . __('Include past events in list and calendar views.', 'kh-events') . '</label>';
    }

    // Sanitization Callbacks
    public function sanitize_general_settings($input) {
        $sanitized = array();
        $sanitized['currency'] = sanitize_text_field($input['currency'] ?? 'USD');
        $sanitized['date_format'] = sanitize_text_field($input['date_format'] ?? 'Y-m-d');
        $sanitized['time_format'] = sanitize_text_field($input['time_format'] ?? 'H:i');
        return $sanitized;
    }

    public function sanitize_maps_settings($input) {
        $sanitized = array();
        $sanitized['google_maps_api_key'] = sanitize_text_field($input['google_maps_api_key'] ?? '');
        $sanitized['default_map_zoom'] = absint($input['default_map_zoom'] ?? 14);
        return $sanitized;
    }

    public function sanitize_email_settings($input) {
        $sanitized = array();
        $sanitized['from_email'] = sanitize_email($input['from_email'] ?? '');
        $sanitized['from_name'] = sanitize_text_field($input['from_name'] ?? '');
        $sanitized['booking_confirmation'] = isset($input['booking_confirmation']) ? '1' : '0';
        return $sanitized;
    }

    public function sanitize_booking_settings($input) {
        $sanitized = array();
        $sanitized['allow_guest_bookings'] = isset($input['allow_guest_bookings']) ? '1' : '0';
        $sanitized['booking_cutoff_hours'] = absint($input['booking_cutoff_hours'] ?? 24);
        return $sanitized;
    }

    public function sanitize_display_settings($input) {
        $sanitized = array();
        $sanitized['events_per_page'] = absint($input['events_per_page'] ?? 10);
        $sanitized['show_past_events'] = isset($input['show_past_events']) ? '1' : '0';
        return $sanitized;
    }

    public function payment_section_callback() {
        echo '<p>' . __('Configure payment processing settings for event bookings.', 'kh-events') . '</p>';
    }

    public function enable_payments_field_callback() {
        $options = get_option('kh_events_payment_settings');
        $enabled = isset($options['enable_payments']) ? $options['enable_payments'] : '0';
        echo '<input type="checkbox" name="kh_events_payment_settings[enable_payments]" value="1" ' . checked($enabled, '1', false) . ' />';
        echo '<label>' . __('Enable payment processing for event bookings.', 'kh-events') . '</label>';
    }

    public function default_gateway_field_callback() {
        $options = get_option('kh_events_payment_settings');
        $default_gateway = isset($options['default_gateway']) ? $options['default_gateway'] : 'stripe';

        if (function_exists('KH_Payment_Handler')) {
            $handler = KH_Payment_Handler::instance();
            $gateways = $handler->get_available_gateways();

            echo '<select name="kh_events_payment_settings[default_gateway]" id="kh_events_default_gateway">';
            echo '<option value="">' . __('Select Default Gateway', 'kh-events') . '</option>';

            foreach ($gateways as $gateway_id => $gateway) {
                echo '<option value="' . $gateway_id . '" ' . selected($default_gateway, $gateway_id, false) . '>' . $gateway->get_gateway_name() . '</option>';
            }
            echo '</select>';
        } else {
            echo '<p>' . __('Payment handler not available.', 'kh-events') . '</p>';
        }
    }

    public function payment_description_field_callback() {
        $options = get_option('kh_events_payment_settings');
        $description = isset($options['payment_description']) ? $options['payment_description'] : __('Event booking payment', 'kh-events');
        echo '<input type="text" name="kh_events_payment_settings[payment_description]" value="' . esc_attr($description) . '" class="regular-text" />';
        echo '<p class="description">' . __('Default description for payment transactions.', 'kh-events') . '</p>';
    }

    public function sanitize_payment_settings($input) {
        $sanitized = array();
        $sanitized['enable_payments'] = isset($input['enable_payments']) ? '1' : '0';
        $sanitized['default_gateway'] = sanitize_text_field($input['default_gateway'] ?? '');
        $sanitized['payment_description'] = sanitize_text_field($input['payment_description'] ?? '');
        return $sanitized;
    }

    // Helper methods
    public static function get_option($key, $default = '') {
        $option_name = 'kh_events_' . $key . '_settings';
        $options = get_option($option_name, array());
        return $options[$key] ?? $default;
    }

    public static function get_general_option($key, $default = '') {
        return self::get_option('general', $key, $default);
    }

    public static function get_maps_option($key, $default = '') {
        return self::get_option('maps', $key, $default);
    }

    public static function get_email_option($key, $default = '') {
        return self::get_option('email', $key, $default);
    }

    public static function get_booking_option($key, $default = '') {
        return self::get_option('booking', $key, $default);
    }

    public static function get_display_option($key, $default = '') {
        return self::get_option('display', $key, $default);
    }

    // GDPR Settings Callbacks
    public function sanitize_gdpr_settings($settings) {
        return array(
            'require_consent' => isset($settings['require_consent']) ? 'yes' : 'no',
            'consent_text' => sanitize_text_field($settings['consent_text'] ?? ''),
            'additional_consent_text' => wp_kses_post($settings['additional_consent_text'] ?? ''),
            'data_retention' => absint($settings['data_retention'] ?? 2555), // Default 7 years in days
        );
    }

    public function gdpr_section_callback() {
        echo '<p>' . __('Configure GDPR compliance settings for data privacy and consent management.', 'kh-events') . '</p>';
        echo '<p>' . __('These settings help you comply with data protection regulations by managing user consent and data retention.', 'kh-events') . '</p>';
    }

    public function require_consent_field_callback() {
        $settings = get_option('kh_events_gdpr_settings', array());
        $require_consent = $settings['require_consent'] ?? 'yes';

        echo '<label>';
        echo '<input type="checkbox" name="kh_events_gdpr_settings[require_consent]" value="yes" ' . checked($require_consent, 'yes', false) . '> ';
        echo __('Require user consent before processing bookings and event submissions', 'kh-events');
        echo '</label>';
        echo '<p class="description">' . __('When enabled, users must agree to your privacy policy before submitting forms.', 'kh-events') . '</p>';
    }

    public function consent_text_field_callback() {
        $settings = get_option('kh_events_gdpr_settings', array());
        $consent_text = $settings['consent_text'] ?? __('I agree to the processing of my personal data according to the %s.', 'kh-events');

        echo '<textarea name="kh_events_gdpr_settings[consent_text]" rows="3" cols="50" class="large-text">' . esc_textarea($consent_text) . '</textarea>';
        echo '<p class="description">' . __('Use %s as a placeholder for the privacy policy link. This text will appear as a required checkbox on all forms.', 'kh-events') . '</p>';
    }

    public function additional_consent_text_field_callback() {
        $settings = get_option('kh_events_gdpr_settings', array());
        $additional_text = $settings['additional_consent_text'] ?? '';

        wp_editor($additional_text, 'kh_events_gdpr_settings_additional_consent_text', array(
            'textarea_name' => 'kh_events_gdpr_settings[additional_consent_text]',
            'textarea_rows' => 5,
            'media_buttons' => false,
            'tinymce' => false,
            'quicktags' => true,
        ));

        echo '<p class="description">' . __('Optional additional consent information that appears below the main consent checkbox.', 'kh-events') . '</p>';
    }

    public function data_retention_field_callback() {
        $settings = get_option('kh_events_gdpr_settings', array());
        $retention = $settings['data_retention'] ?? 2555; // 7 years in days

        echo '<input type="number" name="kh_events_gdpr_settings[data_retention]" value="' . esc_attr($retention) . '" min="1" max="9999" step="1"> ';
        echo __('days', 'kh-events');
        echo '<p class="description">' . __('How long to retain booking and event data (in days). Default is 7 years (2555 days) for accounting purposes.', 'kh-events') . '</p>';
    }

    public static function get_gdpr_option($key, $default = '') {
        return self::get_option('gdpr', $key, $default);
    }

    // Integrations Settings Callbacks

    public function sanitize_integrations_settings($settings) {
        $sanitized = array();

        // Zoom settings
        if (isset($settings['zoom'])) {
            $sanitized['zoom'] = array(
                'client_id' => sanitize_text_field($settings['zoom']['client_id'] ?? ''),
                'client_secret' => sanitize_text_field($settings['zoom']['client_secret'] ?? ''),
            );
        }

        // Eventbrite settings
        if (isset($settings['eventbrite'])) {
            $sanitized['eventbrite'] = array(
                'client_id' => sanitize_text_field($settings['eventbrite']['client_id'] ?? ''),
                'client_secret' => sanitize_text_field($settings['eventbrite']['client_secret'] ?? ''),
            );
        }

        // Facebook settings
        if (isset($settings['facebook'])) {
            $sanitized['facebook'] = array(
                'app_id' => sanitize_text_field($settings['facebook']['app_id'] ?? ''),
                'app_secret' => sanitize_text_field($settings['facebook']['app_secret'] ?? ''),
                'page_id' => sanitize_text_field($settings['facebook']['page_id'] ?? ''),
            );
        }

        return $sanitized;
    }

    // Zoom Callbacks
    public function zoom_section_callback() {
        echo '<p>' . __('Connect your Zoom account to automatically create meetings for events.', 'kh-events') . '</p>';
        echo '<p>' . sprintf(__('Get your API credentials from the <a href="%s" target="_blank">Zoom Marketplace</a>.', 'kh-events'), 'https://marketplace.zoom.us/') . '</p>';
    }

    public function zoom_client_id_field_callback() {
        $settings = get_option('kh_events_integrations_settings', array());
        $client_id = $settings['zoom']['client_id'] ?? '';

        echo '<input type="text" name="kh_events_integrations_settings[zoom][client_id]" value="' . esc_attr($client_id) . '" class="regular-text">';
        echo '<p class="description">' . __('Your Zoom OAuth Client ID.', 'kh-events') . '</p>';
    }

    public function zoom_client_secret_field_callback() {
        $settings = get_option('kh_events_integrations_settings', array());
        $client_secret = $settings['zoom']['client_secret'] ?? '';

        echo '<input type="password" name="kh_events_integrations_settings[zoom][client_secret]" value="' . esc_attr($client_secret) . '" class="regular-text">';
        echo '<p class="description">' . __('Your Zoom OAuth Client Secret.', 'kh-events') . '</p>';
    }

    public function zoom_connection_field_callback() {
        if (!class_exists('KH_Event_Integrations')) {
            require_once KH_EVENTS_DIR . 'includes/class-kh-event-integrations.php';
        }

        $integrations = KH_Event_Integrations::instance();

        if ($integrations->is_zoom_connected()) {
            echo '<p style="color: green;">✓ ' . __('Connected to Zoom', 'kh-events') . '</p>';
            echo '<p><a href="#" class="button" id="kh-refresh-zoom-token">' . __('Refresh Token', 'kh-events') . '</a></p>';
        } else {
            $auth_url = $integrations->get_zoom_auth_url();
            if ($auth_url) {
                echo '<p><a href="' . esc_url($auth_url) . '" class="button button-primary">' . __('Connect to Zoom', 'kh-events') . '</a></p>';
            } else {
                echo '<p>' . __('Please enter your Zoom API credentials above.', 'kh-events') . '</p>';
            }
        }
    }

    // Eventbrite Callbacks
    public function eventbrite_section_callback() {
        echo '<p>' . __('Connect your Eventbrite account to sync events and manage tickets.', 'kh-events') . '</p>';
        echo '<p>' . sprintf(__('Get your API credentials from <a href="%s" target="_blank">Eventbrite Developer</a>.', 'kh-events'), 'https://www.eventbrite.com/platform/') . '</p>';
    }

    public function eventbrite_client_id_field_callback() {
        $settings = get_option('kh_events_integrations_settings', array());
        $client_id = $settings['eventbrite']['client_id'] ?? '';

        echo '<input type="text" name="kh_events_integrations_settings[eventbrite][client_id]" value="' . esc_attr($client_id) . '" class="regular-text">';
        echo '<p class="description">' . __('Your Eventbrite OAuth Client ID.', 'kh-events') . '</p>';
    }

    public function eventbrite_client_secret_field_callback() {
        $settings = get_option('kh_events_integrations_settings', array());
        $client_secret = $settings['eventbrite']['client_secret'] ?? '';

        echo '<input type="password" name="kh_events_integrations_settings[eventbrite][client_secret]" value="' . esc_attr($client_secret) . '" class="regular-text">';
        echo '<p class="description">' . __('Your Eventbrite OAuth Client Secret.', 'kh-events') . '</p>';
    }

    public function eventbrite_connection_field_callback() {
        if (!class_exists('KH_Event_Integrations')) {
            require_once KH_EVENTS_DIR . 'includes/class-kh-event-integrations.php';
        }

        $integrations = KH_Event_Integrations::instance();

        if ($integrations->is_eventbrite_connected()) {
            echo '<p style="color: green;">✓ ' . __('Connected to Eventbrite', 'kh-events') . '</p>';
        } else {
            $auth_url = $integrations->get_eventbrite_auth_url();
            if ($auth_url) {
                echo '<p><a href="' . esc_url($auth_url) . '" class="button button-primary">' . __('Connect to Eventbrite', 'kh-events') . '</a></p>';
            } else {
                echo '<p>' . __('Please enter your Eventbrite API credentials above.', 'kh-events') . '</p>';
            }
        }
    }

    // Facebook Callbacks
    public function facebook_section_callback() {
        echo '<p>' . __('Connect your Facebook account to create events and engage with your audience.', 'kh-events') . '</p>';
        echo '<p>' . sprintf(__('Create a Facebook App at <a href="%s" target="_blank">Facebook Developers</a>.', 'kh-events'), 'https://developers.facebook.com/') . '</p>';
    }

    public function facebook_app_id_field_callback() {
        $settings = get_option('kh_events_integrations_settings', array());
        $app_id = $settings['facebook']['app_id'] ?? '';

        echo '<input type="text" name="kh_events_integrations_settings[facebook][app_id]" value="' . esc_attr($app_id) . '" class="regular-text">';
        echo '<p class="description">' . __('Your Facebook App ID.', 'kh-events') . '</p>';
    }

    public function facebook_app_secret_field_callback() {
        $settings = get_option('kh_events_integrations_settings', array());
        $app_secret = $settings['facebook']['app_secret'] ?? '';

        echo '<input type="password" name="kh_events_integrations_settings[facebook][app_secret]" value="' . esc_attr($app_secret) . '" class="regular-text">';
        echo '<p class="description">' . __('Your Facebook App Secret.', 'kh-events') . '</p>';
    }

    public function facebook_connection_field_callback() {
        if (!class_exists('KH_Event_Integrations')) {
            require_once KH_EVENTS_DIR . 'includes/class-kh-event-integrations.php';
        }

        $integrations = KH_Event_Integrations::instance();
        $settings = get_option('kh_events_integrations_settings', array());

        if ($integrations->is_facebook_connected()) {
            echo '<p style="color: green;">✓ ' . __('Connected to Facebook', 'kh-events') . '</p>';

            // Page selection
            $pages = $settings['facebook']['pages'] ?? array();
            $selected_page = $settings['facebook']['page_id'] ?? '';

            if (!empty($pages)) {
                echo '<select name="kh_events_integrations_settings[facebook][page_id]">';
                echo '<option value="">' . __('Select a page...', 'kh-events') . '</option>';
                foreach ($pages as $page) {
                    $selected = selected($selected_page, $page['id'], false);
                    echo '<option value="' . esc_attr($page['id']) . '" ' . $selected . '>' . esc_html($page['name']) . '</option>';
                }
                echo '</select>';
                echo '<p class="description">' . __('Select the Facebook page to post events to.', 'kh-events') . '</p>';
            }
        } else {
            $auth_url = $integrations->get_facebook_auth_url();
            if ($auth_url) {
                echo '<p><a href="' . esc_url($auth_url) . '" class="button button-primary">' . __('Connect to Facebook', 'kh-events') . '</a></p>';
            } else {
                echo '<p>' . __('Please enter your Facebook App credentials above.', 'kh-events') . '</p>';
            }
        }
    }

    // Permissions Settings Callbacks

    public function sanitize_permissions_settings($settings) {
        $sanitized = array();

        $sanitized['enable_advanced_permissions'] = isset($settings['enable_advanced_permissions']) ? 1 : 0;
        $sanitized['default_user_group'] = sanitize_text_field($settings['default_user_group'] ?? 'kh_viewer');

        if (isset($settings['group_permissions']) && is_array($settings['group_permissions'])) {
            $sanitized['group_permissions'] = array();
            foreach ($settings['group_permissions'] as $group => $permissions) {
                $sanitized['group_permissions'][sanitize_text_field($group)] = array_map('intval', $permissions);
            }
        }

        return $sanitized;
    }

    public function permissions_section_callback() {
        echo '<p>' . __('Configure advanced permissions and user group restrictions for the KH Events plugin.', 'kh-events') . '</p>';
    }

    public function enable_advanced_permissions_field_callback() {
        $settings = get_option('kh_events_permissions_settings', array());
        $enabled = $settings['enable_advanced_permissions'] ?? 0;

        echo '<input type="checkbox" name="kh_events_permissions_settings[enable_advanced_permissions]" value="1" ' . checked(1, $enabled, false) . '>';
        echo '<label for="kh_events_permissions_settings[enable_advanced_permissions]">' . __('Enable advanced role-based permissions and user group restrictions', 'kh-events') . '</label>';
        echo '<p class="description">' . __('When enabled, users will be assigned to groups with specific permissions. When disabled, standard WordPress roles apply.', 'kh-events') . '</p>';
    }

    public function default_user_group_field_callback() {
        $settings = get_option('kh_events_permissions_settings', array());
        $default_group = $settings['default_user_group'] ?? 'kh_viewer';

        if (!class_exists('KH_Event_Permissions')) {
            require_once KH_EVENTS_DIR . 'includes/class-kh-event-permissions.php';
        }

        $permissions = KH_Event_Permissions::instance();
        $groups = $permissions->get_available_groups();

        echo '<select name="kh_events_permissions_settings[default_user_group]">';
        foreach ($groups as $slug => $name) {
            echo '<option value="' . esc_attr($slug) . '" ' . selected($default_group, $slug, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Default user group assigned to new users.', 'kh-events') . '</p>';
    }

    public function group_permissions_field_callback() {
        if (!class_exists('KH_Event_Permissions')) {
            require_once KH_EVENTS_DIR . 'includes/class-kh-event-permissions.php';
        }

        $permissions = KH_Event_Permissions::instance();
        $groups = $permissions->get_available_groups();
        $group_permissions = $permissions->get_group_permissions();

        $permission_labels = array(
            'can_create_events' => __('Create Events', 'kh-events'),
            'can_edit_events' => __('Edit Events', 'kh-events'),
            'can_delete_events' => __('Delete Events', 'kh-events'),
            'can_view_bookings' => __('View Bookings', 'kh-events'),
            'can_manage_bookings' => __('Manage Bookings', 'kh-events'),
            'can_view_reports' => __('View Reports', 'kh-events'),
            'can_manage_users' => __('Manage Users', 'kh-events'),
        );

        echo '<div class="kh-permissions-matrix">';
        echo '<table class="widefat">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('User Group', 'kh-events') . '</th>';
        foreach ($permission_labels as $permission => $label) {
            echo '<th>' . esc_html($label) . '</th>';
        }
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($groups as $group_slug => $group_name) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($group_name) . '</strong></td>';

            foreach ($permission_labels as $permission => $label) {
                $checked = isset($group_permissions[$group_slug][$permission]) && $group_permissions[$group_slug][$permission] ? 'checked' : '';
                echo '<td>';
                echo '<input type="checkbox" name="kh_events_permissions_settings[group_permissions][' . esc_attr($group_slug) . '][' . esc_attr($permission) . ']" value="1" ' . $checked . '>';
                echo '</td>';
            }

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '<p class="description">' . __('Configure permissions for each user group. Changes take effect immediately.', 'kh-events') . '</p>';
    }

    // Analytics Settings Callbacks

    public function sanitize_analytics_settings($settings) {
        $sanitized = array();

        $sanitized['enable_analytics'] = isset($settings['enable_analytics']) ? 1 : 0;
        $sanitized['data_retention_days'] = absint($settings['data_retention_days'] ?? 730); // Default 2 years
        $sanitized['enable_dashboard_widgets'] = isset($settings['enable_dashboard_widgets']) ? 1 : 0;

        return $sanitized;
    }

    public function analytics_section_callback() {
        echo '<p>' . __('Configure analytics and reporting settings for your events.', 'kh-events') . '</p>';
        echo '<p>' . __('Analytics data helps you understand event performance, user engagement, and business metrics.', 'kh-events') . '</p>';
    }

    public function enable_analytics_field_callback() {
        $settings = get_option('kh_events_analytics_settings', array());
        $enabled = $settings['enable_analytics'] ?? 1;

        echo '<input type="checkbox" name="kh_events_analytics_settings[enable_analytics]" value="1" ' . checked(1, $enabled, false) . '>';
        echo '<label for="kh_events_analytics_settings[enable_analytics]">' . __('Enable event analytics tracking', 'kh-events') . '</label>';
        echo '<p class="description">' . __('Collect and analyze data about event views, bookings, payments, and attendance.', 'kh-events') . '</p>';
    }

    public function analytics_retention_field_callback() {
        $settings = get_option('kh_events_analytics_settings', array());
        $retention = $settings['data_retention_days'] ?? 730;

        echo '<input type="number" name="kh_events_analytics_settings[data_retention_days]" value="' . esc_attr($retention) . '" min="30" max="2555" class="small-text">';
        echo '<p class="description">' . __('Number of days to keep analytics data (minimum 30 days, maximum ~7 years).', 'kh-events') . '</p>';
    }

    public function analytics_dashboard_field_callback() {
        $settings = get_option('kh_events_analytics_settings', array());
        $enabled = $settings['enable_dashboard_widgets'] ?? 1;

        echo '<input type="checkbox" name="kh_events_analytics_settings[enable_dashboard_widgets]" value="1" ' . checked(1, $enabled, false) . '>';
        echo '<label for="kh_events_analytics_settings[enable_dashboard_widgets]">' . __('Show analytics widgets on WordPress dashboard', 'kh-events') . '</label>';
        echo '<p class="description">' . __('Display key metrics and popular events on the main WordPress dashboard.', 'kh-events') . '</p>';
    }
}