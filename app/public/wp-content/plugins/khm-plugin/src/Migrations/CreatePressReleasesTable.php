<?php

namespace KHM\Migrations;

class CreatePressReleasesTable {

    public static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'khm_press_releases';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            error_log('[KHM QC] Press releases table already exists.');
            return;
        }

        $sql = "CREATE TABLE `{$table}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `sponsor_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `commentary_id` BIGINT UNSIGNED NULL,
            `title` VARCHAR(255) NOT NULL,
            `content` LONGTEXT NOT NULL,
            `excerpt` TEXT NULL,
            `status` ENUM('draft', 'submitted', 'published', 'rejected') NOT NULL DEFAULT 'draft',
            `rejection_reason` TEXT NULL,
            `submission_date` DATETIME NULL,
            `published_date` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `sponsor_id` (`sponsor_id`),
            KEY `user_id` (`user_id`),
            KEY `status` (`status`),
            KEY `created_at` (`created_at`)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        error_log('[KHM QC] Press releases table created.');
    }

    public static function drop_tables(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        error_log('[KHM QC] Press releases table dropped.');
    }
}
