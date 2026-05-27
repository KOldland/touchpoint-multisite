<?php
/*
Plugin Name: KH Attribution
Description: Affiliate Attribution and ROI Tracking Engine
Version: 1.0.0
Author: KHM Dev
Text Domain: kh-attribution
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Manual Autoloader for KH Attribution
 * Fallback for environments without Composer
 */
spl_autoload_register(function ($class) {
    $prefix = 'KH\\Attribution\\';
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

// Also handle the un-namespaced legacy files for now
spl_autoload_register(function ($class) {
    $legacy_classes = [
        'KHM_Attribution_Performance_Manager' => 'src/Services/Attribution/PerformanceManager.php',
        'KHM_Attribution_Async_Manager'       => 'src/Services/Attribution/AsyncManager.php',
        'KHM_Attribution_Query_Builder'       => 'src/Services/Attribution/QueryBuilder.php',
    ];

    if (isset($legacy_classes[$class])) {
        $file = __DIR__ . '/' . $legacy_classes[$class];
        if (file_exists($file)) {
            require $file;
        }
    }
});

// Bootstrap the plugin components
add_action( 'plugins_loaded', function() {
    \KH\Attribution\Providers\Bootstrap::init();
});
