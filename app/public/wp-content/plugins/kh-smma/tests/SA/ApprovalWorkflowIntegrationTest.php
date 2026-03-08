<?php

declare( strict_types=1 );

use KH_SMMA\Api\SponsorApprovalController;
use KH_SMMA\Scheduling\DispatchEligibilityService;
use KH_SMMA\Scheduling\ScheduleRepository;
use KH_SMMA\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Scheduling/ScheduleRepository.php';
require_once dirname( __DIR__, 2 ) . '/src/Scheduling/DispatchEligibilityService.php';
require_once dirname( __DIR__, 2 ) . '/src/API/SponsorApprovalController.php';
require_once dirname( __DIR__, 2 ) . '/src/Sponsor/ApprovalTelemetryService.php';
require_once dirname( __DIR__, 2 ) . '/src/Sponsor/ApprovalSafetyService.php';

final class ApprovalWorkflowIntegrationTest extends TestCase {
    private array $fixture;

    protected function setUp(): void {
        $GLOBALS['kh_test_post_meta'] = array();
        $GLOBALS['kh_test_filters'] = array();
        $GLOBALS['kh_test_caps'] = array();
        $GLOBALS['kh_test_options'] = array();

        $path = dirname( __DIR__ ) . '/fixtures/sponsor/approval_workflow_cases.json';
        $this->fixture = json_decode( (string) file_get_contents( $path ), true ) ?: array();
    }

    public function test_warn_schedule_stays_visible_in_pending_approval_list(): void {
        $rows = $this->fixture['integration']['warn_visibility_rows'];

        $repo = new class( $rows ) extends ScheduleRepository {
            private array $rows;

            public function __construct( array $rows ) {
                $this->rows = $rows;
            }

            public function getPendingApprovals( array $filters = array(), ?array $fixture_rows = null ): array {
                return parent::getPendingApprovals( $filters, $this->rows );
            }

            public function getSponsors( ?array $fixture_rows = null ): array {
                return parent::getSponsors( $this->rows );
            }
        };

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $controller = new SponsorApprovalController( $repo, $logger );
        $response = $controller->list_schedules( new WP_REST_Request( array(
            'status' => 'pending',
            'page' => 1,
            'per_page' => 20,
        ) ) );

        $this->assertIsArray( $response );
        $this->assertSame( 1, $response['total'] );
        $this->assertSame( '1301', $response['rows'][0]['schedule_id'] );
        $this->assertSame( 'WARN', $response['rows'][0]['compliance_status'] );
        $this->assertTrue( (bool) $response['rows'][0]['approval_required'] );
    }

    public function test_dispatch_is_blocked_then_unblocked_after_approval(): void {
        $case = $this->fixture['integration']['dispatch_transition_case'];
        $schedule_id = (int) $case['schedule_id'];

        $GLOBALS['kh_test_post_meta'][ $schedule_id ] = array(
            '_kh_smma_approval_required' => 1,
            '_kh_smma_approval_status' => 'pending',
            '_kh_smma_sponsor_id' => (string) $case['sponsor_id'],
            '_kh_smma_compliance_status' => 'WARN',
        );

        $eligibility = new DispatchEligibilityService();
        $blocked = $eligibility->enforce_before_dispatch( null, $schedule_id, array(), array() );

        $this->assertInstanceOf( WP_Error::class, $blocked );
        $this->assertArrayHasKey( 'APPROVAL_REQUIRED', $blocked->errors );
        $this->assertSame( 'pending_approval', $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_schedule_status'] );
        $this->assertSame( 'Awaiting Approval', $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_queue_label'] );

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();
        $repo = new ScheduleRepository( $logger );
        $approved = $repo->approveSchedule(
            (string) $case['schedule_id'],
            (int) $case['reviewer_user_id'],
            (string) $case['review_notes'],
            'trace_integration_unblock_1'
        );

        $this->assertIsArray( $approved );
        $this->assertSame( 'approved', $approved['status'] );

        $allowed = $eligibility->enforce_before_dispatch( null, $schedule_id, array(), array() );
        $this->assertNull( $allowed );
        $this->assertSame( 'pending', $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_schedule_status'] );
        $this->assertSame( 'Ready', $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_queue_label'] );
    }
}
