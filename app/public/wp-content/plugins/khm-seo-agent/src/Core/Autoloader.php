<?php
/**
 * Autoloader for KHM SEO Agent plugin.
 */

namespace KHM_SEO_AGENT\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Autoloader {
    private static $namespace = 'KHM_SEO_AGENT\\';
    private static $base_dir;

    public static function register() {
        self::$base_dir = KHM_SEO_AGENT_PLUGIN_DIR . 'src/';
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    public static function autoload( $class_name ) {
        $len = strlen( self::$namespace );
        if ( strncmp( self::$namespace, $class_name, $len ) !== 0 ) {
            return;
        }

        $relative_class = substr( $class_name, $len );
        $file = self::$base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}

Autoloader::register();
