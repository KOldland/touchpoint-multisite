<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Reconciliation\SettlementWorker;
use KH_SMMA\Reconciliation\PaidReconciliationAdjustmentService;
use KH_SMMA\Reconciliation\FxService;
use KH_SMMA\Services\AuditLogger;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/FxService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationAdjustmentService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SettlementWorker.php';

/**
 * PAID-05 — SettlementWorker unit tests.
 */
final class SettlementWorkerTest extends TestCase {

    /** @var wpdb&\PHPUnit\Framework\MockObject\MockObject */
    private $db;

    /** @var PaidReconciliationAdjustmentService&\PHPUnit\Framework\MockObject\MockObject */
    private $adj_service;

    /** @var FxService */
    private $fx;

    /** @var AuditLogger&\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    /** @var SettlementWorker */
    private $worker;

    /** Returns a minimal reconciliation row for the given sponsor/currency. */
    private function make_rec( string $rec_id, string $sponsor_id, string $currency, float $actual ): array {
        return [
            'reconciliation_id'       => $rec_id,
            'manifest_id'             => 'man_001',
            'sponsor_id'              => $sponsor_id,
            'campaign_id'             => 'camp_001',
            'adapter'                 => 'linkedin_sandbox',
            'actual_spend'            => (string) $actual,
            'estimated_spend'         => (string) $actual,
            'currency'                => $currency,
            'discrepancy_percent'     => '0.0000',
            'status'                  => 'reconciled',
            'partial_failure'         => '0',
            'operation_ids'           => '',
            'notes'                   => null,
            'created_at'              => '2026-03-01 10:00:00',
            'updated_at'              => '2026-03-01 10:00:00',
            'settlement_id'           => null,
        ];
    }

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
        $GLOBALS['kh_test_filters'] = [];

        $this->db         = $this->createMock( wpdb::class );
        $this->db->prefix = 'wp_';

        $this->adj_service = $this->createMock( PaidReconciliationAdjustmentService::class );
        $this->fx          = new FxService( [ 'AUD_USD' => 0.6453, 'USD_AUD' => 1.5497 ] );
        $this->logger      = $this->createMock( AuditLogger::class );

