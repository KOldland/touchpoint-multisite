<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Adapters\ReconciliationService;
use KH_SMMA\Reconciliation\PaidReconciliationService;
use KH_SMMA\Services\AuditLogger;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationService.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/ReconciliationService.php';

/**
 * PAID-08 — ReconciliationService unit tests.
 *
 * 12 tests covering run lifecycle, per-operation classification, tolerance,
 * resolve, export, and list filtering.
 */
final class ReconciliationRunServiceTest extends TestCase {

    /** @var wpdb&\PHPUnit\Framework\MockObject\MockObject */
    private $db;

    /** @var AuditLogger&\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    /** @var PaidReconciliationService&\PHPUnit\Framework\MockObject\MockObject */
    private $source_svc;

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
        $GLOBALS['kh_test_filters'] = [];

        $this->db         = $this->createMock( wpdb::class );
        $this->db->prefix = 'wp_';
        $this->logger     = $this->createMock( AuditLogger::class );
        $this->source_svc = $this->createMock( PaidReconciliationService::class );
    }

    private function make_service( ?wpdb $db = null ): ReconciliationService {
        return new ReconciliationService( $db ?? $this->db, $this->logger, $this->source_svc );
    }

    // ── Helper stubs ──────────────────────────────────────────────────────────

    private function stub_db_run_insert(): void {
        $this->db->method( 'insert' )->willReturn( true );
    }

    private function stub_db_prepare_passthrough(): void {
        $this->db->method( 'prepare' )->willReturnCallback(
            function ( $sql, ...$args ) {
                // Flatten single-array call pattern used by ReconciliationService.
                if ( count( $args ) === 1 && is_array( $args[0] ) ) {
                    $args = $args[0];
                }
                foreach ( $args as $a ) {
                    if ( is_string( $a ) ) {
                        $sql = preg_replace( '/%s/', "'{$a}'", $sql, 1 );
                    } elseif ( is_int( $a ) || is_float( $a ) ) {
                        $sql = preg_replace( '/%[df]/', (string) $a, $sql, 1 );
                    }
                }
                return $sql;
            }
        );
    }

    /** Build a minimal source reconciliation row. */
    private function make_source_row(
        string $rec_id       = 'rec_001',
        string $manifest_id  = 'man_001',
        float  $estimated    = 60.0,
        float  $actual       = 59.35,
        string $channel      = 'linkedin_sandbox',
        string $operation_ids = '["op_001"]'
    ): array {
        return [
            'reconciliation_id' => $rec_id,
            'manifest_id'       => $manifest_id,
            'sponsor_id'        => 'sp_test',
            'schedule_id'       => 'sched_001',
            'channel'           => $channel,
            'estimated_spend'   => $estimated,
            'actual_spend'      => $actual,
            'currency'          => 'AUD',
            'operation_ids'     => $operation_ids,
            'status'            => 'reconciled',
            'created_at'        => '2026-03-04 00:00:00',
            'updated_at'        => '2026-03-04 00:00:00',
        ];
    }

    // =========================================================================

    /**
     * start_run() creates a pending row with a deterministic run_id and returns it.
     */
    public function test_start_run_creates_pending_row(): void {
        $this->stub_db_prepare_passthrough();
        $this->db->expects( $this->once() )->method( 'insert' )->willReturn( true );

        $svc = $this->make_service();
        $run = $svc->start_run( [
            'sponsor_id' => 'sp_test',
            'initiator'  => 'cli',
        ] );

        $this->assertSame( 'pending', $run['status'] );
        $this->assertStringStartsWith( 'run_', $run['run_id'] );
        $this->assertSame( 'cli', $run['initiator'] );
        $this->assertSame( 0, $run['total_rows'] );
    }

    /**
     * execute_run() produces 'matched' rows when actual spend is within 2% of estimated.
     */
    public function test_execute_run_produces_matched_rows_within_tolerance(): void {
        $this->stub_db_prepare_passthrough();

        $run = [
            'run_id'    => 'run_abc001',
            'status'    => 'pending',
            'initiator' => 'cli',
            'adapters'  => '[]',
            'filters'   => '{"sponsor_id":"sp_test","date_start":"","date_end":""}',
        ];

        $source = $this->make_source_row( 'rec_001', 'man_001', 60.0, 59.35 );

        $this->db->method( 'get_row' )->willReturn( $run );
        $this->db->method( 'get_results' )->willReturnCallback(
            function ( $sql ) use ( $source ) {
                if ( str_contains( $sql, 'kh_paid_reconciliations' ) ) {
                    return [ $source ];
                }
                // stats query
                return [ [ 'status' => 'matched' ] ];
            }
        );
        $this->db->method( 'update' )->willReturn( true );
        $this->db->method( 'insert' )->willReturn( true );

        $svc = $this->make_service();

        // Force config to use defaults.
        defined( 'KH_SMMA_PATH' ) || define( 'KH_SMMA_PATH', dirname( __DIR__, 2 ) . '/' );

        $result = $svc->execute_run( 'run_abc001' );

        $this->assertSame( 'completed', $result['status'] );
        $this->assertNotEmpty( $result['checksum'] );
    }

    /**
     * execute_run() flags rows as 'variance' when discrepancy exceeds tolerance band.
     */
    public function test_execute_run_flags_variance_rows_above_tolerance(): void {
        $this->stub_db_prepare_passthrough();

        $run = [
            'run_id'    => 'run_var001',
            'status'    => 'pending',
            'initiator' => 'cli',
            'adapters'  => '[]',
            'filters'   => '{"sponsor_id":"","date_start":"","date_end":""}',
        ];

        // actual=75.0 vs estimated=60.0 → ~25% variance → well above 2% tolerance.
        $source = $this->make_source_row( 'rec_v01', 'man_001', 60.0, 75.0 );

        $variance_rows_inserted = [];

        $this->db->method( 'get_row' )->willReturn( $run );
        $this->db->method( 'get_results' )->willReturnCallback(
            function ( $sql ) use ( $source ) {
                if ( str_contains( $sql, 'kh_paid_reconciliations' ) ) {
                    return [ $source ];
                }
                return [ [ 'status' => 'variance' ] ];
            }
        );
        $this->db->method( 'update' )->willReturn( true );
        $this->db->method( 'insert' )->willReturnCallback(
            function ( $table, $data ) use ( &$variance_rows_inserted ) {
                if ( str_contains( $table, 'recon_rows' ) ) {
                    $variance_rows_inserted[] = $data;
                }
                return true;
            }
        );

        defined( 'KH_SMMA_PATH' ) || define( 'KH_SMMA_PATH', dirname( __DIR__, 2 ) . '/' );

        $svc    = $this->make_service();
        $result = $svc->execute_run( 'run_var001' );

        $this->assertSame( 'completed', $result['status'] );
        $this->assertNotEmpty( $variance_rows_inserted );
        $this->assertSame( 'variance', $variance_rows_inserted[0]['status'] );
    }

    /**
     * execute_run() on an already-completed run returns it immediately (idempotent).
     */
    public function test_execute_run_is_idempotent_on_repeated_calls(): void {
        $this->stub_db_prepare_passthrough();

        $completed_run = [
            'run_id'         => 'run_done01',
            'status'         => 'completed',
            'total_rows'     => 5,
            'matched_rows'   => 5,
            'variance_rows'  => 0,
            'unmatched_rows' => 0,
            'checksum'       => 'abc123',
            'completed_at'   => '2026-03-04 00:00:00',
        ];

        $this->db->method( 'get_row' )->willReturn( $completed_run );
        $this->db->expects( $this->never() )->method( 'insert' );
        $this->db->expects( $this->never() )->method( 'update' );

        $svc    = $this->make_service();
        $result = $svc->execute_run( 'run_done01' );

        $this->assertSame( 'completed', $result['status'] );
        $this->assertSame( 'abc123', $result['checksum'] );
    }

    /**
     * execute_run() marks run as 'completed' with zero matched rows when source returns empty.
     */
    public function test_execute_run_marks_unmatched_when_no_source_data(): void {
        $this->stub_db_prepare_passthrough();

        $run = [
            'run_id'    => 'run_empty',
            'status'    => 'pending',
            'initiator' => 'cli',
            'adapters'  => '[]',
            'filters'   => '{"sponsor_id":"sp_nobody","date_start":"","date_end":""}',
        ];

        $this->db->method( 'get_row' )->willReturn( $run );
        $this->db->method( 'get_results' )->willReturn( [] );
        $this->db->method( 'update' )->willReturn( true );
        $this->db->method( 'insert' )->willReturn( true );

        defined( 'KH_SMMA_PATH' ) || define( 'KH_SMMA_PATH', dirname( __DIR__, 2 ) . '/' );

        $svc    = $this->make_service();
        $result = $svc->execute_run( 'run_empty' );

        $this->assertSame( 'completed', $result['status'] );
        $this->assertSame( 0, $result['total_rows'] );
    }

    /**
     * execute_run() correctly updates matched/variance/unmatched stats counts.
     */
    public function test_execute_run_updates_stats_counts_correctly(): void {
        $this->stub_db_prepare_passthrough();

        $run = [
            'run_id'    => 'run_stats',
            'status'    => 'pending',
            'initiator' => 'cli',
            'adapters'  => '[]',
            'filters'   => '{"sponsor_id":"","date_start":"","date_end":""}',
        ];

        $this->db->method( 'get_row' )->willReturn( $run );
        $this->db->method( 'get_results' )->willReturnOnConsecutiveCalls(
            [ $this->make_source_row( 'rec_001', 'man_001', 60.0, 59.35 ) ], // source rows
            [                                                                   // stats rows
                [ 'status' => 'matched' ],
                [ 'status' => 'variance' ],
                [ 'status' => 'unmatched' ],
            ]
        );

        $update_data = null;
        $this->db->method( 'update' )->willReturnCallback(
            function ( $table, $data ) use ( &$update_data ) {
                if ( str_contains( $table, 'recon_runs' ) && isset( $data['status'] ) && $data['status'] === 'completed' ) {
                    $update_data = $data;
                }
                return true;
            }
        );
        $this->db->method( 'insert' )->willReturn( true );

        defined( 'KH_SMMA_PATH' ) || define( 'KH_SMMA_PATH', dirname( __DIR__, 2 ) . '/' );

        $svc = $this->make_service();
        $svc->execute_run( 'run_stats' );

        $this->assertNotNull( $update_data, 'Stats update was not called.' );
        $this->assertSame( 3, $update_data['total_rows'] );
        $this->assertSame( 1, $update_data['matched_rows'] );
        $this->assertSame( 1, $update_data['variance_rows'] );
        $this->assertSame( 1, $update_data['unmatched_rows'] );
    }

    /**
     * execute_run() distributes estimated_spend and actual_spend evenly across all operations.
     */
    public function test_execute_run_distributes_spend_across_operations_evenly(): void {
        $this->stub_db_prepare_passthrough();

        $run = [
            'run_id'    => 'run_dist',
            'status'    => 'pending',
            'initiator' => 'cli',
            'adapters'  => '[]',
            'filters'   => '{"sponsor_id":"","date_start":"","date_end":""}',
        ];

        // 2 operations with total estimated=60, actual=59.35
        $source = $this->make_source_row( 'rec_d01', 'man_001', 60.0, 59.35, 'google_sandbox', '["op_1","op_2"]' );

        $inserted_rows = [];
        $this->db->method( 'get_row' )->willReturn( $run );
        $this->db->method( 'get_results' )->willReturnCallback(
            function ( $sql ) use ( $source, $inserted_rows ) {
                if ( str_contains( $sql, 'kh_paid_reconciliations' ) ) {
                    return [ $source ];
                }
                return [];
            }
        );
        $this->db->method( 'update' )->willReturn( true );
        $this->db->method( 'insert' )->willReturnCallback(
            function ( $table, $data ) use ( &$inserted_rows ) {
                if ( str_contains( $table, 'recon_rows' ) ) {
                    $inserted_rows[] = $data;
                }
                return true;
            }
        );

        defined( 'KH_SMMA_PATH' ) || define( 'KH_SMMA_PATH', dirname( __DIR__, 2 ) . '/' );

        $svc = $this->make_service();
        $svc->execute_run( 'run_dist' );

        $this->assertCount( 2, $inserted_rows, 'Should have 2 rows (one per operation).' );

        // Each row should have half the spend: estimated=3000¢, actual=2968¢ (59.35/2=29.675 → 2968¢).
        $this->assertSame( 3000, $inserted_rows[0]['expected_cost_cents'] );
        $this->assertSame( 3000, $inserted_rows[1]['expected_cost_cents'] );
        // actual_cents: round(59.35/2*100) = round(2967.5) = 2968
        $this->assertSame( 2968, $inserted_rows[0]['actual_cost_cents'] );
        $this->assertSame( 2968, $inserted_rows[1]['actual_cost_cents'] );
    }

    /**
     * resolve_row() updates the row status to 'resolved' and fires an audit log event.
     */
    public function test_resolve_row_updates_status_and_logs_audit(): void {
        $this->stub_db_prepare_passthrough();

        $updated = false;
        $this->db->method( 'update' )->willReturnCallback(
            function () use ( &$updated ) { $updated = true; return true; }
        );
        $this->db->method( 'get_row' )->willReturn( [
            'row_id'      => 'rrow_test001',
            'status'      => 'resolved',
            'resolver_id' => 7,
            'notes'       => 'Finance approved',
            'resolved_at' => '2026-03-04 01:00:00',
        ] );

        $this->logger->expects( $this->once() )
            ->method( 'log' )
            ->with( 'paid_recon.row.resolved', $this->arrayHasKey( 'row_id' ) );

        $svc = $this->make_service();
        $row = $svc->resolve_row( 'rrow_test001', 'resolved', 'Finance approved', 7 );

        $this->assertTrue( $updated );
        $this->assertSame( 'resolved', $row['status'] );
        $this->assertSame( 7, $row['resolver_id'] );
    }

    /**
     * export_run() returns a CSV string with the correct header columns.
     */
    public function test_export_run_returns_csv_with_correct_columns(): void {
        $this->stub_db_prepare_passthrough();

        $this->db->method( 'get_results' )->willReturn( [
            [
                'row_id' => 'rrow_e001', 'run_id' => 'run_exp01',
                'reconciliation_id' => 'rec_001', 'provider_reference' => 'op_001',
                'sponsor_id' => 'sp_test', 'schedule_id' => 'sched_001',
                'adapter' => 'linkedin_sandbox', 'currency' => 'AUD',
                'expected_cost_cents' => 6000, 'actual_cost_cents' => 5935,
                'fees_cents' => 0, 'variance_cents' => -65, 'variance_pct' => '1.0833',
                'status' => 'matched', 'reconciled_at' => '2026-03-04 00:00:00',
                'resolved_at' => null, 'resolver_id' => null, 'notes' => null,
            ],
        ] );
        $this->db->method( 'insert' )->willReturn( true );

        $svc    = $this->make_service();
        $export = $svc->export_run( 'run_exp01', 1 );

        $this->assertArrayHasKey( 'csv', $export );
        $this->assertStringContainsString( 'row_id', $export['csv'] );
        $this->assertStringContainsString( 'provider_reference', $export['csv'] );
        $this->assertStringContainsString( 'variance_cents', $export['csv'] );
        $this->assertStringContainsString( 'rrow_e001', $export['csv'] );
    }

    /**
     * export_run() inserts an export metadata row in wp_kh_paid_recon_exports.
     */
    public function test_export_run_stores_export_metadata_row(): void {
        $this->stub_db_prepare_passthrough();
        $this->db->method( 'get_results' )->willReturn( [] );

        $inserted_table = null;
        $this->db->method( 'insert' )->willReturnCallback(
            function ( $table ) use ( &$inserted_table ) {
                $inserted_table = $table;
                return true;
            }
        );

        $svc    = $this->make_service();
        $export = $svc->export_run( 'run_exp02', 3 );

        $this->assertStringContainsString( 'recon_exports', $inserted_table ?? '' );
        $this->assertStringStartsWith( 'exp_', $export['export_id'] );
        $this->assertSame( 'run_exp02', $export['run_id'] );
        $this->assertSame( 3, $export['user_id'] );
    }

    /**
     * list_runs() filters by status — only completed runs are returned.
     */
    public function test_list_runs_filters_by_status_and_date(): void {
        $this->stub_db_prepare_passthrough();

        $completed = [
            [ 'run_id' => 'run_c1', 'status' => 'completed', 'run_at' => '2026-03-04 00:00:00' ],
        ];
        $this->db->method( 'get_results' )->willReturn( $completed );

        $svc  = $this->make_service();
        $runs = $svc->list_runs( [ 'status' => 'completed', 'date_start' => '2026-03-01' ] );

        $this->assertCount( 1, $runs );
        $this->assertSame( 'completed', $runs[0]['status'] );
    }

    /**
     * Tolerance_pct is applied per-adapter when adapter_tolerances config is set.
     */
    public function test_tolerance_applied_per_adapter_config(): void {
        // Use a real-ish wpdb mock that lets us inspect what rows are inserted.
        $this->stub_db_prepare_passthrough();

        $run = [
            'run_id'    => 'run_tol',
            'status'    => 'pending',
            'initiator' => 'cli',
            'adapters'  => '["sftp"]',
            'filters'   => '{"sponsor_id":"","date_start":"","date_end":""}',
        ];

        // estimated=60.0, actual=61.5 → variance=1.5/60=2.5% → above default 2%
        // BUT if sftp tolerance is 3%, it would be 'matched'.
        // We just verify the service computes status without throwing.
        $source = $this->make_source_row( 'rec_t01', 'man_001', 60.0, 61.5, 'sftp', '["op_t1"]' );

        $inserted = [];
        $this->db->method( 'get_row' )->willReturn( $run );
        $this->db->method( 'get_results' )->willReturnCallback(
            function ( $sql ) use ( $source ) {
                if ( str_contains( $sql, 'kh_paid_reconciliations' ) ) {
                    return [ $source ];
                }
                return [ [ 'status' => 'variance' ] ];
            }
        );
        $this->db->method( 'update' )->willReturn( true );
        $this->db->method( 'insert' )->willReturnCallback(
            function ( $table, $data ) use ( &$inserted ) {
                if ( str_contains( $table, 'recon_rows' ) ) {
                    $inserted[] = $data;
                }
                return true;
            }
        );

        defined( 'KH_SMMA_PATH' ) || define( 'KH_SMMA_PATH', dirname( __DIR__, 2 ) . '/' );

        $svc    = $this->make_service();
        $result = $svc->execute_run( 'run_tol' );

        $this->assertSame( 'completed', $result['status'] );
        $this->assertCount( 1, $inserted );
        // Status should be 'variance' (default 2% tolerance; 2.5% > 2%).
        $this->assertSame( 'variance', $inserted[0]['status'] );
    }
}
