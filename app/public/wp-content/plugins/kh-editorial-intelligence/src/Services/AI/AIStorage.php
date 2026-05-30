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
     * Check if a user has remaining budget.
     * 
     * @param int $user_id The User ID.
     * @return array Budget details including 'has_budget' boolean.
     */
    public function check_budget( int $user_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_budgets';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT token_limit, token_used FROM $table WHERE scope = 'user' AND scope_id = %s AND reset_at > NOW()",
            $user_id
        ), ARRAY_A );

        if ( ! $row ) {
            return [
                'has_budget' => true, // Assume true if no row exists (default budget will be created on usage)
                'token_limit' => (int) get_option( 'kh_editorial_default_token_limit', 500000 ),
                'token_used' => 0
            ];
        }

        return [
            'has_budget' => ( (int) $row['token_limit'] > (int) $row['token_used'] ),
            'token_limit' => (int) $row['token_limit'],
            'token_used' => (int) $row['token_used']
        ];
    }

    /**
     * Record usage in budget.
     * Uses an UPSERT pattern to ensure user budget rows exist.
     */
    public function update_budget_usage( $user_id, $tokens ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_budgets';

        $tokens = intval( $tokens );
        if ( $tokens <= 0 ) {
            return true;
        }

        // Try to update existing row first
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE $table SET token_used = token_used + %d WHERE scope = 'user' AND scope_id = %s AND reset_at > NOW()",
            $tokens, $user_id
        ) );

        if ( $updated === 0 ) {
            // No row updated, check if it's because it doesn't exist or just no valid period.
            // We insert a default monthly budget for the user if none exists.
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table WHERE scope = 'user' AND scope_id = %s AND reset_at > NOW()",
                $user_id
            ) );

            if ( ! $exists ) {
                return $wpdb->insert( $table, [
                    'scope'       => 'user',
                    'scope_id'    => $user_id,
                    'period'      => 'monthly',
                    'token_limit' => get_option( 'kh_editorial_default_token_limit', 500000 ), // 500k tokens default
                    'token_used'  => $tokens,
                    'reset_at'    => date( 'Y-m-d H:i:s', strtotime( 'first day of next month' ) ),
                ] );
            }
        }

        return $updated !== false;
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