        $this->worker = new SettlementWorker( $this->db, $this->adj_service, $this->fx, $this->logger );
    }

    // -------------------------------------------------------------------------

    /**
     * run() creates one settlement row per sponsor_id + currency group.
     */
    public function test_run_creates_settlement_row_per_sponsor_currency_group(): void {
        $rows = [
            $this->make_rec( 'rec_001', 'sp_A', 'AUD', 60.0 ),
            $this->make_rec( 'rec_002', 'sp_B', 'AUD', 40.0 ),
        ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_results' )->willReturn( $rows );
        $this->db->method( 'insert' )->willReturn( true );
        $this->db->method( 'update' )->willReturn( true );

        $this->adj_service->method( 'compute_settled_amount' )->willReturnArgument( 1 );

        $settlements = $this->worker->run();

        $this->assertCount( 2, $settlements, 'One settlement per sponsor group.' );
    }

    /**
     * run() includes adjustment amounts in settled totals via compute_settled_amount().
     */
    public function test_run_applies_adjustments_in_settled_amount(): void {
        $rows = [ $this->make_rec( 'rec_001', 'sp_A', 'AUD', 60.0 ) ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_results' )->willReturn( $rows );
        $this->db->method( 'insert' )->willReturn( true );
        $this->db->method( 'update' )->willReturn( true );

        // After +5 adjustment, settled = 65.0.
        $this->adj_service->method( 'compute_settled_amount' )->willReturn( 65.0 );

        $settlements = $this->worker->run();

        // FX = 1.0 (AUD → AUD), so total_settled = 65.0
        $this->assertSame( 65.0, (float) $settlements[0]['total_settled'] );
    }

    /**
     * run() converts settled amounts via FxService when target_currency differs.
     */
    public function test_run_applies_fx_rate_correctly(): void {
        $rows = [ $this->make_rec( 'rec_001', 'sp_A', 'AUD', 100.0 ) ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_results' )->willReturn( $rows );
        $this->db->method( 'insert' )->willReturn( true );
        $this->db->method( 'update' )->willReturn( true );

        $this->adj_service->method( 'compute_settled_amount' )->willReturn( 100.0 );

        $settlements = $this->worker->run( [ 'target_currency' => 'USD' ] );

        // 100 AUD × 0.6453 = 64.53
        $this->assertSame( 64.53, (float) $settlements[0]['total_settled'] );
        $this->assertSame( 0.6453, (float) $settlements[0]['fx_rate'] );
    }

    /**
     * run() calls db->update() to mark each reconciliation as 'settled' with the settlement_id.
     */
    public function test_run_marks_each_reconciliation_as_settled_with_settlement_id(): void {
        $rows = [
            $this->make_rec( 'rec_001', 'sp_A', 'AUD', 50.0 ),
            $this->make_rec( 'rec_002', 'sp_A', 'AUD', 30.0 ),
        ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_results' )->willReturn( $rows );
        $this->db->method( 'insert' )->willReturn( true );

        $this->adj_service->method( 'compute_settled_amount' )->willReturnArgument( 1 );

        // Expect update() called once per reconciliation row.
        $this->db->expects( $this->exactly( 2 ) )
                 ->method( 'update' )
                 ->with(
                     $this->anything(),
                     $this->callback( fn( $data ) => 'settled' === $data['status'] && ! empty( $data['settlement_id'] ) ),
                     $this->anything()
                 );

        $this->worker->run();
    }

    /**
     * run() fires the kh_paid_settlement_complete action and logs paid_settlement.complete.
     */
    public function test_run_fires_complete_action_and_audit_per_settlement(): void {
        $rows = [ $this->make_rec( 'rec_001', 'sp_A', 'AUD', 60.0 ) ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_results' )->willReturn( $rows );
        $this->db->method( 'insert' )->willReturn( true );
        $this->db->method( 'update' )->willReturn( true );

        $this->adj_service->method( 'compute_settled_amount' )->willReturn( 60.0 );

        $this->logger->expects( $this->once() )
                     ->method( 'log' )
                     ->with( $this->equalTo( 'paid_settlement.complete' ), $this->anything() );

        $fired = false;
        add_action( 'kh_paid_settlement_complete', function ( $unused, $row ) use ( &$fired ) {
            $fired = true;
        }, 10, 2 );

        $this->worker->run();

        $this->assertTrue( $fired, 'kh_paid_settlement_complete was not fired.' );
    }

    /**
     * run() returns empty array when no unsettled reconciliations are found.
     */
    public function test_run_excludes_reconciliations_already_settled(): void {
        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_results' )->willReturn( [] ); // Nothing to settle.

        $settlements = $this->worker->run();

        $this->assertEmpty( $settlements );
    }

    /**
     * export_ledger_csv() returns a CSV string with the correct header and data row.
     */
    public function test_export_ledger_csv_has_correct_columns_and_data(): void {
        $settlement = [
            'settlement_id'      => 'sett_abc000001',
            'sponsor_id'         => 'sp_123',
            'currency'           => 'AUD',
            'total_settled'      => '64.5300',
            'fx_rate'            => '0.645300',
            'settled_at'         => '2026-03-01 12:00:00',
            'reconciliation_ids' => '["rec_001"]',
            'batch_size'         => '1',
            'notes'              => null,
        ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_row' )->willReturn( $settlement );

        $csv = $this->worker->export_ledger_csv( 'sett_abc000001' );

        $lines = array_values( array_filter( explode( "\n", $csv ) ) );

        // Header line.
        $this->assertStringContainsString( 'settlement_id', $lines[0] );
        $this->assertStringContainsString( 'total_settled', $lines[0] );
        $this->assertStringContainsString( 'reconciliation_ids', $lines[0] );

        // Data line.
        $this->assertStringContainsString( 'sett_abc000001', $lines[1] );
        $this->assertStringContainsString( 'sp_123', $lines[1] );
    }
}
