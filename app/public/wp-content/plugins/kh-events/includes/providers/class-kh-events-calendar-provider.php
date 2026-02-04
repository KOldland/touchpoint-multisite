<?php
/**
 * Calendar View Service Provider for KH Events
 *
 * Provides comprehensive calendar display functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Calendar_Provider extends KH_Events_Service_Provider {

    /**
     * Calendar views
     */
    private $calendar_views = array();

    /**
     * Register the calendar services
     */
    public function register() {
        // Bind calendar services
        $this->bind('kh_events_calendar', 'KH_Events_Calendar', true);
        $this->bind('kh_events_calendar_renderer', 'KH_Events_Calendar_Renderer', true);
        $this->bind('kh_events_calendar_shortcodes', 'KH_Events_Calendar_Shortcodes', true);
    }

    /**
     * Boot the calendar provider
     */
    public function boot() {
        // Register shortcodes
        add_shortcode('kh_event_calendar', array($this, 'calendar_shortcode'));

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // AJAX handlers for calendar
        add_action('wp_ajax_kh_events_get_calendar_events', array($this, 'ajax_get_calendar_events'));
        add_action('wp_ajax_nopriv_kh_events_get_calendar_events', array($this, 'ajax_get_calendar_events'));
        add_action('wp_ajax_kh_events_load_calendar', array($this, 'ajax_load_calendar'));
        add_action('wp_ajax_nopriv_kh_events_load_calendar', array($this, 'ajax_load_calendar'));
        add_action('wp_ajax_kh_events_get_event_details', array($this, 'ajax_get_event_details'));
        add_action('wp_ajax_nopriv_kh_events_get_event_details', array($this, 'ajax_get_event_details'));

        // Add calendar to event archives if enabled
        add_action('kh_events_before_event_loop', array($this, 'add_calendar_to_archive'));

        // Register calendar views
        $this->register_calendar_views();
    }

    /**
     * Register available calendar views
     */
    private function register_calendar_views() {
        $this->calendar_views = array(
            'month' => array(
                'label' => __('Month', 'kh-events'),
                'class' => 'KH_Events_Calendar_Month_View',
                'default' => true
            ),
            'week' => array(
                'label' => __('Week', 'kh-events'),
                'class' => 'KH_Events_Calendar_Week_View'
            ),
            'day' => array(
                'label' => __('Day', 'kh-events'),
                'class' => 'KH_Events_Calendar_Day_View'
            ),
            'list' => array(
                'label' => __('List', 'kh-events'),
                'class' => 'KH_Events_Calendar_List_View'
            )
        );
    }

    /**
     * Calendar shortcode
     */
    public function calendar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'view' => 'month',
            'categories' => '',
            'locations' => '',
            'limit' => 0,
            'show_filters' => 'true',
            'theme' => 'default'
        ), $atts);

        $calendar_shortcodes = $this->get('kh_events_calendar_shortcodes');
        return $calendar_shortcodes->render_calendar($atts);
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only load on pages with calendar
        if (!$this->has_calendar_content()) {
            return;
        }

        wp_enqueue_style(
            'kh-events-calendar',
            KH_EVENTS_URL . 'assets/css/kh-events-calendar.css',
            array(),
            KH_EVENTS_VERSION
        );

        wp_enqueue_script(
            'kh-events-calendar',
            KH_EVENTS_URL . 'assets/js/kh-events-calendar.js',
            array('jquery'),
            KH_EVENTS_VERSION,
            true
        );

        // Localize script
        wp_localize_script('kh-events-calendar', 'kh_events_calendar', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kh_events_calendar_nonce'),
            'i18n' => array(
                'date' => __('Date', 'kh-events'),
                'time' => __('Time', 'kh-events'),
                'location' => __('Location', 'kh-events'),
                'price' => __('Price', 'kh-events'),
                'capacity' => __('Capacity', 'kh-events'),
                'view_event' => __('View Event', 'kh-events'),
                'book_now' => __('Book Now', 'kh-events'),
                'loading' => __('Loading...', 'kh-events'),
                'error' => __('Error', 'kh-events'),
            )
        ));
    }

    /**
     * Check if current page has calendar content
     */
    private function has_calendar_content() {
        global $post;

        // Check for shortcode
        if (isset($post->post_content) && has_shortcode($post->post_content, 'kh_event_calendar')) {
            return true;
        }

        // Check for calendar archive page
        if (is_post_type_archive('kh_event') && $this->calendar_enabled_on_archive()) {
            return true;
        }

        return false;
    }

    /**
     * Check if calendar is enabled on archive pages
     */
    private function calendar_enabled_on_archive() {
        $settings = get_option('kh_events_settings', array());
        return isset($settings['display']['show_calendar_archive']) && $settings['display']['show_calendar_archive'];
    }

    /**
     * AJAX handler for getting calendar events
     */
    public function ajax_get_calendar_events() {
        check_ajax_referer('kh_events_calendar_nonce', 'nonce');

        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $view = sanitize_text_field($_POST['view'] ?? 'month');
        $categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : array();
        $locations = isset($_POST['locations']) ? array_map('intval', $_POST['locations']) : array();

        $database = $this->get('kh_events_db');
        $events = $database->search_events(array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => 'scheduled',
            'categories' => $categories,
            'locations' => $locations
        ));

        // Format events for calendar
        $formatted_events = array();
        foreach ($events as $event) {
            $formatted_events[] = array(
                'id' => $event['event_id'],
                'title' => $event['title'],
                'start' => $event['start_date'],
                'end' => $event['end_date'],
                'url' => get_permalink($event['post_id']),
                'className' => 'kh-event-calendar-item',
                'extendedProps' => array(
                    'description' => wp_trim_words($event['description'], 20),
                    'location' => $this->get_event_location_name($event['event_id']),
                    'time' => $this->format_event_time($event),
                )
            );
        }

        wp_send_json_success($formatted_events);
    }

    /**
     * Get event location name
     */
    private function get_event_location_name($event_id) {
        $database = $this->get('kh_events_db');
        $locations = $database->get_event_locations($event_id);

        if (!empty($locations)) {
            return $locations[0]['name'];
        }

        return '';
    }

    /**
     * Format event time for display
     */
    private function format_event_time($event) {
        $time_format = get_option('time_format', 'g:i A');

        if ($event['start_time'] && $event['end_time']) {
            return date_i18n($time_format, strtotime($event['start_time'])) . ' - ' .
                   date_i18n($time_format, strtotime($event['end_time']));
        } elseif ($event['start_time']) {
            return __('Starts at', 'kh-events') . ' ' . date_i18n($time_format, strtotime($event['start_time']));
        }

        return __('All day', 'kh-events');
    }

    /**
     * Add calendar to event archive pages
     */
    public function add_calendar_to_archive() {
        if (!$this->calendar_enabled_on_archive()) {
            return;
        }

        echo do_shortcode('[kh_event_calendar view="month" show_filters="true"]');
    }

    /**
     * Get available calendar views
     */
    public function get_calendar_views() {
        return $this->calendar_views;
    }

    /**
     * Get calendar view instance
     */
    public function get_calendar_view($view_type) {
        if (!isset($this->calendar_views[$view_type])) {
            $view_type = 'month'; // Default fallback
        }

        $view_class = $this->calendar_views[$view_type]['class'];

        if (class_exists($view_class)) {
            return new $view_class();
        }

        return null;
    }

    /**
     * AJAX handler for loading calendar view
     */
    public function ajax_load_calendar() {
        check_ajax_referer('kh_events_calendar_nonce', 'nonce');

        $view = sanitize_text_field($_POST['view'] ?? 'month');
        $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
        $filters = $_POST['filters'] ?? array();

        // Sanitize filters
        $sanitized_filters = array();
        if (!empty($filters['category'])) {
            $sanitized_filters['categories'] = array(intval($filters['category']));
        }
        if (!empty($filters['location'])) {
            $sanitized_filters['locations'] = array(intval($filters['location']));
        }

        try {
            $calendar_renderer = $this->get('kh_events_calendar_renderer');
            $html = $this->render_calendar_view($calendar_renderer, $view, $date, $sanitized_filters);

            wp_send_json_success(array(
                'html' => $html
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * AJAX handler for getting event details
     */
    public function ajax_get_event_details() {
        check_ajax_referer('kh_events_calendar_nonce', 'nonce');

        $event_id = intval($_POST['event_id'] ?? 0);

        if (!$event_id) {
            wp_send_json_error(array(
                'message' => __('Invalid event ID', 'kh-events')
            ));
        }

        try {
            $event_details = $this->get_event_details($event_id);

            wp_send_json_success($event_details);
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Render calendar view using renderer
     */
    private function render_calendar_view($renderer, $view, $date, $filters = array()) {
        switch ($view) {
            case 'month':
                return $renderer->render_month_view($date, $filters);
            case 'week':
                return $renderer->render_week_view($date, $filters);
            case 'day':
                return $renderer->render_day_view($date, $filters);
            case 'list':
                return $renderer->render_list_view($date, $filters);
            default:
                return $renderer->render_month_view($date, $filters);
        }
    }

    /**
     * Get event details for modal
     */
    private function get_event_details($event_id) {
        $database = $this->get('kh_events_db');
        $event = $database->get_event($event_id);

        if (!$event) {
            throw new Exception(__('Event not found', 'kh-events'));
        }

        $locations = $database->get_event_locations($event_id);
        $location_name = !empty($locations) ? $locations[0]['name'] : '';

        return array(
            'title' => $event['title'],
            'date' => date_i18n(get_option('date_format'), strtotime($event['start_date'])),
            'time' => $this->format_event_time($event),
            'location' => $location_name,
            'price' => $event['price'] > 0 ? $event['price'] . ' ' . $event['currency'] : '',
            'description' => wpautop($event['description']),
            'capacity' => $event['max_capacity'],
            'booked' => $event['current_bookings'],
            'url' => get_permalink($event['post_id']),
            'can_book' => $event['max_capacity'] > $event['current_bookings']
        );
    }
}