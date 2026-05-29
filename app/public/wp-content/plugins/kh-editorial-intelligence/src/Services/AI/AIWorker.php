<?php

namespace KH\Editorial\Services\AI;

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
    }

    /**
     * Entry point for job processing.
     * 
     * @param string $job_id The UUID of the job.
     * @param string $type   The type of job (e.g., 'draft').
     */
    public function process_job( $job_id, $type ) {
        $job = $this->storage->get_job( $job_id );
        if ( ! $job ) {
            error_log( "[Editorial AI] Worker error: Job {$job_id} not found." );
            return;
        }

        // Mark as processing
        $this->storage->update_job( $job_id, 'processing' );
        $this->storage->log_event( $job_id, 'job_started', [ 'type' => $type ] );

        /**
         * Dispatch job execution via filter.
         * 
         * Other plugins (like kh-editorial-author) should hook into this 
         * to provide the actual execution logic for their job types.
         */
        $result = apply_filters( "kh_editorial_execute_job_{$type}", null, $job );

        if ( is_wp_error( $result ) ) {
            $this->handle_failure( $job_id, $result );
        } elseif ( $result !== null ) {
            $this->handle_success( $job_id, $result );
        } else {
            $this->handle_failure( $job_id, new \WP_Error( 
                'unhandled_job', 
                sprintf( 'No handler found for job type: %s', $type ) 
            ) );
        }
    }

    /**
     * Handle successful job completion.
     */
    private function handle_success( $job_id, $result ) {
        $this->storage->update_job( $job_id, 'completed', [
            'response' => wp_json_encode( $result )
        ] );
        
        $this->storage->log_event( $job_id, 'job_completed' );

        // Update budget if usage data and creator ID are available
        if ( ! empty( $result['usage'] ) && ! empty( $result['created_by'] ) ) {
            $tokens = intval( $result['usage']['total_tokens'] ?? 0 );
            $this->storage->update_budget_usage( $result['created_by'], $tokens );
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
