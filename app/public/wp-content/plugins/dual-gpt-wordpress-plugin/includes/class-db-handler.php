<?php
/**
 * Database handler for Dual-GPT Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_DB_Handler {

    /**
     * Insert a new session
     */
    public function insert_session($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_sessions';

        $defaults = array(
            'id' => wp_generate_uuid4(),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        $data = wp_parse_args($data, $defaults);

        if (!empty($data['idempotency_key'])) {
            $existing = $this->get_session_by_idempotency($data['idempotency_key']);
            if ($existing) {
                return $existing['id'];
            }
        }

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            $db_error = isset($wpdb->last_error) && $wpdb->last_error ? $wpdb->last_error : 'No database error reported.';
            return new WP_Error('db_insert_error', 'Failed to insert session. DB error: ' . $db_error);
        }

        return $data['id'];
    }

    /**
     * Insert a new job
     */
    public function insert_job($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_jobs';

        $defaults = array(
            'id' => wp_generate_uuid4(),
            'status' => 'queued',
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
        );

        $data = wp_parse_args($data, $defaults);

        if (!empty($data['idempotency_key'])) {
            $existing = $this->get_job_by_idempotency($data['session_id'], $data['idempotency_key']);
            if ($existing) {
                return $existing['id'];
            }
        }

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            $db_error = isset($wpdb->last_error) && $wpdb->last_error ? $wpdb->last_error : 'No database error reported.';
            return new WP_Error('db_insert_error', 'Failed to insert job. DB error: ' . $db_error);
        }

        return $data['id'];
    }

    /**
     * Update job status
     */
    public function update_job_status($job_id, $status, $additional_data = array()) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_jobs';

        $data = array_merge($additional_data, array(
            'status' => $status,
        ));

        if ($status === 'completed' || $status === 'failed') {
            $data['finished_at'] = current_time('mysql');
        }

        $result = $wpdb->update($table, $data, array('id' => $job_id));

        return $result !== false;
    }

    /**
     * Insert audit log
     */
    public function insert_audit_log($job_id, $event_type, $payload = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_audit';

        $data = array(
            'job_id' => $job_id,
            'event_type' => $event_type,
            'payload_json' => $payload ? wp_json_encode($payload) : null,
            'created_at' => current_time('mysql'),
        );

        $result = $wpdb->insert($table, $data);

        return $result !== false;
    }

    /**
     * Get session by ID
     */
    public function get_session($session_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_sessions';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %s", $session_id),
            ARRAY_A
        );
    }

    /**
     * Delete session by ID
     */
    public function delete_session($session_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_sessions';

        $result = $wpdb->delete(
            $table,
            array('id' => $session_id),
            array('%s')
        );

        if ($result === false) {
            $db_error = isset($wpdb->last_error) && $wpdb->last_error ? $wpdb->last_error : 'No database error reported.';
            return new WP_Error('db_delete_error', 'Failed to delete session. DB error: ' . $db_error);
        }

        return $result > 0;
    }

    /**
     * Update session metadata JSON
     */
    public function update_session_meta($session_id, $meta) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_sessions';
        $meta_json = $meta !== null ? wp_json_encode($meta) : null;

        $result = $wpdb->update(
            $table,
            array(
                'meta_json' => $meta_json,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $session_id)
        );

        if ($result === false) {
            $db_error = isset($wpdb->last_error) && $wpdb->last_error ? $wpdb->last_error : 'No database error reported.';
            error_log('[PLANNER] update_session_meta failed: ' . $db_error);
        }

        return $result !== false;
    }

    /**
     * Get sessions with optional filters
     */
    public function get_sessions($args = array()) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_sessions';
        $where = array();
        $params = array();

        if (!empty($args['created_by'])) {
            $where[] = 'created_by = %d';
            $params[] = (int) $args['created_by'];
        }

        if (!empty($args['role'])) {
            $where[] = 'role = %s';
            $params[] = $args['role'];
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = !empty($args['limit']) ? (int) $args['limit'] : 25;
        if ($limit < 1) {
            $limit = 25;
        }

        $query = "SELECT * FROM $table $where_sql ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;

        $prepared = $wpdb->prepare($query, $params);
        return $wpdb->get_results($prepared, ARRAY_A);
    }

    /**
     * Get session by idempotency key
     */
    public function get_session_by_idempotency($idempotency_key) {
        if (empty($idempotency_key)) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ai_sessions';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE idempotency_key = %s", $idempotency_key),
            ARRAY_A
        );
    }

    /**
     * Get job by ID
     */
    public function get_job($job_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_jobs';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %s", $job_id),
            ARRAY_A
        );
    }

    /**
     * Insert article into session meta
     */
    public function insert_article($session_id, $article_data) {
        $session = $this->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found');
        }

        $meta = $session['meta_json'] ? json_decode($session['meta_json'], true) : array();
        if (!is_array($meta)) {
            $meta = array();
        }

        $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();
        $articles[] = $article_data;
        $meta['articles'] = $articles;

        return $this->update_session_meta($session_id, $meta);
    }

    /**
     * Update article framework info
     */
    public function update_article_framework($session_id, $article_id, $framework_data) {
        $session = $this->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found');
        }

        $meta = $session['meta_json'] ? json_decode($session['meta_json'], true) : array();
        if (!is_array($meta)) {
            $meta = array();
        }

        $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();
        foreach ($articles as $index => $article) {
            if (($article['id'] ?? '') !== $article_id) {
                continue;
            }
            $articles[$index]['framework'] = $framework_data;
            break;
        }

        $meta['articles'] = $articles;
        return $this->update_session_meta($session_id, $meta);
    }

    /**
     * Add citation to an article
     */
    public function add_article_citation($session_id, $article_id, $citation) {
        $session = $this->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found');
        }

        $meta = $session['meta_json'] ? json_decode($session['meta_json'], true) : array();
        if (!is_array($meta)) {
            $meta = array();
        }

        $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();
        foreach ($articles as $index => $article) {
            if (($article['id'] ?? '') !== $article_id) {
                continue;
            }
            $citations = isset($articles[$index]['citations']) && is_array($articles[$index]['citations'])
                ? $articles[$index]['citations']
                : array();
            $citations[] = $citation;
            $articles[$index]['citations'] = $citations;
            $articles[$index]['citation_count'] = count($citations);
            break;
        }

        $meta['articles'] = $articles;
        return $this->update_session_meta($session_id, $meta);
    }

    /**
     * Get job by idempotency key
     */
    public function get_job_by_idempotency($session_id, $idempotency_key) {
        if (empty($idempotency_key)) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ai_jobs';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE session_id = %s AND idempotency_key = %s",
                $session_id,
                $idempotency_key
            ),
            ARRAY_A
        );
    }

    /**
     * Get preset by ID
     */
    public function get_preset($preset_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_presets';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %s", $preset_id),
            ARRAY_A
        );
    }

    /**
     * Check user budget
     */
    public function check_user_budget($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_budgets';

        $budget = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE scope = 'user' AND scope_id = %s AND reset_at > NOW()",
                $user_id
            ),
            ARRAY_A
        );

        if (!$budget) {
            // Create default budget if not exists
            $this->create_user_budget($user_id);
            return array('token_limit' => 200000, 'token_used' => 0); // Default limits
        }

        return $budget;
    }

    /**
     * Create user budget
     */
    private function create_user_budget($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_budgets';

        $data = array(
            'scope' => 'user',
            'scope_id' => $user_id,
            'period' => 'monthly',
            'token_limit' => 200000, // Default limit
            'token_used' => 0,
            'reset_at' => date('Y-m-d H:i:s', strtotime('+1 month')),
        );

        $wpdb->insert($table, $data);
    }

    /**
     * Update token usage
     */
    public function update_token_usage($user_id, $tokens_used) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_budgets';

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table SET token_used = token_used + %d WHERE scope = 'user' AND scope_id = %s AND reset_at > NOW()",
                $tokens_used,
                $user_id
            )
        );
    }

    /**
     * Insert a new preset
     */
    public function insert_preset($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_presets';

        $defaults = array(
            'id' => wp_generate_uuid4(),
            'preset_version' => '1.0.0',
            'is_locked' => false,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            return new WP_Error('db_insert_error', 'Failed to insert preset');
        }

        return $data['id'];
    }

    /**
     * Update preset
     */
    public function update_preset($preset_id, $data) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_presets';

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update($table, $data, array('id' => $preset_id));

        return $result !== false;
    }

    /**
     * Delete preset
     */
    public function delete_preset($preset_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_presets';

        $result = $wpdb->delete($table, array('id' => $preset_id));

        return $result !== false;
    }

    /**
     * Get all presets
     */
    public function get_presets($role = null, $limit = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'ai_presets';

        $where = '';
        $args = array();

        if ($role) {
            $where = 'WHERE role = %s OR role = "both"';
            $args[] = $role;
        }

        $limit_clause = $limit ? $wpdb->prepare('LIMIT %d', $limit) : '';

        $query = "SELECT * FROM $table $where ORDER BY name ASC $limit_clause";

        if (!empty($args)) {
            $query = $wpdb->prepare($query, $args);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Create default presets
     */
    public function create_default_presets() {
        $defaults = array(
            array(
                'id' => 'research-default',
                'name' => 'Research Assistant',
                'role' => 'research',
                'system_prompt' => 'You are an expert research assistant. Use available tools to gather comprehensive, accurate information. Always cite sources and provide evidence for your findings.',
                'default_model' => 'gpt-4',
                'params_json' => wp_json_encode(array(
                    'temperature' => 0.3,
                    'max_tokens' => 2000,
                )),
                'tool_whitelist' => wp_json_encode(array('web_search', 'fetch_url', 'summarize_pdf', 'citation_check')),
                'is_locked' => true,
            ),
            array(
                'id' => 'author-default',
                'name' => 'Content Author',
                'role' => 'author',
                'system_prompt' => 'You are a professional content author. Create engaging, well-structured content that follows journalistic best practices. Focus on clarity, accuracy, and compelling storytelling.',
                'default_model' => 'gpt-4',
                'params_json' => wp_json_encode(array(
                    'temperature' => 0.7,
                    'max_tokens' => 1500,
                )),
                'tool_whitelist' => wp_json_encode(array('outline_from_brief', 'expand_section', 'style_guard', 'citation_guard')),
                'is_locked' => true,
            ),
            array(
                'id' => 'pll-writer-gpt',
                'name' => 'PLL Writer GPT',
                'role' => 'author',
                'system_prompt' => 'You are PLL Writer GPT, a specialized content creation AI trained on premium long-form journalism. You excel at creating publication-ready drafts with sophisticated narrative structures, precise language, and editorial excellence.',
                'default_model' => 'gpt-4',
                'params_json' => wp_json_encode(array(
                    'temperature' => 0.6,
                    'max_tokens' => 2000,
                    'presence_penalty' => 0.1,
                    'frequency_penalty' => 0.1,
                )),
                'tool_whitelist' => wp_json_encode(array('outline_from_brief', 'expand_section', 'style_guard', 'citation_guard')),
                'is_locked' => true,
            ),
            array(
                'id' => 'seo-agent',
                'name' => 'SEO Agent',
                'role' => 'seo',
                'system_prompt' => 'You are the KHM SEO Agent. Analyze content for SEO, prioritize improvements and produce structured JSON. Only use allowed tools. Do NOT apply changes directly without a confirmed apply action from a human. For upstream proposals return structured proposals and preview content. Return errors in a strict JSON schema when failing. Respect sponsor_safe and no_hallucination constraints.',
                'default_model' => 'gpt-4-turbo',
                'params_json' => wp_json_encode(array(
                    'temperature' => 0.2,
                    'max_tokens' => 2000,
                )),
                'tool_whitelist' => wp_json_encode(array('seo_tools', 'research_tools')),
                'is_locked' => true,
            ),
            array(
                'id' => 'fg-framework-generator',
                'name' => 'Framework Generator',
                'role' => 'research',
                'system_prompt' => 'You are the Framework Generator. Produce a Research Brief from the provided article idea and constraints. Follow the Research Process:
Phase 1: Foundational Discovery — source 12–16 unique articles across diverse domains (no duplicate domains). Group findings into strategic insight areas. Do not output raw URLs in Phase 1 JSON (persist them separately).
Phase 2: Deep Dive & Validation — validate 6–8 citations including at least 1 academic journal, 1 analyst report, 1 industry media source, and 1 case study. Use fetch_url and CrossRef/OpenAlex to verify APA metadata. If APA metadata can\'t be verified, set apa_string: \'details_unavailable\'. No invented metadata.
Phase 3: Synthesis — produce the final Research Brief JSON with required sections. All observations must be grounded in citations with passage_snippets and confidence values. Prioritise 2023–2026 research. Label sponsored citations. Ensure style: grounded, pragmatic, enterprise-informed. Validate output against the framework_brief schema. If validation fails, retry up to 2 times.',
                'default_model' => 'gpt-4o-mini',
                'params_json' => wp_json_encode(array(
                    'temperature' => 0.2,
                    'max_tokens' => 3000,
                    'response_format' => array('type' => 'json_object'),
                )),
                'tool_whitelist' => wp_json_encode(array('web_search', 'fetch_url', 'summarize_pdf', 'citation_check', 'crossref_api', 'openalex_api')),
                'is_locked' => true,
            ),
        );

        foreach ($defaults as $preset) {
            // Check if preset already exists
            if (!$this->get_preset($preset['id'])) {
                $this->insert_preset($preset);
            }
        }
    }
}
