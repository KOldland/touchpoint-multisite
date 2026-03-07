<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Reconciliation\PaidReconciliationService;
use KH_SMMA\Services\AuditLogger;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationService.php';

/**
 * PAID-04 — PaidReconciliationService unit tests.
 *
 * Uses createMock(wpdb::class) and createMock(AuditLogger::class) to isolate
 * the service from real DB and audit infrastructure.
 */
final class ReconciliationServiceTest extends TestCase {

    private const MANIFEST_ID = 'man_20260303_001';
    private const IDEM_KEY    = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

    /** @var wpdb&\PHPUnit\Framework\MockObject\MockObject */
    private $db;

    /** @var AuditLogger&\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    /** Returns a minimal execute_response for 'success' status. */
    private function make_execute_response( float $actual_spend = 59.35, string $status = 'success' ): array {
        return [
            'manifest_id'        => self::MANIFEST_ID,
            'status'             => $status,
            'total_actual_spend' => $actual_spend,
            'currency'           => 'AUD',
            'operation_results'  => [
                [ 'operation_id' => 'op_1', 'result' => 'created', 'actual_spend' => $actual_spend ],
            ],
            'adapter_meta'       => [ 'adapter' => 'linkedin_sandbox', 'version' => '1.0.0' ],
        ];
    }

    /** Returns a minimal dry_run response with a given estimated_spend. */
    private function make_dry_run_response( float $estimated = 60.0 ): array {
        return [
            'manifest_id'           => self::MANIFEST_ID,
            'total_estimated_spend' => $estimated,
            'currency'              => 'AUD',
        ];
    }

    protected function setUp(): void {
        $GLOBALS['kh_test_options']  = [];
        $GLOBALS['kh_test_filters']  = [];

        $this->db     = $this->createMock( wpdb::class );
        $this->db->prefix = 'wp_';
        $this->logger = $this->createMock( AuditLogger::class );
    }

    // -------------------------------------------------------------------------

    /**
     * Happy path: new reconciliation → insert called → row with status 'reconciled' returned.
     */
    public function test_reconcile_creates_row_and_returns_it(): void {
        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_row' )->willReturn( null );
        $this->db->method( 'insert' )->willReturn( true );

        $svc = new PaidReconciliationService( $this->db, $this->logger );
        $row = $svc->reconcile(
            self::MANIFEST_ID,
            $this->make_execute_response(),
            $this->make_dry_run_response(),
            [ 'idempotency_key' => self::IDEM_KEY ]
        );

        $this->assertSame( 'reconciled', $row['status'] );
        $this->assertStringStartsWith( 'rec_', $row['reconciliation_id'] );
        $this->assertSame( self::MANIFEST_ID, $row['manifest_id'] );
        $this->assertSame( 59.35, $row['actual_spend'] );
        $this->assertSame( 60.0, $row['estimated_spend'] );
    }

