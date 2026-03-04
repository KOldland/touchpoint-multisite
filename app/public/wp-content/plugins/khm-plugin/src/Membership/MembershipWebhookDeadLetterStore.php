<?php

namespace KHM\Membership;

class MembershipWebhookDeadLetterStore {
    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'khm_webhook_dead_letter';
    }

    public static function maybe_create_table(): void {
        global $wpdb;
        $table = self::table_name();
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            event_id varchar(255) NOT NULL,
            event_type varchar(128) NOT NULL,
            payload longtext NULL,
            payload_hash char(64) NULL,
            reason varchar(64) NOT NULL,
            error_message text NULL,
            status varchar(16) NOT NULL DEFAULT 'open',
            attempts int unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_khm_dead_event_reason (event_id, reason),
            KEY idx_khm_dead_status (status),
            KEY idx_khm_dead_type (event_type)
        ) {$charset_collate};";
        $wpdb->query( $sql );
    }

    public static function store( string $event_id, string $event_type, string $payload, string $reason, string $error_message = '' ): bool {
        self::maybe_create_table();
        global $wpdb;

        $event_id = sanitize_text_field( $event_id );
        $event_type = sanitize_text_field( $event_type );
        $reason = sanitize_key( $reason );
        if ( '' === $event_id || '' === $event_type || '' === $reason ) {
            return false;
        }

        $table = self::table_name();
        $exists = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, attempts FROM {$table} WHERE event_id = %s AND reason = %s LIMIT 1",
                $event_id,
                $reason
            ),
            ARRAY_A
        );

        $record = [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'payload' => self::build_payload( $payload ),
            'payload_hash' => hash( 'sha256', $payload ),
            'reason' => $reason,
            'error_message' => $error_message !== '' ? substr( $error_message, 0, 2000 ) : null,
            'status' => 'open',
            'updated_at' => current_time( 'mysql', 1 ),
        ];

        if ( is_array( $exists ) && isset( $exists['id'] ) ) {
            $record['attempts'] = (int) ( $exists['attempts'] ?? 1 ) + 1;
            $record['resolved_at'] = null;
            return false !== $wpdb->update(
                $table,
                $record,
                [ 'id' => (int) $exists['id'] ],
                [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ],
                [ '%d' ]
            );
        }

        $record['attempts'] = 1;
        $record['created_at'] = current_time( 'mysql', 1 );

        return false !== $wpdb->insert(
            $table,
            $record,
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
        );
    }

    public static function list_open( int $limit = 50 ): array {
        self::maybe_create_table();
        global $wpdb;

        $table = self::table_name();
        $limit = max( 1, min( 500, $limit ) );
        $sql = $wpdb->prepare(
            "SELECT id, event_id, event_type, reason, attempts, error_message, created_at, updated_at
             FROM {$table}
             WHERE status = %s
             ORDER BY id DESC
             LIMIT %d",
            'open',
            $limit
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );
        return is_array( $rows ) ? $rows : [];
    }

    public static function get_by_id( int $id ): ?array {
        self::maybe_create_table();
        global $wpdb;

        if ( $id <= 0 ) {
            return null;
        }

        $table = self::table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
            ARRAY_A
        );

        return is_array( $row ) ? $row : null;
    }

    public static function mark_resolved( int $id ): bool {
        self::maybe_create_table();
        global $wpdb;

        if ( $id <= 0 ) {
            return false;
        }

        $table = self::table_name();
        return false !== $wpdb->update(
            $table,
            [
                'status' => 'resolved',
                'resolved_at' => current_time( 'mysql', 1 ),
                'updated_at' => current_time( 'mysql', 1 ),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    private static function build_payload( string $payload ): string {
        $payload = trim( $payload );
        if ( strlen( $payload ) <= 4000 ) {
            return $payload;
        }

        $hash = hash( 'sha256', $payload );
        return substr( $payload, 0, 4000 ) . "\n/* truncated payload sha256: {$hash} */";
    }
}
