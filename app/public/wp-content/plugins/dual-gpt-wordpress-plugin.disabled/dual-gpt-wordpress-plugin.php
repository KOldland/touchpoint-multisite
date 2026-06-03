<?php
/**
 * Plugin Name: Dual-GPT WordPress Plugin for Research + Authoring
 * Plugin URI: https://github.com/KOldland/1927MSuite
 * Description: A custom WordPress plugin that integrates OpenAI Responses API into Gutenberg editor for dual-GPT authoring experience.
 * Version: 1.0.0
 * Author: Kris Oldland
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dual-gpt-wordpress-plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('DUAL_GPT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DUAL_GPT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DUAL_GPT_PLUGIN_VERSION', '1.0.0');

// Include core files
require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-dual-gpt-plugin.php';
require_once DUAL_GPT_PLUGIN_DIR . 'admin/class-dual-gpt-admin.php';

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'dual_gpt_plugin_activate');
register_deactivation_hook(__FILE__, 'dual_gpt_plugin_deactivate');

// Initialize the plugin
function dual_gpt_plugin_init() {
    $plugin = new Dual_GPT_Plugin();
    $plugin->init();
}
add_action('plugins_loaded', 'dual_gpt_plugin_init');

// Activation function
function dual_gpt_plugin_activate() {
    // Include required classes for activation
    require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-db-handler.php';
    
    $plugin = new Dual_GPT_Plugin();
    $plugin->activate();
}

// Deactivation function
function dual_gpt_plugin_deactivate() {
    $plugin = new Dual_GPT_Plugin();
    $plugin->deactivate();
}