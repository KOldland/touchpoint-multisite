<?php

declare( strict_types=1 );

use KH_SMMA\Scheduling\ScheduleRepository;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Sponsor\ApprovalTelemetryService;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Scheduling/ScheduleRepository.php';
require_once dirname( __DIR__, 2 ) . '/src/Sponsor/ApprovalTelemetryService.php';

final class ApprovalTelemetryServiceTest extends TestCase {
    private array $fixture;

    protected function setUp(): void {
        $GLOBALS['kh_test_filters'] = array();
        $GLOBALS['kh_test_caps'] = array();

        $path = dirname( __DIR__ ) . '/fixtures/sponsor/approval_telemetry_cases.json';
        $this->fixture = json_decode( (string) file_get_contents( $path ), true ) ?: array();
    }

    public function test_requested_event_emitted_and_audited(): void {
        $repo = $this->createMock( ScheduleRepository::class );
        $repo->method( 'pendingApprovalsCount' )->willReturn( 12 );

        /** @var AuditLogger&\PHPUnit\Framework\MockObject\MockObject $logger */
        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'record_event' ) )
            ->getMock();
        $logger->expects( $this->once() )
            ->method( 'record_event' )
            ->with(
                $this->anything(),
                $this->equalTo( 'sponsor.approval.requested' ),
                $this->anything(),
                $this->arrayHasKey( 'review_notes' )
            );

        $captured = array();
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$captured ) {
            $captured[] = array( 'event' => $event_name, 'payload' => $payload );
        }, 10, 3 );

        $service = new ApprovalTelemetryService( $repo, $logger );
        $case = $this->fixture['requested_case'];
        $service->approval_requested( $case['schedule'], (string) $case['schedule']['approval_reason'], 'trace_req_1' );

        $events = array_column( $captured, 'event' );
        $this->assertContains( 'sponsor.approval.requested', $events );
        $this->assertContains( 'alert.approval_backlog', $events );
    }

    public function test_approved_event_emitted_without_notes_in_telemetry(): void {
        $repo = $this->createMock( ScheduleRepository::class );
        $repo->method( 'pendingApprovalsCount' )->willReturn( 3 );
        $repo->method( 'sponsorDecisionStats' )->willReturn( array(
            'reject_count' => 1,
            'approval_count' => 10,
            'reject_rate' => 0.1,
        ) );

        /** @var AuditLogger&\PHPUnit\Framework\MockObject\MockObject $logger */
        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'record_event' ) )
            ->getMock();
        $logger->expects( $this->once() )
            ->method( 'record_event' )
            ->with(
                $this->anything(),
                $this->equalTo( 'sponsor.approval.approved' ),
                $this->anything(),
                $this->arrayHasKey( 'review_notes' )
            );

        $captured = null;
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$captured ) {
            if ( 'sponsor.approval.approved' === $event_name ) {
                $captured = $payload;
            }
        }, 10, 3 );

        $service = new ApprovalTelemetryService( $repo, $logger );
        $case = $this->fixture['approved_case'];
        $service->approval_approved( $case['schedule'], (int) $case['reviewer_user_id'], (string) $case['review_notes'], 'trace_app_1' );

        $this->assertIsArray( $captured );
        $this->assertArrayNotHasKey( 'review_notes', $captured );
        $this->assertSame( '902', $captured['schedule_id'] );
    }

    public function test_rejected_event_emitted_and_reject_spike_alert(): void {
        $repo = $this->createMock( ScheduleRepository::class );
        $repo->method( 'pendingApprovalsCount' )->willReturn( 4 );
        $repo->method( 'sponsorDecisionStats' )->willReturn( array(
            'reject_count' => 7,
            'approval_count' => 10,
            'reject_rate' => 0.7,
        ) );

        /** @var AuditLogger&\PHPUnit\Framework\MockObject\MockObject $logger */
        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'record_event' ) )
            ->getMock();
        $logger->expects( $this->once() )
            ->method( 'record_event' )
            ->with(
                $this->anything(),
                $this->equalTo( 'sponsor.approval.rejected' ),
                $this->anything(),
                $this->arrayHasKey( 'review_notes' )
            );

        $events = array();
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$events ) {
            $events[] = $event_name;
        }, 10, 3 );

        $service = new ApprovalTelemetryService( $repo, $logger );
        $case = $this->fixture['rejected_case'];
        $service->approval_rejected( $case['schedule'], (int) $case['reviewer_user_id'], (string) $case['review_notes'], 'trace_rej_1' );

        $this->assertContains( 'sponsor.approval.rejected', $events );
        $this->assertContains( 'alert.sponsor_reject_spike', $events );
    }
}
