<?php

namespace KHM\Elementor\Widgets;

use Elementor\Widget_Base;

/**
 * Affiliate Dashboard Widget - wraps [khm_affiliate_dashboard].
 */
class AffiliateDashboard_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_affiliate_dashboard_widget';
    }

    public function get_title() {
        return __( 'KHM Affiliate Dashboard', 'khm-membership' );
    }

    public function get_icon() {
        return 'eicon-user-circle-o';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        // No controls required for this wrapper.
    }

    protected function render() {
        echo do_shortcode( '[khm_affiliate_dashboard]' );
    }
}
