<?php
/**
 * Sponsor tables migration.
 *
 * @package KHM\Sponsors
 */

namespace KHM\Sponsors;

defined( 'ABSPATH' ) || exit;

class SponsorMigration {
    const DOCS_TABLE = 'khm_sponsor_docs';
    const AUDIT_TABLE = 'khm_sponsor_audit';
    const SPONSORS_TABLE = 'khm_sponsors';

    public static function docs_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::DOCS_TABLE;
    }

    public static function sponsors_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::SPONSORS_TABLE;
    }

    public static function audit_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::AUDIT_TABLE;
    }

    public static function create_tables(): bool {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $sponsors_table = self::sponsors_table_name();
        $docs_table = self::docs_table_name();
        $audit_table = self::audit_table_name();

        $sponsors_sql = "CREATE TABLE IF NOT EXISTS {$sponsors_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            url VARCHAR(255) NULL,
            contact_email VARCHAR(255) NULL,
            publish_allowed TINYINT(1) DEFAULT 0,
            created_by BIGINT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY publish_allowed (publish_allowed),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $docs_sql = "CREATE TABLE IF NOT EXISTS {$docs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sponsor_id BIGINT UNSIGNED NOT NULL,
            title TEXT NOT NULL,
            url TEXT NOT NULL,
            authors TEXT NULL,
            publisher VARCHAR(255) NULL,
            pub_date DATE NULL,
            meta JSON NULL,
            allowed_for_export TINYINT(1) DEFAULT 1,
            approved TINYINT(1) DEFAULT 0,
            created_by BIGINT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sponsor_id (sponsor_id),
            KEY approved (approved),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $audit_sql = "CREATE TABLE IF NOT EXISTS {$audit_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT NULL,
            job_id VARCHAR(64) NULL,
            sponsor_doc_ids TEXT NULL,
            public_doc_ids TEXT NULL,
            action VARCHAR(32) NOT NULL,
            actor VARCHAR(64) NULL,
            model_version VARCHAR(64) NULL,
            prompt_hash VARCHAR(64) NULL,
            justification TEXT NULL,
            payload JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sponsors_sql );
        dbDelta( $docs_sql );
        dbDelta( $audit_sql );

        $url_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'url' ), ARRAY_A );
        if ( empty( $url_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN url VARCHAR(255) NULL" );
        }
        $export_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$docs_table} LIKE %s", 'allowed_for_export' ), ARRAY_A );
        if ( empty( $export_column ) ) {
            $wpdb->query( "ALTER TABLE {$docs_table} ADD COLUMN allowed_for_export TINYINT(1) DEFAULT 1" );
        }

        return self::table_exists();
    }

    public static function table_exists(): bool {
        global $wpdb;
        $sponsors_table = self::sponsors_table_name();
        $docs_table = self::docs_table_name();
        $audit_table = self::audit_table_name();
        $sponsors_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sponsors_table ) ) === $sponsors_table;
        $docs_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $docs_table ) ) === $docs_table;
        $audit_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $audit_table ) ) === $audit_table;
        return $sponsors_exists && $docs_exists && $audit_exists;
    }
}
