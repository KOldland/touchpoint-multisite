<?php

use KH_SMMA\Scheduling\ScheduleRepository;
use KH_SMMA\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/Scheduling/ScheduleRepository.php';
require_once dirname( __DIR__ ) . '/src/Services/AuditLogger.php';

class ScheduleRepositoryTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['kh_test_post_meta'] = array();
        $GLOBALS['kh_test_filters'] = array();
    }

    private function fixtures(): array {
        $path = __DIR__ . '/fixtures/smma/pending_schedules.json';
        return json_decode( (string) file_get_contents( $path ), true ) ?: array();
    }

    private function historyFixtures(): array {
        $path = __DIR__ . '/fixtures/smma/approval_history.json';
        return json_decode( (string) file_get_contents( $path ), true ) ?: array();
    }

    public function test_default_returns_pending_only(): void {
        $repo = new ScheduleRepository();
        $result = $repo->getPendingApprovals( array(), $this->fixtures() );

        $this->assertSame( 2, $result['total'] );
        $this->assertSame( '101', $result['rows'][0]['schedule_id'] );
        $this->assertSame( '104', $result['rows'][1]['schedule_id'] );
    }

    public function test_status_and_sponsor_filters_apply(): void {
        $repo = new ScheduleRepository();
        $result = $repo->getPendingApprovals(
            array(
                'status' => 'approved',
                'sponsor_id' => 'sp_2',
            ),
            $this->fixtures()
        );

        $this->assertSame( 1, $result['total'] );
        $this->assertSame( '102', $result['rows'][0]['schedule_id'] );
    }

    public function test_search_by_schedule_id_or_title(): void {
        $repo = new ScheduleRepository();

        $by_id = $repo->getPendingApprovals(
            array(
                'status' => 'all',
                'search_term' => '103',
            ),
            $this->fixtures()
        );
        $this->assertSame( 1, $by_id['total'] );
        $this->assertSame( '103', $by_id['rows'][0]['schedule_id'] );

        $by_title = $repo->getPendingApprovals(
            array(
                'status' => 'all',
                'search_term' => 'Promo A',
            ),
            $this->fixtures()
        );
        $this->assertSame( 1, $by_title['total'] );
        $this->assertSame( '104', $by_title['rows'][0]['schedule_id'] );
    }

    public function test_approve_schedule_persists_metadata_and_logs_audit(): void {
        $GLOBALS['kh_test_post_meta'][101]['_kh_smma_approval_status'] = 'pending';

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $logger->expects( $this->once() )
            ->method( 'log' )
            ->with(
                'sponsor.approval.approved',
                $this->callback( function ( $ctx ) {
                    return isset( $ctx['details']['trace_id'] )
                        && isset( $ctx['details']['schedule_id'] )
                        && isset( $ctx['details']['reviewer_id'] );
                } )
            );

        $repo = new ScheduleRepository( $logger );
        $result = $repo->approveSchedule( '101', 45, 'Looks good' );

        $this->assertIsArray( $result );
        $this->assertSame( 'approved', $result['status'] );
        $this->assertSame( '45', $result['approved_by'] );
        $this->assertSame( 'approved', $GLOBALS['kh_test_post_meta'][101]['_kh_smma_approval_status'] );
        $this->assertSame( 45, $GLOBALS['kh_test_post_meta'][101]['_kh_smma_approved_by'] );
        $this->assertSame( 'Looks good', $GLOBALS['kh_test_post_meta'][101]['_kh_smma_review_notes'] );
    }

    public function test_reject_schedule_persists_metadata(): void {
        $GLOBALS['kh_test_post_meta'][102]['_kh_smma_approval_status'] = 'pending';

        $repo = new ScheduleRepository();
        $result = $repo->rejectSchedule( '102', 51, 'Needs revision' );

        $this->assertIsArray( $result );
        $this->assertSame( 'rejected', $result['status'] );
        $this->assertSame( '51', $result['rejected_by'] );
        $this->assertSame( 'rejected', $GLOBALS['kh_test_post_meta'][102]['_kh_smma_approval_status'] );
        $this->assertSame( 51, $GLOBALS['kh_test_post_meta'][102]['_kh_smma_rejected_by'] );
        $this->assertSame( 'Needs revision', $GLOBALS['kh_test_post_meta'][102]['_kh_smma_review_notes'] );
    }

    public function test_invalid_transition_is_blocked(): void {
        $GLOBALS['kh_test_post_meta'][103]['_kh_smma_approval_status'] = 'approved';

        $repo = new ScheduleRepository();
        $result = $repo->rejectSchedule( '103', 12, 'Override not allowed' );

        $this->assertTrue( is_wp_error( $result ) );
    }

    public function test_telemetry_emitted_on_persisted_decision(): void {
        $GLOBALS['kh_test_post_meta'][104]['_kh_smma_approval_status'] = 'pending';

        $captured = null;
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$captured ) {
            $captured = array( $event_name, $payload );
        }, 10, 3 );

        $repo = new ScheduleRepository();
        $repo->approveSchedule( '104', 7, 'Ready' );

        $this->assertNotNull( $captured );
        $this->assertSame( 'sponsor.approval.approved', $captured[0] );
        $this->assertSame( '104', $captured[1]['schedule_id'] );
        $this->assertSame( 7, $captured[1]['reviewer_id'] );
        $this->assertArrayHasKey( 'trace_id', $captured[1] );
        $this->assertArrayHasKey( 'timestamp', $captured[1] );
    }

    public function test_get_approval_history_returns_latest_first_events(): void {
        $repo = new ScheduleRepository();
        $history = $repo->getApprovalHistory( '101', $this->historyFixtures() );

        $this->assertCount( 3, $history );
        $this->assertSame( 'approved', $history[0]['event'] );
        $this->assertSame( 'submitted', $history[1]['event'] );
        $this->assertSame( 'rejected', $history[2]['event'] );
        $this->assertSame( 'Final approval', $history[0]['notes'] );
        $this->assertSame( 'Needs revision', $history[2]['notes'] );
    }

    public function test_get_approval_history_filters_other_schedule_records(): void {
        $repo = new ScheduleRepository();
        $history = $repo->getApprovalHistory( '101', $this->historyFixtures() );

        foreach ( $history as $event ) {
            $this->assertSame( '101', $event['schedule_id'] );
        }
    }
}
