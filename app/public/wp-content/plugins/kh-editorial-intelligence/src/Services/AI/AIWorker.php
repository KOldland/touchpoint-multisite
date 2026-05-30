<?php

namespace KH\Editorial\Services\AI;

use KH\Editorial\Core\Container;
use KH\Editorial\Core\LLMService;
use KH\Editorial\Services\AI\SEOAgent;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AIWorker
 * 
 * Background processor for AI jobs. Listens for job creation,
 * manages state transitions, and dispatches to specific handlers.
 */
class AIWorker {

    /**
     * @var AIStorage
     */
    private $storage;

    public function __construct() {
        $this->storage = new AIStorage();
    }

    /**
     * Initialize the worker by hooking into job creation.
     */
    public function init() {
        add_action( 'kh_editorial_job_created', [ $this, 'process_job' ], 10, 2 );
        add_filter( 'kh_editorial_execute_job_seo_audit', [ $this, 'handle_seo_audit_job' ], 10, 2 );
    }

    /**
     * Entry point for job processing.
     */
    public function process_job( $job_id, $type ) {
        $job = $this->storage->get_job( $job_id );
        if ( ! $job ) {
            error_log( "[Editorial AI] Worker error: Job {$job_id} not found." );
            return;
        }

        $this->storage->update_job( $job_id, 'processing' );
        $this->storage->log_event( $job_id, 'job_started', [ 'type' => $type ] );

        $result = apply_filters( "kh_editorial_execute_job_{$type}", null, $job );

        if ( is_wp_error( $result ) ) {
            $this->handle_failure( $job_id, $result );
        } elseif ( $result !== null ) {
            // Pass the job creator ID for budgeting attribution
            $this->handle_success( $job_id, $result, (int) ($job['created_by'] ?? 0) );
        } else {
            $this->handle_failure( $job_id, new \WP_Error( 
                'unhandled_job', 
                sprintf( 'No handler found for job type: %s', $type ) 
            ) );
        }
    }

    /**
     * Specialized handler for SEO Audit jobs.
     */
    public function handle_seo_audit_job( $dummy, $job ) {
        $payload = json_decode( $job['payload'], true );
        $post_id = (int) ($payload['post_id'] ?? 0);
        $keyword = $payload['keyword'] ?? '';

        if ( ! $post_id ) {
            return new \WP_Error( 'invalid_post', 'Missing Post ID in job payload.' );
        }

        try {
            /** @var SEOAgent $agent */
            $agent = Container::get('SEOAgent');
            
            // 1. Get Agent context (Analysis + Prompt)
            $audit_context = $agent->audit( $post_id, $keyword );

            if ( $audit_context['status'] === 'fallback' ) {
                return $audit_context['llm_output'];
            }

            // 2. Execute LLM Request via unified LLMService
            // Use the Agent's detailed prompt as the SYSTEM instruction for persona integrity.
            $messages = [
                [ 'role' => 'system', 'content' => $audit_context['prompt'] ]
            ];

            $llm_result = LLMService::post_completion( $messages );

            if ( is_wp_error( $llm_result ) ) {
                $this->storage->log_event( $job['id'], 'llm_fallback_triggered', [ 'error' => $llm_result->get_error_message() ] );
                return $audit_context['deterministic_payload'];
            }
            
            // 3. Process & Augment
            $final_payload = $agent->process_response( $llm_result['content'], [
                'post_id'               => $post_id,
                'deterministic_payload' => $audit_context['deterministic_payload']
            ]);

            // Include usage data for the success handler
            $final_payload['usage'] = $llm_result['usage'];

            return $final_payload;

        } catch (\Exception $e) {
            return new \WP_Error( 'agent_error', $e->getMessage() );
        }
    }

    /**
     * Handle successful job completion.
     */
    private function handle_success( $job_id, $result, int $user_id ) {
        $this->storage->update_job( $job_id, 'completed', [
            'response' => wp_json_encode( $result )
        ] );

        $this->storage->log_event( $job_id, 'job_completed' );

        // Financial Integrity: Record usage in the budget table
        if ( ! empty( $result['usage'] ) && $user_id > 0 ) {
            $tokens = intval( $result['usage']['total_tokens'] ?? 0 );
            $this->storage->update_budget_usage( $user_id, $tokens );
        }

        do_action( 'kh_editorial_job_completed', $job_id, $result );
    }

    /**
     * Handle job failure.
     */
    private function handle_failure( $job_id, $error ) {
        $this->storage->update_job( $job_id, 'failed', [
            'error_message' => $error->get_error_message()
        ] );

        $this->storage->log_event( $job_id, 'job_failed', [
            'code'    => $error->get_error_code(),
            'message' => $error->get_error_message()
        ] );

        do_action( 'kh_editorial_job_failed', $job_id, $error );
    }
}
