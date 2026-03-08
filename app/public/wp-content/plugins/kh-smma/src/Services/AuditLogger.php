<?php
namespace KH_SMMA\Services;

use wpdb;

use function current_time;
use function get_current_user_id;
use function maybe_serialize;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AuditLogger {
    /** @var wpdb */
    private $db;

    /** @var string */
    private $table;

    public function __construct( wpdb $db ) {
        $this->db    = $db;
        $this->table = $this->db->prefix . 'kh_smma_audit_log';
    }

    public function install() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $this->db->get_charset_collate();
        $sql             = "CREATE TABLE {$this->table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            action varchar(100) NOT NULL,
            object_type varchar(50) DEFAULT '',
            object_id bigint(20) unsigned DEFAULT 0,
            details longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY action (action),
            KEY object_type (object_type)
        ) {$charset_collate};";

        \dbDelta( $sql );
    }

    public function log( $action, array $context = array() ) {
        $data = array(
            'user_id'    => isset( $context['user_id'] ) ? (int) $context['user_id'] : get_current_user_id(),
            'action'     => $action,
            'object_type'=> $context['object_type'] ?? '',
            'object_id'  => isset( $context['object_id'] ) ? (int) $context['object_id'] : 0,
            'details'    => maybe_serialize( $context['details'] ?? array() ),
            'created_at' => current_time( 'mysql' ),
        );

        $this->db->insert( $this->table, $data );
    }

    public function log_generate_request( int $post_id, int $user_id, string $prompt_hash, array $details = array() ) {
        $this->log( 'smma_generate_request', array(
            'user_id'     => $user_id,
            'object_type' => 'post',
            'object_id'   => $post_id,
            'details'     => array_merge( $details, array(
                'prompt_hash' => $prompt_hash,
            ) ),
        ) );
    }

    public function log_generate_response( string $response_hash, array $variant_ids = array(), array $details = array() ) {
        $this->log( 'smma_generate_response', array(
            'details' => array_merge( $details, array(
                'response_hash' => $response_hash,
                'variant_ids'   => array_values( $variant_ids ),
            ) ),
        ) );
    }

    /**
     * Persist an event to the audit log.
     *
     * Supports two signatures:
     * - Compliance: record_event(event_name, payload)
     * - Telemetry: record_event(trace_id, event_name, timestamp, payload)
     *
     * @param mixed ...$args Variable arguments based on signature.
     */
    public function record_event( ...$args ): void {
        // Handle compliance signature: record_event(event_name, payload)
        if ( count( $args ) === 2 && is_string( $args[0] ) && is_array( $args[1] ) ) {
            $event_name = $args[0];
            $payload = $args[1];
            
            $context = array(
                'user_id'     => isset( $payload['user_id'] ) ? (int) $payload['user_id'] : get_current_user_id(),
                'object_type' => (string) ( $payload['object_type'] ?? 'compliance_rule' ),
                'object_id'   => isset( $payload['object_id'] ) ? (int) $payload['object_id'] : 0,
                'details'     => $payload,
            );
            
            $this->log( $event_name, $context );
            return;
        }
        
        // Handle telemetry signature: record_event(trace_id, event_name, timestamp, payload)
        if ( count( $args ) === 4 ) {
            list( $trace_id, $event_name, $timestamp, $payload ) = $args;
            
            $this->log( 'telemetry_event', array(
                'object_type' => 'telemetry',
                'details'     => array(
                    'trace_id'   => $trace_id,
                    'event_name' => $event_name,
                    'timestamp'  => $timestamp,
                    'payload'    => $payload,
                ),
            ) );
            return;
        }
        
        // Fallback for unexpected arguments
        error_log( 'AuditLogger::record_event called with unexpected arguments: ' . wp_json_encode( $args ) );
    }

    /**
     * Return the most recent telemetry_event audit rows decoded for display.
     *
     * @param int $limit Maximum rows (1–100, default 10).
     * @return array  Rows with ->decoded_details populated.
     */
    public function get_recent_telemetry_events( int $limit = 10 ): array {
        $limit = max( 1, min( 100, $limit ) );
        $rows  = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE action = 'telemetry_event' ORDER BY id DESC LIMIT %d",
                $limit
            )
        );

        if ( empty( $rows ) ) {
            return array();
        }

        foreach ( $rows as $row ) {
            $row->decoded_details = maybe_unserialize( $row->details );
        }

        return $rows;
    }

    /**
     * Return all telemetry_event rows for a trace_id, ordered ascending so
     * the workflow can be read top-to-bottom.
     *
     * @param string $trace_id UUID v4 correlation identifier.
     * @return array  Decoded event rows.
     */
    public function get_events_by_trace( string $trace_id ): array {
        if ( '' === $trace_id ) {
            return array();
        }

        $safe_like = '%' . $this->db->esc_like( $trace_id ) . '%';
        $rows      = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table}
                  WHERE action = 'telemetry_event'
                    AND details LIKE %s
                  ORDER BY id ASC",
                $safe_like
            )
        );

        if ( empty( $rows ) ) {
            return array();
        }

        $events = array();
        foreach ( $rows as $row ) {
            $decoded = maybe_unserialize( $row->details );
            if ( ! is_array( $decoded ) ) {
                continue;
            }
            // Guard: only include rows whose trace_id exactly matches.
            if ( ( $decoded['trace_id'] ?? '' ) !== $trace_id ) {
                continue;
            }
            $row->decoded_details = $decoded;
            $events[]             = $row;
        }

        return $events;
    }
}
