<?php
/**
 * Search Log Schema
 *
 * DDL for the kh_search_log table that tracks search queries,
 * answer synthesis success/failure, and rate-limit hits.
 *
 * @package KH\Editorial\Database
 */

namespace KH\Editorial\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchLogSchema
 */
class SearchLogSchema {

    /**
     * Install the search log table.
     *
     * @return void
     */
    public static function install(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'kh_search_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            query VARCHAR(500) NOT NULL,
            source VARCHAR(50) NOT NULL DEFAULT 'public',
            blog_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            result_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
            latency_ms INT(10) UNSIGNED NOT NULL DEFAULT 0,
            answer_synthesized TINYINT(1) NOT NULL DEFAULT 0,
            answer_success TINYINT(1) NOT NULL DEFAULT 0,
            rate_limited TINYINT(1) NOT NULL DEFAULT 0,
            user_ip VARCHAR(45) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_created_at (created_at),
            KEY idx_source (source),
            KEY idx_rate_limited (rate_limited),
            KEY idx_answer_success (answer_success)
        ) {$charset_collate} ENGINE=InnoDB;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}