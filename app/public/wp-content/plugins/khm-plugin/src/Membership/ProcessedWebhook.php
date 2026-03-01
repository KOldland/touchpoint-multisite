<?php

namespace KHM\Membership;

/**
 * Tracks Stripe webhook processing state for idempotency and operations.
 */
class ProcessedWebhook {
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED  = 'processed';
    public const STATUS_FAILED     = 'failed';
    private const DEFAULT_RETENTION_DAYS = 30;
    private const DEFAULT_PAYLOAD_MODE = 'excerpt';

    /**
     * Ensure processed-webhooks table exists.
     */
    public static function maybe_create_table(): void {
        global $wpdb;

        $table = self::table_name();
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            event_id varchar(255) NOT NULL,
            event_type varchar(128) NOT NULL,
            status varchar(16) NOT NULL DEFAULT 'processing',
            payload longtext NULL,
            payload_hash char(64) NULL,
            attempts int unsigned NOT NULL DEFAULT 1,
            notes text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            processed_at datetime NULL,
            PRIMARY KEY  (event_id),
            KEY idx_khm_processed_status (status),
            KEY idx_khm_processed_type (event_type)
        ) {$charset_collate};";
        $wpdb->query( $sql );
    }

    public static function maybe_schedule_cleanup(): void {
        if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
            return;
        }

        if ( ! wp_next_scheduled( 'khm_membership_webhook_cleanup' ) ) {
            wp_schedule_event( time() + 3600, 'daily', 'khm_membership_webhook_cleanup' );
        }
    }

    public static function cleanup_old_events(): void {
        self::maybe_create_table();
        global $wpdb;

        $retention_default = self::DEFAULT_RETENTION_DAYS;
        if ( defined( 'KHM_MEMBERSHIP_WEBHOOK_RETENTION_DAYS' ) ) {
            $retention_default = (int) KHM_MEMBERSHIP_WEBHOOK_RETENTION_DAYS;
        }

        $days = (int) apply_filters( 'khm_membership_webhook_retention_days', $retention_default );
        $days = max( 1, $days );
        $day_seconds = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * $day_seconds ) );
        $table = self::table_name();

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $cutoff
            )
        );
    }

    /**
     * Claim a Stripe event for processing.
     *
     * @return string claimed|processed|processing|failed
     */
    public static function claim_event( string $event_id, string $event_type, string $payload ): string {
        self::maybe_create_table();
        global $wpdb;

        $table = self::table_name();
        $inserted = $wpdb->insert(
            $table,
            [
                'event_id'     => $event_id,
                'event_type'   => $event_type,
                'status'       => self::STATUS_PROCESSING,
                'payload'      => self::build_payload( $payload ),
                'payload_hash' => hash( 'sha256', $payload ),
                'attempts'     => 1,
                'created_at'   => current_time( 'mysql', 1 ),
                'updated_at'   => current_time( 'mysql', 1 ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
        );

        if ( false !== $inserted ) {
            return 'claimed';
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT status FROM {$table} WHERE event_id = %s LIMIT 1",
                $event_id
            ),
            ARRAY_A
        );

        if ( empty( $row['status'] ) ) {
            return 'processing';
        }

        return (string) $row['status'];
    }

    public static function mark_processed( string $event_id, string $notes = '' ): void {
        self::set_status( $event_id, self::STATUS_PROCESSED, $notes, true );
    }

    public static function mark_failed( string $event_id, string $notes = '' ): void {
        self::set_status( $event_id, self::STATUS_FAILED, $notes, false );
    }

    public static function mark_processing( string $event_id, string $notes = '' ): void {
        self::set_status( $event_id, self::STATUS_PROCESSING, $notes, false, true );
    }

    public static function get_event( string $event_id ): ?array {
        self::maybe_create_table();
        global $wpdb;

        $table = self::table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE event_id = %s LIMIT 1", $event_id ),
            ARRAY_A
        );

        return is_array( $row ) ? $row : null;
    }

    public static function list_events( int $limit = 50 ): array {
        self::maybe_create_table();
        global $wpdb;

        $table = self::table_name();
        $limit = max( 1, min( 500, $limit ) );
        $sql = $wpdb->prepare(
            "SELECT event_id, event_type, status, attempts, created_at, updated_at, processed_at, notes
             FROM {$table}
             ORDER BY created_at DESC
             LIMIT %d",
            $limit
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );
        return is_array( $rows ) ? $rows : [];
    }

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'khm_processed_webhooks';
    }

    private static function set_status(
        string $event_id,
        string $status,
        string $notes,
        bool $set_processed_at = false,
        bool $increment_attempts = false
    ): void {
        self::maybe_create_table();
        global $wpdb;

        $table = self::table_name();
        $data = [
            'status'     => $status,
            'notes'      => self::truncate_notes( $notes ),
            'updated_at' => current_time( 'mysql', 1 ),
        ];
        $format = [ '%s', '%s', '%s' ];

        if ( $set_processed_at ) {
            $data['processed_at'] = current_time( 'mysql', 1 );
            $format[] = '%s';
        }

        if ( $increment_attempts ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET attempts = attempts + 1 WHERE event_id = %s",
                    $event_id
                )
            );
        }

        $wpdb->update(
            $table,
            $data,
            [ 'event_id' => $event_id ],
            $format,
            [ '%s' ]
        );
    }

    private static function build_payload( string $payload ): string {
        $payload_mode_default = self::DEFAULT_PAYLOAD_MODE;
        if ( defined( 'KHM_MEMBERSHIP_WEBHOOK_PAYLOAD_MODE' ) ) {
            $payload_mode_default = (string) KHM_MEMBERSHIP_WEBHOOK_PAYLOAD_MODE;
        }

        $mode = (string) apply_filters( 'khm_membership_webhook_payload_mode', $payload_mode_default );
        $hash = hash( 'sha256', $payload );

        if ( 'hash' === $mode ) {
            return '';
        }

        $payload = self::redact_payload( $payload );
        $payload = trim( $payload );

        $max_len = ( 'full' === $mode ) ? 200000 : 4000;
        if ( strlen( $payload ) <= $max_len ) {
            return $payload;
        }

        return substr( $payload, 0, $max_len ) . "\n/* truncated payload sha256: {$hash} */";
    }

    private static function redact_payload( string $payload ): string {
        // Basic PII redaction for storage in audit logs.
        $patterns = [
            '/"email"\s*:\s*"[^"]*"/i' => '"email":"[REDACTED]"',
            '/"phone"\s*:\s*"[^"]*"/i' => '"phone":"[REDACTED]"',
            '/"mobile"\s*:\s*"[^"]*"/i' => '"mobile":"[REDACTED]"',
            '/"line1"\s*:\s*"[^"]*"/i' => '"line1":"[REDACTED]"',
            '/"line2"\s*:\s*"[^"]*"/i' => '"line2":"[REDACTED]"',
            '/"postal_code"\s*:\s*"[^"]*"/i' => '"postal_code":"[REDACTED]"',
        ];

        return (string) preg_replace( array_keys( $patterns ), array_values( $patterns ), $payload );
    }

    private static function truncate_notes( string $notes ): string {
        $notes = trim( $notes );
        if ( strlen( $notes ) <= 65535 ) {
            return $notes;
        }

        return substr( $notes, 0, 65535 );
    }
}
