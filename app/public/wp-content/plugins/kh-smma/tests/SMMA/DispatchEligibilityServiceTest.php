<?php

use KH_SMMA\Scheduling\DispatchEligibilityService;
use KH_SMMA\Services\Card1StateStore;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Scheduling/DispatchEligibilityService.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/Card1StateStore.php';

final class DispatchEligibilityServiceTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		putenv( 'KH_SMMA_TEST_MODE=ci' );
		$GLOBALS['kh_test_post_meta'] = array();
		$GLOBALS['kh_test_options']['kh_smma_card1_state'] = array(
			'generate_requests' => array(),
			'variants' => array(),
			'variant_revisions' => array(),
			'schedules' => array(),
			'schedule_queue' => array(),
			'idempotency' => array(),
		);
	}

	public function test_pending_approval_blocks_dispatch(): void {
		update_post_meta( 77, '_kh_smma_approval_required', true );
		update_post_meta( 77, '_kh_smma_approval_status', 'pending' );

		$service = new DispatchEligibilityService();
		$result = $service->enforce_before_dispatch( null, 77, array(), array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertArrayHasKey( 'APPROVAL_REQUIRED', $result->errors );
		$this->assertSame( 'pending_approval', get_post_meta( 77, '_kh_smma_schedule_status', true ) );
		$this->assertSame( 'Awaiting Approval', get_post_meta( 77, '_kh_smma_queue_label', true ) );
	}

	public function test_rejected_blocks_dispatch(): void {
		update_post_meta( 78, '_kh_smma_approval_required', true );
		update_post_meta( 78, '_kh_smma_approval_status', 'rejected' );

		$service = new DispatchEligibilityService();
		$result = $service->enforce_before_dispatch( null, 78, array(), array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertArrayHasKey( 'APPROVAL_REQUIRED', $result->errors );
		$this->assertSame( 'rejected', get_post_meta( 78, '_kh_smma_schedule_status', true ) );
		$this->assertSame( 'Rejected', get_post_meta( 78, '_kh_smma_queue_label', true ) );
	}

	public function test_approved_or_not_required_allows_dispatch(): void {
		$service = new DispatchEligibilityService();

		update_post_meta( 79, '_kh_smma_approval_required', true );
		update_post_meta( 79, '_kh_smma_approval_status', 'approved' );
		$this->assertNull( $service->enforce_before_dispatch( null, 79, array(), array() ) );
		$this->assertSame( 'pending', get_post_meta( 79, '_kh_smma_schedule_status', true ) );

		update_post_meta( 80, '_kh_smma_approval_required', false );
		update_post_meta( 80, '_kh_smma_approval_status', 'pending' );
		$this->assertNull( $service->enforce_before_dispatch( null, 80, array(), array() ) );
	}

	public function test_card1_store_re_evaluates_after_approval_change(): void {
		$store = new Card1StateStore();
		$store->create_generate_request( array( 'request_id' => 'req_dispatch_1', 'post_id' => '1', 'status' => 'success' ) );
		$variant_id = $store->upsert_variant(
			'req_dispatch_1',
			array(
				'variant_id' => 'var_dispatch_1',
				'text' => 'dispatch copy',
				'rationale' => 'x',
				'asset_hints' => array(),
				'platform' => 'linkedin',
				'compliance_status' => 'OK',
				'compliance_reason' => '',
				'compliance' => array( 'status' => 'OK', 'reasons' => array() ),
			)
		);
		$created = $store->create_schedule(
			'idem-dispatch-1',
			9,
			array(
				'variant_id' => $variant_id,
				'sponsor_id' => 'sp_1',
				'schedule_time' => gmdate( 'c', time() - 120 ),
				'boost_options' => array( 'budget_cents' => 10000, 'channels' => array( 'linkedin' ) ),
				'approval_required' => true,
				'approval_status' => 'pending',
				'compliance_status' => 'WARN',
				'compliance_reason' => 'needs approval',
				'status' => 'pending_approval',
			)
		);
		$schedule_id = $created['schedule']['schedule_id'];

		$blocked = $store->reevaluate_dispatch_eligibility( $schedule_id );
		$this->assertFalse( $blocked['eligible'] );
		$this->assertSame( 'pending_approval', $blocked['status'] );

		$state = $GLOBALS['kh_test_options']['kh_smma_card1_state'];
		$state['schedules'][ $schedule_id ]['approval_status'] = 'approved';
		$GLOBALS['kh_test_options']['kh_smma_card1_state'] = $state;

		$eligible = $store->reevaluate_dispatch_eligibility( $schedule_id );
		$this->assertTrue( $eligible['eligible'] );
		$this->assertSame( 'queued_for_execution', $eligible['status'] );
	}
}
