<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Adapters\GoogleSandboxAdapter;
use KH_SMMA\Adapters\LinkedInSandboxAdapter;
use KH_SMMA\Adapters\ManualExportAdapter;
use KH_SMMA\Adapters\AdapterIdempotencyStore;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/PaidAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/AdapterIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Helpers/DeterministicRng.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/GoogleSandboxAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/LinkedInSandboxAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/ManualExportAdapter.php';

/**
 * PAID — Sandbox Safety Tests.
 *
 * Verifies that all sandbox adapters (Google, LinkedIn, ManualExport):
 *   1. Produce identical output whether or not live credential env vars are set.
 *   2. Report network_calls = false in their metadata.
 *   3. Do not alter their output shape when SFTP/API credential vars are set.
 *
 * Sentinel credentials used:
 *   STRIPE_SECRET_KEY=sk_live_SENTINEL
 *   LINKEDIN_ACCESS_TOKEN=urn:li:accessToken:SENTINEL
 *   GOOGLE_ADS_TOKEN=ya29.SENTINEL
 *   GOOGLE_ADS_DEVELOPER_TOKEN=dev.SENTINEL
 *   KH_SFTP_HOST=sftp.sentinel.invalid
 *   KH_ACCOUNTING_API_URL=https://sentinel.invalid/accounting
 *
 * 10 tests.
 */
final class SandboxSafetyTest extends TestCase {

    private const SENTINEL_VARS = [
        'STRIPE_SECRET_KEY'         => 'sk_live_SENTINEL',
        'LINKEDIN_ACCESS_TOKEN'     => 'urn:li:accessToken:SENTINEL',
        'GOOGLE_ADS_TOKEN'          => 'ya29.SENTINEL',
        'GOOGLE_ADS_DEVELOPER_TOKEN'=> 'dev.SENTINEL',
        'KH_SFTP_HOST'              => 'sftp.sentinel.invalid',
        'KH_ACCOUNTING_API_URL'     => 'https://sentinel.invalid/accounting',
    ];

