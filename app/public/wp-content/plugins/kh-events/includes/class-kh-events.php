<?php
/**
 * Main KH Events class
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events {

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
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        add_action('template_redirect', array($this, 'handle_ical_export'));

        // Include meta classes
        require_once KH_EVENTS_DIR . 'includes/class-kh-event-meta.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-location-meta.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-events-views.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-event-tickets.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-event-bookings.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-recurring-events.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-event-status.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-event-filters-widget.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-events-admin-settings.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-payment-gateways.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-event-import-export.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-event-rest-api.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-event-timezone.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-event-gdpr.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-event-permissions.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-event-integrations.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-event-analytics.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-events-woocommerce-bridge.php';
        require_once KH_EVENTS_DIR . 'includes/class-kh-events-coupon-system.php';

        new KH_Event_Meta();
        new KH_Location_Meta();
        new KH_Events_Views();
        new KH_Event_Tickets();
        new KH_Event_Bookings();
        KH_Recurring_Events::instance();
        KH_Event_Status::instance();
        KH_Events_Admin_Settings::instance();
        KH_Payment_Handler::instance();
        KH_Event_Import_Export::instance();
        KH_Event_REST_API::instance();
        KH_Event_Timezone::instance();
        KH_Event_GDPR::instance();
        KH_Event_Permissions::instance();
        KH_Event_Integrations::instance();
        KH_Event_Analytics::instance();
        KH_Events_WooCommerce_Bridge::instance();
        KH_Events_Coupon_System::instance();

        // Register widget
        add_action('widgets_init', array($this, 'register_widgets'));

        $this->register_phase_engine_hooks();
    }

    public function register_widgets() {
        register_widget('KH_Event_Filters_Widget');
    }

    public function init() {
        // Register custom post types
        $this->register_post_types();
        $this->register_taxonomies();

        // Load textdomain
        load_plugin_textdomain('kh-events', false, dirname(KH_EVENTS_BASENAME) . '/languages/');

        // Add admin enhancements
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_filter('manage_kh_event_posts_columns', array($this, 'add_event_columns'));
        add_action('manage_kh_event_posts_custom_column', array($this, 'populate_event_columns'), 10, 2);
        add_filter('manage_edit-kh_event_sortable_columns', array($this, 'make_event_columns_sortable'));
        add_action('restrict_manage_posts', array($this, 'add_event_filters'));
        add_filter('parse_query', array($this, 'filter_events_query'));

        // AJAX handlers
        add_action('wp_ajax_kh_duplicate_event', array($this, 'ajax_duplicate_event'));
        add_action('wp_ajax_kh_search_events', array('KH_Events_Views', 'ajax_search_events'));
        add_action('wp_ajax_nopriv_kh_search_events', array('KH_Events_Views', 'ajax_search_events'));
        add_action('wp_ajax_kh_submit_event', array('KH_Events_Views', 'ajax_submit_event'));
        add_action('wp_ajax_kh_get_dashboard_stats', array('KH_Events_Views', 'ajax_get_dashboard_stats'));
        add_action('wp_ajax_kh_get_user_events', array('KH_Events_Views', 'ajax_get_user_events'));

        // Timezone AJAX handlers
        add_action('wp_ajax_kh_save_user_timezone', array($this, 'ajax_save_user_timezone'));
        add_action('wp_ajax_kh_update_event_timezone', array($this, 'ajax_update_event_timezone'));
        add_action('wp_ajax_kh_get_event_timezone_info', array($this, 'ajax_get_event_timezone_info'));

        // Dashboard widget
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));

        // Initialize new Phase 3 components
        KH_Events_Email_Marketing::instance();
        KH_Events_Analytics::instance();
        KH_Events_Enhanced_API::instance();
    }

    private function register_phase_engine_hooks() {
        add_action('kh_event_booking_created', array($this, 'phase_booking_created'), 10, 2);
        add_action('kh_event_attendance_marked', array($this, 'phase_attendance_marked'), 10, 3);
        add_action('kh_event_viewed', array($this, 'phase_event_viewed'), 10, 2);
    }

    public function phase_booking_created($booking_id, $event_id) {
        $user_id = get_current_user_id();
        $event_key = $this->map_phase_event('booking_created', array(
            'booking_id' => $booking_id,
            'event_id' => $event_id
        ));

        $this->record_phase_event(
            $user_id,
            $event_key,
            'kh_events_booking',
            array(
                'booking_id' => $booking_id,
                'event_id' => $event_id
            )
        );
    }

    public function phase_attendance_marked($event_id, $user_id, $status) {
        $status_key = $this->is_attendance_complete($status) ? 'attendance_present' : 'attendance_other';
        $event_key = $this->map_phase_event($status_key, array(
            'event_id' => $event_id,
            'status' => $status
        ));

        $this->record_phase_event(
            (int) $user_id,
            $event_key,
            'kh_events_attendance',
            array(
                'event_id' => $event_id,
                'status' => $status
            )
        );
    }

    public function phase_event_viewed($event_id, $user_id = null) {
        $event_key = $this->map_phase_event('event_view', array(
            'event_id' => $event_id
        ));

        $this->record_phase_event(
            (int) ($user_id ?: 0),
            $event_key,
            'kh_events_view',
            array(
                'event_id' => $event_id,
                'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
            )
        );
    }

    private function is_attendance_complete($status) {
        $status = strtolower((string) $status);
        return $status === 'present' || $status === 'attended' || $status === 'complete';
    }

    private function map_phase_event($key, array $context = array()) {
        $map = array(
            'booking_created' => 'webinar_passive',
            'attendance_present' => 'webinar_full_marketing_15',
            'attendance_other' => 'webinar_partial_25_75_marketing',
            'event_view' => 'webinar_passive',
        );

        if (function_exists('apply_filters')) {
            $map = apply_filters('kh_events_phase_engine_event_map', $map, $context);
        }

        return $map[$key] ?? '';
    }

    private function record_phase_event($user_id, $event_id, $source, $metadata = array()) {
        if (!$user_id || empty($event_id)) {
            return;
        }

        if (!class_exists('\\KH_SMMA\\Services\\PhaseEngine')) {
            return;
        }

        global $wpdb;
        $engine = new \KH_SMMA\Services\PhaseEngine($wpdb);
        $engine->record_event((int) $user_id, $event_id, $source, (array) $metadata);
    }

    private function register_post_types() {
        // Event post type
        register_post_type('kh_event', array(
            'labels' => array(
                'name' => __('Events', 'kh-events'),
                'singular_name' => __('Event', 'kh-events'),
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
            'show_in_rest' => true,
            'taxonomies' => array('kh_event_category', 'kh_event_tag'),
        ));

        // Location post type
        register_post_type('kh_location', array(
            'labels' => array(
                'name' => __('Locations', 'kh-events'),
                'singular_name' => __('Location', 'kh-events'),
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
            'show_in_rest' => true,
        ));

        // Booking post type
        register_post_type('kh_booking', array(
            'labels' => array(
                'name' => __('Bookings', 'kh-events'),
                'singular_name' => __('Booking', 'kh-events'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'kh-events',
            'supports' => array('title'),
            'capability_type' => 'post',
        ));
    }

    private function register_taxonomies() {
        // Event Categories
        register_taxonomy('kh_event_category', 'kh_event', array(
            'labels' => array(
                'name' => __('Event Categories', 'kh-events'),
                'singular_name' => __('Event Category', 'kh-events'),
            ),
            'hierarchical' => true,
            'public' => true,
            'show_in_rest' => true,
        ));

        // Event Tags
        register_taxonomy('kh_event_tag', 'kh_event', array(
            'labels' => array(
                'name' => __('Event Tags', 'kh-events'),
                'singular_name' => __('Event Tag', 'kh-events'),
            ),
            'hierarchical' => false,
            'public' => true,
            'show_in_rest' => true,
        ));
    }

    public function admin_menu() {
        add_menu_page(
            __('KH Events', 'kh-events'),
            __('KH Events', 'kh-events'),
            'manage_options',
            'kh-events',
            array($this, 'admin_page'),
            'dashicons-calendar-alt',
            30
        );
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('KH Events', 'kh-events'); ?></h1>
            <p><?php _e('Welcome to KH Events - your comprehensive event management solution.', 'kh-events'); ?></p>
        </div>
        <?php
    }

    public function enqueue_admin_styles($hook) {
        // Load on our admin pages and edit screens
        if (strpos($hook, 'kh-events') !== false || strpos($hook, 'kh_booking') !== false || $hook === 'edit.php') {
            wp_enqueue_style('kh-events-admin', KH_EVENTS_URL . 'assets/css/admin.css', array(), KH_EVENTS_VERSION);
            wp_enqueue_style('kh-timezone-admin', KH_EVENTS_URL . 'assets/css/timezone.css', array(), KH_EVENTS_VERSION);
        }

        if ('toplevel_page_kh-events' !== $hook && 'events_page_kh-events-settings' !== $hook) {
            return;
        }
        wp_enqueue_style('kh-events-admin', KH_EVENTS_URL . 'assets/css/kh-events-admin.css', array(), KH_EVENTS_VERSION);
        wp_enqueue_script('kh-events-admin', KH_EVENTS_URL . 'assets/js/kh-events-admin.js', array('jquery'), KH_EVENTS_VERSION, true);
        wp_localize_script('kh-events-admin', 'kh_events_admin', array(
            'nonce' => wp_create_nonce('kh_duplicate_event'),
            'ajax_url' => admin_url('admin-ajax.php'),
        ));
    }

    public function frontend_enqueue_scripts() {
        if ( function_exists( 'kh_events_is_builder_preview' ) && kh_events_is_builder_preview() ) {
            return;
        }
        // Load on event pages
        if (is_singular('kh_event') || is_post_type_archive('kh_event')) {
            wp_enqueue_style('kh-timezone-frontend', KH_EVENTS_URL . 'assets/css/timezone.css', array(), KH_EVENTS_VERSION);
        }
    }

    public function add_event_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['event_date'] = __('Event Date', 'kh-events');
                $new_columns['event_location'] = __('Location', 'kh-events');
                $new_columns['event_bookings'] = __('Bookings', 'kh-events');
            }
        }
        return $new_columns;
    }

    public function populate_event_columns($column, $post_id) {
        switch ($column) {
            case 'event_date':
                $start_date = get_post_meta($post_id, '_kh_event_start_date', true);
                $start_time = get_post_meta($post_id, '_kh_event_start_time', true);
                if ($start_date) {
                    $date_format = KH_Events_Admin_Settings::get_general_option('date_format', 'Y-m-d');
                    $time_format = KH_Events_Admin_Settings::get_general_option('time_format', 'H:i');
                    $formatted_date = date($date_format, strtotime($start_date));
                    $formatted_time = $start_time ? date($time_format, strtotime($start_time)) : '';
                    echo $formatted_date . ($formatted_time ? ' ' . $formatted_time : '');
                } else {
                    echo __('No date set', 'kh-events');
                }
                break;

            case 'event_location':
                $location_id = get_post_meta($post_id, '_kh_event_location', true);
                if ($location_id) {
                    $location = get_post($location_id);
                    if ($location) {
                        echo '<a href="' . get_edit_post_link($location_id) . '">' . get_the_title($location_id) . '</a>';
                    }
                } else {
                    echo __('No location', 'kh-events');
                }
                break;

            case 'event_bookings':
                $bookings = get_posts(array(
                    'post_type' => 'kh_booking',
                    'meta_query' => array(
                        array(
                            'key' => '_kh_booking_event_id',
                            'value' => $post_id,
                            'compare' => '='
                        )
                    ),
                    'posts_per_page' => -1
                ));
                $count = count($bookings);
                echo '<a href="' . admin_url('edit.php?post_type=kh_booking&event_id=' . $post_id) . '">' . $count . ' ' . _n('booking', 'bookings', $count, 'kh-events') . '</a>';
                break;
        }
    }

    public function make_event_columns_sortable($columns) {
        $columns['event_date'] = 'event_date';
        return $columns;
    }

    public function add_event_filters() {
        global $typenow;
        if ($typenow !== 'kh_event') {
            return;
        }

        // Category filter
        $selected_category = isset($_GET['kh_event_category']) ? $_GET['kh_event_category'] : '';
        wp_dropdown_categories(array(
            'show_option_all' => __('All Categories', 'kh-events'),
            'taxonomy' => 'kh_event_category',
            'name' => 'kh_event_category',
            'selected' => $selected_category,
            'value_field' => 'slug',
            'hierarchical' => true,
        ));

        // Tag filter
        $selected_tag = isset($_GET['kh_event_tag']) ? $_GET['kh_event_tag'] : '';
        wp_dropdown_categories(array(
            'show_option_all' => __('All Tags', 'kh-events'),
            'taxonomy' => 'kh_event_tag',
            'name' => 'kh_event_tag',
            'selected' => $selected_tag,
            'value_field' => 'slug',
            'hierarchical' => false,
        ));

        // Date filter
        $selected_date = isset($_GET['kh_event_date']) ? $_GET['kh_event_date'] : '';
        echo '<input type="date" name="kh_event_date" value="' . esc_attr($selected_date) . '" placeholder="' . __('Filter by date', 'kh-events') . '" />';

        // Status filter
        $selected_status = isset($_GET['kh_event_status']) ? $_GET['kh_event_status'] : '';
        echo '<select name="kh_event_status">';
        echo '<option value="">' . __('All Statuses', 'kh-events') . '</option>';
        echo '<option value="upcoming" ' . selected($selected_status, 'upcoming', false) . '>' . __('Upcoming', 'kh-events') . '</option>';
        echo '<option value="past" ' . selected($selected_status, 'past', false) . '>' . __('Past', 'kh-events') . '</option>';
        echo '<option value="today" ' . selected($selected_status, 'today', false) . '>' . __('Today', 'kh-events') . '</option>';
        echo '</select>';
    }

    public function filter_events_query($query) {
        global $pagenow;
        if (!is_admin() || $pagenow !== 'edit.php' || !isset($_GET['post_type']) || $_GET['post_type'] !== 'kh_event') {
            return $query;
        }

        $meta_query = array();

        // Category filter
        if (!empty($_GET['kh_event_category'])) {
            $query->set('tax_query', array(
                array(
                    'taxonomy' => 'kh_event_category',
                    'field' => 'slug',
                    'terms' => $_GET['kh_event_category']
                )
            ));
        }

        // Tag filter
        if (!empty($_GET['kh_event_tag'])) {
            $tax_query = $query->get('tax_query') ?: array();
            $tax_query[] = array(
                'taxonomy' => 'kh_event_tag',
                'field' => 'slug',
                'terms' => $_GET['kh_event_tag']
            );
            $query->set('tax_query', $tax_query);
        }

        // Date filter
        if (!empty($_GET['kh_event_date'])) {
            $meta_query[] = array(
                'key' => '_kh_event_start_date',
                'value' => $_GET['kh_event_date'],
                'compare' => '=',
                'type' => 'DATE'
            );
        }

        // Status filter
        if (!empty($_GET['kh_event_status'])) {
            $today = date('Y-m-d');
            switch ($_GET['kh_event_status']) {
                case 'upcoming':
                    $meta_query[] = array(
                        'key' => '_kh_event_start_date',
                        'value' => $today,
                        'compare' => '>=',
                        'type' => 'DATE'
                    );
                    break;
                case 'past':
                    $meta_query[] = array(
                        'key' => '_kh_event_start_date',
                        'value' => $today,
                        'compare' => '<',
                        'type' => 'DATE'
                    );
                    break;
                case 'today':
                    $meta_query[] = array(
                        'key' => '_kh_event_start_date',
                        'value' => $today,
                        'compare' => '=',
                        'type' => 'DATE'
                    );
                    break;
            }
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
    }

    public function ajax_duplicate_event() {
        check_ajax_referer('kh_duplicate_event', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'kh_event') {
            wp_send_json_error(__('Invalid event'));
        }

        // Duplicate the post
        $new_post = array(
            'post_title' => $post->post_title . ' (Copy)',
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => 'draft',
            'post_type' => 'kh_event',
            'post_author' => get_current_user_id(),
        );

        $new_post_id = wp_insert_post($new_post);

        if (is_wp_error($new_post_id)) {
            wp_send_json_error($new_post_id->get_error_message());
        }

        // Duplicate post meta
        $meta_keys = array(
            '_kh_event_start_date',
            '_kh_event_end_date',
            '_kh_event_start_time',
            '_kh_event_end_time',
            '_kh_event_location',
            '_kh_event_recurring',
            '_kh_event_recurrence_type',
            '_kh_event_recurrence_interval',
            '_kh_event_recurrence_end_date',
        );

        foreach ($meta_keys as $meta_key) {
            $meta_value = get_post_meta($post_id, $meta_key, true);
            if ($meta_value) {
                update_post_meta($new_post_id, $meta_key, $meta_value);
            }
        }

        // Duplicate taxonomies
        $taxonomies = array('kh_event_category', 'kh_event_tag');
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'ids'));
            wp_set_post_terms($new_post_id, $terms, $taxonomy);
        }

        wp_send_json_success(array('post_id' => $new_post_id));
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'kh_events_dashboard_widget',
            __('KH Events Overview', 'kh-events'),
            array($this, 'dashboard_widget_content'),
            null,
            null,
            'normal',
            'high'
        );
    }

    public function dashboard_widget_content() {
        // Get stats
        $total_events = wp_count_posts('kh_event')->publish;
        $pending_events = wp_count_posts('kh_event')->pending;
        $upcoming_events = $this->get_upcoming_events_count();
        $total_bookings = wp_count_posts('kh_booking')->publish;
        $today_events = $this->get_today_events_count();

        ?>
        <div class="kh-events-dashboard-widget">
            <div class="kh-events-stats-grid">
                <div class="kh-events-stat-card">
                    <span class="kh-events-stat-number"><?php echo $total_events; ?></span>
                    <span class="kh-events-stat-label"><?php _e('Published Events', 'kh-events'); ?></span>
                </div>
                <div class="kh-events-stat-card">
                    <span class="kh-events-stat-number"><?php echo $pending_events; ?></span>
                    <span class="kh-events-stat-label"><?php _e('Pending Review', 'kh-events'); ?></span>
                </div>
                <div class="kh-events-stat-card">
                    <span class="kh-events-stat-number"><?php echo $upcoming_events; ?></span>
                    <span class="kh-events-stat-label"><?php _e('Upcoming', 'kh-events'); ?></span>
                </div>
                <div class="kh-events-stat-card">
                    <span class="kh-events-stat-number"><?php echo $total_bookings; ?></span>
                    <span class="kh-events-stat-label"><?php _e('Total Bookings', 'kh-events'); ?></span>
                </div>
            </div>

            <div class="kh-events-recent-events">
                <h4><?php _e('Recent Events', 'kh-events'); ?></h4>
                <?php
                $recent_events = get_posts(array(
                    'post_type' => 'kh_event',
                    'posts_per_page' => 5,
                    'post_status' => 'publish',
                    'orderby' => 'date',
                    'order' => 'DESC'
                ));

                if ($recent_events) {
                    echo '<ul>';
                    foreach ($recent_events as $event) {
                        $event_date = get_post_meta($event->ID, '_kh_event_start_date', true);
                        $formatted_date = $event_date ? date('M j, Y', strtotime($event_date)) : __('No date', 'kh-events');
                        echo '<li>';
                        echo '<a href="' . get_edit_post_link($event->ID) . '" class="event-title">' . get_the_title($event->ID) . '</a>';
                        echo '<div class="event-date">' . $formatted_date . '</div>';
                        echo '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p>' . __('No events found.', 'kh-events') . '</p>';
                }
                ?>
            </div>

            <?php if ($pending_events > 0): ?>
            <div class="kh-events-pending-events">
                <h4><?php _e('Pending Events', 'kh-events'); ?></h4>
                <?php
                $pending_events_list = get_posts(array(
                    'post_type' => 'kh_event',
                    'posts_per_page' => 3,
                    'post_status' => 'pending',
                    'orderby' => 'date',
                    'order' => 'DESC'
                ));

                if ($pending_events_list) {
                    echo '<ul>';
                    foreach ($pending_events_list as $event) {
                        $author = get_userdata($event->post_author);
                        $author_name = $author ? $author->display_name : __('Unknown', 'kh-events');
                        echo '<li>';
                        echo '<a href="' . get_edit_post_link($event->ID) . '" class="event-title">' . get_the_title($event->ID) . '</a>';
                        echo '<div class="event-author">' . sprintf(__('By: %s', 'kh-events'), $author_name) . '</div>';
                        echo '</li>';
                    }
                    echo '</ul>';
                    if ($pending_events > 3) {
                        echo '<p><a href="' . admin_url('edit.php?post_status=pending&post_type=kh_event') . '">' . sprintf(__('View all %d pending events', 'kh-events'), $pending_events) . '</a></p>';
                    }
                }
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_upcoming_events_count() {
        $today = date('Y-m-d');
        $args = array(
            'post_type' => 'kh_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_kh_event_start_date',
                    'value' => $today,
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            )
        );
        $query = new WP_Query($args);
        return $query->found_posts;
    }

    private function get_today_events_count() {
        $today = date('Y-m-d');
        $args = array(
            'post_type' => 'kh_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_kh_event_start_date',
                    'value' => $today,
                    'compare' => '=',
                    'type' => 'DATE'
                )
            )
        );
        $query = new WP_Query($args);
        return $query->found_posts;
    }

    public function handle_ical_export() {
        if (!isset($_GET['kh_action']) || $_GET['kh_action'] !== 'export_ical') {
            return;
        }

        if (!isset($_GET['event_id'])) {
            wp_die(__('Event ID is required.', 'kh-events'));
        }

        $event_id = intval($_GET['event_id']);
        $event = get_post($event_id);

        if (!$event || $event->post_type !== 'kh_event') {
            wp_die(__('Invalid event.', 'kh-events'));
        }

        // Generate iCal content
        $ical_content = $this->generate_ical_content($event_id);

        // Set headers for download
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_title(get_the_title($event_id)) . '.ics"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $ical_content;
        exit;
    }

    private function generate_ical_content($event_id) {
        $event = get_post($event_id);
        $title = get_the_title($event_id);
        $description = get_the_content(null, false, $event_id);
        $start_date = get_post_meta($event_id, '_kh_event_start_date', true);
        $start_time = get_post_meta($event_id, '_kh_event_start_time', true);
        $end_date = get_post_meta($event_id, '_kh_event_end_date', true) ?: $start_date;
        $end_time = get_post_meta($event_id, '_kh_event_end_time', true);

        // Get location
        $location = '';
        $location_id = get_post_meta($event_id, '_kh_event_location', true);
        if ($location_id) {
            $location = get_the_title($location_id);
        }

        // Format dates for iCal (YYYYMMDDTHHMMSS)
        $start_datetime = $this->format_ical_datetime($start_date, $start_time);
        $end_datetime = $this->format_ical_datetime($end_date, $end_time);

        // Generate unique ID
        $uid = $event_id . '@' . parse_url(home_url(), PHP_URL_HOST);

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//KH Events//EN\r\n";
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . $uid . "\r\n";
        $ical .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $ical .= "DTSTART:" . $start_datetime . "\r\n";
        $ical .= "DTEND:" . $end_datetime . "\r\n";
        $ical .= "SUMMARY:" . $this->escape_ical_text($title) . "\r\n";
        if ($description) {
            $ical .= "DESCRIPTION:" . $this->escape_ical_text($description) . "\r\n";
        }
        if ($location) {
            $ical .= "LOCATION:" . $this->escape_ical_text($location) . "\r\n";
        }
        $ical .= "URL:" . get_permalink($event_id) . "\r\n";
        $ical .= "END:VEVENT\r\n";
        $ical .= "END:VCALENDAR\r\n";

        return $ical;
    }

    private function format_ical_datetime($date, $time) {
        if (empty($date)) return '';

        $datetime = $date;
        if (!empty($time)) {
            $datetime .= ' ' . $time;
        } else {
            $datetime .= ' 00:00:00';
        }

        $timestamp = strtotime($datetime);
        return gmdate('Ymd\THis\Z', $timestamp);
    }

    private function escape_ical_text($text) {
        // Escape commas, semicolons, and backslashes
        $text = str_replace(',', '\,', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace("\n", '\n', $text);
        return $text;
    }

    public static function activate() {
        // Activation tasks
        flush_rewrite_rules();
    }

    /**
     * AJAX save user timezone
     */
    public function ajax_save_user_timezone() {
        check_ajax_referer('kh_timezone_convert', 'nonce');

        $timezone = sanitize_text_field($_POST['timezone']);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }

        $timezone_instance = KH_Event_Timezone::instance();
        if ($timezone_instance->set_user_timezone($timezone, $user_id)) {
            wp_send_json_success(array('timezone' => $timezone));
        } else {
            wp_send_json_error('Failed to save timezone');
        }
    }

    /**
     * AJAX update event timezone
     */
    public function ajax_update_event_timezone() {
        check_ajax_referer('kh_timezone_info', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        $event_id = absint($_POST['event_id']);
        $timezone = sanitize_text_field($_POST['timezone']);

        $timezone_instance = KH_Event_Timezone::instance();
        if ($timezone_instance->set_event_timezone($event_id, $timezone)) {
            wp_send_json_success(array('event_id' => $event_id, 'timezone' => $timezone));
        } else {
            wp_send_json_error('Failed to update event timezone');
        }
    }

    /**
     * AJAX get event timezone info
     */
    public function ajax_get_event_timezone_info() {
        check_ajax_referer('kh_timezone_convert', 'nonce');

        $event_id = absint($_POST['event_id']);
        $event = get_post($event_id);

        if (!$event || $event->post_type !== 'kh_event') {
            wp_send_json_error('Event not found');
        }

        $timezone_instance = KH_Event_Timezone::instance();
        $event_timezone = $timezone_instance->get_event_timezone($event_id);

        $start_date = get_post_meta($event_id, '_kh_event_start_date', true);
        $start_time = get_post_meta($event_id, '_kh_event_start_time', true);
        $datetime = $start_date . ($start_time ? ' ' . $start_time : '');

        wp_send_json_success(array(
            'event_id' => $event_id,
            'timezone' => $event_timezone,
            'timezone_name' => $timezone_instance->get_available_timezones()[$event_timezone] ?? $event_timezone,
            'offset' => $timezone_instance->get_timezone_offset($event_timezone),
            'abbr' => $timezone_instance->get_timezone_abbr($event_timezone),
            'local_time' => $timezone_instance->format_datetime_for_user($datetime, $event_timezone)
        ));
    }

    public static function deactivate() {
        // Deactivation tasks
        flush_rewrite_rules();
    }
}
