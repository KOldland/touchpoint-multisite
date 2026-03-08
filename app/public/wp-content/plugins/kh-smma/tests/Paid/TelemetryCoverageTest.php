<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Adapters\GoogleSandboxAdapter;
use KH_SMMA\Adapters\LinkedInSandboxAdapter;
use KH_SMMA\Adapters\ManualExportAdapter;
use KH_SMMA\Adapters\AdapterIdempotencyStore;
use KH_SMMA\Adapters\ReconciliationService;
use KH_SMMA\Reconciliation\PaidReconciliationService;
use KH_SMMA\Reconciliation\SettlementDeliveryService;
use KH_SMMA\Reconciliation\DeliveryIdempotencyStore;
use KH_SMMA\Reconciliation\AccountingAdapterContract;
use KH_SMMA\Services\AuditLogger;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/PaidAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/AdapterIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Helpers/DeterministicRng.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/GoogleSandboxAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/LinkedInSandboxAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/ManualExportAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/AccountingAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/DeliveryIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SettlementWorker.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/FxService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationAdjustmentService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SettlementDeliveryService.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/ReconciliationService.php';

/**
 * PAID — Telemetry Coverage Tests.
 *
 * Verifies that all major audit events are fired with the required fields.
 * Uses PHPUnit mock ->expects($this->once())->method('log')->with(event, callback)
 * to assert the audit log payload is complete.
 *
 * Events tested:
 *   - paid_adapter.execute (Google)
 *   - paid_adapter.execute (LinkedIn)
 *   - paid_adapter.execute (ManualExport)
 *   - paid_adapter.reconciled (PaidReconciliationService)
 *   - paid_reconciliation.discrepancy_alert (PaidReconciliationService)
 *   - paid_delivery.delivered (SettlementDeliveryService)
 *   - paid_recon.run.completed (ReconciliationService PAID-08)
 *   - paid_recon.row.variance (ReconciliationService PAID-08)
 *
 * 8 tests.
 */
