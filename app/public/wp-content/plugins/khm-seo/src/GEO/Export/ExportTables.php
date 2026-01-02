<?php
/**
 * Export Tables
 *
 * Database table management for GEO export functionality
 *
 * @package KHM_SEO\GEO\Export
 * @since 2.0.0
 */

namespace KHM_SEO\GEO\Export;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * ExportTables Class
 */
class ExportTables {

    /**
     * @var string Table prefix
     */
    private $table_prefix;

    /**
     * Constructor - Initialize table management
     */
    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix;
    }

    /**
     * Get table name
     *
     * @param string $table Table name
     * @return string Full table name
     */
    public function get_table_name( $table ) {
        return $this->table_prefix . 'khm_geo_' . $table;
    }

    /**
     * Create export-related tables
     */
    public function create_tables() {
        $this->create_export_log_table();
        $this->create_export_schedules_table();
    }

    /**
     * Create export log table
     */
    private function create_export_log_table() {
        global $wpdb;

        $table_name = $this->get_table_name( 'export_log' );
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            export_id varchar(100) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            data_types text NOT NULL,
            format varchar(10) NOT NULL,
            filename varchar(255) NOT NULL,
            file_path text NOT NULL,
            file_size bigint(20) unsigned DEFAULT 0,
            status enum('pending','processing','completed','failed') DEFAULT 'pending',
            options text,
            error_message text,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY export_id (export_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Create export schedules table
     */
    private function create_export_schedules_table() {
        global $wpdb;

        $table_name = $this->get_table_name( 'export_schedules' );
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            user_id bigint(20) unsigned NOT NULL,
            frequency enum('daily','weekly','monthly') NOT NULL,
            data_types text NOT NULL,
            format varchar(10) NOT NULL DEFAULT 'json',
            options text,
            recipients text,
            next_run datetime NOT NULL,
            last_run datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY next_run (next_run),
            KEY is_active (is_active)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Drop export tables
     */
    public function drop_tables() {
        global $wpdb;

        $tables = array(
            $this->get_table_name( 'export_log' ),
            $this->get_table_name( 'export_schedules' )
        );

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS $table" );
        }
    }

    /**
     * Log export operation
     *
     * @param array $data Export data
     * @return int|false Insert ID or false on failure
     */
    public function log_export( $data ) {
        global $wpdb;

        $table_name = $this->get_table_name( 'export_log' );

        $insert_data = array(
            'export_id' => $data['export_id'],
            'user_id' => $data['user_id'] ?? get_current_user_id(),
            'data_types' => maybe_serialize( $data['data_types'] ),
            'format' => $data['format'],
            'filename' => $data['filename'] ?? '',
            'file_path' => $data['file_path'] ?? '',
            'file_size' => $data['file_size'] ?? 0,
            'status' => $data['status'] ?? 'pending',
            'options' => maybe_serialize( $data['options'] ?? array() ),
            'error_message' => $data['error_message'] ?? '',
            'started_at' => $data['started_at'] ?? current_time( 'mysql' ),
            'completed_at' => $data['completed_at'] ?? null
        );

        $result = $wpdb->insert( $table_name, $insert_data );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update export log
     *
     * @param string $export_id Export ID
     * @param array $data Update data
     * @return bool Success
     */
    public function update_export_log( $export_id, $data ) {
        global $wpdb;

        $table_name = $this->get_table_name( 'export_log' );

        $update_data = array();
        if ( isset( $data['status'] ) ) {
            $update_data['status'] = $data['status'];
        }
        if ( isset( $data['filename'] ) ) {
            $update_data['filename'] = $data['filename'];
        }
        if ( isset( $data['file_path'] ) ) {
            $update_data['file_path'] = $data['file_path'];
        }
        if ( isset( $data['file_size'] ) ) {
            $update_data['file_size'] = $data['file_size'];
        }
        if ( isset( $data['error_message'] ) ) {
            $update_data['error_message'] = $data['error_message'];
        }
        if ( isset( $data['completed_at'] ) ) {
            $update_data['completed_at'] = $data['completed_at'];
        }

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array( 'export_id' => $export_id )
        );

        return $result !== false;
    }

    /**
     * Get export log entry
     *
     * @param string $export_id Export ID
     * @return object|null Export log entry
     */
    public function get_export_log( $export_id ) {
        global $wpdb;

        $table_name = $this->get_table_name( 'export_log' );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE export_id = %s",
            $export_id
        ) );
    }

    /**
     * Get recent exports
     *
     * @param int $limit Number of exports to retrieve
     * @return array Export log entries
     */
    public function get_recent_exports( $limit = 50 ) {
        global $wpdb;

        $table_name = $this->get_table_name( 'export_log' );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
            $limit
        ) );

        // Unserialize data
        foreach ( $results as $result ) {
            $result->data_types = maybe_unserialize( $result->data_types );
            $result->options = maybe_unserialize( $result->options );
        }

        return $results;
    }

    /**
     * Schedule export
     *
     * @param array $data Schedule data
     * @return int|false Insert ID or false on failure
     */
    public function schedule_export( $data ) {
        global $wpdb;

        $table_name = $this->get_table_name( 'export_schedules' );

        $insert_data = array(
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'user_id' => $data['user_id'] ?? get_current_user_id(),
            'frequency' => $data['frequency'],
            'data_types' => maybe_serialize( $data['data_types'] ),
            'format' => $data['format'] ?? 'json',
            'options' => maybe_serialize( $data['options'] ?? array() ),
            'recipients' => maybe_serialize( $data['recipients'] ?? array() ),
            'next_run' => $data['next_run'],
            'is_active' => $data['is_active'] ?? 1
        );

        $result = $wpdb->insert( $table_name, $insert_data );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get scheduled exports
     *
     * @param int $user_id User ID (optional)
     * @return array Scheduled exports
     */
    public function get_scheduled_exports( $user_id = null ) {
        global $wpdb;

        $table_name = $this->get_table_name( 'export_schedules' );

        $where = "WHERE is_active = 1";
        $params = array();

        if ( $user_id ) {
            $where .= " AND user_id = %d";
            $params[] = $user_id;
        }

        $query = "SELECT * FROM $table_name $where ORDER BY next_run ASC";
        if ( ! empty( $params ) ) {
            $query = $wpdb->prepare( $query, $params );
        }

        $results = $wpdb->get_results( $query );

        // Unserialize data
        foreach ( $results as $result ) {
            $result->data_types = maybe_unserialize( $result->data_types );
            $result->options = maybe_unserialize( $result->options );
            $result->recipients = maybe_unserialize( $result->recipients );
        }

        return $results;
    }

    /**
     * Update scheduled export
     *
     * @param int $schedule_id Schedule ID
     * @param array $data Update data
     * @return bool Success
     */
    public function update_scheduled_export( $schedule_id, $data ) {
        global $wpdb;

        $table_name = $this->get_table_name( 'export_schedules' );

        $update_data = array();
        if ( isset( $data['next_run'] ) ) {
            $update_data['next_run'] = $data['next_run'];
        }
        if ( isset( $data['last_run'] ) ) {
            $update_data['last_run'] = $data['last_run'];
        }
        if ( isset( $data['is_active'] ) ) {
            $update_data['is_active'] = $data['is_active'];
        }

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array( 'id' => $schedule_id )
        );

        return $result !== false;
    }

    /**
     * Delete scheduled export
     *
     * @param int $schedule_id Schedule ID
     * @return bool Success
     */
    public function delete_scheduled_export( $schedule_id ) {
        global $wpdb;

        $table_name = $this->get_table_name( 'export_schedules' );

        $result = $wpdb->delete(
            $table_name,
            array( 'id' => $schedule_id )
        );

        return $result !== false;
    }

    /**
     * Get due scheduled exports
     *
     * @return array Due scheduled exports
     */
    public function get_due_scheduled_exports() {
        global $wpdb;

        $table_name = $this->get_table_name( 'export_schedules' );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE is_active = 1 AND next_run <= %s",
            current_time( 'mysql' )
        ) );

        // Unserialize data
        foreach ( $results as $result ) {
            $result->data_types = maybe_unserialize( $result->data_types );
            $result->options = maybe_unserialize( $result->options );
            $result->recipients = maybe_unserialize( $result->recipients );
        }

        return $results;
    }

    /**
     * Clean up old export logs
     *
     * @param int $days_retention Retention period in days
     */
    public function cleanup_old_logs( $days_retention = 30 ) {
        global $wpdb;

        $table_name = $this->get_table_name( 'export_log' );

        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days_retention} days" ) );

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            $cutoff_date
        ) );
    }
}