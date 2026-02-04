<?php
/**
 * KH Events Recurring Events Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Recurring_Events {

    private static $instance = null;

    // Recurrence patterns
    private $patterns = array(
        'daily' => array(
            'label' => 'Daily',
            'description' => 'Repeat every day'
        ),
        'weekly' => array(
            'label' => 'Weekly',
            'description' => 'Repeat every week'
        ),
        'monthly' => array(
            'label' => 'Monthly',
            'description' => 'Repeat every month'
        ),
        'yearly' => array(
            'label' => 'Yearly',
            'description' => 'Repeat every year'
        )
    );

    // Weekdays for weekly recurrence
    private $weekdays = array(
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday'
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
        add_action('add_meta_boxes', array($this, 'add_recurrence_meta_box'));
        add_action('save_post', array($this, 'save_recurrence_settings'));
        add_action('wp_ajax_kh_generate_recurring_instances', array($this, 'ajax_generate_instances'));
        add_action('wp_ajax_kh_delete_recurring_series', array($this, 'ajax_delete_series'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('kh_get_events_for_list', array($this, 'expand_recurring_events'), 10, 2);
        add_filter('kh_get_events_for_calendar', array($this, 'expand_recurring_events'), 10, 2);
        add_action('kh_event_booked', array($this, 'handle_recurring_booking'));
    }

    /**
     * Add recurrence meta box to event edit screen
     */
    public function add_recurrence_meta_box() {
        add_meta_box(
            'kh_event_recurrence',
            __('Recurring Event', 'kh-events'),
            array($this, 'render_recurrence_meta_box'),
            'kh_event',
            'normal',
            'high'
        );
    }

    /**
     * Render the recurrence meta box
     */
    public function render_recurrence_meta_box($post) {
        wp_nonce_field('kh_recurrence_nonce', 'kh_recurrence_nonce');

        $is_recurring = get_post_meta($post->ID, '_kh_event_recurring', true);
        $recurrence_pattern = get_post_meta($post->ID, '_kh_event_recurrence_pattern', true) ?: 'weekly';
        $recurrence_interval = get_post_meta($post->ID, '_kh_event_recurrence_interval', true) ?: 1;
        $recurrence_end_type = get_post_meta($post->ID, '_kh_event_recurrence_end_type', true) ?: 'never';
        $recurrence_end_date = get_post_meta($post->ID, '_kh_event_recurrence_end_date', true);
        $recurrence_count = get_post_meta($post->ID, '_kh_event_recurrence_count', true) ?: 10;
        $recurrence_weekdays = get_post_meta($post->ID, '_kh_event_recurrence_weekdays', true) ?: array();
        $recurrence_monthly_type = get_post_meta($post->ID, '_kh_event_recurrence_monthly_type', true) ?: 'date';

        ?>
        <div class="kh-recurrence-settings">
            <p>
                <label>
                    <input type="checkbox" name="kh_event_recurring" value="1" <?php checked($is_recurring, '1'); ?> />
                    <?php _e('This is a recurring event', 'kh-events'); ?>
                </label>
            </p>

            <div class="kh-recurrence-options" style="<?php echo $is_recurring ? '' : 'display:none;'; ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="kh_recurrence_pattern"><?php _e('Repeat', 'kh-events'); ?></label></th>
                        <td>
                            <select name="kh_recurrence_pattern" id="kh_recurrence_pattern">
                                <?php foreach ($this->patterns as $key => $pattern): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($recurrence_pattern, $key); ?>>
                                        <?php echo esc_html($pattern['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span id="kh-pattern-description"><?php echo esc_html($this->patterns[$recurrence_pattern]['description']); ?></span>
                        </td>
                    </tr>

                    <tr id="kh-interval-row">
                        <th><label for="kh_recurrence_interval"><?php _e('Every', 'kh-events'); ?></label></th>
                        <td>
                            <input type="number" name="kh_recurrence_interval" id="kh_recurrence_interval"
                                   value="<?php echo esc_attr($recurrence_interval); ?>" min="1" max="30" />
                            <span id="kh-interval-label"><?php echo esc_html($this->get_interval_label($recurrence_pattern, $recurrence_interval)); ?></span>
                        </td>
                    </tr>

                    <tr id="kh-weekdays-row" style="<?php echo $recurrence_pattern === 'weekly' ? '' : 'display:none;'; ?>">
                        <th><?php _e('Repeat on', 'kh-events'); ?></th>
                        <td>
                            <?php foreach ($this->weekdays as $key => $label): ?>
                                <label>
                                    <input type="checkbox" name="kh_recurrence_weekdays[]" value="<?php echo esc_attr($key); ?>"
                                           <?php checked(in_array($key, $recurrence_weekdays)); ?> />
                                    <?php echo esc_html($label); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>

                    <tr id="kh-monthly-type-row" style="<?php echo $recurrence_pattern === 'monthly' ? '' : 'display:none;'; ?>">
                        <th><?php _e('Monthly repeat', 'kh-events'); ?></th>
                        <td>
                            <label>
                                <input type="radio" name="kh_recurrence_monthly_type" value="date"
                                       <?php checked($recurrence_monthly_type, 'date'); ?> />
                                <?php _e('On the same date each month', 'kh-events'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="kh_recurrence_monthly_type" value="weekday"
                                       <?php checked($recurrence_monthly_type, 'weekday'); ?> />
                                <?php _e('On the same weekday each month', 'kh-events'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th><?php _e('End recurrence', 'kh-events'); ?></th>
                        <td>
                            <label>
                                <input type="radio" name="kh_recurrence_end_type" value="never"
                                       <?php checked($recurrence_end_type, 'never'); ?> />
                                <?php _e('Never', 'kh-events'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="kh_recurrence_end_type" value="date"
                                       <?php checked($recurrence_end_type, 'date'); ?> />
                                <?php _e('On date:', 'kh-events'); ?>
                                <input type="date" name="kh_recurrence_end_date" value="<?php echo esc_attr($recurrence_end_date); ?>" />
                            </label><br>
                            <label>
                                <input type="radio" name="kh_recurrence_end_type" value="count"
                                       <?php checked($recurrence_end_type, 'count'); ?> />
                                <?php _e('After', 'kh-events'); ?>
                                <input type="number" name="kh_recurrence_count" value="<?php echo esc_attr($recurrence_count); ?>" min="1" max="100" />
                                <?php _e('occurrences', 'kh-events'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <div class="kh-recurrence-preview">
                    <h4><?php _e('Preview', 'kh-events'); ?></h4>
                    <div id="kh-recurrence-preview-content">
                        <?php _e('Configure recurrence settings to see preview', 'kh-events'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Save recurrence settings
     */
    public function save_recurrence_settings($post_id) {
        if (!isset($_POST['kh_recurrence_nonce']) || !wp_verify_nonce($_POST['kh_recurrence_nonce'], 'kh_recurrence_nonce')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $is_recurring = isset($_POST['kh_event_recurring']) ? '1' : '0';
        update_post_meta($post_id, '_kh_event_recurring', $is_recurring);

        if ($is_recurring === '1') {
            update_post_meta($post_id, '_kh_event_recurrence_pattern', sanitize_text_field($_POST['kh_recurrence_pattern'] ?? 'weekly'));
            update_post_meta($post_id, '_kh_event_recurrence_interval', intval($_POST['kh_recurrence_interval'] ?? 1));
            update_post_meta($post_id, '_kh_event_recurrence_end_type', sanitize_text_field($_POST['kh_recurrence_end_type'] ?? 'never'));
            update_post_meta($post_id, '_kh_event_recurrence_end_date', sanitize_text_field($_POST['kh_recurrence_end_date'] ?? ''));
            update_post_meta($post_id, '_kh_event_recurrence_count', intval($_POST['kh_recurrence_count'] ?? 10));
            update_post_meta($post_id, '_kh_event_recurrence_weekdays', array_map('sanitize_text_field', $_POST['kh_recurrence_weekdays'] ?? array()));
            update_post_meta($post_id, '_kh_event_recurrence_monthly_type', sanitize_text_field($_POST['kh_recurrence_monthly_type'] ?? 'date'));

            // Generate recurring instances
            $this->generate_recurring_instances($post_id);
        } else {
            // Clean up recurring data
            $this->delete_recurring_instances($post_id);
            delete_post_meta($post_id, '_kh_event_recurrence_pattern');
            delete_post_meta($post_id, '_kh_event_recurrence_interval');
            delete_post_meta($post_id, '_kh_event_recurrence_end_type');
            delete_post_meta($post_id, '_kh_event_recurrence_end_date');
            delete_post_meta($post_id, '_kh_event_recurrence_count');
            delete_post_meta($post_id, '_kh_event_recurrence_weekdays');
            delete_post_meta($post_id, '_kh_event_recurrence_monthly_type');
        }
    }

    /**
     * Generate recurring event instances
     */
    private function generate_recurring_instances($parent_id) {
        // Delete existing instances first
        $this->delete_recurring_instances($parent_id);

        $pattern = get_post_meta($parent_id, '_kh_event_recurrence_pattern', true);
        $interval = get_post_meta($parent_id, '_kh_event_recurrence_interval', true);
        $end_type = get_post_meta($parent_id, '_kh_event_recurrence_end_type', true);
        $end_date = get_post_meta($parent_id, '_kh_event_recurrence_end_date', true);
        $count = get_post_meta($parent_id, '_kh_event_recurrence_count', true);
        $weekdays = get_post_meta($parent_id, '_kh_event_recurrence_weekdays', true);
        $monthly_type = get_post_meta($parent_id, '_kh_event_recurrence_monthly_type', true);

        $start_date = get_post_meta($parent_id, '_kh_event_start_date', true);
        if (!$start_date) return;

        $start_datetime = new DateTime($start_date);
        $instances = array();

        // Generate up to 100 instances to prevent infinite loops
        $max_instances = 100;
        $current_date = clone $start_datetime;
        $instance_count = 0;

        while ($instance_count < $max_instances) {
            // Check end conditions
            if ($end_type === 'date' && $end_date && $current_date > new DateTime($end_date)) {
                break;
            }
            if ($end_type === 'count' && $instance_count >= $count) {
                break;
            }

            // Skip the original date (it's the parent event)
            if ($instance_count > 0) {
                $instances[] = $current_date->format('Y-m-d');
            }

            // Calculate next occurrence
            $current_date = $this->get_next_occurrence($current_date, $pattern, $interval, $weekdays, $monthly_type);
            $instance_count++;

            // Safety check
            if (!$current_date) break;
        }

        // Store instances
        update_post_meta($parent_id, '_kh_recurring_instances', $instances);
    }

    /**
     * Get next occurrence based on pattern
     */
    private function get_next_occurrence($current_date, $pattern, $interval, $weekdays, $monthly_type) {
        $next_date = clone $current_date;

        switch ($pattern) {
            case 'daily':
                $next_date->modify("+{$interval} day");
                break;

            case 'weekly':
                if (!empty($weekdays)) {
                    $current_weekday = strtolower($next_date->format('l'));
                    $weekday_keys = array_keys($this->weekdays);
                    $current_index = array_search($current_weekday, $weekday_keys);

                    // Find next weekday in the list
                    $next_index = ($current_index + 1) % count($weekday_keys);
                    $days_ahead = ($next_index - $current_index + 7) % 7;
                    if ($days_ahead === 0) $days_ahead = 7;

                    $next_date->modify("+{$days_ahead} days");
                } else {
                    $next_date->modify("+{$interval} week");
                }
                break;

            case 'monthly':
                if ($monthly_type === 'weekday') {
                    // Complex logic for same weekday each month
                    $current_weekday = $next_date->format('l');
                    $current_week_of_month = ceil($next_date->format('j') / 7);

                    $next_date->modify("+{$interval} month");
                    $next_date->modify('first day of this month');

                    // Find the same weekday and week of month
                    $target_weekday = $next_date->format('N'); // 1 = Monday, 7 = Sunday
                    $current_weekday_num = $next_date->format('N');

                    $days_diff = ($target_weekday - $current_weekday_num + 7) % 7;
                    $next_date->modify("+{$days_diff} days");

                    // Adjust to correct week of month
                    if ($current_week_of_month > 1) {
                        $next_date->modify('+' . ($current_week_of_month - 1) . ' weeks');
                    }
                } else {
                    $next_date->modify("+{$interval} month");
                }
                break;

            case 'yearly':
                $next_date->modify("+{$interval} year");
                break;
        }

        return $next_date;
    }

    /**
     * Delete recurring instances
     */
    private function delete_recurring_instances($parent_id) {
        delete_post_meta($parent_id, '_kh_recurring_instances');
    }

    /**
     * Expand recurring events in queries
     */
    public function expand_recurring_events($events, $args) {
        $expanded_events = array();

        foreach ($events as $event) {
            $expanded_events[] = $event;

            // Check if this is a recurring event
            $is_recurring = get_post_meta($event->ID, '_kh_event_recurring', true);
            if ($is_recurring !== '1') continue;

            $instances = get_post_meta($event->ID, '_kh_recurring_instances', true);
            if (!is_array($instances)) continue;

            $start_date = get_post_meta($event->ID, '_kh_event_start_date', true);
            $end_date = $args['end_date'] ?? date('Y-m-d', strtotime('+1 year'));

            foreach ($instances as $instance_date) {
                if ($instance_date >= $args['start_date'] && $instance_date <= $end_date) {
                    // Create a virtual event instance
                    $instance_event = clone $event;
                    $instance_event->ID = $event->ID . '_' . $instance_date;
                    $instance_event->recurring_parent_id = $event->ID;
                    $instance_event->recurring_date = $instance_date;

                    // Update the date meta for this instance
                    $instance_event->_kh_event_start_date = $instance_date;

                    $expanded_events[] = $instance_event;
                }
            }
        }

        return $expanded_events;
    }

    /**
     * Handle recurring event bookings
     */
    public function handle_recurring_booking($booking_data) {
        // Logic for handling bookings on recurring events
        // This would need to be implemented based on how you want to handle
        // bookings for recurring events (book entire series vs individual instances)
    }

    /**
     * AJAX generate instances
     */
    public function ajax_generate_instances() {
        check_ajax_referer('kh_recurrence_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_die('Unauthorized');
        }

        $this->generate_recurring_instances($post_id);

        $instances = get_post_meta($post_id, '_kh_recurring_instances', true);
        wp_send_json_success(array('instances' => $instances));
    }

    /**
     * AJAX delete series
     */
    public function ajax_delete_series() {
        check_ajax_referer('kh_recurrence_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_die('Unauthorized');
        }

        $this->delete_recurring_instances($post_id);
        wp_send_json_success();
    }

    /**
     * Get interval label
     */
    private function get_interval_label($pattern, $interval) {
        $labels = array(
            'daily' => _n('day', 'days', $interval, 'kh-events'),
            'weekly' => _n('week', 'weeks', $interval, 'kh-events'),
            'monthly' => _n('month', 'months', $interval, 'kh-events'),
            'yearly' => _n('year', 'years', $interval, 'kh-events')
        );

        return $labels[$pattern] ?? '';
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

        wp_enqueue_script('kh-recurring-events-admin', KH_EVENTS_URL . 'assets/js/recurring-events-admin.js', array('jquery'), KH_EVENTS_VERSION, true);
        wp_enqueue_style('kh-recurring-events-admin', KH_EVENTS_URL . 'assets/css/recurring-events-admin.css', array(), KH_EVENTS_VERSION);

        wp_localize_script('kh-recurring-events-admin', 'kh_recurrence_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kh_recurrence_nonce')
        ));
    }

    /**
     * Get patterns
     */
    public function get_patterns() {
        return $this->patterns;
    }

    /**
     * Check if event is recurring
     */
    public function is_recurring($event_id) {
        return get_post_meta($event_id, '_kh_event_recurring', true) === '1';
    }

    /**
     * Get recurring instances
     */
    public function get_instances($event_id) {
        return get_post_meta($event_id, '_kh_recurring_instances', true) ?: array();
    }
}