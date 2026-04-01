<?php

namespace KHM\Preview\REST;

use DateInterval;
use DateTimeImmutable;
use KHM\Preview\Services\PreviewAnalyticsService;
use KHM\Preview\Services\PreviewLinkService;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

class PreviewRestController {
    private $service;
    private $analytics;

    public function __construct( PreviewLinkService $service, PreviewAnalyticsService $analytics ) {
        $this->service   = $service;
        $this->analytics = $analytics;
    }

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( 'khm-preview/v1', '/links', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'create_link' ],
                'permission_callback' => [ $this, 'can_modify_link' ],
                'args'                => $this->get_create_args(),
            ],
        ] );

        register_rest_route( 'khm-preview/v1', '/links/(?P<id>\\d+)', [
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'revoke_link' ],
                'permission_callback' => [ $this, 'can_modify_link' ],
            ],
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_link' ],
                'permission_callback' => [ $this, 'can_view_link' ],
            ],
        ] );

        register_rest_route( 'khm-preview/v1', '/links/(?P<id>\\d+)/extend', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'extend_link' ],
                'permission_callback' => [ $this, 'can_modify_link' ],
                'args'                => [
                    'hours' => [
                        'type'    => 'integer',
                        'default' => 48,
                        'minimum' => 1,
                    ],
                ],
            ],
        ] );

        register_rest_route( 'khm-preview/v1', '/posts/(?P<post_id>\\d+)/link', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_post_link' ],
                'permission_callback' => [ $this, 'can_view_post_link' ],
            ],
        ] );
    }

    public function can_modify_link( WP_REST_Request $request ): bool {
        $post_id = (int) $request->get_param( 'post_id' );
        if ( ! $post_id && $request->get_param( 'id' ) ) {
            $link = $this->service->get_link( (int) $request->get_param( 'id' ) );
            if ( $link ) {
                $post_id = (int) $link['post_id'];
            }
        }
        return $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );
    }

    public function can_view_link( WP_REST_Request $request ): bool {
        $link = $this->service->get_link( (int) $request->get_param( 'id' ) );
        return $link ? current_user_can( 'edit_post', (int) $link['post_id'] ) : false;
    }

    public function can_view_post_link( WP_REST_Request $request ): bool {
        $post_id = (int) $request->get_param( 'post_id' );
        return current_user_can( 'edit_post', $post_id );
    }

    public function create_link( WP_REST_Request $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        $hours   = max( 1, (int) $request->get_param( 'hours' ) );

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_REST_Response( null, 403 );
        }

        $expires = ( new DateTimeImmutable( 'now', wp_timezone() ) )->add( new DateInterval( 'PT' . $hours . 'H' ) );
        $result  = $this->service->create_link( $post_id, get_current_user_id(), $expires, [ 'source' => 'rest' ] );

        return new WP_REST_Response( [
            'id'     => $result['id'],
            'token'  => $result['token'],
            'link'   => add_query_arg( [
                'p'                 => $post_id,
                'khm_preview_post'  => $post_id,
                'khm_preview_token' => $result['token'],
            ], home_url( '/' ) ),
        ], 201 );
    }

    public function revoke_link( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        $this->service->revoke_link( $id );
        return new WP_REST_Response( null, 204 );
    }

    public function extend_link( WP_REST_Request $request ) {
        $id    = (int) $request->get_param( 'id' );
        $hours = max( 1, (int) $request->get_param( 'hours' ) );

        $link = $this->service->get_link( $id );
        if ( ! $link || ! current_user_can( 'edit_post', (int) $link['post_id'] ) ) {
            return new WP_REST_Response( null, 403 );
        }

        $expires = ( new DateTimeImmutable( 'now', wp_timezone() ) )->add( new DateInterval( 'PT' . $hours . 'H' ) );
        $this->service->extend_link( $id, $expires );
        return new WP_REST_Response( [ 'id' => $id, 'expires_at' => $expires->format( 'c' ) ] );
    }

    public function get_link( WP_REST_Request $request ) {
        $id   = (int) $request->get_param( 'id' );
        $link = $this->service->get_link( $id );
        if ( ! $link ) {
            return new WP_REST_Response( null, 404 );
        }
        $hits = $this->analytics->get_recent_hits( $id );
        $link['hits'] = $hits;
        return new WP_REST_Response( $link );
    }

    public function get_post_link( WP_REST_Request $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        $link    = $this->service->get_latest_link( $post_id );
        if ( ! $link ) {
            return new WP_REST_Response( null, 204 );
        }

        $is_expired = strtotime( (string) $link['expires_at'] ) < current_time( 'timestamp' );
        $status     = (string) $link['status'];
        if ( 'active' === $status && $is_expired ) {
            $status = 'expired';
        }

        $link['status_display'] = $status;
        $link['is_expired']     = $is_expired;
        $link['hits']           = $this->analytics->get_recent_hits( (int) $link['id'] );
        return new WP_REST_Response( $link );
    }

    private function get_create_args(): array {
        return [
            'post_id' => [
                'type'     => 'integer',
                'required' => true,
                'validate_callback' => function ( $value ) {
                    return get_post( (int) $value ) instanceof WP_Post;
                },
            ],
            'hours' => [
                'type'    => 'integer',
                'default' => 48,
                'minimum' => 1,
            ],
        ];
    }
}
