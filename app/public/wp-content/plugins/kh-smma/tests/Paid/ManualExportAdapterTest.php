<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Adapters\ManualExportAdapter;
use KH_SMMA\Adapters\AdapterIdempotencyStore;
use KH_SMMA\Services\AuditLogger;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/Exceptions/AdapterExecutionException.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/PaidAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/AdapterIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/ManualExportAdapter.php';

/**
 * PAID-02 — ManualExportAdapter conformance tests.
 *
 * Verifies that ManualExportAdapter implements PaidAdapterContract correctly:
 * - dry_run() returns conformant response shape
 * - execute() returns awaiting_manual_export status with package_url
 * - execute() is idempotent for the same meta.idempotency_key
 * - execute() calls AuditLogger with the correct action
 * - operation_ids in dry_run response match the manifest input
 */
final class ManualExportAdapterTest extends TestCase {

    private const FIXTURE = __DIR__ . '/../fixtures/golden/paid_adapter_dry_run_manifest.json';

    private ManualExportAdapter $adapter;
    private AdapterIdempotencyStore $store;
    private array $manifest;

    protected function setUp(): void {
        // Reset in-memory WP options between tests.
        $GLOBALS['kh_test_options'] = [];

        $this->store   = new AdapterIdempotencyStore();
        $this->adapter = new ManualExportAdapter( null, $this->store );
        $this->manifest = json_decode( (string) file_get_contents( self::FIXTURE ), true );
    }

    // -------------------------------------------------------------------------
    // dry_run tests
    // -------------------------------------------------------------------------

    public function test_dry_run_response_has_required_fields(): void {
        $res = $this->adapter->dry_run( $this->manifest );

        $this->assertArrayHasKey( 'manifest_id', $res );
        $this->assertArrayHasKey( 'operations', $res );
        $this->assertArrayHasKey( 'total_estimated_spend', $res );
        $this->assertArrayHasKey( 'currency', $res );
        $this->assertArrayHasKey( 'timestamp', $res );
    }

    public function test_dry_run_estimated_spend_is_numeric_and_non_negative(): void {
        $res = $this->adapter->dry_run( $this->manifest );

        $this->assertIsFloat( $res['total_estimated_spend'] );
        $this->assertGreaterThanOrEqual( 0.0, $res['total_estimated_spend'] );
    }

    public function test_dry_run_operation_ids_match_manifest(): void {
        $res = $this->adapter->dry_run( $this->manifest );

        $manifest_ids  = array_column( $this->manifest['operations'], 'operation_id' );
        $response_ids  = array_column( $res['operations'], 'operation_id' );

        $this->assertSame( $manifest_ids, $response_ids );
    }

    // -------------------------------------------------------------------------
    // execute tests
    // -------------------------------------------------------------------------

    public function test_execute_returns_awaiting_manual_export(): void {
        $res = $this->adapter->execute( $this->manifest );

        $this->assertSame( 'awaiting_manual_export', $res['status'] );
        $this->assertNotEmpty( $res['package_url'] );
        $this->assertSame( $this->manifest['manifest_id'], $res['manifest_id'] );
    }

    public function test_execute_idempotency_returns_identical_response(): void {
        $manifest = $this->manifest;
        $manifest['meta']['idempotency_key'] = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $first  = $this->adapter->execute( $manifest );
        $second = $this->adapter->execute( $manifest );

        $this->assertSame(
            $first,
            $second,
            'execute() must return identical response for the same idempotency_key'
        );
    }

    public function test_execute_calls_audit_logger(): void {
        $mock_logger = $this->createMock( AuditLogger::class );
        $mock_logger->expects( $this->once() )
                    ->method( 'log' )
                    ->with(
                        $this->equalTo( 'paid_adapter.execute' ),
                        $this->callback( function ( $context ) {
                            return isset( $context['details']['manifest_id'] )
                                && isset( $context['details']['adapter'] )
                                && 'ManualExportAdapter' === $context['details']['adapter'];
                        } )
                    );

        $adapter = new ManualExportAdapter( $mock_logger, $this->store );
        $adapter->execute( $this->manifest );
    }
}
