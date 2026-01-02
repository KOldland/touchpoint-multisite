<?php

namespace TouchpointCore\Elementor;

use Elementor\Widget_Base;

class StyledExcerpt_Widget extends Widget_Base {
    public function get_name() {
        return 'styled_excerpt_widget';
    }

    public function get_title() {
        return __( 'Styled Excerpt', 'touchpoint-core' );
    }

    public function get_icon() {
        return 'eicon-excerpt';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        // No controls; uses current post excerpt.
    }

    protected function render() {
        echo do_shortcode('[styled_excerpt]');
    }
}
