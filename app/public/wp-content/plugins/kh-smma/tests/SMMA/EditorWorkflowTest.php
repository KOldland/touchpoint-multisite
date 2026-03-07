<?php

use KH_SMMA\API\RestController;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Services\FeatureFlags;
use KH_SMMA\Services\SmmaGenerator;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/MockLLMClient.php';
require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/API/RestController.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/FeatureFlags.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/SmmaGenerator.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/Card1StateStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/ComplianceValidator.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/SchemaValidator.php';

final class EditorWorkflowTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		KH_SMMA\Tests\inject_mock_llm_client();
		putenv( 'KH_SMMA_TEST_MODE=ci' );
		$GLOBALS['kh_test_options'][ FeatureFlags::OPTION_KEY ] = array( 'smma' => true, 'smma_paid_adapters' => false );
		$GLOBALS['kh_test_options']['kh_smma_card1_state'] = array(
			'generate_requests' => array(),
			'variants' => array(),
			'variant_revisions' => array(),
			'schedules' => array(),
			'schedule_queue' => array(),
			'idempotency' => array(),
		);
	}

	public function test_variant_grid_fixture_matches_expected_shape(): void {
		$fixture = json_decode(
			(string) file_get_contents( dirname( __DIR__ ) . '/fixtures/smma/variant_grid_response.json' ),
			true
		);

		$this->assertIsArray( $fixture );
		$this->assertArrayHasKey( 'variants', $fixture );
		$this->assertIsArray( $fixture['variants'] );
		$this->assertNotEmpty( $fixture['variants'] );
		$this->assertArrayHasKey( 'variant_id', $fixture['variants'][0] );
		$this->assertArrayHasKey( 'linkedIn', $fixture['variants'][0] );
		$this->assertArrayHasKey( 'compliance_status', $fixture['variants'][0]['linkedIn'] );
	}

	public function test_generate_edit_schedule_api_flow_succeeds(): void {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_awareness_ok.json' );
		$controller = $this->build_controller();

		$generated = $controller->handle_generate(
			new WP_REST_Request(
				array(
					'post_id' => 900,
					'blocks_summary' => 'Workflow integration post content.',
					'num_variants' => 1,
					'geo_targets' => array( 'AU' ),
					'consent' => true,
				),
				array( 'X-Request-Id' => 'req-editor-flow-1' )
			)
		);

		$this->assertIsArray( $generated );
		$variant_id = $generated['variants'][0]['variant_id'];
		$this->assertNotEmpty( $variant_id );

		$edit = $controller->handle_variant_edit_v2(
			new WP_REST_Request(
				array(
					'variant_id' => $variant_id,
					'editor_user_id' => 42,
					'text' => 'Updated editor workflow copy with clear claims.',
					'asset_hints' => array(),
					'metadata' => array( 'edit_reason' => 'Improve clarity' ),
				),
				array( 'Idempotency-Key' => 'idem-editor-flow-edit' )
			)
		);
		$this->assertIsArray( $edit );
		$this->assertArrayHasKey( 'revision_id', $edit );

		$schedule = $controller->handle_schedule(
			new WP_REST_Request(
				array(
					'variant_id' => $variant_id,
					'sponsor_id' => '123',
					'schedule_time' => '2026-04-01T10:00:00Z',
					'boost_options' => array(
						'budget_cents' => 15000,
						'currency' => 'AUD',
						'channels' => array( 'linkedin' ),
						'prioritize' => 'reach',
					),
					'metadata' => array(),
				),
				array( 'Idempotency-Key' => 'idem-editor-flow-schedule' )
			)
		);

		$this->assertIsArray( $schedule );
		$this->assertArrayHasKey( 'schedule_id', $schedule );
		$this->assertArrayHasKey( 'status', $schedule );
	}

	private function build_controller(): RestController {
		$db = new wpdb();
		return new RestController( new FeatureFlags(), new SmmaGenerator(), new AuditLogger( $db ) );
	}
}
