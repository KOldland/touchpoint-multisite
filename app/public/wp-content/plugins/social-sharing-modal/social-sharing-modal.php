<?php
/*
Plugin Name: Social Sharing Modal
Description: Adds a floating button to share articles via email using a modal.
Version: 1.0
Author: Kirsty Hennah
*/

if (!defined('ABSPATH')) exit;

// Load functional logic
require_once __DIR__ . '/includes/sm.functions.php';

// Load modal markup as a shortcode
require_once __DIR__ . '/includes/email-modal.php';

// Elementor widget registration
add_action('elementor/widgets/register', function($widgets_manager) {
    if (! class_exists('\Elementor\Widget_Base')) {
        return;
    }

    $widget_file = __DIR__ . '/includes/elementor/class-ssm-modal-widget.php';
    if (file_exists($widget_file)) {
        require_once $widget_file;
    }

    if (class_exists('\SSM_Modal_Widget')) {
        $widgets_manager->register(new \SSM_Modal_Widget());
    }
});
