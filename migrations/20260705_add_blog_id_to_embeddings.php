<?php
/**
 * Migration Script: Add blog_id column to atomic_embeddings table
 *
 * Enables multisite-scoped RAG search by filtering embeddings by blog_id.
 * Existing rows are backfilled with the current site's blog_id.
 *
 * File: app/public/migrations/20260705_add_blog_id_to_embeddings.php
 * Phase: 5 (Search API)
 */

defined('ABSPATH') || exit;

function run_blog_id_to_embeddings_migration() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'atomic_embeddings';

    // Check if blog_id column already exists
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            'blog_id'
        )
    );

    if (empty($column_exists)) {
        // Add blog_id column, default to 0
        $result = $wpdb->query(
            "ALTER TABLE {$table_name} ADD COLUMN blog_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER post_id, ADD INDEX idx_blog_id (blog_id)"
        );

        if ($result === false) {
            error_log('[KH Migration] Failed to add blog_id to atomic_embeddings: ' . $wpdb->last_error);
            return;
        }

        // Backfill existing rows with current blog_id
        $current_blog_id = get_current_blog_id();
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_name} SET blog_id = %d WHERE blog_id = 0",
                $current_blog_id
            )
        );
    }
}

// Execute migration
run_blog_id_to_embeddings_migration();