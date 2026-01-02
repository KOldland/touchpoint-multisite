<?php

namespace KHAdManager\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Elementor widget wrapping the [kh_ad] shortcode.
 */
class Ad_Widget extends Widget_Base {

    public function get_name() {
        return 'kh_ad_widget';
    }

    public function get_title() {
        return __( 'KH Ad Slot', 'kh-ad-manager' );
    }

    public function get_icon() {
        return 'eicon-banner';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Ad Slot', 'kh-ad-manager' ),
            ]
        );

        $this->add_control(
            'slot',
            [
                'label' => __( 'Slot', 'kh-ad-manager' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __( 'e.g. sidebar1, header, popup', 'kh-ad-manager' ),
                'label_block' => true,
                'description' => __( 'Slot slug to render. Must match a registered ad slot.', 'kh-ad-manager' ),
            ]
        );

        $this->add_control(
            'category_id',
            [
                'label' => __( 'Category ID (optional)', 'kh-ad-manager' ),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'description' => __( 'Target a specific ad category ID if desired.', 'kh-ad-manager' ),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $attrs = [];

        if ( ! empty( $s['slot'] ) ) {
            $attrs[] = 'slot="' . esc_attr( $s['slot'] ) . '"';
        }

        if ( ! empty( $s['category_id'] ) ) {
            $attrs[] = 'category_id="' . esc_attr( $s['category_id'] ) . '"';
        }

        if ( empty( $attrs ) ) {
            echo '<div class="kh-ad-widget-error">' . esc_html__( 'Please provide a slot.', 'kh-ad-manager' ) . '</div>';
            return;
        }

        echo do_shortcode( '[kh_ad ' . implode( ' ', $attrs ) . ']' );
    }
}
