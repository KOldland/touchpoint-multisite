<?php

namespace KH\EditorialAuthor\API;

use KH\EditorialAuthor\Agents\AuthorOrchestrator;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class AuthorEndpoints {

    public function register_routes() {
        register_rest_route('editorial/v1', '/author/run', [
            'methods' => 'POST',
            'callback' => [$this, 'run_author_agent'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'mode' => [
                    'type' => 'string',
                    'required' => true,
                    'enum' => ['draft', 'abstract', 'enrichment'],
                ],
                'planner_session_id' => [
                    'type' => 'integer',
                    'required' => false,
                ],
                'draft_content' => [
                    'type' => 'string',
                    'required' => false,
                ],
                'instructions' => [
                    'type' => 'string',
                    'required' => false,
                ],
            ],
        ]);
    }

    public function check_permissions() {
        return current_user_can('edit_posts');
    }

    public function run_author_agent(WP_REST_Request $request) {
        $params = $request->get_params();
        $orchestrator = new AuthorOrchestrator();
        
        $result = $orchestrator->run($params, get_current_user_id());

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result, 200);
    }
}
