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
                'llm_output' => $this->get_fallback_payload( $post, $analysis, $keyword ),
                'status' => 'fallback',
                'error' => array(
                    'code' => 'openai_unavailable',
                    'message' => 'OpenAI API key not configured or invalid.',
                ),
            ) );
        }

        $session_id = $this->create_dual_gpt_session( $post_id, $keyword );
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
                'llm_output' => $this->get_fallback_payload( $post, $analysis, $keyword ),
                'status' => 'fallback',
                'error' => array(
                    'code' => $job_id->get_error_code(),
                    'message' => $job_id->get_error_message(),
                ),
            ) );
        }

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
                'llm_output' => $this->get_fallback_payload( $post, $analysis, $keyword ),
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
                'session_id' => $session_id,
                'job_id' => $job_id,
                'llm_output' => $this->get_fallback_payload( $post, $analysis, $keyword ),
                'status' => 'fallback',
                'error' => array(
                    'code' => $validation->get_error_code(),
                    'message' => $validation->get_error_message(),
                ),
            ) );
        }

        $llm_payload = $this->enrich_llm_payload( $llm_payload, $post, $analysis, $keyword );

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

    private function get_fallback_payload( $post, $analysis, $keyword ) {
        return $this->build_deterministic_payload( $post, $analysis, $keyword );
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

        $context = $this->get_job_context( $job_result );
        if ( $context ) {
            $llm_payload = $this->enrich_llm_payload(
                $llm_payload,
                $context['post'],
                $context['analysis'],
                $context['keyword']
            );
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

    private function create_dual_gpt_session( $post_id, $keyword = '' ) {
        $request = new \WP_REST_Request( 'POST', '/dual-gpt/v1/sessions' );
        $request->set_param( 'role', 'seo' );
        $request->set_param( 'title', 'SEO Agent - ' . current_time( 'mysql' ) );
        $request->set_param( 'post_id', $post_id );
        $request->set_param( 'meta', array(
            'source' => 'khm_seo_agent',
            'focus_keyword' => sanitize_text_field( $keyword ),
        ) );

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

    private function create_dual_gpt_job( $session_id, $prompt ) {
        $request = new \WP_REST_Request( 'POST', '/dual-gpt/v1/jobs' );
        $request->set_param( 'session_id', $session_id );
        $request->set_param( 'prompt', $prompt );
        $request->set_param( 'model', 'gpt-4o' );
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

        // Strip markdown code fences if present
        $content = preg_replace('/^```(?:json)?[\r\n]+|```[\r\n]*$/', '', trim($content));

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
        if ( ! class_exists( 'Dual_GPT_DB_Handler' ) ) {
            return null;
        }

        $session_id = sanitize_text_field( $job['session_id'] ?? '' );
        if ( '' === $session_id ) {
            return null;
        }

        $db = new \Dual_GPT_DB_Handler();
        $session = $db->get_session( $session_id );
        if ( ! is_array( $session ) ) {
            return null;
        }

        $post_id = intval( $session['post_id'] ?? 0 );
        if ( ! $post_id ) {
            return null;
        }

        $post = get_post( $post_id );
        if ( ! $post || ! function_exists( 'khm_seo' ) || ! khm_seo() ) {
            return null;
        }

        $analysis_engine = khm_seo()->get_analysis_engine();
        if ( ! $analysis_engine ) {
            return null;
        }

        $meta = json_decode( $session['meta_json'] ?? '', true );
        if ( ! is_array( $meta ) ) {
            $meta = array();
        }

        $keyword = sanitize_text_field( $meta['focus_keyword'] ?? '' );
        if ( '' === $keyword ) {
            $keyword = sanitize_text_field( get_post_meta( $post_id, '_khm_seo_focus_keyword', true ) );
        }

        $analysis = $analysis_engine->analyze( array(
            'post_id' => $post_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'meta_description' => get_post_meta( $post_id, '_khm_seo_description', true ),
            'focus_keyword' => $keyword,
        ) );

        return array(
            'post' => $post,
            'analysis' => $analysis,
            'keyword' => $keyword,
        );
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
