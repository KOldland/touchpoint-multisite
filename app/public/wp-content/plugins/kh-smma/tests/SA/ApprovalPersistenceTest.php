<?php

declare( strict_types=1 );

use KH_SMMA\Scheduling\ScheduleRepository;
use KH_SMMA\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Scheduling/ScheduleRepository.php';
require_once dirname( __DIR__, 2 ) . '/src/Sponsor/ApprovalTelemetryService.php';

final class ApprovalPersistenceTest extends TestCase {
    private array $fixture;

    protected function setUp(): void {
        $GLOBALS['kh_test_post_meta'] = array();
        $GLOBALS['kh_test_filters'] = array();
        $GLOBALS['kh_test_caps'] = array();

        $path = dirname( __DIR__ ) . '/fixtures/sponsor/approval_workflow_cases.json';
        $this->fixture = json_decode( (string) file_get_contents( $path ), true ) ?: array();
    }

    public function test_approve_persists_expected_metadata_and_audit_event(): void {
        $case = $this->fixture['persistence']['approve_case'];
        $schedule_id = (int) $case['schedule_id'];

        $GLOBALS['kh_test_post_meta'][ $schedule_id ] = array(
            '_kh_smma_approval_status' => 'pending',
            '_kh_smma_approval_required' => 1,
            '_kh_smma_approval_reason' => 'sponsor_review',
            '_kh_smma_sponsor_id' => (string) $case['sponsor_id'],
            '_kh_smma_compliance_status' => 'WARN',
            '_kh_smma_compliance_ruleset_version' => 'r2026.03',
        );

        $approved_logged = false;
        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();
        $logger->expects( $this->atLeastOnce() )
            ->method( 'log' )
            ->willReturnCallback( function ( string $action, array $context ) use ( $case, $schedule_id, &$approved_logged ): void {
                if ( 'sponsor.approval.approved' !== $action ) {
                    return;
                }

                $approved_logged = (int) ( $context['object_id'] ?? 0 ) === $schedule_id
                    && (int) ( $context['user_id'] ?? 0 ) === (int) $case['reviewer_user_id']
                    && (string) ( $context['details']['review_notes'] ?? '' ) === (string) $case['review_notes'];
            } );

        $repo = new ScheduleRepository( $logger );
        $result = $repo->approveSchedule(
            (string) $case['schedule_id'],
            (int) $case['reviewer_user_id'],
            (string) $case['review_notes'],
            (string) $case['trace_id']
        );

        $this->assertIsArray( $result );
        $this->assertSame( 'approved', $result['status'] );
        $this->assertSame( (string) $case['trace_id'], $result['trace_id'] );

        $meta = $GLOBALS['kh_test_post_meta'][ $schedule_id ];
        $this->assertSame( 'approved', $meta['_kh_smma_approval_status'] );
        $this->assertSame( 0, $meta['_kh_smma_approval_required'] );
        $this->assertSame( '', $meta['_kh_smma_approval_reason'] );
        $this->assertSame( (int) $case['reviewer_user_id'], $meta['_kh_smma_approved_by'] );
        $this->assertSame( (string) $case['review_notes'], $meta['_kh_smma_review_notes'] );
        $this->assertSame( 'WARN', $meta['_kh_smma_last_approved_compliance_status'] );
        $this->assertTrue( $approved_logged );
    }

    public function test_reject_persists_expected_metadata_and_audit_event(): void {
        $case = $this->fixture['persistence']['reject_case'];
        $schedule_id = (int) $case['schedule_id'];

        $GLOBALS['kh_test_post_meta'][ $schedule_id ] = array(
            '_kh_smma_approval_status' => 'pending',
            '_kh_smma_approval_required' => 1,
            '_kh_smma_approval_reason' => 'sponsor_review',
            '_kh_smma_sponsor_id' => (string) $case['sponsor_id'],
            '_kh_smma_compliance_status' => 'WARN',
        );

        $rejected_logged = false;
        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();
        $logger->expects( $this->atLeastOnce() )
            ->method( 'log' )
            ->willReturnCallback( function ( string $action, array $context ) use ( $case, $schedule_id, &$rejected_logged ): void {
                if ( 'sponsor.approval.rejected' !== $action ) {
                    return;
                }

                $rejected_logged = (int) ( $context['object_id'] ?? 0 ) === $schedule_id
                    && (int) ( $context['user_id'] ?? 0 ) === (int) $case['reviewer_user_id']
                    && (string) ( $context['details']['review_notes'] ?? '' ) === (string) $case['review_notes'];
            } );

        $repo = new ScheduleRepository( $logger );
        $result = $repo->rejectSchedule(
            (string) $case['schedule_id'],
            (int) $case['reviewer_user_id'],
            (string) $case['review_notes'],
            (string) $case['trace_id']
        );

        $this->assertIsArray( $result );
        $this->assertSame( 'rejected', $result['status'] );
        $this->assertSame( (string) $case['trace_id'], $result['trace_id'] );

        $meta = $GLOBALS['kh_test_post_meta'][ $schedule_id ];
        $this->assertSame( 'rejected', $meta['_kh_smma_approval_status'] );
        $this->assertSame( (int) $case['reviewer_user_id'], $meta['_kh_smma_rejected_by'] );
        $this->assertSame( (string) $case['review_notes'], $meta['_kh_smma_review_notes'] );
        $this->assertArrayHasKey( '_kh_smma_rejected_at', $meta );
        $this->assertTrue( $rejected_logged );
    }
}
