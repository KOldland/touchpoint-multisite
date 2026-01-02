<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Elementor widget for event booking form shortcode.
 */
class KH_Event_BookingForm_Widget extends Widget_Base {
    public function get_name() {
        return 'kh_event_booking_form_widget';
    }

    public function get_title() {
        return __('KH Event Booking Form', 'kh-events');
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Booking Form', 'kh-events'),
            ]
        );

        $this->add_control(
            'event_id',
            [
                'label' => __('Event ID', 'kh-events'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'description' => __('Event ID to book. Leave empty to use context if available.', 'kh-events'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $attrs = [];
        if (!empty($s['event_id'])) {
            $attrs[] = 'event_id="' . esc_attr($s['event_id']) . '"';
        }

        echo do_shortcode('[kh_event_booking_form ' . implode(' ', $attrs) . ']');
    }
}
