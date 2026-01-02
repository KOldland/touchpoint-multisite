<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * KHM Member Wrapper Widget
 * Shows content only to members (or specific levels).
 */
class Member_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_member_widget';
    }

    public function get_title() {
        return __( 'KHM Member Content', 'khm-membership' );
    }

    public function get_icon() {
        return 'eicon-lock-user';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Member Content', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'levels',
            [
                'label' => __( 'Restrict to Levels (comma-separated IDs)', 'khm-membership' ),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'description' => __( 'Leave blank to allow any active membership.', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'content',
            [
                'label' => __( 'Member Content', 'khm-membership' ),
                'type' => Controls_Manager::WYSIWYG,
                'default' => __( 'This content is for members only.', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'fallback_html',
            [
                'label' => __( 'Fallback for Non-members', 'khm-membership' ),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __( 'Please sign up to view this content.', 'khm-membership' ),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $levels   = trim( $settings['levels'] ?? '' );
        $content  = $settings['content'] ?? '';
        $fallback = $settings['fallback_html'] ?? '';

        $atts = [];
        if ( $levels !== '' ) {
            $atts[] = 'levels="' . esc_attr( $levels ) . '"';
        }

        $shortcode = '[khm_member ' . implode( ' ', $atts ) . ']' . $content . '[/khm_member]';
        $output = do_shortcode( $shortcode );

        if ( trim( $output ) === '' && $fallback ) {
            echo '<div class="khm-member-fallback">' . wp_kses_post( $fallback ) . '</div>';
            return;
        }

        echo $output;
    }
}
