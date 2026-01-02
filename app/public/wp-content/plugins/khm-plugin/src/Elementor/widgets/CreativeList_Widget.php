<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * KHM Creative List Widget - wraps [khm_creative_list].
 */
class CreativeList_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_creative_list_widget';
    }

    public function get_title() {
        return __( 'KHM Creative List', 'khm-membership' );
    }

    public function get_icon() {
        return 'eicon-gallery-grid';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Creative List', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'type',
            [
                'label' => __( 'Type', 'khm-membership' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __( 'banner, text, social…', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'limit',
            [
                'label' => __( 'Limit', 'khm-membership' ),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 50,
                'default' => 10,
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
            'show_title',
            [
                'label' => __( 'Show Title', 'khm-membership' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'khm-membership' ),
                'label_off' => __( 'No', 'khm-membership' ),
                'return_value' => 'true',
                'default' => 'false',
            ]
        );

        $this->add_control(
            'columns',
            [
                'label' => __( 'Columns', 'khm-membership' ),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 4,
                'default' => 1,
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

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $attrs = [];

        if ( ! empty( $s['type'] ) ) {
            $attrs[] = 'type="' . esc_attr( $s['type'] ) . '"';
        }
        if ( ! empty( $s['limit'] ) ) {
            $attrs[] = 'limit="' . esc_attr( $s['limit'] ) . '"';
        }
        if ( ! empty( $s['member_id'] ) ) {
            $attrs[] = 'member_id="' . esc_attr( $s['member_id'] ) . '"';
        }
        if ( ( $s['show_title'] ?? '' ) === 'true' ) {
            $attrs[] = 'show_title="true"';
        }
        if ( ! empty( $s['columns'] ) ) {
            $attrs[] = 'columns="' . esc_attr( $s['columns'] ) . '"';
        }
        if ( ! empty( $s['platform'] ) ) {
            $attrs[] = 'platform="' . esc_attr( $s['platform'] ) . '"';
        }

        echo do_shortcode( '[khm_creative_list ' . implode( ' ', $attrs ) . ']' );
    }
}
