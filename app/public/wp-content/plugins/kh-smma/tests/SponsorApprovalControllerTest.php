<?php

use KH_SMMA\Api\SponsorApprovalController;
use KH_SMMA\Notifications\ApprovalNotificationService;
use KH_SMMA\SponsorApproval\ApprovalPermissionService;
use KH_SMMA\Scheduling\ScheduleRepository;
use KH_SMMA\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/Scheduling/ScheduleRepository.php';
require_once dirname( __DIR__ ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__ ) . '/src/API/SponsorApprovalController.php';
require_once dirname( __DIR__ ) . '/src/Notifications/ApprovalNotificationService.php';
require_once dirname( __DIR__ ) . '/src/SponsorApproval/ApprovalPermissionService.php';
require_once dirname( __DIR__ ) . '/src/Sponsor/ApprovalTelemetryService.php';
require_once dirname( __DIR__ ) . '/src/Sponsor/ApprovalSafetyService.php';

class FixtureScheduleRepository extends ScheduleRepository {
    private array $fixtures;
    private array $history_fixtures;

    public function __construct( array $fixtures, array $history_fixtures = array() ) {
        $this->fixtures = $fixtures;
        $this->history_fixtures = $history_fixtures;
    }

    public function getPendingApprovals( array $filters = array(), ?array $fixture_rows = null ): array {
        return parent::getPendingApprovals( $filters, $this->fixtures );
    }

    public function getSponsors( ?array $fixture_rows = null ): array {
        return parent::getSponsors( $this->fixtures );
    }

    public function getApprovalHistory( string $schedule_id, ?array $fixture_records = null ): array {
        return parent::getApprovalHistory( $schedule_id, $this->history_fixtures );
    }

    public function markScheduleForReReview( string $schedule_id, string $reason ): bool {
        foreach ( $this->fixtures as &$row ) {
            if ( (string) ( $row['schedule_id'] ?? '' ) !== (string) $schedule_id ) {
                continue;
            }

            $row['approval_status'] = 'pending';
            $row['approval_required'] = true;
            $row['approval_reason'] = $reason;
            return true;
        }

        return false;
    }

    public function findSchedulesImpactedByClaimChange( string $sponsor_id, array $removed_claims, ?array $fixture_rows = null ): array {
        return parent::findSchedulesImpactedByClaimChange( $sponsor_id, $removed_claims, $this->fixtures );
    }
}

class SponsorApprovalControllerTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['kh_test_post_meta'] = array();
        $GLOBALS['kh_test_filters'] = array();
        $GLOBALS['kh_test_options'] = array();
        $GLOBALS['kh_test_sent_mail'] = array();
        $GLOBALS['kh_test_users'] = array(
            11 => array(
                'ID' => 11,
                'display_name' => 'Owner User',
                'user_email' => 'owner@example.com',
            ),
            22 => array(
                'ID' => 22,
                'display_name' => 'Editor User',
                'user_email' => 'editor@example.com',
            ),
            45 => array(
                'ID' => 45,
                'display_name' => 'Sponsor Manager',
                'user_email' => 'reviewer@example.com',
            ),
        );
        $GLOBALS['kh_test_caps'] = array();
        $GLOBALS['kh_test_user_meta'] = array();
        $GLOBALS['kh_test_current_user_id'] = 1;
    }

    private function fixtures(): array {
        $path = __DIR__ . '/fixtures/smma/pending_schedules.json';
        return json_decode( (string) file_get_contents( $path ), true ) ?: array();
    }

    private function historyFixtures(): array {
        $path = __DIR__ . '/fixtures/smma/approval_history.json';
        return json_decode( (string) file_get_contents( $path ), true ) ?: array();
    }

    public function test_list_endpoint_returns_filtered_rows(): void {
        $repo = new FixtureScheduleRepository( $this->fixtures() );
        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();
        $controller = new SponsorApprovalController( $repo, $logger );

        $request = new WP_REST_Request(
            array(
                'status' => 'pending',
                'search_term' => 'Launch',
                'page' => 1,
                'per_page' => 25,
            )
        );

        $result = $controller->list_schedules( $request );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'rows', $result );
        $this->assertSame( 1, $result['total'] );
        $this->assertSame( '101', $result['rows'][0]['schedule_id'] );
    }

    public function test_review_started_returns_ok(): void {
        $repo = new ScheduleRepository();
        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();
        $controller = new SponsorApprovalController( $repo, $logger );

        $request = new WP_REST_Request(
            array(
                'schedule_ids' => array( '101', '104' ),
                'reviewer_user_id' => 55,
            )
        );

        $response = $controller->review_started( $request );

        $this->assertIsArray( $response );
        $this->assertSame( 'ok', $response['status'] );
        $this->assertSame( 2, $response['count'] );
    }

    public function test_approve_endpoint_persists_and_returns_structured_response(): void {
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_approval_status'] = 'pending';

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $repo = new ScheduleRepository( $logger );
        $controller = new SponsorApprovalController( $repo, $logger );

        $request = new WP_REST_Request(
            array(
                'schedule_id' => '101',
                'reviewer_user_id' => 45,
                'review_notes' => 'Approved from test',
            )
        );

        $response = $controller->approve_schedules( $request );

        $this->assertIsArray( $response );
        $this->assertSame( 1, $response['count'] );
        $this->assertSame( 'approved', $response['results'][0]['status'] );
        $this->assertSame( '101', $response['results'][0]['schedule_id'] );
        $this->assertSame( 'approved', $GLOBALS['kh_test_post_meta'][101]['_kh_smma_approval_status'] );
    }

    public function test_reject_endpoint_persists_and_returns_structured_response(): void {
        $GLOBALS['kh_test_post_meta'][104]['_kh_smma_approval_status'] = 'pending';

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $repo = new ScheduleRepository( $logger );
        $controller = new SponsorApprovalController( $repo, $logger );

        $request = new WP_REST_Request(
            array(
                'schedule_id' => '104',
                'reviewer_user_id' => 52,
                'review_notes' => 'Rejected from test',
            )
        );

        $response = $controller->reject_schedules( $request );

        $this->assertIsArray( $response );
        $this->assertSame( 1, $response['count'] );
        $this->assertSame( 'rejected', $response['results'][0]['status'] );
        $this->assertSame( '104', $response['results'][0]['schedule_id'] );
        $this->assertSame( 'rejected', $GLOBALS['kh_test_post_meta'][104]['_kh_smma_approval_status'] );
    }

    public function test_invalid_transition_returns_structured_error(): void {
        $GLOBALS['kh_test_post_meta'][103]['_kh_smma_approval_status'] = 'approved';

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $repo = new ScheduleRepository( $logger );
        $controller = new SponsorApprovalController( $repo, $logger );

        $request = new WP_REST_Request(
            array(
                'schedule_id' => '103',
                'reviewer_user_id' => 10,
                'review_notes' => 'not allowed',
            )
        );

        $response = $controller->reject_schedules( $request );
        $this->assertIsArray( $response );
        $this->assertSame( 'INVALID_APPROVAL_TRANSITION', $response['error'] );
    }

    public function test_history_endpoint_returns_schedule_timeline(): void {
        $repo = new FixtureScheduleRepository( $this->fixtures(), $this->historyFixtures() );
        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();
        $controller = new SponsorApprovalController( $repo, $logger );

        $request = new WP_REST_Request(
            array(
                'schedule_id' => '101',
            )
        );

        $response = $controller->approval_history( $request );

        $this->assertIsArray( $response );
        $this->assertSame( '101', $response['schedule_id'] );
        $this->assertCount( 3, $response['history'] );
        $this->assertSame( 'approved', $response['history'][0]['event'] );
    }

    public function test_history_view_emits_telemetry_without_notes(): void {
        $repo = new FixtureScheduleRepository( $this->fixtures(), $this->historyFixtures() );
        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();
        $controller = new SponsorApprovalController( $repo, $logger );

        $captured = null;
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$captured ) {
            if ( 'sponsor.approval.history_viewed' === $event_name ) {
                $captured = $payload;
            }
        }, 10, 3 );

        $request = new WP_REST_Request(
            array(
                'schedule_id' => '101',
            )
        );

        $controller->approval_history( $request );

        $this->assertIsArray( $captured );
        $this->assertSame( '101', $captured['schedule_id'] );
        $this->assertSame( 1, $captured['viewer_user_id'] );
        $this->assertArrayHasKey( 'trace_id', $captured );
        $this->assertArrayHasKey( 'timestamp', $captured );
        $this->assertArrayNotHasKey( 'review_notes', $captured );
    }

    public function test_approve_endpoint_emits_notifications_after_successful_transition(): void {
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_approval_status'] = 'pending';
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_created_by'] = 11;
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_editor_user_id'] = 22;

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $repo = new ScheduleRepository( $logger );
        $controller = new SponsorApprovalController( $repo, $logger );
        $notifications = new ApprovalNotificationService( $logger );
        $notifications->register();

        $captured = array();
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$captured ) {
            if ( 'sponsor.notification.approval_sent' === $event_name ) {
                $captured[] = $payload;
            }
        }, 10, 3 );

        $request = new WP_REST_Request(
            array(
                'schedule_id' => '101',
                'reviewer_user_id' => 45,
                'review_notes' => 'Approved from test',
            )
        );

        $response = $controller->approve_schedules( $request );

        $this->assertIsArray( $response );
        $this->assertSame( 'approved', $response['results'][0]['status'] );
        $this->assertCount( 2, $GLOBALS['kh_test_sent_mail'] );
        $this->assertCount( 2, $captured );
        $this->assertSame( '101', $captured[0]['schedule_id'] );
    }

    public function test_admin_can_approve_any_schedule(): void {
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_approval_status'] = 'pending';
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_sponsor_id'] = 'sp_2';
        $GLOBALS['kh_test_caps'] = array(
            'manage_options' => true,
            'manage_sponsors' => false,
            'edit_schedules' => false,
        );
        $GLOBALS['kh_test_current_user_id'] = 1;

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $controller = new SponsorApprovalController(
            new ScheduleRepository( $logger ),
            $logger,
            new ApprovalPermissionService()
        );

        $response = $controller->approve_schedules( new WP_REST_Request( array(
            'schedule_id' => '101',
            'reviewer_user_id' => 45,
            'review_notes' => 'Admin decision',
        ) ) );

        $this->assertSame( 'approved', $response['results'][0]['status'] );
    }

    public function test_sponsor_manager_can_approve_assigned_sponsor_schedule(): void {
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_approval_status'] = 'pending';
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_sponsor_id'] = 'sp_1';
        $GLOBALS['kh_test_caps'] = array(
            'manage_options' => false,
            'manage_sponsors' => true,
            'edit_schedules' => true,
        );
        $GLOBALS['kh_test_current_user_id'] = 45;
        $GLOBALS['kh_test_user_meta'][45]['assigned_sponsor_id'] = 'sp_1';

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $controller = new SponsorApprovalController(
            new ScheduleRepository( $logger ),
            $logger,
            new ApprovalPermissionService()
        );

        $response = $controller->approve_schedules( new WP_REST_Request( array(
            'schedule_id' => '101',
            'reviewer_user_id' => 45,
            'review_notes' => 'Manager decision',
        ) ) );

        $this->assertSame( 'approved', $response['results'][0]['status'] );
    }

    public function test_sponsor_manager_cannot_approve_other_sponsor_schedule_and_emits_denied_telemetry(): void {
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_approval_status'] = 'pending';
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_sponsor_id'] = 'sp_1';
        $GLOBALS['kh_test_caps'] = array(
            'manage_options' => false,
            'manage_sponsors' => true,
            'edit_schedules' => true,
        );
        $GLOBALS['kh_test_current_user_id'] = 45;
        $GLOBALS['kh_test_user_meta'][45]['assigned_sponsor_id'] = 'sp_2';

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $controller = new SponsorApprovalController(
            new ScheduleRepository( $logger ),
            $logger,
            new ApprovalPermissionService()
        );

        $captured = null;
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$captured ) {
            if ( 'sponsor.approval.permission_denied' === $event_name ) {
                $captured = $payload;
            }
        }, 10, 3 );

        $response = $controller->approve_schedules( new WP_REST_Request( array(
            'schedule_id' => '101',
            'reviewer_user_id' => 45,
            'review_notes' => 'Denied decision',
        ) ) );

        $this->assertSame( 'APPROVAL_PERMISSION_DENIED', $response['error'] );
        $this->assertSame( 403, $response['status'] );
        $this->assertSame( 'permission_denied', $response['errors'][0]['code'] );
        $this->assertIsArray( $captured );
        $this->assertSame( '101', $captured['schedule_id'] );
    }

    public function test_bulk_approval_skips_unauthorized_rows_with_summary(): void {
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_approval_status'] = 'pending';
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_sponsor_id'] = 'sp_1';
        $GLOBALS['kh_test_post_meta'][102]['_kh_smma_approval_status'] = 'pending';
        $GLOBALS['kh_test_post_meta'][102]['_kh_smma_sponsor_id'] = 'sp_2';
        $GLOBALS['kh_test_caps'] = array(
            'manage_options' => false,
            'manage_sponsors' => true,
            'edit_schedules' => true,
        );
        $GLOBALS['kh_test_current_user_id'] = 45;
        $GLOBALS['kh_test_user_meta'][45]['assigned_sponsor_id'] = 'sp_1';

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' )
            )
            ->getMock();

        $controller = new SponsorApprovalController(
            new ScheduleRepository( $logger ),
            $logger,
            new ApprovalPermissionService()
        );

        $response = $controller->approve_schedules( new WP_REST_Request( array(
            'schedule_ids' => array( '101', '102' ),
            'reviewer_user_id' => 45,
            'review_notes' => 'Bulk decision',
        ) ) );

        $this->assertSame( 1, $response['approved'] );
        $this->assertSame( 1, $response['skipped'] );
        $this->assertSame( 'permission_denied', $response['errors'][0]['code'] );
    }

    public function test_sponsor_manager_list_is_scoped_to_assigned_sponsor(): void {
        $GLOBALS['kh_test_caps'] = array(
            'manage_options' => false,
            'manage_sponsors' => true,
            'edit_schedules' => true,
        );
        $GLOBALS['kh_test_current_user_id'] = 45;
        $GLOBALS['kh_test_user_meta'][45]['assigned_sponsor_id'] = 'sp_1';

        $repo = new FixtureScheduleRepository( $this->fixtures() );
        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();
        $controller = new SponsorApprovalController( $repo, $logger, new ApprovalPermissionService() );

        $response = $controller->list_schedules( new WP_REST_Request( array(
            'status' => 'all',
            'page' => 1,
            'per_page' => 25,
        ) ) );

        foreach ( $response['rows'] as $row ) {
            $this->assertSame( 'sp_1', $row['sponsor_id'] );
        }
    }

    public function test_approve_fails_if_compliance_is_fail(): void {
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_approval_status'] = 'pending';
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_sponsor_id'] = 'sp_1';
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_compliance_status'] = 'FAIL';

        $GLOBALS['kh_test_caps'] = array(
            'manage_options' => true,
            'manage_sponsors' => true,
            'edit_schedules' => true,
        );
        $GLOBALS['kh_test_current_user_id'] = 1;

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $controller = new SponsorApprovalController(
            new ScheduleRepository( $logger ),
            $logger,
            new ApprovalPermissionService()
        );

        $response = $controller->approve_schedules( new WP_REST_Request( array(
            'schedule_id' => '101',
            'reviewer_user_id' => 1,
            'review_notes' => 'should be blocked',
        ) ) );

        $this->assertSame( 'COMPLIANCE_FAIL_APPROVAL_BLOCKED', $response['error'] );
        $this->assertSame( 409, $response['status'] );
    }

    public function test_re_review_transition_when_compliance_changes(): void {
        $rows = array(
            array(
                'schedule_id' => '501',
                'post_title' => 'Approved Schedule',
                'sponsor_id' => 'sp_1',
                'sponsor_name' => 'Sponsor One',
                'submitter' => 'Owner',
                'requested_schedule_date' => '2026-03-10 12:00',
                'approval_status' => 'approved',
                'compliance_status' => 'WARN',
                'last_approved_compliance_status' => 'OK',
                'ruleset_version' => 'v2',
                'last_approved_ruleset_version' => 'v1',
            ),
        );

        $repo = new FixtureScheduleRepository( $rows );
        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $requested = null;
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$requested ) {
            if ( 'sponsor.approval.requested' === $event_name ) {
                $requested = $payload;
            }
        }, 10, 3 );

        $GLOBALS['kh_test_caps'] = array(
            'manage_options' => true,
        );

        $controller = new SponsorApprovalController( $repo, $logger, new ApprovalPermissionService() );
        $response = $controller->list_schedules( new WP_REST_Request( array(
            'status' => 'all',
            'page' => 1,
            'per_page' => 25,
        ) ) );

        $this->assertSame( 'pending', $response['rows'][0]['approval_status'] );
        $this->assertSame( 'compliance_changed', $response['rows'][0]['approval_reason'] );
        $this->assertIsArray( $requested );
        $this->assertSame( '501', $requested['schedule_id'] );
        $this->assertSame( 'sp_1', $requested['sponsor_id'] );
    }

    public function test_re_review_transition_when_claim_permissions_change(): void {
        $rows = array(
            array(
                'schedule_id' => '502',
                'post_title' => 'Claim Sensitive Schedule',
                'sponsor_id' => 'sp_1',
                'sponsor_name' => 'Sponsor One',
                'submitter' => 'Owner',
                'requested_schedule_date' => '2026-03-11 12:00',
                'approval_status' => 'approved',
                'compliance_status' => 'OK',
                'claims_used' => array( 'claim_b' ),
                'last_approved_allowed_claims' => array( 'claim_a', 'claim_b' ),
                'allowed_claims' => array( 'claim_a' ),
            ),
        );

        $repo = new FixtureScheduleRepository( $rows );
        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $GLOBALS['kh_test_caps'] = array(
            'manage_options' => true,
        );

        $controller = new SponsorApprovalController( $repo, $logger, new ApprovalPermissionService() );
        $response = $controller->list_schedules( new WP_REST_Request( array(
            'status' => 'all',
            'page' => 1,
            'per_page' => 25,
        ) ) );

        $this->assertSame( 'pending', $response['rows'][0]['approval_status'] );
        $this->assertSame( 'sponsor_claim_change', $response['rows'][0]['approval_reason'] );
    }

    public function test_approve_emits_telemetry_without_reviewer_notes(): void {
        $GLOBALS['kh_test_post_meta'][601]['_kh_smma_approval_status'] = 'pending';
        $GLOBALS['kh_test_post_meta'][601]['_kh_smma_sponsor_id'] = 'sp_6';
        $GLOBALS['kh_test_caps'] = array(
            'manage_options' => true,
        );

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log', 'record_event' ) )
            ->getMock();

        $captured = array();
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$captured ) {
            if ( 'sponsor.approval.approved' === $event_name ) {
                $captured[] = $payload;
            }
        }, 10, 3 );

        $controller = new SponsorApprovalController( new ScheduleRepository( $logger ), $logger, new ApprovalPermissionService() );
        $controller->approve_schedules( new WP_REST_Request( array(
            'schedule_id' => '601',
            'reviewer_user_id' => 12,
            'review_notes' => 'private note',
        ) ) );

        $this->assertNotEmpty( $captured );
        $this->assertArrayNotHasKey( 'review_notes', $captured[0] );
        $this->assertSame( '601', $captured[0]['schedule_id'] );
    }

    public function test_reject_emits_telemetry_without_reviewer_notes(): void {
        $GLOBALS['kh_test_post_meta'][602]['_kh_smma_approval_status'] = 'pending';
        $GLOBALS['kh_test_post_meta'][602]['_kh_smma_sponsor_id'] = 'sp_6';
        $GLOBALS['kh_test_caps'] = array(
            'manage_options' => true,
        );

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log', 'record_event' ) )
            ->getMock();

        $captured = array();
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$captured ) {
            if ( 'sponsor.approval.rejected' === $event_name ) {
                $captured[] = $payload;
            }
        }, 10, 3 );

        $controller = new SponsorApprovalController( new ScheduleRepository( $logger ), $logger, new ApprovalPermissionService() );
        $controller->reject_schedules( new WP_REST_Request( array(
            'schedule_id' => '602',
            'reviewer_user_id' => 14,
            'review_notes' => 'private reject note',
        ) ) );

        $this->assertNotEmpty( $captured );
        $this->assertArrayNotHasKey( 'review_notes', $captured[0] );
        $this->assertSame( '602', $captured[0]['schedule_id'] );
    }
}
