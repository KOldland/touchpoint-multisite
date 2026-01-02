<?php

namespace Touchpoint\Elementor;

use Elementor\Widget_Base;

class TestSlots_Widget extends Widget_Base {
    public function get_name() {
        return 'kh_test_slots_widget';
    }

    public function get_title() {
        return __( 'Ad Test Slots', 'touchpoint' );
    }

    public function get_icon() {
        return 'eicon-preview-medium';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        // No controls; renders helper output.
    }

    protected function render() {
        echo do_shortcode('[kh_test_slots]');
    }
}
