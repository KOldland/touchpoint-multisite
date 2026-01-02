<?php

defined('ABSPATH') or exit;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Elementor widget for TouchPoint MailChimp subscription form.
 */
class TMC_Form_Widget extends Widget_Base {

    public function get_name() {
        return 'tmc_form_widget';
    }

    public function get_title() {
        return __( 'TouchPoint MailChimp Form', 'touchpoint-mailchimp' );
    }

    public function get_icon() {
        return 'eicon-mail';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_form',
            [
                'label' => __( 'Form', 'touchpoint-mailchimp' ),
            ]
        );

        $this->add_control(
            'list_id',
            [
                'label' => __( 'List ID', 'touchpoint-mailchimp' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __( 'Audience/List ID', 'touchpoint-mailchimp' ),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'style',
            [
                'label' => __( 'Style', 'touchpoint-mailchimp' ),
                'type' => Controls_Manager::SELECT,
                'default' => 'default',
                'options' => [
                    'default' => __( 'Default', 'touchpoint-mailchimp' ),
                    'compact' => __( 'Compact', 'touchpoint-mailchimp' ),
                    'stacked' => __( 'Stacked', 'touchpoint-mailchimp' ),
                ],
            ]
        );

        $this->add_control(
            'show_interests',
            [
                'label' => __( 'Show Interests', 'touchpoint-mailchimp' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'touchpoint-mailchimp' ),
                'label_off' => __( 'No', 'touchpoint-mailchimp' ),
                'return_value' => 'true',
                'default' => 'false',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $attrs = [];

        if ( ! empty( $s['list_id'] ) ) {
            $attrs[] = 'list_id="' . esc_attr( $s['list_id'] ) . '"';
        }
        if ( ! empty( $s['style'] ) ) {
            $attrs[] = 'style="' . esc_attr( $s['style'] ) . '"';
        }
        if ( ( $s['show_interests'] ?? '' ) === 'true' ) {
            $attrs[] = 'show_interests="true"';
        }

        echo do_shortcode( '[tmc_form ' . implode( ' ', $attrs ) . ']' );
    }
}
