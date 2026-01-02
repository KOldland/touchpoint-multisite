<?php

namespace KHM\Preview;

/**
 * Simple PSR-4 compatible autoloader for the plugin namespace.
 */
class Autoloader {
    /**
     * Initialize the autoloader registration.
     */
    public static function init(): void {
        spl_autoload_register( function ( $class ): void {
            $prefix = __NAMESPACE__ . '\\';
            if ( strpos( $class, $prefix ) !== 0 ) {
                return;
            }

            $relative = substr( $class, strlen( $prefix ) );
            $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
            $file     = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        } );
    }
}
