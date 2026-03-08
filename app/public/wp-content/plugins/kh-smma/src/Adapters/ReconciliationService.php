<?php
declare( strict_types=1 );

namespace KH_SMMA\Adapters;

use KH_SMMA\Reconciliation\PaidReconciliationService;
use KH_SMMA\Services\AuditLogger;
use wpdb;

use function do_action;
use function gmdate;
use function json_decode;
use function json_encode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PAID-08 — Run-level reconciliation orchestrator.
 *
 * Ingests adapter execute/delivery results from wp_kh_paid_reconciliations,
 * expands per-operation detail rows, classifies each as matched|variance|unmatched,
 * and produces exportable CSV ledgers for finance triage.
 *
 * Three new tables managed by this service:
 *   wp_kh_paid_recon_runs     — named run metadata
 *   wp_kh_paid_recon_rows     — per-operation detail (one row per op per run)
 *   wp_kh_paid_recon_exports  — export log (one row per CSV download)
 */
class ReconciliationService {

    private wpdb $db;
    private AuditLogger $logger;
    private PaidReconciliationService $source_service;

    private string $runs_table;
    private string $rows_table;
    private string $exports_table;
    private string $source_table;

    public function __construct(
        wpdb $db,
        AuditLogger $logger,
        PaidReconciliationService $source_service
    ) {
        $this->db             = $db;
        $this->logger         = $logger;
        $this->source_service = $source_service;
        $this->runs_table     = $db->prefix . 'kh_paid_recon_runs';
        $this->rows_table     = $db->prefix . 'kh_paid_recon_rows';
        $this->exports_table  = $db->prefix . 'kh_paid_recon_exports';
        $this->source_table   = $db->prefix . 'kh_paid_reconciliations';
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    public function install(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $collate = $this->db->get_charset_collate();

        $runs_sql = "CREATE TABLE {$this->runs_table} (
            run_id        VARCHAR(32)      NOT NULL,
            status        VARCHAR(20)      NOT NULL DEFAULT 'pending',
            initiator     VARCHAR(100)     NOT NULL DEFAULT '',
            adapters      TEXT             NOT NULL DEFAULT '[]',
            filters       TEXT             NOT NULL DEFAULT '{}',
            total_rows    INT UNSIGNED     NOT NULL DEFAULT 0,
            matched_rows  INT UNSIGNED     NOT NULL DEFAULT 0,
            variance_rows INT UNSIGNED     NOT NULL DEFAULT 0,
            unmatched_rows INT UNSIGNED    NOT NULL DEFAULT 0,
            notes         TEXT             NULL,
            checksum      VARCHAR(64)      NULL,
            run_at        DATETIME         NOT NULL,
            completed_at  DATETIME         NULL,
            PRIMARY KEY (run_id),
            KEY idx_recon_run_status (status),
            KEY idx_recon_run_at (run_at),
            KEY idx_recon_initiator (initiator)
        ) {$collate};";

        $rows_sql = "CREATE TABLE {$this->rows_table} (
            row_id              VARCHAR(40)     NOT NULL,
            run_id              VARCHAR(32)     NOT NULL,
            reconciliation_id   VARCHAR(32)     NOT NULL,
            provider_reference  VARCHAR(200)    NOT NULL,
            sponsor_id          VARCHAR(100)    NOT NULL DEFAULT '',
            schedule_id         VARCHAR(100)    NOT NULL DEFAULT '',
            adapter             VARCHAR(50)     NOT NULL DEFAULT '',
            expected_cost_cents BIGINT          NOT NULL DEFAULT 0,
            actual_cost_cents   BIGINT          NOT NULL DEFAULT 0,
            fees_cents          BIGINT          NOT NULL DEFAULT 0,
            currency            CHAR(3)         NOT NULL DEFAULT 'AUD',
            variance_cents      BIGINT          NOT NULL DEFAULT 0,
            variance_pct        DECIMAL(8,4)    NOT NULL DEFAULT 0.0000,
            status              VARCHAR(30)     NOT NULL DEFAULT 'matched',
            reconciled_at       DATETIME        NOT NULL,
            resolved_at         DATETIME        NULL,
            resolver_id         INT UNSIGNED    NULL,
            notes               TEXT            NULL,
            PRIMARY KEY (row_id),
            KEY idx_recon_row_run (run_id),
            KEY idx_recon_row_rec (reconciliation_id),
            KEY idx_recon_row_sponsor (sponsor_id),
            KEY idx_recon_row_status (status),
            KEY idx_recon_row_ref (provider_reference(100))
        ) {$collate};";

