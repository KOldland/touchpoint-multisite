<?php

namespace KHM_SEO\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class SmartTag_Widget extends Widget_Base {
    public function get_name() {
        return 'khm_smart_tag_widget';
    }

    public function get_title() {
        return __( 'Smart Tag', 'khm-seo' );
    }

    public function get_icon() {
        return 'eicon-dynamic-field';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Smart Tag', 'khm-seo' ),
            ]
        );

        $this->add_control(
            'tag',
            [
                'label' => __( 'Tag', 'khm-seo' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => 'site_name',
            ]
        );

        $this->add_control(
            'fallback',
            [
                'label' => __( 'Fallback', 'khm-seo' ),
                'type' => Controls_Manager::TEXT,
                'description' => __( 'Used if the tag cannot be resolved.', 'khm-seo' ),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $attrs = [];
        if (! empty($s['tag'])) {
            $attrs[] = 'tag="' . esc_attr($s['tag']) . '"';
        }
        if (! empty($s['fallback'])) {
            $attrs[] = 'fallback="' . esc_attr($s['fallback']) . '"';
        }
        echo do_shortcode('[smart_tag ' . implode(' ', $attrs) . ']');
    }
}
