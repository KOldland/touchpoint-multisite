<?php

namespace Touchpoint\Elementor;

use Elementor\Widget_Base;

class Abstract_Widget extends Widget_Base {
    public function get_name() {
        return 'abstract_block_widget';
    }

    public function get_title() {
        return __( 'Abstract Block', 'touchpoint' );
    }

    public function get_icon() {
        return 'eicon-text-area';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        // No controls; renders template part.
    }

    protected function render() {
        echo do_shortcode('[abstract_block]');
    }
}
