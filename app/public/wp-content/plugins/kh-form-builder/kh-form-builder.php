<?php
/**
 * Plugin Name: KH Form Builder
 * Description: Lightweight, elegant form builder with shortcode rendering.
 * Version: 0.1.0
 * Author: KH Team
 * Text Domain: kh-form-builder
 */

if (! defined('ABSPATH')) {
    exit;
}

define('KH_FORM_BUILDER_PATH', plugin_dir_path(__FILE__));
define('KH_FORM_BUILDER_URL', plugin_dir_url(__FILE__));
define('KH_FORM_BUILDER_VERSION', '0.1.0');

require_once KH_FORM_BUILDER_PATH . 'includes/Plugin.php';

add_action('plugins_loaded', ['KHFormBuilder\Plugin', 'init']);
