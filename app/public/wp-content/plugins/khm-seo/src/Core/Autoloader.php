<?php
/**
 * Autoloader for KHM SEO plugin.
 *
 * @package KHM_SEO
 * @version 1.0.0
 */

namespace KHM_SEO\Core;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Autoloader class for handling PSR-4 autoloading.
 */
class Autoloader {
    
    /**
     * The namespace prefix for this plugin.
     *
     * @var string
     */
    private static $namespace = 'KHM_SEO\\';
    
    /**
     * The base directory for the namespace prefix.
     *
     * @var string
     */
    private static $base_dir;
    
    /**
     * Register the autoloader.
     */
    public static function register() {
        self::$base_dir = KHM_SEO_PLUGIN_DIR . 'src/';
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }
    
    /**
     * Autoload classes.
     *
     * @param string $class_name The fully-qualified class name.
     */
    public static function autoload( $class_name ) {
        // Does the class use the namespace prefix?
        $len = strlen( self::$namespace );
        if ( strncmp( self::$namespace, $class_name, $len ) !== 0 ) {
            return;
        }
        
        // Get the relative class name
        $relative_class = substr( $class_name, $len );
        
        // Replace the namespace separator with the directory separator
        // and append with .php
        $file = self::$base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
        
        // If the file exists, require it
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}

// Register the autoloader
Autoloader::register();