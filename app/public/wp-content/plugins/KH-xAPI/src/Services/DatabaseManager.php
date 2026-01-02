<?php
namespace KH\XAPI\Services;

class DatabaseManager {
    public const VERSION_OPTION = 'kh_xapi_schema_version';
    public const SCHEMA_VERSION = '0.1.0';

    public function register_hooks(): void {
        add_action( 'admin_init', [ $this, 'maybe_upgrade' ] );
    }

    public function maybe_upgrade(): void {
        if ( version_compare( get_option( self::VERSION_OPTION ), self::SCHEMA_VERSION, '>=' ) ) {
            return;
        }

        $this->create_completion_table();
        $this->create_scorm_table();

        update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
    }

    private function create_completion_table(): void {
        global $wpdb;

        $table_name       = $wpdb->prefix . 'kh_xapi_completions';
        $charset_collate  = $wpdb->get_charset_collate();
        $sql              = "CREATE TABLE {$table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            content_id int(11) NOT NULL,
            user_id int(11) NOT NULL,
            status varchar(16) DEFAULT NULL,
            percentage float DEFAULT NULL,
            score float DEFAULT NULL,
            timespent int(11) DEFAULT NULL,
            statement longtext,
            registration varchar(255) DEFAULT NULL,
            recorded_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_content (user_id, content_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    private function create_scorm_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'kh_xapi_scorm_state';
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE {$table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            content_id int(11) NOT NULL,
            user_id int(11) NOT NULL,
            registration_id varchar(255) DEFAULT NULL,
            var_key varchar(255) NOT NULL,
            var_value longtext NOT NULL,
            recorded_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_content_reg (content_id, user_id, registration_id(4))
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
