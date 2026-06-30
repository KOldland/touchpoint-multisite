<?php
/**
 * Migration Script: Add FULLTEXT index to content_registry table
 *
 * The content_registry search_articles() method uses MATCH...AGAINST
 * but the table was created without a FULLTEXT index. This migration
 * adds one so fulltext queries don't fail.
 *
 * File: app/public/migrations/20260704_add_fulltext_indexes.php
 * Phase: 5 (Search API)
 */

defined('ABSPATH') || exit;

function run_fulltext_indexes_migration() {
    global $wpdb;

    $table_name = $wpdb->base_prefix . 'content_registry';

    // Check if FULLTEXT index already exists
    $indexes = $wpdb->get_results(
        $wpdb->prepare(
            "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
            'idx_fulltext_search'
        )
    );

    if (empty($indexes)) {
        $result = $wpdb->query(
            "ALTER TABLE {$table_name} ADD FULLTEXT INDEX idx_fulltext_search (title, content_body, excerpt)"
        );

        if ($result === false) {
            error_log('[KH Migration] Failed to add FULLTEXT index to content_registry: ' . $wpdb->last_error);
        }
    }
}

// Execute migration
run_fulltext_indexes_migration();