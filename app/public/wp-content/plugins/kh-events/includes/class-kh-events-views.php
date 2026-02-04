<?php
/**
 * Events Views Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Views {

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('kh_events_calendar', array($this, 'calendar_shortcode'));
        add_shortcode('kh_events_list', array($this, 'list_shortcode'));
        add_shortcode('kh_events_day', array($this, 'day_shortcode'));
        add_shortcode('kh_events_week', array($this, 'week_shortcode'));
        add_shortcode('kh_events_photo', array($this, 'photo_shortcode'));
        add_shortcode('kh_events_ical', array($this, 'ical_shortcode'));
        add_shortcode('kh_events_search', array($this, 'search_shortcode'));
        add_shortcode('kh_events_submit', array($this, 'submit_shortcode'));
        add_shortcode('kh_events_dashboard', array($this, 'dashboard_shortcode'));
    }

    /**
     * Load template with theme override support
     *
     * @param string $template_name Template name without .php extension
     * @param array $args Variables to pass to template
     * @return string Template output
     */
    public function load_template($template_name, $args = array()) {
        $template_path = $this->locate_template($template_name);

        if (!$template_path) {
            return '';
        }

        // Extract variables for template use
        if (!empty($args) && is_array($args)) {
            extract($args);
        }

        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Locate template with theme override support
     *
     * @param string $template_name Template name without .php extension
     * @return string|false Template file path or false if not found
     */
    private function locate_template($template_name) {
        $template = $template_name . '.php';

        // Check theme override first
        $theme_template = locate_template(array(
            'kh-events/' . $template,
            'kh-events/templates/' . $template
        ));

        if ($theme_template) {
            return $theme_template;
        }

        // Use plugin template
        $plugin_template = KH_EVENTS_DIR . 'templates/' . $template;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return false;
    }

    public function enqueue_scripts() {
        if ( function_exists( 'kh_events_is_builder_preview' ) && kh_events_is_builder_preview() ) {
            return;
        }
        wp_enqueue_style('kh-events-styles', KH_EVENTS_URL . 'assets/css/kh-events.css', array(), KH_EVENTS_VERSION);
        wp_enqueue_script('kh-events-scripts', KH_EVENTS_URL . 'assets/js/kh-events.js', array('jquery'), KH_EVENTS_VERSION, true);
        wp_enqueue_script('kh-events-advanced', KH_EVENTS_URL . 'assets/js/events-advanced.js', array('jquery'), KH_EVENTS_VERSION, true);
        wp_enqueue_script('kh-events-search', KH_EVENTS_URL . 'assets/js/events-search.js', array('jquery'), KH_EVENTS_VERSION, true);
        wp_localize_script('kh-events-scripts', 'kh_events_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
        ));
    }

    public function ajax_load_calendar() {
        $month = intval($_POST['month']);
        $year = intval($_POST['year']);
        $category = sanitize_text_field($_POST['category']);
        $tag = sanitize_text_field($_POST['tag']);

        ob_start();
        $this->render_calendar(array('month' => $month, 'year' => $year, 'category' => $category, 'tag' => $tag));
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    public function ajax_search_events() {
        $search_term = sanitize_text_field($_POST['search'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        $tag = sanitize_text_field($_POST['tag'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? '');
        $location = sanitize_text_field($_POST['location'] ?? '');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        $limit = intval($_POST['limit'] ?? 10);

        $args = array(
            'post_type' => 'kh_event',
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => '_kh_event_start_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            )
        );

        // Add search term
        if (!empty($search_term)) {
            $args['s'] = $search_term;
        }

        // Add category filter
        if (!empty($category)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'kh_event_category',
                'field' => 'slug',
                'terms' => $category
            );
        }

        // Add tag filter
        if (!empty($tag)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'kh_event_tag',
                'field' => 'slug',
                'terms' => $tag
            );
        }

        // Add status filter
        if (!empty($status)) {
            $args['meta_query'][] = array(
                'key' => '_kh_event_status',
                'value' => $status,
                'compare' => '='
            );
        }

        // Add location filter
        if (!empty($location)) {
            $args['meta_query'][] = array(
                'key' => '_kh_event_location',
                'value' => $location,
                'compare' => 'LIKE'
            );
        }

        // Add date range filter
        if (!empty($start_date) && !empty($end_date)) {
            $args['meta_query'][] = array(
                'key' => '_kh_event_start_date',
                'value' => array($start_date, $end_date),
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            );
        } elseif (!empty($start_date)) {
            $args['meta_query'][] = array(
                'key' => '_kh_event_start_date',
                'value' => $start_date,
                'compare' => '>=',
                'type' => 'DATE'
            );
        } elseif (!empty($end_date)) {
            $args['meta_query'][] = array(
                'key' => '_kh_event_start_date',
                'value' => $end_date,
                'compare' => '<=',
                'type' => 'DATE'
            );
        }

        $events = get_posts($args);
        $results = array();

        foreach ($events as $event) {
            $location_id = get_post_meta($event->ID, '_kh_event_location', true);
            $location_name = $location_id ? get_the_title($location_id) : '';
            $event_status = KH_Event_Status::instance()->get_status_display($event->ID);

            $results[] = array(
                'id' => $event->ID,
                'title' => get_the_title($event->ID),
                'permalink' => get_permalink($event->ID),
                'date' => get_post_meta($event->ID, '_kh_event_start_date', true),
                'time' => get_post_meta($event->ID, '_kh_event_start_time', true),
                'location' => $location_name,
                'excerpt' => get_the_excerpt($event->ID),
                'thumbnail' => get_the_post_thumbnail_url($event->ID, 'thumbnail'),
                'status' => $event_status
            );
        }

        wp_send_json_success(array('events' => $results));
    }

    public function ajax_submit_event() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to submit events.', 'kh-events')));
            return;
        }

        // Check permissions for event creation
        if (class_exists('KH_Event_Permissions')) {
            $permissions = KH_Event_Permissions::instance();
            if (!$permissions->can_create_event(true)) {
                wp_send_json_error(array('message' => __('You do not have permission to submit events.', 'kh-events')));
                return;
            }
        }

        // Validate required fields
        $required_fields = array('title', 'description', 'start_date', 'start_time');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(array('message' => sprintf(__('Field "%s" is required.', 'kh-events'), $field)));
                return;
            }
        }

        $event_data = array(
            'post_title' => sanitize_text_field($_POST['title']),
            'post_content' => wp_kses_post($_POST['description']),
            'post_status' => 'pending', // Events need approval
            'post_author' => get_current_user_id(),
            'post_type' => 'kh_event'
        );

        $event_id = wp_insert_post($event_data);

        if (is_wp_error($event_id)) {
            wp_send_json_error(array('message' => __('Failed to create event.', 'kh-events')));
            return;
        }

        // Save meta data
        update_post_meta($event_id, '_kh_event_start_date', sanitize_text_field($_POST['start_date']));
        update_post_meta($event_id, '_kh_event_start_time', sanitize_text_field($_POST['start_time']));

        if (!empty($_POST['end_date'])) {
            update_post_meta($event_id, '_kh_event_end_date', sanitize_text_field($_POST['end_date']));
        }
        if (!empty($_POST['end_time'])) {
            update_post_meta($event_id, '_kh_event_end_time', sanitize_text_field($_POST['end_time']));
        }

        // Handle location
        if (!empty($_POST['location'])) {
            $location_id = $this->create_or_get_location($_POST['location']);
            if ($location_id) {
                update_post_meta($event_id, '_kh_event_location', $location_id);
            }
        }

        // Handle categories
        if (!empty($_POST['categories']) && is_array($_POST['categories'])) {
            wp_set_post_terms($event_id, array_map('intval', $_POST['categories']), 'kh_event_category');
        }

        // Handle tags
        if (!empty($_POST['tags'])) {
            wp_set_post_terms($event_id, sanitize_text_field($_POST['tags']), 'kh_event_tag');
        }

        // Handle featured image
        if (!empty($_POST['featured_image'])) {
            set_post_thumbnail($event_id, intval($_POST['featured_image']));
        }

        // Trigger GDPR consent storage
        do_action('kh_events_event_submitted', $event_id, $_POST);

        wp_send_json_success(array(
            'message' => __('Event submitted successfully and is pending approval.', 'kh-events'),
            'event_id' => $event_id
        ));
    }

    private function create_or_get_location($location_data) {
        if (is_array($location_data)) {
            $location_name = sanitize_text_field($location_data['name']);
        } else {
            $location_name = sanitize_text_field($location_data);
        }

        // Check if location already exists
        $existing_location = get_posts(array(
            'post_type' => 'kh_location',
            'title' => $location_name,
            'posts_per_page' => 1
        ));

        if (!empty($existing_location)) {
            return $existing_location[0]->ID;
        }

        // Create new location
        $location_id = wp_insert_post(array(
            'post_title' => $location_name,
            'post_status' => 'publish',
            'post_type' => 'kh_location'
        ));

        if (is_array($location_data)) {
            if (!empty($location_data['address'])) {
                update_post_meta($location_id, '_kh_location_address', sanitize_text_field($location_data['address']));
            }
            if (!empty($location_data['city'])) {
                update_post_meta($location_id, '_kh_location_city', sanitize_text_field($location_data['city']));
            }
            if (!empty($location_data['state'])) {
                update_post_meta($location_id, '_kh_location_state', sanitize_text_field($location_data['state']));
            }
            if (!empty($location_data['zip'])) {
                update_post_meta($location_id, '_kh_location_zip', sanitize_text_field($location_data['zip']));
            }
            if (!empty($location_data['country'])) {
                update_post_meta($location_id, '_kh_location_country', sanitize_text_field($location_data['country']));
            }
        }

        return $location_id;
    }

    private function render_calendar($atts) {
        $month = intval($atts['month']);
        $year = intval($atts['year']);
        $category = $atts['category'];
        $tag = $atts['tag'];

        $first_day = mktime(0, 0, 0, $month, 1, $year);
        $days_in_month = date('t', $first_day);
        $day_of_week = date('w', $first_day);

        // Get events for this month
        $events = $this->get_events_for_month($month, $year, $category, $tag);

        // Navigation
        $prev_month = $month - 1;
        $prev_year = $year;
        if ($prev_month < 1) {
            $prev_month = 12;
            $prev_year--;
        }
        $next_month = $month + 1;
        $next_year = $year;
        if ($next_month > 12) {
            $next_month = 1;
            $next_year++;
        }

        // Prepare template variables
        $template_vars = array(
            'atts' => $atts,
            'month' => $month,
            'year' => $year,
            'first_day' => $first_day,
            'days_in_month' => $days_in_month,
            'day_of_week' => $day_of_week,
            'events' => $events,
            'prev_month' => $prev_month,
            'prev_year' => $prev_year,
            'next_month' => $next_month,
            'next_year' => $next_year,
            'category' => $category,
            'tag' => $tag
        );

        echo $this->load_template('calendar', $template_vars);
    }

    private function get_events_for_month($month, $year, $category = '', $tag = '') {
        $args = array(
            'post_type' => 'kh_event',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_kh_event_start_date',
                    'value' => array($year . '-' . $month . '-01', $year . '-' . $month . '-31'),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            )
        );

        $tax_query = array();
        if (!empty($category)) {
            $tax_query[] = array(
                'taxonomy' => 'kh_event_category',
                'field' => 'slug',
                'terms' => $category
            );
        }
        if (!empty($tag)) {
            $tax_query[] = array(
                'taxonomy' => 'kh_event_tag',
                'field' => 'slug',
                'terms' => $tag
            );
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $events = get_posts($args);
        $events_by_date = array();

        foreach ($events as $event) {
            $start_date = get_post_meta($event->ID, '_kh_event_start_date', true);
            if ($start_date) {
                if (!isset($events_by_date[$start_date])) {
                    $events_by_date[$start_date] = array();
                }
                $events_by_date[$start_date][] = $event;
            }
        }

        return apply_filters('kh_get_events_for_month', $events_by_date, $month, $year);
    }

    public function list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'category' => '',
            'tag' => '',
        ), $atts);

        ob_start();
        $this->render_list($atts);
        return ob_get_clean();
    }

    private function render_list($atts) {
        $args = array(
            'post_type' => 'kh_event',
            'posts_per_page' => intval($atts['limit']),
            'meta_key' => '_kh_event_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_kh_event_start_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            )
        );

        if (!empty($atts['category'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'kh_event_category',
                'field' => 'slug',
                'terms' => $atts['category']
            );
        }
        if (!empty($atts['tag'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'kh_event_tag',
                'field' => 'slug',
                'terms' => $atts['tag']
            );
        }

        $events = get_posts($args);
        $events = apply_filters('kh_get_events_for_list', $events, array(
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+1 year')),
        ));

        // Prepare template variables
        $template_vars = array(
            'atts' => $atts,
            'events' => $events
        );

        echo $this->load_template('event-list', $template_vars);
    }

    public function day_shortcode($atts) {
        $atts = shortcode_atts(array(
            'date' => date('Y-m-d'),
            'category' => '',
            'tag' => '',
        ), $atts);

        ob_start();
        $this->render_day($atts);
        return ob_get_clean();
    }

    private function render_day($atts) {
        $date = $atts['date'];
        $category = $atts['category'];
        $tag = $atts['tag'];

        $args = array(
            'post_type' => 'kh_event',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_kh_event_start_date',
                    'value' => $date,
                    'compare' => '=',
                    'type' => 'DATE'
                )
            )
        );

        $tax_query = array();
        if (!empty($category)) {
            $tax_query[] = array(
                'taxonomy' => 'kh_event_category',
                'field' => 'slug',
                'terms' => $category
            );
        }
        if (!empty($tag)) {
            $tax_query[] = array(
                'taxonomy' => 'kh_event_tag',
                'field' => 'slug',
                'terms' => $tag
            );
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $events = get_posts($args);

        ?>
        <div class="kh-events-day">
            <h2><?php echo date('l, F j, Y', strtotime($date)); ?></h2>
            <?php if ($events): ?>
                <?php foreach ($events as $event): ?>
                    <div class="kh-event-item">
                        <h3><a href="<?php echo get_permalink($event->ID); ?>"><?php echo get_the_title($event->ID); ?></a></h3>
                        <div class="kh-event-meta">
                            <span class="kh-event-time"><?php echo get_post_meta($event->ID, '_kh_event_start_time', true); ?> - <?php echo get_post_meta($event->ID, '_kh_event_end_time', true); ?></span>
                        </div>
                        <div class="kh-event-content"><?php echo get_the_content(null, false, $event->ID); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p><?php _e('No events on this day.', 'kh-events'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function week_shortcode($atts) {
        $atts = shortcode_atts(array(
            'date' => date('Y-m-d'),
            'category' => '',
            'tag' => '',
        ), $atts);

        ob_start();
        $this->render_week($atts);
        return ob_get_clean();
    }

    private function render_week($atts) {
        $date = $atts['date'];
        $category = $atts['category'];
        $tag = $atts['tag'];

        // Get the start of the week (Monday)
        $start_of_week = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $end_of_week = date('Y-m-d', strtotime('sunday this week', strtotime($date)));

        $args = array(
            'post_type' => 'kh_event',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_kh_event_start_date',
                    'value' => array($start_of_week, $end_of_week),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            ),
            'meta_key' => '_kh_event_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC'
        );

        $tax_query = array();
        if (!empty($category)) {
            $tax_query[] = array(
                'taxonomy' => 'kh_event_category',
                'field' => 'slug',
                'terms' => $category
            );
        }
        if (!empty($tag)) {
            $tax_query[] = array(
                'taxonomy' => 'kh_event_tag',
                'field' => 'slug',
                'terms' => $tag
            );
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $events = get_posts($args);

        // Group events by day
        $events_by_day = array();
        for ($i = 0; $i < 7; $i++) {
            $day_date = date('Y-m-d', strtotime($start_of_week . ' +' . $i . ' days'));
            $events_by_day[$day_date] = array();
        }

        foreach ($events as $event) {
            $event_date = get_post_meta($event->ID, '_kh_event_start_date', true);
            if (isset($events_by_day[$event_date])) {
                $events_by_day[$event_date][] = $event;
            }
        }

        // Prepare template variables
        $template_vars = array(
            'atts' => $atts,
            'date' => $date,
            'start_of_week' => $start_of_week,
            'end_of_week' => $end_of_week,
            'events_by_day' => $events_by_day,
            'category' => $category,
            'tag' => $tag
        );

        echo $this->load_template('calendar-week', $template_vars);
    }

    public function photo_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 12,
            'category' => '',
            'tag' => '',
            'columns' => 3,
        ), $atts);

        ob_start();
        $this->render_photo($atts);
        return ob_get_clean();
    }

    private function render_photo($atts) {
        $args = array(
            'post_type' => 'kh_event',
            'posts_per_page' => intval($atts['limit']),
            'meta_key' => '_kh_event_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_kh_event_start_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            )
        );

        if (!empty($atts['category'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'kh_event_category',
                'field' => 'slug',
                'terms' => $atts['category']
            );
        }
        if (!empty($atts['tag'])) {
            $args['tax_query'][] = array(
                'taxonomy' => 'kh_event_tag',
                'field' => 'slug',
                'terms' => $atts['tag']
            );
        }

        $events = get_posts($args);
        $columns = intval($atts['columns']);

        // Prepare template variables
        $template_vars = array(
            'atts' => $atts,
            'events' => $events,
            'columns' => $columns
        );

        echo $this->load_template('calendar-photo', $template_vars);
    }

    public function ical_shortcode($atts) {
        $atts = shortcode_atts(array(
            'event_id' => '',
            'text' => __('Add to Calendar', 'kh-events'),
            'type' => 'ical', // 'ical' or 'google'
        ), $atts);

        if (empty($atts['event_id'])) {
            return '<p>' . __('Event ID is required for calendar export.', 'kh-events') . '</p>';
        }

        $event_id = intval($atts['event_id']);
        $event = get_post($event_id);

        if (!$event || $event->post_type !== 'kh_event') {
            return '<p>' . __('Invalid event.', 'kh-events') . '</p>';
        }

        $ical_url = add_query_arg(array(
            'kh_action' => 'export_ical',
            'event_id' => $event_id,
        ), home_url());

        $google_url = $this->generate_google_calendar_url($event_id);

        ob_start();
        ?>
        <div class="kh-calendar-export">
            <?php if ($atts['type'] === 'ical' || $atts['type'] === 'both'): ?>
                <a href="<?php echo esc_url($ical_url); ?>" class="kh-ical-export" target="_blank">
                    <span class="kh-export-icon">📅</span>
                    <?php echo esc_html($atts['text']); ?> (.ics)
                </a>
            <?php endif; ?>

            <?php if ($atts['type'] === 'google' || $atts['type'] === 'both'): ?>
                <a href="<?php echo esc_url($google_url); ?>" class="kh-google-export" target="_blank">
                    <span class="kh-export-icon">📅</span>
                    <?php echo esc_html($atts['text']); ?> (Google)
                </a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function generate_google_calendar_url($event_id) {
        $event = get_post($event_id);
        if (!$event) return '#';

        $title = urlencode(get_the_title($event_id));
        $start_date = get_post_meta($event_id, '_kh_event_start_date', true);
        $start_time = get_post_meta($event_id, '_kh_event_start_time', true);
        $end_date = get_post_meta($event_id, '_kh_event_end_date', true) ?: $start_date;
        $end_time = get_post_meta($event_id, '_kh_event_end_time', true);

        // Format dates for Google Calendar
        $start_datetime = $start_date . 'T' . ($start_time ?: '00:00:00');
        $end_datetime = $end_date . 'T' . ($end_time ?: '23:59:59');

        // Convert to Google Calendar format (remove hyphens and colons)
        $start_formatted = str_replace(['-', ':'], '', $start_datetime);
        $end_formatted = str_replace(['-', ':'], '', $end_datetime);

        $description = urlencode(get_the_excerpt($event_id));
        $location = '';
        $location_id = get_post_meta($event_id, '_kh_event_location', true);
        if ($location_id) {
            $location = urlencode(get_the_title($location_id));
        }

        $url = 'https://calendar.google.com/calendar/render?action=TEMPLATE';
        $url .= '&text=' . $title;
        $url .= '&dates=' . $start_formatted . '/' . $end_formatted;
        $url .= '&details=' . $description;
        if ($location) {
            $url .= '&location=' . $location;
        }

        return $url;
    }

    public function map_shortcode($atts) {
        $atts = shortcode_atts(array(
            'height' => '400px',
            'zoom' => '',
            'category' => '',
            'tag' => '',
            'limit' => '50'
        ), $atts);

        ob_start();
        $this->render_map($atts);
        return ob_get_clean();
    }

    private function render_map($atts) {
        // Get Google Maps API key
        $maps_settings = get_option('kh_events_maps_settings', array());
        $api_key = $maps_settings['google_maps_api_key'] ?? '';

        if (empty($api_key)) {
            echo '<div class="kh-events-notice kh-events-notice-warning">';
            echo __('Google Maps API key is required to display the map. Please configure it in KH Events Settings.', 'kh-events');
            echo '</div>';
            return;
        }

        $default_zoom = $maps_settings['default_map_zoom'] ?? 10;
        $zoom = $atts['zoom'] ?: $default_zoom;

        // Get events with locations
        $events = $this->get_events_with_locations($atts);

        // Enqueue Google Maps
        wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($api_key), array(), null, true);
        wp_enqueue_script('kh-events-map', KH_EVENTS_URL . 'assets/js/events-map.js', array('jquery', 'google-maps'), KH_EVENTS_VERSION, true);

        // Prepare event data for JavaScript
        $event_data = array();
        foreach ($events as $event) {
            $location_id = get_post_meta($event->ID, '_kh_event_location', true);
            if ($location_id) {
                $lat = get_post_meta($location_id, '_kh_location_lat', true);
                $lng = get_post_meta($location_id, '_kh_location_lng', true);

                if ($lat && $lng) {
                    $event_data[] = array(
                        'id' => $event->ID,
                        'title' => get_the_title($event->ID),
                        'permalink' => get_permalink($event->ID),
                        'date' => get_post_meta($event->ID, '_kh_event_start_date', true),
                        'time' => get_post_meta($event->ID, '_kh_event_start_time', true),
                        'location' => get_the_title($location_id),
                        'lat' => $lat,
                        'lng' => $lng,
                        'address' => $this->get_location_address($location_id)
                    );
                }
            }
        }

        wp_localize_script('kh-events-map', 'kh_events_map_data', array(
            'events' => $event_data,
            'zoom' => intval($zoom),
            'center' => $this->get_map_center($event_data)
        ));

        ?>
        <div class="kh-events-map-container">
            <div id="kh-events-map" style="width: 100%; height: <?php echo esc_attr($atts['height']); ?>; border: 1px solid #ccc;"></div>
            <div id="kh-map-info-window" style="display: none;">
                <div class="kh-map-event-info">
                    <h4 class="kh-map-event-title"></h4>
                    <div class="kh-map-event-details">
                        <p class="kh-map-event-date"></p>
                        <p class="kh-map-event-time"></p>
                        <p class="kh-map-event-location"></p>
                    </div>
                    <a href="#" class="kh-map-event-link"><?php _e('View Event', 'kh-events'); ?></a>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_events_with_locations($atts) {
        $args = array(
            'post_type' => 'kh_event',
            'posts_per_page' => intval($atts['limit']),
            'meta_query' => array(
                array(
                    'key' => '_kh_event_location',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_kh_event_location',
                    'value' => '',
                    'compare' => '!='
                )
            )
        );

        // Add date filter for upcoming events only
        $args['meta_query'][] = array(
            'key' => '_kh_event_start_date',
            'value' => date('Y-m-d'),
            'compare' => '>=',
            'type' => 'DATE'
        );

        // Add taxonomy filters
        $tax_query = array();
        if (!empty($atts['category'])) {
            $tax_query[] = array(
                'taxonomy' => 'kh_event_category',
                'field' => 'slug',
                'terms' => $atts['category']
            );
        }
        if (!empty($atts['tag'])) {
            $tax_query[] = array(
                'taxonomy' => 'kh_event_tag',
                'field' => 'slug',
                'terms' => $atts['tag']
            );
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        return get_posts($args);
    }

    private function get_location_address($location_id) {
        $address_parts = array(
            get_post_meta($location_id, '_kh_location_address', true),
            get_post_meta($location_id, '_kh_location_city', true),
            get_post_meta($location_id, '_kh_location_state', true),
            get_post_meta($location_id, '_kh_location_zip', true),
            get_post_meta($location_id, '_kh_location_country', true)
        );

        return implode(', ', array_filter($address_parts));
    }

    private function get_map_center($events) {
        if (empty($events)) {
            return array('lat' => 40.7128, 'lng' => -74.0060); // Default to NYC
        }

        $total_lat = 0;
        $total_lng = 0;
        $count = count($events);

        foreach ($events as $event) {
            $total_lat += floatval($event['lat']);
            $total_lng += floatval($event['lng']);
        }

        return array(
            'lat' => $total_lat / $count,
            'lng' => $total_lng / $count
        );
    }

    public function search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => __('Search events...', 'kh-events'),
            'show_filters' => 'true',
            'limit' => 10,
        ), $atts);

        // Get filter options
        $search_filters = array(
            'categories' => get_terms(array('taxonomy' => 'kh_event_category', 'hide_empty' => false)),
            'locations' => get_posts(array('post_type' => 'kh_location', 'posts_per_page' => -1))
        );

        // Prepare template variables
        $template_vars = array(
            'atts' => $atts,
            'search_filters' => $search_filters
        );

        return $this->load_template('event-search', $template_vars);
    }

    public function submit_shortcode($atts) {
        $atts = shortcode_atts(array(
            'require_login' => 'true',
        ), $atts);

        if ($atts['require_login'] === 'true' && !is_user_logged_in()) {
            return '<div class="kh-events-notice kh-events-notice-warning">' .
                   __('You must be logged in to submit events.', 'kh-events') .
                   ' <a href="' . wp_login_url(get_permalink()) . '">' . __('Login', 'kh-events') . '</a>' .
                   '</div>';
        }

        // Check permissions for event creation
        if (class_exists('KH_Event_Permissions')) {
            $permissions = KH_Event_Permissions::instance();
            if (!$permissions->can_create_event(true)) {
                return '<div class="kh-events-notice kh-events-notice-error">' .
                       __('You do not have permission to submit events.', 'kh-events') .
                       '</div>';
            }
        }

        ob_start();
        ?>
        <div class="kh-event-submit">
            <form class="kh-submit-form" enctype="multipart/form-data">
                <div class="kh-form-section">
                    <h3><?php _e('Event Details', 'kh-events'); ?></h3>

                    <div class="kh-form-row">
                        <label for="event-title"><?php _e('Event Title *', 'kh-events'); ?></label>
                        <input type="text" id="event-title" name="title" required>
                    </div>

                    <div class="kh-form-row">
                        <label for="event-description"><?php _e('Description *', 'kh-events'); ?></label>
                        <textarea id="event-description" name="description" rows="5" required></textarea>
                    </div>

                    <div class="kh-form-row">
                        <label for="event-start-date"><?php _e('Start Date *', 'kh-events'); ?></label>
                        <input type="date" id="event-start-date" name="start_date" required>
                    </div>

                    <div class="kh-form-row">
                        <label for="event-start-time"><?php _e('Start Time *', 'kh-events'); ?></label>
                        <input type="time" id="event-start-time" name="start_time" required>
                    </div>

                    <div class="kh-form-row">
                        <label for="event-end-date"><?php _e('End Date', 'kh-events'); ?></label>
                        <input type="date" id="event-end-date" name="end_date">
                    </div>

                    <div class="kh-form-row">
                        <label for="event-end-time"><?php _e('End Time', 'kh-events'); ?></label>
                        <input type="time" id="event-end-time" name="end_time">
                    </div>
                </div>

                <div class="kh-form-section">
                    <h3><?php _e('Location', 'kh-events'); ?></h3>

                    <div class="kh-form-row">
                        <label for="event-location"><?php _e('Venue Name', 'kh-events'); ?></label>
                        <input type="text" id="event-location" name="location[name]" placeholder="<?php _e('e.g. Central Park', 'kh-events'); ?>">
                    </div>

                    <div class="kh-form-row">
                        <label for="event-address"><?php _e('Address', 'kh-events'); ?></label>
                        <input type="text" id="event-address" name="location[address]" placeholder="<?php _e('Street address', 'kh-events'); ?>">
                    </div>

                    <div class="kh-form-row kh-form-row-inline">
                        <div class="kh-form-col">
                            <label for="event-city"><?php _e('City', 'kh-events'); ?></label>
                            <input type="text" id="event-city" name="location[city]">
                        </div>
                        <div class="kh-form-col">
                            <label for="event-state"><?php _e('State', 'kh-events'); ?></label>
                            <input type="text" id="event-state" name="location[state]">
                        </div>
                        <div class="kh-form-col">
                            <label for="event-zip"><?php _e('ZIP', 'kh-events'); ?></label>
                            <input type="text" id="event-zip" name="location[zip]">
                        </div>
                    </div>
                </div>

                <div class="kh-form-section">
                    <h3><?php _e('Categories & Tags', 'kh-events'); ?></h3>

                    <div class="kh-form-row">
                        <label><?php _e('Categories', 'kh-events'); ?></label>
                        <div class="kh-checkbox-group">
                            <?php
                            $categories = get_terms(array('taxonomy' => 'kh_event_category', 'hide_empty' => false));
                            foreach ($categories as $category) {
                                echo '<label class="kh-checkbox-label">';
                                echo '<input type="checkbox" name="categories[]" value="' . esc_attr($category->term_id) . '"> ';
                                echo esc_html($category->name);
                                echo '</label>';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="kh-form-row">
                        <label for="event-tags"><?php _e('Tags', 'kh-events'); ?></label>
                        <input type="text" id="event-tags" name="tags" placeholder="<?php _e('Separate tags with commas', 'kh-events'); ?>">
                    </div>
                </div>

                <div class="kh-form-section">
                    <h3><?php _e('Featured Image', 'kh-events'); ?></h3>

                    <div class="kh-form-row">
                        <div class="kh-image-upload">
                            <input type="hidden" name="featured_image" id="featured-image-id">
                            <div class="kh-image-preview" id="image-preview">
                                <span class="kh-upload-text"><?php _e('Click to upload image', 'kh-events'); ?></span>
                            </div>
                            <button type="button" class="kh-upload-button" id="upload-image"><?php _e('Select Image', 'kh-events'); ?></button>
                        </div>
                    </div>
                </div>

                <?php do_action('kh_events_submit_form_before_submit'); ?>

                <div class="kh-form-actions">
                    <button type="submit" class="kh-submit-button"><?php _e('Submit Event', 'kh-events'); ?></button>
                    <div class="kh-submit-status"></div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function dashboard_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
        ), $atts);

        if (!is_user_logged_in()) {
            return '<div class="kh-events-notice kh-events-notice-warning">' .
                   __('You must be logged in to view your dashboard.', 'kh-events') .
                   ' <a href="' . wp_login_url(get_permalink()) . '">' . __('Login', 'kh-events') . '</a>' .
                   '</div>';
        }

        ob_start();
        ?>
        <div class="kh-events-dashboard" data-user-id="<?php echo esc_attr($atts['user_id']); ?>">
            <div class="kh-dashboard-header">
                <h2><?php _e('Events Dashboard', 'kh-events'); ?></h2>
            </div>

            <div class="kh-dashboard-stats">
                <div class="kh-stat-card">
                    <div class="kh-stat-number" id="total-events">0</div>
                    <div class="kh-stat-label"><?php _e('Total Events', 'kh-events'); ?></div>
                </div>
                <div class="kh-stat-card">
                    <div class="kh-stat-number" id="published-events">0</div>
                    <div class="kh-stat-label"><?php _e('Published', 'kh-events'); ?></div>
                </div>
                <div class="kh-stat-card">
                    <div class="kh-stat-number" id="pending-events">0</div>
                    <div class="kh-stat-label"><?php _e('Pending Review', 'kh-events'); ?></div>
                </div>
                <div class="kh-stat-card">
                    <div class="kh-stat-number" id="total-views">0</div>
                    <div class="kh-stat-label"><?php _e('Total Views', 'kh-events'); ?></div>
                </div>
            </div>

            <div class="kh-dashboard-content">
                <div class="kh-dashboard-section">
                    <h3><?php _e('Your Events', 'kh-events'); ?></h3>
                    <div class="kh-events-list" id="user-events-list">
                        <div class="kh-loading"><?php _e('Loading events...', 'kh-events'); ?></div>
                    </div>
                </div>

                <div class="kh-dashboard-section">
                    <h3><?php _e('Recent Activity', 'kh-events'); ?></h3>
                    <div class="kh-activity-feed" id="activity-feed">
                        <div class="kh-loading"><?php _e('Loading activity...', 'kh-events'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_get_dashboard_stats() {
        $user_id = intval($_POST['user_id']);

        if ($user_id !== get_current_user_id() && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'kh-events')));
            return;
        }

        // Get user's events
        $user_events = get_posts(array(
            'post_type' => 'kh_event',
            'author' => $user_id,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'pending', 'draft')
        ));

        $stats = array(
            'total_events' => count($user_events),
            'published_events' => 0,
            'pending_events' => 0,
            'total_views' => 0
        );

        foreach ($user_events as $event) {
            if ($event->post_status === 'publish') {
                $stats['published_events']++;
            } elseif ($event->post_status === 'pending') {
                $stats['pending_events']++;
            }

            // Get view count (you might want to implement this with a proper analytics plugin)
            $views = get_post_meta($event->ID, '_kh_event_views', true);
            $stats['total_views'] += intval($views);
        }

        wp_send_json_success($stats);
    }

    public function ajax_get_user_events() {
        $user_id = intval($_POST['user_id']);

        if ($user_id !== get_current_user_id() && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'kh-events')));
            return;
        }

        $user_events = get_posts(array(
            'post_type' => 'kh_event',
            'author' => $user_id,
            'posts_per_page' => 20,
            'post_status' => array('publish', 'pending', 'draft'),
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        ob_start();
        if (!empty($user_events)) {
            foreach ($user_events as $event) {
                $event_status = KH_Event_Status::instance()->get_status_display($event->ID);
                $event_date = get_post_meta($event->ID, '_kh_event_date', true);
                $event_time = get_post_meta($event->ID, '_kh_event_time', true);
                $location = get_post_meta($event->ID, '_kh_event_location', true);

                echo '<div class="kh-event-item">';
                echo '<h4><a href="' . get_permalink($event->ID) . '">' . esc_html($event->post_title) . '</a></h4>';
                echo '<div class="kh-event-meta">';
                if ($event_date) {
                    echo '<span class="kh-event-date">' . esc_html(date_i18n(get_option('date_format'), strtotime($event_date))) . '</span>';
                }
                if ($event_time) {
                    echo '<span class="kh-event-time">' . esc_html($event_time) . '</span>';
                }
                echo '<span class="kh-event-status-display kh-event-status-' . esc_attr($event_status['status']) . '" style="background-color: ' . esc_attr($event_status['color']) . ';">' . esc_html($event_status['label']) . '</span>';
                echo '</div>';
                if ($location && isset($location['name'])) {
                    echo '<div class="kh-event-location">' . esc_html($location['name']) . '</div>';
                }
                echo '</div>';
            }
        } else {
            echo '<div class="kh-no-events">No events found.</div>';
        }

        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }
}
