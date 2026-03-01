<?php

namespace KHM\Membership;

class MembershipWebhookAuditLogger {
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
            operation_key varchar(255) NULL,
            object_id varchar(255) NULL,
            user_id bigint unsigned NULL,
            outcome varchar(32) NOT NULL,
            message text NULL,
            context longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_khm_webhook_audit_event (event_id),
            KEY idx_khm_webhook_audit_type (event_type),
            KEY idx_khm_webhook_audit_user (user_id),
            KEY idx_khm_webhook_audit_outcome (outcome),
            KEY idx_khm_webhook_audit_op (operation_key)
        ) {$charset_collate};";
        $wpdb->query( $sql );
    }

    public static function log(
        string $event_id,
        string $event_type,
        string $outcome,
        ?string $object_id = null,
        ?int $user_id = null,
        string $message = '',
        array $context = [],
        ?string $operation_key = null
    ): void {
        self::maybe_create_table();
        global $wpdb;
        $table = self::table_name();

        $wpdb->insert(
            $table,
            [
                'event_id' => $event_id,
                'event_type' => $event_type,
                'operation_key' => $operation_key,
                'object_id' => $object_id,
                'user_id' => $user_id > 0 ? $user_id : null,
                'outcome' => $outcome,
                'message' => self::truncate( $message, 2000 ),
                'context' => ! empty( $context ) ? wp_json_encode( $context ) : null,
                'created_at' => current_time( 'mysql', 1 ),
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
        );
    }

    public static function operation_succeeded( string $operation_key ): bool {
        if ( '' === $operation_key ) {
            return false;
        }

        self::maybe_create_table();
        global $wpdb;
        $table = self::table_name();

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE operation_key = %s AND outcome = %s ORDER BY id DESC LIMIT 1",
                $operation_key,
                'success'
            )
        );

        return ! empty( $exists );
    }

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'khm_membership_webhook_audit';
    }

    private static function truncate( string $value, int $max ): string {
        if ( strlen( $value ) <= $max ) {
            return $value;
        }
        return substr( $value, 0, $max );
    }
}

