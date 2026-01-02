<?php

namespace KHM_SEO\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class ConditionalContent_Widget extends Widget_Base {
    public function get_name() {
        return 'khm_conditional_content_widget';
    }

    public function get_title() {
        return __( 'Conditional Content', 'khm-seo' );
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
                'label' => __( 'Condition', 'khm-seo' ),
            ]
        );

        $this->add_control(
            'tag',
            [
                'label' => __( 'Tag', 'khm-seo' ),
                'type' => Controls_Manager::TEXT,
                'placeholder' => 'user_role',
            ]
        );

        $this->add_control(
            'operator',
            [
                'label' => __( 'Operator', 'khm-seo' ),
                'type' => Controls_Manager::SELECT,
                'default' => 'equals',
                'options' => [
                    'equals'    => __( 'Equals', 'khm-seo' ),
                    'not_equals'=> __( 'Not Equals', 'khm-seo' ),
                    'contains'  => __( 'Contains', 'khm-seo' ),
                ],
            ]
        );

        $this->add_control(
            'value',
            [
                'label' => __( 'Value', 'khm-seo' ),
                'type' => Controls_Manager::TEXT,
            ]
        );

        $this->add_control(
            'content',
            [
                'label' => __( 'Content', 'khm-seo' ),
                'type' => Controls_Manager::WYSIWYG,
                'default' => __( 'Your conditional content here.', 'khm-seo' ),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $attrs = [];
        foreach ( ['tag', 'operator', 'value'] as $key ) {
            if ( ! empty( $s[ $key ] ) ) {
                $attrs[] = $key . '="' . esc_attr( $s[ $key ] ) . '"';
            }
        }
        $content = $s['content'] ?? '';
        echo do_shortcode( '[conditional_content ' . implode( ' ', $attrs ) . ']' . $content . '[/conditional_content]' );
    }
}
