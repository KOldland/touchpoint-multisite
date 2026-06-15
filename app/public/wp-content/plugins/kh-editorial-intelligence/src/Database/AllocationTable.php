<?php

namespace KH\Editorial\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Creates and manages the wp_kh_content_allocation table
 * for tracking cross-site post clones.
 */
class AllocationTable {

    const TABLE_NAME = 'kh_content_allocation';

    /**
     * Create or update the allocation tracking table.
     */
    public static function install(): void {
        global $wpdb;

        $table = $wpdb->base_prefix . self::TABLE_NAME; // base_prefix = network-wide
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            origin_blog_id  BIGINT UNSIGNED NOT NULL DEFAULT 1,
            origin_post_id  BIGINT UNSIGNED NOT NULL,
            target_blog_id  BIGINT UNSIGNED NOT NULL,
            target_post_id  BIGINT UNSIGNED NOT NULL,
            rewrite_applied TINYINT(1) NOT NULL DEFAULT 0,
            allocated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY allocation_unique (origin_blog_id, origin_post_id, target_blog_id),
            KEY origin_lookup (origin_blog_id, origin_post_id),
            KEY target_lookup (target_blog_id, target_post_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Insert an allocation record.
     *
     * @param int  $origin_blog_id
     * @param int  $origin_post_id
     * @param int  $target_blog_id
     * @param int  $target_post_id
     * @param bool $rewrite_applied
     * @return int|false The row ID, or false on error.
     */
    public static function record( int $origin_blog_id, int $origin_post_id, int $target_blog_id, int $target_post_id, bool $rewrite_applied ): int|false {
        global $wpdb;
        $table = $wpdb->base_prefix . self::TABLE_NAME;

        $result = $wpdb->insert(
            $table,
            [
                'origin_blog_id'  => $origin_blog_id,
                'origin_post_id'  => $origin_post_id,
                'target_blog_id'  => $target_blog_id,
                'target_post_id'  => $target_post_id,
                'rewrite_applied' => $rewrite_applied ? 1 : 0,
                'allocated_at'    => current_time( 'mysql', true ),
            ],
            [ '%d', '%d', '%d', '%d', '%d', '%s' ]
        );

        if ( $result === false ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get all allocations for an origin post.
     *
     * @param int $origin_post_id
     * @param int $origin_blog_id
     * @return array Array of rows keyed by target_blog_id.
     */
    public static function get_for_post( int $origin_post_id, int $origin_blog_id = 1 ): array {
        global $wpdb;
        $table = $wpdb->base_prefix . self::TABLE_NAME;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE origin_blog_id = %d AND origin_post_id = %d ORDER BY allocated_at DESC",
                $origin_blog_id,
                $origin_post_id
            ),
            ARRAY_A
        );

        $by_target = [];
        foreach ( (array) $rows as $row ) {
            $by_target[ (int) $row['target_blog_id'] ] = $row;
        }

        return $by_target;
    }

    /**
     * Check if a specific allocation already exists.
     *
     * @param int $origin_post_id
     * @param int $target_blog_id
     * @param int $origin_blog_id
     * @return array|null The allocation row, or null if not found.
     */
    public static function find( int $origin_post_id, int $target_blog_id, int $origin_blog_id = 1 ): ?array {
        global $wpdb;
        $table = $wpdb->base_prefix . self::TABLE_NAME;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE origin_blog_id = %d AND origin_post_id = %d AND target_blog_id = %d",
                $origin_blog_id,
                $origin_post_id,
                $target_blog_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Get all distributed posts grouped by origin post.
     *
     * @param int $origin_blog_id
     * @return array Array keyed by origin_post_id, each value is an array of allocation rows.
     */
    public static function get_all_distributed( int $origin_blog_id = 1 ): array {
        global $wpdb;
        $table = $wpdb->base_prefix . self::TABLE_NAME;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE origin_blog_id = %d ORDER BY allocated_at DESC",
                $origin_blog_id
            ),
            ARRAY_A
        );

        $grouped = [];
        foreach ( (array) $rows as $row ) {
            $post_id = (int) $row['origin_post_id'];
            $grouped[ $post_id ][] = $row;
        }

        return $grouped;
    }

    /**
     * Delete allocation records for an origin post across all targets.
     *
     * @param int $origin_post_id
     * @param int $origin_blog_id
     * @return int Number of rows deleted.
     */
    public static function delete_for_post( int $origin_post_id, int $origin_blog_id = 1 ): int {
        global $wpdb;
        $table = $wpdb->base_prefix . self::TABLE_NAME;

        return $wpdb->delete(
            $table,
            [
                'origin_blog_id' => $origin_blog_id,
                'origin_post_id' => $origin_post_id,
            ],
            [ '%d', '%d' ]
        );
    }
}