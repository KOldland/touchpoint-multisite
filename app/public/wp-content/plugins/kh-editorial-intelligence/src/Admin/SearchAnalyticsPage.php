<?php
/**
 * Search Analytics Admin Page
 *
 * Displays search query logs, aggregate stats, and system health
 * (answer synthesis failure rate, cache status).
 *
 * @package KH\Editorial\Admin
 */

namespace KH\Editorial\Admin;

use KH\Editorial\Search\Services\SearchLogger;
use KH\Editorial\Search\Services\SearchCache;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchAnalyticsPage
 */
class SearchAnalyticsPage {

    /**
     * Initialize hooks.
     *
     * @return void
     */
    public function init(): void {
        // Admin UI guard — only on main site or for super admins
        if ( function_exists( 'khm_can_show_admin_ui' ) && !khm_can_show_admin_ui() ) {
            return;
        }

        add_action( 'admin_menu', array( $this, 'register_submenu' ) );
    }

    /**
     * Register the submenu page under Settings > Search Analytics.
     *
     * @return void
     */
    public function register_submenu(): void {
        // Double-guard for direct instantiation scenarios
        if ( function_exists( 'khm_can_show_admin_ui' ) && !khm_can_show_admin_ui() ) {
            return;
        }

        add_submenu_page(
            'kh-editorial-admin',
            __( 'Search Analytics', 'kh-editorial-intelligence' ),
            __( 'Search Analytics', 'kh-editorial-intelligence' ),
            'manage_options',
            'kh-search-analytics',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render the search analytics page.
     *
     * @return void
     */
    public function render_page(): void {
        $logger = new SearchLogger();
        $cache  = new SearchCache();

        // Handle cache flush action.
        if ( isset( $_GET['flush_search_cache'] ) && check_admin_referer( 'flush_search_cache' ) ) {
            $flushed = $cache->flush_all();
            echo '<div class="notice notice-success"><p>Search cache flushed. ' . esc_html( $flushed ) . ' entries cleared.</p></div>';
        }

        // Handle log prune action.
        if ( isset( $_GET['prune_search_logs'] ) && check_admin_referer( 'prune_search_logs' ) ) {
            $pruned = $logger->prune();
            echo '<div class="notice notice-success"><p>Search logs pruned. ' . esc_html( $pruned ) . ' old entries removed.</p></div>';
        }

        $stats       = $logger->get_stats( 'daily' );
        $recent      = $logger->get_recent( 50 );
        $failure_rate = $logger->get_synthesis_failure_rate();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Search Analytics', 'kh-editorial-intelligence' ); ?></h1>

            <!-- Action buttons -->
            <p>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=kh-search-analytics&flush_search_cache=1' ), 'flush_search_cache' ) ); ?>" class="button">
                    <?php esc_html_e( 'Flush Search Cache', 'kh-editorial-intelligence' ); ?>
                </a>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=kh-search-analytics&prune_search_logs=1' ), 'prune_search_logs' ) ); ?>" class="button">
                    <?php esc_html_e( 'Prune Old Logs (>30 days)', 'kh-editorial-intelligence' ); ?>
                </a>
            </p>

            <!-- Health status -->
            <div id="kh-search-health">
                <h2><?php esc_html_e( 'System Health', 'kh-editorial-intelligence' ); ?></h2>
                <table class="widefat striped" style="max-width: 600px;">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'Answer Synthesis Failure Rate (last hour)', 'kh-editorial-intelligence' ); ?></strong></td>
                            <td style="<?php echo $failure_rate > 20 ? 'color: #d63638; font-weight: 700;' : ( $failure_rate > 0 ? 'color: #dba617;' : 'color: #46b450;' ); ?>">
                                <?php echo esc_html( $failure_rate ); ?>%
                                <?php if ( $failure_rate > 20 ) : ?>
                                    ⚠️ <?php esc_html_e( 'Above 20% threshold', 'kh-editorial-intelligence' ); ?>
                                <?php elseif ( $failure_rate > 0 ) : ?>
                                    ⚡ <?php esc_html_e( 'Elevated', 'kh-editorial-intelligence' ); ?>
                                <?php else : ?>
                                    ✅ <?php esc_html_e( 'Healthy', 'kh-editorial-intelligence' ); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Cache Status', 'kh-editorial-intelligence' ); ?></strong></td>
                            <td><?php echo $cache->is_hot() ? '🔥 ' . esc_html__( 'Hot (entries cached)', 'kh-editorial-intelligence' ) : '❄️ ' . esc_html__( 'Cold (no cached entries)', 'kh-editorial-intelligence' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'External Object Cache', 'kh-editorial-intelligence' ); ?></strong></td>
                            <td><?php echo wp_using_ext_object_cache() ? '✅ ' . esc_html__( 'Redis/memcached active', 'kh-editorial-intelligence' ) : '❌ ' . esc_html__( 'Default WP transients', 'kh-editorial-intelligence' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Daily stats -->
            <div id="kh-search-stats" style="margin-top: 24px;">
                <h2><?php esc_html_e( 'Daily Stats (Last 30 Days)', 'kh-editorial-intelligence' ); ?></h2>
                <?php if ( ! empty( $stats ) ) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Queries', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Rate-Limited', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Synthesis Attempts', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Synthesis Success', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Avg Latency (ms)', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Avg Results', 'kh-editorial-intelligence' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $stats as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row['period'] ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( (int) $row['total_queries'] ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( (int) $row['rate_limited_count'] ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( (int) $row['synthesis_attempts'] ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( (int) $row['synthesis_successes'] ) ); ?></td>
                            <td><?php echo esc_html( round( (float) $row['avg_latency_ms'], 0 ) ); ?></td>
                            <td><?php echo esc_html( round( (float) $row['avg_result_count'], 1 ) ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <p><?php esc_html_e( 'No search data recorded yet.', 'kh-editorial-intelligence' ); ?></p>
                <?php endif; ?>
            </div>

            <!-- Recent queries -->
            <div id="kh-search-recent" style="margin-top: 24px;">
                <h2><?php esc_html_e( 'Recent Queries (Last 50)', 'kh-editorial-intelligence' ); ?></h2>
                <?php if ( ! empty( $recent ) ) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Time', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Query', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Source', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Blog', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Results', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Latency (ms)', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Synthesis', 'kh-editorial-intelligence' ); ?></th>
                            <th><?php esc_html_e( 'Rate Limited', 'kh-editorial-intelligence' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $recent as $entry ) : ?>
                        <tr>
                            <td><?php echo esc_html( $entry['created_at'] ); ?></td>
                            <td><code><?php echo esc_html( mb_substr( $entry['query'], 0, 60 ) ); ?></code></td>
                            <td><?php echo esc_html( $entry['source'] ); ?></td>
                            <td><?php echo esc_html( $entry['blog_id'] ); ?></td>
                            <td><?php echo esc_html( $entry['result_count'] ); ?></td>
                            <td><?php echo esc_html( $entry['latency_ms'] ); ?></td>
                            <td style="<?php echo ! empty( $entry['answer_synthesized'] ) ? ( ! empty( $entry['answer_success'] ) ? 'color: #46b450;' : 'color: #d63638;' ) : ''; ?>">
                                <?php
                                if ( ! empty( $entry['answer_synthesized'] ) ) {
                                    echo ! empty( $entry['answer_success'] ) ? '✅ ' . esc_html__( 'OK', 'kh-editorial-intelligence' ) : '❌ ' . esc_html__( 'Fail', 'kh-editorial-intelligence' );
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td><?php echo ! empty( $entry['rate_limited'] ) ? '⚠️' : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <p><?php esc_html_e( 'No recent queries.', 'kh-editorial-intelligence' ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}