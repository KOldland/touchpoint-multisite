<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Reconciliation\PaidReconciliationService;
use KH_SMMA\Reconciliation\PaidReconciliationAdjustmentService;
use KH_SMMA\Reconciliation\FxService;
use KH_SMMA\Reconciliation\SettlementWorker;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Adapters\LinkedInSandboxAdapter;
use KH_SMMA\Adapters\AdapterIdempotencyStore;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/PaidAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/AdapterIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Helpers/DeterministicRng.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/LinkedInSandboxAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationAdjustmentService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/FxService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SettlementWorker.php';

// ---------------------------------------------------------------------------
// Minimal in-memory wpdb for settlement integration tests.
// Stores rows keyed by the row's primary key per table suffix.
// ---------------------------------------------------------------------------

if ( ! class_exists( 'SettlementIntegrationWpdb' ) ) {

    class SettlementIntegrationWpdb extends wpdb {
        /** @var array<string, array> table_suffix => [pk => row] */
        private array $stores = [];

        // ── Primary key resolution ──────────────────────────────────────────

        private function pk_for( string $table ): string {
            if ( str_contains( $table, 'reconciliation_adjustments' ) ) {
                return 'adjustment_id';
            }
            if ( str_contains( $table, 'settlements' ) ) {
                return 'settlement_id';
            }
            return 'reconciliation_id';
        }

        private function suffix( string $full_table ): string {
            return ltrim( str_replace( 'wp_kh_', '', $full_table ), '_' );
        }

        // ── wpdb API ────────────────────────────────────────────────────────

        public function insert( $table, $data, $format = [] ): bool {
            $t   = $this->suffix( $table );
            $pk  = $this->pk_for( $table );
            $key = $data[ $pk ] ?? uniqid();
            $this->stores[ $t ][ $key ] = $data;
            return true;
        }

        public function update( $table, $data, $where ): bool {
            $t = $this->suffix( $table );
            foreach ( $this->stores[ $t ] ?? [] as &$row ) {
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
            // Match: WHERE <col> = '<val>'
            if ( preg_match( "/FROM (\S+) WHERE (\w+) = '([^']+)'/", $query, $m ) ) {
                $t   = $this->suffix( $m[1] );
                $col = $m[2];
                $val = $m[3];
                foreach ( $this->stores[ $t ] ?? [] as $row ) {
                    if ( ( $row[ $col ] ?? null ) === $val ) {
                        return $row;
                    }
                }
            }
            return null;
        }

        public function get_results( $query, $output = ARRAY_A ): array {
            // Adjustments by reconciliation_id.
            if ( preg_match( "/FROM (\S+) WHERE reconciliation_id = '([^']+)'/", $query, $m ) ) {
                $t      = $this->suffix( $m[1] );
                $rec_id = $m[2];
                return array_values( array_filter(
                    $this->stores[ $t ] ?? [],
                    fn( $r ) => ( $r['reconciliation_id'] ?? null ) === $rec_id
                ) );
            }

            // Unsettled reconciliations for SettlementWorker::run().
            if ( preg_match( "/FROM (\S+) WHERE status IN/", $query, $m ) ) {
                $t = $this->suffix( $m[1] );
                return array_values( array_filter(
                    $this->stores[ $t ] ?? [],
                    fn( $r ) => in_array( $r['status'] ?? '', [ 'reconciled', 'discrepancy' ], true )
                        && null === ( $r['settlement_id'] ?? null )
                ) );
            }

            return [];
        }

        public function prepare( $query, ...$args ): string {
            $filled = $query;
            foreach ( $args as $a ) {
                if ( is_string( $a ) ) {
                    $filled = preg_replace( '/%s/', "'{$a}'", $filled, 1 );
                } elseif ( is_int( $a ) || is_float( $a ) ) {
                    $filled = preg_replace( '/%[df]/', (string) $a, $filled, 1 );
                }
            }
            return $filled;
        }

        public function get_charset_collate(): string {
            return '';
        }
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

/**
 * PAID-05 — Reconciliation + Settlement integration tests.
 *
 * Uses LinkedInSandboxAdapter + SettlementIntegrationWpdb (in-memory).
 * No network calls, no real DB.
 */
final class ReconciliationSettlementIntegrationTest extends TestCase {

    private const LI_FIXTURE = __DIR__ . '/../fixtures/golden/paid_adapter_dry_run_manifest.json';

    /** @var SettlementIntegrationWpdb */
    private $db;

    /** @var PaidReconciliationService */
    private $rec_service;

    /** @var PaidReconciliationAdjustmentService */
    private $adj_service;

    /** @var SettlementWorker */
    private $worker;

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
        $GLOBALS['kh_test_filters'] = [];

        $this->db = new SettlementIntegrationWpdb();

        $logger = $this->createMock( AuditLogger::class );
        $logger->method( 'log' )->willReturn( null );

        $this->rec_service = new PaidReconciliationService( $this->db, $logger );
        $this->adj_service = new PaidReconciliationAdjustmentService( $this->db, $logger );
        $fx                = new FxService( [] ); // All same-currency; rate = 1.0

        $this->worker = new SettlementWorker(
            $this->db,
            $this->adj_service,
            $fx,
            $logger
        );
    }

    // -------------------------------------------------------------------------

    /**
     * Full flow: execute → reconcile → adjust(+5.0) → settle.
     *
     * settled_amount = actual_spend (59.35) + adjustment (5.00) = 64.35
     */
    public function test_full_flow_execute_reconcile_adjust_then_settle(): void {
        $manifest = json_decode( (string) file_get_contents( self::LI_FIXTURE ), true );
        $idem_key = $manifest['meta']['idempotency_key'];

        $store   = new AdapterIdempotencyStore();
        $adapter = new LinkedInSandboxAdapter( null, $store );

        $dry_res  = $adapter->dry_run( $manifest );
        $exec_res = $adapter->execute( $manifest );

        $row = $this->rec_service->reconcile(
            $manifest['manifest_id'],
            $exec_res,
            $dry_res,
            [
                'idempotency_key' => $idem_key,
                'sponsor_id'      => $manifest['meta']['sponsor_id'] ?? 'sp_test',
            ]
        );

        $this->assertSame( 'reconciled', $row['status'] );

        $rec_id = $row['reconciliation_id'];

        // Add a positive adjustment of 5.00.
        $this->adj_service->create_adjustment( $rec_id, 5.0, 'AUD', 'Rate correction', 1 );

        // Run settlement.
        $settlements = $this->worker->run();

        $this->assertCount( 1, $settlements );

        // settled_amount = 59.35 + 5.00 = 64.35
        $this->assertSame( 64.35, (float) $settlements[0]['total_settled'] );
        $this->assertStringStartsWith( 'sett_', $settlements[0]['settlement_id'] );
    }

    /**
     * Settlement ledger CSV has the correct column headers and first data row.
     */
    public function test_settlement_ledger_csv_format(): void {
        $manifest = json_decode( (string) file_get_contents( self::LI_FIXTURE ), true );
        $idem_key = $manifest['meta']['idempotency_key'];

        $store   = new AdapterIdempotencyStore();
        $adapter = new LinkedInSandboxAdapter( null, $store );

        $exec_res = $adapter->execute( $manifest );

        $this->rec_service->reconcile(
            $manifest['manifest_id'],
            $exec_res,
            null,
            [
                'idempotency_key' => $idem_key,
                'sponsor_id'      => $manifest['meta']['sponsor_id'] ?? 'sp_test',
            ]
        );

        $settlements  = $this->worker->run();
        $settlement_id = $settlements[0]['settlement_id'];

        $csv   = $this->worker->export_ledger_csv( $settlement_id );
        $lines = array_values( array_filter( explode( "\n", $csv ) ) );

        // Header must contain required columns.
        $header = $lines[0];
        foreach ( [ 'settlement_id', 'sponsor_id', 'total_settled', 'fx_rate', 'settled_at', 'reconciliation_ids', 'batch_size' ] as $col ) {
            $this->assertStringContainsString( $col, $header, "CSV header missing column: {$col}" );
        }

        // Data row must include the settlement_id.
        $this->assertStringContainsString( $settlement_id, $lines[1] );
    }

    /**
     * Adjust → reverse → settle: the reversal cancels the adjustment so
     * settled_amount equals the original actual_spend.
     */
    public function test_adjustment_reversal_then_settle_cancels_adjustment(): void {
        $manifest = json_decode( (string) file_get_contents( self::LI_FIXTURE ), true );
        $idem_key = $manifest['meta']['idempotency_key'];

        $store   = new AdapterIdempotencyStore();
        $adapter = new LinkedInSandboxAdapter( null, $store );

        $exec_res = $adapter->execute( $manifest );
        $actual   = (float) $exec_res['total_actual_spend'];  // 59.35

        $row = $this->rec_service->reconcile(
            $manifest['manifest_id'],
            $exec_res,
            null,
            [
                'idempotency_key' => $idem_key,
                'sponsor_id'      => $manifest['meta']['sponsor_id'] ?? 'sp_test',
            ]
        );

        $rec_id = $row['reconciliation_id'];

        // Create adjustment, then immediately reverse it.
        $adj = $this->adj_service->create_adjustment( $rec_id, 10.0, 'AUD', 'Oops', 1 );
        $this->adj_service->create_reversal( $adj['adjustment_id'], 1 );

        // Settle: net adjustment = +10 - 10 = 0 → settled_amount = actual_spend.
        $settlements = $this->worker->run();

        $this->assertCount( 1, $settlements );
        $this->assertSame( $actual, (float) $settlements[0]['total_settled'] );
    }
}
