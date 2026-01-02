<?php
/**
 * Measurement Database Tables
 *
 * Handles database table creation and management for measurement data
 *
 * @package KHM_SEO\GEO\Measurement
 * @since 2.0.0
 */

namespace KHM_SEO\GEO\Measurement;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * MeasurementTables Class
 */
class MeasurementTables {

    /**
     * Table names
     */
    const TABLES = array(
        'metrics' => 'geo_metrics',
        'search_analytics' => 'geo_search_analytics',
        'performance_history' => 'geo_performance_history'
    );

    /**
     * Get table name with prefix
     *
     * @param string $table Table key
     * @return string Full table name
     */
    public function get_table_name( $table ) {
        global $wpdb;

        if ( ! isset( self::TABLES[ $table ] ) ) {
            return false;
        }

        return $wpdb->prefix . self::TABLES[ $table ];
    }

    /**
     * Get all table names
     *
     * @return array Table names with prefixes
     */
    public function get_all_table_names() {
        global $wpdb;

        $tables = array();
        foreach ( self::TABLES as $key => $table ) {
            $tables[ $key ] = $wpdb->prefix . $table;
        }

        return $tables;
    }

    /**
     * Install measurement tables
     */
    public function install_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // Metrics table - stores all tracking events
        $metrics_table = $this->get_table_name( 'metrics' );
        $metrics_sql = "CREATE TABLE {$metrics_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metric_type varchar(50) NOT NULL,
            post_id bigint(20) unsigned DEFAULT 0,
            user_id bigint(20) unsigned DEFAULT 0,
            session_id varchar(255) DEFAULT '',
            metric_data longtext DEFAULT '',
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY metric_type (metric_type),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY timestamp (timestamp),
            KEY session_id (session_id(191))
        ) {$charset_collate};";

        dbDelta( $metrics_sql );

        // Search analytics table - stores Google Search Console data
        $search_table = $this->get_table_name( 'search_analytics' );
        $search_sql = "CREATE TABLE {$search_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id bigint(20) unsigned DEFAULT 0,
            keyword varchar(255) NOT NULL,
            position int(11) DEFAULT 0,
            impressions int(11) DEFAULT 0,
            clicks int(11) DEFAULT 0,
            ctr decimal(5,4) DEFAULT 0.0000,
            date_collected datetime NOT NULL,
            PRIMARY KEY (id),
            KEY entity_id (entity_id),
            KEY keyword (keyword(191)),
            KEY date_collected (date_collected),
            KEY position (position)
        ) {$charset_collate};";

        dbDelta( $search_sql );

        // Performance history table - stores calculated performance scores over time
        $performance_table = $this->get_table_name( 'performance_history' );
        $performance_sql = "CREATE TABLE {$performance_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            performance_score int(11) DEFAULT 0,
            engagement_rate decimal(5,4) DEFAULT 0.0000,
            citation_rate decimal(5,4) DEFAULT 0.0000,
            page_views int(11) DEFAULT 0,
            calculated_date datetime NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY calculated_date (calculated_date),
            KEY performance_score (performance_score)
        ) {$charset_collate};";

        dbDelta( $performance_sql );

        // Store installation version
        update_option( 'khm_geo_measurement_db_version', '2.0.0' );
    }

    /**
     * Uninstall measurement tables
     */
    public function uninstall_tables() {
        global $wpdb;

        $tables = $this->get_all_table_names();

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }

        delete_option( 'khm_geo_measurement_db_version' );
    }

    /**
     * Check table status
     *
     * @return array Table status information
     */
    public function check_tables_status() {
        global $wpdb;

        $status = array();
        $tables = $this->get_all_table_names();

        foreach ( $tables as $key => $table ) {
            $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;

            $status[ $key ] = array(
                'exists' => $exists,
                'table_name' => $table
            );

            if ( $exists ) {
                // Get record count
                $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
                $status[ $key ]['record_count'] = intval( $count );

                // Get latest record date
                $latest = $wpdb->get_var( "SELECT MAX(timestamp) FROM {$table} WHERE timestamp IS NOT NULL" );
                if ( ! $latest && $key === 'search_analytics' ) {
                    $latest = $wpdb->get_var( "SELECT MAX(date_collected) FROM {$table}" );
                }
                $status[ $key ]['latest_record'] = $latest;
            }
        }

        return $status;
    }

    /**
     * Get database statistics
     *
     * @return array Database statistics
     */
    public function get_database_stats() {
        global $wpdb;

        $stats = array(
            'total_metrics' => 0,
            'total_search_queries' => 0,
            'total_performance_records' => 0,
            'oldest_metric' => null,
            'newest_metric' => null,
            'metrics_by_type' => array()
        );

        $tables = $this->get_all_table_names();

        // Metrics table stats
        if ( isset( $tables['metrics'] ) ) {
            $stats['total_metrics'] = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['metrics']}" ) );
            $stats['oldest_metric'] = $wpdb->get_var( "SELECT MIN(timestamp) FROM {$tables['metrics']}" );
            $stats['newest_metric'] = $wpdb->get_var( "SELECT MAX(timestamp) FROM {$tables['metrics']}" );

            // Metrics by type
            $type_stats = $wpdb->get_results(
                "SELECT metric_type, COUNT(*) as count FROM {$tables['metrics']} GROUP BY metric_type ORDER BY count DESC"
            );

            foreach ( $type_stats as $stat ) {
                $stats['metrics_by_type'][ $stat->metric_type ] = intval( $stat->count );
            }
        }

        // Search analytics stats
        if ( isset( $tables['search_analytics'] ) ) {
            $stats['total_search_queries'] = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['search_analytics']}" ) );
        }

        // Performance history stats
        if ( isset( $tables['performance_history'] ) ) {
            $stats['total_performance_records'] = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['performance_history']}" ) );
        }

        return $stats;
    }

    /**
     * Clean up old data
     *
     * @param int $retention_days Days to keep data
     */
    public function cleanup_old_data( $retention_days = 365 ) {
        global $wpdb;

        $tables = $this->get_all_table_names();
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

        // Clean metrics table
        if ( isset( $tables['metrics'] ) ) {
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$tables['metrics']} WHERE timestamp < %s",
                $cutoff_date
            ) );
        }

        // Clean search analytics (keep longer for trending analysis)
        if ( isset( $tables['search_analytics'] ) ) {
            $search_cutoff = date( 'Y-m-d H:i:s', strtotime( "-730 days" ) ); // 2 years
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$tables['search_analytics']} WHERE date_collected < %s",
                $search_cutoff
            ) );
        }

        // Clean performance history (keep 1 year)
        if ( isset( $tables['performance_history'] ) ) {
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$tables['performance_history']} WHERE calculated_date < %s",
                $cutoff_date
            ) );
        }
    }

    /**
     * Optimize tables
     */
    public function optimize_tables() {
        global $wpdb;

        $tables = $this->get_all_table_names();

        foreach ( $tables as $table ) {
            $wpdb->query( "OPTIMIZE TABLE {$table}" );
        }
    }
}