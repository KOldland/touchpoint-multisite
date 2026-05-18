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
    const INGEST_JOBS_TABLE = 'khm_sponsor_ingest_jobs';
    const SOURCES_TABLE = 'khm_sponsor_sources';
    const LIBRARIES_TABLE = 'khm_sponsor_libraries';

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

    public static function ingest_jobs_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::INGEST_JOBS_TABLE;
    }

    public static function sources_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::SOURCES_TABLE;
    }

    public static function libraries_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::LIBRARIES_TABLE;
    }

    public static function create_tables(): bool {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $sponsors_table = self::sponsors_table_name();
        $docs_table = self::docs_table_name();
        $audit_table = self::audit_table_name();
        $ingest_jobs_table = self::ingest_jobs_table_name();
        $sources_table = self::sources_table_name();
        $libraries_table = self::libraries_table_name();

        $sponsors_sql = "CREATE TABLE IF NOT EXISTS {$sponsors_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            url VARCHAR(255) NULL,
            contact_email VARCHAR(255) NULL,
            hq_location VARCHAR(255) NULL,
            regions VARCHAR(255) NULL,
            company_size_band VARCHAR(64) NULL,
            pilot_scheme_available TINYINT(1) DEFAULT 0,
            free_trial_available TINYINT(1) DEFAULT 0,
            software_expertise LONGTEXT NULL,
            hardware_capabilities LONGTEXT NULL,
            consultancy_areas LONGTEXT NULL,
            deployment_modes LONGTEXT NULL,
            support_tiers LONGTEXT NULL,
            provider_type LONGTEXT NULL,
            publish_allowed TINYINT(1) DEFAULT 0,
            created_by BIGINT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY publish_allowed (publish_allowed),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $docs_sql = "CREATE TABLE IF NOT EXISTS {$docs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sponsor_id BIGINT UNSIGNED NOT NULL,
            library_id BIGINT UNSIGNED NULL,
            title TEXT NOT NULL,
            url TEXT NOT NULL,
            authors TEXT NULL,
            publisher VARCHAR(255) NULL,
            pub_date DATE NULL,
            cover_thumbnail_url TEXT NULL,
            meta JSON NULL,
            allowed_for_export TINYINT(1) DEFAULT 1,
            approved TINYINT(1) DEFAULT 0,
            created_by BIGINT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sponsor_id (sponsor_id),
            KEY library_id (library_id),
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

        $ingest_jobs_sql = "CREATE TABLE IF NOT EXISTS {$ingest_jobs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sponsor_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'queued',
            source_type VARCHAR(32) NOT NULL DEFAULT 'urls',
            payload LONGTEXT NULL,
            total_items INT UNSIGNED NOT NULL DEFAULT 0,
            processed_items INT UNSIGNED NOT NULL DEFAULT 0,
            succeeded_items INT UNSIGNED NOT NULL DEFAULT 0,
            failed_items INT UNSIGNED NOT NULL DEFAULT 0,
            error_message TEXT NULL,
            created_by BIGINT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sponsor_id (sponsor_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $sources_sql = "CREATE TABLE IF NOT EXISTS {$sources_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sponsor_id BIGINT UNSIGNED NOT NULL,
            library_id BIGINT UNSIGNED NULL,
            root_url TEXT NOT NULL,
            domain_allowlist TEXT NULL,
            max_pages INT UNSIGNED NOT NULL DEFAULT 25,
            max_depth INT UNSIGNED NOT NULL DEFAULT 2,
            max_response_kb INT UNSIGNED NOT NULL DEFAULT 512,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            last_run_at DATETIME NULL,
            last_job_id BIGINT UNSIGNED NULL,
            created_by BIGINT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sponsor_id (sponsor_id),
            KEY library_id (library_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $libraries_sql = "CREATE TABLE IF NOT EXISTS {$libraries_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sponsor_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            topic VARCHAR(255) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            created_by BIGINT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sponsor_id (sponsor_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sponsors_sql );
        dbDelta( $docs_sql );
        dbDelta( $audit_sql );
        dbDelta( $ingest_jobs_sql );
        dbDelta( $sources_sql );
        dbDelta( $libraries_sql );

        $url_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'url' ), ARRAY_A );
        if ( empty( $url_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN url VARCHAR(255) NULL" );
        }
        $hq_location_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'hq_location' ), ARRAY_A );
        if ( empty( $hq_location_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN hq_location VARCHAR(255) NULL" );
        }
        $regions_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'regions' ), ARRAY_A );
        if ( empty( $regions_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN regions VARCHAR(255) NULL" );
        }
        $company_size_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'company_size_band' ), ARRAY_A );
        if ( empty( $company_size_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN company_size_band VARCHAR(64) NULL" );
        }
        $pilot_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'pilot_scheme_available' ), ARRAY_A );
        if ( empty( $pilot_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN pilot_scheme_available TINYINT(1) DEFAULT 0" );
        }
        $trial_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'free_trial_available' ), ARRAY_A );
        if ( empty( $trial_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN free_trial_available TINYINT(1) DEFAULT 0" );
        }
        $json_cols = ['software_expertise', 'hardware_capabilities', 'consultancy_areas', 'deployment_modes', 'support_tiers', 'provider_type'];
        foreach ($json_cols as $col) {
            $existing = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", $col ), ARRAY_A );
            if ( empty( $existing ) ) {
                $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN {$col} LONGTEXT NULL" );
            }
        }
        $export_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$docs_table} LIKE %s", 'allowed_for_export' ), ARRAY_A );
        if ( empty( $export_column ) ) {
            $wpdb->query( "ALTER TABLE {$docs_table} ADD COLUMN allowed_for_export TINYINT(1) DEFAULT 1" );
        }

        $sponsor_logo_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'logo_attachment_id' ), ARRAY_A );
        if ( empty( $sponsor_logo_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN logo_attachment_id BIGINT UNSIGNED NULL" );
        }

        $sponsor_social_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'social_profiles' ), ARRAY_A );
        if ( empty( $sponsor_social_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN social_profiles LONGTEXT NULL" );
        }

        $sponsor_team_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'team_member_levels' ), ARRAY_A );
        if ( empty( $sponsor_team_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN team_member_levels LONGTEXT NULL" );
        }

        $sponsor_primary_first_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'primary_contact_first_name' ), ARRAY_A );
        if ( empty( $sponsor_primary_first_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN primary_contact_first_name VARCHAR(120) NULL" );
        }

        $sponsor_primary_last_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'primary_contact_last_name' ), ARRAY_A );
        if ( empty( $sponsor_primary_last_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN primary_contact_last_name VARCHAR(120) NULL" );
        }

        $sponsor_primary_job_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'primary_contact_job_title' ), ARRAY_A );
        if ( empty( $sponsor_primary_job_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN primary_contact_job_title VARCHAR(150) NULL" );
        }

        $sponsor_primary_email_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'primary_contact_email' ), ARRAY_A );
        if ( empty( $sponsor_primary_email_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN primary_contact_email VARCHAR(255) NULL" );
        }

        $sponsor_team_members_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", 'team_members' ), ARRAY_A );
        if ( empty( $sponsor_team_members_column ) ) {
            $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN team_members LONGTEXT NULL" );
        }

        $docs_library_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$docs_table} LIKE %s", 'library_id' ), ARRAY_A );
        if ( empty( $docs_library_column ) ) {
            $wpdb->query( "ALTER TABLE {$docs_table} ADD COLUMN library_id BIGINT UNSIGNED NULL" );
            $wpdb->query( "ALTER TABLE {$docs_table} ADD KEY library_id (library_id)" );
        }

        $docs_thumbnail_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$docs_table} LIKE %s", 'cover_thumbnail_url' ), ARRAY_A );
        if ( empty( $docs_thumbnail_column ) ) {
            $wpdb->query( "ALTER TABLE {$docs_table} ADD COLUMN cover_thumbnail_url TEXT NULL" );
        }

        $source_library_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sources_table} LIKE %s", 'library_id' ), ARRAY_A );
        if ( empty( $source_library_column ) ) {
            $wpdb->query( "ALTER TABLE {$sources_table} ADD COLUMN library_id BIGINT UNSIGNED NULL" );
            $wpdb->query( "ALTER TABLE {$sources_table} ADD KEY library_id (library_id)" );
        }

        // New Account Details columns for sponsors
        $new_sponsor_cols = [
            'hq_location'            => 'VARCHAR(255) NULL',
            'regions'                => 'VARCHAR(255) NULL',
            'company_size_band'      => 'VARCHAR(64) NULL',
            'pilot_scheme_available' => 'TINYINT(1) DEFAULT 0',
            'free_trial_available'   => 'TINYINT(1) DEFAULT 0',
            'software_expertise'     => 'LONGTEXT NULL',
            'hardware_capabilities'  => 'LONGTEXT NULL',
            'consultancy_areas'      => 'LONGTEXT NULL',
            'deployment_modes'       => 'LONGTEXT NULL',
            'support_tiers'          => 'LONGTEXT NULL',
            'provider_type'          => 'LONGTEXT NULL',
            'implementation_support' => 'TINYINT(1) DEFAULT 0',
            'support_hours'          => 'VARCHAR(64) DEFAULT "business"',
            'updated_at'             => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];

        foreach ( $new_sponsor_cols as $col => $type ) {
            $exists = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$sponsors_table} LIKE %s", $col ), ARRAY_A );
            if ( empty( $exists ) ) {
                $wpdb->query( "ALTER TABLE {$sponsors_table} ADD COLUMN {$col} {$type}" );
            }
        }

        return self::table_exists();
    }

    public static function table_exists(): bool {
        global $wpdb;
        $sponsors_table = self::sponsors_table_name();
        $docs_table = self::docs_table_name();
        $audit_table = self::audit_table_name();
        $ingest_jobs_table = self::ingest_jobs_table_name();
        $sources_table = self::sources_table_name();
        $libraries_table = self::libraries_table_name();
        $sponsors_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sponsors_table ) ) === $sponsors_table;
        $docs_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $docs_table ) ) === $docs_table;
        $audit_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $audit_table ) ) === $audit_table;
        $ingest_jobs_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ingest_jobs_table ) ) === $ingest_jobs_table;
        $sources_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sources_table ) ) === $sources_table;
        $libraries_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $libraries_table ) ) === $libraries_table;
        return $sponsors_exists && $docs_exists && $audit_exists && $ingest_jobs_exists && $sources_exists && $libraries_exists;
    }
}
