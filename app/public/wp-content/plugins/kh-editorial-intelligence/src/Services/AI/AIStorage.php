<?php

namespace KH\Editorial\Services\AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AIStorage
 * 
 * Handles all database interactions for the suite's AI infrastructure.
 * Modeled after the AttributionStorage pattern.
 */
class AIStorage {

    /**
     * Insert a new AI job into the queue.
     */
    public function insert_job( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_jobs';

        $defaults = [
            'id'           => wp_generate_uuid4(),
            'status'       => 'queued',
            'created_at'   => current_time( 'mysql' ),
            'created_by'   => get_current_user_id(),
        ];

        $data = wp_parse_args( $data, $defaults );

        // Check for idempotency
        if ( ! empty( $data['idempotency_key'] ) ) {
            $existing = $this->get_job_by_idempotency( $data['session_id'], $data['idempotency_key'] );
            if ( $existing ) {
                return $existing['id'];
            }
        }

        $result = $wpdb->insert( $table, $data );

        if ( $result === false ) {
            error_log( '[Editorial AI] DB Insert failed: ' . $wpdb->last_error );
            return new \WP_Error( 'db_insert_error', 'Failed to insert AI job.' );
        }

        return $data['id'];
    }

    /**
     * Update job status and data.
     */
    public function update_job( $job_id, $status, $additional_data = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_jobs';

        $data = array_merge( $additional_data, [
            'status' => $status,
        ] );

        if ( in_array( $status, [ 'completed', 'failed' ], true ) ) {
            $data['finished_at'] = current_time( 'mysql' );
        }

        $result = $wpdb->update( $table, $data, [ 'id' => $job_id ] );
        return $result !== false;
    }

    /**
     * Get job by ID.
     */
    public function get_job( $job_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_jobs';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %s", $job_id ), ARRAY_A );
    }

    /**
     * Get job by idempotency key.
     */
    public function get_job_by_idempotency( $session_id, $key ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_jobs';
        return $wpdb->get_row( $wpdb->prepare( 
            "SELECT id FROM $table WHERE session_id = %s AND idempotency_key = %s", 
            $session_id, $key 
        ), ARRAY_A );
    }

    /**
     * Record usage in budget.
     */
    public function update_budget_usage( $user_id, $tokens ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_budgets';

        return $wpdb->query( $wpdb->prepare(
            "UPDATE $table SET token_used = token_used + %d WHERE scope = 'user' AND scope_id = %s AND reset_at > NOW()",
            $tokens, $user_id
        ) );
    }

    /**
     * Log an audit event.
     */
    public function log_event( $job_id, $type, $payload = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_audit';

        return $wpdb->insert( $table, [
            'job_id'       => $job_id,
            'event_type'   => $type,
            'payload_json' => $payload ? wp_json_encode( $payload ) : null,
            'created_at'   => current_time( 'mysql' ),
        ] );
    }
}