final class TelemetryCoverageTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
        $GLOBALS['kh_test_filters'] = [];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function make_manifest( string $id = 'man_telemetry_001' ): array {
        return [
            'manifest_id' => $id,
            'meta'        => [
                'sponsor_id'      => 'sp_telemetry',
                'idempotency_key' => "idem_{$id}",
            ],
            'operations'  => [
                [
                    'operation_id' => 'op_telemetry_001',
                    'bid'          => [ 'amount' => 100.0, 'currency' => 'AUD' ],
                    'start_time'   => '2026-03-01T00:00:00Z',
                    'end_time'     => '2026-03-08T00:00:00Z',
                ],
            ],
        ];
    }

    private function make_execute_response( string $adapter_name = 'google_sandbox' ): array {
        return [
            'manifest_id'        => 'man_telemetry_001',
            'status'             => 'success',
            'operation_results'  => [
                [
                    'operation_id'            => 'op_telemetry_001',
                    'operation_id_on_channel' => 'g_op_abc123',
                    'result'                  => 'created',
                    'actual_spend'            => 75.0,
                    'currency'                => 'AUD',
                    'error'                   => null,
                ],
            ],
            'total_actual_spend' => 75.0,
            'currency'           => 'AUD',
            'errors'             => null,
            'timestamp'          => '2026-03-04T00:00:00Z',
            'adapter_meta'       => [
                'adapter'         => $adapter_name,
                'version'         => '1.0.0',
                'idempotency_key' => 'idem_man_telemetry_001',
            ],
        ];
    }

    private function stub_recon_db_for_new_row(): \PHPUnit\Framework\MockObject\MockObject {
        $db         = $this->createMock( wpdb::class );
        $db->prefix = 'wp_';
        $db->method( 'prepare' )->willReturnArgument( 0 );
        $db->method( 'get_row' )->willReturn( null ); // no existing row
        $db->method( 'insert' )->willReturn( true );
        return $db;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * GoogleSandboxAdapter::execute() logs paid_adapter.execute with required fields.
     */
    public function test_google_execute_logs_required_fields(): void {
        $logger  = $this->createMock( AuditLogger::class );
        $adapter = new GoogleSandboxAdapter( $logger, new AdapterIdempotencyStore() );

        $logger->expects( $this->once() )
            ->method( 'log' )
            ->with(
                'paid_adapter.execute',
                $this->callback( function ( $ctx ) {
                    $d = $ctx['details'] ?? [];
                    return isset( $d['adapter'] )
                        && isset( $d['manifest_id'] )
                        && isset( $d['currency'] )
                        && isset( $d['idempotency_key'] )
                        && isset( $d['status'] );
                } )
            );

        $adapter->execute( $this->make_manifest( 'man_telem_g_exec' ) );
    }

    /**
     * LinkedInSandboxAdapter::execute() logs paid_adapter.execute with required fields.
     */
    public function test_linkedin_execute_logs_required_fields(): void {
        $logger  = $this->createMock( AuditLogger::class );
        $adapter = new LinkedInSandboxAdapter( $logger, new AdapterIdempotencyStore() );

        $logger->expects( $this->once() )
            ->method( 'log' )
            ->with(
                'paid_adapter.execute',
                $this->callback( function ( $ctx ) {
                    $d = $ctx['details'] ?? [];
                    return isset( $d['adapter'] )
                        && isset( $d['manifest_id'] )
                        && isset( $d['currency'] )
                        && isset( $d['idempotency_key'] );
                } )
            );

        $adapter->execute( $this->make_manifest( 'man_telem_li_exec' ) );
    }

    /**
     * ManualExportAdapter::execute() logs paid_adapter.execute with required fields,
     * including estimated_spend and adapter=ManualExportAdapter.
     */
    public function test_manual_export_execute_logs_required_fields(): void {
        $logger  = $this->createMock( AuditLogger::class );
        $adapter = new ManualExportAdapter( $logger, new AdapterIdempotencyStore() );

        $logger->expects( $this->once() )
            ->method( 'log' )
            ->with(
                'paid_adapter.execute',
                $this->callback( function ( $ctx ) {
                    $d = $ctx['details'] ?? [];
                    return isset( $d['manifest_id'] )
                        && isset( $d['adapter'] )
                        && 'ManualExportAdapter' === $d['adapter']
                        && isset( $d['estimated_spend'] )
                        && isset( $d['currency'] )
                        && isset( $d['idempotency_key'] );
                } )
            );

        $adapter->execute( $this->make_manifest( 'man_telem_me_exec' ) );
    }

    /**
     * PaidReconciliationService::reconcile() logs paid_adapter.reconciled with required fields.
     */
    public function test_reconcile_logs_reconciled_event_with_required_fields(): void {
        $logger = $this->createMock( AuditLogger::class );
        $db     = $this->stub_recon_db_for_new_row();
        $svc    = new PaidReconciliationService( $db, $logger );

        $logger->expects( $this->atLeastOnce() )
            ->method( 'log' )
            ->with(
                $this->logicalOr(
                    $this->equalTo( 'paid_adapter.reconciled' ),
                    $this->equalTo( 'paid_reconciliation.discrepancy_alert' )
                ),
                $this->callback( function ( $ctx ) {
                    // For reconciled event we require these fields.
                    if ( ! isset( $ctx['details'] ) ) {
                        return false;
                    }
                    $d = $ctx['details'];
                    return isset( $d['reconciliation_id'] )
                        && isset( $d['manifest_id'] )
                        && isset( $d['status'] )
                        && isset( $d['estimated_spend'] )
                        && isset( $d['actual_spend'] );
                } )
            );

        $exec = $this->make_execute_response();
        $dry  = [ 'total_estimated_spend' => 100.0, 'currency' => 'AUD' ];

        $svc->reconcile( 'man_telemetry_reconcile_001', $exec, $dry );
    }

    /**
     * PaidReconciliationService::reconcile() logs discrepancy_alert when actual spend
     * is more than 10% above estimated (default threshold).
     */
    public function test_reconcile_discrepancy_logs_alert_event(): void {
        $logger = $this->createMock( AuditLogger::class );
        $db     = $this->stub_recon_db_for_new_row();
        $svc    = new PaidReconciliationService( $db, $logger, 10.0 );

        $called_with_alert = false;
        $logger->method( 'log' )
            ->willReturnCallback( function ( $event, $ctx ) use ( &$called_with_alert ) {
                if ( 'paid_reconciliation.discrepancy_alert' === $event ) {
                    $d = $ctx['details'] ?? [];
                    if ( isset( $d['manifest_id'] ) && isset( $d['discrepancy_percent'] ) ) {
                        $called_with_alert = true;
                    }
                }
            } );

        // actual=75, estimated=60 → 25% discrepancy > 10% threshold.
        $exec = array_merge( $this->make_execute_response(), [ 'total_actual_spend' => 75.0 ] );
        $dry  = [ 'total_estimated_spend' => 60.0 ];

        $svc->reconcile( 'man_telemetry_disc_001', $exec, $dry );

        $this->assertTrue( $called_with_alert,
            'paid_reconciliation.discrepancy_alert must be logged when discrepancy exceeds threshold.' );
    }

    /**
     * SettlementDeliveryService::deliver() logs paid_delivery.delivered with required fields
     * when the accounting adapter reports status=delivered.
     */
    public function test_settlement_delivery_logs_delivery_complete(): void {
        $logger = $this->createMock( AuditLogger::class );
        $db     = $this->createMock( wpdb::class );
        $db->prefix = 'wp_';
        $db->method( 'prepare' )->willReturnArgument( 0 );
        $db->method( 'insert' )->willReturn( true );
        $db->method( 'get_charset_collate' )->willReturn( '' );

        $settlement = [
            'settlement_id' => 'stl_telemetry_001',
            'sponsor_id'    => 'sp_telemetry',
            'total_cents'   => 10000,
            'currency'      => 'AUD',
        ];

        $worker = $this->createMock( \KH_SMMA\Reconciliation\SettlementWorker::class );
        $worker->method( 'get_settlement' )->willReturn( $settlement );

        $accounting_adapter = $this->createMock( AccountingAdapterContract::class );
        $accounting_adapter->method( 'adapter_name' )->willReturn( 'sftp_test' );
        $accounting_adapter->method( 'dry_run' )->willReturn( [ 'valid' => true ] );
        $accounting_adapter->method( 'execute' )->willReturn( [
            'status'   => 'delivered',
            'checksum' => 'abc123',
        ] );

        $called_with_delivery = false;
        $logger->method( 'log' )
            ->willReturnCallback( function ( $event, $ctx ) use ( &$called_with_delivery ) {
                if ( 'paid_delivery.delivered' === $event ) {
                    $d = $ctx['details'] ?? [];
                    if ( isset( $d['delivery_id'] ) && isset( $d['settlement_id'] ) && isset( $d['adapter'] ) ) {
                        $called_with_delivery = true;
                    }
                }
            } );

        $store = new DeliveryIdempotencyStore();
        $svc   = new SettlementDeliveryService( $db, $worker, $logger, $store );
        $svc->deliver( 'stl_telemetry_001', $accounting_adapter );

        $this->assertTrue( $called_with_delivery,
            'paid_delivery.delivered must be logged with delivery_id, settlement_id, adapter fields.' );
    }

    /**
     * ReconciliationService::execute_run() logs paid_recon.run.completed with required fields.
     */
    public function test_recon_run_completed_logs_required_fields(): void {
        $logger = $this->createMock( AuditLogger::class );

        $called_with_completed = false;
        $logger->method( 'log' )
            ->willReturnCallback( function ( $event, $ctx ) use ( &$called_with_completed ) {
                if ( 'paid_recon.run.completed' === $event ) {
                    if ( isset( $ctx['run_id'] ) && isset( $ctx['total_rows'] )
                        && isset( $ctx['variance_rows'] ) && isset( $ctx['checksum'] ) ) {
                        $called_with_completed = true;
                    }
                }
            } );

        // Use the integration-style in-memory wpdb.
        $db = new TelemetryReconWpdb();

        $source_svc = $this->createMock( PaidReconciliationService::class );
        $svc        = new ReconciliationService( $db, $logger, $source_svc );

        $source_row = [
            'reconciliation_id' => 'rec_telem_001',
            'manifest_id'       => 'man_telem_run',
            'sponsor_id'        => 'sp_telem',
            'schedule_id'       => 'sched_telem',
            'channel'           => 'google_sandbox',
            'estimated_spend'   => 100.0,
            'actual_spend'      => 101.0,
            'currency'          => 'AUD',
            'operation_ids'     => '["op_telem_001"]',
            'status'            => 'reconciled',
            'created_at'        => '2026-03-04 00:00:00',
            'updated_at'        => '2026-03-04 00:00:00',
        ];
        $db->seed_source_rows( [ $source_row ] );

        $run = $svc->start_run( [ 'initiator' => 'telemetry_test' ] );
        $svc->execute_run( $run['run_id'] );

        $this->assertTrue( $called_with_completed,
            'paid_recon.run.completed must be logged with run_id, total_rows, variance_rows, checksum.' );
    }

    /**
     * ReconciliationService::execute_run() logs paid_recon.row.variance when a row exceeds tolerance.
     */
    public function test_recon_row_variance_logs_required_fields(): void {
        $logger = $this->createMock( AuditLogger::class );

        $variance_logged = false;
        $logger->method( 'log' )
            ->willReturnCallback( function ( $event, $ctx ) use ( &$variance_logged ) {
                if ( 'paid_recon.row.variance' === $event ) {
                    if ( isset( $ctx['run_id'] ) && isset( $ctx['row_id'] )
                        && isset( $ctx['provider_reference'] ) && isset( $ctx['variance_cents'] ) ) {
                        $variance_logged = true;
                    }
                }
            } );

        $db = new TelemetryReconWpdb();

        $source_svc = $this->createMock( PaidReconciliationService::class );
        $svc        = new ReconciliationService( $db, $logger, $source_svc );

        // estimated=60, actual=70 → 16.7% above default 2% tolerance → variance row.
        $source_row = [
            'reconciliation_id' => 'rec_telem_var_001',
            'manifest_id'       => 'man_telem_var',
            'sponsor_id'        => 'sp_telem',
            'schedule_id'       => 'sched_telem',
            'channel'           => 'linkedin_sandbox',
            'estimated_spend'   => 60.0,
            'actual_spend'      => 70.0,
            'currency'          => 'AUD',
            'operation_ids'     => '["op_telem_var_001"]',
            'status'            => 'discrepancy',
            'created_at'        => '2026-03-04 00:00:00',
            'updated_at'        => '2026-03-04 00:00:00',
        ];
        $db->seed_source_rows( [ $source_row ] );

        $run = $svc->start_run( [ 'initiator' => 'telemetry_variance_test' ] );
        $svc->execute_run( $run['run_id'] );

        $this->assertTrue( $variance_logged,
            'paid_recon.row.variance must be logged with run_id, row_id, provider_reference, variance_cents.' );
    }
}

// ── In-memory wpdb for telemetry tests (mirrors ReconIntegrationWpdb) ─────────

class TelemetryReconWpdb extends wpdb {
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
        if ( str_contains( $query, 'kh_paid_reconciliations' ) ) {
            return $this->seeded_source_rows;
        }
        if ( preg_match( "/FROM\s+(\S+)\s+WHERE\s+run_id\s*=\s*'([^']+)'/i", $query, $m ) ) {
            $suffix = $this->table_suffix( $m[1] );
            $run_id = $m[2];
            return array_values( array_filter(
                $this->stores[ $suffix ] ?? [],
                fn( $r ) => ( $r['run_id'] ?? null ) === $run_id
            ) );
        }
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
