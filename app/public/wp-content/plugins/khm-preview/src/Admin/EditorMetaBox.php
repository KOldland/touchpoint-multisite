<?php

namespace KHM\Preview\Admin;

use DateInterval;
use DateTimeImmutable;
use KHM\Preview\Services\PreviewAnalyticsService;
use KHM\Preview\Services\PreviewLinkService;

class EditorMetaBox {
    private $service;
    private $analytics;

    public function __construct( PreviewLinkService $service, PreviewAnalyticsService $analytics ) {
        $this->service   = $service;
        $this->analytics = $analytics;
    }

    public function register(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'admin_post_khm_preview_create', [ $this, 'handle_create_request' ] );
        add_action( 'admin_post_khm_preview_revoke', [ $this, 'handle_revoke_request' ] );
        add_action( 'admin_post_khm_preview_extend', [ $this, 'handle_extend_request' ] );
    }

    public function add_meta_box(): void {
        $post_types = get_post_types( [ 'public' => true ], 'names' );
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'khm-preview-meta',
                __( 'Preview Links', 'khm-preview' ),
                [ $this, 'render_meta_box' ],
                $post_type,
                'side',
                'high'
            );
        }
    }

    public function render_meta_box( $post ): void {
        $link = $this->service->get_active_link( $post->ID );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'khm_preview_meta_box', 'khm_preview_nonce' ); ?>
            <input type="hidden" name="action" value="khm_preview_create" />
            <input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>" />
            <p>
                <?php if ( $link ) : ?>
                    <strong><?php esc_html_e( 'Active preview link available.', 'khm-preview' ); ?></strong><br/>
                    <code><?php echo esc_html( $this->build_preview_url( $post->ID, $link['token'] ) ); ?></code><br/>
                    <small>
                        <?php
                        printf(
                            /* translators: %s: human readable time */
                            esc_html__( 'Expires %s', 'khm-preview' ),
                            esc_html( get_date_from_gmt( $link['expires_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) )
                        );
                        ?>
                    </small>
                <?php else : ?>
                    <?php esc_html_e( 'No preview link generated yet.', 'khm-preview' ); ?>
                <?php endif; ?>
            </p>
            <p>
                <label for="khm-preview-expiration">
                    <?php esc_html_e( 'Expires in (hours)', 'khm-preview' ); ?>
                </label>
                <input type="number" id="khm-preview-expiration" name="khm_preview_hours" value="48" min="1" class="small-text" />
            </p>
            <p>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e( 'Generate Preview Link', 'khm-preview' ); ?>
                </button>
            </p>
        </form>
        <?php if ( $link ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'khm_preview_revoke', 'khm_preview_revoke_nonce' ); ?>
                <input type="hidden" name="action" value="khm_preview_revoke" />
                <input type="hidden" name="link_id" value="<?php echo esc_attr( $link['id'] ); ?>" />
                <button type="submit" class="button button-secondary">
                    <?php esc_html_e( 'Revoke Link', 'khm-preview' ); ?>
                </button>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px;">
                <?php wp_nonce_field( 'khm_preview_extend', 'khm_preview_extend_nonce' ); ?>
                <input type="hidden" name="action" value="khm_preview_extend" />
                <input type="hidden" name="link_id" value="<?php echo esc_attr( $link['id'] ); ?>" />
                <label for="khm-preview-extend-hours">
                    <?php esc_html_e( 'Extend by hours', 'khm-preview' ); ?>
                </label>
                <input type="number" name="khm_extend_hours" id="khm-preview-extend-hours" value="24" min="1" class="small-text" />
                <button type="submit" class="button">
                    <?php esc_html_e( 'Extend', 'khm-preview' ); ?>
                </button>
            </form>
            <?php $this->render_hits_table( (int) $link['id'] ); ?>
        <?php endif; ?>
        <p style="margin-top:10px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=khm-preview-links&post_id=' . $post->ID ) ); ?>" class="button-link">
                <?php esc_html_e( 'Open Preview Manager', 'khm-preview' ); ?>
            </a>
        </p>
        <?php
    }

    private function build_preview_url( int $post_id, string $token ): string {
        return add_query_arg(
            [
                'khm_preview_post' => $post_id,
                'khm_preview_token'=> $token,
            ],
            home_url( '/' )
        );
    }

    public function handle_create_request(): void {
        if ( ! isset( $_POST['khm_preview_nonce'], $_POST['post_id'] ) || ! wp_verify_nonce( $_POST['khm_preview_nonce'], 'khm_preview_meta_box' ) ) {
            wp_die( __( 'Invalid preview request.', 'khm-preview' ) );
        }

        $post_id = (int) $_POST['post_id'];
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( __( 'Insufficient permissions.', 'khm-preview' ) );
        }

        $hours = max( 1, (int) $_POST['khm_preview_hours'] );
        $expires = ( new DateTimeImmutable( 'now', wp_timezone() ) )->add( new DateInterval( 'PT' . $hours . 'H' ) );
        $this->service->create_link( $post_id, get_current_user_id(), $expires );

        wp_safe_redirect( wp_get_referer() ?: admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
        exit;
    }

    public function handle_revoke_request(): void {
        if ( ! isset( $_POST['khm_preview_revoke_nonce'], $_POST['link_id'] ) || ! wp_verify_nonce( $_POST['khm_preview_revoke_nonce'], 'khm_preview_revoke' ) ) {
            wp_die( __( 'Invalid request.', 'khm-preview' ) );
        }
        $link_id = (int) $_POST['link_id'];
        $link    = $this->service->get_link( $link_id );
        if ( ! $link || ! current_user_can( 'edit_post', (int) $link['post_id'] ) ) {
            wp_die( __( 'Insufficient permissions.', 'khm-preview' ) );
        }
        $this->service->revoke_link( $link_id );
        wp_safe_redirect( wp_get_referer() ?: admin_url( 'post.php?post=' . $link['post_id'] . '&action=edit' ) );
        exit;
    }

    public function handle_extend_request(): void {
        if ( ! isset( $_POST['khm_preview_extend_nonce'], $_POST['link_id'] ) || ! wp_verify_nonce( $_POST['khm_preview_extend_nonce'], 'khm_preview_extend' ) ) {
            wp_die( __( 'Invalid request.', 'khm-preview' ) );
        }
        $link_id = (int) $_POST['link_id'];
        $link    = $this->service->get_link( $link_id );
        if ( ! $link || ! current_user_can( 'edit_post', (int) $link['post_id'] ) ) {
            wp_die( __( 'Insufficient permissions.', 'khm-preview' ) );
        }
        $hours = max( 1, (int) $_POST['khm_extend_hours'] );
        $expires = ( new DateTimeImmutable( 'now', wp_timezone() ) )->add( new DateInterval( 'PT' . $hours . 'H' ) );
        $this->service->extend_link( $link_id, $expires );
        wp_safe_redirect( wp_get_referer() ?: admin_url( 'post.php?post=' . $link['post_id'] . '&action=edit' ) );
        exit;
    }

    private function render_hits_table( int $link_id ): void {
        $hits = $this->analytics->get_recent_hits( $link_id );
        if ( empty( $hits ) ) {
            echo '<p>' . esc_html__( 'No preview views recorded yet.', 'khm-preview' ) . '</p>';
            return;
        }
        echo '<h4>' . esc_html__( 'Recent Views', 'khm-preview' ) . '</h4>';
        echo '<ul>';
        foreach ( $hits as $hit ) {
            printf(
                '<li>%s - %s</li>',
                esc_html( get_date_from_gmt( $hit['viewed_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
                esc_html( $hit['ip'] )
            );
        }
        echo '</ul>';
    }
}
