<?php

namespace KHM_SEO\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class LocalBusiness_Widget extends Widget_Base {
    public function get_name() {
        return 'khm_local_business_widget';
    }

    public function get_title() {
        return __( 'Local Business Info', 'khm-seo' );
    }

    public function get_icon() {
        return 'eicon-info-box';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Local Business', 'khm-seo' ),
            ]
        );

        $this->add_control(
            'show_hours',
            [
                'label' => __( 'Show Hours', 'khm-seo' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'khm-seo' ),
                'label_off' => __( 'No', 'khm-seo' ),
                'return_value' => 'true',
                'default' => 'true',
            ]
        );

        $this->add_control(
            'show_reviews',
            [
                'label' => __( 'Show Reviews', 'khm-seo' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'khm-seo' ),
                'label_off' => __( 'No', 'khm-seo' ),
                'return_value' => 'true',
                'default' => 'false',
            ]
        );

        $this->add_control(
            'show_map',
            [
                'label' => __( 'Show Map', 'khm-seo' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'khm-seo' ),
                'label_off' => __( 'No', 'khm-seo' ),
                'return_value' => 'true',
                'default' => 'false',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        // Output base business info
        echo do_shortcode('[local_business_info]');

        if ( ( $s['show_hours'] ?? '' ) === 'true' ) {
            echo do_shortcode('[business_hours]');
        }

        if ( ( $s['show_reviews'] ?? '' ) === 'true' ) {
            echo do_shortcode('[business_reviews]');
        }

        if ( ( $s['show_map'] ?? '' ) === 'true' ) {
            echo do_shortcode('[google_map]');
        }
    }
}
