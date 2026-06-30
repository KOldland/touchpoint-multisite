<?php
/**
 * Migration: Add registry_id column to khm_press_releases table
 * File: app/public/migrations/20260701_add_registry_id_to_press_releases.php
 *
 * Enables Quote Club press releases to be tracked in the centralized
 * content registry for network-wide content tracking and gap analysis.
 */

defined('ABSPATH') || exit;

function run_press_releases_registry_id_migration() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'khm_press_releases';

    // Check if column already exists
    $column_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
               AND TABLE_NAME = %s 
               AND COLUMN_NAME = 'registry_id'",
            DB_NAME,
            $table_name
        )
    );

    if ($column_exists) {
        return; // Already migrated
    }

    // Add registry_id column
    $wpdb->query(
        "ALTER TABLE `{$table_name}` 
         ADD COLUMN `registry_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `id`,
         ADD INDEX `idx_registry_id` (`registry_id`)"
    );
}

// Execute migration
run_press_releases_registry_id_migration();