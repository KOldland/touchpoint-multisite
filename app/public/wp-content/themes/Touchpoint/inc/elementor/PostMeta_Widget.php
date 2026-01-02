<?php

namespace Touchpoint\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class PostMeta_Widget extends Widget_Base {
    public function get_name() {
        return 'post_meta_block_widget';
    }

    public function get_title() {
        return __( 'Post Meta Block', 'touchpoint' );
    }

    public function get_icon() {
        return 'eicon-post-info';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Meta', 'touchpoint' ),
            ]
        );

        $this->add_control(
            'show',
            [
                'label' => __( 'Show', 'touchpoint' ),
                'type' => Controls_Manager::TEXT,
                'default' => 'category,title,author,date',
                'description' => __( 'Comma-separated fields to show: category,title,author,date', 'touchpoint' ),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $show = ! empty( $s['show'] ) ? $s['show'] : 'category,title,author,date';
        echo do_shortcode( '[post_meta_block show="' . esc_attr( $show ) . '"]' );
    }
}
