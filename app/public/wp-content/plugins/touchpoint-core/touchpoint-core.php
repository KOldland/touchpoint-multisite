<?php
/**
 * Plugin Name: Touchpoint Core
 * Description: Shared functionality for Touchpoint (taxonomies, shortcodes, ACF tweaks).
 * Version: 1.1.0
 * Author: Kris Oldland
 * Text Domain: touchpoint-core
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TOUCHPOINT_CORE_URL', plugin_dir_url(__FILE__));
define('TOUCHPOINT_CORE_PATH', plugin_dir_path(__FILE__));

// Basic PSR-4 Autoloader for the src/ directory
spl_autoload_register(function ($class) {
    $prefix = 'TouchpointCore\\';
    $base_dir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

function touchpoint_core_init() {
    $plugin = new \TouchpointCore\Plugin();
    $plugin->init();
}

// Initialize early enough for taxonomies and other hooks
touchpoint_core_init();
