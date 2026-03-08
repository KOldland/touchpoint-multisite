<?php

use KH_SMMA\Adapters\ManualExportAdapter;
use KH_SMMA\Api\ManualExportController;
use KH_SMMA\API\RestController;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Services\Card1StateStore;
use KH_SMMA\Services\ExportBundleService;
use KH_SMMA\Services\FeatureFlags;
use KH_SMMA\Services\SmmaGenerator;
use KH_SMMA\Telemetry\EventEmitter;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/MockLLMClient.php';
require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/API/RestController.php';
require_once dirname( __DIR__, 2 ) . '/src/API/ManualExportController.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/PaidAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/AdapterIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/ManualExportAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/FeatureFlags.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/SmmaGenerator.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/Card1StateStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/ExportBundleService.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/ComplianceValidator.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/SchemaValidator.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/TraceContext.php';
require_once dirname( __DIR__, 2 ) . '/src/Telemetry/EventEmitter.php';
require_once dirname( __DIR__, 2 ) . '/src/Security/CapabilityManager.php';

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

final class WorkflowSmokeTest extends TestCase {
	private string $tmp_dir;

	protected function setUp(): void {
		parent::setUp();
		KH_SMMA\Tests\inject_mock_llm_client();
		putenv( 'KH_SMMA_TEST_MODE=ci' );
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_awareness_ok.json' );
		$this->tmp_dir = sys_get_temp_dir() . '/smma_workflow_smoke_' . uniqid();
		mkdir( $this->tmp_dir, 0775, true );

		$GLOBALS['kh_test_options'][ FeatureFlags::OPTION_KEY ] = array( 'smma' => true, 'smma_paid_adapters' => false );
		$GLOBALS['kh_test_options']['kh_smma_card1_state'] = array(
			'generate_requests' => array(),
			'variants' => array(),
			'variant_revisions' => array(),
			'schedules' => array(),
			'schedule_queue' => array(),
			'idempotency' => array(),
		);
	}

	public function test_full_workflow_generate_edit_schedule_export_is_deterministic(): void {
		$fixture = json_decode(
			(string) file_get_contents( dirname( __DIR__ ) . '/fixtures/smma/workflow_smoke_case.json' ),
			true
		);
		$events = array();
		add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$events ) {
			$events[] = (string) $event_name;
		}, 10, 3 );

		$db = new wpdb();
		$logger = new AuditLogger( $db );
		$store = new Card1StateStore();
		$emitter = new EventEmitter( $logger );
		$controller = new RestController( new FeatureFlags(), new SmmaGenerator(), $logger, null, null, $store, $emitter );

		$generated = $controller->handle_generate(
			new WP_REST_Request(
				array(
					'post_id' => (int) $fixture['post_id'],
					'blocks_summary' => (string) $fixture['blocks_summary'],
					'num_variants' => (int) $fixture['num_variants'],
					'geo_targets' => (array) $fixture['geo_targets'],
					'consent' => (bool) $fixture['consent'],
				),
				array( 'X-Request-Id' => 'req-workflow-smoke-1' )
			)
		);
		$this->assertIsArray( $generated );
		$variant_id = (string) $generated['variants'][0]['variant_id'];

		$edit = $controller->handle_variant_edit_v2(
			new WP_REST_Request(
				array(
					'variant_id' => $variant_id,
					'editor_user_id' => (int) $fixture['editor_user_id'],
					'text' => (string) $fixture['edit_text'],
					'metadata' => array( 'edit_reason' => 'workflow smoke update' ),
					'asset_hints' => array(),
				),
				array( 'Idempotency-Key' => 'idem-workflow-edit-1' )
			)
		);
		$this->assertIsArray( $edit );
		$this->assertArrayHasKey( 'revision_id', $edit );

		$schedule = $controller->handle_schedule(
			new WP_REST_Request(
				array(
					'variant_id' => $variant_id,
					'sponsor_id' => (string) $fixture['sponsor_id'],
					'schedule_time' => (string) $fixture['schedule_time'],
					'boost_options' => array(
						'budget_cents' => (int) $fixture['budget_cents'],
						'currency' => 'AUD',
						'channels' => array( 'linkedin' ),
						'prioritize' => 'reach',
					),
					'metadata' => array(),
				),
				array( 'Idempotency-Key' => 'idem-workflow-schedule-1' )
			)
		);
		$this->assertIsArray( $schedule );
		$this->assertArrayHasKey( 'schedule_id', $schedule );

		$export_service = new ExportBundleService( $this->tmp_dir );
		$manual_adapter = new ManualExportAdapter( $logger, null, $export_service );
		$export_controller = new ManualExportController( $logger, $store, $export_service, $manual_adapter );
		$created_bundle = $export_controller->create_schedule_bundle(
			new WP_REST_Request( array( 'schedule_id' => $schedule['schedule_id'] ) )
		);
		$this->assertIsArray( $created_bundle );
		$this->assertSame( 'created', $created_bundle['status'] );

		$emitter->emit( 'schedule.dispatch', array(
			'schedule_id' => (string) $schedule['schedule_id'],
			'adapter' => 'manual',
			'result' => 'dispatched',
			'service' => 'smma',
		) );

		$this->assertContains( 'generate.request', $events );
		$this->assertContains( 'generate.response', $events );
		$this->assertContains( 'variant.edit', $events );
		$this->assertContains( 'schedule.create', $events );
		$this->assertContains( 'schedule.dispatch', $events );
	}
}
