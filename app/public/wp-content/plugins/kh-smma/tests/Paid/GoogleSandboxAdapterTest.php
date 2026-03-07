<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Adapters\GoogleSandboxAdapter;
use KH_SMMA\Adapters\AdapterIdempotencyStore;
use KH_SMMA\Services\AuditLogger;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/PaidAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/AdapterIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Helpers/DeterministicRng.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/GoogleSandboxAdapter.php';

/**
 * PAID-03 — GoogleSandboxAdapter conformance tests.
 *
 * Verifies:
 * - dry_run() returns required fields and is deterministic.
 * - execute() returns success with deterministic operation_id_on_channel and actual_spend.
 * - execute() is idempotent for the same meta.idempotency_key.
 * - execute() returns partial_success with simulate_failures.
 * - execute() calls AuditLogger with 'paid_adapter.execute'.
 */
final class GoogleSandboxAdapterTest extends TestCase {

    /** Uses the Google-channel manifest variant for semantic correctness. */
    private const FIXTURE = __DIR__ . '/../fixtures/golden/google_sandbox_dry_run_manifest.json';

    private GoogleSandboxAdapter $adapter;
    private AdapterIdempotencyStore $store;
    private array $manifest;

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
        $this->store   = new AdapterIdempotencyStore();
        $this->adapter = new GoogleSandboxAdapter( null, $this->store );
        $this->manifest = json_decode( (string) file_get_contents( self::FIXTURE ), true );
    }

    // -------------------------------------------------------------------------
    // dry_run tests
    // -------------------------------------------------------------------------

    public function test_dry_run_has_required_fields(): void {
        $res = $this->adapter->dry_run( $this->manifest );

        $this->assertArrayHasKey( 'manifest_id', $res );
        $this->assertArrayHasKey( 'operations', $res );
        $this->assertArrayHasKey( 'total_estimated_spend', $res );
        $this->assertArrayHasKey( 'currency', $res );
        $this->assertArrayHasKey( 'timestamp', $res );
        $this->assertNotEmpty( $res['operations'] );

        $op = $res['operations'][0];
        $this->assertArrayHasKey( 'operation_id', $op );
        $this->assertArrayHasKey( 'estimated_spend', $op );
        $this->assertArrayHasKey( 'confidence', $op );
        $this->assertSame( 0.91, $op['confidence'] );
    }

    public function test_dry_run_is_deterministic(): void {
        $first  = $this->adapter->dry_run( $this->manifest );
        $second = $this->adapter->dry_run( $this->manifest );

        unset( $first['timestamp'], $second['timestamp'] );

        $this->assertSame( $first, $second, 'dry_run() must be deterministic for identical input.' );
    }

    public function test_dry_run_operation_ids_match_manifest(): void {
        $res = $this->adapter->dry_run( $this->manifest );

        $manifest_ids = array_column( $this->manifest['operations'], 'operation_id' );
        $response_ids = array_column( $res['operations'], 'operation_id' );

        $this->assertSame( $manifest_ids, $response_ids );
    }

    // -------------------------------------------------------------------------
    // execute tests
    // -------------------------------------------------------------------------

    public function test_execute_returns_success(): void {
        $res = $this->adapter->execute( $this->manifest );

        $this->assertSame( 'success', $res['status'] );
        $this->assertSame( $this->manifest['manifest_id'], $res['manifest_id'] );
        $this->assertNotEmpty( $res['operation_results'] );
        $this->assertSame( 'google_sandbox', $res['adapter_meta']['adapter'] );

        $op_result = $res['operation_results'][0];
        $this->assertSame( 'created', $op_result['result'] );
        $this->assertSame( 'g_op_84a2dfb9e9a6', $op_result['operation_id_on_channel'] );
        $this->assertSame( 60.07, $op_result['actual_spend'] );
    }

    public function test_execute_idempotency_returns_identical_response(): void {
        $first  = $this->adapter->execute( $this->manifest );
        $second = $this->adapter->execute( $this->manifest );

        $this->assertSame(
            $first,
            $second,
            'execute() must return identical response for the same idempotency_key.'
        );
    }

    public function test_execute_partial_failure_simulation(): void {
        $manifest = $this->manifest;
        $manifest['meta']['simulate_failures'] = [ 'op_1' => false ];

        $res = $this->adapter->execute( $manifest );

        $this->assertSame( 'partial_success', $res['status'] );

        $op_result = $res['operation_results'][0];
        $this->assertSame( 'failed', $op_result['result'] );
        $this->assertFalse( $op_result['error']['retryable'] );
        $this->assertSame( 'simulated_failure', $op_result['error']['code'] );
    }

    public function test_execute_calls_audit_logger(): void {
        $mock_logger = $this->createMock( AuditLogger::class );
        $mock_logger->expects( $this->once() )
                    ->method( 'log' )
                    ->with(
                        $this->equalTo( 'paid_adapter.execute' ),
                        $this->callback( function ( $context ) {
                            return isset( $context['details']['adapter'] )
                                && 'google_sandbox' === $context['details']['adapter']
                                && isset( $context['details']['manifest_id'] );
                        } )
                    );

        $adapter = new GoogleSandboxAdapter( $mock_logger, $this->store );
        $adapter->execute( $this->manifest );
    }
}
