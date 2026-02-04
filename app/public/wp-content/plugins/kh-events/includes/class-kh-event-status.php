<?php
/**
 * KH Events Status Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Event_Status {

    private static $instance = null;

    // Define available event statuses
    private $statuses = array(
        'scheduled' => array(
            'label' => 'Scheduled',
            'description' => 'Event is scheduled to take place',
            'color' => '#4CAF50'
        ),
        'canceled' => array(
            'label' => 'Canceled',
            'description' => 'Event has been canceled',
            'color' => '#f44336'
        ),
        'postponed' => array(
            'label' => 'Postponed',
            'description' => 'Event has been postponed to a later date',
            'color' => '#FF9800'
        )
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
        add_action('add_meta_boxes', array($this, 'add_status_meta_box'));
        add_action('save_post', array($this, 'save_event_status'));
        add_filter('manage_kh_event_posts_columns', array($this, 'add_status_column'));
        add_action('manage_kh_event_posts_custom_column', array($this, 'populate_status_column'), 10, 2);
        add_filter('manage_edit-kh_event_sortable_columns', array($this, 'make_status_column_sortable'));
        add_action('restrict_manage_posts', array($this, 'add_status_filter'));
        add_filter('parse_query', array($this, 'filter_status_query'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_filter('the_content', array($this, 'add_status_to_content'));
    }

    /**
     * Add status meta box to event edit screen
     */
    public function add_status_meta_box() {
        add_meta_box(
            'kh_event_status',
            __('Event Status', 'kh-events'),
            array($this, 'render_status_meta_box'),
            'kh_event',
            'side',
            'high'
        );
    }

    /**
     * Render the status meta box
     */
    public function render_status_meta_box($post) {
        wp_nonce_field('kh_event_status_nonce', 'kh_event_status_nonce');

        $current_status = get_post_meta($post->ID, '_kh_event_status', true);
        $current_status = $current_status ? $current_status : 'scheduled';

        echo '<div class="kh-event-status-selector">';
        echo '<select name="kh_event_status" id="kh_event_status" class="widefat">';

        foreach ($this->statuses as $status_key => $status_data) {
            $selected = selected($current_status, $status_key, false);
            echo '<option value="' . esc_attr($status_key) . '" ' . $selected . ' data-color="' . esc_attr($status_data['color']) . '">';
            echo esc_html($status_data['label']);
            echo '</option>';
        }

        echo '</select>';
        echo '<p class="description" id="kh-status-description">' . esc_html($this->statuses[$current_status]['description']) . '</p>';
        echo '</div>';
    }

    /**
     * Save event status
     */
    public function save_event_status($post_id) {
        if (!isset($_POST['kh_event_status_nonce']) || !wp_verify_nonce($_POST['kh_event_status_nonce'], 'kh_event_status_nonce')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['kh_event_status']) && array_key_exists($_POST['kh_event_status'], $this->statuses)) {
            update_post_meta($post_id, '_kh_event_status', sanitize_text_field($_POST['kh_event_status']));
        }
    }

    /**
     * Add status column to events list
     */
    public function add_status_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['kh_event_status'] = __('Status', 'kh-events');
            }
        }
        return $new_columns;
    }

    /**
     * Populate status column
     */
    public function populate_status_column($column, $post_id) {
        if ($column === 'kh_event_status') {
            $status = get_post_meta($post_id, '_kh_event_status', true);
            $status = $status ? $status : 'scheduled';

            if (isset($this->statuses[$status])) {
                $status_data = $this->statuses[$status];
                echo '<span class="kh-event-status-badge" style="background-color: ' . esc_attr($status_data['color']) . '; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px;">';
                echo esc_html($status_data['label']);
                echo '</span>';
            }
        }
    }

    /**
     * Make status column sortable
     */
    public function make_status_column_sortable($columns) {
        $columns['kh_event_status'] = 'kh_event_status';
        return $columns;
    }

    /**
     * Add status filter dropdown
     */
    public function add_status_filter() {
        global $typenow;
        if ($typenow !== 'kh_event') {
            return;
        }

        $selected_status = isset($_GET['kh_event_status_filter']) ? $_GET['kh_event_status_filter'] : '';

        echo '<select name="kh_event_status_filter" id="kh_event_status_filter">';
        echo '<option value="">' . __('All Statuses', 'kh-events') . '</option>';

        foreach ($this->statuses as $status_key => $status_data) {
            $selected = selected($selected_status, $status_key, false);
            echo '<option value="' . esc_attr($status_key) . '" ' . $selected . '>';
            echo esc_html($status_data['label']);
            echo '</option>';
        }

        echo '</select>';
    }

    /**
     * Filter events by status
     */
    public function filter_status_query($query) {
        global $pagenow;
        if ($pagenow !== 'edit.php' || !isset($query->query_vars['post_type']) || $query->query_vars['post_type'] !== 'kh_event') {
            return;
        }

        if (isset($_GET['kh_event_status_filter']) && !empty($_GET['kh_event_status_filter'])) {
            $status = sanitize_text_field($_GET['kh_event_status_filter']);

            $meta_query = array(
                array(
                    'key' => '_kh_event_status',
                    'value' => $status,
                    'compare' => '='
                )
            );

            $query->set('meta_query', $meta_query);
        }
    }

    /**
     * Get event status
     */
    public function get_event_status($post_id) {
        $status = get_post_meta($post_id, '_kh_event_status', true);
        return $status ? $status : 'scheduled';
    }

    /**
     * Get status label
     */
    public function get_status_label($status) {
        return isset($this->statuses[$status]) ? $this->statuses[$status]['label'] : 'Scheduled';
    }

    /**
     * Get status color
     */
    public function get_status_color($status) {
        return isset($this->statuses[$status]) ? $this->statuses[$status]['color'] : '#4CAF50';
    }

    /**
     * Get all statuses
     */
    public function get_statuses() {
        return $this->statuses;
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        global $post;
        if ($post && $post->post_type !== 'kh_event') {
            return;
        }

        wp_enqueue_script('kh-event-status-admin', KH_EVENTS_URL . 'assets/js/event-status-admin.js', array('jquery'), KH_EVENTS_VERSION, true);
        wp_enqueue_style('kh-event-status-admin', KH_EVENTS_URL . 'assets/css/event-status-admin.css', array(), KH_EVENTS_VERSION);
    }

    /**
     * Enqueue frontend assets for status badge.
     */
    public function enqueue_frontend_scripts() {
        if ( function_exists( 'kh_events_is_builder_preview' ) && kh_events_is_builder_preview() ) {
            return;
        }
        if (!is_singular('kh_event')) {
            return;
        }

        $css = '.kh-event-status-display{display:inline-block;padding:6px 10px;border-radius:4px;color:#fff;font-weight:600;margin-bottom:10px;}';
        wp_register_style('kh-event-status-frontend', false, array(), KH_EVENTS_VERSION);
        wp_add_inline_style('kh-event-status-frontend', $css);
        wp_enqueue_style('kh-event-status-frontend');
    }

    /**
     * Add status display to single event content
     */
    public function add_status_to_content($content) {
        if (is_singular('kh_event') && in_the_loop()) {
            $status_display = $this->get_status_display(get_the_ID());
            $status_html = '<div class="kh-event-status-display kh-event-status-' . esc_attr($status_display['status']) . '" style="background-color: ' . esc_attr($status_display['color']) . ';">';
            $status_html .= esc_html($status_display['label']);
            $status_html .= '</div>';

            $content = $status_html . $content;
        }

        return $content;
    }

    /**
     * Get status for display in frontend
     */
    public function get_status_display($post_id) {
        $status = $this->get_event_status($post_id);
        $status_data = $this->statuses[$status];

        return array(
            'status' => $status,
            'label' => $status_data['label'],
            'color' => $status_data['color'],
            'description' => $status_data['description']
        );
    }
}
