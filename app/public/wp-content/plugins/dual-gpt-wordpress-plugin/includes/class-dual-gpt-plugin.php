<?php
/**
 * Main plugin class for Dual-GPT WordPress Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Plugin {

    /**
     * Initialize the plugin
     */
    public function init() {
        // Hook into WordPress
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));

        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Initialize admin if in admin area
        if (is_admin()) {
            $admin = new Dual_GPT_Admin();
            $admin->init();

            // Add AJAX handlers
            add_action('wp_ajax_dual_gpt_test_api', array($this, 'ajax_test_api'));
            add_action('admin_notices', array($this, 'maybe_show_api_key_notice'));
        }

        // Include additional classes
        $this->include_classes();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('dual-gpt-wordpress-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Enqueue scripts for admin pages if needed
    }

    /**
     * Warn admins about API key configuration issues
     */
    public function maybe_show_api_key_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $connector = new Dual_GPT_OpenAI_Connector();
        $source = $connector->get_api_key_source();

        if (!$source) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('Dual-GPT: OpenAI API key is missing. Set OPENAI_API_KEY in the environment (recommended) or define DUAL_GPT_OPENAI_API_KEY.', 'dual-gpt-wordpress-plugin')
            );
            return;
        }

        if ($source === 'option') {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html__('Dual-GPT: API key is loaded from the WordPress database. For production, prefer an environment variable (OPENAI_API_KEY) or a wp-config constant to keep secrets out of the database.', 'dual-gpt-wordpress-plugin')
            );
        }
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'dual-gpt-sidebar',
            DUAL_GPT_PLUGIN_URL . 'assets/js/sidebar.js',
            array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch'),
            DUAL_GPT_PLUGIN_VERSION,
            true
        );

        wp_enqueue_style(
            'dual-gpt-sidebar',
            DUAL_GPT_PLUGIN_URL . 'assets/css/sidebar.css',
            array(),
            DUAL_GPT_PLUGIN_VERSION
        );

        // Localize script with data
        wp_localize_script('dual-gpt-sidebar', 'dualGptData', array(
            'nonce' => wp_create_nonce('wp_rest'),
            'restUrl' => rest_url('dual-gpt/v1/'),
        ));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Sessions endpoint
        register_rest_route('dual-gpt/v1', '/sessions', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_session'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // Jobs endpoint
        register_rest_route('dual-gpt/v1', '/jobs', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_job'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // Streaming endpoint
        register_rest_route('dual-gpt/v1', '/jobs/(?P<id>[a-zA-Z0-9\-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'stream_job'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // Presets endpoints
        register_rest_route('dual-gpt/v1', '/presets', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_presets'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_preset'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        // Individual preset endpoints
        register_rest_route('dual-gpt/v1', '/presets/(?P<id>[a-zA-Z0-9\-]+)', array(
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_preset'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_preset'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        // Audit endpoint
        register_rest_route('dual-gpt/v1', '/audit', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_audit_logs'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // Budgets endpoint
        register_rest_route('dual-gpt/v1', '/budgets', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_budgets'),
                'permission_callback' => array($this, 'check_permissions'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'update_budget'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        // Blocks import endpoint
        register_rest_route('dual-gpt/v1', '/blocks/import', array(
            'methods' => 'POST',
            'callback' => array($this, 'import_blocks'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // Framework Generator endpoints
        $fg_api = new Framework_Generator_API();
        $fg_api->register_routes();
    }

    /**
     * Check if job is a Framework Generator job
     */
    private function is_framework_generator_job($job) {
        return strpos($job['idempotency_key'] ?? '', 'phase') !== false ||
               ($job['preset_id'] === 'fg-framework-generator');
    }

    /**
     * Process Framework Generator job
     */
    private function process_framework_generator_job($job) {
        $workers = new Framework_Generator_Workers();

        if (strpos($job['idempotency_key'], 'phase1') === 0) {
            $workers->process_phase1($job['id']);
        } elseif (strpos($job['idempotency_key'], 'phase2') === 0) {
            $workers->process_phase2($job['id']);
        } elseif (strpos($job['idempotency_key'], 'phase3') === 0) {
            $workers->process_phase3($job['id']);
        } elseif (strpos($job['idempotency_key'], 'author-') === 0) {
            // Handle author pass-through (would delegate to author system)
            $db = new Dual_GPT_DB_Handler();
            $db->update_job_status($job['id'], 'completed');
        }
    }

    /**
     * Check admin permissions
     */
    public function check_admin_permissions() {
        return current_user_can('manage_options');
    }

    /**
     * Create a new session
     */
    public function create_session($request) {
        $db = new Dual_GPT_DB_Handler();

        $params = $request->get_params();
        $role = sanitize_text_field($params['role'] ?? 'research');
        $preset_id = !empty($params['preset_id']) ? sanitize_text_field($params['preset_id']) : null;
        $title = !empty($params['title']) ? sanitize_text_field($params['title']) : null;
        $post_id = !empty($params['post_id']) ? intval($params['post_id']) : null;
        $idempotency_key = !empty($params['idempotency_key']) ? sanitize_text_field($params['idempotency_key']) : null;
        if ($idempotency_key && strlen($idempotency_key) > 64) {
            $idempotency_key = substr($idempotency_key, 0, 64);
        }

        $user_id = get_current_user_id();
        if (!$this->check_rate_limit('session', $user_id, 10, 60)) {
            return new WP_Error(
                'rate_limited',
                'Too many session requests. Please wait a moment and try again.',
                array('status' => 429)
            );
        }

        $session_data = array(
            'role' => $role,
            'preset_id' => $preset_id,
            'title' => $title,
            'post_id' => $post_id,
            'idempotency_key' => $idempotency_key,
        );

        if ($idempotency_key) {
            $existing = $db->get_session_by_idempotency($idempotency_key);
            if ($existing) {
                return new WP_REST_Response(array(
                    'session_id' => $existing['id'],
                    'role' => $existing['role'],
                    'preset_id' => $existing['preset_id'],
                    'idempotent' => true,
                ), 200);
            }
        }

        $session_id = $db->insert_session($session_data);

        if (is_wp_error($session_id)) {
            return new WP_Error('session_creation_failed', $session_id->get_error_message(), array('status' => 500));
        }

        return new WP_REST_Response(array(
            'session_id' => $session_id,
            'role' => $role,
            'preset_id' => $preset_id,
        ), 201);
    }

    /**
     * Create a new job with enhanced validation
     */
    public function create_job($request) {
        $db = new Dual_GPT_DB_Handler();
        $openai = new Dual_GPT_OpenAI_Connector();

        $params = $request->get_params();

        // Enhanced input validation
        $session_id = sanitize_text_field($params['session_id'] ?? '');
        $prompt = sanitize_textarea_field($params['prompt'] ?? '');
        $model = sanitize_text_field($params['model'] ?? 'gpt-4');

        // Validate required fields
        if (empty($session_id)) {
            return new WP_Error('missing_session_id', 'Session ID is required', array('status' => 400));
        }

        if (empty($prompt)) {
            return new WP_Error('missing_prompt', 'Prompt is required', array('status' => 400));
        }

        $idempotency_key = !empty($params['idempotency_key']) ? sanitize_text_field($params['idempotency_key']) : null;
        if ($idempotency_key && strlen($idempotency_key) > 64) {
            $idempotency_key = substr($idempotency_key, 0, 64);
        }
        $user_id = get_current_user_id();

        if (!$this->check_rate_limit('job', $user_id, 15, 60)) {
            return new WP_Error(
                'rate_limited',
                'Too many job requests. Please slow down and retry shortly.',
                array('status' => 429)
            );
        }

        // Enhanced prompt validation
        if (strlen($prompt) > 10000) {
            return new WP_Error('prompt_too_long', 'Prompt exceeds maximum length of 10,000 characters', array('status' => 400));
        }

        if (strlen($prompt) < 10) {
            return new WP_Error('prompt_too_short', 'Prompt must be at least 10 characters long', array('status' => 400));
        }

        // Validate model
        $allowed_models = array('gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo');
        if (!in_array($model, $allowed_models)) {
            return new WP_Error('invalid_model', 'Invalid model specified. Allowed models: ' . implode(', ', $allowed_models), array('status' => 400));
        }

        // Validate session exists and is accessible
        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('invalid_session', 'Session not found', array('status' => 404));
        }

        // Check if user owns the session or has permission to access it
        if ($session['user_id'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        // Check budget with more detailed response
        $user_id = get_current_user_id();
        $budget = $db->check_user_budget($user_id);
        if ($budget['token_used'] >= $budget['token_limit']) {
            return new WP_Error('budget_exceeded',
                sprintf('Token budget exceeded. Current usage: %s/%s tokens. Reset date: %s',
                    number_format($budget['token_used']),
                    number_format($budget['token_limit']),
                    $budget['reset_at'] ? date('M j, Y', strtotime($budget['reset_at'])) : 'Never'
                ),
                array('status' => 429)
            );
        }

        // Validate API key with better error message
        if (!$openai->validate_api_key()) {
            return new WP_Error('invalid_api_key',
                'OpenAI API key is not configured or invalid. Please check your settings.',
                array('status' => 500)
            );
        }

        $job_data = array(
            'session_id' => $session_id,
            'model' => $model,
            'input_prompt' => $prompt,
            'idempotency_key' => $idempotency_key,
        );

        if ($idempotency_key) {
            $existing_job = $db->get_job_by_idempotency($session_id, $idempotency_key);
            if ($existing_job) {
                return new WP_REST_Response(array(
                    'job_id' => $existing_job['id'],
                    'status' => $existing_job['status'],
                    'message' => 'Existing job returned via idempotency key',
                    'idempotent' => true,
                ), 200);
            }
        }

        $job_id = $db->insert_job($job_data);

        if (is_wp_error($job_id)) {
            $this->log_error('Failed to create job in database', array(
                'session_id' => $session_id,
                'error' => $job_id->get_error_message()
            ));
            return new WP_Error('job_creation_failed',
                'Failed to create job: ' . $job_id->get_error_message(),
                array('status' => 500)
            );
        }

        // Start job processing asynchronously
        $this->process_job_async($job_id);

        return new WP_REST_Response(array(
            'job_id' => $job_id,
            'status' => 'queued',
            'message' => 'Job created and queued for processing',
            'estimated_tokens' => $this->estimate_token_usage($prompt, $model),
        ), 201);
    }

    /**
     * Stream job results
     */
    public function stream_job($request) {
        $job_id = $request->get_param('id');

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');

        // Disable output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Stream job updates
        $this->stream_job_updates($job_id);

        exit;
    }

    /**
     * Get presets
     */
    public function get_presets($request) {
        $db = new Dual_GPT_DB_Handler();

        $params = $request->get_params();
        $role = !empty($params['role']) ? sanitize_text_field($params['role']) : null;

        $presets = $db->get_presets($role);

        return new WP_REST_Response($presets, 200);
    }

    /**
     * Create preset
     */
    public function create_preset($request) {
        $db = new Dual_GPT_DB_Handler();

        $params = $request->get_params();

        // Validate required fields
        $required = array('name', 'role', 'system_prompt');
        foreach ($required as $field) {
            if (empty($params[$field])) {
                return new WP_Error('missing_field', "Required field: $field", array('status' => 400));
            }
        }

        $preset_data = array(
            'name' => sanitize_text_field($params['name']),
            'role' => sanitize_text_field($params['role']),
            'system_prompt' => sanitize_textarea_field($params['system_prompt']),
            'default_model' => sanitize_text_field($params['default_model'] ?? 'gpt-4'),
            'params_json' => !empty($params['params']) ? wp_json_encode($params['params']) : null,
            'tool_whitelist' => !empty($params['tool_whitelist']) ? wp_json_encode($params['tool_whitelist']) : null,
        );

        $preset_id = $db->insert_preset($preset_data);

        if (is_wp_error($preset_id)) {
            return $preset_id;
        }

        $preset_data['id'] = $preset_id;
        return new WP_REST_Response($preset_data, 201);
    }

    /**
     * Update preset
     */
    public function update_preset($request) {
        $db = new Dual_GPT_DB_Handler();
        $preset_id = $request->get_param('id');

        $params = $request->get_params();

        // Check if preset exists and is not locked
        $existing_preset = $db->get_preset($preset_id);
        if (!$existing_preset) {
            return new WP_Error('preset_not_found', 'Preset not found', array('status' => 404));
        }

        if ($existing_preset['is_locked']) {
            return new WP_Error('preset_locked', 'Cannot modify locked preset', array('status' => 403));
        }

        $update_data = array();

        if (isset($params['name'])) {
            $update_data['name'] = sanitize_text_field($params['name']);
        }
        if (isset($params['system_prompt'])) {
            $update_data['system_prompt'] = sanitize_textarea_field($params['system_prompt']);
        }
        if (isset($params['default_model'])) {
            $update_data['default_model'] = sanitize_text_field($params['default_model']);
        }
        if (isset($params['params'])) {
            $update_data['params_json'] = wp_json_encode($params['params']);
        }
        if (isset($params['tool_whitelist'])) {
            $update_data['tool_whitelist'] = wp_json_encode($params['tool_whitelist']);
        }

        $success = $db->update_preset($preset_id, $update_data);

        if (!$success) {
            return new WP_Error('update_failed', 'Failed to update preset', array('status' => 500));
        }

        $updated_preset = $db->get_preset($preset_id);
        return new WP_REST_Response($updated_preset, 200);
    }

    /**
     * Delete preset
     */
    public function delete_preset($request) {
        $db = new Dual_GPT_DB_Handler();
        $preset_id = $request->get_param('id');

        // Check if preset exists and is not locked
        $existing_preset = $db->get_preset($preset_id);
        if (!$existing_preset) {
            return new WP_Error('preset_not_found', 'Preset not found', array('status' => 404));
        }

        if ($existing_preset['is_locked']) {
            return new WP_Error('preset_locked', 'Cannot delete locked preset', array('status' => 403));
        }

        $success = $db->delete_preset($preset_id);

        if (!$success) {
            return new WP_Error('delete_failed', 'Failed to delete preset', array('status' => 500));
        }

        return new WP_REST_Response(array('message' => 'Preset deleted'), 200);
    }

    /**
     * Get audit logs
     */
    public function get_audit_logs($request) {
        $db = new Dual_GPT_DB_Handler();

        $params = $request->get_params();
        $job_id = !empty($params['job_id']) ? sanitize_text_field($params['job_id']) : null;
        $limit = intval($params['limit'] ?? 50);

        // Would implement audit log retrieval
        // For now, return empty array
        return new WP_REST_Response(array(
            'logs' => array(),
            'total' => 0,
        ), 200);
    }

    /**
     * Get budgets
     */
    public function get_budgets($request) {
        $db = new Dual_GPT_DB_Handler();

        $user_id = get_current_user_id();
        $budget = $db->check_user_budget($user_id);

        return new WP_REST_Response(array(
            'budget' => $budget,
        ), 200);
    }

    /**
     * Update budget
     */
    public function update_budget($request) {
        $db = new Dual_GPT_DB_Handler();

        $params = $request->get_params();

        // Validate required fields
        if (!isset($params['scope']) || !isset($params['scope_id']) || !isset($params['token_limit'])) {
            return new WP_Error('missing_fields', 'Required fields: scope, scope_id, token_limit', array('status' => 400));
        }

        $scope = sanitize_text_field($params['scope']);
        $scope_id = sanitize_text_field($params['scope_id']);
        $token_limit = intval($params['token_limit']);

        // For now, we'll update or create a budget record
        // In a real implementation, you'd want more sophisticated budget management
        global $wpdb;
        $table = $wpdb->prefix . 'ai_budgets';

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE scope = %s AND scope_id = %s",
                $scope,
                $scope_id
            )
        );

        if ($existing) {
            $wpdb->update(
                $table,
                array(
                    'token_limit' => $token_limit,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $existing->id)
            );
        } else {
            $wpdb->insert($table, array(
                'scope' => $scope,
                'scope_id' => $scope_id,
                'period' => 'monthly',
                'token_limit' => $token_limit,
                'token_used' => 0,
                'reset_at' => date('Y-m-d H:i:s', strtotime('+1 month')),
            ));
        }

        return new WP_REST_Response(array(
            'message' => 'Budget updated',
            'scope' => $scope,
            'scope_id' => $scope_id,
            'token_limit' => $token_limit,
        ), 200);
    }

    /**
     * Import blocks
     */
    public function import_blocks($request) {
        $params = $request->get_params();
        $blocks_json = $params['blocks_json'] ?? '';
        $post_id = intval($params['post_id'] ?? 0);

        if (empty($blocks_json) || !$post_id) {
            return new WP_Error('missing_data', 'Blocks JSON and post ID required', array('status' => 400));
        }

        $blocks_data = json_decode($blocks_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'Invalid blocks JSON', array('status' => 400));
        }

        // Validate and convert blocks
        $gutenberg_blocks = $this->convert_blocks_json_to_gutenberg($blocks_data);

        if (is_wp_error($gutenberg_blocks)) {
            return $gutenberg_blocks;
        }

        // Insert blocks into post
        $this->insert_blocks_into_post($post_id, $gutenberg_blocks);

        return new WP_REST_Response(array(
            'message' => 'Blocks imported successfully',
            'blocks_count' => count($gutenberg_blocks),
        ), 200);
    }

    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_api() {
        check_ajax_referer('dual_gpt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $openai = new Dual_GPT_OpenAI_Connector();

        if (!$openai->validate_api_key()) {
            wp_send_json_error(array('message' => 'Invalid or missing API key'));
        }

        wp_send_json_success(array('message' => 'API key is valid'));
    }

    /**
     * Process job asynchronously
     */
    private function process_job_async($job_id) {
        // In a real implementation, this would use WordPress cron or a queue system
        // For now, we'll process synchronously for simplicity
        $this->process_job($job_id);
    }

    /**
     * Process a job with enhanced error handling and recovery
     */
    private function process_job($job_id) {
        $db = new Dual_GPT_DB_Handler();

        $job = $db->get_job($job_id);
        if (!$job) {
            $this->log_error('Job not found', array('job_id' => $job_id));
            return;
        }

        // Special handling for Framework Generator jobs
        if ($this->is_framework_generator_job($job)) {
            $this->process_framework_generator_job($job);
            return;
        }

        $openai = new Dual_GPT_OpenAI_Connector();

        $db->update_job_status($job_id, 'running');

        $retry_count = 0;
        $max_retries = 3;
        $last_error = null;

        while ($retry_count <= $max_retries) {
            try {
                // Get session and preset info
                $session = $db->get_session($job['session_id']);
                if (!$session) {
                    throw new Exception('Session not found for job ' . $job_id);
                }

                $preset = $session ? $db->get_preset($session['preset_id']) : null;

                // Validate preset if specified
                if ($session['preset_id'] && !$preset) {
                    throw new Exception('Preset not found: ' . $session['preset_id']);
                }

                // Prepare messages
                $messages = array(
                    array(
                        'role' => 'system',
                        'content' => $preset ? $preset['system_prompt'] : 'You are a helpful assistant.',
                    ),
                    array(
                        'role' => 'user',
                        'content' => $job['input_prompt'],
                    ),
                );

                // Prepare tools based on role
                $tools = array();
                if ($session && $session['role'] === 'research') {
                    $research_tools = new Dual_GPT_Research_Tools();
                    $tools = $research_tools->get_tool_definitions();
                } elseif ($session && $session['role'] === 'author') {
                    $author_tools = new Dual_GPT_Author_Tools();
                    $tools = $author_tools->get_tool_definitions();
                }

                // Make OpenAI call with timeout handling
                $response = $openai->create_chat_completion($messages, $job['model'], $tools);

                if (is_wp_error($response)) {
                    $error_code = $response->get_error_code();
                    $error_message = $response->get_error_message();

                    // Check if this is a retryable error
                    if ($this->is_retryable_error($error_code) && $retry_count < $max_retries) {
                        $retry_count++;
                        $this->log_warning('Retryable error, attempt ' . $retry_count . '/' . $max_retries, array(
                            'job_id' => $job_id,
                            'error' => $error_message,
                            'retry_count' => $retry_count
                        ));
                        sleep(pow(2, $retry_count)); // Exponential backoff
                        continue;
                    }

                    throw new Exception('OpenAI API error: ' . $error_message);
                }

                if (!isset($response['choices']) || empty($response['choices'])) {
                    throw new Exception('Invalid response structure from OpenAI API');
                }

                // Process tool calls if any
                $tool_calls = $response['choices'][0]['message']['tool_calls'] ?? array();
                $final_response = $response;

                if (!empty($tool_calls)) {
                    // Execute tools and continue conversation
                    $final_response = $this->process_tool_calls($tool_calls, $messages, $job, $session);
                }

                // Extract response content
                $response_content = $final_response['choices'][0]['message']['content'] ?? '';

                // Validate response content
                if (empty($response_content) && empty($tool_calls)) {
                    throw new Exception('Empty response from AI model');
                }

                // Calculate costs
                $prompt_tokens = $final_response['usage']['prompt_tokens'] ?? 0;
                $completion_tokens = $final_response['usage']['completion_tokens'] ?? 0;
                $cost_data = $openai->calculate_cost($job['model'], $prompt_tokens, $completion_tokens);

                // Update job with results
                $update_data = array(
                    'response_json' => wp_json_encode($final_response),
                    'usage_prompt_tokens' => $prompt_tokens,
                    'usage_completion_tokens' => $completion_tokens,
                    'cost_micro' => $cost_data['cost_micro'],
                );

                // Try to parse as Blocks JSON
                $blocks_json = $this->extract_blocks_json($response_content);
                if ($blocks_json) {
                    $update_data['output_blocks_json'] = wp_json_encode($blocks_json);
                }

                $db->update_job_status($job_id, 'completed', $update_data);

                // Update user token usage
                $usage_user_id = !empty($job['created_by']) ? intval($job['created_by']) : get_current_user_id();
                $db->update_token_usage($usage_user_id, $prompt_tokens + $completion_tokens);

                // Audit logging
                $db->insert_audit_log($job_id, 'finish', array(
                    'status' => 'completed',
                    'tokens_used' => $prompt_tokens + $completion_tokens,
                    'cost' => $cost_data['cost_usd'],
                    'retries' => $retry_count,
                ));

                // Success - break out of retry loop
                break;

            } catch (Exception $e) {
                $last_error = $e;
                $retry_count++;

                // Log the error with context
                $this->log_error('Job processing error', array(
                    'job_id' => $job_id,
                    'error' => $e->getMessage(),
                    'retry_count' => $retry_count,
                    'max_retries' => $max_retries,
                    'trace' => $e->getTraceAsString()
                ));

                // If we've exhausted retries or it's not a retryable error, fail the job
                if ($retry_count > $max_retries || !$this->is_retryable_error($e->getMessage())) {
                    $db->update_job_status($job_id, 'failed', array(
                        'error_message' => $e->getMessage(),
                        'retry_count' => $retry_count,
                    ));

                    $db->insert_audit_log($job_id, 'error', array(
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'retries_attempted' => $retry_count,
                    ));

                    // Send notification if configured
                    $this->send_error_notification($job_id, $e->getMessage());
                    break;
                }

                // Wait before retrying
                sleep(min(pow(2, $retry_count), 30)); // Exponential backoff, max 30 seconds
            }
        }
    }

    /**
     * Process tool calls
     */
    private function process_tool_calls($tool_calls, &$messages, $job, $session) {
        $openai = new Dual_GPT_OpenAI_Connector();
        $db = new Dual_GPT_DB_Handler();

        // Add assistant's tool call message
        $messages[] = array(
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => $tool_calls,
        );

        // Execute each tool call
        $tool_results = array();
        foreach ($tool_calls as $tool_call) {
            $function_name = $tool_call['function']['name'];
            $function_args = json_decode($tool_call['function']['arguments'], true);

            // Audit tool call
            $db->insert_audit_log($job['id'], 'tool_call', array(
                'tool' => $function_name,
                'args' => $function_args,
            ));

            // Execute tool
            $result = $this->execute_tool($function_name, $function_args, $session);

            $tool_results[] = array(
                'tool_call_id' => $tool_call['id'],
                'content' => wp_json_encode($result),
            );

            // Add tool result message
            $messages[] = array(
                'role' => 'tool',
                'tool_call_id' => $tool_call['id'],
                'content' => wp_json_encode($result),
            );
        }

        // Make final call with tool results
        return $openai->create_chat_completion($messages, $job['model']);
    }

    /**
     * Execute a tool
     */
    private function execute_tool($tool_name, $args, $session) {
        if ($session['role'] === 'research') {
            $tools = new Dual_GPT_Research_Tools();
        } elseif ($session['role'] === 'author') {
            $tools = new Dual_GPT_Author_Tools();
        } else {
            return array('error' => 'Unknown role');
        }

        return $tools->execute_tool($tool_name, $args);
    }

    /**
     * Extract Blocks JSON from response
     */
    private function extract_blocks_json($content) {
        // Try to parse as JSON first
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['blocks'])) {
            return $data;
        }

        // Look for JSON code blocks
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $data = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['blocks'])) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Stream job updates
     */
    private function stream_job_updates($job_id) {
        $db = new Dual_GPT_DB_Handler();

        // Send initial connection
        $this->send_sse_event('connected', array('job_id' => $job_id));

        $last_status = '';
        $timeout = 30; // 30 seconds timeout
        $start_time = time();

        while (time() - $start_time < $timeout) {
            $job = $db->get_job($job_id);

            if (!$job) {
                $this->send_sse_event('error', array('message' => 'Job not found'));
                break;
            }

            if ($job['status'] !== $last_status) {
                $this->send_sse_event('status', array(
                    'status' => $job['status'],
                    'job' => $job,
                ));
                $last_status = $job['status'];
            }

            if (in_array($job['status'], array('completed', 'failed'))) {
                break;
            }

            // Sleep for a bit before checking again
            sleep(1);
        }

        $this->send_sse_event('end', array());
    }

    /**
     * Send SSE event
     */
    private function send_sse_event($event, $data) {
        echo "event: $event\n";
        echo "data: " . wp_json_encode($data) . "\n\n";
        flush();
    }

    /**
     * Convert Blocks JSON to Gutenberg blocks
     */
    private function convert_blocks_json_to_gutenberg($blocks_data) {
        if (!isset($blocks_data['blocks']) || !is_array($blocks_data['blocks'])) {
            return new WP_Error('invalid_blocks', 'Invalid blocks structure', array('status' => 400));
        }

        $gutenberg_blocks = array();

        foreach ($blocks_data['blocks'] as $block_data) {
            $block_type = $block_data['type'] ?? 'paragraph';
            $content = $block_data['content'] ?? '';

            switch ($block_type) {
                case 'heading':
                    $level = $block_data['level'] ?? 2;
                    $gutenberg_blocks[] = array(
                        'blockName' => 'core/heading',
                        'attrs' => array(
                            'level' => $level,
                        ),
                        'innerBlocks' => array(),
                        'innerHTML' => "<h$level>$content</h$level>",
                        'innerContent' => array("<h$level>$content</h$level>"),
                    );
                    break;

                case 'paragraph':
                    $gutenberg_blocks[] = array(
                        'blockName' => 'core/paragraph',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => "<p>$content</p>",
                        'innerContent' => array("<p>$content</p>"),
                    );
                    break;

                case 'list':
                    $ordered = $block_data['ordered'] ?? false;
                    $items = $block_data['items'] ?? array();
                    $list_type = $ordered ? 'ol' : 'ul';
                    $list_items = '';
                    foreach ($items as $item) {
                        $list_items .= "<li>$item</li>";
                    }
                    $gutenberg_blocks[] = array(
                        'blockName' => 'core/list',
                        'attrs' => array(
                            'ordered' => $ordered,
                        ),
                        'innerBlocks' => array(),
                        'innerHTML' => "<$list_type>$list_items</$list_type>",
                        'innerContent' => array("<$list_type>$list_items</$list_type>"),
                    );
                    break;

                case 'quote':
                    $cite = $block_data['cite'] ?? '';
                    $gutenberg_blocks[] = array(
                        'blockName' => 'core/quote',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => "<blockquote class=\"wp-block-quote\"><p>$content</p><cite>$cite</cite></blockquote>",
                        'innerContent' => array(
                            "<blockquote class=\"wp-block-quote\">",
                            "<p>$content</p>",
                            "<cite>$cite</cite>",
                            "</blockquote>"
                        ),
                    );
                    break;

                case 'separator':
                    $gutenberg_blocks[] = array(
                        'blockName' => 'core/separator',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => '<hr class="wp-block-separator has-alpha-channel-opacity"/>',
                        'innerContent' => array('<hr class="wp-block-separator has-alpha-channel-opacity"/>'),
                    );
                    break;

                default:
                    // Default to paragraph for unknown types
                    $gutenberg_blocks[] = array(
                        'blockName' => 'core/paragraph',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => "<p>$content</p>",
                        'innerContent' => array("<p>$content</p>"),
                    );
                    break;
            }
        }

        return $gutenberg_blocks;
    }

    /**
     * Insert blocks into post
     */
    private function insert_blocks_into_post($post_id, $gutenberg_blocks) {
        // Convert to Gutenberg content
        $content = '';
        foreach ($gutenberg_blocks as $block) {
            $content .= serialize_block($block) . "\n";
        }

        // Update post content
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $content,
        ));
    }

    /**
     * Enhanced error logging with context
     */
    private function log_error($message, $context = array()) {
        $log_message = '[Dual-GPT Error] ' . $message;
        $sanitized_context = $this->sanitize_context($context);
        if (!empty($sanitized_context)) {
            $log_message .= ' | Context: ' . wp_json_encode($sanitized_context);
        }
        error_log($log_message);

        // Also log to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($log_message);
        }
    }

    /**
     * Enhanced warning logging with context
     */
    private function log_warning($message, $context = array()) {
        $log_message = '[Dual-GPT Warning] ' . $message;
        $sanitized_context = $this->sanitize_context($context);
        if (!empty($sanitized_context)) {
            $log_message .= ' | Context: ' . wp_json_encode($sanitized_context);
        }
        error_log($log_message);
    }

    /**
     * Redact/trim sensitive values before logging
     */
    private function sanitize_context($context) {
        if (empty($context)) {
            return array();
        }

        $redacted_keys = array('input_prompt', 'response_json', 'output_blocks_json', 'tool_calls', 'trace', 'args');
        $sanitized = array();

        foreach ($context as $key => $value) {
            if (in_array($key, $redacted_keys, true)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_string($value) && strlen($value) > 300) {
                $sanitized[$key] = substr($value, 0, 300) . '...';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if an error is retryable
     */
    private function is_retryable_error($error) {
        $retryable_patterns = array(
            'timeout',
            'rate limit',
            '429',
            '502',
            '503',
            '504',
            'connection',
            'network',
            'temporary',
        );

        $error_lower = strtolower($error);
        foreach ($retryable_patterns as $pattern) {
            if (strpos($error_lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send error notification (placeholder for future implementation)
     */
    private function send_error_notification($job_id, $error_message) {
        // Placeholder for email/Slack notifications
        // In a real implementation, this could send notifications to admins
        $this->log_warning('Error notification triggered', array(
            'job_id' => $job_id,
            'error' => $error_message,
        ));

        // TODO: Implement actual notification system
        // - Email notifications to admins
        // - Slack/Discord webhooks
        // - WordPress admin notices
    }

    /**
     * Estimate token usage for a prompt and model
     */
    private function estimate_token_usage($prompt, $model) {
        // Rough estimation: ~4 characters per token for English text
        $char_count = strlen($prompt);
        $estimated_tokens = ceil($char_count / 4);

        // Add some overhead for system messages and response
        $estimated_tokens += 100;

        // Model-specific adjustments
        switch ($model) {
            case 'gpt-4':
                $estimated_tokens = ceil($estimated_tokens * 1.1); // GPT-4 uses more tokens
                break;
            case 'gpt-4-turbo':
                $estimated_tokens = ceil($estimated_tokens * 0.9); // More efficient
                break;
            case 'gpt-3.5-turbo':
                $estimated_tokens = ceil($estimated_tokens * 0.8); // Most efficient
                break;
        }

        return $estimated_tokens;
    }

    /**
     * Include additional classes
     */
    private function include_classes() {
        // Include database handler
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-db-handler.php';

        // Include OpenAI connector
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-openai-connector.php';

        // Include Framework Generator API
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-framework-generator-api.php';

        // Include Framework Generator Workers
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-framework-generator-workers.php';

        // Include Framework Generator Citation Verifier
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-framework-generator-citation-verifier.php';

        // Include Framework Generator Exporter
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-framework-generator-exporter.php';

        // Include Framework Brief Validator
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/class-framework-brief-validator.php';

        // Include tool classes
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/tools/class-research-tools.php';
        require_once DUAL_GPT_PLUGIN_DIR . 'includes/tools/class-author-tools.php';

        // Include admin class
        if (is_admin()) {
            require_once DUAL_GPT_PLUGIN_DIR . 'admin/class-dual-gpt-admin.php';
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();

        // Create default presets
        $db = new Dual_GPT_DB_Handler();
        $db->create_default_presets();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup if needed
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // AI Sessions table
        $table_sessions = $wpdb->prefix . 'ai_sessions';
        $sql_sessions = "CREATE TABLE $table_sessions (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            post_id BIGINT(20) UNSIGNED NULL,
            role ENUM('research', 'author') NOT NULL,
            preset_id VARCHAR(36) NULL,
            title VARCHAR(255) NULL,
            system_prompt TEXT NULL,
            preset_version VARCHAR(20) NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            idempotency_key VARCHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_post_id (post_id),
            INDEX idx_role (role),
            INDEX idx_created_by (created_by),
            UNIQUE KEY idx_session_idempotency (idempotency_key)
        ) $charset_collate;";

        // AI Jobs table
        $table_jobs = $wpdb->prefix . 'ai_jobs';
        $sql_jobs = "CREATE TABLE $table_jobs (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            session_id VARCHAR(36) NOT NULL,
            model VARCHAR(50) NOT NULL,
            status ENUM('queued', 'running', 'completed', 'failed') DEFAULT 'queued',
            input_prompt TEXT NULL,
            response_json LONGTEXT NULL,
            output_blocks_json LONGTEXT NULL,
            compliance_json LONGTEXT NULL,
            schema_version VARCHAR(10) DEFAULT '1',
            usage_prompt_tokens INT DEFAULT 0,
            usage_completion_tokens INT DEFAULT 0,
            cost_micro INT DEFAULT 0,
            preset_version VARCHAR(20) NULL,
            tool_chain TEXT NULL,
            hard_cap_hit BOOLEAN DEFAULT FALSE,
            error_code VARCHAR(50) NULL,
            error_message TEXT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            idempotency_key VARCHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            INDEX idx_session_id (session_id),
            INDEX idx_status (status),
            UNIQUE KEY idx_job_idempotency (session_id, idempotency_key),
            FOREIGN KEY (session_id) REFERENCES $table_sessions(id) ON DELETE CASCADE
        ) $charset_collate;";

        // AI Presets table
        $table_presets = $wpdb->prefix . 'ai_presets';
        $sql_presets = "CREATE TABLE $table_presets (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            role ENUM('research', 'author', 'both') NOT NULL,
            system_prompt TEXT NULL,
            default_model VARCHAR(50) NULL,
            params_json LONGTEXT NULL,
            tool_whitelist TEXT NULL,
            preset_version VARCHAR(20) DEFAULT '1.0.0',
            is_locked BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_role (role)
        ) $charset_collate;";

        // AI Audit table
        $table_audit = $wpdb->prefix . 'ai_audit';
        $sql_audit = "CREATE TABLE $table_audit (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            job_id VARCHAR(36) NOT NULL,
            event_type ENUM('queued', 'tool_call', 'delta', 'finish', 'error') NOT NULL,
            payload_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_job_id (job_id),
            INDEX idx_event_type (event_type),
            FOREIGN KEY (job_id) REFERENCES $table_jobs(id) ON DELETE CASCADE
        ) $charset_collate;";

        // AI Budgets table
        $table_budgets = $wpdb->prefix . 'ai_budgets';
        $sql_budgets = "CREATE TABLE $table_budgets (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            scope ENUM('site', 'role', 'user') NOT NULL,
            scope_id VARCHAR(100) NULL,
            period ENUM('monthly') NOT NULL,
            token_limit INT NOT NULL,
            token_used INT DEFAULT 0,
            reset_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_scope (scope, scope_id),
            INDEX idx_reset_at (reset_at)
        ) $charset_collate;";

        // Framework Generator Validated Citations table
        $table_fg_citations = $wpdb->prefix . 'fg_validated_citations';
        $sql_fg_citations = "CREATE TABLE $table_fg_citations (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            session_id VARCHAR(36) NOT NULL,
            job_id VARCHAR(36) NULL,
            title TEXT,
            lead_author VARCHAR(255),
            publication VARCHAR(255),
            organisation VARCHAR(255),
            year SMALLINT,
            url TEXT,
            apa_string TEXT,
            apa_details_available TINYINT(1) DEFAULT 1,
            passage_snippet TEXT,
            type VARCHAR(50),
            tier VARCHAR(10),
            authority_score FLOAT DEFAULT 0.0,
            confidence FLOAT DEFAULT 0.5,
            sponsored TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            INDEX idx_org (organisation),
            INDEX idx_type (type)
        ) $charset_collate;";

        // Framework Generator Briefs table
        $table_fg_briefs = $wpdb->prefix . 'fg_briefs';
        $sql_fg_briefs = "CREATE TABLE $table_fg_briefs (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            session_id VARCHAR(36) NOT NULL,
            article_idea JSON,
            title VARCHAR(1000),
            overview TEXT,
            context TEXT,
            application JSON,
            observations JSON,
            key_themes JSON,
            citations JSON,
            writer_guidance JSON,
            metadata JSON,
            produced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            scoring JSON,
            INDEX idx_session_id (session_id)
        ) $charset_collate;";

        // Framework Generator Exports table
        $table_fg_exports = $wpdb->prefix . 'fg_exports';
        $sql_fg_exports = "CREATE TABLE $table_fg_exports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fg_brief_id VARCHAR(36),
            format VARCHAR(20),
            file_url TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";

        // Framework Generator Raw Articles table (staging)
        $table_fg_raw = $wpdb->prefix . 'fg_raw_articles';
        $sql_fg_raw = "CREATE TABLE $table_fg_raw (
            id VARCHAR(36) PRIMARY KEY,
            session_id VARCHAR(36) NOT NULL,
            title TEXT,
            url TEXT,
            domain VARCHAR(255),
            author VARCHAR(255),
            date DATE,
            source_type VARCHAR(50),
            snippet TEXT,
            extracted_claims JSON,
            keywords JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX(session_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sessions);
        dbDelta($sql_jobs);
        dbDelta($sql_presets);
        dbDelta($sql_audit);
        dbDelta($sql_budgets);
        dbDelta($sql_fg_citations);
        dbDelta($sql_fg_briefs);
        dbDelta($sql_fg_exports);
        dbDelta($sql_fg_raw);
    }

    /**
     * Simple per-user rate limiting to reduce abuse
     */
    private function check_rate_limit($type, $user_id, $limit, $window_seconds) {
        $key = "dual_gpt_rate_{$type}_{$user_id}";
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
}
