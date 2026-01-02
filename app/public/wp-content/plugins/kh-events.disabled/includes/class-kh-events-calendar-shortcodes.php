<?php
/**
 * KH Events Calendar Shortcodes
 *
 * Handles calendar shortcode rendering and display
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Calendar_Shortcodes {

    /**
     * Render calendar shortcode
     */
    public function render_calendar($atts) {
        $atts = shortcode_atts(array(
            'view' => 'month',
            'categories' => '',
            'locations' => '',
            'limit' => 0,
            'show_filters' => 'true',
            'theme' => 'default',
            'height' => '600px'
        ), $atts);

        // Validate view
        $valid_views = array('month', 'week', 'day', 'list');
        if (!in_array($atts['view'], $valid_views)) {
            $atts['view'] = 'month';
        }

        // Parse categories and locations
        $atts['categories'] = $this->parse_ids($atts['categories']);
        $atts['locations'] = $this->parse_ids($atts['locations']);
        $atts['show_filters'] = $atts['show_filters'] === 'true';

        ob_start();
        $this->render_calendar_html($atts);
        return ob_get_clean();
    }

    /**
     * Parse comma-separated IDs
     */
    private function parse_ids($ids_string) {
        if (empty($ids_string)) {
            return array();
        }

        $ids = explode(',', $ids_string);
        return array_map('intval', array_filter($ids));
    }

    /**
     * Render calendar HTML
     */
    private function render_calendar_html($atts) {
        $calendar_id = 'kh-calendar-' . uniqid();

        ?>
        <div class="kh-events-calendar-wrapper" id="<?php echo esc_attr($calendar_id); ?>" data-view="<?php echo esc_attr($atts['view']); ?>">
            <?php if ($atts['show_filters']): ?>
                <?php $this->render_calendar_filters($atts, $calendar_id); ?>
            <?php endif; ?>

            <?php $this->render_calendar_navigation($atts, $calendar_id); ?>

            <div class="kh-events-calendar-container" style="height: <?php echo esc_attr($atts['height']); ?>;">
                <div class="kh-events-calendar-loading">
                    <?php _e('Loading calendar...', 'kh-events'); ?>
                </div>
            </div>

            <?php $this->render_calendar_templates(); ?>
        </div>
        <?php
    }

    /**
     * Render calendar filters
     */
    private function render_calendar_filters($atts, $calendar_id) {
        ?>
        <div class="kh-events-calendar-filters">
            <div class="kh-events-calendar-filter-group">
                <label for="<?php echo esc_attr($calendar_id); ?>-categories"><?php _e('Categories:', 'kh-events'); ?></label>
                <select id="<?php echo esc_attr($calendar_id); ?>-categories" multiple class="kh-events-calendar-filter">
                    <?php $this->render_category_options($atts['categories']); ?>
                </select>
            </div>

            <div class="kh-events-calendar-filter-group">
                <label for="<?php echo esc_attr($calendar_id); ?>-locations"><?php _e('Locations:', 'kh-events'); ?></label>
                <select id="<?php echo esc_attr($calendar_id); ?>-locations" multiple class="kh-events-calendar-filter">
                    <?php $this->render_location_options($atts['locations']); ?>
                </select>
            </div>

            <button type="button" class="kh-events-calendar-apply-filters button">
                <?php _e('Apply Filters', 'kh-events'); ?>
            </button>
        </div>
        <?php
    }

    /**
     * Render category options
     */
    private function render_category_options($selected = array()) {
        $categories = get_terms(array(
            'taxonomy' => 'kh_event_category',
            'hide_empty' => false,
        ));

        foreach ($categories as $category) {
            $selected_attr = in_array($category->term_id, $selected) ? ' selected' : '';
            echo '<option value="' . esc_attr($category->term_id) . '"' . $selected_attr . '>' . esc_html($category->name) . '</option>';
        }
    }

    /**
     * Render location options
     */
    private function render_location_options($selected = array()) {
        $locations = get_posts(array(
            'post_type' => 'kh_location',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ));

        foreach ($locations as $location) {
            $selected_attr = in_array($location->ID, $selected) ? ' selected' : '';
            echo '<option value="' . esc_attr($location->ID) . '"' . $selected_attr . '>' . esc_html($location->post_title) . '</option>';
        }
    }

    /**
     * Render calendar navigation
     */
    private function render_calendar_navigation($atts, $calendar_id) {
        $current_date = current_time('Y-m-d');
        $current_month = date('F Y', strtotime($current_date));

        ?>
        <div class="kh-events-calendar-navigation">
            <div class="kh-events-calendar-nav-left">
                <button type="button" class="kh-events-calendar-prev" title="<?php esc_attr_e('Previous', 'kh-events'); ?>">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </button>

                <button type="button" class="kh-events-calendar-today" title="<?php esc_attr_e('Today', 'kh-events'); ?>">
                    <?php _e('Today', 'kh-events'); ?>
                </button>

                <button type="button" class="kh-events-calendar-next" title="<?php esc_attr_e('Next', 'kh-events'); ?>">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>

            <div class="kh-events-calendar-title">
                <h3><?php echo esc_html($current_month); ?></h3>
            </div>

            <div class="kh-events-calendar-nav-right">
                <div class="kh-events-calendar-view-selector">
                    <button type="button" class="kh-events-calendar-view-button active" data-view="month">
                        <?php _e('Month', 'kh-events'); ?>
                    </button>
                    <button type="button" class="kh-events-calendar-view-button" data-view="week">
                        <?php _e('Week', 'kh-events'); ?>
                    </button>
                    <button type="button" class="kh-events-calendar-view-button" data-view="day">
                        <?php _e('Day', 'kh-events'); ?>
                    </button>
                    <button type="button" class="kh-events-calendar-view-button" data-view="list">
                        <?php _e('List', 'kh-events'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render calendar templates
     */
    private function render_calendar_templates() {
        ?>
        <script type="text/template" id="kh-calendar-event-template">
            <div class="kh-calendar-event" data-event-id="<%= id %>">
                <div class="kh-calendar-event-title"><%= title %></div>
                <% if (extendedProps.time) { %>
                    <div class="kh-calendar-event-time"><%= extendedProps.time %></div>
                <% } %>
                <% if (extendedProps.location) { %>
                    <div class="kh-calendar-event-location"><%= extendedProps.location %></div>
                <% } %>
            </div>
        </script>

        <script type="text/template" id="kh-calendar-tooltip-template">
            <div class="kh-calendar-tooltip">
                <h4><%= title %></h4>
                <div class="kh-calendar-tooltip-meta">
                    <% if (extendedProps.time) { %>
                        <div class="kh-calendar-tooltip-time">
                            <span class="dashicons dashicons-clock"></span> <%= extendedProps.time %>
                        </div>
                    <% } %>
                    <% if (extendedProps.location) { %>
                        <div class="kh-calendar-tooltip-location">
                            <span class="dashicons dashicons-location"></span> <%= extendedProps.location %>
                        </div>
                    <% } %>
                </div>
                <% if (extendedProps.description) { %>
                    <div class="kh-calendar-tooltip-description"><%= extendedProps.description %></div>
                <% } %>
                <a href="<%= url %>" class="kh-calendar-tooltip-link"><?php _e('View Event', 'kh-events'); ?></a>
            </div>
        </script>
        <?php
    }
}