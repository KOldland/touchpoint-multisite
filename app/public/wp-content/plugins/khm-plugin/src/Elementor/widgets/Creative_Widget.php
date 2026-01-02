<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * KHM Creative Widget - wraps [khm_creative].
 */
class Creative_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_creative_widget';
    }

    public function get_title() {
        return __( 'KHM Creative', 'khm-membership' );
    }

    public function get_icon() {
        return 'eicon-image';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Creative', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'id',
            [
                'label' => __( 'Creative ID', 'khm-membership' ),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'description' => __( 'Enter the creative ID to render.', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'member_id',
            [
                'label' => __( 'Member ID (optional)', 'khm-membership' ),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
            ]
        );

        $this->add_control(
            'platform',
            [
                'label' => __( 'Platform', 'khm-membership' ),
                'type' => Controls_Manager::SELECT,
                'default' => 'website',
                'options' => [
                    'website' => __( 'Website', 'khm-membership' ),
                    'social'  => __( 'Social', 'khm-membership' ),
                    'email'   => __( 'Email', 'khm-membership' ),
                    'other'   => __( 'Other', 'khm-membership' ),
                ],
            ]
        );

        $this->add_control(
            'new_window',
            [
                'label' => __( 'Open in new window', 'khm-membership' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'khm-membership' ),
                'label_off' => __( 'No', 'khm-membership' ),
                'return_value' => 'true',
                'default' => 'true',
            ]
        );

        $this->add_control(
            'css_class',
            [
                'label' => __( 'CSS Class', 'khm-membership' ),
                'type' => Controls_Manager::TEXT,
                'description' => __( 'Optional CSS class to append.', 'khm-membership' ),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings    = $this->get_settings_for_display();
        $attrs       = [];
        $creative_id = absint( $settings['id'] ?? 0 );
        if ( $creative_id ) {
            $attrs[] = 'id="' . esc_attr( $creative_id ) . '"';
        }

        if ( ! empty( $settings['member_id'] ) ) {
            $attrs[] = 'member_id="' . esc_attr( $settings['member_id'] ) . '"';
        }

        if ( ! empty( $settings['platform'] ) ) {
            $attrs[] = 'platform="' . esc_attr( $settings['platform'] ) . '"';
        }

        if ( ( $settings['new_window'] ?? '' ) === 'true' ) {
            $attrs[] = 'new_window="true"';
        }

        if ( ! empty( $settings['css_class'] ) ) {
            $attrs[] = 'css_class="' . esc_attr( $settings['css_class'] ) . '"';
        }

        echo do_shortcode( '[khm_creative ' . implode( ' ', $attrs ) . ']' );
    }
}
