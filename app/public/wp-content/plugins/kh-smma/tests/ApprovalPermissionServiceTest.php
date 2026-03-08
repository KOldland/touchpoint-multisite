<?php

use KH_SMMA\SponsorApproval\ApprovalPermissionService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/SponsorApproval/ApprovalPermissionService.php';

class ApprovalPermissionServiceTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['kh_test_caps'] = array();
        $GLOBALS['kh_test_user_meta'] = array();
        $GLOBALS['kh_test_current_user_id'] = 1;
    }

    private function fixture( string $key ): array {
        $path = __DIR__ . '/fixtures/sponsor_approval_permissions.json';
        $data = json_decode( (string) file_get_contents( $path ), true ) ?: array();
        return (array) ( $data[ $key ] ?? array() );
    }

    public function test_admin_can_approve_any_schedule(): void {
        $case = $this->fixture( 'admin' );
        $GLOBALS['kh_test_caps'] = $case['caps'];
        $GLOBALS['kh_test_current_user_id'] = (int) $case['user_id'];

        $service = new ApprovalPermissionService();
        $this->assertTrue( $service->can_approve_schedule( $case['schedule'], (int) $case['user_id'] ) );
    }

    public function test_sponsor_manager_can_approve_assigned_sponsor_schedule(): void {
        $case = $this->fixture( 'manager_allowed' );
        $GLOBALS['kh_test_caps'] = $case['caps'];
        $GLOBALS['kh_test_current_user_id'] = (int) $case['user_id'];
        $GLOBALS['kh_test_user_meta'][ (int) $case['user_id'] ]['assigned_sponsor_id'] = $case['assigned_sponsor_id'];

        $service = new ApprovalPermissionService();
        $this->assertTrue( $service->can_approve_schedule( $case['schedule'], (int) $case['user_id'] ) );
    }

    public function test_sponsor_manager_cannot_approve_other_sponsor_schedule(): void {
        $case = $this->fixture( 'manager_denied' );
        $GLOBALS['kh_test_caps'] = $case['caps'];
        $GLOBALS['kh_test_current_user_id'] = (int) $case['user_id'];
        $GLOBALS['kh_test_user_meta'][ (int) $case['user_id'] ]['assigned_sponsor_id'] = $case['assigned_sponsor_id'];

        $service = new ApprovalPermissionService();
        $this->assertFalse( $service->can_approve_schedule( $case['schedule'], (int) $case['user_id'] ) );
    }

    public function test_unauthorized_user_is_blocked(): void {
        $GLOBALS['kh_test_caps'] = array(
            'manage_options' => false,
            'manage_sponsors' => false,
            'edit_schedules' => false,
        );
        $GLOBALS['kh_test_current_user_id'] = 77;

        $service = new ApprovalPermissionService();
        $this->assertFalse( $service->can_manage_approvals( 77 ) );
        $this->assertFalse( $service->can_approve_schedule( array(
            'schedule_id' => '999',
            'sponsor_id' => 'sp_1',
            'approval_status' => 'pending',
        ), 77 ) );
    }
}
