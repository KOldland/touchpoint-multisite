<?php

namespace KHM\Membership;

/**
 * Tracks Stripe webhook processing state for idempotency and operations.
 */
class ProcessedWebhook {
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED  = 'processed';
    public const STATUS_FAILED     = 'failed';

    /**
     * Ensure processed-webhooks table exists.
     */
    public static function maybe_create_table(): void {
        global $wpdb;

        $table = self::table_name();
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
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
        dbDelta( $sql );
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
        $payload = trim( $payload );
        if ( strlen( $payload ) <= 200000 ) {
            return $payload;
        }

        return substr( $payload, 0, 200000 );
    }

    private static function truncate_notes( string $notes ): string {
        $notes = trim( $notes );
        if ( strlen( $notes ) <= 65535 ) {
            return $notes;
        }

        return substr( $notes, 0, 65535 );
    }
}
