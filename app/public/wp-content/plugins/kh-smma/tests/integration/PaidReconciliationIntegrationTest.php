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
 * PAID-08 — Reconciliation run integration tests.
 *
 * Uses ReconIntegrationWpdb (in-memory wpdb) to run the full start_run →
 * execute_run → export_run → resolve_row pipeline without a real database.
 * 4 tests covering the canonical integration scenarios.
 */
final class PaidReconciliationIntegrationTest extends TestCase {

    /** @var ReconIntegrationWpdb */
    private $db;

    /** @var AuditLogger&\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    /** @var PaidReconciliationService&\PHPUnit\Framework\MockObject\MockObject */
    private $source_svc;

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
        $GLOBALS['kh_test_filters'] = [];

        $this->db         = new ReconIntegrationWpdb();
        $this->logger     = $this->createMock( AuditLogger::class );
        $this->source_svc = $this->createMock( PaidReconciliationService::class );

        defined( 'KH_SMMA_PATH' ) || define( 'KH_SMMA_PATH', dirname( __DIR__, 2 ) . '/' );
    }

    private function make_service(): ReconciliationService {
        return new ReconciliationService( $this->db, $this->logger, $this->source_svc );
    }

    private function make_source_row(
        string $rec_id    = 'rec_001',
        float  $estimated = 60.0,
        float  $actual    = 59.35,
        string $ops       = '["op_001"]'
    ): array {
        return [
            'reconciliation_id' => $rec_id,
            'manifest_id'       => 'man_int_001',
            'sponsor_id'        => 'sp_inttest',
            'schedule_id'       => 'sched_int_001',
            'channel'           => 'linkedin_sandbox',
            'estimated_spend'   => $estimated,
            'actual_spend'      => $actual,
            'currency'          => 'AUD',
            'operation_ids'     => $ops,
            'status'            => 'reconciled',
            'created_at'        => '2026-03-04 00:00:00',
            'updated_at'        => '2026-03-04 00:00:00',
        ];
    }

    // =========================================================================

    /**
     * Full flow: start_run → execute_run → export_run.
     * Verifies all DB stores are populated and CSV is produced.
     */
    public function test_full_flow_start_run_execute_and_export(): void {
        $this->db->seed_source_rows( [ $this->make_source_row() ] );

        $svc = $this->make_service();

        // Start run.
        $run = $svc->start_run( [
            'sponsor_id' => 'sp_inttest',
            'initiator'  => 'integration_test',
        ] );
        $this->assertSame( 'pending', $run['status'] );
        $this->assertStringStartsWith( 'run_', $run['run_id'] );

        // Execute run.
        $completed = $svc->execute_run( $run['run_id'] );
        $this->assertSame( 'completed', $completed['status'] );
        $this->assertGreaterThanOrEqual( 1, $completed['total_rows'] );

        // Export run.
        $export = $svc->export_run( $run['run_id'], 1 );
        $this->assertStringContainsString( 'row_id', $export['csv'] );
        $this->assertStringStartsWith( 'exp_', $export['export_id'] );
        $this->assertNotEmpty( $export['checksum'] );
    }

    /**
     * Variance rows are correctly flagged when spend difference exceeds tolerance.
     */
    public function test_variance_rows_flagged_above_threshold(): void {
        // estimated=60, actual=70 → 16.7% above default 2% tolerance.
        $this->db->seed_source_rows( [ $this->make_source_row( 'rec_v01', 60.0, 70.0 ) ] );

        $svc     = $this->make_service();
        $run     = $svc->start_run( [ 'initiator' => 'test' ] );
        $result  = $svc->execute_run( $run['run_id'] );

        $this->assertSame( 'completed', $result['status'] );
        $this->assertGreaterThanOrEqual( 1, $result['variance_rows'] );
        $this->assertSame( 0, $result['unmatched_rows'] );

        // Verify detail row status in DB.
        $rows = $svc->get_run_rows( $run['run_id'] );
        $this->assertNotEmpty( $rows );
        $this->assertSame( 'variance', $rows[0]['status'] );
    }

    /**
     * resolve_row() persists the resolved status and audit note.
     */
    public function test_resolve_row_persists_with_audit(): void {
        $this->db->seed_source_rows( [ $this->make_source_row( 'rec_r01', 60.0, 70.0 ) ] );

        $this->logger->expects( $this->atLeastOnce() )
            ->method( 'log' )
            ->with( $this->logicalOr(
                $this->equalTo( 'paid_recon.run.started' ),
                $this->equalTo( 'paid_recon.run.completed' ),
                $this->equalTo( 'paid_recon.row.variance' ),
                $this->equalTo( 'paid_recon.row.resolved' )
            ), $this->anything() );

        $svc    = $this->make_service();
        $run    = $svc->start_run( [ 'initiator' => 'test' ] );
        $svc->execute_run( $run['run_id'] );

        $rows   = $svc->get_run_rows( $run['run_id'] );
        $this->assertNotEmpty( $rows );

        $row_id = $rows[0]['row_id'];
        $resolved = $svc->resolve_row( $row_id, 'resolved', 'Finance approved manually', 99 );

        $this->assertSame( 'resolved', $resolved['status'] );
        $this->assertSame( 99, (int) $resolved['resolver_id'] );
        $this->assertSame( 'Finance approved manually', $resolved['notes'] );
    }

    /**
     * execute_run() is idempotent: re-running a completed run returns it unchanged.
     */
    public function test_run_is_idempotent_across_multiple_execute_calls(): void {
        $this->db->seed_source_rows( [ $this->make_source_row() ] );

        $svc        = $this->make_service();
        $run        = $svc->start_run( [ 'initiator' => 'test' ] );
        $first_run  = $svc->execute_run( $run['run_id'] );
        $second_run = $svc->execute_run( $run['run_id'] );

        $this->assertSame( 'completed', $first_run['status'] );
        $this->assertSame( 'completed', $second_run['status'] );
        $this->assertSame( $first_run['checksum'], $second_run['checksum'] );
        $this->assertSame( $first_run['total_rows'], $second_run['total_rows'] );
    }
}

