<?php

use PHPUnit\Framework\TestCase;
use KH_SMMA\Adapters\ManualExportAdapter;
use KH_SMMA\Adapters\AdapterIdempotencyStore;
use KH_SMMA\Api\ManualExportController;
use KH_SMMA\Services\AuditLogger;

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/PaidAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/AdapterIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/ManualExportAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Security/CapabilityManager.php';
require_once dirname( __DIR__, 2 ) . '/src/API/ManualExportController.php';

if ( ! function_exists( 'register_rest_route' ) ) {
    function register_rest_route( $namespace, $route, $args ) {}
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        public $data;
        public int $status;
        public function __construct( $data = null, int $status = 200 ) {
            $this->data   = $data;
            $this->status = $status;
        }
    }
}

/**
 * PAID — ManualExport download integration tests.
 *
 * Verifies the full flow: ManualExportAdapter::execute() stores the bundle,
 * then ManualExportController::download_bundle() retrieves it correctly.
 *
 * 2 tests.
 */
final class ManualExportDownloadIntegrationTest extends TestCase {

    /** @var AuditLogger&\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    protected function setUp(): void {
        $GLOBALS['kh_test_options'] = [];
        $GLOBALS['kh_test_filters'] = [];
        $this->logger = $this->createMock( AuditLogger::class );
    }

    private function make_manifest( string $manifest_id = 'man_dl_int_001' ): array {
        return [
            'manifest_id' => $manifest_id,
            'meta'        => [
                'sponsor_id'      => 'sp_int_dl',
                'idempotency_key' => "idem_{$manifest_id}",
            ],
            'operations'  => [
                [
                    'operation_id' => 'op_dl_001',
                    'bid'          => [ 'amount' => 100.0, 'currency' => 'AUD' ],
                    'start_time'   => '2026-03-01T00:00:00Z',
                    'end_time'     => '2026-03-08T00:00:00Z',
                ],
            ],
        ];
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * Full flow: execute() stores bundle → download_bundle() returns bundle.
     */
    public function test_full_flow_execute_then_download_via_controller(): void {
        $manifest    = $this->make_manifest( 'man_dl_flow_001' );
        $adapter     = new ManualExportAdapter( $this->logger, new AdapterIdempotencyStore() );
        $exec_result = $adapter->execute( $manifest );

        $this->assertSame( 'awaiting_manual_export', $exec_result['status'] );

        // Retrieve via controller.
        $ctrl    = new ManualExportController( $this->logger );
        $request = new WP_REST_Request( [ 'manifest_id' => $manifest['manifest_id'] ] );
        $bundle  = $ctrl->download_bundle( $request );

        // rest_ensure_response() returns the bundle array directly in test context.
        $this->assertIsArray( $bundle );
        $this->assertSame( $manifest['manifest_id'], $bundle['manifest_id'] );
        $this->assertArrayHasKey( 'operations', $bundle );
        $this->assertArrayHasKey( 'meta', $bundle );
    }

    /**
     * Downloaded bundle contains the expected schema fields from execute() contract.
     */
    public function test_downloaded_bundle_has_expected_schema_fields(): void {
        $manifest = $this->make_manifest( 'man_dl_schema_001' );
        $adapter  = new ManualExportAdapter( null, new AdapterIdempotencyStore() );
        $adapter->execute( $manifest );

        $ctrl    = new ManualExportController( $this->logger );
        $request = new WP_REST_Request( [ 'manifest_id' => $manifest['manifest_id'] ] );
        $bundle  = $ctrl->download_bundle( $request );

        // Validate contract fields from docs/paid/sandbox_adapter.md.
        $this->assertArrayHasKey( 'manifest_id', $bundle,
            'Bundle must contain manifest_id.' );
        $this->assertArrayHasKey( 'operations', $bundle,
            'Bundle must contain operations array.' );
        $this->assertArrayHasKey( 'meta', $bundle,
            'Bundle must contain meta object.' );
        $this->assertSame( 'sp_int_dl', $bundle['meta']['sponsor_id'],
            'Bundle meta must preserve sponsor_id.' );
        $this->assertIsArray( $bundle['operations'],
            'Bundle operations must be an array.' );
        $this->assertNotEmpty( $bundle['operations'],
            'Bundle must have at least one operation.' );
    }
}
