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
            return new WP_Error('db_insert_error', 'Failed to insert session');
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
            return new WP_Error('db_insert_error', 'Failed to insert job');
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
            'updated_at' => current_time('mysql'),
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
            return array('token_limit' => 100000, 'token_used' => 0); // Default limits
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
            'token_limit' => 100000, // Default limit
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
                'id' => 'fg-framework-generator',
                'name' => 'Framework Generator',
                'role' => 'research',
                'system_prompt' => 'You are a Framework Generator specialized in creating evidence-based research briefs. You excel at discovering relevant articles, validating citations, and synthesizing comprehensive research frameworks with proper attribution.',
                'default_model' => 'gpt-4o-mini',
                'params_json' => wp_json_encode(array(
                    'temperature' => 0.4,
                    'max_tokens' => 3000,
                )),
                'tool_whitelist' => wp_json_encode(array()),
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
