<?php
namespace KH\XAPI\Services;

class AddonManager {
    private array $addons = [];
    private string $addons_dir;

    public function __construct() {
        $this->addons_dir = KH_XAPI_PATH . '/addons';

        if ( ! defined( 'KH_XAPI_ADDON_DIR' ) ) {
            define( 'KH_XAPI_ADDON_DIR', $this->addons_dir );
        }

        if ( ! defined( 'GRASSBLADE_ADDON_DIR' ) ) {
            define( 'GRASSBLADE_ADDON_DIR', $this->addons_dir );
        }
    }

    public function boot(): void {
        if ( ! is_dir( $this->addons_dir ) ) {
            return;
        }

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_report_scripts' ] );

        foreach ( scandir( $this->addons_dir ) as $entry ) {
            if ( $entry === '.' || $entry === '..' ) {
                continue;
            }

            $path = $this->addons_dir . '/' . $entry;
            if ( is_dir( $path ) ) {
                $this->addons[] = $entry;
                $this->include_addon_file( $entry, 'functions.php' );
            }
        }
    }

    private function include_addon_file( string $addon, string $file ): void {
        $full = $this->addons_dir . '/' . $addon . '/' . $file;

        if ( file_exists( $full ) ) {
            include_once $full;
        }
    }

    public function enqueue_report_scripts(): void {
        $settings = get_option( 'kh_xapi_reports', [] );
        if ( empty( $settings['scripts'] ) ) {
            return;
        }

        $scripts = array_filter( array_map( 'trim', explode( ',', $settings['scripts'] ) ) );
        foreach ( $scripts as $handle ) {
            wp_enqueue_script( $handle );
        }
    }
}
