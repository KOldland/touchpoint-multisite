<?php

namespace KHM_SEO\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class SeoWidget_Widget extends Widget_Base {
    public function get_name() {
        return 'khm_seo_widget_widget';
    }

    public function get_title() {
        return __( 'SEO Widget', 'khm-seo' );
    }

    public function get_icon() {
        return 'eicon-site-identity';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'SEO Widget', 'khm-seo' ),
            ]
        );

        $this->add_control(
            'type',
            [
                'label' => __( 'Type', 'khm-seo' ),
                'type' => Controls_Manager::SELECT,
                'default' => 'overview',
                'options' => [
                    'overview' => __( 'Overview', 'khm-seo' ),
                    'keywords' => __( 'Keywords', 'khm-seo' ),
                    'technical' => __( 'Technical', 'khm-seo' ),
                ],
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => __( 'Title', 'khm-seo' ),
                'type' => Controls_Manager::TEXT,
                'label_block' => true,
            ]
        );

        $this->add_control(
            'class',
            [
                'label' => __( 'CSS Class', 'khm-seo' ),
                'type' => Controls_Manager::TEXT,
            ]
        );

        $this->add_control(
            'height',
            [
                'label' => __( 'Height', 'khm-seo' ),
                'type' => Controls_Manager::TEXT,
                'default' => 'auto',
                'description' => __( 'Set a fixed height or use auto.', 'khm-seo' ),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $attrs = [];
        foreach (['type', 'title', 'class', 'height'] as $key) {
            if (! empty($s[$key])) {
                $attrs[] = $key . '="' . esc_attr($s[$key]) . '"';
            }
        }
        echo do_shortcode('[khm_seo_widget ' . implode(' ', $attrs) . ']');
    }
}
