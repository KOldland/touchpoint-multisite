<?php
/**
 * Plugin Name:       KH Bounce
 * Description:       Exit-intent modal system inspired by the legacy wBounce plugin.
 * Version:           0.1.0
 * Author:            KH Marketing Suite
 * Text Domain:       kh-bounce
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'KH_BOUNCE_VERSION' ) ) {
    define( 'KH_BOUNCE_VERSION', '0.1.0' );
}

define( 'KH_BOUNCE_PLUGIN_FILE', __FILE__ );
define( 'KH_BOUNCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'KH_BOUNCE_URL', plugin_dir_url( __FILE__ ) );

autoload_kh_bounce();

register_activation_hook( __FILE__, 'kh_bounce_activate' );
register_deactivation_hook( __FILE__, 'kh_bounce_deactivate' );

/**
 * Simple autoloader for KH Bounce classes.
 */
function autoload_kh_bounce() {
    spl_autoload_register( function ( $class ) {
        if ( 0 !== strpos( $class, 'KH_Bounce' ) ) {
            return;
        }

        $class = strtolower( str_replace( '\\', '/', str_replace( '_', '-', $class ) ) );
        $path  = KH_BOUNCE_PATH . 'includes/' . $class . '.php';

        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }

        // Support sub-namespaces e.g. KH_Bounce\Admin\Settings.
        $parts = explode( '/', $class );
        $file  = array_pop( $parts );
        $sub   = implode( '/', $parts );
        $path  = KH_BOUNCE_PATH . 'includes/' . $sub . '/' . $file . '.php';

        if ( file_exists( $path ) ) {
            require_once $path;
        }
    } );
}

/**
 * Plugin activation callback.
 */
function kh_bounce_activate() {
    $defaults = array(
        'status'          => 'on',
        'template'        => 'classic',
        'title'           => __( 'Wait! Before you go...', 'kh-bounce' ),
        'text'            => __( 'Join our marketing insiders newsletter and get instant access to playbooks.', 'kh-bounce' ),
        'cta_label'       => __( 'Get the Playbook', 'kh-bounce' ),
        'cta_url'         => home_url( '/newsletter/' ),
        'dismiss_label'   => __( 'No thanks', 'kh-bounce' ),
        'display_on_home' => '1',
        'show_on_mobile'  => '0',
        'test_mode'       => '0',
        'telemetry_mode'  => 'none',
    );

    if ( ! get_option( 'kh_bounce_settings' ) ) {
        add_option( 'kh_bounce_settings', $defaults );
    } else {
        $current = get_option( 'kh_bounce_settings', array() );
        update_option( 'kh_bounce_settings', wp_parse_args( $current, $defaults ) );
    }
}

/**
 * Plugin deactivation callback.
 */
function kh_bounce_deactivate() {
    // Keep options for now; placeholder for future cleanup.
}

/**
 * Boot the plugin.
 */
function kh_bounce() {
    static $instance = null;

    if ( null === $instance ) {
        $instance = new KH_Bounce_Plugin();
    }

    return $instance;
}

// Boot plugin after WordPress and other plugins are loaded.
add_action( 'plugins_loaded', 'kh_bounce' );
