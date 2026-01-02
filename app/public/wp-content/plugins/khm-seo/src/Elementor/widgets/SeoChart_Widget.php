<?php

namespace KHM_SEO\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class SeoChart_Widget extends Widget_Base {
    public function get_name() {
        return 'khm_seo_chart_widget';
    }

    public function get_title() {
        return __( 'SEO Chart', 'khm-seo' );
    }

    public function get_icon() {
        return 'eicon-chart-area';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Chart', 'khm-seo' ),
            ]
        );

        $this->add_control(
            'type',
            [
                'label' => __( 'Chart Type', 'khm-seo' ),
                'type' => Controls_Manager::SELECT,
                'default' => 'performance',
                'options' => [
                    'performance' => __( 'Performance', 'khm-seo' ),
                    'keywords'    => __( 'Keywords', 'khm-seo' ),
                    'traffic'     => __( 'Traffic', 'khm-seo' ),
                ],
            ]
        );

        $this->add_control(
            'timeframe',
            [
                'label' => __( 'Timeframe', 'khm-seo' ),
                'type' => Controls_Manager::SELECT,
                'default' => '30d',
                'options' => [
                    '7d'  => __( '7 days', 'khm-seo' ),
                    '30d' => __( '30 days', 'khm-seo' ),
                    '90d' => __( '90 days', 'khm-seo' ),
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $attrs = [];
        if (! empty($s['type'])) {
            $attrs[] = 'type="' . esc_attr($s['type']) . '"';
        }
        if (! empty($s['timeframe'])) {
            $attrs[] = 'timeframe="' . esc_attr($s['timeframe']) . '"';
        }
        echo do_shortcode('[khm_seo_chart ' . implode(' ', $attrs) . ']');
    }
}
