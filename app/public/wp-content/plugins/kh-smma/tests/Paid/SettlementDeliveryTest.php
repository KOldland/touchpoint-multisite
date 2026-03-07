<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Reconciliation\AccountingAdapterContract;
use KH_SMMA\Reconciliation\SftpAccountingAdapter;
use KH_SMMA\Reconciliation\AccountingApiAdapter;
use KH_SMMA\Reconciliation\SettlementDeliveryService;
use KH_SMMA\Reconciliation\DeliveryIdempotencyStore;
use KH_SMMA\Reconciliation\SettlementWorker;
use KH_SMMA\Services\AuditLogger;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/AccountingAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/DeliveryIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SftpAccountingAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/AccountingApiAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/FxService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationAdjustmentService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SettlementWorker.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SettlementDeliveryService.php';

if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $key ) {
        unset( $GLOBALS['kh_test_options'][ $key ] );
        return true;
    }
}

/**
 * PAID-06 — SettlementDeliveryService and accounting adapter unit tests.
 */
final class SettlementDeliveryTest extends TestCase {

    /** @var wpdb&\PHPUnit\Framework\MockObject\MockObject */
    private $db;

    /** @var SettlementWorker&\PHPUnit\Framework\MockObject\MockObject */
    private $worker;

    /** @var AuditLogger&\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    /** @var DeliveryIdempotencyStore */
    private $store;

    /** @var SettlementDeliveryService */
    private $service;

    /** @var SftpAccountingAdapter */
    private $sftp;

    /** @var AccountingApiAdapter */
    private $api;

    /** Returns a minimal settlement row for testing. */
    private function make_settlement( string $id = 'sett_abc123def456' ): array {
        return [
            'settlement_id'      => $id,
            'sponsor_id'         => 'sp_456',
            'currency'           => 'AUD',
            'total_settled'      => '118.4000',
            'fx_rate'            => '1.000000',
            'settled_at'         => '2026-03-04 00:00:00',
            'reconciliation_ids' => '["rec_001"]',
            'batch_size'         => '1',
            'notes'              => null,
        ];
    }

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
        $GLOBALS['kh_test_filters'] = [];
        $GLOBALS['kh_sftp_sandbox'] = [];
        $GLOBALS['kh_api_sandbox']  = [];

        $this->db         = $this->createMock( wpdb::class );
        $this->db->prefix = 'wp_';

        $this->worker = $this->createMock( SettlementWorker::class );
        $this->logger = $this->createMock( AuditLogger::class );
        $this->store  = new DeliveryIdempotencyStore();

        $this->service = new SettlementDeliveryService(
            $this->db,
            $this->worker,
            $this->logger,
            $this->store
        );

