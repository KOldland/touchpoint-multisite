<?php

use KH_SMMA\API\ScheduleController;
use KH_SMMA\Services\Card1StateStore;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/Card1StateStore.php';
require_once dirname( __DIR__, 2 ) . '/src/API/ScheduleController.php';

class ComplianceSchedulingTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		putenv( 'KH_SMMA_TEST_MODE=ci' );
		$GLOBALS['kh_test_options']['kh_smma_card1_state'] = array(
			'generate_requests' => array(),
			'variants' => array(),
			'variant_revisions' => array(),
			'schedules' => array(),
			'schedule_queue' => array(),
			'idempotency' => array(),
		);
	}

	public function test_fail_variant_is_blocked(): void {
		$store = new Card1StateStore();
		$store->create_generate_request( array( 'request_id' => 'req_c_fail', 'post_id' => '1', 'status' => 'success' ) );
		$store->upsert_variant(
			'req_c_fail',
			array(
				'variant_id' => 'var_fail',
				'text' => 'x',
				'rationale' => 'x',
				'asset_hints' => array(),
				'platform' => 'linkedin',
				'compliance_status' => 'FAIL',
				'compliance_reason' => 'banned phrase',
				'compliance' => array( 'status' => 'FAIL', 'reasons' => array( 'banned phrase' ) ),
			)
		);

		$controller = new ScheduleController( $store );
		$result = $controller->enforce_compliance_gate( new WP_REST_Request( array( 'variant_id' => 'var_fail' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertArrayHasKey( 'COMPLIANCE_FAIL', $result->errors );
	}

	public function test_warn_variant_returns_pending_approval(): void {
		$store = new Card1StateStore();
		$store->create_generate_request( array( 'request_id' => 'req_c_warn', 'post_id' => '1', 'status' => 'success' ) );
		$store->upsert_variant(
			'req_c_warn',
			array(
				'variant_id' => 'var_warn',
				'text' => 'x',
				'rationale' => 'x',
				'asset_hints' => array(),
				'platform' => 'linkedin',
				'compliance_status' => 'WARN',
				'compliance_reason' => 'review',
				'compliance' => array( 'status' => 'WARN', 'reasons' => array( 'review' ) ),
			)
		);

		$controller = new ScheduleController( $store );
		$result = $controller->enforce_compliance_gate( new WP_REST_Request( array( 'variant_id' => 'var_warn' ) ) );

		$this->assertIsArray( $result );
		$this->assertSame( 'pending_approval', $result['status'] );
	}

	public function test_ok_variant_allows_schedule(): void {
		$store = new Card1StateStore();
		$store->create_generate_request( array( 'request_id' => 'req_c_ok', 'post_id' => '1', 'status' => 'success' ) );
		$store->upsert_variant(
			'req_c_ok',
			array(
				'variant_id' => 'var_ok',
				'text' => 'x',
				'rationale' => 'x',
				'asset_hints' => array(),
				'platform' => 'linkedin',
				'compliance_status' => 'OK',
				'compliance_reason' => '',
				'compliance' => array( 'status' => 'OK', 'reasons' => array() ),
			)
		);

		$controller = new ScheduleController( $store );
		$result = $controller->enforce_compliance_gate( new WP_REST_Request( array( 'variant_id' => 'var_ok' ) ) );

		$this->assertIsArray( $result );
		$this->assertSame( 'ok', $result['status'] );
	}
}
