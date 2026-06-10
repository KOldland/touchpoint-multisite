<?php
/**
 * Plugin Name: KH Editorial Author
 * Description: Phase 2 of the Editorial Suite. Handles modular article generation, abstracting, and enrichment.
 * Version: 1.0.0
 * Author: KH
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Autoloader for the kh-editorial-author namespace
spl_autoload_register(function ($class) {
    $prefix = 'KH\\EditorialAuthor\\';
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

// Initialize the plugin
add_action('plugins_loaded', function () {
    \KH\EditorialAuthor\Core\AuthorPlugin::get_instance();
});
