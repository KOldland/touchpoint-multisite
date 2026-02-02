<?php
namespace KH_SMMA\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages admin assets (JavaScript, CSS) for KH-SMMA plugin
 */
class AssetsManager {
    public function register(): void {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    public function enqueue_admin_assets( $hook ): void {
        // Only load on Boost Visibility page
        if ( 'seo_page_khm-seo-boost-visibility' !== $hook ) {
            return;
        }

        $plugin_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
        $version = '1.0.0';

        // Enqueue JavaScript
        wp_enqueue_script(
            'kh-smma-admin',
            $plugin_url . 'assets/js/smma-admin.js',
            array( 'jquery' ),
            $version,
            true
        );

        // Localize script with REST API settings
        wp_localize_script( 'kh-smma-admin', 'khSMMASettings', array(
            'apiUrl' => rest_url( 'kh-smma/v1' ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ) );

        // Enqueue admin styles
        wp_enqueue_style(
            'kh-smma-admin',
            $plugin_url . 'assets/css/smma-admin.css',
            array(),
            $version
        );
    }
}
