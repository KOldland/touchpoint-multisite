<?php
/**
 * Plugin Name: KHM Preview Manager
 * Description: Internal preview infrastructure for marketing suite content.
 * Version: 0.1.0
 * Author: KHM Marketing Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/src/Autoloader.php';
\KHM\Preview\Autoloader::init();

register_activation_hook( __FILE__, function () {
    ( new \KHM\Preview\Database\Migrations\Installer() )->install();
} );

register_deactivation_hook( __FILE__, function () {
    ( new \KHM\Preview\Database\Migrations\Installer() )->deactivate();
} );

add_action( 'plugins_loaded', function () {
    ( new \KHM\Preview\Plugin() )->boot();
} );
