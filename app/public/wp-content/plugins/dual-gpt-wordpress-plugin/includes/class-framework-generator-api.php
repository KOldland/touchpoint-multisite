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
                'planner_session_id' => array(
                    'type' => 'string',
                    'required' => false,
                ),
            ),
        ));

        register_rest_route('fg/v1', '/citation-qa/(?P<session_id>[a-zA-Z0-9-]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'citation_qa'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'approved_citation_ids' => array(
                    'type' => 'array',
                    'items' => array('type' => 'integer'),
                    'required' => true,
                ),
                'rejected_citation_ids' => array(
                    'type' => 'array',
                    'items' => array('type' => 'integer'),
                    'required' => true,
                ),
                'additional_keywords' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                    'required' => false,
                    'default' => array(),
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
     * Simple per-user rate limiting
     */
    private function check_rate_limit($type, $user_id, $limit, $window_seconds) {
        $key = "fg_rate_{$type}_{$user_id}";
        $data = get_transient($key);
        $now = time();

        if (!$data || !isset($data['window_start']) || ($now - $data['window_start']) > $window_seconds) {
            set_transient($key, array('window_start' => $now, 'count' => 1), $window_seconds);
            return true;
        }

        if ($data['count'] >= $limit) {
            return false;
        }

        $data['count']++;
        set_transient($key, $data, $window_seconds);
        return true;
    }

    /**
     * Start framework generation
     */
    public function start_framework_generation($request) {
        $db = new Dual_GPT_DB_Handler();

        // Check rate limit
        $user_id = get_current_user_id();
        if (!$this->check_rate_limit('fg_start', $user_id, 5, 3600)) { // 5 per hour
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded. Please try again later.', array('status' => 429));
        }

        // Check budget
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

        $approved_ids = is_array($params['approved_citation_ids'] ?? null) ? $params['approved_citation_ids'] : array();
        $rejected_ids = is_array($params['rejected_citation_ids'] ?? null) ? $params['rejected_citation_ids'] : array();
        $additional_keywords = is_array($params['additional_keywords'] ?? null) ? $params['additional_keywords'] : array();

        if (empty($approved_ids) && empty($rejected_ids)) {
            return new WP_Error('missing_decisions', 'At least one approved or rejected citation ID is required.', array('status' => 400));
        }

        $invalid_ids = array();

        // Update approved citations - use individual updates for proper SQL safety
        foreach ($approved_ids as $citation_id) {
            $citation_id = sanitize_text_field($citation_id);
            // Validate UUID format
            if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $citation_id)) {
                $invalid_ids[] = $citation_id;
                continue;
            }
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}fg_validated_citations SET approved = 1 WHERE id = %s AND session_id = %s",
                    $citation_id,
                    $session_id
                )
            );
        }

        // Mark rejected citations - use individual updates for proper SQL safety
        foreach ($rejected_ids as $citation_id) {
            $citation_id = sanitize_text_field($citation_id);
            // Validate UUID format
            if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $citation_id)) {
                $invalid_ids[] = $citation_id;
                continue;
            }
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}fg_validated_citations SET approved = 0 WHERE id = %s AND session_id = %s",
                    $citation_id,
                    $session_id
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

        $warnings = array();
        if (!empty($invalid_ids)) {
            $warnings[] = 'Some citation IDs were invalid and were skipped.';
        }
        if (empty($approved_ids)) {
            $warnings[] = 'No citations were approved. Framework generation will use remaining data.';
        }

        return array(
            'job_id' => $job_id,
            'status' => 'queued',
            'warnings' => $warnings,
            'invalid_ids' => $invalid_ids,
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
        global $wpdb;

        if (!in_array($format, array('docx', 'html'))) {
            return new WP_Error('invalid_format', 'Invalid format. Supported: docx, html', array('status' => 400));
        }

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

        $exporter = new Framework_Generator_Exporter();

        if ($format === 'docx') {
            $result = $exporter->export_to_docx($brief_id);
        } else {
            $result = $exporter->export_to_html($brief_id);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'file_url' => $result['file_url'],
            'format' => $format,
            'filename' => $result['filename'],
        );
    }

    /**
     * Pass to author
     */
    public function pass_to_author($request) {
        $brief_id = $request->get_param('fg_brief_id');
        $target = $request->get_param('target');
        $instructions = $request->get_param('instructions');
        $planner_session_id = $request->get_param('planner_session_id');

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
            $model = 'gpt-4o-mini';
            if (class_exists('\\Dual_GPT\\Dual_GPT_LLM_Client')) {
                $llm_client = new \Dual_GPT\Dual_GPT_LLM_Client();
                $model = $llm_client->get_model_name();
            }

            // Create author job
            $job_data = array(
                'session_id' => $brief['session_id'],
                'model' => $model,
                'input_prompt' => wp_json_encode(array(
                    'author_mode' => 'draft',
                    'framework_brief_id' => $brief_id,
                    'planner_session_id' => $planner_session_id,
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
     * Citation QA endpoint
     */
    public function citation_qa($request) {
        $session_id = $request->get_param('session_id');
        $approved_citation_ids = $request->get_param('approved_citation_ids');
        $rejected_citation_ids = $request->get_param('rejected_citation_ids');
        $additional_keywords = $request->get_param('additional_keywords');

        global $wpdb;

        // Update approved citations
        if (!empty($approved_citation_ids)) {
            $placeholders = implode(',', array_fill(0, count($approved_citation_ids), '%d'));
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}fg_validated_citations SET status = 'approved' WHERE id IN ($placeholders)",
                    $approved_citation_ids
                )
            );
        }

        // Update rejected citations and add domains to exclusions
        if (!empty($rejected_citation_ids)) {
            $placeholders = implode(',', array_fill(0, count($rejected_citation_ids), '%d'));
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}fg_validated_citations SET status = 'rejected' WHERE id IN ($placeholders)",
                    $rejected_citation_ids
                )
            );

            // Add domains to exclusions
            foreach ($rejected_citation_ids as $citation_id) {
                $citation = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT url FROM {$wpdb->prefix}fg_validated_citations WHERE id = %d",
                        $citation_id
                    ),
                    ARRAY_A
                );
                if ($citation) {
                    $domain = $this->extract_domain_from_url($citation['url']);
                    if ($domain) {
                        $this->add_session_exclusion($session_id, $domain);
                    }
                }
            }
        }

        // Add additional keywords to session
        if (!empty($additional_keywords)) {
            $this->add_session_keywords($session_id, $additional_keywords);
        }

        // Log audit
        $db = new Dual_GPT_DB_Handler();
        $db->insert_audit_log(null, 'citation_qa_completed', array(
            'session_id' => $session_id,
            'approved_count' => count($approved_citation_ids),
            'rejected_count' => count($rejected_citation_ids),
            'additional_keywords' => $additional_keywords,
        ));

        return array(
            'status' => 'qa_completed',
            'approved_count' => count($approved_citation_ids),
            'rejected_count' => count($rejected_citation_ids),
        );
    }

    /**
     * Extract domain from URL
     */
    private function extract_domain_from_url($url) {
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }

    /**
     * Add domain to session exclusions
     */
    private function add_session_exclusion($session_id, $domain) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'fg_session_exclusions',
            array(
                'session_id' => $session_id,
                'domain' => $domain,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s')
        );
    }

    /**
     * Add keywords to session
     */
    private function add_session_keywords($session_id, $keywords) {
        global $wpdb;
        foreach ($keywords as $keyword) {
            $wpdb->insert(
                $wpdb->prefix . 'fg_session_keywords',
                array(
                    'session_id' => $session_id,
                    'keyword' => $keyword,
                    'created_at' => current_time('mysql'),
                ),
                array('%s', '%s', '%s')
            );
        }
    }
}