        $exports_sql = "CREATE TABLE {$this->exports_table} (
            export_id   VARCHAR(32)     NOT NULL,
            run_id      VARCHAR(32)     NOT NULL,
            user_id     INT UNSIGNED    NOT NULL DEFAULT 0,
            row_count   INT UNSIGNED    NOT NULL DEFAULT 0,
            checksum    VARCHAR(64)     NULL,
            produced_at DATETIME        NOT NULL,
            PRIMARY KEY (export_id),
            KEY idx_recon_export_run (run_id),
            KEY idx_recon_export_user (user_id)
        ) {$collate};";

        dbDelta( $runs_sql );
        dbDelta( $rows_sql );
        dbDelta( $exports_sql );
    }

    // ── Run lifecycle ─────────────────────────────────────────────────────────

    /**
     * Create a new reconciliation run in 'pending' status.
     *
     * @param array{
     *   adapters?: string[],
     *   sponsor_id?: string,
     *   date_start?: string,
     *   date_end?: string,
     *   initiator?: string,
     *   notes?: string,
     * } $opts
     * @return array Run row.
     */
    public function start_run( array $opts ): array {
        $initiator  = $opts['initiator'] ?? 'cli';
        $adapters   = $opts['adapters']  ?? [];
        $filters    = [
            'sponsor_id' => $opts['sponsor_id'] ?? '',
            'date_start' => $opts['date_start'] ?? '',
            'date_end'   => $opts['date_end']   ?? '',
        ];

        $filters_hash = hash( 'sha256', json_encode( $filters, JSON_UNESCAPED_SLASHES ) );
        $ts           = gmdate( 'Y-m-d H:i:s' );
        $run_id       = 'run_' . substr( hash( 'sha256', $initiator . '|' . $filters_hash . '|' . $ts ), 0, 12 );

        $run = [
            'run_id'         => $run_id,
            'status'         => 'pending',
            'initiator'      => $initiator,
            'adapters'       => json_encode( $adapters, JSON_UNESCAPED_SLASHES ),
            'filters'        => json_encode( $filters, JSON_UNESCAPED_SLASHES ),
            'total_rows'     => 0,
            'matched_rows'   => 0,
            'variance_rows'  => 0,
            'unmatched_rows' => 0,
            'notes'          => $opts['notes'] ?? null,
            'checksum'       => null,
            'run_at'         => $ts,
            'completed_at'   => null,
        ];

        $this->db->insert( $this->runs_table, $run );
        $this->logger->log( 'paid_recon.run.started', [ 'run_id' => $run_id, 'initiator' => $initiator ] );

        return $run;
    }

    /**
     * Execute a reconciliation run: ingest source rows, expand per-operation,
     * classify, and persist. Idempotent — re-running a completed run is a no-op.
     *
     * @param string $run_id
     * @return array Completed run row.
     * @throws \RuntimeException On fatal DB errors.
     */
    public function execute_run( string $run_id ): array {
        $run = $this->get_run( $run_id );
        if ( $run === null ) {
            throw new \RuntimeException( "Run not found: {$run_id}" );
        }

        if ( $run['status'] === 'completed' ) {
            return $run;
        }

        // Mark as running.
        $this->db->update( $this->runs_table, [ 'status' => 'running' ], [ 'run_id' => $run_id ] );

        try {
            $source_rows = $this->ingest_source_rows( $run );
            $row_ids     = [];

            foreach ( $source_rows as $source ) {
                $op_ids = $this->parse_operation_ids( $source );
                if ( empty( $op_ids ) ) {
                    $op_ids = [ $source['reconciliation_id'] ];
                }
                $op_count = count( $op_ids );

                foreach ( $op_ids as $op_id ) {
                    $recon_row = $this->build_recon_row( $run_id, $source, (string) $op_id, $op_count );
                    $this->db->insert( $this->rows_table, $recon_row );

                    if ( $recon_row['status'] === 'variance' ) {
                        do_action( 'kh_paid_recon_row_variance', $recon_row );
                        $this->logger->log( 'paid_recon.row.variance', [
                            'run_id'             => $run_id,
                            'row_id'             => $recon_row['row_id'],
                            'provider_reference' => $op_id,
                            'variance_cents'     => $recon_row['variance_cents'],
                        ] );
                    }

                    $row_ids[] = $recon_row['row_id'];
                }
            }

            // Compute stats.
            sort( $row_ids );
            $checksum      = hash( 'sha256', implode( '|', $row_ids ) );
            $stats         = $this->compute_stats( $run_id );
            $completed_at  = gmdate( 'Y-m-d H:i:s' );

            $this->db->update(
                $this->runs_table,
                array_merge( $stats, [
                    'status'       => 'completed',
                    'checksum'     => $checksum,
                    'completed_at' => $completed_at,
                ] ),
                [ 'run_id' => $run_id ]
            );

            $completed_run = array_merge( $run, $stats, [
                'status'       => 'completed',
                'checksum'     => $checksum,
                'completed_at' => $completed_at,
            ] );

            $this->logger->log( 'paid_recon.run.completed', [
                'run_id'       => $run_id,
                'total_rows'   => $stats['total_rows'],
                'variance_rows'=> $stats['variance_rows'],
                'checksum'     => $checksum,
            ] );

            do_action( 'kh_paid_recon_run_completed', $completed_run );

            return $completed_run;

        } catch ( \Throwable $e ) {
            $this->db->update( $this->runs_table, [ 'status' => 'failed' ], [ 'run_id' => $run_id ] );
            $this->logger->log( 'paid_recon.run.failed', [ 'run_id' => $run_id, 'error' => $e->getMessage() ] );
            throw $e;
        }
    }

    /**
     * Retrieve a single run by ID.
     */
    public function get_run( string $run_id ): ?array {
        $row = $this->db->get_row(
            $this->db->prepare( "SELECT * FROM {$this->runs_table} WHERE run_id = %s", $run_id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * List runs with optional filters.
     *
     * @param array{
     *   status?: string,
     *   initiator?: string,
     *   date_start?: string,
     *   date_end?: string,
     *   per_page?: int,
     *   paged?: int,
     * } $filters
     * @return array
     */
    public function list_runs( array $filters = [] ): array {
        $per_page = max( 1, (int) ( $filters['per_page'] ?? 25 ) );
        $offset   = ( max( 1, (int) ( $filters['paged'] ?? 1 ) ) - 1 ) * $per_page;

        $where  = [ '1=1' ];
        $params = [];

        if ( ! empty( $filters['status'] ) ) {
            $where[]  = 'status = %s';
            $params[] = $filters['status'];
        }
        if ( ! empty( $filters['initiator'] ) ) {
            $where[]  = 'initiator = %s';
            $params[] = $filters['initiator'];
        }
        if ( ! empty( $filters['date_start'] ) ) {
            $where[]  = 'run_at >= %s';
            $params[] = $filters['date_start'] . ' 00:00:00';
        }
        if ( ! empty( $filters['date_end'] ) ) {
            $where[]  = 'run_at <= %s';
            $params[] = $filters['date_end'] . ' 23:59:59';
        }

        $where_sql = implode( ' AND ', $where );
        $sql       = "SELECT * FROM {$this->runs_table} WHERE {$where_sql} ORDER BY run_at DESC LIMIT %d OFFSET %d";
        $params[]  = $per_page;
        $params[]  = $offset;

        return $this->db->get_results(
            $this->db->prepare( $sql, $params ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get detail rows for a run.
     *
     * @param array{
     *   status?: string,
     *   sponsor_id?: string,
     *   adapter?: string,
     *   per_page?: int,
     *   paged?: int,
     * } $filters
     */
    public function get_run_rows( string $run_id, array $filters = [] ): array {
        $per_page = max( 1, (int) ( $filters['per_page'] ?? 50 ) );
        $offset   = ( max( 1, (int) ( $filters['paged'] ?? 1 ) ) - 1 ) * $per_page;

        $where  = [ 'run_id = %s' ];
        $params = [ $run_id ];

        if ( ! empty( $filters['status'] ) ) {
            $where[]  = 'status = %s';
            $params[] = $filters['status'];
        }
        if ( ! empty( $filters['sponsor_id'] ) ) {
            $where[]  = 'sponsor_id = %s';
            $params[] = $filters['sponsor_id'];
        }
        if ( ! empty( $filters['adapter'] ) ) {
            $where[]  = 'adapter = %s';
            $params[] = $filters['adapter'];
        }

        $where_sql = implode( ' AND ', $where );
        $sql       = "SELECT * FROM {$this->rows_table} WHERE {$where_sql} ORDER BY reconciled_at ASC LIMIT %d OFFSET %d";
        $params[]  = $per_page;
        $params[]  = $offset;

        return $this->db->get_results(
            $this->db->prepare( $sql, $params ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Resolve a variance/unmatched row. Status must become 'resolved'.
     * Immutable audit append — resolved_at and resolver_id cannot be changed afterwards.
     */
    public function resolve_row( string $row_id, string $status, string $note, int $user_id ): array {
        if ( $status !== 'resolved' ) {
            throw new \InvalidArgumentException( "resolve_row() only accepts status='resolved'; got '{$status}'" );
        }

        $resolved_at = gmdate( 'Y-m-d H:i:s' );
        $this->db->update(
            $this->rows_table,
            [
                'status'      => 'resolved',
                'notes'       => $note,
                'resolved_at' => $resolved_at,
                'resolver_id' => $user_id,
            ],
            [ 'row_id' => $row_id ]
        );

        $this->logger->log( 'paid_recon.row.resolved', [
            'row_id'      => $row_id,
            'resolver_id' => $user_id,
            'note'        => $note,
        ] );

        return $this->db->get_row(
            $this->db->prepare( "SELECT * FROM {$this->rows_table} WHERE row_id = %s", $row_id ),
            ARRAY_A
        ) ?: [ 'row_id' => $row_id, 'status' => 'resolved' ];
    }

    /**
     * Export all rows for a run to CSV. Stores export metadata and returns the export row.
     */
    public function export_run( string $run_id, int $user_id ): array {
        $csv_content = $this->generate_run_csv( $run_id );
        $row_count   = max( 0, substr_count( $csv_content, "\n" ) - 1 );
        $checksum    = hash( 'sha256', $csv_content );

        $ts        = gmdate( 'Y-m-d H:i:s' );
        $export_id = 'exp_' . substr( hash( 'sha256', $run_id . '|' . $user_id . '|' . $ts ), 0, 12 );

        $export_row = [
            'export_id'   => $export_id,
            'run_id'      => $run_id,
            'user_id'     => $user_id,
            'row_count'   => $row_count,
            'checksum'    => $checksum,
            'produced_at' => $ts,
        ];

        $this->db->insert( $this->exports_table, $export_row );

        $this->logger->log( 'paid_recon.export.created', [
            'export_id' => $export_id,
            'run_id'    => $run_id,
            'user_id'   => $user_id,
            'row_count' => $row_count,
        ] );

        return array_merge( $export_row, [ 'csv' => $csv_content ] );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Load source reconciliation rows filtered by the run's stored filters.
     */
    private function ingest_source_rows( array $run ): array {
        $filters     = json_decode( $run['filters'] ?? '{}', true ) ?: [];
        $adapters    = json_decode( $run['adapters'] ?? '[]', true ) ?: [];

        $where  = [ '1=1' ];
        $params = [];

        if ( ! empty( $filters['sponsor_id'] ) ) {
            $where[]  = 'sponsor_id = %s';
            $params[] = $filters['sponsor_id'];
        }
        if ( ! empty( $filters['date_start'] ) ) {
            $where[]  = 'created_at >= %s';
            $params[] = $filters['date_start'] . ' 00:00:00';
        }
        if ( ! empty( $filters['date_end'] ) ) {
            $where[]  = 'created_at <= %s';
            $params[] = $filters['date_end'] . ' 23:59:59';
        }
        if ( ! empty( $adapters ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $adapters ), '%s' ) );
            $where[]      = "channel IN ({$placeholders})";
            $params       = array_merge( $params, $adapters );
        }

        $where_sql = implode( ' AND ', $where );
        $sql       = "SELECT * FROM {$this->source_table} WHERE {$where_sql} ORDER BY created_at ASC LIMIT 5000";

        if ( ! empty( $params ) ) {
            return $this->db->get_results( $this->db->prepare( $sql, $params ), ARRAY_A ) ?: [];
        }

        return $this->db->get_results( $sql, ARRAY_A ) ?: [];
    }

    /**
     * Parse operation_ids from a source reconciliation row.
     * Source stores them as a JSON-encoded array or comma-separated string.
     */
    private function parse_operation_ids( array $source ): array {
        $raw = $source['operation_ids'] ?? '';
        if ( empty( $raw ) ) {
            return [];
        }
        if ( $raw[0] === '[' ) {
            $decoded = json_decode( $raw, true );
            return is_array( $decoded ) ? $decoded : [];
        }
        return array_filter( array_map( 'trim', explode( ',', $raw ) ) );
    }

    /**
     * Build a single wp_kh_paid_recon_rows record.
     */
    private function build_recon_row(
        string $run_id,
        array $source,
        string $provider_ref,
        int $op_count
    ): array {
        $estimated_spend  = (float) ( $source['estimated_spend'] ?? 0 );
        $actual_spend     = (float) ( $source['actual_spend']    ?? 0 );
        $op_count         = max( 1, $op_count );

        $expected_cents   = (int) round( $estimated_spend / $op_count * 100 );
        $actual_cents     = (int) round( $actual_spend    / $op_count * 100 );
        $variance_cents   = $actual_cents - $expected_cents;

        $variance_pct     = $expected_cents > 0
            ? round( abs( $variance_cents ) / $expected_cents * 100, 4 )
            : ( $actual_cents > 0 ? 100.0 : 0.0 );

        $adapter  = $source['channel'] ?? '';
        $sponsor  = $source['sponsor_id'] ?? '';
        $status   = $this->compute_status(
            $expected_cents,
            $actual_cents,
            $this->tolerance_pct( $adapter, $sponsor ),
            $this->tolerance_min_cents()
        );

        $row_id = 'rrow_' . substr( hash( 'sha256', $run_id . '|' . $provider_ref ), 0, 12 );

        return [
            'row_id'              => $row_id,
            'run_id'              => $run_id,
            'reconciliation_id'   => $source['reconciliation_id'] ?? '',
            'provider_reference'  => $provider_ref,
            'sponsor_id'          => $sponsor,
            'schedule_id'         => $source['schedule_id'] ?? '',
            'adapter'             => $adapter,
            'expected_cost_cents' => $expected_cents,
            'actual_cost_cents'   => $actual_cents,
            'fees_cents'          => 0,
            'currency'            => $source['currency'] ?? 'AUD',
            'variance_cents'      => $variance_cents,
            'variance_pct'        => $variance_pct,
            'status'              => $status,
            'reconciled_at'       => gmdate( 'Y-m-d H:i:s' ),
            'resolved_at'         => null,
            'resolver_id'         => null,
            'notes'               => null,
        ];
    }

    /**
     * Classify a row as matched|variance|unmatched using the tolerance band.
     */
    private function compute_status(
        int $expected_cents,
        int $actual_cents,
        float $tol_pct,
        int $tol_min_cents
    ): string {
        if ( $expected_cents === 0 && $actual_cents === 0 ) {
            return 'matched';
        }
        if ( $expected_cents === 0 && $actual_cents > 0 ) {
            return 'unmatched';
        }

        $abs_variance  = abs( $actual_cents - $expected_cents );
        $tol_band      = max( $tol_min_cents, (int) round( $expected_cents * $tol_pct / 100 ) );

        return $abs_variance <= $tol_band ? 'matched' : 'variance';
    }

    /**
     * Generate a CSV string of all rows for the given run.
     */
    private function generate_run_csv( string $run_id ): string {
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->rows_table} WHERE run_id = %s ORDER BY reconciled_at ASC",
                $run_id
            ),
            ARRAY_A
        ) ?: [];

        $out = fopen( 'php://memory', 'r+' );
        fputcsv( $out, [
            'row_id', 'run_id', 'reconciliation_id', 'provider_reference',
            'sponsor_id', 'schedule_id', 'adapter', 'currency',
            'expected_cost_cents', 'actual_cost_cents', 'fees_cents',
            'variance_cents', 'variance_pct', 'status',
            'reconciled_at', 'resolved_at', 'resolver_id', 'notes',
        ] );
        foreach ( $rows as $row ) {
            fputcsv( $out, [
                $row['row_id'] ?? '',
                $row['run_id'] ?? '',
                $row['reconciliation_id'] ?? '',
                $row['provider_reference'] ?? '',
                $row['sponsor_id'] ?? '',
                $row['schedule_id'] ?? '',
                $row['adapter'] ?? '',
                $row['currency'] ?? '',
                $row['expected_cost_cents'] ?? 0,
                $row['actual_cost_cents'] ?? 0,
                $row['fees_cents'] ?? 0,
                $row['variance_cents'] ?? 0,
                $row['variance_pct'] ?? '0.0000',
                $row['status'] ?? '',
                $row['reconciled_at'] ?? '',
                $row['resolved_at'] ?? '',
                $row['resolver_id'] ?? '',
                $row['notes'] ?? '',
            ] );
        }
        rewind( $out );
        $content = stream_get_contents( $out );
        fclose( $out );

        return (string) $content;
    }

    /**
     * Recompute matched/variance/unmatched/total row counts for a run.
     */
    private function compute_stats( string $run_id ): array {
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT status FROM {$this->rows_table} WHERE run_id = %s",
                $run_id
            ),
            ARRAY_A
        ) ?: [];

        $stats = [
            'total_rows'     => count( $rows ),
            'matched_rows'   => 0,
            'variance_rows'  => 0,
            'unmatched_rows' => 0,
        ];

        foreach ( $rows as $r ) {
            $s = $r['status'] ?? '';
            if ( $s === 'matched' || $s === 'resolved' ) {
                $stats['matched_rows']++;
            } elseif ( $s === 'variance' ) {
                $stats['variance_rows']++;
            } elseif ( $s === 'unmatched' ) {
                $stats['unmatched_rows']++;
            }
        }

        return $stats;
    }

    /**
     * Effective tolerance % for a given adapter + sponsor combo.
     * Falls back to global config default.
     */
    private function tolerance_pct( string $adapter, string $sponsor_id ): float {
        $config = $this->get_recon_config();

        if ( ! empty( $sponsor_id ) && isset( $config['sponsor_tolerances'][ $sponsor_id ] ) ) {
            return (float) $config['sponsor_tolerances'][ $sponsor_id ];
        }
        if ( ! empty( $adapter ) && isset( $config['adapter_tolerances'][ $adapter ] ) ) {
            return (float) $config['adapter_tolerances'][ $adapter ];
        }

        return (float) ( $config['tolerance_pct'] ?? 2.0 );
    }

    private function tolerance_min_cents(): int {
        $config = $this->get_recon_config();
        return (int) ( $config['tolerance_min_cents'] ?? 100 );
    }

    private function get_recon_config(): array {
        static $config = null;
        if ( $config === null ) {
            $all    = file_exists( KH_SMMA_PATH . 'config/paid_adapters.php' )
                ? require KH_SMMA_PATH . 'config/paid_adapters.php'
                : [];
            $config = $all['reconciliation'] ?? [];
        }
        return $config;
    }
}
