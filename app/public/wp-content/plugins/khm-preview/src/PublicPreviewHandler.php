<?php

namespace KHM\Preview;

use KHM\Preview\Database\Repositories\PreviewLinkRepository;
use KHM\Preview\Services\PreviewAnalyticsService;
use KHM\Preview\Token\TokenGenerator;

class PublicPreviewHandler {
    private $link_repository;
    private $analytics;
    private $token_generator;

    public function __construct( PreviewLinkRepository $repository, PreviewAnalyticsService $analytics, TokenGenerator $token_generator ) {
        $this->link_repository = $repository;
        $this->analytics       = $analytics;
        $this->token_generator = $token_generator;
    }

    public function register(): void {
        add_action( 'init', [ $this, 'maybe_render_preview' ] );
    }

    public function maybe_render_preview(): void {
        if ( ! isset( $_GET['khm_preview_post'], $_GET['khm_preview_token'] ) ) {
            return;
        }

        $post_id = (int) $_GET['khm_preview_post'];
        $token   = sanitize_text_field( wp_unslash( $_GET['khm_preview_token'] ) );
        $hash    = $this->token_generator->hash_token( $token );
        $link    = $this->link_repository->find_by_token_hash( $hash );

        if ( ! $link || (int) $link['post_id'] !== $post_id || $link['status'] !== 'active' || strtotime( $link['expires_at'] ) < current_time( 'timestamp' ) ) {
            wp_die( __( 'Preview link is invalid or expired.', 'khm-preview' ), 403 );
        }

        // Ensure the token presented matches the stored hash (prevents leaked hashes being used directly).
        if ( ! hash_equals( $link['token_hash'], $hash ) ) {
            wp_die( __( 'Preview link is invalid.', 'khm-preview' ), 403 );
        }

        $current_p = isset( $_GET['p'] ) ? (int) $_GET['p'] : 0;
        if ( $current_p !== $post_id ) {
            $canonical_preview_url = add_query_arg(
                [
                    'p'                 => $post_id,
                    'khm_preview_post'  => $post_id,
                    'khm_preview_token' => $token,
                ],
                home_url( '/' )
            );
            wp_safe_redirect( $canonical_preview_url );
            exit;
        }

        $this->analytics->log_hit( (int) $link['id'], [
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ] );

        add_filter( 'the_posts', function ( $posts ) use ( $post_id ) {
            foreach ( $posts as $post ) {
                if ( (int) $post->ID === $post_id ) {
                    $post->post_status = 'publish';
                    $post->post_password = '';
                    return [ $post ];
                }
            }
            $post = get_post( $post_id );
            if ( $post ) {
                $post->post_status = 'publish';
                $post->post_password = '';
                return [ $post ];
            }
            return $posts;
        } );
    }
}
