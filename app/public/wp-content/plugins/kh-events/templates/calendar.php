<?php
/**
 * Calendar Template
 *
 * Override this template by copying it to yourtheme/kh-events/calendar.php
 *
 * @package KH-Events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Template variables available:
// $atts - Shortcode attributes
// $events - Array of events for the current month
// $month, $year - Current month/year
// $prev_month, $prev_year, $next_month, $next_year - Navigation data
// $first_day, $days_in_month, $day_of_week - Calendar calculation data
?>

<div class="kh-events-calendar" data-category="<?php echo esc_attr($atts['category']); ?>" data-tag="<?php echo esc_attr($atts['tag']); ?>">
    <div class="kh-calendar-navigation">
        <a href="#" class="kh-nav-link" data-month="<?php echo $prev_month; ?>" data-year="<?php echo $prev_year; ?>">&laquo; <?php _e('Previous', 'kh-events'); ?></a>
        <h2><?php echo date('F Y', $first_day); ?></h2>
        <a href="#" class="kh-nav-link" data-month="<?php echo $next_month; ?>" data-year="<?php echo $next_year; ?>"><?php _e('Next', 'kh-events'); ?> &raquo;</a>
    </div>

    <table class="kh-calendar-table">
        <thead>
            <tr>
                <th><?php _e('Sun', 'kh-events'); ?></th>
                <th><?php _e('Mon', 'kh-events'); ?></th>
                <th><?php _e('Tue', 'kh-events'); ?></th>
                <th><?php _e('Wed', 'kh-events'); ?></th>
                <th><?php _e('Thu', 'kh-events'); ?></th>
                <th><?php _e('Fri', 'kh-events'); ?></th>
                <th><?php _e('Sat', 'kh-events'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $day = 1;
            for ($week = 0; $week < 6; $week++) {
                echo '<tr>';
                for ($weekday = 0; $weekday < 7; $weekday++) {
                    if ($week == 0 && $weekday < $day_of_week) {
                        echo '<td class="kh-empty-cell"></td>';
                    } elseif ($day > $days_in_month) {
                        echo '<td class="kh-empty-cell"></td>';
                    } else {
                        $date_key = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $day_events = isset($events[$date_key]) ? $events[$date_key] : array();
                        echo '<td class="kh-day-cell">';
                        echo '<div class="kh-day-number">' . $day . '</div>';
                        foreach ($day_events as $event) {
                            $event_class = 'kh-event-link';
                            if ($event['status'] !== 'publish') {
                                $event_class .= ' kh-event-' . $event['status'];
                            }
                            echo '<a href="' . esc_url($event['permalink']) . '" class="' . esc_attr($event_class) . '" title="' . esc_attr($event['title']) . '">';
                            echo esc_html($event['title']);
                            echo '</a>';
                        }
                        echo '</td>';
                        $day++;
                    }
                }
                echo '</tr>';
                if ($day > $days_in_month) break;
            }
            ?>
        </tbody>
    </table>
</div>