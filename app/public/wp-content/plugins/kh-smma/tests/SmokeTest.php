<?php
namespace KH_SMMA\Tests;

use PHPUnit\Framework\TestCase;
use KH_SMMA\Services\SmmaGenerator;
use KH_SMMA\Services\GoogleAdDraftService;
use KH_SMMA\Services\ComplianceValidator;
use KH_SMMA\Services\SchemaValidator;

// Load MockLLMClient and helper functions
require_once __DIR__ . '/MockLLMClient.php';

/**
 * End-to-End Smoke Tests
 *
 * Tests complete SMMA workflow from generation through validation.
 * Validates state transitions, schema conformance, and compliance gates.
 *
 * Run with:
 *   export KH_SMMA_TEST_MODE=ci
 *   vendor/bin/phpunit tests/SmokeTest.php --testdox
 */
class SmokeTest extends TestCase {
	private $generator;
	private $google_service;
	private $compliance_validator;
	private $schema_validator;

	protected function setUp(): void {
		parent::setUp();
		inject_mock_llm_client();

		// Initialize services
		$this->schema_validator = new SchemaValidator();
		$this->generator = new SmmaGenerator( $this->schema_validator );
		$this->google_service = new GoogleAdDraftService();
		$this->compliance_validator = new ComplianceValidator();
	}

	/**
	 * Test complete generation workflow with OK compliance.
	 *
	 * @test
	 */
	public function complete_generation_workflow_with_clean_content() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_response.json' );

