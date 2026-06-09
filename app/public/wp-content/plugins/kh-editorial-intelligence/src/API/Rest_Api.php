<?php
namespace KH\Editorial\API;

use KH\Editorial\Core\Container;

/**
 * Rest_Api
 * 
 * Main REST API controller for the Editorial Intelligence Suite.
 */
class Rest_Api {

    protected string $namespace = 'editorial/v1';

    /**
     * Initialize REST routes.
     */
    public function init(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register the routes.
     */
    public function register_routes(): void {
        // --- Membership Namespace ---
        register_rest_route($this->namespace, '/member/post-data/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_member_post_data'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => ['validate_callback' => fn($param) => is_numeric($param)]
            ]
        ]);

        register_rest_route($this->namespace, '/member/posts-data', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_bulk_member_post_data'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/member/library', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'toggle_library_status'],
            'permission_callback' => fn() => is_user_logged_in()
        ]);

        // --- SEO Intelligence Namespace ---
        register_rest_route($this->namespace, '/seo/audit', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_seo_audit'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'post_id'         => ['required' => true, 'type' => 'integer'],
                'idempotency_key' => ['required' => true, 'type' => 'string'],
                'keyword'         => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field']
            ]
        ]);

        register_rest_route($this->namespace, '/seo/audit/status/(?P<job_id>[a-f0-9-]+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_seo_audit_status'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route($this->namespace, '/seo/apply', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_seo_apply'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'post_id'         => ['required' => true, 'type' => 'integer'],
                'actions'         => ['required' => true, 'type' => 'array'],
                'idempotency_key' => ['required' => true, 'type' => 'string'],
                'job_id'          => ['required' => false, 'type' => 'string'],
                'allow_schema'    => ['required' => false, 'type' => 'boolean', 'default' => false]
            ]
        ]);

        register_rest_route($this->namespace, '/seo/keywords', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_get_seo_keywords'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'post_id' => ['required' => true, 'type' => 'integer']
            ]
        ]);

        // --- Recommendation Intelligence Namespace ---
        register_rest_route($this->namespace, '/recommendations/(?P<post_id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_get_recommendations'],
            'permission_callback' => '__return_true',
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type'     => 'integer',
                    'validate_callback' => fn($param) => is_numeric($param)
                ],
                'limit'   => [
                    'required' => false,
                    'type'     => 'integer',
                    'default'  => 3,
                    'sanitize_callback' => fn($param) => min(max((int)$param, 1), 10)
                ],
                'force'   => [
                    'required' => false,
                    'type'     => 'boolean',
                    'default'  => false
                ]
            ]
        ]);

        register_rest_route($this->namespace, "/answer-cards/suggest", [
            "methods"             => \WP_REST_Server::CREATABLE,
            "callback"            => [$this, "handle_suggest_answer_cards"],
            "permission_callback" => [$this, "verify_nonce_and_logged_in"],
            "args" => [
                "post_id"         => ["required" => true, "type" => "integer"],
                "prompt"          => ["required" => true, "type" => "string", "sanitize_callback" => "sanitize_text_field"],
                "context"         => ["required" => false, "type" => "string", "sanitize_callback" => "sanitize_textarea_field"]
            ]
        ]);

        // --- Suggest AnswerCards Endpoint ---
        if (class_exists('KH\\Editorial\\API\\SuggestAnswerCardsEndpoint')) {
            $suggest_endpoint = new \KH\Editorial\API\SuggestAnswerCardsEndpoint();
            $suggest_endpoint->register();
        }

        // --- Audit & Budget Endpoints ---
        if (class_exists('KH\\Editorial\\API\\AuditEndpoints')) {
            $audit_endpoint = new \KH\Editorial\API\AuditEndpoints();
            $audit_endpoint->register();
        }

        if (class_exists('KH\\Editorial\\API\\BudgetEndpoints')) {
            $budget_endpoint = new \KH\Editorial\API\BudgetEndpoints();
            $budget_endpoint->register();
        }
    }

    /**
     * GET Handler for Recommendations.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_get_recommendations(\WP_REST_Request $request) {
        $post_id = (int) $request['post_id'];
        $limit   = (int) $request['limit'];
        $force   = (bool) $request['force'];

        // Pre-flight Guard: Verify the post exists and is published
        $post = get_post($post_id);
        if (!$post || 'publish' !== $post->post_status) {
            return new \WP_Error('post_not_found', 'The requested post was not found or is not published.', ['status' => 404]);
        }

        try {
            $agent = Container::get('RecommendationAgent');
            $data  = $agent->get_recommendations($post_id, $limit, $force);

            return new \WP_REST_Response([
                'success' => true,
                'data'    => $data,
                'forced'  => $force
            ], 200);

        } catch (\Exception $e) {
            return new \WP_Error('service_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * POST Handler for SEO Audit dispatch.
     */
    public function handle_seo_audit(\WP_REST_Request $request) {
        $post_id         = (int) $request['post_id'];
        $idempotency_key = $request['idempotency_key'];
        $keyword         = $request['keyword'] ?? '';
        $user_id         = get_current_user_id();

        try {
            $storage = Container::get('AIStorage');
            $budget  = $storage->check_budget($user_id);
            if (!$budget['has_budget']) {
                return new \WP_Error('budget_exceeded', 'Your AI token budget has been exceeded.', ['status' => 402]);
            }

            $existing = $storage->get_job_by_idempotency('seo-' . $post_id, $idempotency_key);
            if ($existing) {
                return new \WP_REST_Response(['job_id' => $existing['id'], 'status' => 'queued', 'reused' => true], 200);
            }

            $job_id = $storage->insert_job([
                'type'            => 'seo_audit',
                'session_id'      => 'seo-' . $post_id,
                'payload'         => wp_json_encode([
                    'post_id' => $post_id,
                    'keyword' => $keyword,
                    'user_id' => $user_id
                ]),
                'idempotency_key' => $idempotency_key
            ]);

            if (is_wp_error($job_id)) return $job_id;

            do_action('kh_editorial_job_created', $job_id, 'seo_audit');

            return new \WP_REST_Response(['job_id' => $job_id, 'status' => 'queued'], 202);

        } catch (\Exception $e) {
            return new \WP_Error('service_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * GET Handler for SEO Audit status.
     */
    public function handle_seo_audit_status(\WP_REST_Request $request) {
        $job_id = $request['job_id'];
        try {
            $storage = Container::get('AIStorage');
            $job     = $storage->get_job($job_id);
            if (!$job) return new \WP_Error('job_not_found', 'Audit job not found.', ['status' => 404]);
            $response = ['job_id' => $job['id'], 'status' => $job['status']];
            if ($job['status'] === 'completed') {
                $response['data'] = json_decode($job['response'], true);
            } elseif ($job['status'] === 'failed') {
                $response['error'] = $job['error_message'];
            }
            return new \WP_REST_Response($response, 200);
        } catch (\Exception $e) {
            return new \WP_Error('service_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * POST Handler for SEO Apply.
     */
    public function handle_seo_apply(\WP_REST_Request $request) {
        $post_id         = (int) $request['post_id'];
        $actions         = $request['actions'];
        $idempotency_key = $request['idempotency_key'];
        $job_id          = $request['job_id'] ?? '';
        $allow_schema    = (bool) $request['allow_schema'];
        $user_id         = get_current_user_id();

        try {
            $toolkit = Container::get('SEOToolkit');
            $result  = $toolkit->apply_actions($post_id, $actions, $idempotency_key, $allow_schema, $user_id, $job_id);
            if (is_wp_error($result)) return $result;
            return new \WP_REST_Response($result, 200);
        } catch (\Exception $e) {
            return new \WP_Error('service_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * GET Handler for SEO Keywords.
     */
    public function handle_get_seo_keywords(\WP_REST_Request $request) {
        $post_id = (int) $request['post_id'];
        if ( ! $post_id ) return new \WP_Error( 'missing_post_id', 'Post ID is required.', array( 'status' => 400 ) );

        $focus = get_post_meta( $post_id, '_khm_seo_focus_keyword', true );
        $keywords = get_post_meta( $post_id, '_khm_seo_keywords', true );

        $list = array();
        if ( is_string( $keywords ) && '' !== trim( $keywords ) ) {
            $list = array_filter( array_map( 'trim', explode( ',', $keywords ) ) );
        } elseif ( is_array( $keywords ) ) {
            $list = array_filter( $keywords );
        }
        if ( $focus && ! in_array( $focus, $list, true ) ) array_unshift( $list, $focus );

        return new \WP_REST_Response( array(
            'keywords' => array_values( $list ),
            'focus_keyword' => $focus,
        ), 200 );
    }

    /**
     * Verify REST API nonce and user is logged in.
     *
     * @return bool
     */
    public function verify_nonce_and_logged_in(): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return false;
        }

        return true;
    }

    /**
     * Verify REST API nonce and user can edit posts.
     *
     * @return bool
     */
    public function verify_nonce_and_edit_posts(): bool {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return false;
        }

        $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return false;
        }

        return true;
    }


    // --- Legacy Membership Handlers (Kept for compatibility) ---

    public function get_member_post_data($request) {
        $post_id = $request['id'];
        $user_id = get_current_user_id();
        try {
            $bridge = Container::get('SocialBridge');
            $data = $bridge->get_member_post_data($post_id, $user_id);
            return is_wp_error($data) ? $data : new \WP_REST_Response($data, 200);
        } catch (\Exception $e) {
            return new \WP_Error('container_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function get_bulk_member_post_data($request) {
        $ids_raw = $request->get_param('ids');
        if (empty($ids_raw)) return new \WP_REST_Response([], 200);
        $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
        $user_id = get_current_user_id();
        try {
            $bridge = Container::get('SocialBridge');
            $results = [];
            foreach ($ids as $post_id) {
                $data = $bridge->get_member_post_data($post_id, $user_id);
                if (!is_wp_error($data)) $results[$post_id] = $data;
            }
            return new \WP_REST_Response($results, 200);
        } catch (\Exception $e) {
            return new \WP_Error('container_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function toggle_library_status($request) {
        $post_id = $request->get_param('post_id');
        $user_id = get_current_user_id();
        if (!$post_id || !get_post($post_id)) return new \WP_Error('invalid_post', 'Post not found or invalid', ['status' => 404]);
        $action = $request->get_param('action');
        if ($action === 'remove') {
            delete_user_meta($user_id, '_khm_saved_post_' . $post_id);
            $message = 'Removed from library';
            $is_saved = false;
        } else {
            update_user_meta($user_id, '_khm_saved_post_' . $post_id, time());
            $message = 'Saved to library';
            $is_saved = true;
        }
        do_action('khm_library_updated', $user_id, $post_id, $action);
        return new \WP_REST_Response(['success' => true, 'message' => $message, 'is_saved' => $is_saved], 200);
    }
}