    private AdapterIdempotencyStore $store;

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
        $GLOBALS['kh_test_filters'] = [];
        $this->store = new AdapterIdempotencyStore();
    }

    protected function tearDown(): void {
        // Clear any sentinel env vars set during tests.
        foreach ( array_keys( self::SENTINEL_VARS ) as $var ) {
            putenv( "{$var}=" );
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function make_manifest( string $manifest_id = 'man_safety_001' ): array {
        return [
            'manifest_id' => $manifest_id,
            'meta'        => [
                'sponsor_id'      => 'sp_safety',
                'idempotency_key' => "idem_{$manifest_id}",
            ],
            'operations'  => [
                [
                    'operation_id' => 'op_safety_001',
                    'bid'          => [ 'amount' => 50.0, 'currency' => 'AUD' ],
                    'start_time'   => '2026-03-01T00:00:00Z',
                    'end_time'     => '2026-03-08T00:00:00Z',
                ],
            ],
        ];
    }

    private function set_sentinels(): void {
        foreach ( self::SENTINEL_VARS as $var => $value ) {
            putenv( "{$var}={$value}" );
        }
    }

    private function clear_sentinels(): void {
        foreach ( array_keys( self::SENTINEL_VARS ) as $var ) {
            putenv( "{$var}=" );
        }
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * GoogleSandboxAdapter::dry_run() returns identical spend regardless of live secret env vars.
     */
    public function test_google_sandbox_dry_run_reads_no_live_secrets(): void {
        $adapter  = new GoogleSandboxAdapter();
        $manifest = $this->make_manifest( 'man_g_dry_safety' );

        $this->clear_sentinels();
        $baseline = $adapter->dry_run( $manifest );

        $this->set_sentinels();
        $with_sentinels = $adapter->dry_run( $manifest );

        $this->assertSame( $baseline['total_estimated_spend'], $with_sentinels['total_estimated_spend'] );
        $this->assertSame( $baseline['manifest_id'], $with_sentinels['manifest_id'] );
        $this->assertCount( count( $baseline['operations'] ), $with_sentinels['operations'] );
        $this->assertSame( $baseline['operations'][0]['confidence'], $with_sentinels['operations'][0]['confidence'] );
    }

    /**
     * GoogleSandboxAdapter::execute() returns identical spend regardless of live secret env vars.
     */
    public function test_google_sandbox_execute_reads_no_live_secrets(): void {
        $manifest = $this->make_manifest( 'man_g_exec_safety' );

        $this->clear_sentinels();
        $a1 = new GoogleSandboxAdapter( null, new AdapterIdempotencyStore() );
        $baseline = $a1->execute( $manifest );

        $this->set_sentinels();
        $a2 = new GoogleSandboxAdapter( null, new AdapterIdempotencyStore() );
        $with_sentinels = $a2->execute( $manifest );

        $this->assertSame( $baseline['total_actual_spend'], $with_sentinels['total_actual_spend'] );
        $this->assertSame( $baseline['status'], $with_sentinels['status'] );
        $this->assertSame( $baseline['manifest_id'], $with_sentinels['manifest_id'] );
    }

    /**
     * LinkedInSandboxAdapter::dry_run() returns identical spend regardless of live secret env vars.
     */
    public function test_linkedin_sandbox_dry_run_reads_no_live_secrets(): void {
        $adapter  = new LinkedInSandboxAdapter();
        $manifest = $this->make_manifest( 'man_li_dry_safety' );

        $this->clear_sentinels();
        $baseline = $adapter->dry_run( $manifest );

        $this->set_sentinels();
        $with_sentinels = $adapter->dry_run( $manifest );

        $this->assertSame( $baseline['total_estimated_spend'], $with_sentinels['total_estimated_spend'] );
        $this->assertSame( $baseline['manifest_id'], $with_sentinels['manifest_id'] );
        $this->assertSame( $baseline['operations'][0]['confidence'], $with_sentinels['operations'][0]['confidence'] );
    }

    /**
     * LinkedInSandboxAdapter::execute() returns identical spend regardless of live secret env vars.
     */
    public function test_linkedin_sandbox_execute_reads_no_live_secrets(): void {
        $manifest = $this->make_manifest( 'man_li_exec_safety' );

        $this->clear_sentinels();
        $a1 = new LinkedInSandboxAdapter( null, new AdapterIdempotencyStore() );
        $baseline = $a1->execute( $manifest );

        $this->set_sentinels();
        $a2 = new LinkedInSandboxAdapter( null, new AdapterIdempotencyStore() );
        $with_sentinels = $a2->execute( $manifest );

        $this->assertSame( $baseline['total_actual_spend'], $with_sentinels['total_actual_spend'] );
        $this->assertSame( $baseline['status'], $with_sentinels['status'] );
        $this->assertSame( $baseline['manifest_id'], $with_sentinels['manifest_id'] );
    }

    /**
     * ManualExportAdapter::execute() stores bundle correctly regardless of live secret env vars.
     */
    public function test_manual_export_execute_reads_no_live_secrets(): void {
        $manifest = $this->make_manifest( 'man_me_exec_safety' );

        $this->clear_sentinels();
        $GLOBALS['kh_test_options'] = [];
        $a1       = new ManualExportAdapter( null, new AdapterIdempotencyStore() );
        $baseline = $a1->execute( $manifest );

        $this->set_sentinels();
        $GLOBALS['kh_test_options'] = [];
        $a2             = new ManualExportAdapter( null, new AdapterIdempotencyStore() );
        $with_sentinels = $a2->execute( $manifest );

        $this->assertSame( $baseline['total_estimated_spend'], $with_sentinels['total_estimated_spend'] );
        $this->assertSame( $baseline['status'], $with_sentinels['status'] );
        $this->assertSame( $baseline['manifest_id'], $with_sentinels['manifest_id'] );
    }

    /**
     * Google + LinkedIn sandbox adapters declare no network calls in metadata.
     */
    public function test_sandbox_dry_run_makes_no_network_calls(): void {
        $google   = new GoogleSandboxAdapter();
        $linkedin = new LinkedInSandboxAdapter();

        $g_meta = $google->get_metadata();
        $l_meta = $linkedin->get_metadata();

        $this->assertFalse( $g_meta['capabilities']['network_calls'],
            'GoogleSandboxAdapter must declare network_calls=false.' );
        $this->assertFalse( $l_meta['capabilities']['network_calls'],
            'LinkedInSandboxAdapter must declare network_calls=false.' );
    }

    /**
     * Google + LinkedIn sandbox adapters declare no network calls for execute in metadata.
     */
    public function test_sandbox_execute_makes_no_network_calls(): void {
        $google   = new GoogleSandboxAdapter();
        $linkedin = new LinkedInSandboxAdapter();

        $this->assertFalse( $google->get_metadata()['capabilities']['network_calls'],
            'GoogleSandboxAdapter execute must be offline.' );
        $this->assertFalse( $linkedin->get_metadata()['capabilities']['network_calls'],
            'LinkedInSandboxAdapter execute must be offline.' );
    }

    /**
     * ManualExportAdapter produces no outbound network calls (no wp_remote_get/post invocation).
     * Verified by confirming the bundle is stored in WP option, not retrieved via HTTP.
     */
    public function test_manual_export_makes_no_network_calls(): void {
        $GLOBALS['kh_test_options'] = [];
        $manifest = $this->make_manifest( 'man_me_net_safety' );

        $adapter  = new ManualExportAdapter( null, new AdapterIdempotencyStore() );
        $response = $adapter->execute( $manifest );

        // Bundle stored locally (WP option), not via HTTP → package_url starts with 'option:'.
        $this->assertStringStartsWith( 'option:', $response['package_url'],
            'ManualExportAdapter must store bundles in WP option, not via network.' );

        // Verify bundle retrievable from in-memory WP option store (no network involved).
        $bundle_key = 'kh_paid_bundle_' . $manifest['manifest_id'];
        $this->assertNotEmpty( $GLOBALS['kh_test_options'][ $bundle_key ],
            'Bundle must be present in WP options after execute().' );
    }

    /**
     * Sandbox adapter output is identical whether or not bad-credential env vars are present.
     */
    public function test_sandbox_output_is_unchanged_when_bad_secrets_present(): void {
        $manifest = $this->make_manifest( 'man_unchanged_safety' );

        $this->clear_sentinels();
        $g_clean = ( new GoogleSandboxAdapter() )->dry_run( $manifest );
        $l_clean = ( new LinkedInSandboxAdapter() )->dry_run( $manifest );

        $this->set_sentinels();
        $g_dirty = ( new GoogleSandboxAdapter() )->dry_run( $manifest );
        $l_dirty = ( new LinkedInSandboxAdapter() )->dry_run( $manifest );

        $this->assertSame( $g_clean['total_estimated_spend'], $g_dirty['total_estimated_spend'],
            'Google dry_run output must not change with bad secrets present.' );
        $this->assertSame( $l_clean['total_estimated_spend'], $l_dirty['total_estimated_spend'],
            'LinkedIn dry_run output must not change with bad secrets present.' );
    }

    /**
     * Asserts that all sentinel credential variable names tested are known risk vars.
     * Documents the exact variable names covered by this test suite.
     */
    public function test_known_secret_env_var_names_are_tested(): void {
        $required = [
            'STRIPE_SECRET_KEY',
            'LINKEDIN_ACCESS_TOKEN',
            'GOOGLE_ADS_TOKEN',
            'GOOGLE_ADS_DEVELOPER_TOKEN',
            'KH_SFTP_HOST',
            'KH_ACCOUNTING_API_URL',
        ];

        foreach ( $required as $var ) {
            $this->assertArrayHasKey( $var, self::SENTINEL_VARS,
                "Sentinel env var '{$var}' must be included in the safety test set." );
        }
    }
}
