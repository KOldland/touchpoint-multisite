<?php

namespace Touchpoint\Elementor;

use Elementor\Widget_Base;

class Footnotes_Widget extends Widget_Base {
    public function get_name() {
        return 'footnotes_block_widget';
    }

    public function get_title() {
        return __( 'Footnotes Block', 'touchpoint' );
    }

    public function get_icon() {
        return 'eicon-editor-ol';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        // No controls.
    }

    protected function render() {
        echo do_shortcode('[footnotes_block]');
    }
}
