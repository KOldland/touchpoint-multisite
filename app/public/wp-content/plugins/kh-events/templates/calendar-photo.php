<?php
/**
 * Photo Grid Template
 *
 * Override this template by copying it to yourtheme/kh-events/calendar-photo.php
 *
 * @package KH-Events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Template variables available:
// $atts - Shortcode attributes
// $events - Array of events
// $columns - Number of columns for grid
?>

<div class="kh-events-photo-grid" data-columns="<?php echo esc_attr($atts['columns']); ?>">
    <?php if (!empty($events)): ?>
        <?php foreach ($events as $event): ?>
            <div class="kh-photo-event-item">
                <div class="kh-photo-event-image">
                    <?php if (has_post_thumbnail($event->ID)): ?>
                        <a href="<?php echo esc_url(get_permalink($event->ID)); ?>">
                            <?php echo get_the_post_thumbnail($event->ID, 'medium', array('alt' => get_the_title($event->ID))); ?>
                        </a>
                    <?php else: ?>
                        <div class="kh-no-image">
                            <span><?php _e('No Image', 'kh-events'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="kh-photo-event-content">
                    <h3 class="kh-photo-event-title">
                        <a href="<?php echo esc_url(get_permalink($event->ID)); ?>">
                            <?php echo esc_html(get_the_title($event->ID)); ?>
                        </a>
                    </h3>

                    <div class="kh-photo-event-meta">
                        <div class="kh-photo-event-date">
                            <?php echo esc_html(get_post_meta($event->ID, '_kh_event_start_date', true)); ?>
                            <?php if (get_post_meta($event->ID, '_kh_event_start_time', true)): ?>
                                at <?php echo esc_html(get_post_meta($event->ID, '_kh_event_start_time', true)); ?>
                            <?php endif; ?>
                        </div>

                        <?php
                        $location_id = get_post_meta($event->ID, '_kh_event_location', true);
                        if ($location_id):
                        ?>
                            <div class="kh-photo-event-location">
                                <?php echo esc_html(get_the_title($location_id)); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="kh-photo-event-excerpt">
                        <?php echo wp_trim_words(get_the_excerpt($event->ID), 15); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="kh-no-events">
            <p><?php _e('No upcoming events found.', 'kh-events'); ?></p>
        </div>
    <?php endif; ?>
</div>