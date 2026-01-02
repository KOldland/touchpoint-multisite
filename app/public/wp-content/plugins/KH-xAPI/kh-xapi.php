<?php
/**
 * Plugin Name: KH xAPI
 * Description: Internal learning telemetry framework providing xAPI, completion, and reporting hooks.
 * Version: 0.1.0
 * Author: KH Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'KH_XAPI_PATH' ) ) {
    define( 'KH_XAPI_PATH', __DIR__ );
}
if ( ! defined( 'KH_XAPI_URL' ) ) {
    define( 'KH_XAPI_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'KH_XAPI_VERSION' ) ) {
    define( 'KH_XAPI_VERSION', '0.1.0' );
}

require_once KH_XAPI_PATH . '/includes/template-helpers.php';

spl_autoload_register(
    static function ( $class ) {
        $prefix   = 'KH\\XAPI\\';
        $base_dir = __DIR__ . '/src/';

        $len = strlen( $prefix );
        if ( strncmp( $class, $prefix, $len ) !== 0 ) {
            return;
        }

        $relative_class = substr( $class, $len );
        $file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

        if ( file_exists( $file ) ) {
            require $file;
        }
    }
);

use KH\XAPI\Plugin;

function kh_xapi() {
    static $instance = null;

    if ( null === $instance ) {
        $instance = new Plugin();
    }

    return $instance;
}

add_action(
    'plugins_loaded',
    static function () {
        kh_xapi()->init();
    }
);

register_activation_hook(
    __FILE__,
    static function () {
        kh_xapi()->db()->maybe_upgrade();
    }
);
