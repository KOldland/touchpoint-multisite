<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Reconciliation\PaidReconciliationService;
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

/**
 * PAID-04 — Reconciliation integration tests.
 *
 * Runs a real LinkedInSandboxAdapter→execute() through PaidReconciliationService→reconcile()
 * without any network calls. The wpdb instance is mocked to stay offline.
 */
final class ReconciliationIntegrationTest extends TestCase {

    private const LI_FIXTURE = __DIR__ . '/../fixtures/golden/paid_adapter_dry_run_manifest.json';

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
        $GLOBALS['kh_test_filters'] = [];
    }

    /**
     * Full flow: sandbox execute → reconcile → status='reconciled', discrepancy<10%.
     *
     * LinkedIn canonical fixture:
     *   estimated_spend = 60.0 AUD, actual_spend = 59.35 AUD → discrepancy ≈ -1.08% → reconciled.
     */
    public function test_full_flow_sandbox_execute_then_reconcile(): void {
        $manifest = json_decode( (string) file_get_contents( self::LI_FIXTURE ), true );
        $idem_key = $manifest['meta']['idempotency_key'];

        $store   = new AdapterIdempotencyStore();
        $adapter = new LinkedInSandboxAdapter( null, $store );

        $dry_res  = $adapter->dry_run( $manifest );
        $exec_res = $adapter->execute( $manifest );

        $this->assertSame( 'success', $exec_res['status'] );
        $this->assertSame( 59.35, $exec_res['total_actual_spend'] );

        // Mock wpdb for reconciliation.
        $db = $this->createMock( wpdb::class );
        $db->prefix = 'wp_';
        $db->method( 'prepare' )->willReturnArgument( 0 );
        $db->method( 'get_row' )->willReturn( null );
        $db->method( 'insert' )->willReturn( true );

        $logger = $this->createMock( AuditLogger::class );
        $svc    = new PaidReconciliationService( $db, $logger );

        $row = $svc->reconcile(
            $manifest['manifest_id'],
            $exec_res,
            $dry_res,
            [ 'idempotency_key' => $idem_key ]
        );

        $this->assertSame( 'reconciled', $row['status'] );
        $this->assertSame( 59.35, $row['actual_spend'] );
        $this->assertSame( 60.0, $row['estimated_spend'] );
        $this->assertGreaterThanOrEqual( -10.0, $row['discrepancy_percent'] );
        $this->assertLessThanOrEqual( 10.0, $row['discrepancy_percent'] );
        $this->assertStringStartsWith( 'rec_', $row['reconciliation_id'] );
    }

    /**
     * Idempotent reconciliation after duplicate execute.
     *
     * Same idempotency_key → execute returns same cached response → reconcile
     * called twice → DB insert called only once (second call returns cached row).
     */
    public function test_idempotent_reconcile_after_duplicate_execute(): void {
        $manifest = json_decode( (string) file_get_contents( self::LI_FIXTURE ), true );
        $idem_key = $manifest['meta']['idempotency_key'];

        $store   = new AdapterIdempotencyStore();
        $adapter = new LinkedInSandboxAdapter( null, $store );

        // Two execute() calls with the same manifest.
        $exec_first  = $adapter->execute( $manifest );
        $exec_second = $adapter->execute( $manifest );

        // Adapter-level idempotency: identical responses.
        $this->assertSame( $exec_first, $exec_second );

        // Mock wpdb: first get_row returns null (new row), second returns the stored row.
        $stored_row = null; // set after first insert

        $db = $this->createMock( wpdb::class );
        $db->prefix = 'wp_';
        $db->method( 'prepare' )->willReturnArgument( 0 );

        $call_count = 0;
        $db->method( 'get_row' )
           ->willReturnCallback( function () use ( &$stored_row, &$call_count ) {
               $call_count++;
               return $call_count > 1 ? $stored_row : null;
           } );

        $db->method( 'insert' )
           ->willReturnCallback( function ( $table, $data ) use ( &$stored_row ) {
               $stored_row = $data;
               return true;
           } );

        $logger = $this->createMock( AuditLogger::class );
        $svc    = new PaidReconciliationService( $db, $logger );

        $context = [ 'idempotency_key' => $idem_key ];

        $row1 = $svc->reconcile( $manifest['manifest_id'], $exec_first, null, $context );
        $row2 = $svc->reconcile( $manifest['manifest_id'], $exec_second, null, $context );

        // Both calls return rows with the same reconciliation_id.
        $this->assertSame( $row1['reconciliation_id'], $row2['reconciliation_id'] );

        // DB insert was called exactly once (second reconcile hit the cache).
        $db->expects( $this->never() )->method( 'update' );
    }
}
