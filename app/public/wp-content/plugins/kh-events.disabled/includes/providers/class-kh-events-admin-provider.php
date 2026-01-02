<?php
/**
 * Admin Interface Service Provider for KH Events
 *
 * Provides modern, intuitive admin interface for event management
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Admin_Provider extends KH_Events_Service_Provider {

    /**
     * Admin pages
     */
    private $admin_pages = array();

    /**
     * Settings sections
     */
    private $settings_sections = array();

    /**
     * Settings fields
     */
    private $settings_fields = array();

    /**
     * Register the admin services
     */
    public function register() {
        // Bind admin services
        $this->bind('kh_events_admin', 'KH_Events_Admin', true);
        $this->bind('kh_events_admin_settings', 'KH_Events_Admin_Settings', true);
        $this->bind('kh_events_admin_menus', 'KH_Events_Admin_Menus', true);
    }

    /**
     * Boot the admin provider
     */
    public function boot() {
        // Only load admin functionality in admin area
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', array($this, 'register_admin_menus'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_kh_events_save_settings', array($this, 'ajax_save_settings'));

        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));

        // Customize admin columns
        add_filter('manage_kh_event_posts_columns', array($this, 'customize_event_columns'));
        add_action('manage_kh_event_posts_custom_column', array($this, 'render_event_columns'), 10, 2);

        // Add quick edit support
        add_action('quick_edit_custom_box', array($this, 'quick_edit_fields'), 10, 2);
        add_action('save_post', array($this, 'save_quick_edit_data'));
    }

    /**
     * Register admin menus
     */
    public function register_admin_menus() {
        $admin_menus = $this->get('kh_events_admin_menus');
        $admin_menus->register_menus();
    }

    /**
     * Register settings
     */
    public function register_settings() {
        $admin_settings = $this->get('kh_events_admin_settings');
        $admin_settings->register_settings();
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'kh-events') === false && strpos($hook, 'kh_event') === false) {
            return;
        }

        wp_enqueue_style(
            'kh-events-admin',
            KH_EVENTS_URL . 'assets/css/admin.css',
            array(),
            KH_EVENTS_VERSION
        );

        wp_enqueue_script(
            'kh-events-admin',
            KH_EVENTS_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-datepicker'),
            KH_EVENTS_VERSION,
            true
        );

        // Localize script
        wp_localize_script('kh-events-admin', 'kh_events_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kh_events_admin_nonce'),
            'strings' => array(
                'saving' => __('Saving...', 'kh-events'),
                'saved' => __('Settings saved successfully!', 'kh-events'),
                'error' => __('Error saving settings.', 'kh-events'),
            ),
        ));
    }

    /**
     * AJAX save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('kh_events_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'kh-events'));
        }

        $section = sanitize_text_field($_POST['section']);
        $settings = $_POST['settings'];

        // Sanitize settings based on section
        $sanitized_settings = $this->sanitize_settings($settings, $section);

        // Save settings
        $admin_settings = $this->get('kh_events_admin_settings');
        $result = $admin_settings->save_settings($section, $sanitized_settings);

        if ($result) {
            wp_send_json_success(__('Settings saved successfully!', 'kh-events'));
        } else {
            wp_send_json_error(__('Error saving settings.', 'kh-events'));
        }
    }

    /**
     * Sanitize settings based on section
     */
    private function sanitize_settings($settings, $section) {
        $sanitized = array();

        switch ($section) {
            case 'general':
                $sanitized['default_timezone'] = sanitize_text_field($settings['default_timezone'] ?? 'UTC');
                $sanitized['default_currency'] = sanitize_text_field($settings['default_currency'] ?? 'USD');
                $sanitized['date_format'] = sanitize_text_field($settings['date_format'] ?? 'Y-m-d');
                $sanitized['time_format'] = sanitize_text_field($settings['time_format'] ?? 'H:i');
                break;

            case 'booking':
                $sanitized['enable_bookings'] = isset($settings['enable_bookings']) ? 1 : 0;
                $sanitized['require_account'] = isset($settings['require_account']) ? 1 : 0;
                $sanitized['confirmation_email'] = isset($settings['confirmation_email']) ? 1 : 0;
                $sanitized['admin_notifications'] = isset($settings['admin_notifications']) ? 1 : 0;
                break;

            case 'display':
                $sanitized['show_timezone'] = isset($settings['show_timezone']) ? 1 : 0;
                $sanitized['show_capacity'] = isset($settings['show_capacity']) ? 1 : 0;
                $sanitized['show_price'] = isset($settings['show_price']) ? 1 : 0;
                $sanitized['calendar_theme'] = sanitize_text_field($settings['calendar_theme'] ?? 'default');
                break;
        }

        return $sanitized;
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        if (isset($_GET['kh-events-saved'])) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __('KH Events settings saved successfully!', 'kh-events') . '</p>';
            echo '</div>';
        }
    }

    /**
     * Customize event columns
     */
    public function customize_event_columns($columns) {
        $new_columns = array();

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            // Add our custom columns after title
            if ($key === 'title') {
                $new_columns['event_date'] = __('Event Date', 'kh-events');
                $new_columns['event_status'] = __('Status', 'kh-events');
                $new_columns['event_capacity'] = __('Capacity', 'kh-events');
                $new_columns['event_bookings'] = __('Bookings', 'kh-events');
            }
        }

        return $new_columns;
    }

    /**
     * Render event columns
     */
    public function render_event_columns($column, $post_id) {
        $database = $this->get('kh_events_db');

        switch ($column) {
            case 'event_date':
                $event = $database->get_event($post_id);
                if ($event) {
                    $start_date = date_i18n(get_option('date_format'), strtotime($event['start_date']));
                    $start_time = $event['start_time'] ? date_i18n(get_option('time_format'), strtotime($event['start_time'])) : '';
                    echo esc_html($start_date);
                    if ($start_time) {
                        echo '<br><small>' . esc_html($start_time) . '</small>';
                    }
                }
                break;

            case 'event_status':
                $event = $database->get_event($post_id);
                if ($event) {
                    $status = $event['event_status'];
                    $status_labels = array(
                        'scheduled' => __('Scheduled', 'kh-events'),
                        'canceled' => __('Canceled', 'kh-events'),
                        'postponed' => __('Postponed', 'kh-events'),
                        'draft' => __('Draft', 'kh-events'),
                    );

                    $status_class = 'status-' . $status;
                    echo '<span class="event-status ' . esc_attr($status_class) . '">' . esc_html($status_labels[$status] ?? $status) . '</span>';
                }
                break;

            case 'event_capacity':
                $event = $database->get_event($post_id);
                if ($event && $event['max_capacity']) {
                    $capacity = $event['max_capacity'];
                    $bookings = $event['current_bookings'] ?? 0;
                    $percentage = $capacity > 0 ? round(($bookings / $capacity) * 100) : 0;

                    echo esc_html($bookings . '/' . $capacity);
                    echo '<br><small>' . esc_html($percentage) . '%</small>';
                } else {
                    echo __('Unlimited', 'kh-events');
                }
                break;

            case 'event_bookings':
                $bookings = $database->get_event_bookings($post_id);
                $confirmed = array_filter($bookings, function($booking) {
                    return $booking['booking_status'] === 'confirmed';
                });

                echo '<a href="' . esc_url(admin_url('edit.php?post_type=kh_booking&event_id=' . $post_id)) . '">';
                echo esc_html(count($confirmed)) . ' ' . __('confirmed', 'kh-events');
                echo '</a>';
                break;
        }
    }

    /**
     * Add quick edit fields
     */
    public function quick_edit_fields($column_name, $post_type) {
        if ($post_type !== 'kh_event') {
            return;
        }

        switch ($column_name) {
            case 'event_status':
                ?>
                <fieldset class="inline-edit-col-right">
                    <div class="inline-edit-col">
                        <label>
                            <span class="title"><?php _e('Status', 'kh-events'); ?></span>
                            <select name="event_status">
                                <option value="scheduled"><?php _e('Scheduled', 'kh-events'); ?></option>
                                <option value="canceled"><?php _e('Canceled', 'kh-events'); ?></option>
                                <option value="postponed"><?php _e('Postponed', 'kh-events'); ?></option>
                                <option value="draft"><?php _e('Draft', 'kh-events'); ?></option>
                            </select>
                        </label>
                    </div>
                </fieldset>
                <?php
                break;
        }
    }

    /**
     * Save quick edit data
     */
    public function save_quick_edit_data($post_id) {
        if (!isset($_POST['event_status']) || !current_user_can('edit_post', $post_id)) {
            return;
        }

        $status = sanitize_text_field($_POST['event_status']);
        $valid_statuses = array('scheduled', 'canceled', 'postponed', 'draft');

        if (in_array($status, $valid_statuses)) {
            $database = $this->get('kh_events_db');
            $database->save_event(array(
                'post_id' => $post_id,
                'event_status' => $status,
            ));
        }
    }
}