<?php

namespace KHM\Preview\Database\Migrations;

use wpdb;

class Installer {
    /**
     * Run installation migrations.
     */
    public function install(): void {
        $this->maybe_create_tables();
    }

    /**
     * Placeholder for deactivation cleanup.
     */
    public function deactivate(): void {
        // Intentionally empty for now; future cleanup could remove scheduled events.
    }

    private function maybe_create_tables(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $links   = $wpdb->prefix . 'khm_preview_links';
        $hits    = $wpdb->prefix . 'khm_preview_hits';

        $links_sql = "CREATE TABLE $links (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            token varchar(255) NOT NULL,
            token_hash varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            expires_at datetime NOT NULL,
            created_by bigint(20) unsigned NOT NULL,
            allowed_recipients longtext NULL,
            meta longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY token (token),
            KEY token_hash (token_hash)
        ) $charset;";

        $hits_sql = "CREATE TABLE $hits (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            link_id bigint(20) unsigned NOT NULL,
            viewed_at datetime NOT NULL,
            ip varchar(100) NULL,
            user_agent text NULL,
            meta longtext NULL,
            PRIMARY KEY  (id),
            KEY link_id (link_id)
        ) $charset;";

        dbDelta( $links_sql );
        dbDelta( $hits_sql );
    }
}
