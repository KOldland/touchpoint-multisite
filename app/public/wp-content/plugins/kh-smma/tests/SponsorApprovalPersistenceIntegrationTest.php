<?php

use KH_SMMA\Api\SponsorApprovalController;
use KH_SMMA\Scheduling\ScheduleRepository;
use KH_SMMA\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/Scheduling/ScheduleRepository.php';
require_once dirname( __DIR__ ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__ ) . '/src/API/SponsorApprovalController.php';

class SponsorApprovalPersistenceIntegrationTest extends TestCase {
    private function pendingFixture(): array {
        $path = __DIR__ . '/fixtures/smma/pending_schedule.json';
        return json_decode( (string) file_get_contents( $path ), true ) ?: array();
    }

    protected function setUp(): void {
        $GLOBALS['kh_test_post_meta'] = array();
        $GLOBALS['kh_test_filters'] = array();
    }

    public function test_approve_flow_persists_metadata_and_emits_audit_and_telemetry(): void {
        $fixture = $this->pendingFixture();
        $post_id = 201;
        $GLOBALS['kh_test_post_meta'][ $post_id ]['_kh_smma_approval_status'] = (string) $fixture['approval_status'];

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $logger->expects( $this->once() )
            ->method( 'log' )
            ->with(
                'sponsor.approval.approved',
                $this->callback( function ( $ctx ) use ( $post_id ) {
                    return isset( $ctx['details']['schedule_id'] )
                        && (string) $post_id === (string) $ctx['details']['schedule_id']
                        && isset( $ctx['details']['review_notes'] );
                } )
            );

        $captured = null;
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$captured ) {
            $captured = array( $event_name, $payload );
        }, 10, 3 );

        $repo = new ScheduleRepository( $logger );
        $controller = new SponsorApprovalController( $repo, $logger );

        $response = $controller->approve_schedules( new WP_REST_Request( array(
            'schedule_id' => (string) $post_id,
            'reviewer_user_id' => 77,
            'review_notes' => 'Approved in integration test',
        ) ) );

        $this->assertIsArray( $response );
        $this->assertSame( 'approved', $response['status'] );
        $this->assertSame( 'approved', $GLOBALS['kh_test_post_meta'][ $post_id ]['_kh_smma_approval_status'] );
        $this->assertSame( 77, $GLOBALS['kh_test_post_meta'][ $post_id ]['_kh_smma_approved_by'] );

        $this->assertNotNull( $captured );
        $this->assertSame( 'sponsor.approval.approved', $captured[0] );
        $this->assertSame( (string) $post_id, $captured[1]['schedule_id'] );
        $this->assertArrayHasKey( 'trace_id', $captured[1] );
        $this->assertArrayNotHasKey( 'review_notes', $captured[1] );
    }

    public function test_reject_flow_persists_metadata_and_emits_audit_and_telemetry(): void {
        $post_id = 202;
        $GLOBALS['kh_test_post_meta'][ $post_id ]['_kh_smma_approval_status'] = 'pending';

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $logger->expects( $this->once() )
            ->method( 'log' )
            ->with(
                'sponsor.approval.rejected',
                $this->callback( function ( $ctx ) use ( $post_id ) {
                    return isset( $ctx['details']['schedule_id'] )
                        && (string) $post_id === (string) $ctx['details']['schedule_id'];
                } )
            );

        $captured = null;
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$captured ) {
            $captured = array( $event_name, $payload );
        }, 10, 3 );

        $repo = new ScheduleRepository( $logger );
        $controller = new SponsorApprovalController( $repo, $logger );

        $response = $controller->reject_schedules( new WP_REST_Request( array(
            'schedule_id' => (string) $post_id,
            'reviewer_user_id' => 78,
            'review_notes' => 'Rejected in integration test',
        ) ) );

        $this->assertIsArray( $response );
        $this->assertSame( 'rejected', $response['status'] );
        $this->assertSame( 'rejected', $GLOBALS['kh_test_post_meta'][ $post_id ]['_kh_smma_approval_status'] );
        $this->assertSame( 78, $GLOBALS['kh_test_post_meta'][ $post_id ]['_kh_smma_rejected_by'] );

        $this->assertNotNull( $captured );
        $this->assertSame( 'sponsor.approval.rejected', $captured[0] );
        $this->assertSame( (string) $post_id, $captured[1]['schedule_id'] );
    }
}
