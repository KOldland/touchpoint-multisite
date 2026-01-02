<?php
namespace KH_SMMA\Admin;

use KH_SMMA\Security\CapabilityManager;
use wpdb;

use function absint;
use function add_action;
use function add_submenu_page;
use function add_query_arg;
use function admin_url;
use function check_admin_referer;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function paginate_links;
use function sanitize_text_field;
use function selected;
use function submit_button;
use function wp_die;
use function wp_nonce_field;
use function wp_safe_redirect;
use function fputcsv;
use function fopen;
use function fclose;
use function header;
use function __;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AuditLogPage {
    private wpdb $db;
    private string $table;
    private int $per_page = 25;

    public function __construct( wpdb $db ) {
        $this->db    = $db;
        $this->table = $this->db->prefix . 'kh_smma_audit_log';
    }

    public function register(): void {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_post_kh_smma_export_audit', array( $this, 'export_csv' ) );
    }

    public function add_menu(): void {
        add_submenu_page(
            'kh-smma-dashboard',
            __( 'KH Social Audit Log', 'kh-smma' ),
            __( 'Audit Log', 'kh-smma' ),
            CapabilityManager::CAP_MANAGE,
            'kh-smma-audit-log',
            array( $this, 'render_page' )
        );
    }

    public function render_page(): void {
        if ( ! CapabilityManager::can_manage_accounts() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        $filters   = $this->get_filters();
        $result    = $this->query_logs( $filters );
        $logs      = $result['items'];
        $total     = $result['total'];
        $total_pages = max( 1, (int) ceil( $total / $this->per_page ) );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Social Manager Audit Log', 'kh-smma' ); ?></h1>
            <p><?php esc_html_e( 'All queue/account actions are logged for compliance. Filter, review, or export as needed.', 'kh-smma' ); ?></p>

            <form method="get" class="kh-sma-filters">
                <input type="hidden" name="page" value="kh-smma-audit-log" />
                <label>
                    <?php esc_html_e( 'Action', 'kh-smma' ); ?>
                    <select name="action_filter">
                        <option value=""><?php esc_html_e( 'All actions', 'kh-smma' ); ?></option>
                        <?php foreach ( $this->get_distinct_actions() as $action ) : ?>
                            <option value="<?php echo esc_attr( $action ); ?>" <?php selected( $filters['action_filter'], $action ); ?>><?php echo esc_html( $action ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <?php esc_html_e( 'From', 'kh-smma' ); ?>
                    <input type="date" name="date_start" value="<?php echo esc_attr( $filters['date_start'] ); ?>" />
                </label>
                <label>
                    <?php esc_html_e( 'To', 'kh-smma' ); ?>
                    <input type="date" name="date_end" value="<?php echo esc_attr( $filters['date_end'] ); ?>" />
                </label>
                <label>
                    <?php esc_html_e( 'Search details', 'kh-smma' ); ?>
                    <input type="search" name="details" value="<?php echo esc_attr( $filters['details'] ); ?>" />
                </label>
                <?php submit_button( __( 'Filter', 'kh-smma' ), 'secondary', '', false ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'kh_smma_export_audit' ); ?>
                <input type="hidden" name="action" value="kh_smma_export_audit" />
                <?php submit_button( __( 'Export CSV', 'kh-smma' ), 'secondary' ); ?>
            </form>

            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Date', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'User', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Object', 'kh-smma' ); ?></th>
                        <th><?php esc_html_e( 'Details', 'kh-smma' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $logs ) ) : ?>
                        <tr><td colspan="5"><?php esc_html_e( 'No log entries found for the selected filters.', 'kh-smma' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr>
                                <td><?php echo esc_html( $log->created_at ); ?></td>
                                <td><?php echo esc_html( $log->user_id ); ?></td>
                                <td><code><?php echo esc_html( $log->action ); ?></code></td>
                                <td><?php echo esc_html( sprintf( '%s #%d', $log->object_type, $log->object_id ) ); ?></td>
                                <td><textarea readonly rows="2" class="widefat"><?php echo esc_html( $log->details ); ?></textarea></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links( array(
                        'base'      => add_query_arg( array_merge( $filters, array( 'paged' => '%#%' ) ), admin_url( 'admin.php?page=kh-smma-audit-log' ) ),
                        'format'    => '%#%',
                        'current'   => max( 1, $filters['paged'] ),
                        'total'     => $total_pages,
                        'prev_text' => __( '&laquo;', 'kh-smma' ),
                        'next_text' => __( '&raquo;', 'kh-smma' ),
                    ) );
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function export_csv(): void {
        if ( ! CapabilityManager::can_manage_accounts() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_export_audit' );

        $logs = $this->query_logs( array( 'paged' => 1, 'per_page' => 5000 ) )['items'];

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="kh-smma-audit-log.csv"' );
        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'created_at', 'user_id', 'action', 'object_type', 'object_id', 'details' ) );
        foreach ( $logs as $log ) {
            fputcsv( $output, array( $log->created_at, $log->user_id, $log->action, $log->object_type, $log->object_id, $log->details ) );
        }
        fclose( $output );
        exit;
    }

    private function get_filters(): array {
        $filters = array(
            'action_filter' => sanitize_text_field( $_GET['action_filter'] ?? '' ),
            'date_start'    => sanitize_text_field( $_GET['date_start'] ?? '' ),
            'date_end'      => sanitize_text_field( $_GET['date_end'] ?? '' ),
            'details'       => sanitize_text_field( $_GET['details'] ?? '' ),
            'paged'         => max( 1, absint( $_GET['paged'] ?? 1 ) ),
        );

        return $filters;
    }

    private function query_logs( array $filters ): array {
        $per_page = isset( $filters['per_page'] ) ? absint( $filters['per_page'] ) : $this->per_page;
        $offset   = ( max( 1, $filters['paged'] ) - 1 ) * $per_page;

        $where  = array( '1=1' );
        $params = array();

        if ( ! empty( $filters['action_filter'] ) ) {
            $where[]  = 'action = %s';
            $params[] = $filters['action_filter'];
        }

        if ( ! empty( $filters['date_start'] ) ) {
            $where[]  = 'created_at >= %s';
            $params[] = $filters['date_start'] . ' 00:00:00';
        }

        if ( ! empty( $filters['date_end'] ) ) {
            $where[]  = 'created_at <= %s';
            $params[] = $filters['date_end'] . ' 23:59:59';
        }

        if ( ! empty( $filters['details'] ) ) {
            $where[]  = 'details LIKE %s';
            $params[] = '%' . $this->db->esc_like( $filters['details'] ) . '%';
        }

        $where_sql = implode( ' AND ', $where );

        $query_sql = "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";

        $items = $this->db->get_results( $this->db->prepare( $query_sql, array_merge( $params, array( $per_page, $offset ) ) ) );

        if ( ! empty( $params ) ) {
            $count_sql = $this->db->prepare( $count_sql, $params );
        }

        $total = $this->db->get_var( $count_sql );

        return array(
            'items' => $items,
            'total' => (int) $total,
        );
    }

    private function get_distinct_actions(): array {
        $results = $this->db->get_col( "SELECT DISTINCT action FROM {$this->table} ORDER BY action ASC" );
        return $results ?: array();
    }
}
