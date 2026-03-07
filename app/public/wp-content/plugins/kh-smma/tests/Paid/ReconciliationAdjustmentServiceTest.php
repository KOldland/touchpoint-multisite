<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Reconciliation\PaidReconciliationAdjustmentService;
use KH_SMMA\Services\AuditLogger;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationAdjustmentService.php';

/**
 * PAID-05 — PaidReconciliationAdjustmentService unit tests.
 *
 * All time-dependent calls use the TestHelpers stub: current_time() returns
 * '2026-01-24 00:00:00', making adjustment_id generation deterministic.
 */
final class ReconciliationAdjustmentServiceTest extends TestCase {

    private const REC_ID    = 'rec_abc000000001';
    private const ADJ_USER  = 7;

    /** @var wpdb&\PHPUnit\Framework\MockObject\MockObject */
    private $db;

    /** @var AuditLogger&\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    /** @var PaidReconciliationAdjustmentService */
    private $svc;

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
        $GLOBALS['kh_test_filters'] = [];

        $this->db         = $this->createMock( wpdb::class );
        $this->db->prefix = 'wp_';
        $this->logger     = $this->createMock( AuditLogger::class );
        $this->svc        = new PaidReconciliationAdjustmentService( $this->db, $this->logger );
    }

    // -------------------------------------------------------------------------

    /**
     * create_adjustment() returns a row with all required fields.
     */
    public function test_create_adjustment_returns_row_with_correct_fields(): void {
        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'insert' )->willReturn( true );

        $row = $this->svc->create_adjustment( self::REC_ID, 10.50, 'AUD', 'Rate correction', self::ADJ_USER );

        $this->assertStringStartsWith( 'adj_', $row['adjustment_id'] );
        $this->assertSame( self::REC_ID, $row['reconciliation_id'] );
        $this->assertSame( 10.50, $row['amount'] );
        $this->assertSame( 'AUD', $row['currency'] );
        $this->assertSame( 'Rate correction', $row['reason'] );
        $this->assertSame( self::ADJ_USER, $row['adjusted_by'] );
        $this->assertNull( $row['reversal_of'] );
        $this->assertNotEmpty( $row['created_at'] );
    }

    /**
     * create_adjustment() fires the 'paid_adjustment.created' audit event.
     */
    public function test_create_adjustment_fires_paid_adjustment_created_audit(): void {
        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'insert' )->willReturn( true );

        $this->logger->expects( $this->once() )
                     ->method( 'log' )
                     ->with(
                         $this->equalTo( 'paid_adjustment.created' ),
                         $this->callback( fn( $ctx ) => isset( $ctx['details']['reconciliation_id'] ) )
                     );

        $this->svc->create_adjustment( self::REC_ID, 5.0, 'AUD', 'Test', self::ADJ_USER );
    }

    /**
     * create_adjustment() fires the kh_paid_adjustment_created action hook.
     */
    public function test_create_adjustment_fires_action_hook(): void {
        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'insert' )->willReturn( true );

        $received = null;
        // do_action routes as apply_filters(tag, null, $row); row is 2nd arg.
        add_action( 'kh_paid_adjustment_created', function ( $unused, $row ) use ( &$received ) {
            $received = $row;
        }, 10, 2 );

        $this->svc->create_adjustment( self::REC_ID, 5.0, 'AUD', 'Hook test', self::ADJ_USER );

        $this->assertNotNull( $received, 'kh_paid_adjustment_created was not fired.' );
        $this->assertSame( self::REC_ID, $received['reconciliation_id'] );
    }

    /**
     * create_reversal() returns a row with the opposite (negated) amount.
     */
    public function test_create_reversal_creates_row_with_opposite_amount(): void {
        $original_id = 'adj_orig000000';

        $original = [
            'adjustment_id'     => $original_id,
            'reconciliation_id' => self::REC_ID,
            'amount'            => '10.00',
            'currency'          => 'AUD',
        ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        // First get_row: fetch original. Second get_row: check for existing reversal (none).
        $this->db->method( 'get_row' )->willReturnOnConsecutiveCalls( $original, null );
        $this->db->method( 'insert' )->willReturn( true );

        $row = $this->svc->create_reversal( $original_id, self::ADJ_USER );

        $this->assertSame( -10.0, $row['amount'] );
    }

    /**
     * create_reversal() sets reversal_of to the original adjustment_id.
     */
    public function test_create_reversal_sets_reversal_of_field(): void {
        $original_id = 'adj_orig000000';

        $original = [
            'adjustment_id'     => $original_id,
            'reconciliation_id' => self::REC_ID,
            'amount'            => '10.00',
            'currency'          => 'AUD',
        ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_row' )->willReturnOnConsecutiveCalls( $original, null );
        $this->db->method( 'insert' )->willReturn( true );

        $row = $this->svc->create_reversal( $original_id, self::ADJ_USER );

        $this->assertSame( $original_id, $row['reversal_of'] );
        $this->assertStringStartsWith( 'adj_', $row['adjustment_id'] );
    }

    /**
     * create_reversal() throws RuntimeException if already reversed.
     */
    public function test_create_reversal_throws_if_already_reversed(): void {
        $original_id = 'adj_orig000000';

        $original = [
            'adjustment_id' => $original_id,
            'reconciliation_id' => self::REC_ID,
            'amount' => '5.00',
            'currency' => 'AUD',
        ];
        $existing_reversal = [ 'adjustment_id' => 'adj_rev0000001' ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_row' )->willReturnOnConsecutiveCalls( $original, $existing_reversal );

        $this->expectException( \RuntimeException::class );
        $this->svc->create_reversal( $original_id, self::ADJ_USER );
    }

    /**
     * compute_settled_amount() sums positive and negative adjustments correctly.
     */
    public function test_compute_settled_amount_sums_positive_and_negative_adjustments(): void {
        $adjustments = [
            [ 'amount' => '5.00' ],
            [ 'amount' => '-2.00' ],
        ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_results' )->willReturn( $adjustments );

        $result = $this->svc->compute_settled_amount( self::REC_ID, 100.0 );

        // 100.0 + 5.0 - 2.0 = 103.0
        $this->assertSame( 103.0, $result );
    }

    /**
     * compute_settled_amount() with no adjustments returns actual_spend unchanged.
     */
    public function test_compute_settled_amount_with_no_adjustments_returns_actual_spend(): void {
        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_results' )->willReturn( [] );

        $result = $this->svc->compute_settled_amount( self::REC_ID, 59.35 );

        $this->assertSame( 59.35, $result );
    }

    /**
     * adjustment_id is deterministic: identical inputs always produce the same ID.
     * (current_time is stubbed to '2026-01-24 00:00:00' in TestHelpers.)
     */
    public function test_adjustment_id_is_deterministic_for_same_inputs(): void {
        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'insert' )->willReturn( true );

        $row1 = $this->svc->create_adjustment( self::REC_ID, 10.0, 'AUD', 'Same reason', self::ADJ_USER );

        $db2         = $this->createMock( wpdb::class );
        $db2->prefix = 'wp_';
        $db2->method( 'prepare' )->willReturnArgument( 0 );
        $db2->method( 'insert' )->willReturn( true );
        $svc2 = new PaidReconciliationAdjustmentService( $db2, $this->logger );

        $row2 = $svc2->create_adjustment( self::REC_ID, 10.0, 'AUD', 'Same reason', self::ADJ_USER );

        $this->assertSame(
            $row1['adjustment_id'],
            $row2['adjustment_id'],
            'adjustment_id must be deterministic for identical inputs.'
        );
    }
}
