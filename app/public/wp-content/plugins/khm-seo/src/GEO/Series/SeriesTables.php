<?php
/**
 * Series Database Tables
 *
 * Manages database tables for AnswerCard series functionality
 *
 * @package KHM_SEO\GEO\Series
 * @since 2.0.0
 */

namespace KHM_SEO\GEO\Series;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * SeriesTables Class
 */
class SeriesTables {

    /**
     * @var array Table definitions
     */
    private $tables = array();

    /**
     * Constructor - Initialize table definitions
     */
    public function __construct() {
        $this->init_table_definitions();
        // Only initialize hooks if WordPress functions are available
        if ( function_exists( 'register_activation_hook' ) ) {
            $this->init_hooks();
        }
    }

    /**
     * Initialize table definitions
     */
    private function init_table_definitions() {
        global $wpdb;

        $this->tables = array(
            'series' => array(
                'name' => $wpdb->prefix . 'khm_geo_series',
                'schema' => "CREATE TABLE {$wpdb->prefix}khm_geo_series (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    title varchar(255) NOT NULL,
                    description text,
                    type varchar(50) NOT NULL DEFAULT 'sequential',
                    auto_progression tinyint(1) NOT NULL DEFAULT 1,
                    created_by bigint(20) unsigned NOT NULL,
                    created_at datetime NOT NULL,
                    updated_at datetime NOT NULL,
                    PRIMARY KEY (id),
                    KEY type (type),
                    KEY created_by (created_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            ),

            'series_items' => array(
                'name' => $wpdb->prefix . 'khm_geo_series_items',
                'schema' => "CREATE TABLE {$wpdb->prefix}khm_geo_series_items (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    series_id bigint(20) unsigned NOT NULL,
                    post_id bigint(20) unsigned NOT NULL,
                    position int(11) NOT NULL DEFAULT 0,
                    added_at datetime NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY series_post (series_id, post_id),
                    KEY series_id (series_id),
                    KEY post_id (post_id),
                    KEY position (position)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            ),

            'series_meta' => array(
                'name' => $wpdb->prefix . 'khm_geo_series_meta',
                'schema' => "CREATE TABLE {$wpdb->prefix}khm_geo_series_meta (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    series_id bigint(20) unsigned NOT NULL,
                    meta_key varchar(255) NOT NULL,
                    meta_value longtext,
                    PRIMARY KEY (id),
                    KEY series_id (series_id),
                    KEY meta_key (meta_key(191))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            )
        );
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook( KHM_SEO_PLUGIN_FILE, array( $this, 'create_tables' ) );
        add_action( 'plugins_loaded', array( $this, 'check_table_versions' ) );
    }

    /**
     * Create all series tables
     */
    public function create_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( $this->tables as $table_key => $table_info ) {
            $this->create_table( $table_key );
        }

        $this->update_db_version();
    }

    /**
     * Create a specific table
     *
     * @param string $table_key Table key
     * @return bool Success status
     */
    private function create_table( $table_key ) {
        global $wpdb;

        if ( ! isset( $this->tables[ $table_key ] ) ) {
            return false;
        }

        $table_info = $this->tables[ $table_key ];

        // Check if table exists
        if ( $this->table_exists( $table_info['name'] ) ) {
            return $this->upgrade_table( $table_key );
        }

        // Create table
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $table_info['schema'] );

        // Verify table was created
        if ( $this->table_exists( $table_info['name'] ) ) {
            $this->log_table_creation( $table_key );
            return true;
        }

