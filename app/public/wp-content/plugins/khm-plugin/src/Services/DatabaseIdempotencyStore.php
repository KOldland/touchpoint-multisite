<?php
/**
 * Database Idempotency Store
 *
 * Tracks processed webhook events in WordPress database to prevent duplicate processing.
 *
 * @package KHM\Services
 */

namespace KHM\Services;

use KHM\Contracts\IdempotencyStoreInterface;

class DatabaseIdempotencyStore implements IdempotencyStoreInterface {

    private string $tableName;

    public function __construct() {
        global $wpdb;
        $this->tableName = $wpdb->prefix . 'khm_webhook_events';
    }

    /**
     * Check if an event has already been processed.
     */
    public function hasProcessed( string $eventId ): bool {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a known safe, plugin-owned table string.
                "SELECT COUNT(*) FROM {$this->tableName} WHERE event_id = %s",
                $eventId
            )
        );

        return (int) $count > 0;
    }

    /**
     * Mark an event as processed.
     */
    public function markProcessed( string $eventId, string $gateway, array $metadata = [] ): void {
        global $wpdb;

        // Use INSERT IGNORE to avoid duplicate-key fatals under concurrent delivery.
        $result = $wpdb->query(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is controlled.
                "INSERT IGNORE INTO {$this->tableName} (event_id, gateway, metadata, processed_at) VALUES ( %s, %s, %s, %s )",
                $eventId,
                $gateway,
                wp_json_encode( $metadata ),
                current_time( 'mysql', true )
            )
        );

        if ( false === $result ) {
            // Log and continue; duplicate key should already be ignored.
            error_log( 'Idempotency insert failed: ' . $wpdb->last_error );
        }
    }

    /**
     * Retrieve details of a processed event.
     */
    public function getProcessedEvent( string $eventId ): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a known safe, plugin-owned table string.
                "SELECT * FROM {$this->tableName} WHERE event_id = %s LIMIT 1",
                $eventId
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        // Decode metadata JSON
        $row['metadata'] = json_decode($row['metadata'], true) ?? [];

        return $row;
    }

    /**
     * Clean up old processed event records.
     */
    public function cleanup( int $daysOld = 90 ): int {
        global $wpdb;

        $cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a known safe, plugin-owned table string.
                "DELETE FROM {$this->tableName} WHERE processed_at < %s",
                $cutoffDate
            )
        );

        return (int) $deleted;
    }

    /**
     * Create the webhook events table.
     *
     * Should be called during plugin activation.
     */
    public static function createTable(): void {
        global $wpdb;
        $tableName = $wpdb->prefix . 'khm_webhook_events';
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id varchar(255) NOT NULL,
            gateway varchar(50) NOT NULL,
            metadata text,
            processed_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_id (event_id),
            KEY gateway (gateway),
            KEY processed_at (processed_at)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
