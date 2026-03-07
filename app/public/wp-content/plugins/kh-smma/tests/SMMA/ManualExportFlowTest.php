<?php

use KH_SMMA\Adapters\ManualExportAdapter;
use KH_SMMA\Api\ManualExportController;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Services\Card1StateStore;
use KH_SMMA\Services\ExportBundleService;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/PaidAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/AdapterIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/ManualExportAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/Card1StateStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/ExportBundleService.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Security/CapabilityManager.php';
require_once dirname( __DIR__, 2 ) . '/src/API/ManualExportController.php';

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public $data;
		public $status;
		public function __construct( $data = null, int $status = 200 ) {
			$this->data = $data;
			$this->status = $status;
		}
	}
}

final class ManualExportFlowTest extends TestCase {
	private string $tmp_dir;

	protected function setUp(): void {
		parent::setUp();
		putenv( 'KH_SMMA_TEST_MODE=ci' );
		$this->tmp_dir = sys_get_temp_dir() . '/smma_flow_export_' . uniqid();
		mkdir( $this->tmp_dir, 0775, true );
		$GLOBALS['kh_test_options']['kh_smma_card1_state'] = array(
			'generate_requests' => array(),
			'variants' => array(),
			'variant_revisions' => array(),
			'schedules' => array(),
			'schedule_queue' => array(),
			'idempotency' => array(),
		);
	}

	public function test_approved_schedule_can_generate_and_download_bundle(): void {
		$store = new Card1StateStore();
		$store->create_generate_request( array( 'request_id' => 'req_export_1', 'post_id' => '11', 'status' => 'success' ) );
		$variant_id = $store->upsert_variant(
			'req_export_1',
			array(
				'variant_id' => 'var_export_1',
				'text' => 'Export me.',
				'rationale' => 'Ops export test',
				'asset_hints' => array(),
				'platform' => 'linkedin',
				'compliance_status' => 'OK',
				'compliance_reason' => '',
				'compliance' => array( 'status' => 'OK', 'reasons' => array() ),
			),
			array()
		);

		$schedule_result = $store->create_schedule(
			'idem-export-1',
			7,
			array(
				'variant_id' => $variant_id,
				'sponsor_id' => 'sp_1',
				'schedule_time' => '2026-04-01T10:00:00Z',
				'boost_options' => array( 'budget_cents' => 10000, 'channels' => array( 'linkedin' ) ),
				'status' => 'queued',
				'approval_required' => false,
				'approval_status' => 'approved',
				'compliance_status' => 'OK',
				'compliance_reason' => '',
			)
		);
		$schedule_id = $schedule_result['schedule']['schedule_id'];

		$db = new wpdb();
		$logger = new AuditLogger( $db );
		$service = new ExportBundleService( $this->tmp_dir );
		$adapter = new ManualExportAdapter( $logger, null, $service );
		$controller = new ManualExportController( $logger, $store, $service, $adapter );

		$created = $controller->create_schedule_bundle(
			new WP_REST_Request( array( 'schedule_id' => $schedule_id ), array( 'X-Trace-Id' => 'trace-export-1' ) )
		);
		$this->assertIsArray( $created );
		$this->assertSame( 'created', $created['status'] );
		$this->assertSame( $schedule_id, $created['schedule_id'] );

		$download = $controller->download_schedule_bundle(
			new WP_REST_Request( array( 'schedule_id' => $schedule_id ), array( 'X-Trace-Id' => 'trace-export-1' ) )
		);
		$this->assertInstanceOf( WP_REST_Response::class, $download );
		$this->assertSame( 200, $download->status );
		$this->assertSame( 'application/zip', $download->data['content_type'] );
		$this->assertSame( 'approved', $download->data['manifest']['approval_status'] );
		$this->assertSame( 'OK', $download->data['manifest']['compliance_status'] );
	}
}
