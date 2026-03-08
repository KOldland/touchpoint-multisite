<?php
declare( strict_types=1 );

namespace KH_SMMA\Admin;

use KH_SMMA\Adapters\ReconciliationService;
use KH_SMMA\Security\CapabilityManager;
use KH_SMMA\Services\AuditLogger;

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
use function fputcsv;
use function fopen;
use function fclose;
use function header;
use function paginate_links;
use function sanitize_text_field;
use function selected;
use function submit_button;
use function wp_die;
use function wp_nonce_field;
use function wp_redirect;
use function wp_verify_nonce;
use function __;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PAID-08 — Paid Reconciliation Admin Dashboard.
 *
 * Provides a capability-gated admin page showing reconciliation runs, per-operation
 * detail rows, inline variance resolution, and CSV export. Follows the AuditLogPage
 * inline PHP/HTML pattern.
 */
class PaidReconciliationPage {

    private ReconciliationService $service;
    private AuditLogger $logger;
    private int $per_page = 25;

    public function __construct( ReconciliationService $service, AuditLogger $logger ) {
        $this->service = $service;
        $this->logger  = $logger;
    }

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
    }

    public function add_menu(): void {
        add_submenu_page(
            'kh-smma-dashboard',
            __( 'Paid Reconciliation', 'kh-smma' ),
            __( 'Reconciliation', 'kh-smma' ),
            CapabilityManager::CAP_MANAGE_PAID_ADAPTERS,
            'kh-smma-paid-recon',
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        if ( ! CapabilityManager::can_manage_paid_adapters() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        // Handle POST: start a new run.
        if ( isset( $_POST['kh_paid_recon_start_run'] ) ) {
            $this->handle_start_run();
            return;
        }

        // Handle POST: resolve a row.
        if ( isset( $_POST['kh_paid_recon_resolve_row'] ) ) {
            $this->handle_resolve_row();
            return;
        }

        // Handle GET: CSV export.
        if ( isset( $_GET['export'] ) && isset( $_GET['run_id'] ) ) {
            $this->handle_export();
            return;
        }

        // Dispatch: detail view or list view.
        if ( ! empty( $_GET['run_id'] ) ) {
            $this->render_run_detail( sanitize_text_field( $_GET['run_id'] ) );
        } else {
            $this->render_runs_list();
        }
    }

    // ── List view ─────────────────────────────────────────────────────────────

    private function render_runs_list(): void {
        $filters = $this->get_list_filters();
        $runs    = $this->service->list_runs( array_merge( $filters, [ 'per_page' => $this->per_page ] ) );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Paid Reconciliation Runs', 'kh-smma' ); ?></h1>

            <h2><?php esc_html_e( 'Start New Run', 'kh-smma' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=kh-smma-paid-recon' ) ); ?>">
                <?php wp_nonce_field( 'kh_paid_recon_start_run' ); ?>
                <input type="hidden" name="kh_paid_recon_start_run" value="1" />
                <table class="form-table">
                    <tr>
                        <th><label for="sponsor_id"><?php esc_html_e( 'Sponsor ID', 'kh-smma' ); ?></label></th>
                        <td><input type="text" id="sponsor_id" name="sponsor_id" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="adapter"><?php esc_html_e( 'Adapter (optional)', 'kh-smma' ); ?></label></th>
                        <td><input type="text" id="adapter" name="adapter" class="regular-text" placeholder="linkedin_sandbox,google_sandbox" /></td>
                    </tr>
                    <tr>
                        <th><label for="date_start"><?php esc_html_e( 'From date', 'kh-smma' ); ?></label></th>
                        <td><input type="date" id="date_start" name="date_start" /></td>
                    </tr>
                    <tr>
                        <th><label for="date_end"><?php esc_html_e( 'To date', 'kh-smma' ); ?></label></th>
                        <td><input type="date" id="date_end" name="date_end" /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Start Reconciliation Run', 'kh-smma' ) ); ?>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Recent Runs', 'kh-smma' ); ?></h2>
            <form method="get">
                <input type="hidden" name="page" value="kh-smma-paid-recon" />
                <label>
                    <?php esc_html_e( 'Status', 'kh-smma' ); ?>
                    <select name="status_filter">
                        <option value=""><?php esc_html_e( 'All', 'kh-smma' ); ?></option>
                        <?php foreach ( [ 'pending', 'running', 'completed', 'failed' ] as $s ) : ?>
                            <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $filters['status'], $s ); ?>><?php echo esc_html( $s ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php submit_button( __( 'Filter', 'kh-smma' ), 'secondary', '', false ); ?>
            </form>

            <?php $this->render_run_table( $runs ); ?>
        </div>
        <?php
    }

    private function render_run_table( array $runs ): void {
        if ( empty( $runs ) ) {
            echo '<p>' . esc_html__( 'No reconciliation runs found.', 'kh-smma' ) . '</p>';
            return;
        }
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Run ID', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Initiator', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Total', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Matched', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Variance', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Unmatched', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Run at', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'kh-smma' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $runs as $run ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $run['run_id'] ?? '' ); ?></code></td>
                        <td><?php echo esc_html( $run['status'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $run['initiator'] ?? '' ); ?></td>
                        <td><?php echo absint( $run['total_rows'] ?? 0 ); ?></td>
                        <td><?php echo absint( $run['matched_rows'] ?? 0 ); ?></td>
                        <td><?php echo absint( $run['variance_rows'] ?? 0 ); ?></td>
                        <td><?php echo absint( $run['unmatched_rows'] ?? 0 ); ?></td>
                        <td><?php echo esc_html( $run['run_at'] ?? '' ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=kh-smma-paid-recon&run_id=' . urlencode( $run['run_id'] ?? '' ) ) ); ?>">
                                <?php esc_html_e( 'View', 'kh-smma' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // ── Detail view ───────────────────────────────────────────────────────────

    private function render_run_detail( string $run_id ): void {
        $run = $this->service->get_run( $run_id );
        if ( $run === null ) {
            wp_die( esc_html__( 'Run not found.', 'kh-smma' ) );
        }

        $row_filters = [
            'status'     => sanitize_text_field( $_GET['row_status'] ?? '' ),
            'sponsor_id' => sanitize_text_field( $_GET['sponsor_id'] ?? '' ),
            'adapter'    => sanitize_text_field( $_GET['adapter'] ?? '' ),
            'per_page'   => 50,
            'paged'      => max( 1, absint( $_GET['paged'] ?? 1 ) ),
        ];

        $rows = $this->service->get_run_rows( $run_id, $row_filters );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( sprintf( __( 'Reconciliation Run: %s', 'kh-smma' ), $run_id ) ); ?></h1>

            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=kh-smma-paid-recon' ) ); ?>">
                    &larr; <?php esc_html_e( 'Back to runs', 'kh-smma' ); ?>
                </a>
            </p>

            <table class="form-table">
                <tr><th><?php esc_html_e( 'Status', 'kh-smma' ); ?></th><td><?php echo esc_html( $run['status'] ?? '' ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Initiator', 'kh-smma' ); ?></th><td><?php echo esc_html( $run['initiator'] ?? '' ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Run at', 'kh-smma' ); ?></th><td><?php echo esc_html( $run['run_at'] ?? '' ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Completed at', 'kh-smma' ); ?></th><td><?php echo esc_html( $run['completed_at'] ?? '' ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Total rows', 'kh-smma' ); ?></th><td><?php echo absint( $run['total_rows'] ?? 0 ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Matched', 'kh-smma' ); ?></th><td><?php echo absint( $run['matched_rows'] ?? 0 ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Variance', 'kh-smma' ); ?></th><td><?php echo absint( $run['variance_rows'] ?? 0 ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Unmatched', 'kh-smma' ); ?></th><td><?php echo absint( $run['unmatched_rows'] ?? 0 ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Checksum', 'kh-smma' ); ?></th><td><code><?php echo esc_html( $run['checksum'] ?? '' ); ?></code></td></tr>
            </table>

            <p>
                <a class="button" href="<?php echo esc_url( add_query_arg( [
                    'page'   => 'kh-smma-paid-recon',
                    'run_id' => $run_id,
                    'export' => '1',
                    '_wpnonce' => wp_create_nonce( 'kh_paid_recon_export_' . $run_id ),
                ], admin_url( 'admin.php' ) ) ); ?>">
                    <?php esc_html_e( 'Export CSV', 'kh-smma' ); ?>
                </a>
            </p>

            <h2><?php esc_html_e( 'Detail Rows', 'kh-smma' ); ?></h2>

            <form method="get">
                <input type="hidden" name="page" value="kh-smma-paid-recon" />
                <input type="hidden" name="run_id" value="<?php echo esc_attr( $run_id ); ?>" />
                <label>
                    <?php esc_html_e( 'Status', 'kh-smma' ); ?>
                    <select name="row_status">
                        <option value=""><?php esc_html_e( 'All', 'kh-smma' ); ?></option>
                        <?php foreach ( [ 'matched', 'variance', 'unmatched', 'resolved' ] as $s ) : ?>
                            <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $row_filters['status'], $s ); ?>><?php echo esc_html( $s ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php submit_button( __( 'Filter', 'kh-smma' ), 'secondary', '', false ); ?>
            </form>

            <?php $this->render_rows_table( $rows, $run_id ); ?>
        </div>
        <?php
    }

    private function render_rows_table( array $rows, string $run_id ): void {
        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'No rows found for the selected filters.', 'kh-smma' ) . '</p>';
            return;
        }
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Row ID', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Provider Ref', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Adapter', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Sponsor', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Expected ¢', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Actual ¢', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Variance ¢', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Var %', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Currency', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Resolved at', 'kh-smma' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'kh-smma' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rows as $row ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $row['row_id'] ?? '' ); ?></code></td>
                        <td><code><?php echo esc_html( $row['provider_reference'] ?? '' ); ?></code></td>
                        <td><?php echo esc_html( $row['adapter'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $row['sponsor_id'] ?? '' ); ?></td>
                        <td><?php echo (int) ( $row['expected_cost_cents'] ?? 0 ); ?></td>
                        <td><?php echo (int) ( $row['actual_cost_cents'] ?? 0 ); ?></td>
                        <td><?php echo (int) ( $row['variance_cents'] ?? 0 ); ?></td>
                        <td><?php echo esc_html( number_format( (float) ( $row['variance_pct'] ?? 0 ), 2 ) ); ?>%</td>
                        <td><?php echo esc_html( $row['currency'] ?? '' ); ?></td>
                        <td><strong><?php echo esc_html( $row['status'] ?? '' ); ?></strong></td>
                        <td><?php echo esc_html( $row['resolved_at'] ?? '' ); ?></td>
                        <td>
                            <?php if ( in_array( $row['status'] ?? '', [ 'variance', 'unmatched' ], true ) ) : ?>
                                <?php $this->resolve_row_form( $run_id, $row['row_id'] ?? '' ); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function resolve_row_form( string $run_id, string $row_id ): void {
        ?>
        <form method="post" style="display:inline-block;">
            <?php wp_nonce_field( 'kh_paid_recon_resolve_' . $row_id ); ?>
            <input type="hidden" name="kh_paid_recon_resolve_row" value="1" />
            <input type="hidden" name="run_id" value="<?php echo esc_attr( $run_id ); ?>" />
            <input type="hidden" name="row_id" value="<?php echo esc_attr( $row_id ); ?>" />
            <input type="text" name="resolve_note" placeholder="<?php esc_attr_e( 'Resolution note', 'kh-smma' ); ?>" required class="small-text" />
            <button type="submit" class="button button-small"><?php esc_html_e( 'Resolve', 'kh-smma' ); ?></button>
        </form>
        <?php
    }

    // ── Form handlers ─────────────────────────────────────────────────────────

    private function handle_start_run(): void {
        check_admin_referer( 'kh_paid_recon_start_run' );

        $sponsor_id = sanitize_text_field( $_POST['sponsor_id'] ?? '' );
        $adapter    = sanitize_text_field( $_POST['adapter'] ?? '' );
        $date_start = sanitize_text_field( $_POST['date_start'] ?? '' );
        $date_end   = sanitize_text_field( $_POST['date_end'] ?? '' );

        $adapters = array_filter( array_map( 'trim', explode( ',', $adapter ) ) );

        $current_user = wp_get_current_user();
        $initiator    = $current_user->user_login ?: 'admin';

        $run = $this->service->start_run( [
            'sponsor_id' => $sponsor_id,
            'adapters'   => $adapters,
            'date_start' => $date_start,
            'date_end'   => $date_end,
            'initiator'  => $initiator,
        ] );

        $this->service->execute_run( $run['run_id'] );

        wp_redirect( admin_url( 'admin.php?page=kh-smma-paid-recon&run_id=' . urlencode( $run['run_id'] ) ) );
        exit;
    }

    private function handle_resolve_row(): void {
        $row_id = sanitize_text_field( $_POST['row_id'] ?? '' );
        check_admin_referer( 'kh_paid_recon_resolve_' . $row_id );

        $run_id = sanitize_text_field( $_POST['run_id'] ?? '' );
        $note   = sanitize_text_field( $_POST['resolve_note'] ?? '' );

        $this->service->resolve_row( $row_id, 'resolved', $note, get_current_user_id() );

        wp_redirect( admin_url( 'admin.php?page=kh-smma-paid-recon&run_id=' . urlencode( $run_id ) ) );
        exit;
    }

    private function handle_export(): void {
        $run_id  = sanitize_text_field( $_GET['run_id'] ?? '' );
        $nonce   = sanitize_text_field( $_GET['_wpnonce'] ?? '' );

        if ( ! wp_verify_nonce( $nonce, 'kh_paid_recon_export_' . $run_id ) ) {
            wp_die( esc_html__( 'Invalid nonce.', 'kh-smma' ) );
        }

        $export = $this->service->export_run( $run_id, get_current_user_id() );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="recon_run_' . sanitize_file_name( $run_id ) . '.csv"' );
        echo $export['csv']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    // ── Filter helpers ────────────────────────────────────────────────────────

    private function get_list_filters(): array {
        return [
            'status'     => sanitize_text_field( $_GET['status_filter'] ?? '' ),
            'initiator'  => sanitize_text_field( $_GET['initiator'] ?? '' ),
            'date_start' => sanitize_text_field( $_GET['date_start'] ?? '' ),
            'date_end'   => sanitize_text_field( $_GET['date_end'] ?? '' ),
            'paged'      => max( 1, absint( $_GET['paged'] ?? 1 ) ),
        ];
    }
}
