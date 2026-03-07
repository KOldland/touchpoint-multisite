<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Adapters\LinkedInSandboxAdapter;
use KH_SMMA\Adapters\GoogleSandboxAdapter;
use KH_SMMA\Adapters\AdapterIdempotencyStore;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/PaidAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/AdapterIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Helpers/DeterministicRng.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/LinkedInSandboxAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/GoogleSandboxAdapter.php';

/**
 * PAID-03 — Integration smoke tests: sandbox adapters in simulated SMMA schedule flow.
 *
 * These tests simulate the end-to-end path from a schedule payload →
 * dry_run → execute (× 2 for idempotency) without any network calls.
 * All adapters run 100% offline.
 */
final class PaidSandboxIntegrationTest extends TestCase {

    private const LI_FIXTURE = __DIR__ . '/../fixtures/golden/paid_adapter_dry_run_manifest.json';
    private const GG_FIXTURE = __DIR__ . '/../fixtures/golden/google_sandbox_dry_run_manifest.json';

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
    }

    /**
     * Simulates: schedule dispatched → LinkedIn sandbox dry_run → execute → idempotent re-execute.
     *
     * Assertions:
     * - dry_run returns required fields
     * - execute returns 'success' with deterministic operation_id_on_channel
     * - second execute with same idempotency_key returns identical response (no double-write)
     */
    public function test_linkedin_dry_run_then_execute_idempotent(): void {
        $manifest = json_decode( (string) file_get_contents( self::LI_FIXTURE ), true );

        $store   = new AdapterIdempotencyStore();
        $adapter = new LinkedInSandboxAdapter( null, $store );

        // 1. dry_run
        $dry = $adapter->dry_run( $manifest );
        $this->assertSame( $manifest['manifest_id'], $dry['manifest_id'] );
        $this->assertIsFloat( $dry['total_estimated_spend'] );
        $this->assertGreaterThan( 0.0, $dry['total_estimated_spend'] );

        // 2. execute
        $first_exec = $adapter->execute( $manifest );
        $this->assertSame( 'success', $first_exec['status'] );
        $this->assertSame( 'li_op_51a139aa874b', $first_exec['operation_results'][0]['operation_id_on_channel'] );

        // 3. second execute must return identical response (idempotent)
        $second_exec = $adapter->execute( $manifest );
        $this->assertSame(
            $first_exec,
            $second_exec,
            'Second execute with same idempotency_key must return cached response.'
        );
    }

    /**
     * Simulates partial failure: one op forced to fail → partial_success returned.
     * Tests both LinkedIn and Google adapters in the same flow.
     */
    public function test_partial_failure_flow_linkedin_and_google(): void {
        $li_manifest = json_decode( (string) file_get_contents( self::LI_FIXTURE ), true );
        $gg_manifest = json_decode( (string) file_get_contents( self::GG_FIXTURE ), true );

        // Use distinct idempotency keys so both adapters can run independently.
        $li_manifest['meta']['idempotency_key'] = 'bbbb0000-0000-0000-0000-000000000001';
        $gg_manifest['meta']['idempotency_key'] = 'bbbb0000-0000-0000-0000-000000000002';

        // Force failure on op_1 for both.
        $li_manifest['meta']['simulate_failures'] = [ 'op_1' => true ];
        $gg_manifest['meta']['simulate_failures'] = [ 'op_1' => false ];

        $li_store   = new AdapterIdempotencyStore();
        $li_adapter = new LinkedInSandboxAdapter( null, $li_store );

        $gg_store   = new AdapterIdempotencyStore();
        $gg_adapter = new GoogleSandboxAdapter( null, $gg_store );

        $li_res = $li_adapter->execute( $li_manifest );
        $gg_res = $gg_adapter->execute( $gg_manifest );

        // LinkedIn: partial_success, retryable=true
        $this->assertSame( 'partial_success', $li_res['status'] );
        $li_op = $li_res['operation_results'][0];
        $this->assertSame( 'failed', $li_op['result'] );
        $this->assertTrue( $li_op['error']['retryable'] );

        // Google: partial_success, retryable=false
        $this->assertSame( 'partial_success', $gg_res['status'] );
        $gg_op = $gg_res['operation_results'][0];
        $this->assertSame( 'failed', $gg_op['result'] );
        $this->assertFalse( $gg_op['error']['retryable'] );
    }
}