    /**
     * Idempotent: get_row returns an existing row → insert NOT called → cached row returned.
     */
    public function test_reconcile_is_idempotent(): void {
        $cached = [ 'reconciliation_id' => 'rec_abc', 'status' => 'reconciled' ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_row' )->willReturn( $cached );
        $this->db->expects( $this->never() )->method( 'insert' );

        $svc = new PaidReconciliationService( $this->db, $this->logger );
        $row = $svc->reconcile(
            self::MANIFEST_ID,
            $this->make_execute_response(),
            null,
            [ 'idempotency_key' => self::IDEM_KEY ]
        );

        $this->assertSame( $cached, $row );
    }

    /**
     * Execute status = 'partial_success' → reconciliation status = 'partial'.
     */
    public function test_reconcile_partial_success_status(): void {
        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_row' )->willReturn( null );
        $this->db->method( 'insert' )->willReturn( true );

        $svc = new PaidReconciliationService( $this->db, $this->logger );
        $row = $svc->reconcile(
            self::MANIFEST_ID,
            $this->make_execute_response( 0.0, 'partial_success' ),
            null,
            [ 'idempotency_key' => self::IDEM_KEY ]
        );

        $this->assertSame( 'partial', $row['status'] );
    }

    /**
     * |discrepancy| > 10% → 'discrepancy_alert' audit log fired.
     */
    public function test_reconcile_discrepancy_fires_alert_and_audit(): void {
        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_row' )->willReturn( null );
        $this->db->method( 'insert' )->willReturn( true );

        // estimated = 60.0, actual = 70.0 → discrepancy ≈ 16.67% > 10%
        $this->logger->expects( $this->exactly( 2 ) )
                     ->method( 'log' )
                     ->withConsecutive(
                         [ $this->equalTo( 'paid_adapter.reconciled' ), $this->anything() ],
                         [ $this->equalTo( 'paid_reconciliation.discrepancy_alert' ), $this->anything() ]
                     );

        $svc = new PaidReconciliationService( $this->db, $this->logger );
        $row = $svc->reconcile(
            self::MANIFEST_ID,
            $this->make_execute_response( 70.0 ),    // actual = 70
            $this->make_dry_run_response( 60.0 ),    // estimated = 60
            [ 'idempotency_key' => self::IDEM_KEY ]
        );

        $this->assertSame( 'discrepancy', $row['status'] );
    }

    /**
     * |discrepancy| ≤ 10% → status = 'reconciled', no discrepancy alert.
     */
    public function test_reconcile_within_threshold_is_reconciled(): void {
        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_row' )->willReturn( null );
        $this->db->method( 'insert' )->willReturn( true );

        // estimated = 60.0, actual = 60.6 → discrepancy = 1%
        $this->logger->expects( $this->once() )
                     ->method( 'log' )
                     ->with( $this->equalTo( 'paid_adapter.reconciled' ), $this->anything() );

        $svc = new PaidReconciliationService( $this->db, $this->logger );
        $row = $svc->reconcile(
            self::MANIFEST_ID,
            $this->make_execute_response( 60.6 ),
            $this->make_dry_run_response( 60.0 ),
            [ 'idempotency_key' => self::IDEM_KEY ]
        );

        $this->assertSame( 'reconciled', $row['status'] );
    }

    /**
     * do_action('kh_paid_reconciliation_complete') fires with the reconciliation row.
     */
    public function test_reconcile_fires_complete_action(): void {
        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_row' )->willReturn( null );
        $this->db->method( 'insert' )->willReturn( true );

        $received = null;
        // TestHelpers routes do_action through apply_filters(tag, null, $row),
        // so $row arrives as the 2nd argument; accepted_args=2 is required.
        add_action( 'kh_paid_reconciliation_complete', function ( $unused, $row ) use ( &$received ) {
            $received = $row;
        }, 10, 2 );

        $svc = new PaidReconciliationService( $this->db, $this->logger );
        $svc->reconcile(
            self::MANIFEST_ID,
            $this->make_execute_response(),
            null,
            [ 'idempotency_key' => self::IDEM_KEY ]
        );

        $this->assertNotNull( $received, 'kh_paid_reconciliation_complete action was not fired.' );
        $this->assertSame( self::MANIFEST_ID, $received['manifest_id'] );
    }

    /**
     * Two calls with identical inputs always produce the same reconciliation_id.
     */
    public function test_reconcile_deterministic_id(): void {
        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_row' )->willReturn( null );
        $this->db->method( 'insert' )->willReturn( true );

        $svc = new PaidReconciliationService( $this->db, $this->logger );

        $row1 = $svc->reconcile(
            self::MANIFEST_ID,
            $this->make_execute_response(),
            null,
            [ 'idempotency_key' => self::IDEM_KEY ]
        );

        // Reset filter state for second call (new db mock returns null again).
        $db2 = $this->createMock( wpdb::class );
        $db2->prefix = 'wp_';
        $db2->method( 'prepare' )->willReturnArgument( 0 );
        $db2->method( 'get_row' )->willReturn( null );
        $db2->method( 'insert' )->willReturn( true );

        $svc2 = new PaidReconciliationService( $db2, $this->logger );
        $row2 = $svc2->reconcile(
            self::MANIFEST_ID,
            $this->make_execute_response(),
            null,
            [ 'idempotency_key' => self::IDEM_KEY ]
        );

        $this->assertSame(
            $row1['reconciliation_id'],
            $row2['reconciliation_id'],
            'reconciliation_id must be deterministic for identical inputs.'
        );
    }
}
