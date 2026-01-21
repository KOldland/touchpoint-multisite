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

            // Verify session ownership
            if (!$this->can_access_session($existing_session, $user_id)) {
                return new WP_Error('rest_forbidden', 'You are not allowed to access this session', array('status' => 403));
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
     * Check if current user can access a session
     */
    private function can_access_session($session, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // Admins can access any session
        if (current_user_can('manage_options')) {
            return true;
        }

        // Check session ownership
        $session_owner_id = null;
        if (is_array($session) && isset($session['created_by'])) {
            $session_owner_id = (int) $session['created_by'];
        } elseif (is_object($session) && isset($session->created_by)) {
            $session_owner_id = (int) $session->created_by;
        }

        return $session_owner_id && (int) $user_id === $session_owner_id;
    }

    /**
     * Get session status
     */
    public function get_session_status($request) {
        $session_id = $request->get_param('session_id');
        $db = new Dual_GPT_DB_Handler();
        $user_id = get_current_user_id();

        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        // Verify session ownership
        if (!$this->can_access_session($session, $user_id)) {
            return new WP_Error('rest_forbidden', 'You are not allowed to access this session', array('status' => 403));
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
        $user_id = get_current_user_id();

        // Verify session ownership
        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if (!$this->can_access_session($session, $user_id)) {
            return new WP_Error('rest_forbidden', 'You are not allowed to access this session', array('status' => 403));
        }

        global $wpdb;

        // Update approved citations - fix SQL injection by using integer IDs
        if (!empty($params['approved_citation_ids']) && is_array($params['approved_citation_ids'])) {
            $approved_ids = array_filter(array_map('absint', $params['approved_citation_ids']));
            if (!empty($approved_ids)) {
                $ids_list = implode(',', $approved_ids);
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}fg_validated_citations SET approved = 1 WHERE id IN ($ids_list) AND session_id = %s",
                        $session_id
                    )
                );
            }
        }

        // Mark rejected citations - fix SQL injection by using integer IDs
        if (!empty($params['rejected_citation_ids']) && is_array($params['rejected_citation_ids'])) {
            $rejected_ids = array_filter(array_map('absint', $params['rejected_citation_ids']));
            if (!empty($rejected_ids)) {
                $ids_list = implode(',', $rejected_ids);
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}fg_validated_citations SET approved = 0 WHERE id IN ($ids_list) AND session_id = %s",
                        $session_id
                    )
                );
            }
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
        $db = new Dual_GPT_DB_Handler();
        $user_id = get_current_user_id();

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

        // Verify session ownership through brief's session_id
        $session = $db->get_session($brief['session_id']);
        if (!$session || !$this->can_access_session($session, $user_id)) {
            return new WP_Error('rest_forbidden', 'You are not allowed to access this brief', array('status' => 403));
        }

        return $brief;
    }

    /**
     * Export brief
     */
    public function export_brief($request) {
        $brief_id = $request->get_param('fg_brief_id');
        $format = $request->get_param('format');
        $db = new Dual_GPT_DB_Handler();
        $user_id = get_current_user_id();

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

        // Verify session ownership
        $session = $db->get_session($brief['session_id']);
        if (!$session || !$this->can_access_session($session, $user_id)) {
            return new WP_Error('rest_forbidden', 'You are not allowed to export this brief', array('status' => 403));
        }

        // Generate export file
        $file_url = $this->generate_export_file($brief, $format);

        // Record export with error checking
        $insert_result = $wpdb->insert(
            $wpdb->prefix . 'fg_exports',
            array(
                'fg_brief_id' => $brief_id,
                'format' => $format,
                'file_url' => $file_url,
            )
        );

        if ($insert_result === false) {
            return new WP_Error(
                'export_record_failed',
                'Failed to record export in database.',
                array(
                    'status' => 500,
                    'db_error' => $wpdb->last_error,
                )
            );
        }

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
        $user_id = get_current_user_id();

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

        // Verify session ownership
        $session = $db->get_session($brief['session_id']);
        if (!$session || !$this->can_access_session($session, $user_id)) {
            return new WP_Error('rest_forbidden', 'You are not allowed to pass this brief to author', array('status' => 403));
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

        // For human author, create a placeholder job to maintain audit trail consistency
        $human_job_data = array(
            'session_id' => $brief['session_id'],
            'model' => 'human-author',
            'input_prompt' => wp_json_encode(array(
                'brief' => $brief,
                'instructions' => $instructions,
            )),
            'preset_id' => 'human-default',
            'idempotency_key' => 'human-' . wp_generate_uuid4(),
        );

        $human_job_id = $db->insert_job($human_job_data);
        if (is_wp_error($human_job_id)) {
            return $human_job_id;
        }

        $db->insert_audit_log($human_job_id, 'passed_to_human', array(
            'brief_id' => $brief_id,
            'instructions' => $instructions,
        ));

        return array('status' => 'passed_to_human', 'job_id' => $human_job_id);
    }

    /**
     * Generate export file
     * 
     * NOTE: This is a placeholder implementation. Full implementation would
     * generate actual files in various formats (PDF, DOCX, etc.) and save them
     * to the uploads directory.
     */
    private function generate_export_file($brief, $format) {
        // Placeholder: In production, this would create actual export files
        // Example implementation would:
        // 1. Create a file in wp-content/uploads/fg-exports/
        // 2. Populate it with formatted brief content
        // 3. Return the actual file URL
        // For now, return a placeholder URL
        return home_url('/wp-content/uploads/fg-exports/' . $brief['id'] . '.' . $format);
    }
}