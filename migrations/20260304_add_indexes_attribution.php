<?php
declare(strict_types=1);

/**
 * MEM-09 index hardening migration.
 *
 * Usage:
 *   wp eval-file migrations/20260304_add_indexes_attribution.php
 */

if ( ! isset( $GLOBALS['wpdb'] ) || ! $GLOBALS['wpdb'] instanceof wpdb ) {
    fwrite( STDERR, "This migration must run inside WordPress (wp eval-file).\n" );
    exit( 1 );
}

$wpdb = $GLOBALS['wpdb'];

$tables = [
    $wpdb->prefix . 'promotion_attribution' => [
        'idx_promotion_schedule_created' => 'schedule_id, created_at',
        'idx_promotion_sponsor_created' => 'sponsor_id, created_at',
        'idx_promotion_created_id' => 'created_at, id',
        'idx_promotion_retention_cursor' => 'anonymized_at, legal_hold_until, id',
    ],
    $wpdb->prefix . 'khm_processed_webhook_events' => [
        'idx_khm_processed_events_status_created' => 'status, created_at',
        'idx_khm_processed_events_type_created' => 'event_type, created_at',
    ],
    $wpdb->prefix . 'khm_processed_webhooks' => [
        'idx_khm_processed_status_created' => 'status, created_at',
        'idx_khm_processed_type_created' => 'event_type, created_at',
    ],
    $wpdb->prefix . 'khm_webhook_dead_letter' => [
        'idx_khm_dead_status_updated' => 'status, updated_at',
    ],
    $wpdb->prefix . 'khm_email_queue' => [
        'idx_khm_email_queue_status_schedule_priority' => 'status, scheduled_at, priority',
        'idx_khm_email_queue_status_updated' => 'status, updated_at',
    ],
    $wpdb->prefix . 'khm_email_logs' => [
        'idx_khm_email_logs_status_created' => 'status, created_at',
    ],
    $wpdb->prefix . 'kh_paid_reconciliations' => [
        'idx_paid_reconciliations_status_created' => 'status, created_at',
        'idx_paid_reconciliations_sponsor_created' => 'sponsor_id, created_at',
        'idx_paid_reconciliations_discrepancy' => 'discrepancy_percent, created_at',
    ],
];

$created = [];
$skipped = [];

foreach ( $tables as $table => $indexes ) {
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $exists !== $table ) {
        $skipped[] = "{$table} (missing table)";
        continue;
    }

    foreach ( $indexes as $name => $columns ) {
        $has = $wpdb->get_var(
            $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", $name )
        );

        if ( $has ) {
            $skipped[] = "{$table}.{$name} (exists)";
            continue;
        }

        $sql = "CREATE INDEX {$name} ON {$table} ({$columns})";
        $result = $wpdb->query( $sql );
        if ( false === $result ) {
            fwrite( STDERR, "Failed: {$sql}\n" );
            exit( 1 );
        }

        $created[] = "{$table}.{$name}";
    }
}

fwrite( STDOUT, "MEM-09 index migration complete.\n" );
fwrite( STDOUT, "Created indexes: " . count( $created ) . "\n" );
foreach ( $created as $line ) {
    fwrite( STDOUT, " + {$line}\n" );
}

fwrite( STDOUT, "Skipped: " . count( $skipped ) . "\n" );
foreach ( $skipped as $line ) {
    fwrite( STDOUT, " - {$line}\n" );
}
