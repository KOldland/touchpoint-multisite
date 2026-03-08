<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Reconciliation\PaidReconciliationAdjustmentService;
use KH_SMMA\Reconciliation\FxService;
use KH_SMMA\Reconciliation\SettlementWorker;
use KH_SMMA\Reconciliation\AccountingAdapterContract;
use KH_SMMA\Reconciliation\SftpAccountingAdapter;
use KH_SMMA\Reconciliation\AccountingApiAdapter;
use KH_SMMA\Reconciliation\SettlementDeliveryService;
use KH_SMMA\Reconciliation\DeliveryIdempotencyStore;
use KH_SMMA\Services\AuditLogger;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/FxService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationAdjustmentService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SettlementWorker.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/AccountingAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/DeliveryIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SftpAccountingAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/AccountingApiAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SettlementDeliveryService.php';

if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $key ) {
        unset( $GLOBALS['kh_test_options'][ $key ] );
        return true;
    }
}

// ---------------------------------------------------------------------------
// In-memory wpdb for settlement + delivery integration tests.
// Extends SettlementIntegrationWpdb with delivery table support.
// ---------------------------------------------------------------------------

if ( ! class_exists( 'DeliveryIntegrationWpdb' ) ) {

    class DeliveryIntegrationWpdb extends wpdb {
        /** @var array<string, array> table_suffix => [pk => row] */
        private array $stores = [];

        // ── Primary key resolution ──────────────────────────────────────────

        private function pk_for( string $table ): string {
            if ( str_contains( $table, 'settlement_deliveries' ) ) {
                return 'delivery_id';
            }
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

        // ── wpdb API ─────────────────────────────────────────────────────────

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
            // Delivery rows by settlement_id.
            if ( preg_match( "/FROM (\S+) WHERE settlement_id = '([^']+)'/", $query, $m ) ) {
                $t             = $this->suffix( $m[1] );
                $settlement_id = $m[2];
                return array_values( array_filter(
                    $this->stores[ $t ] ?? [],
                    fn( $r ) => ( $r['settlement_id'] ?? null ) === $settlement_id
                ) );
            }

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

        public function get_var( $query ): ?string {
            return '0';
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

        /** Expose stored rows for assertions. */
        public function rows( string $table_suffix ): array {
            return array_values( $this->stores[ $table_suffix ] ?? [] );
        }
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

/**
 * PAID-06 — Settlement + Delivery accounting integration tests.
 *
 * Uses DeliveryIntegrationWpdb (in-memory), SftpAccountingAdapter,
 * AccountingApiAdapter, and SettlementDeliveryService.
 * No network calls, no real DB.
 */
final class SettlementAccountingIntegrationTest extends TestCase {

    /** @var DeliveryIntegrationWpdb */
    private $db;

    /** @var SettlementWorker */
    private $worker;

    /** @var SettlementDeliveryService */
    private $delivery_service;

    /** @var SftpAccountingAdapter */
    private $sftp;

    /** @var AccountingApiAdapter */
    private $api;

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
        $GLOBALS['kh_test_filters'] = [];
        $GLOBALS['kh_sftp_sandbox'] = [];
        $GLOBALS['kh_api_sandbox']  = [];

        $this->db = new DeliveryIntegrationWpdb();

        $logger = $this->createMock( AuditLogger::class );
        $logger->method( 'log' )->willReturn( null );

        $adj_service = new PaidReconciliationAdjustmentService( $this->db, $logger );
        $fx          = new FxService( [] ); // Same-currency; rate = 1.0

        $this->worker = new SettlementWorker( $this->db, $adj_service, $fx, $logger );

        $store = new DeliveryIdempotencyStore();

        $this->delivery_service = new SettlementDeliveryService(
            $this->db,
            $this->worker,
            $logger,
            $store
        );

        $this->sftp = new SftpAccountingAdapter();
        $this->api  = new AccountingApiAdapter();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Seed a settlement row in the in-memory DB and return it. */
    private function seed_settlement( string $id = 'sett_integ001aaaa' ): array {
        $row = [
            'settlement_id'      => $id,
            'sponsor_id'         => 'sp_integration',
            'currency'           => 'AUD',
            'total_settled'      => '118.4000',
            'fx_rate'            => '1.000000',
            'settled_at'         => '2026-03-04 00:00:00',
            'reconciliation_ids' => '["rec_integ_001"]',
            'batch_size'         => '1',
            'notes'              => null,
        ];
        $this->db->insert( 'wp_kh_paid_settlements', $row );
        return $row;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * Full flow: seed settlement → deliver via SFTP → ACK → assert acked status.
     */
    public function test_full_flow_settle_then_deliver_via_sftp_and_ack(): void {
        $settlement    = $this->seed_settlement( 'sett_sftp001aaaa' );
        $settlement_id = $settlement['settlement_id'];

        // ── Deliver ──────────────────────────────────────────────────────────
        $delivery = $this->delivery_service->deliver( $settlement_id, $this->sftp );

        $this->assertSame( 'delivered', $delivery['status'] );
        $this->assertSame( $settlement_id, $delivery['settlement_id'] );
        $this->assertSame( 'sftp', $delivery['adapter'] );
        $this->assertNotEmpty( $delivery['checksum'] );
        $this->assertStringStartsWith( 'del_', $delivery['delivery_id'] );

        // CSV stored in sandbox.
        $this->assertArrayHasKey( $settlement_id, $GLOBALS['kh_sftp_sandbox'] );

        // ── ACK ───────────────────────────────────────────────────────────────
        $acked = $this->delivery_service->record_ack( $delivery['delivery_id'] );

        $this->assertSame( 'acked', $acked['status'] );
        $this->assertNotNull( $acked['acked_at'] );

        // Delivery row in DB should be updated to acked.
        $db_rows = $this->db->rows( 'paid_settlement_deliveries' );
        $this->assertNotEmpty( $db_rows );
        $this->assertSame( 'acked', $db_rows[0]['status'] );
    }

    /**
     * Full flow: seed settlement → deliver via Accounting API → ACK.
     */
    public function test_full_flow_settle_then_deliver_via_api_and_ack(): void {
        $settlement    = $this->seed_settlement( 'sett_api001bbbbb' );
        $settlement_id = $settlement['settlement_id'];

        $delivery = $this->delivery_service->deliver( $settlement_id, $this->api );

        $this->assertSame( 'delivered', $delivery['status'] );
        $this->assertSame( 'accounting_api', $delivery['adapter'] );
        $this->assertArrayHasKey( 'receipt_id', $delivery['adapter_meta'] ?? [] );

        // JSON payload stored in sandbox.
        $this->assertArrayHasKey( $settlement_id, $GLOBALS['kh_api_sandbox'] );

        $acked = $this->delivery_service->record_ack( $delivery['delivery_id'] );
        $this->assertSame( 'acked', $acked['status'] );
    }

    /**
     * Delivering the same settlement twice (no force) returns the cached
     * row without inserting a duplicate delivery.
     */
    public function test_idempotent_delivery_does_not_create_duplicate_rows(): void {
        $settlement    = $this->seed_settlement( 'sett_idem002cccc' );
        $settlement_id = $settlement['settlement_id'];

        $first  = $this->delivery_service->deliver( $settlement_id, $this->sftp );
        $second = $this->delivery_service->deliver( $settlement_id, $this->sftp );

        // Same delivery_id returned on both calls.
        $this->assertSame( $first['delivery_id'], $second['delivery_id'] );

        // Only one row inserted into the deliveries table.
        $db_rows = $this->db->rows( 'paid_settlement_deliveries' );
        $this->assertCount( 1, $db_rows, 'Only one delivery row should exist.' );
    }

    /**
     * Retry after a transient failure eventually succeeds and marks the
     * delivery as delivered.
     */
    public function test_retry_after_transient_failure_then_succeed(): void {
        $settlement    = $this->seed_settlement( 'sett_retry003ddd' );
        $settlement_id = $settlement['settlement_id'];

        // First delivery with simulated transient failure.
        $failed_delivery = $this->delivery_service->deliver(
            $settlement_id,
            $this->sftp,
            [ 'simulate_failures' => 'transient' ]
        );

        $this->assertSame( 'failed', $failed_delivery['status'] );
        $this->assertSame( 1, (int) $failed_delivery['attempts'] );

        // Retry without failure simulation — adapter succeeds.
        $retried = $this->delivery_service->retry(
            $failed_delivery['delivery_id'],
            $this->sftp,
            []
        );

        $this->assertSame( 'delivered', $retried['status'] );
        $this->assertSame( 2, (int) $retried['attempts'] );
        $this->assertNotEmpty( $retried['checksum'] );

        // Verify DB row updated.
        $db_rows = $this->db->rows( 'paid_settlement_deliveries' );
        $this->assertNotEmpty( $db_rows );
        $this->assertSame( 'delivered', $db_rows[0]['status'] );
    }
}
