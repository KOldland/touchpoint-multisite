<?php

namespace KHM\Preview\Admin;

class PreviewAdminPage {
    /** @var string */
    private $hook_suffix;

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_menu(): void {
        $this->hook_suffix = add_submenu_page(
            'khm-dashboard',
            __( 'Preview Links', 'khm-preview' ),
            __( 'Preview Links', 'khm-preview' ),
            'edit_posts',
            'khm-preview-links',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( empty( $this->hook_suffix ) || $hook !== $this->hook_suffix ) {
            return;
        }

        $handle = 'khm-preview-admin';
        $plugin_file = dirname( __DIR__, 2 ) . '/khm-preview.php';
        wp_enqueue_style( $handle, plugins_url( 'assets/css/preview-admin.css', $plugin_file ), [], '0.1.0' );
        wp_enqueue_script( $handle, plugins_url( 'assets/js/preview-admin.js', $plugin_file ), [], '0.1.0', true );
        wp_localize_script( $handle, 'khmPreviewData', [
            'restUrl' => rest_url( 'khm-preview/v1' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'recentPosts' => $this->get_recent_posts(),
            'initialPostId' => isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : null,
        ] );
    }

    public function render_page(): void {
        echo '<div class="wrap khm-preview-admin">';
        echo '<h1>' . esc_html__( 'Marketing Suite Preview Links', 'khm-preview' ) . '</h1>';
        echo '<p>' . esc_html__( 'Manage draft previews for campaigns, articles, and landing pages directly from the Marketing Suite.', 'khm-preview' ) . '</p>';
        echo '<div id="khm-preview-manager" class="khm-preview-manager"></div>';
        echo '</div>';
    }

    private function get_recent_posts(): array {
        $posts = get_posts( [
            'post_status' => [ 'draft', 'pending', 'future' ],
            'posts_per_page' => 10,
            'orderby' => 'modified',
            'order'   => 'DESC',
        ] );

        return array_map( function ( $post ) {
            return [
                'id'     => (int) $post->ID,
                'title'  => get_the_title( $post ) ?: __( '(No Title)', 'khm-preview' ),
                'status' => $post->post_status,
                'type'   => $post->post_type,
                'author' => get_the_author_meta( 'display_name', $post->post_author ),
                'modified' => get_post_modified_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), false, $post ),
            ];
        }, $posts );
    }
}
