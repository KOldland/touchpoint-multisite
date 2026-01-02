<?php

namespace KHM_SEO\Elementor\Widgets;

use Elementor\Widget_Base;

class Breadcrumbs_Widget extends Widget_Base {
    public function get_name() {
        return 'khm_breadcrumbs_widget';
    }

    public function get_title() {
        return __( 'KHM Breadcrumbs', 'khm-seo' );
    }

    public function get_icon() {
        return 'eicon-breadcrumbs';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        // No controls needed; uses context.
    }

    protected function render() {
        echo do_shortcode('[khm_breadcrumbs]');
    }
}
