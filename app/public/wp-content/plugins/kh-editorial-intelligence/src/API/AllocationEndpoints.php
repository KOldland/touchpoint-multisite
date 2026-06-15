<?php

namespace KH\Editorial\API;

use KH\Editorial\Services\AllocationService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoints for cross-site content allocation.
 */
class AllocationEndpoints {

    private AllocationService $service;

    public function __construct() {
        $this->service = new AllocationService();
    }

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        // POST /kh-editorial/v1/allocation/clone
        register_rest_route( 'kh-editorial/v1', '/allocation/clone', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_clone' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'post_id'     => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'site_slugs'  => [
                    'required'          => true,
                    'type'              => 'array',
                    'items'             => [ 'type' => 'string' ],
                    'sanitize_callback' => function ( $slugs ) {
                        return array_map( 'sanitize_key', (array) $slugs );
                    },
                ],
                'rewrite'     => [
                    'required'          => false,
                    'type'              => 'boolean',
                    'default'           => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ] );

        // GET /kh-editorial/v1/allocation/status/{post_id}
        register_rest_route( 'kh-editorial/v1', '/allocation/status/(?P<post_id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'handle_status' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'post_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );

        // GET /kh-editorial/v1/allocation/sites — list available target sites
        register_rest_route( 'kh-editorial/v1', '/allocation/sites', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'handle_sites' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );
    }

    /**
     * Permission check: user must be able to edit posts.
     */
    public function check_permission(): bool {
        return current_user_can( 'edit_posts' );
    }

    /**
     * POST /allocation/clone — clone a post to one or more target sites.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_clone( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id    = $request->get_param( 'post_id' );
        $site_slugs = $request->get_param( 'site_slugs' );
        $rewrite    = $request->get_param( 'rewrite' ) ?? false;

        // Validate origin post exists
        $origin_post = get_post( $post_id );
        if ( ! $origin_post ) {
            return new WP_Error(
                'post_not_found',
                __( 'Origin post not found.', 'kh-editorial-intelligence' ),
                [ 'status' => 404 ]
            );
        }

        // Validate post is on the hub site (blog_id=1)
        if ( get_current_blog_id() !== 1 ) {
            return new WP_Error(
                'not_hub_site',
                __( 'Content allocation is only available from the central hub.', 'kh-editorial-intelligence' ),
                [ 'status' => 400 ]
            );
        }

        if ( empty( $site_slugs ) ) {
            return new WP_Error(
                'no_sites',
                __( 'No target sites selected.', 'kh-editorial-intelligence' ),
                [ 'status' => 400 ]
            );
        }

        $results  = [];
        $errors   = [];
        $success_count = 0;

        foreach ( $site_slugs as $slug ) {
            $blog_id = $this->service->resolve_blog_id( $slug );

            if ( ! $blog_id ) {
                $errors[] = [
                    'slug'    => $slug,
                    'message' => __( 'Could not resolve blog ID for this site.', 'kh-editorial-intelligence' ),
                ];
                continue;
            }

            $result = $this->service->clone_to_site( $post_id, $blog_id, $rewrite );

            if ( $result['success'] ) {
                $success_count++;
                $results[] = [
                    'slug'            => $slug,
                    'blog_id'         => $blog_id,
                    'target_post_id'  => $result['target_post_id'],
                    'edit_url'        => $result['edit_url'] ?? '',
                    'rewrite_applied' => $result['rewrite_applied'] ?? false,
                    'message'         => $result['message'] ?? '',
                ];
            } else {
                $errors[] = [
                    'slug'    => $slug,
                    'blog_id' => $blog_id,
                    'message' => $result['message'] ?? __( 'Clone failed.', 'kh-editorial-intelligence' ),
                ];
            }
        }

        return new WP_REST_Response( [
            'success'        => $success_count > 0,
            'success_count'  => $success_count,
            'total'          => count( $site_slugs ),
            'results'        => $results,
            'errors'         => $errors,
            'message'        => sprintf(
                /* translators: %d: number of successfully cloned posts */
                _n(
                    '%d post cloned successfully.',
                    '%d posts cloned successfully.',
                    $success_count,
                    'kh-editorial-intelligence'
                ),
                $success_count
            ),
        ] );
    }

    /**
     * GET /allocation/status/{post_id} — get allocation status for a post.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id = (int) $request->get_param( 'post_id' );

        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error(
                'post_not_found',
                __( 'Post not found.', 'kh-editorial-intelligence' ),
                [ 'status' => 404 ]
            );
        }

        $status = $this->service->get_allocation_status( $post_id );
        $allocated_count = count( array_filter( $status, fn( $s ) => $s['allocated'] ) );

        return new WP_REST_Response( [
            'success'         => true,
            'post_id'         => $post_id,
            'allocated_count' => $allocated_count,
            'total_sites'     => count( $status ),
            'sites'           => $status,
        ] );
    }

    /**
     * GET /allocation/sites — list all available target sites.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_sites( WP_REST_Request $request ): WP_REST_Response {
        $sites = $this->service->get_available_sites();

        return new WP_REST_Response( [
            'success' => true,
            'sites'   => $sites,
            'total'   => count( $sites ),
        ] );
    }
}