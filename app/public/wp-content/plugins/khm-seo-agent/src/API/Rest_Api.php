<?php

namespace KHM_SEO_AGENT\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rest_Api {
    public function register_routes() {
        register_rest_route( 'khm-seo-agent/v1', '/audit', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_audit' ),
            'permission_callback' => array( $this, 'can_edit_posts' ),
            'args' => array(
                'post_id' => array(
                    'type' => 'integer',
                    'required' => true,
                ),
                'keyword' => array(
                    'type' => 'string',
                    'required' => false,
                ),
            ),
        ) );

        register_rest_route( 'khm-seo-agent/v1', '/audit/status', array(
            'methods' => 'GET',
            'callback' => array( $this, 'handle_audit_status' ),
            'permission_callback' => array( $this, 'can_edit_posts' ),
            'args' => array(
                'job_id' => array(
                    'type' => 'string',
                    'required' => true,
                ),
            ),
        ) );

        register_rest_route( 'khm-seo-agent/v1', '/preview', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_preview' ),
            'permission_callback' => array( $this, 'can_edit_posts' ),
            'args' => array(
                'post_id' => array(
                    'type' => 'integer',
                    'required' => true,
                ),
                'actions' => array(
                    'type' => 'array',
                    'required' => true,
                ),
            ),
        ) );

        register_rest_route( 'khm-seo-agent/v1', '/apply', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_apply' ),
            'permission_callback' => array( $this, 'can_edit_posts' ),
            'args' => array(
                'post_id' => array(
                    'type' => 'integer',
                    'required' => true,
                ),
                'actions' => array(
                    'type' => 'array',
                    'required' => true,
                ),
                'job_id' => array(
                    'type' => 'string',
                    'required' => true,
                ),
                'idempotency_key' => array(
                    'type' => 'string',
                    'required' => true,
                ),
                'confirm_schema_changes' => array(
                    'type' => 'boolean',
                    'required' => false,
                ),
            ),
        ) );

        register_rest_route( 'khm-seo-agent/v1', '/keywords', array(
            'methods' => 'GET',
            'callback' => array( $this, 'handle_keywords' ),
            'permission_callback' => array( $this, 'can_edit_posts' ),
            'args' => array(
                'post_id' => array(
                    'type' => 'integer',
                    'required' => true,
                ),
            ),
        ) );
    }

    public function can_edit_posts() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return false;
        }

        // Verify REST API nonce to prevent CSRF
        $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return false;
        }

        return true;
    }

    public function handle_audit( $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        $keyword = sanitize_text_field( $request->get_param( 'keyword' ) ?? '' );

        if ( empty( $post_id ) ) {
            return new \WP_Error( 'missing_post_id', 'Post ID is required.', array( 'status' => 400 ) );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return new \WP_Error( 'post_not_found', 'Post not found.', array( 'status' => 404 ) );
        }

        if ( ! function_exists( 'khm_seo' ) || ! khm_seo() ) {
            return new \WP_Error( 'khm_seo_missing', 'KHM SEO is not available.', array( 'status' => 500 ) );
        }

        $analysis_engine = khm_seo()->get_analysis_engine();
        if ( ! $analysis_engine ) {
            return new \WP_Error( 'analysis_unavailable', 'KHM SEO analysis engine is not available.', array( 'status' => 500 ) );
        }

        $focus_keyword = get_post_meta( $post_id, '_khm_seo_focus_keyword', true );
        if ( empty( $keyword ) && ! empty( $focus_keyword ) ) {
            $keyword = $focus_keyword;
        }

        $data = array(
            'post_id' => $post_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'meta_description' => get_post_meta( $post_id, '_khm_seo_description', true ),
            'focus_keyword' => $keyword,
        );

        $analysis = $analysis_engine->analyze( $data );
        $this->persist_seo_score( $post_id, $analysis );

        $audit_job_id = wp_generate_uuid4();

        if ( ! $this->is_openai_available() ) {
            return rest_ensure_response( array(
                'post_id' => $post_id,
                'analysis' => $analysis,
                'session_id' => null,
                'job_id' => $audit_job_id,
                'llm_output' => $this->get_fallback_payload( $post, $analysis, $keyword ),
                'status' => 'fallback',
                'error' => array(
                    'code' => 'openai_unavailable',
                    'message' => 'OpenAI API key not configured or invalid.',
                ),
            ) );
        }

        $prompt = $this->build_llm_prompt( $post, $analysis, $keyword );

        error_log('[KH SEO] Calling LLMService for post ' . $post_id);
        $route = \KH\Editorial\Core\LLMService::resolve_agent_model('seo_schema');

        $result = \KH\Editorial\Core\LLMService::post_completion([
            [
                'role'    => 'system',
                'content' => 'You are an SEO strategist. Analyze the article and SEO analysis data. Return valid JSON following the exact schema provided.'
            ],
            [
                'role'    => 'user',
                'content' => $prompt,
            ],
        ], [
            'provider'    => $route['provider'],
            'model'       => $route['model'],
            'temperature' => 0.2,
            'max_tokens'  => 3000,
            'response_format' => ['type' => 'json_object'],
        ]);

        if ( is_wp_error( $result ) ) {
            error_log('[KH SEO] LLMService call failed: ' . $result->get_error_message());
            return rest_ensure_response( array(
                'post_id' => $post_id,
                'analysis' => $analysis,
                'job_id' => $audit_job_id,
                'llm_output' => $this->get_fallback_payload( $post, $analysis, $keyword ),
                'status' => 'fallback',
                'error' => array(
                    'code' => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                ),
            ) );
        }

        $llm_payload = $this->parse_llm_output( $result['content'] );
        if ( is_wp_error( $llm_payload ) ) {
            return rest_ensure_response( array(
                'post_id' => $post_id,
                'analysis' => $analysis,
                'job_id' => $audit_job_id,
                'llm_output' => $this->get_fallback_payload( $post, $analysis, $keyword ),
                'status' => 'fallback',
                'error' => array(
                    'code' => $llm_payload->get_error_code(),
                    'message' => $llm_payload->get_error_message(),
                ),
            ) );
        }

        $validation = $this->validate_llm_output( $llm_payload );
        if ( is_wp_error( $validation ) ) {
            return rest_ensure_response( array(
                'post_id' => $post_id,
                'analysis' => $analysis,
                'job_id' => $audit_job_id,
                'llm_output' => $this->get_fallback_payload( $post, $analysis, $keyword ),
                'status' => 'fallback',
                'error' => array(
                    'code' => $validation->get_error_code(),
                    'message' => $validation->get_error_message(),
                ),
            ) );
        }

        $llm_payload = $this->enrich_llm_payload( $llm_payload, $post, $analysis, $keyword );

        error_log('[KH SEO] Audit completed for post ' . $post_id . ' — ' . count($llm_payload['issues'] ?? []) . ' issues, ' . count($llm_payload['apply_actions'] ?? []) . ' actions');

        return rest_ensure_response( array(
            'post_id' => $post_id,
            'analysis' => $analysis,
            'job_id' => $audit_job_id,
            'llm_output' => $llm_payload,
            'status' => 'completed',
        ) );
    }

    public function handle_keywords( $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        if ( ! $post_id ) {
            return new \WP_Error( 'missing_post_id', 'Post ID is required.', array( 'status' => 400 ) );
        }

        $focus = get_post_meta( $post_id, '_khm_seo_focus_keyword', true );
        $keywords = get_post_meta( $post_id, '_khm_seo_keywords', true );

        $list = array();
        if ( is_string( $keywords ) && '' !== trim( $keywords ) ) {
            $list = array_filter( array_map( 'trim', explode( ',', $keywords ) ) );
        } elseif ( is_array( $keywords ) ) {
            $list = array_filter( $keywords );
        }

        if ( $focus && ! in_array( $focus, $list, true ) ) {
            array_unshift( $list, $focus );
        }

        return rest_ensure_response( array(
            'keywords' => array_values( $list ),
            'intent_scores' => array(),
        ) );
    }

    private function get_fallback_payload( $post, $analysis, $keyword ) {
        return $this->build_deterministic_payload( $post, $analysis, $keyword );
    }

    private function is_openai_available() {
        return class_exists( '\KH\Editorial\Core\LLMService' )
            && \KH\Editorial\Core\LLMService::is_configured();
    }

    /**
     * Analyze the current post content with the KHM SEO analysis engine.
     *
     * @param int $post_id Post ID.
     * @param string $keyword Optional focus keyword override.
     * @return array|\WP_Error
     */
    private function analyze_post( $post_id, $keyword = '' ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new \WP_Error( 'post_not_found', 'Post not found.', array( 'status' => 404 ) );
        }

        if ( ! function_exists( 'khm_seo' ) || ! khm_seo() ) {
            return new \WP_Error( 'khm_seo_missing', 'KHM SEO is not available.', array( 'status' => 500 ) );
        }

        $analysis_engine = khm_seo()->get_analysis_engine();
        if ( ! $analysis_engine ) {
            return new \WP_Error( 'analysis_unavailable', 'KHM SEO analysis engine is not available.', array( 'status' => 500 ) );
        }

        $focus_keyword = get_post_meta( $post_id, '_khm_seo_focus_keyword', true );
        if ( empty( $keyword ) && ! empty( $focus_keyword ) ) {
            $keyword = $focus_keyword;
        }

        return $analysis_engine->analyze( array(
            'post_id' => $post_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'meta_description' => get_post_meta( $post_id, '_khm_seo_description', true ),
            'focus_keyword' => sanitize_text_field( $keyword ),
        ) );
    }

    /**
     * Persist the latest SEO score to post meta.
     *
     * @param int $post_id Post ID.
     * @param array $analysis Analysis payload.
     * @return int
     */
    private function persist_seo_score( $post_id, $analysis ) {
        $score = max( 0, min( 100, (int) ( $analysis['overall_score'] ?? 0 ) ) );
        update_post_meta( $post_id, '_khm_seo_score', $score );

        return $score;
    }

    public function handle_audit_status( $request ) {
        // Audit is now synchronous — status polling is no longer needed.
        // The handle_audit() endpoint returns results immediately.
        return rest_ensure_response( array(
            'status' => 'sync_only',
            'message' => 'Audit is now synchronous. Use POST /audit to run audits and get results immediately.',
        ) );
    }

    public function handle_preview( $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        $actions = $request->get_param( 'actions' );

        if ( empty( $post_id ) || empty( $actions ) ) {
            return new \WP_Error( 'invalid_preview', 'post_id and actions are required.', array( 'status' => 400 ) );
        }

        // Build a preview from current state
        $preview = $this->build_preview_from_actions( $post_id, $actions );
        return rest_ensure_response( $preview );
    }

    public function handle_apply( $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        $actions = $request->get_param( 'actions' );
        $job_id = sanitize_text_field( $request->get_param( 'job_id' ) );
        $idempotency_key = sanitize_text_field( $request->get_param( 'idempotency_key' ) );
        $confirm_schema_changes = $this->to_bool( $request->get_param( 'confirm_schema_changes' ) );
        $acting_user_id = get_current_user_id();

        if ( empty( $post_id ) || empty( $actions ) || empty( $job_id ) || empty( $idempotency_key ) ) {
            return new \WP_Error( 'invalid_apply', 'post_id, actions, job_id, and idempotency_key are required.', array( 'status' => 400 ) );
        }

        if ( $this->has_action_type( $actions, 'set_schema_config' ) && ! $confirm_schema_changes ) {
            return new \WP_Error(
                'schema_confirmation_required',
                'Schema configuration changes require confirm_schema_changes=true.',
                array( 'status' => 400 )
            );
        }

        // Apply actions directly via post meta
        $result = $this->apply_actions_direct( $post_id, $actions, $acting_user_id, $idempotency_key, $job_id, $confirm_schema_changes );

        if ( ! empty( $result['success'] ) ) {
            $existing_score = (int) get_post_meta( $post_id, '_khm_seo_score', true );
            $analysis = $this->analyze_post( $post_id );

            if ( ! is_wp_error( $analysis ) ) {
                $next_score = $this->persist_seo_score( $post_id, $analysis );
                $result['analysis'] = $analysis;

                if ( ! isset( $result['changes'] ) || ! is_array( $result['changes'] ) ) {
                    $result['changes'] = array();
                }

                $result['changes'][] = array(
                    'meta_key' => '_khm_seo_score',
                    'old' => $existing_score,
                    'new' => $next_score,
                );
            }
        }

        return rest_ensure_response( $result );
    }

    /**
     * Build a preview of what the actions would change, without writing.
     *
     * @param int $post_id Post ID.
     * @param array $actions Action list.
     * @return array Preview result.
     */
    private function build_preview_from_actions( $post_id, $actions ) {
        $changes = array();
        $post = get_post( $post_id );

        foreach ( $actions as $action ) {
            if ( ! is_array( $action ) || empty( $action['action_type'] ) || ! isset( $action['payload']['value'] ) ) {
                continue;
            }

            $action_type = sanitize_key( $action['action_type'] );
            $new_value = $action['payload']['value'];

            switch ( $action_type ) {
                case 'set_meta_title':
                    $old_value = get_post_meta( $post_id, '_khm_seo_title', true );
                    $changes[] = array(
                        'action_type' => $action_type,
                        'meta_key' => '_khm_seo_title',
                        'old_value' => $old_value,
                        'new_value' => sanitize_text_field( $new_value ),
                    );
                    break;

                case 'set_meta_description':
                    $old_value = get_post_meta( $post_id, '_khm_seo_description', true );
                    $changes[] = array(
                        'action_type' => $action_type,
                        'meta_key' => '_khm_seo_description',
                        'old_value' => $old_value,
                        'new_value' => sanitize_textarea_field( $new_value ),
                    );
                    break;

                case 'set_focus_keyword':
                    $old_value = get_post_meta( $post_id, '_khm_seo_focus_keyword', true );
                    $changes[] = array(
                        'action_type' => $action_type,
                        'meta_key' => '_khm_seo_focus_keyword',
                        'old_value' => $old_value,
                        'new_value' => sanitize_text_field( $new_value ),
                    );
                    break;

                case 'set_keywords':
                    $old_value = get_post_meta( $post_id, '_khm_seo_keywords', true );
                    $changes[] = array(
                        'action_type' => $action_type,
                        'meta_key' => '_khm_seo_keywords',
                        'old_value' => $old_value,
                        'new_value' => sanitize_text_field( $new_value ),
                    );
                    break;

                case 'set_schema_config':
                    $old_value = get_post_meta( $post_id, '_khm_seo_schema_config', true );
                    $changes[] = array(
                        'action_type' => $action_type,
                        'meta_key' => '_khm_seo_schema_config',
                        'old_value' => $old_value,
                        'new_value' => $new_value,
                    );
                    break;
            }
        }

        return array(
            'success' => true,
            'preview' => $changes,
            'message' => 'Preview generated successfully.',
        );
    }

    /**
     * Apply actions directly via post meta, without Dual-GPT.
     *
     * @param int $post_id Post ID.
     * @param array $actions Action list.
     * @param int $acting_user_id User ID.
     * @param string $idempotency_key Idempotency key.
     * @param string $job_id Job ID.
     * @param bool $confirm_schema_changes Whether to confirm schema changes.
     * @return array Apply result.
     */
    private function apply_actions_direct( $post_id, $actions, $acting_user_id, $idempotency_key, $job_id, $confirm_schema_changes ) {
        $applied = array();
        $post = get_post( $post_id );

        foreach ( $actions as $action ) {
            if ( ! is_array( $action ) || empty( $action['action_type'] ) || ! isset( $action['payload']['value'] ) ) {
                continue;
            }

            $action_type = sanitize_key( $action['action_type'] );
            $new_value = $action['payload']['value'];

            switch ( $action_type ) {
                case 'set_meta_title':
                    $old_value = get_post_meta( $post_id, '_khm_seo_title', true );
                    update_post_meta( $post_id, '_khm_seo_title', sanitize_text_field( $new_value ) );
                    $applied[] = array(
                        'action_type' => $action_type,
                        'meta_key' => '_khm_seo_title',
                        'old_value' => $old_value,
                        'new_value' => sanitize_text_field( $new_value ),
                    );
                    break;

                case 'set_meta_description':
                    $old_value = get_post_meta( $post_id, '_khm_seo_description', true );
                    update_post_meta( $post_id, '_khm_seo_description', sanitize_textarea_field( $new_value ) );
                    $applied[] = array(
                        'action_type' => $action_type,
                        'meta_key' => '_khm_seo_description',
                        'old_value' => $old_value,
                        'new_value' => sanitize_textarea_field( $new_value ),
                    );
                    break;

                case 'set_focus_keyword':
                    $old_value = get_post_meta( $post_id, '_khm_seo_focus_keyword', true );
                    update_post_meta( $post_id, '_khm_seo_focus_keyword', sanitize_text_field( $new_value ) );
                    $applied[] = array(
                        'action_type' => $action_type,
                        'meta_key' => '_khm_seo_focus_keyword',
                        'old_value' => $old_value,
                        'new_value' => sanitize_text_field( $new_value ),
                    );
                    break;

                case 'set_keywords':
                    $old_value = get_post_meta( $post_id, '_khm_seo_keywords', true );
                    update_post_meta( $post_id, '_khm_seo_keywords', sanitize_text_field( $new_value ) );
                    $applied[] = array(
                        'action_type' => $action_type,
                        'meta_key' => '_khm_seo_keywords',
                        'old_value' => $old_value,
                        'new_value' => sanitize_text_field( $new_value ),
                    );
                    break;

                case 'set_schema_config':
                    if ( ! $confirm_schema_changes ) {
                        $applied[] = array(
                            'action_type' => $action_type,
                            'meta_key' => '_khm_seo_schema_config',
                            'error' => 'Schema configuration changes require confirm_schema_changes=true.',
                        );
                        break;
                    }
                    $old_value = get_post_meta( $post_id, '_khm_seo_schema_config', true );
                    update_post_meta( $post_id, '_khm_seo_schema_config', $new_value );
                    $applied[] = array(
                        'action_type' => $action_type,
                        'meta_key' => '_khm_seo_schema_config',
                        'old_value' => $old_value,
                        'new_value' => $new_value,
                    );
                    break;
            }
        }

        return array(
            'success' => ! empty( $applied ),
            'changes' => $applied,
            'message' => ! empty( $applied ) ? 'Actions applied successfully via fallback.' : 'No actions were applied.',
        );
    }

    /**
     * Check if actions array contains a specific action type.
     *
     * @param array $actions Actions list.
     * @param string $target_type Action type to find.
     * @return bool
     */
    private function has_action_type( $actions, $target_type ) {
        if ( ! is_array( $actions ) ) {
            return false;
        }

        foreach ( $actions as $action ) {
            if ( ! is_array( $action ) ) {
                continue;
            }

            if ( sanitize_key( $action['action_type'] ?? '' ) === $target_type ) {
                return true;
            }
        }

        return false;
    }

    private function to_bool( $value ) {
        if ( is_bool( $value ) ) {
            return $value;
        }

        if ( is_numeric( $value ) ) {
            return intval( $value ) === 1;
        }

        if ( is_string( $value ) ) {
            $normalized = strtolower( trim( $value ) );
            return in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true );
        }

        return false;
    }

    // Legacy Dual_GPT methods removed — LLMService is now used directly.

    /**
     * Parse LLM response content into structured payload.
     * Handles both JSON-mode responses and raw JSON with markdown fences.
     */
    private function parse_llm_output( $content ) {
        if ( empty( $content ) ) {
            return new \WP_Error( 'empty_llm_content', 'LLM response is empty.', array( 'status' => 500 ) );
        }

        // Strip markdown code fences if present
        $content = preg_replace('/^```(?:json)?[\r\n]+|```[\r\n]*$/', '', trim($content));

        $decoded = json_decode( $content, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'llm_invalid_json', 'LLM returned invalid JSON: ' . json_last_error_msg(), array( 'status' => 422 ) );
        }

        return $decoded;
    }

    private function validate_llm_output( $payload ) {
        $required_top = array( 'summary', 'issues', 'suggestions', 'apply_actions', 'upstream_signals' );
        foreach ( $required_top as $key ) {
            if ( ! array_key_exists( $key, $payload ) ) {
                return new \WP_Error( 'llm_schema_missing', 'LLM output missing key: ' . $key, array( 'status' => 422 ) );
            }
        }

        if ( ! is_array( $payload['summary'] ) ) {
            return new \WP_Error( 'llm_schema_invalid', 'Summary must be an object.', array( 'status' => 422 ) );
        }

        $summary_fields = array( 'issues_total', 'issues_high', 'suggestions_total' );
        foreach ( $summary_fields as $field ) {
            if ( ! isset( $payload['summary'][ $field ] ) ) {
                return new \WP_Error( 'llm_schema_invalid', 'Summary missing field: ' . $field, array( 'status' => 422 ) );
            }
        }

        if ( ! is_array( $payload['issues'] ) || ! is_array( $payload['suggestions'] ) || ! is_array( $payload['apply_actions'] ) || ! is_array( $payload['upstream_signals'] ) ) {
            return new \WP_Error( 'llm_schema_invalid', 'issues, suggestions, apply_actions, and upstream_signals must be arrays.', array( 'status' => 422 ) );
        }

        return true;
    }

    private function enrich_llm_payload( $payload, $post, $analysis, $keyword ) {
        $baseline = $this->build_deterministic_payload( $post, $analysis, $keyword );

        foreach ( array( 'issues', 'suggestions', 'apply_actions', 'upstream_signals' ) as $key ) {
            if ( empty( $payload[ $key ] ) && ! empty( $baseline[ $key ] ) ) {
                $payload[ $key ] = $baseline[ $key ];
            }
        }

        if ( empty( $payload['summary'] ) || ! is_array( $payload['summary'] ) ) {
            $payload['summary'] = array();
        }

        $payload['summary']['issues_total'] = max(
            intval( $payload['summary']['issues_total'] ?? 0 ),
            count( $payload['issues'] ?? array() )
        );
        $payload['summary']['issues_high'] = max(
            intval( $payload['summary']['issues_high'] ?? 0 ),
            $this->count_high_priority_items( $payload['issues'] ?? array() )
        );
        $payload['summary']['suggestions_total'] = max(
            intval( $payload['summary']['suggestions_total'] ?? 0 ),
            count( $payload['suggestions'] ?? array() ),
            count( $payload['apply_actions'] ?? array() )
        );

        if ( empty( $payload['summary']['score'] ) && isset( $baseline['summary']['score'] ) ) {
            $payload['summary']['score'] = $baseline['summary']['score'];
        }

        return $payload;
    }

    private function build_deterministic_payload( $post, $analysis, $keyword ) {
        $issues = $this->map_analysis_items( $analysis['technical_issues'] ?? array(), 'issue' );
        $suggestions = $this->map_analysis_items( $analysis['suggestions'] ?? array(), 'suggestion' );
        $actions = $this->synthesize_apply_actions( $post, $analysis, $keyword );

        return array(
            'summary' => array(
                'issues_total' => count( $issues ),
                'issues_high' => $this->count_high_priority_items( $issues ),
                'suggestions_total' => max( count( $suggestions ), count( $actions ) ),
                'score' => intval( $analysis['overall_score'] ?? 0 ),
            ),
            'issues' => $issues,
            'suggestions' => $suggestions,
            'apply_actions' => $actions,
            'upstream_signals' => array(
                array(
                    'source' => 'khm_seo_analysis_engine',
                    'overall_score' => intval( $analysis['overall_score'] ?? 0 ),
                    'focus_keyword' => $this->resolve_focus_keyword( $post, $keyword ),
                ),
            ),
        );
    }

    private function get_job_context( $job ) {
        // Legacy method kept for backward compatibility.
        // Audit is now synchronous — context is returned directly in handle_audit().
        return null;
    }

    private function map_analysis_items( $items, $fallback_title ) {
        $mapped = array();

        if ( ! is_array( $items ) ) {
            return $mapped;
        }

        foreach ( array_slice( $items, 0, 6 ) as $item ) {
            if ( is_string( $item ) ) {
                $mapped[] = array(
                    'title' => ucfirst( $fallback_title ),
                    'message' => sanitize_text_field( $item ),
                    'priority' => 'medium',
                );
                continue;
            }

            if ( ! is_array( $item ) ) {
                continue;
            }

            $message = $item['message'] ?? $item['action'] ?? $item['suggestion'] ?? '';
            if ( '' === $message ) {
                continue;
            }

            $mapped[] = array(
                'title' => sanitize_text_field( $item['category'] ?? ucfirst( $fallback_title ) ),
                'message' => sanitize_text_field( $message ),
                'priority' => $this->normalize_priority( $item['priority'] ?? $item['impact'] ?? 'medium' ),
            );
        }

        return $mapped;
    }

    private function synthesize_apply_actions( $post, $analysis, $keyword ) {
        $actions = array();
        $resolved_keyword = $this->resolve_focus_keyword( $post, $keyword );
        $current_title = trim( (string) get_post_meta( $post->ID, '_khm_seo_title', true ) );
        $current_description = trim( (string) get_post_meta( $post->ID, '_khm_seo_description', true ) );
        $current_focus_keyword = trim( (string) get_post_meta( $post->ID, '_khm_seo_focus_keyword', true ) );
        $current_keywords = trim( (string) get_post_meta( $post->ID, '_khm_seo_keywords', true ) );
        $current_schema = get_post_meta( $post->ID, '_khm_seo_schema_config', true );

        $recommended_title = $this->build_recommended_meta_title( $post, $resolved_keyword );
        if ( '' !== $recommended_title && $recommended_title !== $current_title ) {
            $actions[] = array(
                'action_type' => 'set_meta_title',
                'payload' => array( 'value' => $recommended_title ),
            );
        }

        $recommended_description = $this->build_recommended_meta_description( $post, $resolved_keyword, $current_description );
        if ( '' !== $recommended_description && $recommended_description !== $current_description ) {
            $actions[] = array(
                'action_type' => 'set_meta_description',
                'payload' => array( 'value' => $recommended_description ),
            );
        }

        if ( '' !== $resolved_keyword && $resolved_keyword !== $current_focus_keyword ) {
            $actions[] = array(
                'action_type' => 'set_focus_keyword',
                'payload' => array( 'value' => $resolved_keyword ),
            );
        }

        $recommended_keywords = $this->build_recommended_keywords( $resolved_keyword, $current_keywords );
        if ( '' !== $recommended_keywords && $recommended_keywords !== $current_keywords ) {
            $actions[] = array(
                'action_type' => 'set_keywords',
                'payload' => array( 'value' => $recommended_keywords ),
            );
        }

        $recommended_schema = $this->build_recommended_schema_config( $post, $current_schema, $recommended_title, $recommended_description );
        if ( $this->schema_configs_differ( $current_schema, $recommended_schema ) ) {
            $actions[] = array(
                'action_type' => 'set_schema_config',
                'payload' => array( 'value' => $recommended_schema ),
            );
        }

        return array_slice( $actions, 0, 5 );
    }

    private function resolve_focus_keyword( $post, $keyword ) {
        $keyword = sanitize_text_field( $keyword );
        if ( '' !== $keyword ) {
            return $keyword;
        }

        $stored = sanitize_text_field( get_post_meta( $post->ID, '_khm_seo_focus_keyword', true ) );
        if ( '' !== $stored ) {
            return $stored;
        }

        $title = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $post->post_title ) ) );
        if ( '' === $title ) {
            return '';
        }

        $title_words = preg_split( '/\s+/', $title );
        $title_words = array_filter( $title_words, function( $word ) {
            return mb_strlen( $word ) > 2;
        } );

        return sanitize_text_field( implode( ' ', array_slice( $title_words, 0, 4 ) ) );
    }

    private function build_recommended_meta_title( $post, $keyword ) {
        $base_title = trim( (string) get_post_meta( $post->ID, '_khm_seo_title', true ) );
        if ( '' === $base_title ) {
            $base_title = trim( wp_strip_all_tags( $post->post_title ) );
        }

        $keyword = trim( $keyword );
        $candidate = $base_title;
        if ( '' !== $keyword && stripos( $candidate, $keyword ) === false ) {
            $candidate = $keyword . ' | ' . $base_title;
        }

        return $this->trim_to_length( $candidate, 60 );
    }

    private function build_recommended_meta_description( $post, $keyword, $current_description ) {
        $description = trim( (string) $current_description );
        if ( '' === $description ) {
            $description = trim( wp_strip_all_tags( $post->post_excerpt ) );
        }

        if ( '' === $description ) {
            $description = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $post->post_content ) ) );
        }

        if ( '' === $description ) {
            $description = trim( wp_strip_all_tags( $post->post_title ) );
        }

        $description = $this->trim_to_length( $description, 155 );
        if ( '' !== $keyword && stripos( $description, $keyword ) === false ) {
            $description = $this->trim_to_length( $keyword . ': ' . $description, 155 );
        }

        return $description;
    }

    private function build_recommended_keywords( $keyword, $current_keywords ) {
        $keywords = array();

        if ( '' !== trim( $keyword ) ) {
            $keywords[] = sanitize_text_field( $keyword );
        }

        if ( '' !== trim( (string) $current_keywords ) ) {
            foreach ( preg_split( '/[,;]+/', $current_keywords ) as $existing ) {
                $existing = sanitize_text_field( trim( $existing ) );
                if ( '' !== $existing ) {
                    $keywords[] = $existing;
                }
            }
        }

        $keywords = array_values( array_unique( $keywords ) );

        return implode( ', ', array_slice( $keywords, 0, 6 ) );
    }

    private function build_recommended_schema_config( $post, $current_schema, $headline, $description ) {
        $schema = is_array( $current_schema ) ? $current_schema : array();
        $schema['enabled'] = true;
        $schema['type'] = sanitize_key( $schema['type'] ?? $this->get_default_schema_type( $post ) );

        if ( ! isset( $schema['custom_fields'] ) || ! is_array( $schema['custom_fields'] ) ) {
            $schema['custom_fields'] = array();
        }

        if ( in_array( $schema['type'], array( 'article', 'person', 'organization', 'product' ), true ) ) {
            if ( '' !== $headline ) {
                $schema['custom_fields']['headline'] = sanitize_text_field( $headline );
            }
            if ( '' !== $description ) {
                $schema['custom_fields']['description'] = sanitize_textarea_field( $description );
            }
        }

        if ( ! isset( $schema['options'] ) || ! is_array( $schema['options'] ) ) {
            $schema['options'] = array();
        }

        $schema['options']['auto_generate'] = '1';
        $schema['options']['validate_output'] = '1';
        if ( 'breadcrumb' !== $schema['type'] ) {
            $schema['options']['include_breadcrumbs'] = '1';
        }

        return $schema;
    }

    private function schema_configs_differ( $current_schema, $recommended_schema ) {
        $current_schema = is_array( $current_schema ) ? $current_schema : array();

        return wp_json_encode( $current_schema ) !== wp_json_encode( $recommended_schema );
    }

    private function get_default_schema_type( $post ) {
        if ( 'product' === $post->post_type ) {
            return 'product';
        }

        if ( 'page' === $post->post_type ) {
            $title = strtolower( $post->post_title );
            if ( strpos( $title, 'about' ) !== false ) {
                return 'organization';
            }
        }

        return 'article';
    }

    private function count_high_priority_items( $items ) {
        $count = 0;

        foreach ( $items as $item ) {
            if ( is_array( $item ) && 'high' === ( $item['priority'] ?? '' ) ) {
                $count++;
            }
        }

        return $count;
    }

    private function normalize_priority( $priority ) {
        $priority = strtolower( sanitize_text_field( (string) $priority ) );
        if ( in_array( $priority, array( 'critical', 'high' ), true ) ) {
            return 'high';
        }

        if ( in_array( $priority, array( 'low', 'minor' ), true ) ) {
            return 'low';
        }

        return 'medium';
    }

    private function trim_to_length( $text, $max_length ) {
        $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
        if ( mb_strlen( $text ) <= $max_length ) {
            return $text;
        }

        $trimmed = mb_substr( $text, 0, $max_length - 1 );
        $last_space = mb_strrpos( $trimmed, ' ' );
        if ( false !== $last_space ) {
            $trimmed = mb_substr( $trimmed, 0, $last_space );
        }

        return rtrim( $trimmed, " ,.|-" );
    }

    private function build_llm_prompt( $post, $analysis, $keyword ) {
        $settings = $this->get_settings();
        $sponsor_safe = ! empty( $settings['sponsor_safe'] );
        $current_state = array(
            'seo_title' => get_post_meta( $post->ID, '_khm_seo_title', true ),
            'meta_description' => get_post_meta( $post->ID, '_khm_seo_description', true ),
            'focus_keyword' => get_post_meta( $post->ID, '_khm_seo_focus_keyword', true ),
            'keywords' => get_post_meta( $post->ID, '_khm_seo_keywords', true ),
            'schema_config' => get_post_meta( $post->ID, '_khm_seo_schema_config', true ),
        );

        $prompt = array();
        $prompt[] = 'You are the KHM SEO Agent. Return JSON only that matches the required schema.';
        $prompt[] = 'sponsor_safe=' . ( $sponsor_safe ? 'true' : 'false' );
        $prompt[] = 'no_hallucination=true';
        $prompt[] = 'Use only these supported action types: set_meta_title, set_meta_description, set_focus_keyword, set_keywords, set_schema_config.';
        $prompt[] = 'set_schema_config payload must be {"value":{"enabled":true,"type":"article|organization|person|product|breadcrumb","custom_fields":{},"options":{}}}.';
        $prompt[] = 'Return 2 to 5 apply_actions whenever title, description, focus keyword, keywords, or schema config can be improved.';
        $prompt[] = 'Use empty apply_actions only if those fields are already well optimized and schema is already enabled.';
        $prompt[] = '';
        $prompt[] = 'Post Title: ' . $post->post_title;
        $prompt[] = 'Focus Keyword: ' . $keyword;
        $prompt[] = 'Current SEO State JSON:';
        $prompt[] = wp_json_encode( $current_state, JSON_UNESCAPED_SLASHES );
        $prompt[] = '';
        $prompt[] = 'SEO Analysis Summary JSON:';
        $analysis_summary = array(
            'overall_score' => $analysis['overall_score'] ?? null,
            'individual_scores' => $analysis['individual_scores'] ?? array(),
            'suggestions' => array_slice( $analysis['suggestions'] ?? array(), 0, 8 ),
            'technical_issues' => array_slice( $analysis['technical_issues'] ?? array(), 0, 8 ),
        );
        $prompt[] = wp_json_encode( $analysis_summary, JSON_UNESCAPED_SLASHES );
        $prompt[] = '';
        $prompt[] = 'Post Content (truncated to 1200 chars):';
        $prompt[] = mb_substr( wp_strip_all_tags( $post->post_content ), 0, 1200 );
        $prompt[] = '';
        $prompt[] = 'Output schema:';
        $prompt[] = '{"summary":{"issues_total":0,"issues_high":0,"suggestions_total":0},"issues":[],"suggestions":[],"apply_actions":[],"upstream_signals":[]}';
        $prompt[] = 'Each apply_actions item must look like {"action_type":"set_meta_title","payload":{"value":"..."}}.';

        return implode( "\n", $prompt );
    }

    private function get_settings() {
        $defaults = array(
            'sponsor_safe' => true,
        );

        $options = get_option( 'khm_seo_agent_settings', array() );
        return wp_parse_args( $options, $defaults );
    }
}
