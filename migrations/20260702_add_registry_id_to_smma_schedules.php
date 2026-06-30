<?php
/**
 * Migration: Add registry_id tracking to SMMA schedules
 * File: app/public/migrations/20260702_add_registry_id_to_smma_schedules.php
 *
 * Enables SMMA social schedules to be tracked in the centralized
 * content registry for network-wide campaign visibility and
 * cross-network attribution tracking.
 */

defined('ABSPATH') || exit;

function run_smma_schedules_registry_id_migration() {
    global $wpdb;

    // SMMA schedules are stored in postmeta under the 'kh_smma_schedule' CPT.
    // This migration ensures the postmeta key exists across the network so
    // that ScheduleQueueProcessor and RestController can persist registry_id.
    //
    // No schema change needed — we use postmeta (meta_key: '_kh_smma_registry_id')
    // just like the Quote Club used a dedicated column on khm_press_releases.
    //
    // We seed an option to track that the migration has been applied.

    $migration_flag = 'khm_smma_registry_id_migration_applied';

    if (get_site_option($migration_flag)) {
        return; // Already applied
    }

    update_site_option($migration_flag, gmdate('Y-m-d H:i:s'));
}

// Execute migration
run_smma_schedules_registry_id_migration();