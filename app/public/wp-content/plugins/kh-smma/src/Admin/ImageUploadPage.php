<?php
declare( strict_types=1 );

namespace KH_SMMA\Admin;

use function add_action;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function esc_html__;
use function esc_url_raw;
use function file_exists;
use function file_get_contents;
use function get_option;
use function plugin_dir_path;
use function rest_url;
use function sanitize_file_name;
use function sanitize_text_field;
use function wp_create_nonce;
use function wp_die;
use function wp_enqueue_media;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_json_encode;
use function wp_localize_script;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImageUploadPage {
    private string $page_hook = '';

    public function register(): void {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_kh_smma_get_fixture', array( $this, 'handle_fixture_request' ) );
    }

    public function add_menu(): void {
        if ( ! $this->can_access() ) {
            return;
        }

        $this->page_hook = (string) add_submenu_page(
            'kh-smma-dashboard',
            __( 'Image Uploads', 'kh-smma' ),
            __( 'Images', 'kh-smma' ),
            'edit_posts',
            'smma-images',
            array( $this, 'render_page' )
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( '' === $this->page_hook || $hook !== $this->page_hook ) {
            return;
        }

        wp_enqueue_media();

        $js_path  = KH_SMMA_PATH . 'assets/js/image-upload.js';
        $css_path = KH_SMMA_PATH . 'assets/css/image-upload.css';
        $version  = defined( 'KH_SMMA_VERSION' ) ? KH_SMMA_VERSION : '1.0.0';

        wp_enqueue_style(
            'kh-smma-image-upload',
            KH_SMMA_URL . 'assets/css/image-upload.css',
            array(),
            file_exists( $css_path ) ? (string) filemtime( $css_path ) : $version
        );

        wp_enqueue_script(
            'kh-smma-image-upload',
            KH_SMMA_URL . 'assets/js/image-upload.js',
            array( 'jquery', 'media-editor', 'media-views' ),
            file_exists( $js_path ) ? (string) filemtime( $js_path ) : $version,
            true
        );

        wp_localize_script( 'kh-smma-image-upload', 'khSmmaImageUpload', array(
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'wp_rest' ),
            'fixtureEndpoint'  => admin_url( 'admin-ajax.php?action=kh_smma_get_fixture' ),
            'composeEndpoint'  => rest_url( 'kh-smma/v1/demo/compose' ),
            'referenceId'      => 'phase3-demo-post-1',
            'savedCompose'     => get_option( 'kh_smma_image_compose_' . md5( 'phase3-demo-post-1' ), array() ),
            'messages'         => array(
                'openUploader' => 'Select images',
                'saveSuccess'  => 'Compose mapping saved for the demo.',
                'saveError'    => 'Unable to save compose mapping.',
                'loadError'    => 'Unable to load fixture data.',
            ),
        ) );
    }

    public function render_page(): void {
        if ( ! $this->can_access() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        require KH_SMMA_PATH . 'admin/image-upload-page.php';
    }

    public function handle_fixture_request(): void {
        if ( ! $this->can_access() ) {
            status_header( 403 );
            header( 'Content-Type: application/json; charset=utf-8' );
            echo wp_json_encode( array( 'code' => 'kh_smma_forbidden', 'message' => 'Insufficient permissions.' ) );
            wp_die();
        }

        $file = sanitize_file_name( (string) ( $_GET['file'] ?? '' ) );
        $allowed = array(
            'upload_response.json',
            'layouts_response.json',
            'compose_response.json',
            'optimize_response.json',
        );

        if ( ! in_array( $file, $allowed, true ) ) {
            status_header( 404 );
            header( 'Content-Type: application/json; charset=utf-8' );
            echo wp_json_encode( array( 'code' => 'kh_smma_fixture_not_found', 'message' => 'Unknown fixture.' ) );
            wp_die();
        }

        $path = trailingslashit( $this->fixture_root() ) . $file;
        if ( ! file_exists( $path ) ) {
            status_header( 404 );
            header( 'Content-Type: application/json; charset=utf-8' );
            echo wp_json_encode( array( 'code' => 'kh_smma_fixture_missing', 'message' => 'Fixture not found on disk.' ) );
            wp_die();
        }

        header( 'Content-Type: application/json; charset=utf-8' );
        echo (string) file_get_contents( $path );
        wp_die();
    }

    private function fixture_root(): string {
        return dirname( KH_SMMA_PATH, 5 ) . '/tests/fixtures/images';
    }

    private function can_access(): bool {
        return current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
    }
}
