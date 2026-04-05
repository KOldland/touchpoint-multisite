<?php

namespace KHM\Migrations;

class AddCommentaryDraftColumns {

    public static function run(): void {
        global $wpdb;
        $table   = $wpdb->prefix . 'khm_sponsor_commentary';
        $charset = $wpdb->get_charset_collate();

        // Add 'draft' to the status enum if not already present.
        $col = $wpdb->get_row("SHOW COLUMNS FROM `{$table}` LIKE 'status'");
        if ($col && strpos((string) $col->Type, 'draft') === false) {
            $wpdb->query(
                "ALTER TABLE `{$table}`
                 MODIFY `status` ENUM(
                     'draft',
                     'pending_editorial',
                     'approved',
                     'rejected',
                     'published'
                 ) NOT NULL DEFAULT 'pending_editorial'"
            );
        }

        // Add rejection_reason column.
        if (!self::column_exists($table, 'rejection_reason')) {
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `rejection_reason` TEXT NULL AFTER `status`");
        }

        // Add draft_token for secure share links.
        if (!self::column_exists($table, 'draft_token')) {
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `draft_token` VARCHAR(64) NULL UNIQUE AFTER `rejection_reason`");
        }

        // Add submitted_at — set when draft is confirmed/submitted.
        if (!self::column_exists($table, 'submitted_at')) {
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `submitted_at` DATETIME NULL AFTER `draft_token`");
        }

        error_log('[KHM QC] AddCommentaryDraftColumns migration complete.');
    }

    private static function column_exists(string $table, string $column): bool {
        global $wpdb;
        $result = $wpdb->get_results(
            $wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column)
        );
        return !empty($result);
    }
}
