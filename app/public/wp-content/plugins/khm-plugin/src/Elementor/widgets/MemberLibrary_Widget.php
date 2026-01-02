<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Member Library Widget - wraps [khm_member_library].
 */
class MemberLibrary_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_member_library_widget';
    }

    public function get_title() {
        return __( 'KHM Member Library', 'khm-membership' );
    }

    public function get_icon() {
        return 'eicon-library-open';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Library', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'view',
            [
                'label' => __( 'View', 'khm-membership' ),
                'type' => Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => [
                    'grid' => __( 'Grid', 'khm-membership' ),
                    'list' => __( 'List', 'khm-membership' ),
                ],
            ]
        );

        $this->add_control(
            'per_page',
            [
                'label' => __( 'Items per page', 'khm-membership' ),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 100,
                'default' => 12,
            ]
        );

        $this->add_control(
            'show_categories',
            [
                'label' => __( 'Show Categories', 'khm-membership' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'khm-membership' ),
                'label_off' => __( 'No', 'khm-membership' ),
                'return_value' => 'true',
                'default' => 'true',
            ]
        );

        $this->add_control(
            'show_search',
            [
                'label' => __( 'Show Search', 'khm-membership' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'khm-membership' ),
                'label_off' => __( 'No', 'khm-membership' ),
                'return_value' => 'true',
                'default' => 'true',
            ]
        );

        $this->add_control(
            'show_filters',
            [
                'label' => __( 'Show Filters', 'khm-membership' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'khm-membership' ),
                'label_off' => __( 'No', 'khm-membership' ),
                'return_value' => 'true',
                'default' => 'true',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $attrs = [];

        if ( ! empty( $s['view'] ) ) {
            $attrs[] = 'view="' . esc_attr( $s['view'] ) . '"';
        }
        if ( ! empty( $s['per_page'] ) ) {
            $attrs[] = 'per_page="' . esc_attr( $s['per_page'] ) . '"';
        }
        if ( ( $s['show_categories'] ?? '' ) !== 'true' ) {
            $attrs[] = 'show_categories="false"';
        }
        if ( ( $s['show_search'] ?? '' ) !== 'true' ) {
            $attrs[] = 'show_search="false"';
        }
        if ( ( $s['show_filters'] ?? '' ) !== 'true' ) {
            $attrs[] = 'show_filters="false"';
        }

        echo do_shortcode( '[khm_member_library ' . implode( ' ', $attrs ) . ']' );
    }
}
