<?php

namespace KHM\Migrations;

class AddCommentaryIsPressRelease {

    public static function run(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_sponsor_commentary';

        if (!self::column_exists($table, 'is_press_release')) {
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `is_press_release` TINYINT(1) NOT NULL DEFAULT 0 AFTER `credits_used`");
        }

        error_log('[KHM QC] AddCommentaryIsPressRelease migration complete.');
    }

    private static function column_exists(string $table, string $column): bool {
        global $wpdb;
        return !empty($wpdb->get_results(
            $wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column)
        ));
    }
}