        $this->sftp = new SftpAccountingAdapter();
        $this->api  = new AccountingApiAdapter();
    }

    // ── Adapter: dry_run ──────────────────────────────────────────────────────

    /**
     * SftpAccountingAdapter::dry_run() returns the expected shape on valid input.
     */
    public function test_sftp_adapter_dry_run_returns_valid_shape(): void {
        $settlement = $this->make_settlement();
        $result     = $this->sftp->dry_run( $settlement );

        $this->assertTrue( $result['valid'], 'dry_run must report valid=true for a complete settlement.' );
        $this->assertSame( 'sftp', $result['adapter'] );
        $this->assertSame( $settlement['settlement_id'], $result['settlement_id'] );
        $this->assertNotEmpty( $result['checksum'], 'checksum must be a non-empty SHA256 string.' );
        $this->assertGreaterThan( 0, $result['payload_size_bytes'] );
        $this->assertContains( 'generate_csv', $result['estimated_ops'] );
        $this->assertContains( 'sftp_upload', $result['estimated_ops'] );
    }

    /**
     * AccountingApiAdapter::dry_run() returns the expected shape on valid input.
     */
    public function test_api_adapter_dry_run_returns_valid_shape(): void {
        $settlement = $this->make_settlement();
        $result     = $this->api->dry_run( $settlement );

        $this->assertTrue( $result['valid'] );
        $this->assertSame( 'accounting_api', $result['adapter'] );
        $this->assertNotEmpty( $result['checksum'] );
        $this->assertContains( 'api_post', $result['estimated_ops'] );
    }

    // ── Adapter: execute ──────────────────────────────────────────────────────

    /**
     * SftpAccountingAdapter::execute() returns status='delivered' for a valid settlement.
     */
    public function test_sftp_adapter_execute_returns_delivered_status(): void {
        $settlement = $this->make_settlement();
        $result     = $this->sftp->execute( $settlement );

        $this->assertSame( 'delivered', $result['status'] );
        $this->assertSame( 'sftp', $result['adapter'] );
        $this->assertStringStartsWith( 'del_', $result['delivery_id'] );
        $this->assertNotEmpty( $result['checksum'] );
        $this->assertNull( $result['error'] );
        $this->assertSame( 'sftp', $result['adapter_meta']['adapter'] );
        // CSV stored in sandbox global.
        $this->assertArrayHasKey( $settlement['settlement_id'], $GLOBALS['kh_sftp_sandbox'] );
    }

    /**
     * AccountingApiAdapter::execute() returns status='delivered' for a valid settlement.
     */
    public function test_api_adapter_execute_returns_delivered_status(): void {
        $settlement = $this->make_settlement();
        $result     = $this->api->execute( $settlement );

        $this->assertSame( 'delivered', $result['status'] );
        $this->assertSame( 'accounting_api', $result['adapter'] );
        $this->assertNotEmpty( $result['checksum'] );
        $this->assertNull( $result['error'] );
        $this->assertArrayHasKey( 'receipt_id', $result['adapter_meta'] );
        // JSON payload stored in sandbox global.
        $this->assertArrayHasKey( $settlement['settlement_id'], $GLOBALS['kh_api_sandbox'] );
    }

    /**
     * SftpAccountingAdapter::execute() is deterministic for the same settlement
     * when backed by an idempotency store.
     */
    public function test_sftp_adapter_execute_is_deterministic_for_same_settlement(): void {
        $settlement = $this->make_settlement();
        $store      = new DeliveryIdempotencyStore();
        $adapter    = new SftpAccountingAdapter( null, $store );

        $first  = $adapter->execute( $settlement );
        $second = $adapter->execute( $settlement );

        // Strip volatile timestamp field before comparison.
        unset( $first['timestamp'], $second['timestamp'] );
        $this->assertSame( $first, $second, 'execute() must return the same result for repeated calls.' );
    }

    /**
     * AccountingApiAdapter::execute() is deterministic for the same settlement.
     */
    public function test_api_adapter_execute_is_deterministic_for_same_settlement(): void {
        $settlement = $this->make_settlement();
        $store      = new DeliveryIdempotencyStore();
        $adapter    = new AccountingApiAdapter( null, $store );

        $first  = $adapter->execute( $settlement );
        $second = $adapter->execute( $settlement );

        unset( $first['timestamp'], $second['timestamp'] );
        $this->assertSame( $first, $second );
    }

    // ── SettlementDeliveryService ─────────────────────────────────────────────

    /**
     * deliver() creates a delivery row when the adapter succeeds.
     */
    public function test_delivery_service_creates_delivery_row_on_success(): void {
        $settlement = $this->make_settlement();

        $this->worker->method( 'get_settlement' )->willReturn( $settlement );

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->expects( $this->once() )->method( 'insert' )->willReturn( true );

        $row = $this->service->deliver( $settlement['settlement_id'], $this->sftp );

        $this->assertSame( 'delivered', $row['status'] );
        $this->assertSame( $settlement['settlement_id'], $row['settlement_id'] );
        $this->assertSame( 'sftp', $row['adapter'] );
        $this->assertStringStartsWith( 'del_', $row['delivery_id'] );
        $this->assertSame( 1, (int) $row['attempts'] );
    }

    /**
     * deliver() returns the cached row without re-inserting when idempotency
     * store already contains a successful delivery for the same settlement + adapter.
     */
    public function test_delivery_service_idempotency_skips_duplicate_deliver(): void {
        $settlement  = $this->make_settlement();
        $cached_row  = [
            'delivery_id'   => 'del_cached001',
            'settlement_id' => $settlement['settlement_id'],
            'adapter'       => 'sftp',
            'status'        => 'delivered',
        ];

        // Pre-load the idempotency store.
        $this->store->store( $settlement['settlement_id'], 'sftp', $cached_row );

        // No DB insert should happen.
        $this->db->expects( $this->never() )->method( 'insert' );

        $row = $this->service->deliver( $settlement['settlement_id'], $this->sftp );

        $this->assertSame( 'del_cached001', $row['delivery_id'], 'Cached row must be returned.' );
    }

    /**
     * retry() re-executes a failed delivery and increments the attempts counter.
     */
    public function test_delivery_service_retries_on_transient_failure_and_increments_attempts(): void {
        $settlement = $this->make_settlement();

        $existing_row = [
            'delivery_id'   => 'del_fail001',
            'settlement_id' => $settlement['settlement_id'],
            'adapter'       => 'sftp',
            'status'        => 'failed',
            'attempts'      => '1',
            'checksum'      => null,
            'delivered_at'  => null,
            'last_error'    => 'Transient error.',
        ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_row' )->willReturn( $existing_row );
        $this->db->method( 'update' )->willReturn( true );

        $this->worker->method( 'get_settlement' )->willReturn( $settlement );

        $row = $this->service->retry( 'del_fail001', $this->sftp );

        $this->assertSame( 'delivered', $row['status'], 'Retry on sandbox adapter should succeed.' );
        $this->assertSame( 2, (int) $row['attempts'], 'Attempts must be incremented to 2.' );
    }

    /**
     * retry() moves the delivery to failed_permanent (DLQ) after max_retries.
     */
    public function test_delivery_service_marks_failed_permanent_after_max_retries(): void {
        $settlement = $this->make_settlement();

        $existing_row = [
            'delivery_id'   => 'del_fail002',
            'settlement_id' => $settlement['settlement_id'],
            'adapter'       => 'sftp',
            'status'        => 'failed',
            'attempts'      => '2',   // attempts=2, and max_retries=3 means this attempt (3) triggers DLQ
            'checksum'      => null,
            'delivered_at'  => null,
            'last_error'    => 'Auth error.',
        ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_row' )->willReturn( $existing_row );
        $this->db->method( 'update' )->willReturn( true );

        $dlq_fired = false;
        add_action( 'kh_paid_delivery_dlq', function ( $unused, $row ) use ( &$dlq_fired ) {
            $dlq_fired = true;
        }, 10, 2 );

        $row = $this->service->retry( 'del_fail002', $this->sftp, [], 3 );

        $this->assertSame( 'failed_permanent', $row['status'] );
        $this->assertTrue( $dlq_fired, 'kh_paid_delivery_dlq action must fire.' );
    }

    /**
     * record_ack() updates the delivery status to acked.
     */
    public function test_record_ack_updates_status_to_acked(): void {
        $delivery_id  = 'del_ack001';
        $existing_row = [
            'delivery_id'   => $delivery_id,
            'settlement_id' => 'sett_abc123def456',
            'adapter'       => 'sftp',
            'status'        => 'delivered',
            'acked_at'      => null,
            'updated_at'    => '2026-03-04 00:00:00',
            'checksum'      => 'abc',
        ];

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'get_row' )->willReturn( $existing_row );
        $this->db->expects( $this->once() )
                 ->method( 'update' )
                 ->with(
                     $this->stringContains( 'settlement_deliveries' ),
                     $this->arrayHasKey( 'acked_at' ),
                     [ 'delivery_id' => $delivery_id ]
                 )
                 ->willReturn( true );

        $row = $this->service->record_ack( $delivery_id );

        $this->assertSame( 'acked', $row['status'] );
        $this->assertNotNull( $row['acked_at'] );
    }

    /**
     * deliver() and record_ack() each log an audit event.
     */
    public function test_audit_logged_on_deliver_and_ack(): void {
        $settlement   = $this->make_settlement();
        $delivery_id  = 'del_audit001';

        $this->worker->method( 'get_settlement' )->willReturn( $settlement );

        $this->db->method( 'prepare' )->willReturnArgument( 0 );
        $this->db->method( 'insert' )->willReturn( true );
        $this->db->method( 'get_row' )->willReturn( [
            'delivery_id'   => $delivery_id,
            'settlement_id' => $settlement['settlement_id'],
            'adapter'       => 'sftp',
            'status'        => 'delivered',
            'acked_at'      => null,
            'updated_at'    => '2026-03-04 00:00:00',
            'checksum'      => 'x',
        ] );
        $this->db->method( 'update' )->willReturn( true );

        $this->logger->expects( $this->atLeast( 2 ) )
                     ->method( 'log' )
                     ->with( $this->logicalOr(
                         $this->equalTo( 'paid_delivery.delivered' ),
                         $this->equalTo( 'paid_delivery.acked' )
                     ) );

        $this->service->deliver( $settlement['settlement_id'], $this->sftp );
        $this->service->record_ack( $delivery_id );
    }
}
