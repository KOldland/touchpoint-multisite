<?php
/**
 * Search Logger
 *
 * Logs search queries to the kh_search_log table for monitoring
 * query volume, answer synthesis rates, and rate-limit hits.
 *
 * @package KH\Editorial\Search\Services
 */

namespace KH\Editorial\Search\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchLogger
 */
class SearchLogger {

    /**
     * Log a search query execution.
     *
     * @param string $query        The search query string.
     * @param string $source       Source identifier ('public', 'internal', etc.).
     * @param int    $blog_id      The blog/site ID.
     * @param int    $result_count Number of results returned.
     * @param int    $latency_ms   Query execution time in milliseconds.
     * @param bool   $synthesized  Whether answer synthesis was attempted.
     * @param bool   $success      Whether answer synthesis succeeded.
     * @param bool   $rate_limited Whether the request was rate-limited.
     * @param string|null $user_ip The requester's IP address (optional).
     * @return void
     */
    public function log_query(
        string  $query,
        string  $source = 'public',
        int     $blog_id = 0,
        int     $result_count = 0,
        int     $latency_ms = 0,
        bool    $synthesized = false,
        bool    $success = false,
        bool    $rate_limited = false,
        ?string $user_ip = null
    ): void {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'kh_search_log',
            array(
                'query'              => mb_substr( $query, 0, 500 ),
                'source'             => $source,
                'blog_id'            => $blog_id,
                'result_count'       => $result_count,
                'latency_ms'         => $latency_ms,
                'answer_synthesized' => $synthesized ? 1 : 0,
                'answer_success'     => $success ? 1 : 0,
                'rate_limited'       => $rate_limited ? 1 : 0,
                'user_ip'            => $user_ip,
            ),
            array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
        );
    }

    /**
     * Get recent search log entries.
     *
     * @param int      $limit   Number of entries to retrieve.
     * @param int|null $blog_id Optional blog ID filter.
     * @return array
     */
    public function get_recent( int $limit = 100, ?int $blog_id = null ): array {
        global $wpdb;

        $table  = $wpdb->prefix . 'kh_search_log';
        $where  = '';
        $params = array();

        if ( null !== $blog_id ) {
            $where   = 'WHERE blog_id = %d';
            $params[] = $blog_id;
        }

        $params[] = $limit;
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d",
            $params
        );

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Get aggregate statistics for a time period.
     *
     * @param string $interval 'hourly', 'daily', or 'all_time'.
     * @return array
     */
    public function get_stats( string $interval = 'daily' ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'kh_search_log';

        switch ( $interval ) {
            case 'hourly':
                $date_trunc = "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')";
                $since      = date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
                break;
            case 'all_time':
                $date_trunc = "DATE_FORMAT(created_at, '%Y-%m-%d')";
                $since      = '1970-01-01';
                break;
            case 'daily':
            default:
                $date_trunc = "DATE_FORMAT(created_at, '%Y-%m-%d')";
                $since      = date( 'Y-m-d', strtotime( '-30 days' ) );
                break;
        }

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    {$date_trunc} AS period,
                    COUNT(*) AS total_queries,
                    SUM(rate_limited) AS rate_limited_count,
                    SUM(answer_synthesized) AS synthesis_attempts,
                    SUM(answer_success) AS synthesis_successes,
                    AVG(latency_ms) AS avg_latency_ms,
                    AVG(result_count) AS avg_result_count
                FROM {$table}
                WHERE created_at >= %s
                GROUP BY period
                ORDER BY period DESC
                LIMIT 30",
                $since
            ),
            ARRAY_A
        );

        return $results ?: array();
    }

    /**
     * Get answer synthesis failure rate (percentage).
     *
     * Context: alerts if > threshold.
     *
     * @return float Failure rate as percentage (0-100), or 0 if no data.
     */
    public function get_synthesis_failure_rate(): float {
        global $wpdb;

        $table = $wpdb->prefix . 'kh_search_log';

        $row = $wpdb->get_row(
            "SELECT
                COUNT(*) AS total,
                SUM(answer_success) AS successes
            FROM {$table}
            WHERE answer_synthesized = 1
              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            ARRAY_A
        );

        if ( ! $row || (int) $row['total'] === 0 ) {
            return 0.0;
        }

        $total     = (int) $row['total'];
        $successes = (int) $row['successes'];
        $failures  = $total - $successes;

        return round( ( $failures / $total ) * 100, 1 );
    }

    /**
     * Prune old log entries (keep last 30 days).
     *
     * @return int Number of deleted rows.
     */
    public function prune(): int {
        global $wpdb;

        $cutoff = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}kh_search_log WHERE created_at < %s",
                $cutoff
            )
        );
    }
}