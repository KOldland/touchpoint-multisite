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
        $plugin_path = dirname( dirname( __DIR__ ) );
        $version = '1.1.0';

        $variant_grid_js = $plugin_path . '/assets/js/smma-variant-grid.js';
        $calendar_modal_js = $plugin_path . '/assets/js/calendar-modal.js';
        $admin_js = $plugin_path . '/assets/js/smma-admin.js';
        $admin_css = $plugin_path . '/assets/css/smma-admin.css';

        wp_enqueue_script(
            'kh-smma-variant-grid',
            $plugin_url . 'assets/js/smma-variant-grid.js',
            array(),
            file_exists( $variant_grid_js ) ? (string) filemtime( $variant_grid_js ) : $version,
            true
        );

        wp_enqueue_script(
            'kh-smma-calendar-modal',
            $plugin_url . 'assets/js/calendar-modal.js',
            array(),
            file_exists( $calendar_modal_js ) ? (string) filemtime( $calendar_modal_js ) : $version,
            true
        );

        wp_enqueue_script(
            'kh-smma-admin',
            $plugin_url . 'assets/js/smma-admin.js',
            array( 'jquery', 'kh-smma-variant-grid', 'kh-smma-calendar-modal' ),
            file_exists( $admin_js ) ? (string) filemtime( $admin_js ) : $version,
            true
        );

        // Localize script with REST API settings
        wp_localize_script( 'kh-smma-admin', 'khSMMASettings', array(
            'apiUrl' => rest_url( 'kh-smma/v1' ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'defaultSponsorId' => '',
            'defaultBoostBudgetCents' => 10000,
            'defaultCurrency' => 'AUD',
            'defaultChannel' => 'linkedin',
        ) );

        // Enqueue admin styles
        wp_enqueue_style(
            'kh-smma-admin',
            $plugin_url . 'assets/css/smma-admin.css',
            array(),
            file_exists( $admin_css ) ? (string) filemtime( $admin_css ) : $version
        );
    }
}
