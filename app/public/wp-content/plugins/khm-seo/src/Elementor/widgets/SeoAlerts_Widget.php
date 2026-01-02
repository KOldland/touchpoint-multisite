<?php

namespace KHM_SEO\Elementor\Widgets;

use Elementor\Widget_Base;

class SeoAlerts_Widget extends Widget_Base {
    public function get_name() {
        return 'khm_seo_alerts_widget';
    }

    public function get_title() {
        return __( 'SEO Alerts', 'khm-seo' );
    }

    public function get_icon() {
        return 'eicon-notice';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        // No controls needed for now.
    }

    protected function render() {
        echo do_shortcode('[khm_seo_alerts]');
    }
}
