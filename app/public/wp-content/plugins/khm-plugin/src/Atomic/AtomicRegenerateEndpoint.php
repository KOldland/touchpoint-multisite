<?php
/**
 * Atomic Regenerate REST Endpoint
 *
 * POST /wp-json/khm/v1/posts/{id}/atomic/regenerate
 *
 * Triggers synchronous GPT decomposition for a specific parent post.
 * Requires: edit_posts capability (logged-in admin/editor).
 *
 * Response:
 * {
 *   "count":      int,    // Number of atomic articles created
 *   "post_ids":   int[],  // IDs of created atomic_article posts
 *   "message":    string
 * }
 *
 * @package KHM\Atomic
 */

namespace KHM\Atomic;

defined( 'ABSPATH' ) || exit;

/**
 * Atomic Regenerate Endpoint
 */
class AtomicRegenerateEndpoint {

    /**
     * Generator instance.
     *
     * @var AtomicArticleGenerator
     */
    private AtomicArticleGenerator $generator;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->generator = new AtomicArticleGenerator();
    }

    /**
     * Register the REST route.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'rest_api_init', function () {
            register_rest_route(
                'khm/v1',
                '/posts/(?P<id>[\d]+)/atomic/regenerate',
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'handle' ),
                    'permission_callback' => array( $this, 'check_permission' ),
                    'args'                => array(
                        'id' => array(
                            'type'              => 'integer',
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                            'minimum'           => 1,
                        ),
                    ),
                )
            );
        } );
    }

    /**
     * Permission check — must be able to edit the target post.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return bool|\WP_Error
     */
    public function check_permission( \WP_REST_Request $request ) {
        $post_id = (int) $request->get_param( 'id' );

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You are not allowed to regenerate atomic articles for this post.', 'khm-membership' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Handle the regeneration request.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle( \WP_REST_Request $request ) {
        $post_id = (int) $request->get_param( 'id' );

        $post = get_post( $post_id );
        if ( ! $post || AtomicArticlePostType::POST_TYPE === $post->post_type ) {
            return new \WP_Error(
                'invalid_post',
                __( 'Post not found or invalid post type.', 'khm-membership' ),
                array( 'status' => 404 )
            );
        }

        $result = $this->generator->generate( $post_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array(
            'count'    => count( $result ),
            'post_ids' => $result,
            'message'  => sprintf(
                /* translators: %d: number of atomic articles */
                _n( '%d atomic article created.', '%d atomic articles created.', count( $result ), 'khm-membership' ),
                count( $result )
            ),
        ) );
    }
}
