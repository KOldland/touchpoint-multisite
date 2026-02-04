<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Elementor widget for KH Events views shortcodes.
 */
class KH_Events_Views_Widget extends Widget_Base {
    public function get_name() {
        return 'kh_events_views_widget';
    }

    public function get_title() {
        return __('KH Events View', 'kh-events');
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('View', 'kh-events'),
            ]
        );

        $this->add_control(
            'view',
            [
                'label' => __('View Type', 'kh-events'),
                'type' => Controls_Manager::SELECT,
                'default' => 'kh_events_calendar',
                'options' => [
                    'kh_event_calendar'   => __('Single Event Calendar', 'kh-events'),
                    'kh_events_calendar'  => __('Calendar', 'kh-events'),
                    'kh_events_list'      => __('List', 'kh-events'),
                    'kh_events_day'       => __('Day', 'kh-events'),
                    'kh_events_week'      => __('Week', 'kh-events'),
                    'kh_events_photo'     => __('Photo', 'kh-events'),
                    'kh_events_ical'      => __('iCal', 'kh-events'),
                    'kh_events_search'    => __('Search', 'kh-events'),
                    'kh_events_submit'    => __('Submit Form', 'kh-events'),
                    'kh_events_dashboard' => __('Dashboard', 'kh-events'),
                ],
            ]
        );

        $this->add_control(
            'category',
            [
                'label' => __('Category', 'kh-events'),
                'type' => Controls_Manager::TEXT,
                'description' => __('Optional category slug/filter.', 'kh-events'),
            ]
        );

        $this->add_control(
            'tag',
            [
                'label' => __('Tag', 'kh-events'),
                'type' => Controls_Manager::TEXT,
                'description' => __('Optional tag slug/filter.', 'kh-events'),
            ]
        );

        $this->add_control(
            'limit',
            [
                'label' => __('Limit', 'kh-events'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 200,
            ]
        );

        $this->add_control(
            'status',
            [
                'label' => __('Status', 'kh-events'),
                'type' => Controls_Manager::TEXT,
                'description' => __('Optional status filter.', 'kh-events'),
            ]
        );

        $this->add_control(
            'location',
            [
                'label' => __('Location', 'kh-events'),
                'type' => Controls_Manager::TEXT,
                'description' => __('Optional location filter.', 'kh-events'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $shortcode = $s['view'] ?? 'kh_events_calendar';

        $attrs = [];
        foreach (['category', 'tag', 'status', 'location', 'limit'] as $key) {
            if (!empty($s[$key])) {
                $attrs[] = $key . '="' . esc_attr($s[$key]) . '"';
            }
        }

        echo do_shortcode('[' . esc_attr($shortcode) . ' ' . implode(' ', $attrs) . ']');
    }
}
