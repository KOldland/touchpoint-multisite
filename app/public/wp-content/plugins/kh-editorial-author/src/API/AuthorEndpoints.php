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

        register_rest_route('editorial/v1', '/author/job/(?P<id>[a-f0-9\-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_job_status'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
    }

    public function check_permissions() {
        return current_user_can('edit_posts');
    }

    public function run_author_agent(WP_REST_Request $request) {
        $params = $request->get_params();
        $orchestrator = \KH\EditorialAuthor\Core\AuthorPlugin::get_instance()->get_orchestrator();
        
        $result = $orchestrator->run($params, get_current_user_id());

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result, 200);
    }

    public function get_job_status(WP_REST_Request $request) {
        $job_id = $request->get_param('id');
        $orchestrator = \KH\EditorialAuthor\Core\AuthorPlugin::get_instance()->get_orchestrator();
        
        // We'll need a way to get job status through orchestrator or bridge
        $bridge = new \KH\EditorialAuthor\Integration\IntelligenceBridge();
        $job = $bridge->get_job($job_id);

        if (is_wp_error($job)) {
            return $job;
        }

        if (!$job) {
            return new WP_Error('job_not_found', 'Job not found.', ['status' => 404]);
        }

        // Parse response if it exists
        if (!empty($job['response'])) {
            $job['response'] = json_decode($job['response'], true);
        }

        return new WP_REST_Response($job, 200);
    }
}
