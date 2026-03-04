<?php
/**
 * Migration: Create promotion_attribution table
 *
 * Persists attribution data for membership signups and conversions linked to
 * schedules & sponsors. Supports DSAR, anonymization, and reporting.
 *
 * @package KHM\Migrations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function khm_migrate_20260304_create_promotion_attribution_up() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'promotion_attribution';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        schedule_id BIGINT UNSIGNED NOT NULL,
        sponsor_id BIGINT UNSIGNED NULL,
        user_id BIGINT UNSIGNED NULL,
        user_email VARCHAR(255) NULL,
        utm_source VARCHAR(128) NULL,
        utm_medium VARCHAR(128) NULL,
        utm_campaign VARCHAR(256) NULL,
        utm_term VARCHAR(256) NULL,
        utm_content VARCHAR(256) NULL,
        phase_at_click VARCHAR(64) NULL,
        conversion_type VARCHAR(64) NOT NULL DEFAULT 'signup',
        reference VARCHAR(255) NULL COMMENT 'session_id or idempotency_key',
        reference_metadata TEXT NULL COMMENT 'JSON metadata',
        consent TINYINT(1) NOT NULL DEFAULT 0,
        consent_source VARCHAR(32) NULL,
        consent_given_at DATETIME NULL,
        plan_id INT NULL,
        anonymized_at DATETIME NULL,
        legal_hold_until DATETIME NULL COMMENT 'If set, record is protected from auto-anonymization',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_schedule_id (schedule_id),
        INDEX idx_sponsor_id (sponsor_id),
        INDEX idx_user_id (user_id),
        INDEX idx_user_email (user_email),
        INDEX idx_conversion_type (conversion_type),
        INDEX idx_created_at (created_at),
        INDEX idx_retention (anonymized_at, legal_hold_until, created_at),
        INDEX idx_schedule_sponsor (schedule_id, sponsor_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    return [
        'status' => 'success',
        'message' => "Table {$table_name} created successfully.",
    ];
}

function khm_migrate_20260304_create_promotion_attribution_down() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'promotion_attribution';
    $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

    return [
        'status' => 'success',
        'message' => "Table {$table_name} dropped successfully.",
    ];
}
