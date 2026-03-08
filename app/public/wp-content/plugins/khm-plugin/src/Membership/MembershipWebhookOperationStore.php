<?php

namespace KHM\Membership;

class MembershipWebhookOperationStore {
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED  = 'succeeded';
    public const STATUS_FAILED     = 'failed';

    public static function maybe_create_table(): void {
        global $wpdb;
        $table = self::table_name();
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            operation_key varchar(255) NOT NULL,
            event_id varchar(255) NOT NULL,
            event_type varchar(128) NOT NULL,
            object_id varchar(255) NULL,
            user_id bigint unsigned NULL,
            status varchar(16) NOT NULL DEFAULT 'processing',
            attempts int unsigned NOT NULL DEFAULT 1,
            last_error text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            PRIMARY KEY (operation_key),
            KEY idx_khm_wh_op_status (status),
            KEY idx_khm_wh_op_event (event_id),
            KEY idx_khm_wh_op_user (user_id)
        ) {$charset_collate};";
        $wpdb->query( $sql );
    }

    /**
     * Claim operation.
     *
     * @return string claimed|duplicate|busy
     */
    public static function claim( string $operation_key, string $event_id, string $event_type, ?string $object_id = null, ?int $user_id = null ): string {
        self::maybe_create_table();
        global $wpdb;
        $table = self::table_name();

        $inserted = $wpdb->insert(
            $table,
            [
                'operation_key' => $operation_key,
                'event_id' => $event_id,
                'event_type' => $event_type,
                'object_id' => $object_id,
                'user_id' => $user_id > 0 ? $user_id : null,
                'status' => self::STATUS_PROCESSING,
                'attempts' => 1,
                'created_at' => current_time( 'mysql', 1 ),
                'updated_at' => current_time( 'mysql', 1 ),
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' ]
        );
        if ( false !== $inserted ) {
            return 'claimed';
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT status, attempts FROM {$table} WHERE operation_key = %s LIMIT 1",
                $operation_key
            ),
            ARRAY_A
        );

        if ( ! is_array( $row ) ) {
            return 'busy';
        }

        $status = (string) ( $row['status'] ?? '' );
        if ( self::STATUS_SUCCEEDED === $status ) {
            return 'duplicate';
        }

        if ( self::STATUS_FAILED === $status ) {
            $wpdb->update(
                $table,
                [
                    'status' => self::STATUS_PROCESSING,
                    'attempts' => (int) ( $row['attempts'] ?? 1 ) + 1,
                    'updated_at' => current_time( 'mysql', 1 ),
                ],
                [ 'operation_key' => $operation_key ],
                [ '%s', '%d', '%s' ],
                [ '%s' ]
            );
            return 'claimed';
        }

        return 'busy';
    }

    public static function mark_succeeded( string $operation_key ): void {
        self::set_status( $operation_key, self::STATUS_SUCCEEDED, '' );
    }

    public static function mark_failed( string $operation_key, string $error ): void {
        self::set_status( $operation_key, self::STATUS_FAILED, $error );
    }

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'khm_membership_webhook_operations';
    }

    private static function set_status( string $operation_key, string $status, string $error ): void {
        self::maybe_create_table();
        global $wpdb;
        $table = self::table_name();

        $wpdb->update(
            $table,
            [
                'status' => $status,
                'last_error' => $error !== '' ? substr( $error, 0, 2000 ) : null,
                'updated_at' => current_time( 'mysql', 1 ),
                'completed_at' => self::STATUS_SUCCEEDED === $status ? current_time( 'mysql', 1 ) : null,
            ],
            [ 'operation_key' => $operation_key ],
            [ '%s', '%s', '%s', '%s' ],
            [ '%s' ]
        );
    }
}

