<?php

use KH_SMMA\Notifications\ApprovalNotificationService;
use KH_SMMA\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__ ) . '/src/Notifications/ApprovalNotificationService.php';

class ApprovalNotificationServiceTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['kh_test_post_meta'] = array();
        $GLOBALS['kh_test_options'] = array();
        $GLOBALS['kh_test_sent_mail'] = array();
        $GLOBALS['kh_test_filters'] = array();
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
    }

    private function fixture( string $key ): array {
        $path = __DIR__ . '/fixtures/sponsor_notifications.json';
        $data = json_decode( (string) file_get_contents( $path ), true ) ?: array();
        return (array) ( $data[ $key ] ?? array() );
    }

    public function test_approval_triggers_in_app_email_telemetry_and_audit(): void {
        $schedule_id = 101;
        $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_created_by'] = 11;
        $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_editor_user_id'] = 22;
        $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_sponsor_contact_email'] = 'sponsor@example.com';

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $logger->expects( $this->exactly( 3 ) )
            ->method( 'log' )
            ->with(
                'sponsor.notification.sent',
                $this->callback( function ( $ctx ) {
                    return isset( $ctx['details']['schedule_id'] )
                        && isset( $ctx['details']['notification_type'] )
                        && isset( $ctx['details']['recipient_type'] );
                } )
            );

        $service = new ApprovalNotificationService( $logger );

        $telemetry = array();
        add_action( 'kh_smma_telemetry_event', function ( $unused, $event_name, $payload ) use ( &$telemetry ) {
            if ( 'sponsor.notification.approval_sent' === $event_name ) {
                $telemetry[] = $payload;
            }
        }, 10, 3 );

        $service->handle_decision( $this->fixture( 'approval_decision' ) );

        $this->assertCount( 3, $GLOBALS['kh_test_sent_mail'] );
        $this->assertSame( 'Schedule Approved', $GLOBALS['kh_test_sent_mail'][0]['subject'] );
        $this->assertStringContainsString( 'The campaign is now eligible for dispatch.', $GLOBALS['kh_test_sent_mail'][0]['message'] );

        $notifications = $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_in_app_notifications'] ?? array();
        $this->assertCount( 3, $notifications );
        $this->assertSame( 'approved', $notifications[0]['decision'] );
        $this->assertArrayHasKey( 'timestamp', $notifications[0] );

        $this->assertCount( 3, $telemetry );
        $this->assertSame( '101', $telemetry[0]['schedule_id'] );
        $this->assertArrayHasKey( 'recipient_type', $telemetry[0] );
        $this->assertArrayNotHasKey( 'email', $telemetry[0] );
    }

    public function test_rejection_triggers_owner_editor_notifications_and_email_reason(): void {
        $schedule_id = 101;
        $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_created_by'] = 11;
        $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_editor_user_id'] = 22;
        $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_sponsor_contact_email'] = 'sponsor@example.com';
        $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_notify_sponsor_on_rejection'] = 0;

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $service = new ApprovalNotificationService( $logger );
        $service->handle_decision( $this->fixture( 'rejection_decision' ) );

        $this->assertCount( 2, $GLOBALS['kh_test_sent_mail'] );
        $this->assertSame( 'Schedule Rejected', $GLOBALS['kh_test_sent_mail'][0]['subject'] );
        $this->assertStringContainsString( 'Reason: Needs revision', $GLOBALS['kh_test_sent_mail'][0]['message'] );
    }

    public function test_duplicate_event_replay_is_suppressed_by_idempotency_guard(): void {
        $schedule_id = 101;
        $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_created_by'] = 11;
        $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_editor_user_id'] = 22;

        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $logger->expects( $this->exactly( 2 ) )->method( 'log' );

        $service = new ApprovalNotificationService( $logger );
        $decision = $this->fixture( 'rejection_decision' );
        $service->handle_decision( $decision );
        $service->handle_decision( $decision );

        $this->assertCount( 2, $GLOBALS['kh_test_sent_mail'] );
        $this->assertCount( 2, $GLOBALS['kh_test_post_meta'][ $schedule_id ]['_kh_smma_in_app_notifications'] ?? array() );
    }

    public function test_email_payload_template_is_constructed_correctly(): void {
        $logger = $this->getMockBuilder( AuditLogger::class )
            ->disableOriginalConstructor()
            ->onlyMethods( array( 'log' ) )
            ->getMock();

        $service = new ApprovalNotificationService( $logger );

        $approved = $service->build_email( 'approved', '123', 'Sponsor Manager', '2026-03-06 10:00:00', '' );
        $this->assertSame( 'Schedule Approved', $approved['subject'] );
        $this->assertStringContainsString( 'Schedule ID: 123', $approved['body'] );
        $this->assertStringContainsString( 'Reviewer: Sponsor Manager', $approved['body'] );

        $rejected = $service->build_email( 'rejected', '123', 'Sponsor Manager', '2026-03-06 10:00:00', 'Policy mismatch' );
        $this->assertSame( 'Schedule Rejected', $rejected['subject'] );
        $this->assertStringContainsString( 'Reason: Policy mismatch', $rejected['body'] );
    }
}
