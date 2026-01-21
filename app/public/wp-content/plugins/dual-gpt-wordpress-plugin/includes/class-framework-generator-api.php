<?php
/**
 * Framework Generator REST API Endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class Framework_Generator_API {

    /**
     * Register REST routes
     */
    public function register_routes() {
        register_rest_route('fg/v1', '/start', array(
            'methods' => 'POST',
            'callback' => array($this, 'start_framework_generation'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'session_id' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'article_idea' => array(
                    'type' => 'object',
                    'required' => true,
                ),
                'focus' => array(
                    'type' => 'object',
                    'required' => false,
                ),
                'sponsor_mode' => array(
                    'type' => 'boolean',
                    'default' => false,
                ),
                'idempotency_key' => array(
                    'type' => 'string',
                    'required' => false,
                ),
            ),
        ));

        register_rest_route('fg/v1', '/session/(?P<session_id>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_session_status'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('fg/v1', '/citation-qa/(?P<session_id>[a-zA-Z0-9-]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_citation_qa'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'approved_citation_ids' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                    'required' => true,
                ),
                'rejected_citation_ids' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                    'required' => false,
                ),
                'additional_keywords' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                    'required' => false,
                ),
            ),
        ));

        register_rest_route('fg/v1', '/brief/(?P<fg_brief_id>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_brief'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('fg/v1', '/export/(?P<fg_brief_id>[a-zA-Z0-9-]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'export_brief'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'format' => array(
                    'type' => 'string',
                    'enum' => array('json', 'docx', 'html', 'zip'),
                    'default' => 'json',
                ),
            ),
        ));

        register_rest_route('fg/v1', '/pass-to-author/(?P<fg_brief_id>[a-zA-Z0-9-]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'pass_to_author'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'target' => array(
                    'type' => 'string',
                    'enum' => array('author-agent', 'human'),
                    'default' => 'author-agent',
                ),
                'instructions' => array(
                    'type' => 'string',
                    'required' => false,
                ),
            ),
        ));
    }

    /**
     * Check user permissions
     */
    public function check_permissions() {
        return current_user_can('edit_posts');
    }

    /**
     * Start framework generation
     */
    public function start_framework_generation($request) {
        $db = new Dual_GPT_DB_Handler();

        // Check budget
        $user_id = get_current_user_id();
        $budget = $db->check_user_budget($user_id);
        if ($budget['token_used'] >= $budget['token_limit']) {
            return new WP_Error('budget_exceeded', 'Token budget exceeded', array('status' => 403));
        }

        $params = $request->get_params();

        // Create or attach session
        $session_data = array(
            'role' => 'research',
            'title' => $params['article_idea']['title'] ?? 'Framework Generation',
            'idempotency_key' => $params['idempotency_key'] ?? null,
        );

        if (!empty($params['session_id'])) {
            $existing_session = $db->get_session($params['session_id']);
            if (!$existing_session) {
                return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
            }
            $session_id = $params['session_id'];
        } else {
            $session_id = $db->insert_session($session_data);
            if (is_wp_error($session_id)) {
                return $session_id;
            }
        }

        // Create Phase 1 job
        $job_data = array(
            'session_id' => $session_id,
            'model' => 'gpt-4o-mini',
            'input_prompt' => wp_json_encode($params),
            'preset_id' => 'fg-framework-generator',
            'idempotency_key' => 'phase1-' . ($params['idempotency_key'] ?? wp_generate_uuid4()),
        );

        $job_id = $db->insert_job($job_data);
        if (is_wp_error($job_id)) {
            return $job_id;
        }

        // Log audit
        $db->insert_audit_log($job_id, 'queued', array('phase' => 'foundational_discovery'));

        return array(
            'session_id' => $session_id,
            'job_id' => $job_id,
            'status' => 'queued',
        );
    }

    /**
     * Get session status
     */
    public function get_session_status($request) {
        $session_id = $request->get_param('session_id');
        $db = new Dual_GPT_DB_Handler();

        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        // Get jobs
        global $wpdb;
        $jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, status, created_at, finished_at FROM {$wpdb->prefix}ai_jobs WHERE session_id = %s ORDER BY created_at DESC",
                $session_id
            ),
            ARRAY_A
        );

        // Get citations if in QA phase
        $citations = array();
        if (!empty($jobs) && $jobs[0]['status'] === 'waiting_for_human') {
            $citations = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}fg_validated_citations WHERE session_id = %s ORDER BY authority_score DESC",
                    $session_id
                ),
                ARRAY_A
            );
        }

        // Get token usage
        $usage = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT SUM(usage_prompt_tokens + usage_completion_tokens) as total_tokens, SUM(cost_micro) as total_cost FROM {$wpdb->prefix}ai_jobs WHERE session_id = %s",
                $session_id
            ),
            ARRAY_A
        );

        return array(
            'session' => $session,
            'jobs' => $jobs,
            'citations' => $citations,
            'usage' => $usage,
        );
    }

    /**
     * Handle citation QA decisions
     */
    public function handle_citation_qa($request) {
        $session_id = $request->get_param('session_id');
        $params = $request->get_params();

        $db = new Dual_GPT_DB_Handler();

        // Update approved citations
        if (!empty($params['approved_citation_ids'])) {
            global $wpdb;
            $placeholders = str_repeat('%s,', count($params['approved_citation_ids']) - 1) . '%s';
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}fg_validated_citations SET approved = 1 WHERE id IN ($placeholders) AND session_id = %s",
                    array_merge($params['approved_citation_ids'], array($session_id))
                )
            );
        }

        // Mark rejected citations
        if (!empty($params['rejected_citation_ids'])) {
            $placeholders = str_repeat('%s,', count($params['rejected_citation_ids']) - 1) . '%s';
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}fg_validated_citations SET approved = 0 WHERE id IN ($placeholders) AND session_id = %s",
                    array_merge($params['rejected_citation_ids'], array($session_id))
                )
            );
        }

        // Create Phase 3 job
        $job_data = array(
            'session_id' => $session_id,
            'model' => 'gpt-4o-mini',
            'preset_id' => 'fg-framework-generator',
            'idempotency_key' => 'phase3-' . wp_generate_uuid4(),
        );

        $job_id = $db->insert_job($job_data);
        if (is_wp_error($job_id)) {
            return $job_id;
        }

        $db->insert_audit_log($job_id, 'queued', array('phase' => 'framework_synthesis'));

        return array(
            'job_id' => $job_id,
            'status' => 'queued',
        );
    }

    /**
     * Get final brief
     */
    public function get_brief($request) {
        $brief_id = $request->get_param('fg_brief_id');

        global $wpdb;
        $brief = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fg_briefs WHERE id = %s",
                $brief_id
            ),
            ARRAY_A
        );

        if (!$brief) {
            return new WP_Error('brief_not_found', 'Brief not found', array('status' => 404));
        }

        return $brief;
    }

    /**
     * Export brief
     */
    public function export_brief($request) {
        $brief_id = $request->get_param('fg_brief_id');
        $format = $request->get_param('format');

        // Get brief
        global $wpdb;
        $brief = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fg_briefs WHERE id = %s",
                $brief_id
            ),
            ARRAY_A
        );

        if (!$brief) {
            return new WP_Error('brief_not_found', 'Brief not found', array('status' => 404));
        }

        // Generate export file (simplified - would need actual implementation)
        $file_url = $this->generate_export_file($brief, $format);

        // Record export
        $wpdb->insert(
            $wpdb->prefix . 'fg_exports',
            array(
                'fg_brief_id' => $brief_id,
                'format' => $format,
                'file_url' => $file_url,
            )
        );

        return array(
            'file_url' => $file_url,
            'format' => $format,
        );
    }

    /**
     * Pass to author
     */
    public function pass_to_author($request) {
        $brief_id = $request->get_param('fg_brief_id');
        $target = $request->get_param('target');
        $instructions = $request->get_param('instructions');

        $db = new Dual_GPT_DB_Handler();

        // Get brief
        global $wpdb;
        $brief = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fg_briefs WHERE id = %s",
                $brief_id
            ),
            ARRAY_A
        );

        if (!$brief) {
            return new WP_Error('brief_not_found', 'Brief not found', array('status' => 404));
        }

        if ($target === 'author-agent') {
            // Create author job
            $job_data = array(
                'session_id' => $brief['session_id'],
                'model' => 'gpt-4',
                'input_prompt' => wp_json_encode(array(
                    'brief' => $brief,
                    'instructions' => $instructions,
                )),
                'preset_id' => 'author-default',
                'idempotency_key' => 'author-' . wp_generate_uuid4(),
            );

            $job_id = $db->insert_job($job_data);
            if (is_wp_error($job_id)) {
                return $job_id;
            }

            $db->insert_audit_log($job_id, 'queued', array('phase' => 'author_generation'));

            return array(
                'job_id' => $job_id,
                'status' => 'queued',
            );
        }

        // For human author, just log
        $db->insert_audit_log(null, 'passed_to_human', array(
            'brief_id' => $brief_id,
            'instructions' => $instructions,
        ));

        return array('status' => 'passed_to_human');
    }

    /**
     * Generate export file (placeholder)
     */
    private function generate_export_file($brief, $format) {
        // This would implement actual file generation
        // For now, return a placeholder URL
        return home_url('/wp-content/uploads/fg-exports/' . $brief['id'] . '.' . $format);
    }
}