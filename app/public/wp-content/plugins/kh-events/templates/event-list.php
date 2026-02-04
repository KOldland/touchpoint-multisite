<?php
/**
 * Event List Template
 *
 * Override this template by copying it to yourtheme/kh-events/event-list.php
 *
 * @package KH-Events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Template variables available:
// $atts - Shortcode attributes
// $events - Array of event posts
// $pagination - Pagination data (if applicable)
?>

<div class="kh-events-list">
    <?php if (!empty($events)): ?>
        <?php foreach ($events as $event): ?>
            <div class="kh-event-list-item">
                <div class="kh-event-list-content">
                    <h3 class="kh-event-list-title">
                        <a href="<?php echo esc_url(get_permalink($event->ID)); ?>">
                            <?php echo esc_html(get_the_title($event->ID)); ?>
                        </a>
                    </h3>

                    <div class="kh-event-list-meta">
                        <div class="kh-event-list-date">
                            <span class="kh-meta-label"><?php _e('Date:', 'kh-events'); ?></span>
                            <?php echo esc_html(get_post_meta($event->ID, '_kh_event_start_date', true)); ?>
                            <?php if (get_post_meta($event->ID, '_kh_event_start_time', true)): ?>
                                at <?php echo esc_html(get_post_meta($event->ID, '_kh_event_start_time', true)); ?>
                            <?php endif; ?>
                        </div>

                        <?php
                        $location_id = get_post_meta($event->ID, '_kh_event_location', true);
                        if ($location_id):
                        ?>
                            <div class="kh-event-list-location">
                                <span class="kh-meta-label"><?php _e('Location:', 'kh-events'); ?></span>
                                <?php echo esc_html(get_the_title($location_id)); ?>
                            </div>
                        <?php endif; ?>

                        <?php
                        $categories = get_the_terms($event->ID, 'kh_event_category');
                        if ($categories && !is_wp_error($categories)):
                        ?>
                            <div class="kh-event-list-categories">
                                <span class="kh-meta-label"><?php _e('Categories:', 'kh-events'); ?></span>
                                <?php
                                $category_links = array();
                                foreach ($categories as $category) {
                                    $category_links[] = '<a href="' . esc_url(get_term_link($category)) . '">' . esc_html($category->name) . '</a>';
                                }
                                echo implode(', ', $category_links);
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="kh-event-list-excerpt">
                        <?php echo wp_trim_words(get_the_excerpt($event->ID), 30); ?>
                    </div>

                    <div class="kh-event-list-actions">
                        <a href="<?php echo esc_url(get_permalink($event->ID)); ?>" class="kh-btn kh-btn-primary">
                            <?php _e('View Details', 'kh-events'); ?>
                        </a>

                        <?php
                        $booking_enabled = get_post_meta($event->ID, '_kh_event_enable_booking', true);
                        if ($booking_enabled && class_exists('KH_Event_Bookings')):
                        ?>
                            <a href="<?php echo esc_url(get_permalink($event->ID) . '#kh-booking-form'); ?>" class="kh-btn kh-btn-secondary">
                                <?php _e('Book Now', 'kh-events'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (has_post_thumbnail($event->ID)): ?>
                    <div class="kh-event-list-image">
                        <a href="<?php echo esc_url(get_permalink($event->ID)); ?>">
                            <?php echo get_the_post_thumbnail($event->ID, 'medium', array('alt' => get_the_title($event->ID))); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
            <div class="kh-events-pagination">
                <?php
                echo paginate_links(array(
                    'total' => $pagination['total_pages'],
                    'current' => $pagination['current_page'],
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '?paged=%#%',
                    'prev_text' => __('&laquo; Previous', 'kh-events'),
                    'next_text' => __('Next &raquo;', 'kh-events'),
                ));
                ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="kh-no-events">
            <p><?php _e('No events found.', 'kh-events'); ?></p>
        </div>
    <?php endif; ?>
</div>