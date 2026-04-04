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
        return current_user_can( 'edit_posts' );
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

        if ( ! $this->is_openai_available() ) {
            return rest_ensure_response( array(
                'post_id' => $post_id,
                'analysis' => $analysis,
                'session_id' => null,
                'job_id' => null,
                'llm_output' => $this->get_fallback_payload( $analysis ),
                'status' => 'fallback',
                'error' => array(
                    'code' => 'openai_unavailable',
                    'message' => 'OpenAI API key not configured or invalid.',
                ),
            ) );
        }

        $session_id = $this->create_dual_gpt_session( $post_id );
        if ( is_wp_error( $session_id ) ) {
            return $session_id;
        }

        $prompt = $this->build_llm_prompt( $post, $analysis, $keyword );
        $job_id = $this->create_dual_gpt_job( $session_id, $prompt );
        if ( is_wp_error( $job_id ) ) {
            return rest_ensure_response( array(
                'post_id' => $post_id,
                'analysis' => $analysis,
                'session_id' => $session_id,
                'job_id' => null,
                'llm_output' => $this->get_fallback_payload( $analysis ),
                'status' => 'fallback',
                'error' => array(
                    'code' => $job_id->get_error_code(),
                    'message' => $job_id->get_error_message(),
                ),
            ) );
        }

        $this->cache_audit_context( $job_id, $post_id, $analysis, $keyword );

        $job_result = $this->wait_for_dual_gpt_job( $job_id, 10 );
        if ( is_wp_error( $job_result ) ) {
            if ( $job_result->get_error_code() === 'job_not_ready' ) {
                return rest_ensure_response( array(
                    'post_id' => $post_id,
                    'analysis' => $analysis,
                    'session_id' => $session_id,
                    'job_id' => $job_id,
                    'status' => 'queued',
                ) );
            }

            return rest_ensure_response( array(
                'post_id' => $post_id,
                'analysis' => $analysis,
                'session_id' => $session_id,
                'job_id' => $job_id,
                'llm_output' => $this->get_fallback_payload( $analysis ),
                'status' => 'fallback',
                'error' => array(
                    'code' => $job_result->get_error_code(),
                    'message' => $job_result->get_error_message(),
                ),
            ) );
        }

        $llm_payload = $this->parse_llm_payload( $job_result );
        if ( is_wp_error( $llm_payload ) ) {
            return rest_ensure_response( array(
                'post_id' => $post_id,
                'analysis' => $analysis,
                'session_id' => $session_id,
                'job_id' => $job_id,
                'llm_output' => $this->get_fallback_payload( $analysis ),
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
                'session_id' => $session_id,
                'job_id' => $job_id,
                'llm_output' => $this->get_fallback_payload( $analysis ),
                'status' => 'fallback',
                'error' => array(
                    'code' => $validation->get_error_code(),
                    'message' => $validation->get_error_message(),
                ),
            ) );
        }

        $llm_payload = $this->ensure_apply_actions( $llm_payload, $analysis, $post, $keyword );

        return rest_ensure_response( array(
            'post_id' => $post_id,
            'analysis' => $analysis,
            'llm_output' => $llm_payload,
            'session_id' => $session_id,
            'job_id' => $job_id,
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

    private function get_fallback_payload( $analysis ) {
        $suggestions = $analysis['suggestions'] ?? array();
        $issues = $analysis['technical_issues'] ?? array();
        $fallback_actions = $this->build_fallback_apply_actions( 0, $analysis, '' );

        return array(
            'summary' => array(
                'issues_total' => is_array( $issues ) ? count( $issues ) : 0,
                'issues_high' => 0,
                'suggestions_total' => is_array( $suggestions ) ? count( $suggestions ) : 0,
            ),
            'issues' => is_array( $issues ) ? array_slice( $issues, 0, 8 ) : array(),
            'suggestions' => is_array( $suggestions ) ? array_slice( $suggestions, 0, 8 ) : array(),
            'apply_actions' => $fallback_actions,
            'upstream_signals' => array(),
        );
    }

    private function is_openai_available() {
        if ( ! class_exists( 'Dual_GPT_OpenAI_Connector' ) ) {
            return false;
        }

        $connector = new \Dual_GPT_OpenAI_Connector();
        return $connector->validate_api_key();
    }

    public function handle_audit_status( $request ) {
        $job_id = sanitize_text_field( $request->get_param( 'job_id' ) );
        if ( empty( $job_id ) ) {
            return new \WP_Error( 'missing_job_id', 'job_id is required.', array( 'status' => 400 ) );
        }

        $job_result = $this->get_dual_gpt_job_result( $job_id );
        if ( is_wp_error( $job_result ) ) {
            return $job_result;
        }

        $llm_payload = $this->parse_llm_payload( $job_result );
        if ( is_wp_error( $llm_payload ) ) {
            return $llm_payload;
        }

        $validation = $this->validate_llm_output( $llm_payload );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $context = $this->get_cached_audit_context( $job_id );
        if ( $context ) {
            $post = get_post( (int) ( $context['post_id'] ?? 0 ) );
            $analysis = is_array( $context['analysis'] ?? null ) ? $context['analysis'] : array();
            $keyword = sanitize_text_field( (string) ( $context['keyword'] ?? '' ) );
            $llm_payload = $this->ensure_apply_actions( $llm_payload, $analysis, $post, $keyword );
        }

        return rest_ensure_response( array(
            'job_id' => $job_id,
            'llm_output' => $llm_payload,
            'status' => 'completed',
        ) );
    }

    public function handle_preview( $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        $actions = $request->get_param( 'actions' );

        if ( empty( $post_id ) || empty( $actions ) ) {
            return new \WP_Error( 'invalid_preview', 'post_id and actions are required.', array( 'status' => 400 ) );
        }

        if ( ! class_exists( 'Dual_GPT_SEO_Tools' ) ) {
            return new \WP_Error( 'seo_tools_missing', 'Dual-GPT SEO tools not available.', array( 'status' => 500 ) );
        }

        $tools = new \Dual_GPT_SEO_Tools();
        $result = $tools->tool_preview_apply( array(
            'post_id' => $post_id,
            'actions' => $actions,
        ) );

        return rest_ensure_response( $result );
    }

    public function handle_apply( $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        $actions = $request->get_param( 'actions' );
        $job_id = sanitize_text_field( $request->get_param( 'job_id' ) );
        $idempotency_key = sanitize_text_field( $request->get_param( 'idempotency_key' ) );
        $acting_user_id = get_current_user_id();

        if ( empty( $post_id ) || empty( $actions ) || empty( $job_id ) || empty( $idempotency_key ) ) {
            return new \WP_Error( 'invalid_apply', 'post_id, actions, job_id, and idempotency_key are required.', array( 'status' => 400 ) );
        }

        if ( ! class_exists( 'Dual_GPT_SEO_Tools' ) ) {
            return new \WP_Error( 'seo_tools_missing', 'Dual-GPT SEO tools not available.', array( 'status' => 500 ) );
        }

        $tools = new \Dual_GPT_SEO_Tools();
        $result = $tools->tool_apply_actions( array(
            'post_id' => $post_id,
            'actions' => $actions,
            'acting_user_id' => $acting_user_id,
            'idempotency_key' => $idempotency_key,
            'job_id' => $job_id,
        ) );

        return rest_ensure_response( $result );
    }

    private function create_dual_gpt_session( $post_id ) {
        $preset_id = $this->ensure_seo_agent_preset();

        $request = new \WP_REST_Request( 'POST', '/dual-gpt/v1/sessions' );
        $request->set_param( 'role', 'seo' );
        if ( '' !== $preset_id ) {
            $request->set_param( 'preset_id', $preset_id );
        }
        $request->set_param( 'title', 'SEO Agent - ' . current_time( 'mysql' ) );
        $request->set_param( 'post_id', $post_id );

        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            return $response->as_error();
        }

        $data = $response->get_data();
        if ( empty( $data['session_id'] ) ) {
            return new \WP_Error( 'session_failed', 'Failed to create Dual-GPT session.', array( 'status' => 500 ) );
        }

        return $data['session_id'];
    }

    private function ensure_seo_agent_preset() {
        if ( ! class_exists( 'Dual_GPT_DB_Handler' ) ) {
            return '';
        }

        $db = new \Dual_GPT_DB_Handler();
        $preset = $db->get_preset( 'seo-agent' );
        if ( $preset ) {
            return 'seo-agent';
        }

        if ( method_exists( $db, 'create_default_presets' ) ) {
            $db->create_default_presets();
            $preset = $db->get_preset( 'seo-agent' );
            if ( $preset ) {
                return 'seo-agent';
            }
        }

        if ( method_exists( $db, 'insert_preset' ) ) {
            $inserted = $db->insert_preset(
                array(
                    'id' => 'seo-agent',
                    'name' => 'SEO Agent',
                    'role' => 'seo',
                    'system_prompt' => 'You are the KHM SEO Agent. Analyze content for SEO, prioritize improvements and produce structured JSON. Only use allowed tools. Do NOT apply changes directly without a confirmed apply action from a human. For upstream proposals return structured proposals and preview content. Return errors in a strict JSON schema when failing. Respect sponsor_safe and no_hallucination constraints.',
                    'default_model' => 'gpt-4-turbo',
                    'params_json' => wp_json_encode(
                        array(
                            'temperature' => 0.2,
                            'max_tokens' => 2000,
                        )
                    ),
                    'tool_whitelist' => wp_json_encode( array( 'seo_tools', 'research_tools' ) ),
                    'is_locked' => true,
                )
            );
            if ( ! is_wp_error( $inserted ) ) {
                $preset = $db->get_preset( 'seo-agent' );
                if ( $preset ) {
                    return 'seo-agent';
                }
            }
        }

        return '';
    }

    private function create_dual_gpt_job( $session_id, $prompt ) {
        $request = new \WP_REST_Request( 'POST', '/dual-gpt/v1/jobs' );
        $request->set_param( 'session_id', $session_id );
        $request->set_param( 'prompt', $prompt );
        $request->set_param( 'model', 'gpt-4-turbo' );
        $request->set_param( 'idempotency_key', 'seo-agent-' . wp_generate_uuid4() );

        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            return $response->as_error();
        }

        $data = $response->get_data();
        if ( empty( $data['job_id'] ) ) {
            return new \WP_Error( 'job_failed', 'Failed to create Dual-GPT job.', array( 'status' => 500 ) );
        }

        return $data['job_id'];
    }

    private function get_dual_gpt_job_result( $job_id ) {
        if ( ! class_exists( 'Dual_GPT_DB_Handler' ) ) {
            return new \WP_Error( 'dual_gpt_db_missing', 'Dual-GPT DB handler unavailable.', array( 'status' => 500 ) );
        }

        $db = new \Dual_GPT_DB_Handler();
        $job = $db->get_job( $job_id );
        if ( ! $job ) {
            return new \WP_Error( 'job_not_found', 'Dual-GPT job not found.', array( 'status' => 404 ) );
        }

        if ( $job['status'] !== 'completed' ) {
            return new \WP_Error( 'job_not_ready', 'Dual-GPT job not completed yet.', array( 'status' => 409 ) );
        }

        return $job;
    }

    private function wait_for_dual_gpt_job( $job_id, $timeout_seconds = 10 ) {
        if ( ! class_exists( 'Dual_GPT_DB_Handler' ) ) {
            return new \WP_Error( 'dual_gpt_db_missing', 'Dual-GPT DB handler unavailable.', array( 'status' => 500 ) );
        }

        $db = new \Dual_GPT_DB_Handler();
        $start = time();

        while ( time() - $start <= $timeout_seconds ) {
            $job = $db->get_job( $job_id );
            if ( ! $job ) {
                return new \WP_Error( 'job_not_found', 'Dual-GPT job not found.', array( 'status' => 404 ) );
            }

            if ( $job['status'] === 'completed' ) {
                return $job;
            }

            if ( $job['status'] === 'failed' ) {
                return new \WP_Error( 'job_failed', $job['error_message'] ?? 'Dual-GPT job failed.', array( 'status' => 500 ) );
            }

            sleep( 1 );
        }

        return new \WP_Error( 'job_not_ready', 'Dual-GPT job not completed yet.', array( 'status' => 409, 'job_id' => $job_id ) );
    }

    private function parse_llm_payload( $job ) {
        $response_json = $job['response_json'] ?? '';
        if ( empty( $response_json ) ) {
            return new \WP_Error( 'empty_llm_response', 'Dual-GPT response_json is empty.', array( 'status' => 500 ) );
        }

        $response = json_decode( $response_json, true );
        if ( ! is_array( $response ) ) {
            return new \WP_Error( 'invalid_llm_response', 'Dual-GPT response_json is invalid.', array( 'status' => 500 ) );
        }

        $content = $response['choices'][0]['message']['content'] ?? '';
        if ( empty( $content ) ) {
            return new \WP_Error( 'empty_llm_content', 'Dual-GPT content is empty.', array( 'status' => 500 ) );
        }

        $decoded = json_decode( $content, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'llm_invalid_json', 'LLM returned invalid JSON.', array(
                'status' => 422,
                'details' => json_last_error_msg(),
            ) );
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

    private function build_llm_prompt( $post, $analysis, $keyword ) {
        $settings = $this->get_settings();
        $sponsor_safe = ! empty( $settings['sponsor_safe'] );

        $prompt = array();
        $prompt[] = 'You are the KHM SEO Agent. Return JSON only that matches the required schema.';
        $prompt[] = 'sponsor_safe=' . ( $sponsor_safe ? 'true' : 'false' );
        $prompt[] = 'no_hallucination=true';
        $prompt[] = '';
        $prompt[] = 'Post Title: ' . $post->post_title;
        $prompt[] = 'Focus Keyword: ' . $keyword;
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

        return implode( "\n", $prompt );
    }

    private function get_settings() {
        $defaults = array(
            'sponsor_safe' => true,
        );

        $options = get_option( 'khm_seo_agent_settings', array() );
        return wp_parse_args( $options, $defaults );
    }

    private function cache_audit_context( $job_id, $post_id, $analysis, $keyword ) {
        if ( empty( $job_id ) ) {
            return;
        }

        set_transient(
            'khm_seo_agent_ctx_' . sanitize_key( $job_id ),
            array(
                'post_id' => (int) $post_id,
                'analysis' => is_array( $analysis ) ? $analysis : array(),
                'keyword' => sanitize_text_field( (string) $keyword ),
            ),
            HOUR_IN_SECONDS
        );
    }

    private function get_cached_audit_context( $job_id ) {
        if ( empty( $job_id ) ) {
            return null;
        }

        $ctx = get_transient( 'khm_seo_agent_ctx_' . sanitize_key( $job_id ) );
        return is_array( $ctx ) ? $ctx : null;
    }

    private function ensure_apply_actions( $payload, $analysis, $post, $keyword ) {
        if ( ! is_array( $payload ) ) {
            return $payload;
        }

        $actions = $payload['apply_actions'] ?? array();
        if ( is_array( $actions ) && count( $actions ) > 0 ) {
            return $payload;
        }

        $post_id = ( $post instanceof \WP_Post ) ? (int) $post->ID : 0;
        $payload['apply_actions'] = $this->build_fallback_apply_actions( $post_id, $analysis, $keyword );
        return $payload;
    }

    private function build_fallback_apply_actions( $post_id, $analysis, $keyword ) {
        $actions = array();
        $post = $post_id > 0 ? get_post( $post_id ) : null;

        $ranked_keywords = $this->rank_keyword_candidates(
            $this->collect_keyword_candidates( $post_id, $post, $analysis, $keyword ),
            $post
        );
        $primary_keyword = isset( $ranked_keywords[0] ) ? $ranked_keywords[0] : sanitize_text_field( (string) $keyword );
        $secondary_keywords = array_slice( array_values( array_diff( $ranked_keywords, array( $primary_keyword ) ) ), 0, 5 );

        if ( $post_id > 0 && '' !== $primary_keyword ) {
            $existing_focus = trim( (string) get_post_meta( $post_id, '_khm_seo_focus_keyword', true ) );
            if ( '' === $existing_focus ) {
                $actions[] = array(
                    'action_type' => 'set_focus_keyword',
                    'payload' => array( 'value' => $primary_keyword ),
                );
            }

            $existing_keywords = trim( (string) get_post_meta( $post_id, '_khm_seo_keywords', true ) );
            if ( '' === $existing_keywords ) {
                $keyword_list = array_unique( array_filter( array_merge( array( $primary_keyword ), $secondary_keywords ) ) );
                $actions[] = array(
                    'action_type' => 'set_keywords',
                    'payload' => array( 'value' => implode( ', ', $keyword_list ) ),
                );
            }
        }

        if ( $post instanceof \WP_Post ) {
            $existing_title = trim( (string) get_post_meta( $post_id, '_khm_seo_title', true ) );
            if ( '' === $existing_title ) {
                $suggested_title = $post->post_title;
                if ( '' !== $primary_keyword && false === stripos( $suggested_title, $primary_keyword ) ) {
                    $suggested_title = trim( $post->post_title . ' | ' . $primary_keyword );
                }
                $actions[] = array(
                    'action_type' => 'set_meta_title',
                    'payload' => array( 'value' => mb_substr( $suggested_title, 0, 60 ) ),
                );
            }

            $existing_description = trim( (string) get_post_meta( $post_id, '_khm_seo_description', true ) );
            if ( '' === $existing_description ) {
                $base = trim( (string) $post->post_excerpt );
                if ( '' === $base ) {
                    $base = trim( wp_strip_all_tags( (string) $post->post_content ) );
                }
                $base = preg_replace( '/\s+/', ' ', $base );
                if ( '' !== $primary_keyword && false === stripos( $base, $primary_keyword ) ) {
                    $base = $primary_keyword . ': ' . $base;
                }
                $actions[] = array(
                    'action_type' => 'set_meta_description',
                    'payload' => array( 'value' => mb_substr( $base, 0, 155 ) ),
                );
            }
        }

        if ( empty( $actions ) && '' !== $primary_keyword ) {
            // Keep at least one non-destructive option visible so the audit is actionable.
            $actions[] = array(
                'action_type' => 'set_keywords',
                'payload' => array( 'value' => $primary_keyword ),
            );
        }

        return $actions;
    }

    private function collect_keyword_candidates( $post_id, $post, $analysis, $keyword ) {
        $candidates = array();
        $this->add_candidate_keyword( $candidates, $keyword );

        if ( $post_id > 0 ) {
            $this->add_candidate_keyword( $candidates, get_post_meta( $post_id, '_khm_seo_focus_keyword', true ) );

            $stored_keywords = get_post_meta( $post_id, '_khm_seo_keywords', true );
            if ( is_string( $stored_keywords ) ) {
                foreach ( preg_split( '/[,;\n]+/', $stored_keywords ) as $term ) {
                    $this->add_candidate_keyword( $candidates, $term );
                }
            } elseif ( is_array( $stored_keywords ) ) {
                foreach ( $stored_keywords as $term ) {
                    $this->add_candidate_keyword( $candidates, $term );
                }
            }

            $terms = wp_get_post_terms( $post_id, array( 'category', 'post_tag' ), array( 'fields' => 'names' ) );
            if ( is_array( $terms ) ) {
                foreach ( $terms as $term_name ) {
                    $this->add_candidate_keyword( $candidates, $term_name );
                }
            }
        }

        foreach ( $this->get_framework_keywords_from_sessions( $post_id ) as $term ) {
            $this->add_candidate_keyword( $candidates, $term );
        }

        if ( $post instanceof \WP_Post ) {
            $text = implode(
                ' ',
                array(
                    (string) $post->post_title,
                    (string) $post->post_excerpt,
                    wp_strip_all_tags( (string) $post->post_content ),
                )
            );
            foreach ( $this->extract_keyphrases_from_text( $text ) as $phrase ) {
                $this->add_candidate_keyword( $candidates, $phrase );
            }
        }

        if ( is_array( $analysis['suggestions'] ?? null ) ) {
            foreach ( $analysis['suggestions'] as $suggestion ) {
                if ( is_string( $suggestion ) ) {
                    foreach ( $this->extract_keyphrases_from_text( $suggestion, 8 ) as $phrase ) {
                        $this->add_candidate_keyword( $candidates, $phrase );
                    }
                }
            }
        }

        return array_values( array_unique( $candidates ) );
    }

    private function get_framework_keywords_from_sessions( $post_id ) {
        if ( $post_id <= 0 ) {
            return array();
        }

        global $wpdb;
        $keywords = array();

        $sessions_table = $wpdb->prefix . 'ai_sessions';
        $sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, meta_json FROM {$sessions_table} WHERE post_id = %d ORDER BY updated_at DESC LIMIT 10",
                $post_id
            ),
            ARRAY_A
        );

        if ( is_array( $sessions ) ) {
            foreach ( $sessions as $session ) {
                $meta = json_decode( (string) ( $session['meta_json'] ?? '' ), true );
                if ( ! is_array( $meta ) ) {
                    continue;
                }

                $phase_candidates = $meta['phase1']['candidate_keywords'] ?? array();
                if ( is_array( $phase_candidates ) ) {
                    foreach ( $phase_candidates as $term ) {
                        $this->add_candidate_keyword( $keywords, $term );
                    }
                }

                if ( ! empty( $meta['articles'] ) && is_array( $meta['articles'] ) ) {
                    foreach ( $meta['articles'] as $article ) {
                        if ( ! is_array( $article ) || empty( $article['keywords'] ) || ! is_array( $article['keywords'] ) ) {
                            continue;
                        }
                        foreach ( $article['keywords'] as $term ) {
                            $this->add_candidate_keyword( $keywords, $term );
                        }
                    }
                }

                if ( ! empty( $session['id'] ) ) {
                    $fg_table = $wpdb->prefix . 'fg_session_keywords';
                    $fg_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $fg_table ) );
                    if ( $fg_exists === $fg_table ) {
                        $rows = $wpdb->get_col(
                            $wpdb->prepare(
                                "SELECT keyword FROM {$fg_table} WHERE session_id = %s ORDER BY created_at DESC LIMIT 20",
                                $session['id']
                            )
                        );
                        if ( is_array( $rows ) ) {
                            foreach ( $rows as $term ) {
                                $this->add_candidate_keyword( $keywords, $term );
                            }
                        }
                    }
                }
            }
        }

        return array_values( array_unique( $keywords ) );
    }

    private function rank_keyword_candidates( $candidates, $post ) {
        if ( ! is_array( $candidates ) || empty( $candidates ) ) {
            return array();
        }

        $title = '';
        $content = '';
        if ( $post instanceof \WP_Post ) {
            $title = strtolower( (string) $post->post_title );
            $content = strtolower(
                wp_strip_all_tags(
                    (string) $post->post_title . ' ' . (string) $post->post_excerpt . ' ' . (string) $post->post_content
                )
            );
        }

        $scores = array();
        foreach ( $candidates as $candidate ) {
            $kw = strtolower( trim( (string) $candidate ) );
            if ( '' === $kw ) {
                continue;
            }

            $words = preg_split( '/\s+/', $kw );
            $word_count = is_array( $words ) ? count( array_filter( $words ) ) : 0;
            if ( $word_count < 1 || $word_count > 5 ) {
                continue;
            }

            $score = 0;
            if ( '' !== $title && false !== stripos( $title, $kw ) ) {
                $score += 40;
            }

            if ( '' !== $content ) {
                $freq = substr_count( $content, $kw );
                $score += min( 30, $freq * 4 );
            }

            if ( $word_count >= 2 && $word_count <= 4 ) {
                $score += 12;
            } elseif ( $word_count === 1 ) {
                $score += 4;
            }

            $score += min( 8, (int) floor( strlen( $kw ) / 12 ) );
            $scores[ $kw ] = max( $score, $scores[ $kw ] ?? 0 );
        }

        arsort( $scores );
        return array_values( array_keys( $scores ) );
    }

    private function extract_keyphrases_from_text( $text, $limit = 20 ) {
        $normalized = strtolower( wp_strip_all_tags( (string) $text ) );
        $normalized = preg_replace( '/[^a-z0-9\s-]/', ' ', $normalized );
        $normalized = preg_replace( '/\s+/', ' ', $normalized );
        $tokens = array_values( array_filter( explode( ' ', trim( (string) $normalized ) ) ) );

        if ( empty( $tokens ) ) {
            return array();
        }

        $stop = array(
            'the', 'and', 'for', 'with', 'that', 'this', 'from', 'into', 'your', 'about', 'have',
            'has', 'are', 'was', 'were', 'will', 'can', 'not', 'but', 'you', 'our', 'their',
            'they', 'them', 'its', 'per', 'via', 'out', 'how', 'why', 'when', 'where', 'what',
            'which', 'while', 'than', 'then', 'also', 'such', 'more', 'most', 'over', 'under',
            'onto', 'across', 'after', 'before', 'between', 'within', 'without', 'using',
        );
        $stop_lookup = array_fill_keys( $stop, true );
        $phrases = array();
        $count = count( $tokens );

        for ( $i = 0; $i < $count; $i++ ) {
            if ( isset( $stop_lookup[ $tokens[ $i ] ] ) || strlen( $tokens[ $i ] ) < 3 ) {
                continue;
            }
            for ( $n = 2; $n <= 4; $n++ ) {
                if ( $i + $n > $count ) {
                    break;
                }
                $slice = array_slice( $tokens, $i, $n );
                $skip = false;
                foreach ( $slice as $word ) {
                    if ( isset( $stop_lookup[ $word ] ) || strlen( $word ) < 3 ) {
                        $skip = true;
                        break;
                    }
                }
                if ( $skip ) {
                    continue;
                }
                $phrase = implode( ' ', $slice );
                $phrases[ $phrase ] = ( $phrases[ $phrase ] ?? 0 ) + 1;
            }
        }

        arsort( $phrases );
        return array_slice( array_keys( $phrases ), 0, max( 1, (int) $limit ) );
    }

    private function add_candidate_keyword( &$bucket, $value ) {
        $term = sanitize_text_field( (string) $value );
        $term = strtolower( trim( preg_replace( '/\s+/', ' ', $term ) ) );
        if ( '' === $term || strlen( $term ) < 3 ) {
            return;
        }
        $bucket[] = $term;
    }
}
