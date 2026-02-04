<?php
/**
 * KH Events Calendar Renderer
 *
 * Handles HTML generation for calendar views
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Calendar_Renderer {

    private $calendar;

    public function __construct() {
        $this->calendar = new KH_Events_Calendar();
    }

    /**
     * Render month view calendar
     */
    public function render_month_view($current_date, $filters = array()) {
        $date_range = $this->calendar->get_date_range($current_date, 'month');
        $events = $this->calendar->get_calendar_events($date_range['start'], $date_range['end'], $filters);

        ob_start();
        ?>
        <div class="kh-events-calendar kh-events-month-view" data-view="month" data-date="<?php echo esc_attr($current_date); ?>">
            <?php $this->render_calendar_header($current_date, 'month'); ?>

            <div class="kh-events-calendar-grid">
                <?php $this->render_month_grid($current_date, $events); ?>
            </div>

            <?php $this->render_calendar_footer(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render week view calendar
     */
    public function render_week_view($current_date, $filters = array()) {
        $date_range = $this->calendar->get_date_range($current_date, 'week');
        $events = $this->calendar->get_calendar_events($date_range['start'], $date_range['end'], $filters);

        ob_start();
        ?>
        <div class="kh-events-calendar kh-events-week-view" data-view="week" data-date="<?php echo esc_attr($current_date); ?>">
            <?php $this->render_calendar_header($current_date, 'week'); ?>

            <div class="kh-events-calendar-grid">
                <?php $this->render_week_grid($current_date, $events); ?>
            </div>

            <?php $this->render_calendar_footer(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render day view calendar
     */
    public function render_day_view($current_date, $filters = array()) {
        $events = $this->calendar->get_events_for_date($current_date);

        ob_start();
        ?>
        <div class="kh-events-calendar kh-events-day-view" data-view="day" data-date="<?php echo esc_attr($current_date); ?>">
            <?php $this->render_calendar_header($current_date, 'day'); ?>

            <div class="kh-events-calendar-day-content">
                <?php $this->render_day_events($current_date, $events); ?>
            </div>

            <?php $this->render_calendar_footer(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render list view calendar
     */
    public function render_list_view($current_date, $filters = array()) {
        $date_range = $this->calendar->get_date_range($current_date, 'list');
        $events = $this->calendar->get_calendar_events($date_range['start'], $date_range['end'], $filters);

        ob_start();
        ?>
        <div class="kh-events-calendar kh-events-list-view" data-view="list" data-date="<?php echo esc_attr($current_date); ?>">
            <?php $this->render_calendar_header($current_date, 'list'); ?>

            <div class="kh-events-calendar-list">
                <?php $this->render_event_list($events); ?>
            </div>

            <?php $this->render_calendar_footer(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render calendar header with navigation
     */
    private function render_calendar_header($current_date, $view) {
        $title = $this->calendar->get_calendar_title($current_date, $view);
        $prev_date = $this->calendar->get_navigation_dates($current_date, 'prev', $view);
        $next_date = $this->calendar->get_navigation_dates($current_date, 'next', $view);
        ?>
        <div class="kh-events-calendar-header">
            <div class="kh-events-calendar-nav">
                <button class="kh-events-nav-prev" data-direction="prev" data-date="<?php echo esc_attr($prev_date); ?>">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </button>

                <h3 class="kh-events-calendar-title"><?php echo esc_html($title); ?></h3>

                <button class="kh-events-nav-next" data-direction="next" data-date="<?php echo esc_attr($next_date); ?>">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>

            <div class="kh-events-calendar-views">
                <button class="kh-events-view-btn <?php echo $view === 'month' ? 'active' : ''; ?>" data-view="month">
                    <?php _e('Month', 'kh-events'); ?>
                </button>
                <button class="kh-events-view-btn <?php echo $view === 'week' ? 'active' : ''; ?>" data-view="week">
                    <?php _e('Week', 'kh-events'); ?>
                </button>
                <button class="kh-events-view-btn <?php echo $view === 'day' ? 'active' : ''; ?>" data-view="day">
                    <?php _e('Day', 'kh-events'); ?>
                </button>
                <button class="kh-events-view-btn <?php echo $view === 'list' ? 'active' : ''; ?>" data-view="list">
                    <?php _e('List', 'kh-events'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render calendar footer
     */
    private function render_calendar_footer() {
        ?>
        <div class="kh-events-calendar-footer">
            <div class="kh-events-calendar-filters">
                <?php $this->render_filters(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render month grid
     */
    private function render_month_grid($current_date, $events) {
        $first_day = strtotime('first day of ' . date('F Y', strtotime($current_date)));
        $last_day = strtotime('last day of ' . date('F Y', strtotime($current_date)));
        $start_of_week = get_option('start_of_week', 1);

        // Calculate start date (first day of week containing first day of month)
        $first_day_of_week = date('w', $first_day);
        $days_to_subtract = ($first_day_of_week - $start_of_week + 7) % 7;
        $grid_start = strtotime("-{$days_to_subtract} days", $first_day);

        // Calculate end date (last day of week containing last day of month)
        $last_day_of_week = date('w', $last_day);
        $days_to_add = (6 - $last_day_of_week + $start_of_week) % 7;
        $grid_end = strtotime("+{$days_to_add} days", $last_day);

        // Group events by date
        $events_by_date = array();
        foreach ($events as $event) {
            $date = $event['start_date'];
            if (!isset($events_by_date[$date])) {
                $events_by_date[$date] = array();
            }
            $events_by_date[$date][] = $event;
        }

        // Render weekday headers
        echo '<div class="kh-events-calendar-weekdays">';
        $weekdays = array(__('Sun', 'kh-events'), __('Mon', 'kh-events'), __('Tue', 'kh-events'),
                         __('Wed', 'kh-events'), __('Thu', 'kh-events'), __('Fri', 'kh-events'), __('Sat', 'kh-events'));
        for ($i = 0; $i < 7; $i++) {
            $day_index = ($start_of_week + $i) % 7;
            echo '<div class="kh-events-weekday">' . esc_html($weekdays[$day_index]) . '</div>';
        }
        echo '</div>';

        // Render calendar grid
        echo '<div class="kh-events-calendar-days">';
        $current = $grid_start;

        while ($current <= $grid_end) {
            $date_str = date('Y-m-d', $current);
            $is_current_month = (date('m', $current) === date('m', strtotime($current_date)));
            $is_today = ($date_str === date('Y-m-d'));
            $day_events = isset($events_by_date[$date_str]) ? $events_by_date[$date_str] : array();

            $classes = array('kh-events-calendar-day');
            if (!$is_current_month) $classes[] = 'kh-events-other-month';
            if ($is_today) $classes[] = 'kh-events-today';
            if (!empty($day_events)) $classes[] = 'kh-events-has-events';

            echo '<div class="' . esc_attr(implode(' ', $classes)) . '" data-date="' . esc_attr($date_str) . '">';
            echo '<div class="kh-events-day-number">' . date('j', $current) . '</div>';

            if (!empty($day_events)) {
                echo '<div class="kh-events-day-events">';
                $max_events = 3; // Show max 3 events per day
                $shown_events = array_slice($day_events, 0, $max_events);
                $remaining = count($day_events) - $max_events;

                foreach ($shown_events as $event) {
                    echo '<div class="kh-events-day-event" data-event-id="' . esc_attr($event['event_id']) . '">';
                    echo '<span class="kh-events-event-title">' . esc_html($event['title']) . '</span>';
                    echo '</div>';
                }

                if ($remaining > 0) {
                    echo '<div class="kh-events-more-events">+' . $remaining . ' ' . __('more', 'kh-events') . '</div>';
                }

                echo '</div>';
            }

            echo '</div>';

            $current = strtotime('+1 day', $current);
        }

        echo '</div>';
    }

    /**
     * Render week grid
     */
    private function render_week_grid($current_date, $events) {
        $date_range = $this->calendar->get_date_range($current_date, 'week');
        $start_date = strtotime($date_range['start']);
        $end_date = strtotime($date_range['end']);

        // Group events by date
        $events_by_date = array();
        foreach ($events as $event) {
            $date = $event['start_date'];
            if (!isset($events_by_date[$date])) {
                $events_by_date[$date] = array();
            }
            $events_by_date[$date][] = $event;
        }

        echo '<div class="kh-events-week-header">';
        $current = $start_date;
        while ($current <= $end_date) {
            $date_str = date('Y-m-d', $current);
            $is_today = ($date_str === date('Y-m-d'));
            $classes = array('kh-events-week-day-header');
            if ($is_today) $classes[] = 'kh-events-today';

            echo '<div class="' . esc_attr(implode(' ', $classes)) . '">';
            echo '<div class="kh-events-week-day-name">' . date_i18n('D', $current) . '</div>';
            echo '<div class="kh-events-week-day-number">' . date('j', $current) . '</div>';
            echo '</div>';

            $current = strtotime('+1 day', $current);
        }
        echo '</div>';

        echo '<div class="kh-events-week-content">';
        $current = $start_date;
        while ($current <= $end_date) {
            $date_str = date('Y-m-d', $current);
            $day_events = isset($events_by_date[$date_str]) ? $events_by_date[$date_str] : array();

            echo '<div class="kh-events-week-day" data-date="' . esc_attr($date_str) . '">';
            if (!empty($day_events)) {
                echo '<div class="kh-events-week-events">';
                foreach ($day_events as $event) {
                    echo '<div class="kh-events-week-event" data-event-id="' . esc_attr($event['event_id']) . '">';
                    echo '<div class="kh-events-event-time">' . esc_html($this->format_event_time($event)) . '</div>';
                    echo '<div class="kh-events-event-title">' . esc_html($event['title']) . '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</div>';

            $current = strtotime('+1 day', $current);
        }
        echo '</div>';
    }

    /**
     * Render day events
     */
    private function render_day_events($current_date, $events) {
        if (empty($events)) {
            echo '<div class="kh-events-no-events">';
            echo '<p>' . __('No events scheduled for this day.', 'kh-events') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="kh-events-day-events">';
        foreach ($events as $event) {
            ?>
            <div class="kh-events-day-event-card" data-event-id="<?php echo esc_attr($event['event_id']); ?>">
                <div class="kh-events-event-header">
                    <h4 class="kh-events-event-title">
                        <a href="<?php echo esc_url(get_permalink($event['post_id'])); ?>">
                            <?php echo esc_html($event['title']); ?>
                        </a>
                    </h4>
                    <div class="kh-events-event-time"><?php echo esc_html($this->format_event_time($event)); ?></div>
                </div>

                <?php if (!empty($event['description'])): ?>
                <div class="kh-events-event-description">
                    <?php echo wp_kses_post(wp_trim_words($event['description'], 30)); ?>
                </div>
                <?php endif; ?>

                <div class="kh-events-event-meta">
                    <?php if (!empty($event['location'])): ?>
                    <div class="kh-events-event-location">
                        <span class="dashicons dashicons-location"></span>
                        <?php echo esc_html($event['location']); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($event['price'] > 0): ?>
                    <div class="kh-events-event-price">
                        <span class="dashicons dashicons-money"></span>
                        <?php echo esc_html($event['price'] . ' ' . $event['currency']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    }

    /**
     * Render event list
     */
    private function render_event_list($events) {
        if (empty($events)) {
            echo '<div class="kh-events-no-events">';
            echo '<p>' . __('No upcoming events found.', 'kh-events') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="kh-events-list-events">';
        $current_date = '';

        foreach ($events as $event) {
            $event_date = date_i18n('Y-m-d', strtotime($event['start_date']));

            if ($event_date !== $current_date) {
                if (!empty($current_date)) {
                    echo '</div>'; // Close previous date group
                }
                $current_date = $event_date;
                echo '<div class="kh-events-list-date-group">';
                echo '<h4 class="kh-events-list-date">' . date_i18n('l, F j, Y', strtotime($event_date)) . '</h4>';
            }

            ?>
            <div class="kh-events-list-event" data-event-id="<?php echo esc_attr($event['event_id']); ?>">
                <div class="kh-events-event-time"><?php echo esc_html($this->format_event_time($event)); ?></div>
                <div class="kh-events-event-details">
                    <h5 class="kh-events-event-title">
                        <a href="<?php echo esc_url(get_permalink($event['post_id'])); ?>">
                            <?php echo esc_html($event['title']); ?>
                        </a>
                    </h5>

                    <?php if (!empty($event['description'])): ?>
                    <div class="kh-events-event-description">
                        <?php echo wp_kses_post(wp_trim_words($event['description'], 20)); ?>
                    </div>
                    <?php endif; ?>

                    <div class="kh-events-event-meta">
                        <?php if (!empty($event['location'])): ?>
                        <span class="kh-events-event-location">
                            <span class="dashicons dashicons-location"></span>
                            <?php echo esc_html($event['location']); ?>
                        </span>
                        <?php endif; ?>

                        <?php if ($event['price'] > 0): ?>
                        <span class="kh-events-event-price">
                            <span class="dashicons dashicons-money"></span>
                            <?php echo esc_html($event['price'] . ' ' . $event['currency']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
        }

        if (!empty($current_date)) {
            echo '</div>'; // Close last date group
        }

        echo '</div>';
    }

    /**
     * Render filters
     */
    private function render_filters() {
        $categories = get_terms(array(
            'taxonomy' => 'kh_event_category',
            'hide_empty' => false,
        ));

        $locations = kh_events_get_service('kh_events_db')->get_all_locations();
        ?>
        <div class="kh-events-filters">
            <select class="kh-events-filter-category">
                <option value=""><?php _e('All Categories', 'kh-events'); ?></option>
                <?php foreach ($categories as $category): ?>
                <option value="<?php echo esc_attr($category->term_id); ?>">
                    <?php echo esc_html($category->name); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select class="kh-events-filter-location">
                <option value=""><?php _e('All Locations', 'kh-events'); ?></option>
                <?php foreach ($locations as $location): ?>
                <option value="<?php echo esc_attr($location['location_id']); ?>">
                    <?php echo esc_html($location['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <button class="kh-events-filter-apply"><?php _e('Apply Filters', 'kh-events'); ?></button>
            <button class="kh-events-filter-clear"><?php _e('Clear', 'kh-events'); ?></button>
        </div>
        <?php
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
}