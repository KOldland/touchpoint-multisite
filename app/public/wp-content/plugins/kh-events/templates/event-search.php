<?php
/**
 * Event Search Template
 *
 * Override this template by copying it to yourtheme/kh-events/event-search.php
 *
 * @package KH-Events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Template variables available:
// $search_query - Search query string
// $search_results - Array of found events
// $search_filters - Available filters (categories, dates, locations)
// $pagination - Pagination data
?>

<div class="kh-events-search" data-limit="<?php echo esc_attr($atts['limit']); ?>">
    <div class="kh-search-form">
        <div class="kh-search-input-wrapper">
            <input type="text" class="kh-search-input" placeholder="<?php echo esc_attr($atts['placeholder']); ?>">
            <button type="button" class="kh-search-button">
                <span class="kh-search-icon">🔍</span>
            </button>
        </div>

        <?php if ($atts['show_filters'] === 'true'): ?>
        <div class="kh-search-filters">
            <div class="kh-filter-row">
                <select class="kh-category-filter">
                    <option value=""><?php _e('All Categories', 'kh-events'); ?></option>
                    <?php
                    foreach ($search_filters['categories'] as $category) {
                        echo '<option value="' . esc_attr($category->slug) . '">' . esc_html($category->name) . '</option>';
                    }
                    ?>
                </select>

                <select class="kh-tag-filter">
                    <option value=""><?php _e('All Tags', 'kh-events'); ?></option>
                    <?php
                    $tags = get_terms(array('taxonomy' => 'kh_event_tag', 'hide_empty' => false));
                    foreach ($tags as $tag) {
                        echo '<option value="' . esc_attr($tag->slug) . '">' . esc_html($tag->name) . '</option>';
                    }
                    ?>
                </select>

                <select class="kh-status-filter">
                    <option value=""><?php _e('All Statuses', 'kh-events'); ?></option>
                    <?php
                    if (class_exists('KH_Event_Status')) {
                        $statuses = KH_Event_Status::instance()->get_statuses();
                        foreach ($statuses as $status_key => $status_data) {
                            echo '<option value="' . esc_attr($status_key) . '">' . esc_html($status_data['label']) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="kh-filter-row">
                <input type="text" class="kh-location-filter" placeholder="<?php _e('Location', 'kh-events'); ?>">

                <input type="date" class="kh-start-date-filter" placeholder="<?php _e('Start Date', 'kh-events'); ?>">

                <input type="date" class="kh-end-date-filter" placeholder="<?php _e('End Date', 'kh-events'); ?>">
            </div>

            <button type="button" class="kh-clear-filters"><?php _e('Clear Filters', 'kh-events'); ?></button>
        </div>
        <?php endif; ?>
    </div>

    <div class="kh-search-results">
        <div class="kh-search-status"><?php _e('Enter search terms to find events.', 'kh-events'); ?></div>
        <div class="kh-results-container"></div>
    </div>
</div>