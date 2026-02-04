<?php
/**
 * Week Calendar Template
 *
 * Override this template by copying it to yourtheme/kh-events/calendar-week.php
 *
 * @package KH-Events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Template variables available:
// $atts - Shortcode attributes
// $events - Array of events for the week
// $start_of_week, $end_of_week - Week date range
// $week_dates - Array of dates in the week
?>

<div class="kh-events-calendar-week" data-category="<?php echo esc_attr($atts['category']); ?>" data-tag="<?php echo esc_attr($atts['tag']); ?>">
    <div class="kh-week-navigation">
        <h2><?php printf(__('Week of %s', 'kh-events'), date('F j, Y', strtotime($start_of_week))); ?></h2>
        <div class="kh-week-nav-links">
            <a href="#" class="kh-nav-link kh-prev-week">&laquo; <?php _e('Previous Week', 'kh-events'); ?></a>
            <a href="#" class="kh-nav-link kh-next-week"><?php _e('Next Week', 'kh-events'); ?> &raquo;</a>
        </div>
    </div>

    <div class="kh-week-header">
        <?php foreach ($week_dates as $date): ?>
            <div class="kh-week-day-header">
                <div class="kh-week-day-name"><?php echo date('D', strtotime($date)); ?></div>
                <div class="kh-week-day-date"><?php echo date('M j', strtotime($date)); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="kh-week-content">
        <?php foreach ($week_dates as $date): ?>
            <div class="kh-week-day-column">
                <?php
                $day_events = isset($events[$date]) ? $events[$date] : array();
                if (!empty($day_events)):
                    foreach ($day_events as $event):
                        $event_class = 'kh-event-item';
                        if ($event['status'] !== 'publish') {
                            $event_class .= ' kh-event-' . $event['status'];
                        }
                ?>
                    <div class="<?php echo esc_attr($event_class); ?>">
                        <div class="kh-event-time">
                            <?php echo esc_html($event['start_time']); ?>
                            <?php if ($event['end_time']): ?>
                                - <?php echo esc_html($event['end_time']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="kh-event-title">
                            <a href="<?php echo esc_url($event['permalink']); ?>" title="<?php echo esc_attr($event['title']); ?>">
                                <?php echo esc_html($event['title']); ?>
                            </a>
                        </div>
                        <?php if ($event['location']): ?>
                            <div class="kh-event-location">
                                <?php echo esc_html($event['location']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php
                    endforeach;
                else:
                ?>
                    <div class="kh-no-events">
                        <?php _e('No events', 'kh-events'); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>