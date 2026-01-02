<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Widget_Base;

/**
 * Elementor widget for the affiliate dashboard shortcode [affiliate_dashboard].
 */
class KSS_Affiliate_Dashboard_Widget extends Widget_Base {

    public function get_name() {
        return 'kss_affiliate_dashboard_widget';
    }

    public function get_title() {
        return __('Affiliate Dashboard', 'social-strip');
    }

    public function get_icon() {
        return 'eicon-dashboard';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        // No controls needed; renders based on logged-in user.
    }

    protected function render() {
        echo do_shortcode('[affiliate_dashboard]');
    }
}