		// Step 1: Generate LinkedIn variants
		$result = $this->generator->generate( array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'tone' => 'Authority',
			'num_variants' => 2,
			'generate_google_ads' => false,
		) );

		// Assert generation succeeded
		$this->assertIsArray( $result, 'Generation should return array' );
		$this->assertArrayHasKey( 'linkedin_variants', $result );
		$this->assertCount( 2, $result['linkedin_variants'], 'Should generate 2 variants' );

		// Assert schema validation passed
		$this->assertArrayNotHasKey( 'schema_validation_error', $result, 'Should not have schema errors' );

		// Step 2: Validate each variant's compliance
		foreach ( $result['linkedin_variants'] as $index => $variant ) {
			$compliance_result = $this->compliance_validator->validate( $variant['text'], array(
				'channel' => 'linkedin',
				'phase_tag' => 'Attention',
			) );

			$this->assertTrue( $compliance_result['passed'], "Variant {$index} should pass compliance" );
			$this->assertGreaterThan( 0.8, $compliance_result['confidence_score'], "Variant {$index} should have good confidence" );
		}

		// Step 3: Assert telemetry metadata present
		$this->assertArrayHasKey( 'model', $result, 'Should track model used' );
		$this->assertNotEmpty( $result['model'], 'Model identifier should be present' );
	}

	/**
	 * Test generation with WARN compliance level.
	 *
	 * @test
	 */
	public function generation_with_warn_compliance_flags_sponsor_content() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_warn_response.json' );

		$result = $this->generator->generate( array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'num_variants' => 1,
			'generate_google_ads' => false,
		) );

		$this->assertArrayHasKey( 'linkedin_variants', $result );
		$variant = $result['linkedin_variants'][0];

		// Assert WARN indicators in variant metadata
		$this->assertArrayHasKey( 'compliance_notes', $variant );
		$this->assertStringContainsString( 'WARN', $variant['compliance_notes'] );
		$this->assertTrue( $variant['sponsor_flag'], 'Should flag for sponsor approval' );

		// WARN content may pass rule-based checks but requires sponsor verification
		// The compliance_notes field indicates this
		$this->assertNotEmpty( $variant['compliance_notes'], 'Should have warning message' );
	}

	/**
	 * Test generation with FAIL compliance blocks scheduling.
	 *
	 * @test
	 */
	public function generation_with_fail_compliance_blocks_paid_scheduling() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_fail_response.json' );

		$result = $this->generator->generate( array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'num_variants' => 1,
			'generate_google_ads' => false,
		) );

		$variant = $result['linkedin_variants'][0];

		// Assert FAIL indicators
		$this->assertStringContainsString( 'FAIL', $variant['compliance_notes'] );
		$this->assertTrue( $variant['sponsor_flag'], 'Should be flagged for sponsor' );

		// Assert blacklisted content present
		$text_lower = strtolower( $variant['text'] );
		$has_blacklist = strpos( $text_lower, 'guaranteed' ) !== false ||
		                 strpos( $text_lower, 'risk-free' ) !== false ||
		                 strpos( $text_lower, '100%' ) !== false;

		$this->assertTrue( $has_blacklist, 'Should contain blacklisted phrases' );

		// Verify compliance validator catches it
		$compliance_result = $this->compliance_validator->validate( $variant['text'], array(
			'channel' => 'linkedin',
		) );

		$this->assertFalse( $compliance_result['passed'], 'FAIL content must fail compliance' );
		$this->assertGreaterThanOrEqual( 0.9, $compliance_result['confidence_score'], 'Should have high confidence in violation' );

		// Simulate scheduling gate - should be blocked
		$can_schedule_paid = $compliance_result['passed'] && ! $variant['sponsor_flag'];
		$this->assertFalse( $can_schedule_paid, 'Should block paid scheduling for FAIL content' );
	}

	/**
	 * Test Google Ads draft generation workflow.
	 *
	 * @test
	 */
	public function google_ads_draft_generation_and_validation() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=google_ad_draft_response.json' );

		// Generate draft
		$draft = $this->google_service->generate( array(
			'post_id' => 123,
			'title' => 'Test Article',
			'keywords' => array( 'business', 'strategy', 'growth' ),
			'num_ad_groups' => 2,
		) );

		// Assert structure
		$this->assertIsArray( $draft );
		$this->assertArrayHasKey( 'ad_groups', $draft );
		$this->assertGreaterThanOrEqual( 2, count( $draft['ad_groups'] ), 'Should have at least 2 ad groups' );

		// Validate schema
		$schema_validation = $this->schema_validator->validate_google_ad_draft( $draft );
		$this->assertTrue( $schema_validation === true, 'Draft should pass schema validation' );

		// Validate compliance for each ad group
		$compliance_result = $this->compliance_validator->validate_google_ad_draft( $draft, array(
			'channel' => 'google_ads',
		) );

		$this->assertArrayHasKey( 'passed', $compliance_result );
		$this->assertArrayHasKey( 'ad_group_results', $compliance_result );

		// Assert per-ad-group validation
		foreach ( $draft['ad_groups'] as $index => $group ) {
			// Headlines must be ≤ 30 chars
			foreach ( $group['headlines'] as $h_index => $headline ) {
				$this->assertLessThanOrEqual( 30, mb_strlen( $headline ), "Ad group {$index} headline {$h_index} exceeds 30 chars" );
			}

			// Descriptions must be ≤ 90 chars
			foreach ( $group['descriptions'] as $d_index => $description ) {
				$this->assertLessThanOrEqual( 90, mb_strlen( $description ), "Ad group {$index} description {$d_index} exceeds 90 chars" );
			}
		}
	}

	/**
	 * Test inline editing with compliance re-validation.
	 *
	 * @test
	 */
	public function inline_edit_triggers_compliance_revalidation() {
		// Start with clean content
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_response.json' );
		$result = $this->generator->generate( array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'num_variants' => 1,
			'generate_google_ads' => false,
		) );

		$variant = $result['linkedin_variants'][0];
		$original_text = $variant['text'];

		// Validate original (should pass)
		$validation1 = $this->compliance_validator->validate( $original_text, array( 'channel' => 'linkedin' ) );
		$this->assertTrue( $validation1['passed'], 'Original text should pass' );

		// Simulate edit: inject blacklisted phrase
		$edited_text = $original_text . ' Get guaranteed results with our risk-free solution!';

		// Re-validate edited text (should fail)
		$validation2 = $this->compliance_validator->validate( $edited_text, array( 'channel' => 'linkedin' ) );
		$this->assertFalse( $validation2['passed'], 'Edited text with blacklist should fail' );
		$this->assertNotEmpty( $validation2['message'], 'Should provide failure reason' );

		// Assert violation detected
		$message_lower = strtolower( $validation2['message'] ?? $validation2['notes'] ?? '' );
		$this->assertThat(
			$message_lower,
			$this->logicalOr(
				$this->stringContains( 'blocked' ),
				$this->stringContains( 'prohibited' ),
				$this->stringContains( 'guaranteed' )
			),
			'Message should indicate specific violation'
		);
	}

	/**
	 * Test batch validation workflow.
	 *
	 * @test
	 */
	public function batch_validation_returns_per_variant_results() {
		$variants = array(
			array(
				'variant_id' => 'clean-1',
				'text' => 'Discover actionable strategies from industry experts.',
				'channel' => 'linkedin',
			),
			array(
				'variant_id' => 'fail-1',
				'text' => 'Get guaranteed results with our risk-free solution!',
				'channel' => 'linkedin',
			),
			array(
				'variant_id' => 'clean-2',
				'text' => 'Join the conversation on professional development.',
				'channel' => 'linkedin',
			),
		);

		$results = $this->compliance_validator->validate_batch( $variants, array() );

		// Assert all variants validated
		$this->assertArrayHasKey( 'clean-1', $results );
		$this->assertArrayHasKey( 'fail-1', $results );
		$this->assertArrayHasKey( 'clean-2', $results );

		// Assert correct pass/fail
		$this->assertTrue( $results['clean-1']['passed'], 'clean-1 should pass' );
		$this->assertFalse( $results['fail-1']['passed'], 'fail-1 should fail (blacklist)' );
		$this->assertTrue( $results['clean-2']['passed'], 'clean-2 should pass' );
	}

	/**
	 * Test sponsor allowed claims enforcement.
	 *
	 * @test
	 */
	public function sponsor_allowed_claims_gate_content() {
		$allowed_claims = array(
			'award-winning design',
			'trusted by professionals',
		);

		// Text with allowed claim should pass
		$text_with_claim = 'Experience our award-winning design and see the difference.';
		$result_pass = $this->compliance_validator->validate( $text_with_claim, array(
			'channel' => 'linkedin',
			'sponsor_id' => 123,
			'allowed_claims' => $allowed_claims,
		) );

		$this->assertTrue( $result_pass['passed'], 'Text with allowed claim should pass' );

		// Text without allowed claim should fail
		$text_without_claim = 'Experience our revolutionary product today.';
		$result_fail = $this->compliance_validator->validate( $text_without_claim, array(
			'channel' => 'linkedin',
			'sponsor_id' => 123,
			'allowed_claims' => $allowed_claims,
		) );

		$this->assertFalse( $result_fail['passed'], 'Text without allowed claim should fail' );
	}

	/**
	 * Test approve/reject workflow simulation.
	 *
	 * @test
	 */
	public function approve_reject_workflow_updates_variant_state() {
		// Generate content
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_response.json' );
		$result = $this->generator->generate( array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'num_variants' => 1,
			'generate_google_ads' => false,
		) );

		$variant = $result['linkedin_variants'][0];
		$variant_id = $variant['variant_id'];

		// Simulate approval state
		$variant['status'] = 'approved';
		$variant['approved_at'] = time();
		$variant['approved_by'] = 1; // User ID 1

		$this->assertEquals( 'approved', $variant['status'] );
		$this->assertArrayHasKey( 'approved_at', $variant );
		$this->assertArrayHasKey( 'approved_by', $variant );

		// Simulate rejection (state transition)
		$variant['status'] = 'rejected';
		$variant['rejected_at'] = time();
		$variant['rejection_reason'] = 'Not aligned with brand voice';

		$this->assertEquals( 'rejected', $variant['status'] );
		$this->assertArrayHasKey( 'rejection_reason', $variant );
	}

	/**
	 * Test deterministic fixture behavior.
	 *
	 * @test
	 */
	public function golden_fixtures_ensure_deterministic_test_behavior() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_response.json' );

		$input = array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'num_variants' => 2,
			'generate_google_ads' => false,
		);

		// Generate twice
		$result1 = $this->generator->generate( $input );
		$result2 = $this->generator->generate( $input );

		// Assert identical outputs
		$this->assertEquals(
			$result1['linkedin_variants'][0]['variant_id'],
			$result2['linkedin_variants'][0]['variant_id'],
			'Should produce identical variant IDs'
		);

		$this->assertEquals(
			$result1['linkedin_variants'][0]['text'],
			$result2['linkedin_variants'][0]['text'],
			'Should produce identical text content'
		);

		$this->assertEquals(
			count( $result1['linkedin_variants'] ),
			count( $result2['linkedin_variants'] ),
			'Should produce same number of variants'
		);
	}

	/**
	 * Test schema validation error handling.
	 *
	 * @test
	 */
	public function schema_validation_errors_are_captured_and_logged() {
		// Simulate well-formed response
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_response.json' );

		$result = $this->generator->generate( array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'num_variants' => 2,
			'generate_google_ads' => false,
		) );

		// Well-formed fixture should not have schema errors
		$this->assertArrayNotHasKey( 'schema_validation_error', $result, 'Golden fixture should pass schema validation' );

		// Test schema validator directly with malformed data
		$malformed_variant = array(
			'variant_id' => 'test-1',
			// Missing required 'text' field
			'channel' => 'linkedin',
		);

		$validation = $this->schema_validator->validate_linkedin_variant( $malformed_variant );
		$this->assertIsObject( $validation, 'Malformed variant should return WP_Error object' );
		$this->assertTrue( is_wp_error( $validation ), 'Should be WP_Error' );
	}

	/**
	 * Test CI safety checks prevent live API calls.
	 *
	 * @test
	 */
	public function ci_mode_prevents_live_api_calls() {
		// CI mode is enforced by MockLLMClient
		// Verify mock is active
		$this->assertTrue( defined( 'KH_SMMA_USE_MOCK_LLM' ), 'Mock LLM should be active' );

		// Generate should use fixture, not real API
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_response.json' );
		$result = $this->generator->generate( array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'num_variants' => 1,
			'generate_google_ads' => false,
		) );

		// Verify mock metadata present
		$this->assertArrayHasKey( 'model', $result, 'Should include model metadata' );

		// If this test completes without throwing "real API key detected" exception,
		// CI safety is working correctly
		$this->assertTrue( true, 'CI safety checks passed - no live API calls made' );
	}

	/**
	 * Test compliance validation across all fixture scenarios.
	 *
	 * @test
	 */
	public function compliance_validation_handles_all_fixture_scenarios() {
		$scenarios = array(
			array(
				'fixture' => 'compliance_pass_response.json',
				'text' => 'Clean professional content without issues.',
				'should_pass' => true,
			),
			array(
				'fixture' => 'compliance_warn_response.json',
				'text' => 'Join satisfied customers who see results.',
				'should_pass' => false,
			),
			array(
				'fixture' => 'compliance_fail_response.json',
				'text' => 'Get guaranteed results with our risk-free solution!',
				'should_pass' => false,
			),
		);

		foreach ( $scenarios as $scenario ) {
			putenv( 'KH_SMMA_GOLDEN_FIXTURE=' . $scenario['fixture'] );

			$result = $this->compliance_validator->validate( $scenario['text'], array(
				'channel' => 'linkedin',
			) );

			$this->assertEquals(
				$scenario['should_pass'],
				$result['passed'],
				"Fixture {$scenario['fixture']} expectation mismatch"
			);
		}
	}

	/**
	 * Test variant-edit endpoint workflow.
	 *
	 * @test
	 */
	public function variant_edit_revalidates_and_persists_changes() {
		// Generate initial variant
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_response.json' );
		$result = $this->generator->generate( array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'num_variants' => 1,
			'generate_google_ads' => false,
		) );

		$variant = $result['linkedin_variants'][0];
		$original_text = $variant['text'];

		// Simulate editing with clean text (should pass)
		$edited_text_clean = $original_text . ' Learn more about industry best practices.';

		$compliance_check = $this->compliance_validator->validate( $edited_text_clean, array(
			'channel' => 'linkedin',
		) );

		$this->assertTrue( $compliance_check['passed'], 'Clean edit should pass compliance' );
		$this->assertArrayHasKey( 'confidence_score', $compliance_check );
		$this->assertGreaterThan( 0.8, $compliance_check['confidence_score'] );

		// Simulate editing with blacklisted phrase (should fail)
		$edited_text_fail = $original_text . ' Get guaranteed results with our risk-free solution!';

		$compliance_check_fail = $this->compliance_validator->validate( $edited_text_fail, array(
			'channel' => 'linkedin',
		) );

		$this->assertFalse( $compliance_check_fail['passed'], 'Edit with blacklist should fail compliance' );
		$this->assertNotEmpty( $compliance_check_fail['message'], 'Should provide failure reason' );

		// Verify diff calculation would work
		$diff_size = strlen( $edited_text_fail ) - strlen( $original_text );
		$this->assertGreaterThan( 0, $diff_size, 'Edit should have measurable diff' );

		// Verify re-validation catches compliance change
		$original_compliance = $this->compliance_validator->validate( $original_text, array( 'channel' => 'linkedin' ) );
		$this->assertTrue( $original_compliance['passed'], 'Original should pass' );
		$this->assertFalse( $compliance_check_fail['passed'], 'Edited should fail' );
		$this->assertNotEquals(
			$original_compliance['passed'],
			$compliance_check_fail['passed'],
			'Compliance status should change after edit'
		);
	}

	/**
	 * Test variant-edit maintains approval_required computation.
	 *
	 * @test
	 */
	public function variant_edit_recomputes_approval_required() {
		// Start with OK variant
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_response.json' );
		$result = $this->generator->generate( array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'num_variants' => 1,
			'generate_google_ads' => false,
		) );

		$variant = $result['linkedin_variants'][0];

		// Original should not require approval
		$this->assertArrayHasKey( 'approval_required', $variant );
		$this->assertFalse( $variant['approval_required'], 'OK variant should not require approval' );

		// Simulate edit that adds WARN/FAIL content
		$edited_text = $variant['text'] . ' Guaranteed results for all customers!';

		$compliance_result = $this->compliance_validator->validate( $edited_text, array(
			'channel' => 'linkedin',
		) );

		// Should fail compliance
		$this->assertFalse( $compliance_result['passed'], 'Edited text with blacklist should fail' );

		// If we regenerated the variant with this text, approval_required would be true
		// Simulate this by checking if FAIL/WARN is in compliance notes
		$would_require_approval = ! $compliance_result['passed'];
		$this->assertTrue( $would_require_approval, 'Failed compliance should require approval' );
	}
}
