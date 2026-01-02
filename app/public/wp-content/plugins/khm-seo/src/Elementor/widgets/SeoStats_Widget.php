<?php

namespace KHM_SEO\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class SeoStats_Widget extends Widget_Base {
    public function get_name() {
        return 'khm_seo_stats_widget';
    }

    public function get_title() {
        return __( 'SEO Stats', 'khm-seo' );
    }

    public function get_icon() {
        return 'eicon-number-field';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Stats', 'khm-seo' ),
            ]
        );

        $this->add_control(
            'metric',
            [
                'label' => __( 'Metric', 'khm-seo' ),
                'type' => Controls_Manager::SELECT,
                'default' => 'score',
                'options' => [
                    'score'      => __( 'Overall Score', 'khm-seo' ),
                    'keywords'   => __( 'Keywords', 'khm-seo' ),
                    'backlinks'  => __( 'Backlinks', 'khm-seo' ),
                    'traffic'    => __( 'Traffic', 'khm-seo' ),
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $attrs = [];
        if (! empty($s['metric'])) {
            $attrs[] = 'metric="' . esc_attr($s['metric']) . '"';
        }
        echo do_shortcode('[khm_seo_stats ' . implode(' ', $attrs) . ']');
    }
}
