<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * KHM Account Widget
 * Wraps the [khm_account] shortcode.
 */
class Account_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_account_widget';
    }

    public function get_title() {
        return __( 'KHM Account', 'khm-membership' );
    }

    public function get_icon() {
        return 'eicon-user-circle-o';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Account', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'section',
            [
                'label' => __( 'Section', 'khm-membership' ),
                'type' => Controls_Manager::SELECT,
                'default' => 'overview',
                'options' => [
                    'overview' => __( 'Overview', 'khm-membership' ),
                    'memberships' => __( 'Memberships', 'khm-membership' ),
                    'orders' => __( 'Orders', 'khm-membership' ),
                    'profile' => __( 'Profile', 'khm-membership' ),
                ],
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => __( 'Show Title', 'khm-membership' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'khm-membership' ),
                'label_off' => __( 'No', 'khm-membership' ),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->add_control(
            'guest_message',
            [
                'label' => __( 'Guest Message', 'khm-membership' ),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __( 'Please log in to view your account.', 'khm-membership' ),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $section  = sanitize_text_field( $settings['section'] ?? 'overview' );

        // If not logged in, show guest message.
        if ( ! is_user_logged_in() ) {
            echo '<div class="khm-account-guest">';
            echo esc_html( $settings['guest_message'] ?? '' );
            echo '</div>';
            return;
        }

        if ( 'yes' === ( $settings['show_title'] ?? '' ) ) {
            echo '<h3 class="khm-account-heading">' . esc_html__( 'Account', 'khm-membership' ) . '</h3>';
        }

        echo do_shortcode( '[khm_account section="' . esc_attr( $section ) . '"]' );
    }
}
