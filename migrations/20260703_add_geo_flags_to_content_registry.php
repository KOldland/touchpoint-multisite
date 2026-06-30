<?php
/**
 * Migration Script: Add geo_flags JSON column to content_registry
 * File: app/public/migrations/20260703_add_geo_flags_to_content_registry.php
 */

defined('ABSPATH') || exit;

function run_geo_flags_migration() {
    global $wpdb;

    $table_name = $wpdb->base_prefix . 'content_registry';

    // Check if column already exists
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            'geo_flags'
        )
    );

    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN geo_flags JSON NULL AFTER smma_flags");
    }
}

// Execute migration
run_geo_flags_migration();