        return false;
    }

    /**
     * Upgrade existing table if needed
     *
     * @param string $table_key Table key
     * @return bool Success status
     */
    private function upgrade_table( $table_key ) {
        // For now, just ensure table exists
        // Future versions can add column upgrades here
        return true;
    }

    /**
     * Check if table exists
     *
     * @param string $table_name Table name
     * @return bool True if exists
     */
    private function table_exists( $table_name ) {
        global $wpdb;

        $query = $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name );
        $result = $wpdb->get_var( $query );

        return $result === $table_name;
    }

    /**
     * Check table versions and upgrade if needed
     */
    public function check_table_versions() {
        $current_version = get_option( 'khm_geo_series_db_version', '0.0.0' );
        $plugin_version = KHM_SEO_VERSION;

        if ( version_compare( $current_version, $plugin_version, '<' ) ) {
            $this->upgrade_database( $current_version, $plugin_version );
        }
    }

    /**
     * Upgrade database to new version
     *
     * @param string $from_version Current version
     * @param string $to_version Target version
     */
    private function upgrade_database( $from_version, $to_version ) {
        // Run upgrade routines based on version differences
        if ( version_compare( $from_version, '2.0.0', '<' ) ) {
            $this->upgrade_to_2_0_0();
        }

        $this->update_db_version();
    }

    /**
     * Upgrade to version 2.0.0
     */
    private function upgrade_to_2_0_0() {
        // Ensure all tables exist
        $this->create_tables();

        // Add any missing indexes or columns
        $this->add_missing_indexes();
    }

    /**
     * Add missing indexes for performance
     */
    private function add_missing_indexes() {
        global $wpdb;

        // Check and add indexes if missing
        $indexes_to_check = array(
            "{$wpdb->prefix}khm_geo_series" => array(
                'type' => 'KEY type (type)',
                'created_by' => 'KEY created_by (created_by)'
            ),
            "{$wpdb->prefix}khm_geo_series_items" => array(
                'series_post' => 'UNIQUE KEY series_post (series_id, post_id)',
                'series_id' => 'KEY series_id (series_id)',
                'post_id' => 'KEY post_id (post_id)',
                'position' => 'KEY position (position)'
            ),
            "{$wpdb->prefix}khm_geo_series_meta" => array(
                'series_id' => 'KEY series_id (series_id)',
                'meta_key' => 'KEY meta_key (meta_key(191))'
            )
        );

        foreach ( $indexes_to_check as $table_name => $indexes ) {
            if ( $this->table_exists( $table_name ) ) {
                foreach ( $indexes as $index_name => $index_sql ) {
                    if ( ! $this->index_exists( $table_name, $index_name ) ) {
                        $wpdb->query( "ALTER TABLE {$table_name} ADD {$index_sql}" );
                    }
                }
            }
        }
    }

    /**
     * Check if index exists
     *
     * @param string $table_name Table name
     * @param string $index_name Index name
     * @return bool True if exists
     */
    private function index_exists( $table_name, $index_name ) {
        global $wpdb;

        $indexes = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
                $index_name
            )
        );

        return ! empty( $indexes );
    }

    /**
     * Update database version
     */
    private function update_db_version() {
        update_option( 'khm_geo_series_db_version', KHM_SEO_VERSION );
    }

    /**
     * Drop all series tables (for cleanup/testing)
     */
    public function drop_tables() {
        global $wpdb;

        foreach ( $this->tables as $table_info ) {
            $table_name = $table_info['name'];

            if ( $this->table_exists( $table_name ) ) {
                $wpdb->query( "DROP TABLE {$table_name}" );
            }
        }

        delete_option( 'khm_geo_series_db_version' );
    }

    /**
     * Get table name by key
     *
     * @param string $table_key Table key
     * @return string|null Table name or null
     */
    public function get_table_name( $table_key ) {
        return $this->tables[ $table_key ]['name'] ?? null;
    }

    /**
     * Get all table names
     *
     * @return array Table names
     */
    public function get_table_names() {
        return array_column( $this->tables, 'name');
    }

    /**
     * Get table schema by key
     *
     * @param string $table_key Table key
     * @return string|null Table schema or null
     */
    public function get_table_schema( $table_key ) {
        return $this->tables[ $table_key ]['schema'] ?? null;
    }

    /**
     * Log table creation
     *
     * @param string $table_key Table key
     */
    private function log_table_creation( $table_key ) {
        error_log( sprintf(
            'KHM SEO Series: Created table %s',
            $this->tables[ $table_key ]['name']
        ) );
    }

    /**
     * Get database statistics
     *
     * @return array Database statistics
     */
    public function get_stats() {
        global $wpdb;

        $stats = array(
            'tables_exist' => 0,
            'total_series' => 0,
            'total_items' => 0,
            'total_meta' => 0
        );

        // Check table existence
        foreach ( $this->tables as $table_info ) {
            if ( $this->table_exists( $table_info['name'] ) ) {
                $stats['tables_exist']++;
            }
        }

        // Get record counts
        if ( $this->table_exists( $this->tables['series']['name'] ) ) {
            $stats['total_series'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['series']['name']}"
            );
        }

        if ( $this->table_exists( $this->tables['series_items']['name'] ) ) {
            $stats['total_items'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['series_items']['name']}"
            );
        }

        if ( $this->table_exists( $this->tables['series_meta']['name'] ) ) {
            $stats['total_meta'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['series_meta']['name']}"
            );
        }

        return $stats;
    }

    /**
     * Optimize tables
     */
    public function optimize_tables() {
        global $wpdb;

        foreach ( $this->tables as $table_info ) {
            $table_name = $table_info['name'];

            if ( $this->table_exists( $table_name ) ) {
                $wpdb->query( "OPTIMIZE TABLE {$table_name}" );
            }
        }
    }

    /**
     * Repair tables
     */
    public function repair_tables() {
        global $wpdb;

        foreach ( $this->tables as $table_info ) {
            $table_name = $table_info['name'];

            if ( $this->table_exists( $table_name ) ) {
                $wpdb->query( "REPAIR TABLE {$table_name}" );
            }
        }
    }
}