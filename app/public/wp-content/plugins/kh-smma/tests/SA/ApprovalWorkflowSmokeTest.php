<?php

declare( strict_types=1 );

use KH_SMMA\Adapters\ManualExportAdapter;
use KH_SMMA\Api\ManualExportController;
use KH_SMMA\Scheduling\DispatchEligibilityService;
use KH_SMMA\Scheduling\ScheduleRepository;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Services\Card1StateStore;
use KH_SMMA\Services\ExportBundleService;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Security/CapabilityManager.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/Card1StateStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/ExportBundleService.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/PaidAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/ManualExportAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/API/ManualExportController.php';
require_once dirname( __DIR__, 2 ) . '/src/Scheduling/ScheduleRepository.php';
require_once dirname( __DIR__, 2 ) . '/src/Scheduling/DispatchEligibilityService.php';
require_once dirname( __DIR__, 2 ) . '/src/Sponsor/ApprovalTelemetryService.php';

if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        public $data;
        public int $status;

        public function __construct( $data = null, int $status = 200 ) {
            $this->data = $data;
            $this->status = $status;
        }

        public function header( $key, $value ): void {
            return;
        }
    }
}

final class ApprovalWorkflowSmokeTest extends TestCase {
    private array $fixture;

    protected function setUp(): void {
        $GLOBALS['kh_test_post_meta'] = array();
        $GLOBALS['kh_test_filters'] = array();
        $GLOBALS['kh_test_caps'] = array( 'edit_posts' => true );
        $GLOBALS['kh_test_options'] = array();

        $path = dirname( __DIR__ ) . '/fixtures/sponsor/approval_workflow_cases.json';
        $this->fixture = json_decode( (string) file_get_contents( $path ), true ) ?: array();
    }

    public function test_warn_pending_approve_dispatch_export_emits_expected_telemetry_sequence(): void {
        $case = $this->fixture['smoke'];
        $schedule = $case['schedule'];
        $schedule_id = (int) $schedule['schedule_id'];

        $GLOBALS['kh_test_post_meta'][ $schedule_id ] = array(
            '_kh_smma_approval_required' => 1,
            '_kh_smma_approval_status' => 'pending',
            '_kh_smma_sponsor_id' => (string) $schedule['sponsor_id'],
            '_kh_smma_compliance_status' => 'WARN',
        );

        $events = array();
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name ) use ( &$events ) {
            $events[] = (string) $event_name;
        }, 10, 3 );

        do_action( 'kh_smma_telemetry_event', 'schedule.create', array(
            'trace_id' => (string) $case['trace_id'],
            'schedule_id' => (string) $schedule['schedule_id'],
        ) );

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log', 'record_event' ) )
            ->getMock();

        $repo = new ScheduleRepository( $logger );
        $approved = $repo->approveSchedule(
            (string) $schedule['schedule_id'],
            48,
            'Smoke approval',
            (string) $case['trace_id']
        );
        $this->assertIsArray( $approved );
        $this->assertSame( 'approved', $approved['status'] );

        $eligibility = new DispatchEligibilityService();
        $dispatch_check = $eligibility->enforce_before_dispatch( null, $schedule_id, array(), array() );
        $this->assertNull( $dispatch_check );

        /** @var Card1StateStore&\PHPUnit\Framework\MockObject\MockObject $state_store */
        $state_store = $this->createMock( Card1StateStore::class );
        $state_store->method( 'get_schedule' )->willReturn( $schedule );
        $state_store->method( 'get_variant' )->willReturn( $case['variant'] );

        /** @var ExportBundleService&\PHPUnit\Framework\MockObject\MockObject $bundle_service */
        $bundle_service = $this->getMockBuilder( ExportBundleService::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'get_bundle' ) )
            ->getMock();
        $bundle_service->method( 'get_bundle' )->willReturn( $case['bundle'] );

        /** @var ManualExportAdapter&\PHPUnit\Framework\MockObject\MockObject $manual_adapter */
        $manual_adapter = $this->getMockBuilder( ManualExportAdapter::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'create_schedule_export_bundle' ) )
            ->getMock();
        $manual_adapter->method( 'create_schedule_export_bundle' )->willReturn( $case['bundle'] );

        $controller = new ManualExportController( $logger, $state_store, $bundle_service, $manual_adapter );

        $created = $controller->create_schedule_bundle( new WP_REST_Request(
            array( 'schedule_id' => (string) $schedule['schedule_id'] ),
            array( 'X-Trace-Id' => (string) $case['trace_id'] )
        ) );
        $this->assertIsArray( $created );
        $this->assertSame( 'created', $created['status'] );

        $download = $controller->download_schedule_bundle( new WP_REST_Request(
            array( 'schedule_id' => (string) $schedule['schedule_id'] ),
            array( 'X-Trace-Id' => (string) $case['trace_id'] )
        ) );

        $this->assertInstanceOf( WP_REST_Response::class, $download );
        $this->assertSame( 200, $download->status );
        $this->assertSame( (string) $schedule['schedule_id'], $download->data['schedule_id'] );
        $this->assertSame( (string) $schedule['variant_id'], $download->data['manifest']['variant_id'] );

        do_action( 'kh_smma_telemetry_event', 'schedule.dispatch', array(
            'trace_id' => (string) $case['trace_id'],
            'schedule_id' => (string) $schedule['schedule_id'],
            'result' => 'dispatched',
        ) );

        $create_index = array_search( 'schedule.create', $events, true );
        $approve_index = array_search( 'sponsor.approval.approved', $events, true );
        $bundle_index = array_search( 'export.bundle.created', $events, true );
        $dispatch_index = array_search( 'schedule.dispatch', $events, true );

        $this->assertNotFalse( $create_index );
        $this->assertNotFalse( $approve_index );
        $this->assertNotFalse( $bundle_index );
        $this->assertNotFalse( $dispatch_index );
        $this->assertLessThan( $approve_index, $create_index );
        $this->assertLessThan( $bundle_index, $approve_index );
        $this->assertLessThan( $dispatch_index, $bundle_index );
    }
}
