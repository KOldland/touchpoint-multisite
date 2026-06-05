<?php
/**
 * Plugin Name: KH Sponsorship Hub
 * Plugin URI: https://1927media.com
 * Description: B2B Sponsorship Hub for Touchpoint Multisite (Pillar 2). Manages dashboards, applications, and migration tools.
 * Version: 1.0.0
 * Author: Kris Oldland
 * Text Domain: kh-sponsorship-hub
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Require Composer autoloader if it exists.
$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Initialize the plugin.
add_action('plugins_loaded', function () {
    // We will initialize the core bootstrap/service provider class here in Step 3
    // if (class_exists(\KhSponsorshipHub\Plugin::class)) {
    //     $plugin = new \KhSponsorshipHub\Plugin();
    //     $plugin->init();
    // }
});