// ── In-memory wpdb for integration tests ────────────────────────────────────

class ReconIntegrationWpdb extends wpdb {
    public string $prefix = 'wp_';

    private array $stores = [
        'recon_runs'     => [],
        'recon_rows'     => [],
        'recon_exports'  => [],
        'reconciliations'=> [],
        'other'          => [],
    ];

    private array $seeded_source_rows = [];

    public function seed_source_rows( array $rows ): void {
        $this->seeded_source_rows = $rows;
    }

    public function insert( $table, $data, $format = [] ): bool {
        $suffix = $this->table_suffix( $table );
        $pk     = $this->pk( $suffix );
        $key    = isset( $data[ $pk ] ) ? $data[ $pk ] : uniqid();
        $this->stores[ $suffix ][ $key ] = $data;
        return true;
    }

    public function update( $table, $data, $where ): bool {
        $suffix = $this->table_suffix( $table );
        foreach ( $this->stores[ $suffix ] as $k => &$row ) {
            $match = true;
            foreach ( $where as $col => $val ) {
                if ( ( $row[ $col ] ?? null ) !== $val ) {
                    $match = false;
                    break;
                }
            }
            if ( $match ) {
                $row = array_merge( $row, $data );
            }
        }
        return true;
    }

    public function get_row( $query, $output = ARRAY_A ): ?array {
        // Match: WHERE column = 'value'
        if ( preg_match( "/FROM\s+(\S+)\s+WHERE\s+(\w+)\s*=\s*'([^']+)'/i", $query, $m ) ) {
            $suffix = $this->table_suffix( $m[1] );
            $col    = $m[2];
            $val    = $m[3];
            foreach ( $this->stores[ $suffix ] ?? [] as $row ) {
                if ( ( $row[ $col ] ?? null ) === $val ) {
                    return $row;
                }
            }
        }
        return null;
    }

    public function get_results( $query, $output = ARRAY_A ): array {
        // Source reconciliations query.
        if ( str_contains( $query, 'kh_paid_reconciliations' ) ) {
            return $this->seeded_source_rows;
        }

        // Stats query: SELECT status FROM wp_kh_paid_recon_rows WHERE run_id = '...'
        if ( preg_match( "/FROM\s+(\S+)\s+WHERE\s+run_id\s*=\s*'([^']+)'/i", $query, $m ) ) {
            $suffix = $this->table_suffix( $m[1] );
            $run_id = $m[2];
            return array_values( array_filter(
                $this->stores[ $suffix ] ?? [],
                fn( $r ) => ( $r['run_id'] ?? null ) === $run_id
            ) );
        }

        // List runs query.
        if ( str_contains( $query, 'recon_runs' ) ) {
            return array_values( $this->stores['recon_runs'] );
        }

        return [];
    }

    public function get_var( $query ): ?string {
        return '0';
    }

    public function prepare( $query, ...$args ): string {
        if ( count( $args ) === 1 && is_array( $args[0] ) ) {
            $args = $args[0];
        }
        foreach ( $args as $a ) {
            if ( is_string( $a ) ) {
                $query = preg_replace( '/%s/', "'{$a}'", $query, 1 );
            } elseif ( is_int( $a ) || is_float( $a ) ) {
                $query = preg_replace( '/%[df]/', (string) $a, $query, 1 );
            }
        }
        return $query;
    }

    public function get_charset_collate(): string {
        return '';
    }

    private function table_suffix( string $table ): string {
        foreach ( [
            'recon_exports'   => 'recon_exports',
            'recon_rows'      => 'recon_rows',
            'recon_runs'      => 'recon_runs',
            'reconciliations' => 'reconciliations',
        ] as $pattern => $key ) {
            if ( str_contains( $table, $pattern ) ) {
                return $key;
            }
        }
        return 'other';
    }

    private function pk( string $suffix ): string {
        return match ( $suffix ) {
            'recon_runs'     => 'run_id',
            'recon_rows'     => 'row_id',
            'recon_exports'  => 'export_id',
            default          => 'id',
        };
    }
}
