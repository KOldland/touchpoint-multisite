<?php
/**
 * Event Filters Widget
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Event_Filters_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'kh_event_filters',
            __('KH Event Filters', 'kh-events'),
            array('description' => __('Filter events by category and tag', 'kh-events'))
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        $current_category = isset($_GET['kh_event_category']) ? sanitize_text_field($_GET['kh_event_category']) : '';
        $current_tag = isset($_GET['kh_event_tag']) ? sanitize_text_field($_GET['kh_event_tag']) : '';

        ?>
        <form method="get" action="<?php echo esc_url(get_permalink()); ?>" class="kh-event-filters">
            <p>
                <label for="kh_event_category"><?php _e('Category:', 'kh-events'); ?></label>
                <?php wp_dropdown_categories(array(
                    'taxonomy' => 'kh_event_category',
                    'name' => 'kh_event_category',
                    'selected' => $current_category,
                    'show_option_all' => __('All Categories', 'kh-events'),
                    'value_field' => 'slug',
                )); ?>
            </p>
            <p>
                <label for="kh_event_tag"><?php _e('Tag:', 'kh-events'); ?></label>
                <?php wp_dropdown_categories(array(
                    'taxonomy' => 'kh_event_tag',
                    'name' => 'kh_event_tag',
                    'selected' => $current_tag,
                    'show_option_all' => __('All Tags', 'kh-events'),
                    'value_field' => 'slug',
                )); ?>
            </p>
            <p>
                <input type="submit" value="<?php _e('Filter', 'kh-events'); ?>" />
            </p>
        </form>
        <?php

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Event Filters', 'kh-events');
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        return $instance;
    }
}