<?php
/**
 * PHPUnit Bootstrap File
 * Loads plugin classes and test helpers for unit testing
 */

// Define ABSPATH if not already defined (for WordPress-free tests)
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

// Define plugin path constants
if ( ! defined( 'KH_SMMA_PATH' ) ) {
    define( 'KH_SMMA_PATH', dirname( __DIR__ ) . '/' );
}

// Load composer autoloader (includes PHPUnit)
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load test helpers (mock WordPress functions)
require_once __DIR__ . '/TestHelpers.php';

// Manually require the plugin's autoloader registration
$plugin_file = dirname( __DIR__ ) . '/src/Plugin.php';
if ( file_exists( $plugin_file ) ) {
    require_once $plugin_file;

    // Register the plugin's autoloader
    spl_autoload_register( function ( $class ) {
        if ( strpos( $class, 'KH_SMMA\\' ) !== 0 ) {
            return;
        }

        $relative = str_replace( 'KH_SMMA\\', '', $class );
        $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
        $file = KH_SMMA_PATH . 'src/' . $relative . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    } );
}
