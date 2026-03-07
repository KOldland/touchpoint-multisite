<?php

use KH_SMMA\API\RestController;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Services\FeatureFlags;
use KH_SMMA\Services\SmmaGenerator;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/MockLLMClient.php';
require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/API/RestController.php';
require_once dirname( __DIR__ ) . '/src/Services/FeatureFlags.php';
require_once dirname( __DIR__ ) . '/src/Services/SmmaGenerator.php';
require_once dirname( __DIR__ ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__ ) . '/src/Services/Card1StateStore.php';
require_once dirname( __DIR__ ) . '/src/Services/ComplianceValidator.php';
require_once dirname( __DIR__ ) . '/src/Services/SchemaValidator.php';

class Card1ApiTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		KH_SMMA\Tests\inject_mock_llm_client();
		$GLOBALS['kh_test_options'][ FeatureFlags::OPTION_KEY ] = array( 'smma' => true, 'smma_paid_adapters' => false );
		$GLOBALS['kh_test_options']['kh_smma_card1_state'] = array(
			'generate_requests' => array(),
			'variants' => array(),
			'variant_revisions' => array(),
			'schedules' => array(),
			'schedule_queue' => array(),
			'idempotency' => array(),
		);
		$GLOBALS['kh_test_db_inserts'] = array();
		$GLOBALS['kh_test_db_replaces'] = array();
		putenv( 'KH_SMMA_TEST_MODE=ci' );
	}

	public function test_generate_rejects_non_json_llm_output(): void {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=telemetry_smoke_expected.json' );

		$controller = $this->build_controller();
		$request = new WP_REST_Request(
			array(
				'post_id' => 100,
				'blocks_summary' => 'Summary',
				'num_variants' => 1,
				'geo_targets' => array( 'AU' ),
				'consent' => true,
			),
			array( 'X-Request-Id' => 'req-test-nonjson' )
		);

		$result = $controller->handle_generate( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertArrayHasKey( 'SMMA_ERR_INVALID_LLM', $result->errors );
	}

	public function test_generate_returns_card1_contract_shape(): void {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_awareness_ok.json' );

		$controller = $this->build_controller();
		$request = new WP_REST_Request(
			array(
				'post_id' => 101,
				'blocks_summary' => 'Summary',
				'num_variants' => 1,
				'geo_targets' => array( 'US' ),
				'consent' => true,
			),
			array( 'X-Request-Id' => 'req-test-ok' )
		);

		$result = $controller->handle_generate( $request );
		$this->assertIsArray( $result );
		$this->assertSame( 'req-test-ok', $result['request_id'] );
		$this->assertArrayHasKey( 'variants', $result );
		$this->assertNotEmpty( $result['variants'] );
		$this->assertArrayHasKey( 'linkedIn', $result['variants'][0] );
		$this->assertArrayHasKey( 'google', $result['variants'][0] );
		$this->assertArrayHasKey( 'provenance', $result );
	}

	public function test_variant_edit_fail_blocks_with_409_error(): void {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_awareness_ok.json' );
		$controller = $this->build_controller();

		$generate = $controller->handle_generate(
			new WP_REST_Request(
				array(
					'post_id' => 102,
					'blocks_summary' => 'Summary',
					'num_variants' => 1,
					'geo_targets' => array( 'AU' ),
					'consent' => true,
				),
				array( 'X-Request-Id' => 'req-edit-seed' )
			)
		);
		$variant_id = $generate['variants'][0]['variant_id'];

		$edit = $controller->handle_variant_edit_v2(
			new WP_REST_Request(
				array(
					'variant_id' => $variant_id,
					'editor_user_id' => 88,
					'text' => 'This is risk-free and guaranteed results.',
					'asset_hints' => array(),
					'metadata' => array(),
				),
				array( 'Idempotency-Key' => 'idem-edit-1' )
			)
		);

		$this->assertInstanceOf( WP_Error::class, $edit );
		$this->assertArrayHasKey( 'SMMA_ERR_COMPLIANCE_FAIL', $edit->errors );
		$this->assertTrue( $this->has_audit_action( 'smma_variant_edit' ) );
		$this->assertTrue( $this->has_audit_action( 'smma_compliance_check' ) );
	}

	public function test_schedule_create_is_idempotent_by_key(): void {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_awareness_ok.json' );
		$controller = $this->build_controller();

		$generate = $controller->handle_generate(
			new WP_REST_Request(
				array(
					'post_id' => 103,
					'blocks_summary' => 'Summary',
					'num_variants' => 1,
					'geo_targets' => array( 'US' ),
					'consent' => true,
				),
				array( 'X-Request-Id' => 'req-schedule-seed' )
			)
		);

		$variant_id = $generate['variants'][0]['variant_id'];
		$schedule_payload = array(
			'variant_id' => $variant_id,
			'sponsor_id' => '123',
			'schedule_time' => '2026-04-01T10:00:00Z',
			'boost_options' => array(
				'budget_cents' => 10000,
				'currency' => 'AUD',
				'channels' => array( 'linkedin' ),
				'prioritize' => 'reach',
			),
			'approval_required' => false,
			'metadata' => array(),
		);

		$first = $controller->handle_schedule(
			new WP_REST_Request( $schedule_payload, array( 'Idempotency-Key' => 'idem-sch-1' ) )
		);
		$second = $controller->handle_schedule(
			new WP_REST_Request( $schedule_payload, array( 'Idempotency-Key' => 'idem-sch-1' ) )
		);

		$this->assertIsArray( $first );
		$this->assertIsArray( $second );
		$this->assertSame( $first['schedule_id'], $second['schedule_id'] );
		$this->assertFalse( $first['idempotent'] );
		$this->assertTrue( $second['idempotent'] );
		$this->assertTrue( $this->has_audit_action( 'smma_schedule_create' ) );
	}

	public function test_schedule_warn_creates_pending_approval_schedule(): void {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_awareness_ok.json' );
		$controller = $this->build_controller();
		$generate = $controller->handle_generate(
			new WP_REST_Request(
				array(
					'post_id' => 104,
					'blocks_summary' => 'Summary',
					'num_variants' => 1,
					'geo_targets' => array( 'US' ),
					'consent' => true,
				),
				array( 'X-Request-Id' => 'req-schedule-warn-seed' )
			)
		);
		$variant_id = $generate['variants'][0]['variant_id'];

		$state = $GLOBALS['kh_test_options']['kh_smma_card1_state'];
		$state['variants'][ $variant_id ]['linkedIn']['compliance_status'] = 'WARN';
		$state['variants'][ $variant_id ]['linkedIn']['compliance_reason'] = 'Needs sponsor review.';
		$state['variants'][ $variant_id ]['linkedIn']['matched_rules'] = array();
		$GLOBALS['kh_test_options']['kh_smma_card1_state'] = $state;

		$response = $controller->handle_schedule(
			new WP_REST_Request(
				array(
					'variant_id' => $variant_id,
					'sponsor_id' => '123',
					'schedule_time' => '2026-04-01T10:00:00Z',
					'boost_options' => array(
						'budget_cents' => 12000,
						'currency' => 'AUD',
						'channels' => array( 'linkedin' ),
					),
				),
				array( 'Idempotency-Key' => 'idem-sch-warn-1', 'X-Trace-Id' => 'trace-warn-1' )
			)
		);

		$this->assertIsArray( $response );
		$this->assertSame( 'pending_approval', $response['status'] );
		$this->assertTrue( $response['approval_required'] );
		$this->assertSame( 'pending', $response['approval_status'] );
		$this->assertSame( 'WARN', $response['compliance_status'] );
		$this->assertArrayHasKey( $response['schedule_id'], $GLOBALS['kh_test_options']['kh_smma_card1_state']['schedules'] );
	}

	public function test_schedule_fail_is_blocked_and_not_created(): void {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_awareness_ok.json' );
		$controller = $this->build_controller();
		$generate = $controller->handle_generate(
			new WP_REST_Request(
				array(
					'post_id' => 105,
					'blocks_summary' => 'Summary',
					'num_variants' => 1,
					'geo_targets' => array( 'US' ),
					'consent' => true,
				),
				array( 'X-Request-Id' => 'req-schedule-fail-seed' )
			)
		);
		$variant_id = $generate['variants'][0]['variant_id'];

		$state = $GLOBALS['kh_test_options']['kh_smma_card1_state'];
		$state['variants'][ $variant_id ]['linkedIn']['compliance_status'] = 'FAIL';
		$state['variants'][ $variant_id ]['linkedIn']['compliance_reason'] = 'Contains banned phrase.';
		$state['variants'][ $variant_id ]['linkedIn']['matched_rules'] = array( 'banned_phrase_x' );
		$GLOBALS['kh_test_options']['kh_smma_card1_state'] = $state;

		$before = count( $GLOBALS['kh_test_options']['kh_smma_card1_state']['schedules'] ?? array() );
		$response = $controller->handle_schedule(
			new WP_REST_Request(
				array(
					'variant_id' => $variant_id,
					'sponsor_id' => '123',
					'schedule_time' => '2026-04-01T10:00:00Z',
					'boost_options' => array(
						'budget_cents' => 12000,
						'currency' => 'AUD',
						'channels' => array( 'linkedin' ),
					),
				),
				array( 'Idempotency-Key' => 'idem-sch-fail-1', 'X-Trace-Id' => 'trace-fail-1' )
			)
		);
		$after = count( $GLOBALS['kh_test_options']['kh_smma_card1_state']['schedules'] ?? array() );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertArrayHasKey( 'COMPLIANCE_FAIL', $response->errors );
		$this->assertSame( $before, $after );
	}

	private function build_controller(): RestController {
		$db = new wpdb();

		return new RestController(
			new FeatureFlags(),
			new SmmaGenerator(),
			new AuditLogger( $db )
		);
	}

	private function has_audit_action( string $action ): bool {
		foreach ( $GLOBALS['kh_test_db_inserts'] ?? array() as $insert ) {
			if ( strpos( (string) ( $insert['table'] ?? '' ), 'kh_smma_audit_log' ) !== false
				&& ( $insert['data']['action'] ?? '' ) === $action ) {
				return true;
			}
		}

		return false;
	}
}
