<?php
/**
 * Suggestion Audit Logger
 *
 * Logs all suggestion requests for debugging and cost tracking.
 *
 * @package KHM\GEO
 */

namespace KHM\GEO;

defined( 'ABSPATH' ) || exit;

/**
 * Suggestion Audit Logger Class
 */
class SuggestionAuditLogger {

    /**
     * Table name (without prefix)
     *
     * @var string
     */
    const TABLE_NAME = 'geo_requests';

    /**
     * Get full table name
     *
     * @return string
     */
    public function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the audit log table
     *
     * @return bool
     */
    public function create_table() {
        global $wpdb;

        $table           = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(50) NOT NULL DEFAULT 'suggest',
            model varchar(100) NOT NULL DEFAULT '',
            prompt_id varchar(50) NOT NULL DEFAULT '',
            cached tinyint(1) NOT NULL DEFAULT 0,
            response_size int(11) unsigned NOT NULL DEFAULT 0,
            prompt_tokens int(11) unsigned NOT NULL DEFAULT 0,
            completion_tokens int(11) unsigned NOT NULL DEFAULT 0,
            estimated_cost decimal(10,6) NOT NULL DEFAULT 0,
            error_message varchar(500) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY post_id (post_id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        return $this->table_exists();
    }

    /**
     * Check if table exists
     *
     * @return bool
     */
    public function table_exists() {
        global $wpdb;
        $table = $this->get_table_name();
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    /**
     * Log a request
     *
     * @param array $data Log data.
     * @return int|false Insert ID or false on failure.
     */
    public function log_request( $data ) {
        global $wpdb;

        // Ensure table exists
        if ( ! $this->table_exists() ) {
            $this->create_table();
        }

        $defaults = array(
            'user_id'           => get_current_user_id(),
            'post_id'           => 0,
            'action'            => 'suggest',
            'model'             => '',
            'prompt_id'         => '',
            'cached'            => 0,
            'response_size'     => 0,
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
            'estimated_cost'    => 0,
            'error_message'     => null,
        );

        $data = wp_parse_args( $data, $defaults );

        $result = $wpdb->insert(
            $this->get_table_name(),
            array(
                'user_id'           => absint( $data['user_id'] ),
                'post_id'           => absint( $data['post_id'] ),
                'action'            => sanitize_key( $data['action'] ),
                'model'             => sanitize_text_field( $data['model'] ),
                'prompt_id'         => sanitize_text_field( $data['prompt_id'] ),
                'cached'            => $data['cached'] ? 1 : 0,
                'response_size'     => absint( $data['response_size'] ),
                'prompt_tokens'     => absint( $data['prompt_tokens'] ),
                'completion_tokens' => absint( $data['completion_tokens'] ),
                'estimated_cost'    => floatval( $data['estimated_cost'] ),
                'error_message'     => $data['error_message'] ? sanitize_text_field( $data['error_message'] ) : null,
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get statistics for a time period
     *
     * @param string $period Period: today, week, month, all.
     * @return array
     */
    public function get_stats( $period = 'today' ) {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return array();
        }

        $table = $this->get_table_name();

        // Calculate date filter
        $date_filter = '';
        switch ( $period ) {
            case 'today':
                $date_filter = 'AND created_at >= CURDATE()';
                break;
            case 'week':
                $date_filter = 'AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $date_filter = 'AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
                break;
            case 'all':
            default:
                $date_filter = '';
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN cached = 1 THEN 1 ELSE 0 END) as cache_hits,
                SUM(CASE WHEN cached = 0 THEN 1 ELSE 0 END) as cache_misses,
                SUM(prompt_tokens + completion_tokens) as total_tokens,
                SUM(estimated_cost) as total_cost,
                SUM(CASE WHEN error_message IS NOT NULL THEN 1 ELSE 0 END) as errors,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT post_id) as unique_posts
            FROM {$table}
            WHERE 1=1 {$date_filter}",
            ARRAY_A
        );
        // phpcs:enable

        if ( ! $stats ) {
            return array();
        }

        $stats['cache_hit_rate'] = $stats['total_requests'] > 0
            ? round( ( $stats['cache_hits'] / $stats['total_requests'] ) * 100, 1 )
            : 0;

        return $stats;
    }

    /**
     * Get recent logs
     *
     * @param int $limit Number of logs to retrieve.
     * @return array
     */
    public function get_recent_logs( $limit = 100 ) {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return array();
        }

        $table = $this->get_table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Get logs for a specific post
     *
     * @param int $post_id Post ID.
     * @return array
     */
    public function get_logs_by_post( $post_id ) {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return array();
        }

        $table = $this->get_table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE post_id = %d ORDER BY created_at DESC",
                $post_id
            ),
            ARRAY_A
        );
    }

    /**
     * Cleanup old logs
     *
     * @param int $days_old Delete logs older than this many days.
     * @return int Number of deleted rows.
     */
    public function cleanup( $days_old = 90 ) {
        global $wpdb;

        if ( ! $this->table_exists() ) {
            return 0;
        }

        $table = $this->get_table_name();

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_old
            )
        );
    }
}

/**
 * Schedule daily cleanup
 */
function khm_geo_schedule_audit_cleanup() {
    if ( ! wp_next_scheduled( 'khm_geo_audit_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'khm_geo_audit_cleanup' );
    }
}
add_action( 'init', __NAMESPACE__ . '\\khm_geo_schedule_audit_cleanup' );

/**
 * Run audit cleanup
 */
function khm_geo_run_audit_cleanup() {
    $logger        = new SuggestionAuditLogger();
    $retention_days = get_option( 'khm_geo_audit_retention_days', 90 );
    $logger->cleanup( $retention_days );
}
add_action( 'khm_geo_audit_cleanup', __NAMESPACE__ . '\\khm_geo_run_audit_cleanup' );
