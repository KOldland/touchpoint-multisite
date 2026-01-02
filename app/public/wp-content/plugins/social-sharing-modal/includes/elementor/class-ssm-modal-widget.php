<?php

defined('ABSPATH') or exit;

use Elementor\Widget_Base;

/**
 * Elementor widget wrapper for [ssm_modal].
 */
class SSM_Modal_Widget extends Widget_Base {

    public function get_name() {
        return 'ssm_modal_widget';
    }

    public function get_title() {
        return __( 'Social Sharing Modal', 'social-sharing-modal' );
    }

    public function get_icon() {
        return 'eicon-share';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        // No controls needed; modal uses plugin settings.
    }

    protected function render() {
        echo do_shortcode('[ssm_modal]');
    }
}
