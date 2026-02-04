<?php
/**
 * Single Event Template
 *
 * Override this template by copying it to yourtheme/kh-events/single-event.php
 *
 * @package KH-Events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Template variables available:
// $event - Event post object
// $event_meta - Event metadata array
?>

<article class="kh-single-event">
    <header class="kh-event-header">
        <?php if (has_post_thumbnail()): ?>
            <div class="kh-event-featured-image">
                <?php the_post_thumbnail('large'); ?>
            </div>
        <?php endif; ?>

        <h1 class="kh-event-title"><?php the_title(); ?></h1>

        <div class="kh-event-meta">
            <div class="kh-event-date">
                <span class="kh-meta-icon">📅</span>
                <span class="kh-meta-label"><?php _e('Date:', 'kh-events'); ?></span>
                <time datetime="<?php echo esc_attr($event_meta['start_date_iso']); ?>">
                    <?php echo esc_html($event_meta['start_date_formatted']); ?>
                    <?php if ($event_meta['start_time']): ?>
                        at <?php echo esc_html($event_meta['start_time']); ?>
                    <?php endif; ?>
                </time>
                <?php if ($event_meta['end_date'] && $event_meta['end_date'] !== $event_meta['start_date']): ?>
                    <span class="kh-meta-separator">-</span>
                    <time datetime="<?php echo esc_attr($event_meta['end_date_iso']); ?>">
                        <?php echo esc_html($event_meta['end_date_formatted']); ?>
                        <?php if ($event_meta['end_time']): ?>
                            at <?php echo esc_html($event_meta['end_time']); ?>
                        <?php endif; ?>
                    </time>
                <?php endif; ?>
            </div>

            <?php if ($event_meta['location']): ?>
                <div class="kh-event-location">
                    <span class="kh-meta-icon">📍</span>
                    <span class="kh-meta-label"><?php _e('Location:', 'kh-events'); ?></span>
                    <address><?php echo esc_html($event_meta['location']); ?></address>
                    <?php if ($event_meta['location_address']): ?>
                        <br><small><?php echo esc_html($event_meta['location_address']); ?></small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php
            $categories = get_the_terms(get_the_ID(), 'kh_event_category');
            if ($categories && !is_wp_error($categories)):
            ?>
                <div class="kh-event-categories">
                    <span class="kh-meta-icon">🏷️</span>
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

            <?php if ($event_meta['organizer']): ?>
                <div class="kh-event-organizer">
                    <span class="kh-meta-icon">👤</span>
                    <span class="kh-meta-label"><?php _e('Organizer:', 'kh-events'); ?></span>
                    <?php echo esc_html($event_meta['organizer']); ?>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="kh-event-content">
        <?php the_content(); ?>
    </div>

    <?php if ($event_meta['booking_enabled'] && class_exists('KH_Event_Bookings')): ?>
        <div id="kh-booking-form" class="kh-event-booking">
            <h3><?php _e('Book This Event', 'kh-events'); ?></h3>
            <?php echo do_shortcode('[kh_event_booking event_id="' . get_the_ID() . '"]'); ?>
        </div>
    <?php endif; ?>

    <?php if ($event_meta['registration_url']): ?>
        <div class="kh-event-registration">
            <a href="<?php echo esc_url($event_meta['registration_url']); ?>" class="kh-btn kh-btn-primary" target="_blank">
                <?php _e('Register Now', 'kh-events'); ?>
            </a>
        </div>
    <?php endif; ?>

    <footer class="kh-event-footer">
        <?php if ($event_meta['ical_url']): ?>
            <div class="kh-event-export">
                <a href="<?php echo esc_url($event_meta['ical_url']); ?>" class="kh-btn kh-btn-secondary">
                    <?php _e('Add to Calendar', 'kh-events'); ?>
                </a>
            </div>
        <?php endif; ?>

        <div class="kh-event-share">
            <span class="kh-share-label"><?php _e('Share:', 'kh-events'); ?></span>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink()); ?>" target="_blank" class="kh-share-facebook">Facebook</a>
            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(get_permalink()); ?>&text=<?php echo urlencode(get_the_title()); ?>" target="_blank" class="kh-share-twitter">Twitter</a>
        </div>
    </footer>
</article>