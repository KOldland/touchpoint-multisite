<?php
/**
 * Settlement Worker
 *
 * Batches reconciled/discrepancy rows into sponsor-level settlements.
 * Groups rows by sponsor_id + currency, sums compute_settled_amount()
 * per row, applies FX conversion, and writes the settlement ledger.
 *
 * Runs on demand via REST (POST /reconciliations/settle) or WP-CLI
 * (wp kh-smma paid:settle) and on a daily WP-cron schedule.
 *
 * @package KH_SMMA\Reconciliation
 * @see     docs/paid/finance_reconciliation_runbook.md
 */

namespace KH_SMMA\Reconciliation;

use KH_SMMA\Services\AuditLogger;
use wpdb;

use function current_time;
use function do_action;
use function wp_schedule_event;
use function wp_next_scheduled;
use function add_action;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettlementWorker {

    /** @var wpdb */
    private $db;

    /** @var PaidReconciliationAdjustmentService */
    private $adj_service;

    /** @var FxService */
    private $fx;

    /** @var AuditLogger */
    private $logger;

    /** @var string */
    private $settlements_table;

    /** @var string */
    private $reconciliations_table;

    public function __construct(
        wpdb $db,
        PaidReconciliationAdjustmentService $adj_service,
        FxService $fx,
        AuditLogger $logger
    ) {
        $this->db                    = $db;
        $this->adj_service           = $adj_service;
        $this->fx                    = $fx;
        $this->logger                = $logger;
        $this->settlements_table     = $this->db->prefix . 'kh_paid_settlements';
        $this->reconciliations_table = $this->db->prefix . 'kh_paid_reconciliations';
    }

    /**
     * Create the settlements table if it doesn't exist.
     */
    public function install(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $this->db->get_charset_collate();
        $table           = $this->settlements_table;

        $sql = "CREATE TABLE {$table} (
            settlement_id VARCHAR(32) NOT NULL,
            sponsor_id VARCHAR(100) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'AUD',
            total_settled DECIMAL(14,4) NOT NULL,
            fx_rate DECIMAL(10,6) NOT NULL DEFAULT 1.000000,
            settled_at DATETIME NOT NULL,
            reconciliation_ids LONGTEXT NOT NULL,
            batch_size INT UNSIGNED NOT NULL DEFAULT 0,
            notes TEXT,
            PRIMARY KEY  (settlement_id),
            KEY sponsor_id (sponsor_id),
            KEY currency (currency),
            KEY settled_at (settled_at)
        ) {$charset_collate};";

        \dbDelta( $sql );
    }

    /**
     * Register the WP-cron hook for scheduled settlement runs.
     */
    public function register(): void {
        add_action( 'kh_smma_run_settlement', [ $this, 'run_scheduled' ] );
    }

    /**
     * Cron callback — runs settlement with default filters.
     */
    public function run_scheduled(): void {
        $this->run();
    }

    /**
     * Run the settlement batch.
     *
     * Collects reconciled/discrepancy rows without a settlement_id,
     * groups by sponsor_id + currency, computes settled amounts,
     * applies FX if target_currency differs, writes settlement rows,
     * and marks each reconciliation as settled.
     *
     * @param array $filters {
     *     @type string $sponsor_id       Filter to a single sponsor.
     *     @type string $currency         Source currency filter.
     *     @type string $target_currency  Convert totals to this currency (default: same as source).
     *     @type string $date_start       YYYY-MM-DD lower bound on created_at.
     *     @type string $date_end         YYYY-MM-DD upper bound on created_at.
     *     @type int    $batch_size       Max rows to process (default 500).
     * }
     * @return array Array of created settlement rows.
     */
    public function run( array $filters = [] ): array {
        $batch_size      = isset( $filters['batch_size'] ) ? max( 1, (int) $filters['batch_size'] ) : 500;
        $target_currency = $filters['target_currency'] ?? null;

        // ── 1. Query unsettled reconciliations ───────────────────────────────
        $where  = [ "status IN ('reconciled','discrepancy')", 'settlement_id IS NULL' ];
        $params = [];

        if ( ! empty( $filters['sponsor_id'] ) ) {
            $where[]  = 'sponsor_id = %s';
            $params[] = $filters['sponsor_id'];
        }

        if ( ! empty( $filters['currency'] ) ) {
            $where[]  = 'currency = %s';
            $params[] = strtoupper( $filters['currency'] );
        }

        if ( ! empty( $filters['date_start'] ) ) {
            $where[]  = 'created_at >= %s';
            $params[] = $filters['date_start'] . ' 00:00:00';
        }

        if ( ! empty( $filters['date_end'] ) ) {
            $where[]  = 'created_at <= %s';
            $params[] = $filters['date_end'] . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $where );
        $query_sql = "SELECT * FROM {$this->reconciliations_table} WHERE {$where_sql} ORDER BY created_at ASC LIMIT %d";

        $rows = ! empty( $params )
            ? $this->db->get_results(
                $this->db->prepare( $query_sql, array_merge( $params, [ $batch_size ] ) ),
                ARRAY_A
            )
            : $this->db->get_results(
                $this->db->prepare( $query_sql, $batch_size ),
                ARRAY_A
            );

        if ( empty( $rows ) ) {
            return [];
        }

        // ── 2. Group by sponsor_id + currency ────────────────────────────────
        $groups = [];
        foreach ( $rows as $row ) {
            $key = $row['sponsor_id'] . '|' . $row['currency'];
            $groups[ $key ][] = $row;
        }

        $settlements = [];
        $now         = current_time( 'mysql' );

        // ── 3. Process each group ────────────────────────────────────────────
        foreach ( $groups as $group_key => $group_rows ) {
            $first_row = $group_rows[0];
            $sponsor_id = $first_row['sponsor_id'];
            $currency   = $first_row['currency'];
            $dest_currency = $target_currency ?? $currency;

            // Sum settled amounts (actual_spend + adjustments) per row.
            $total_settled = 0.0;
            foreach ( $group_rows as $rec ) {
                $total_settled += $this->adj_service->compute_settled_amount(
                    $rec['reconciliation_id'],
                    (float) $rec['actual_spend']
                );
            }

            // Apply FX conversion.
            $fx_rate       = $this->fx->get_rate( $currency, $dest_currency );
            $total_in_dest = $this->fx->convert( $total_settled, $currency, $dest_currency );

            // Compute deterministic settlement_id.
            $settlement_id = 'sett_' . substr(
                hash( 'sha256', $sponsor_id . '|' . $currency . '|' . $now ),
                0, 12
            );

            $rec_ids = array_column( $group_rows, 'reconciliation_id' );

            $settlement_row = [
                'settlement_id'      => $settlement_id,
                'sponsor_id'         => $sponsor_id,
                'currency'           => $currency,
                'total_settled'      => $total_in_dest,
                'fx_rate'            => $fx_rate,
                'settled_at'         => $now,
                'reconciliation_ids' => wp_json_encode( $rec_ids ),
                'batch_size'         => count( $group_rows ),
                'notes'              => null,
            ];

            $this->db->insert( $this->settlements_table, $settlement_row );

            // Mark each reconciliation as settled.
            foreach ( $rec_ids as $rec_id ) {
                $this->db->update(
                    $this->reconciliations_table,
                    [
                        'status'        => 'settled',
                        'settlement_id' => $settlement_id,
                        'updated_at'    => $now,
                    ],
                    [ 'reconciliation_id' => $rec_id ]
                );
            }

            // Audit and fire hook.
            $this->logger->log( 'paid_settlement.complete', [
                'object_type' => 'settlement',
                'details'     => [
                    'settlement_id'      => $settlement_id,
                    'sponsor_id'         => $sponsor_id,
                    'currency'           => $currency,
                    'total_settled'      => $total_in_dest,
                    'fx_rate'            => $fx_rate,
                    'batch_size'         => count( $group_rows ),
                    'reconciliation_ids' => $rec_ids,
                ],
            ] );

            do_action( 'kh_paid_settlement_complete', $settlement_row );

            $settlements[] = $settlement_row;
        }

        return $settlements;
    }

    /**
     * Retrieve a single settlement row by settlement_id.
     *
     * @param string $settlement_id
     * @return array|null
     */
    public function get_settlement( string $settlement_id ): ?array {
        $result = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->settlements_table} WHERE settlement_id = %s",
                $settlement_id
            ),
            ARRAY_A
        );
        return $result ?: null;
    }

    /**
     * Export a settlement ledger as a CSV string.
     *
     * Columns: settlement_id, sponsor_id, currency, total_settled, fx_rate,
     *          settled_at, reconciliation_ids, batch_size
     *
     * @param string $settlement_id
     * @return string CSV string (including header row).
     * @throws \RuntimeException If settlement_id not found.
     */
    public function export_ledger_csv( string $settlement_id ): string {
        $row = $this->get_settlement( $settlement_id );

        if ( null === $row ) {
            throw new \RuntimeException( "Settlement not found: {$settlement_id}" );
        }

        $columns = [
            'settlement_id', 'sponsor_id', 'currency', 'total_settled',
            'fx_rate', 'settled_at', 'reconciliation_ids', 'batch_size',
        ];

        $output  = fopen( 'php://temp', 'r+' );
        fputcsv( $output, $columns );

        $data_row = [];
        foreach ( $columns as $col ) {
            $data_row[] = $row[ $col ] ?? '';
        }
        fputcsv( $output, $data_row );

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv;
    }
}
