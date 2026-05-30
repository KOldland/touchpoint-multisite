<?php
namespace KH\Editorial\API;

use KH\Editorial\Bridge\SocialBridge;

/**
 * Rest_Api
 * 
 * Main REST API controller for the Editorial Intelligence Suite.
 */
class Rest_Api {

    protected $namespace = 'editorial/v1';

    /**
     * Initialize REST routes.
     */
    public function init() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register the routes.
     */
    public function register_routes() {
        // GET Member Data for a Post
        register_rest_route($this->namespace, '/member/post-data/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_member_post_data'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => ['validate_callback' => fn($param) => is_numeric($param)]
            ]
        ]);

        // GET Bulk Member Data for multiple Posts
        register_rest_route($this->namespace, '/member/posts-data', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_bulk_member_post_data'],
            'permission_callback' => '__return_true',
        ]);

        // POST Save to Library
        register_rest_route($this->namespace, '/member/library', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'toggle_library_status'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
    }

    /**
     * GET Handler for member post data.
     */
    public function get_member_post_data($request) {
        $post_id = $request['id'];
        $user_id = get_current_user_id();

        // Get the Bridge via the Container (DI)
        try {
            $bridge = \KH\Editorial\Core\Container::get('SocialBridge');
        } catch (\Exception $e) {
            return new \WP_Error('container_error', $e->getMessage(), ['status' => 500]);
        }

        $data = $bridge->get_member_post_data($post_id, $user_id);

        if (is_wp_error($data)) {
            return $data;
        }

        return new \WP_REST_Response($data, 200);
    }

    /**
     * GET Handler for bulk member post data.
     */
    public function get_bulk_member_post_data($request) {
        $ids_raw = $request->get_param('ids');
        if (empty($ids_raw)) {
            return new \WP_REST_Response([], 200);
        }

        $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
        $user_id = get_current_user_id();
        
        try {
            $bridge = \KH\Editorial\Core\Container::get('SocialBridge');
        } catch (\Exception $e) {
            return new \WP_Error('container_error', $e->getMessage(), ['status' => 500]);
        }

        $results = [];
        foreach ($ids as $post_id) {
            $data = $bridge->get_member_post_data($post_id, $user_id);
            if (!is_wp_error($data)) {
                $results[$post_id] = $data;
            }
        }

        return new \WP_REST_Response($results, 200);
    }

    /**
     * POST Handler to save/remove from library.
     */
    public function toggle_library_status($request) {
        $post_id = $request->get_param('post_id');
        $user_id = get_current_user_id();

        if (!$post_id || !get_post($post_id)) {
            return new \WP_Error('invalid_post', 'Post not found or invalid', ['status' => 404]);
        }

        $action = $request->get_param('action'); // 'save' or 'remove'

        // Logic to update meta/service
        if ($action === 'remove') {
            delete_user_meta($user_id, '_khm_saved_post_' . $post_id);
            $message = 'Removed from library';
            $is_saved = false;
        } else {
            update_user_meta($user_id, '_khm_saved_post_' . $post_id, time());
            $message = 'Saved to library';
            $is_saved = true;
        }

        // Also trigger legacy hook for sync
        do_action('khm_library_updated', $user_id, $post_id, $action);

        return new \WP_REST_Response([
            'success' => true,
            'message' => $message,
            'is_saved' => $is_saved
        ], 200);
    }
}
