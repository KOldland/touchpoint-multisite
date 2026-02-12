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
 * Golden AI Tests
 *
 * Validates LLM generation outputs against golden fixtures.
 * Ensures schema conformance, compliance levels, and data integrity.
 *
 * Run with:
 *   export KH_SMMA_TEST_MODE=ci
 *   vendor/bin/phpunit tests/GoldenAITest.php --testdox
 */
class GoldenAITest extends TestCase {
	private $schema_validator;

	protected function setUp(): void {
		parent::setUp();
		inject_mock_llm_client();
		$this->schema_validator = new SchemaValidator();
	}

	/**
	 * Test LinkedIn generation with OK compliance.
	 *
	 * @test
	 */
	public function linkedin_generation_ok_passes_schema_validation() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_response.json' );

		$generator = new SmmaGenerator();
		$result = $generator->generate( array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'tone' => 'Authority',
			'num_variants' => 2,
			'generate_google_ads' => false,
		) );

		// Assert basic structure
		$this->assertArrayHasKey( 'linkedin_variants', $result );
		$this->assertArrayHasKey( 'variants', $result );
		$this->assertIsArray( $result['linkedin_variants'] );
		$this->assertCount( 2, $result['linkedin_variants'] );

		// Validate schema for each variant
		foreach ( $result['linkedin_variants'] as $index => $variant ) {
			$validation = $this->schema_validator->validate_linkedin_variant( $variant );
			$this->assertTrue(
				$validation === true,
				"Variant [{$index}] failed schema validation: " . ( is_wp_error( $validation ) ? $validation->get_error_message() : '' )
			);

			// Assert required fields
			$this->assertArrayHasKey( 'variant_id', $variant, "Variant [{$index}] missing variant_id" );
			$this->assertArrayHasKey( 'text', $variant, "Variant [{$index}] missing text" );
			$this->assertArrayHasKey( 'channel', $variant, "Variant [{$index}] missing channel" );

			// Assert channel is linkedin
			$this->assertEquals( 'linkedin', $variant['channel'], "Variant [{$index}] channel must be 'linkedin'" );

			// Assert text is non-empty
			$this->assertNotEmpty( $variant['text'], "Variant [{$index}] text cannot be empty" );

			// Assert no compliance issues for OK fixture
			$compliance_notes = $variant['compliance_notes'] ?? '';
			$this->assertNotContainsCaseInsensitive( 'FAIL', $compliance_notes, "Variant [{$index}] should not have FAIL compliance" );
			$this->assertNotContainsCaseInsensitive( 'WARN', $compliance_notes, "Variant [{$index}] should not have WARN compliance" );

			// Assert approval_required field is present and correct
			$this->assertArrayHasKey( 'approval_required', $variant, "Variant [{$index}] missing approval_required field" );
			$this->assertIsBool( $variant['approval_required'], "Variant [{$index}] approval_required must be boolean" );
			$this->assertFalse( $variant['approval_required'], "Variant [{$index}] with OK compliance should not require approval" );
		}
	}

	/**
	 * Test LinkedIn generation with WARN compliance.
	 *
	 * @test
	 */
	public function linkedin_generation_warn_contains_compliance_warnings() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_warn_response.json' );

		$generator = new SmmaGenerator();
		$result = $generator->generate( array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'tone' => 'Authority',
			'num_variants' => 1,
			'generate_google_ads' => false,
		) );

		$this->assertArrayHasKey( 'linkedin_variants', $result );
		$this->assertNotEmpty( $result['linkedin_variants'] );

		$variant = $result['linkedin_variants'][0];

		// Validate schema
		$validation = $this->schema_validator->validate_linkedin_variant( $variant );
		$this->assertTrue( $validation === true, 'WARN variant must still pass schema validation' );

		// Assert WARN compliance notes present
		$this->assertArrayHasKey( 'compliance_notes', $variant );
		$this->assertContainsCaseInsensitive( 'WARN', $variant['compliance_notes'], 'Must contain WARN indicator' );

		// Assert sponsor flag is set for sponsored content
		$this->assertTrue( $variant['sponsor_flag'], 'Sponsor flag should be true for WARN variants' );

		// Assert text contains typical WARN patterns
		$text_lower = strtolower( $variant['text'] );
		$has_warn_pattern = strpos( $text_lower, 'satisfied customers' ) !== false ||
		                     strpos( $text_lower, 'results that exceed' ) !== false ||
		                     strpos( $text_lower, 'trust our proven' ) !== false;

		$this->assertTrue( $has_warn_pattern, 'WARN variant should contain claims needing verification' );

		// Assert approval_required is true for WARN compliance
		$this->assertArrayHasKey( 'approval_required', $variant, 'WARN variant missing approval_required field' );
		$this->assertIsBool( $variant['approval_required'], 'WARN variant approval_required must be boolean' );
		$this->assertTrue( $variant['approval_required'], 'WARN variant should require approval' );
	}

	/**
	 * Test LinkedIn generation with FAIL compliance.
	 *
	 * @test
	 */
	public function linkedin_generation_fail_contains_critical_violations() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_fail_response.json' );

		$generator = new SmmaGenerator();
		$result = $generator->generate( array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'tone' => 'Authority',
			'num_variants' => 1,
			'generate_google_ads' => false,
		) );

		$this->assertArrayHasKey( 'linkedin_variants', $result );
		$this->assertNotEmpty( $result['linkedin_variants'] );

		$variant = $result['linkedin_variants'][0];

		// Validate schema (structure should still be valid)
		$validation = $this->schema_validator->validate_linkedin_variant( $variant );
		$this->assertTrue( $validation === true, 'FAIL variant structure must be valid' );

		// Assert FAIL compliance notes
		$this->assertArrayHasKey( 'compliance_notes', $variant );
		$this->assertContainsCaseInsensitive( 'FAIL', $variant['compliance_notes'], 'Must contain FAIL indicator' );

		// Assert text contains blacklisted phrases
		$text_lower = strtolower( $variant['text'] );
		$has_blacklist = strpos( $text_lower, 'guaranteed' ) !== false ||
		                  strpos( $text_lower, 'risk-free' ) !== false ||
		                  strpos( $text_lower, '100%' ) !== false;

		$this->assertTrue( $has_blacklist, 'FAIL variant should contain blacklisted phrases' );

		// Assert sponsor flag is set
		$this->assertTrue( $variant['sponsor_flag'], 'Sponsor flag should be true for FAIL variants' );

		// Assert approval_required is true for FAIL compliance
		$this->assertArrayHasKey( 'approval_required', $variant, 'FAIL variant missing approval_required field' );
		$this->assertIsBool( $variant['approval_required'], 'FAIL variant approval_required must be boolean' );
		$this->assertTrue( $variant['approval_required'], 'FAIL variant should require approval' );
	}

	/**
	 * Test Google Ads draft generation.
	 *
	 * @test
	 */
	public function google_ads_draft_passes_schema_validation() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=google_ad_draft_response.json' );

		$google_service = new GoogleAdDraftService();
		$draft = $google_service->generate( array(
			'post_id' => 123,
			'title' => 'Test Article',
			'keywords' => array( 'business', 'strategy', 'growth' ),
			'num_ad_groups' => 2,
		) );

		// Assert basic structure
		$this->assertIsArray( $draft );
		$this->assertArrayHasKey( 'ad_groups', $draft );
		$this->assertIsArray( $draft['ad_groups'] );
		$this->assertNotEmpty( $draft['ad_groups'], 'Draft must contain at least one ad group' );

		// Validate schema
		$validation = $this->schema_validator->validate_google_ad_draft( $draft );
		$this->assertTrue(
			$validation === true,
			'Google Ads draft failed schema validation: ' . ( is_wp_error( $validation ) ? $validation->get_error_message() : '' )
		);

		// Validate each ad group
		foreach ( $draft['ad_groups'] as $index => $group ) {
			$group_label = "Ad group [{$index}]";

			// Required fields
			$this->assertArrayHasKey( 'keyword_cluster', $group, "{$group_label} missing keyword_cluster" );
			$this->assertArrayHasKey( 'headlines', $group, "{$group_label} missing headlines" );
			$this->assertArrayHasKey( 'descriptions', $group, "{$group_label} missing descriptions" );
			$this->assertArrayHasKey( 'final_url', $group, "{$group_label} missing final_url" );

			// Headlines validation
			$this->assertIsArray( $group['headlines'], "{$group_label} headlines must be array" );
			$this->assertGreaterThanOrEqual( 3, count( $group['headlines'] ), "{$group_label} must have at least 3 headlines" );

			foreach ( $group['headlines'] as $h_index => $headline ) {
				$this->assertIsString( $headline, "{$group_label} headline[{$h_index}] must be string" );
				$this->assertLessThanOrEqual( 30, mb_strlen( $headline ), "{$group_label} headline[{$h_index}] exceeds 30 chars" );
			}

			// Descriptions validation
			$this->assertIsArray( $group['descriptions'], "{$group_label} descriptions must be array" );
			$this->assertGreaterThanOrEqual( 2, count( $group['descriptions'] ), "{$group_label} must have at least 2 descriptions" );

			foreach ( $group['descriptions'] as $d_index => $description ) {
				$this->assertIsString( $description, "{$group_label} description[{$d_index}] must be string" );
				$this->assertLessThanOrEqual( 90, mb_strlen( $description ), "{$group_label} description[{$d_index}] exceeds 90 chars" );
			}

			// URL validation
			$this->assertNotEmpty( $group['final_url'], "{$group_label} final_url cannot be empty" );
			$this->assertStringStartsWith( 'http', $group['final_url'], "{$group_label} final_url must be valid URL" );

			// UTM tracking
			if ( isset( $group['final_url_with_utm'] ) ) {
				$this->assertStringContainsString( 'utm_source', $group['final_url_with_utm'], "{$group_label} final_url_with_utm missing UTM params" );
			}

			// CPC suggestion
			if ( isset( $group['cpc_suggestion'] ) ) {
				$this->assertIsNumeric( $group['cpc_suggestion'], "{$group_label} cpc_suggestion must be numeric" );
				$this->assertGreaterThan( 0, $group['cpc_suggestion'], "{$group_label} cpc_suggestion must be positive" );
			}
		}
	}

	/**
	 * Test compliance check with PASS result.
	 *
	 * @test
	 */
	public function compliance_pass_returns_clean_validation() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=compliance_pass_response.json' );

		$validator = new ComplianceValidator();
		$result = $validator->validate( 'Clean professional content without issues.', array(
			'channel' => 'linkedin',
			'phase_tag' => 'Attention',
		) );

		// Assert passed
		$this->assertArrayHasKey( 'passed', $result );
		$this->assertTrue( $result['passed'], 'Compliance check should pass for clean content' );

		// Assert confidence
		$this->assertArrayHasKey( 'confidence_score', $result );
		$this->assertGreaterThan( 0.9, $result['confidence_score'], 'PASS should have high confidence' );

		// Assert no flags
		$flags = $result['flags'] ?? array();
		$this->assertEmpty( $flags, 'PASS should have no compliance flags' );

		// Assert message is empty
		$message = $result['message'] ?? '';
		$this->assertEmpty( $message, 'PASS should have no error message' );
	}

	/**
	 * Test compliance check with WARN result.
	 *
	 * @test
	 */
	public function compliance_warn_identifies_unverified_claims() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=compliance_warn_response.json' );

		$validator = new ComplianceValidator();
		$result = $validator->validate( 'Join satisfied customers who see results.', array(
			'channel' => 'linkedin',
			'phase_tag' => 'Attention',
		) );

		// Assert failed
		$this->assertArrayHasKey( 'passed', $result );
		$this->assertFalse( $result['passed'], 'Compliance check should fail for WARN content' );

		// Assert message present
		$this->assertArrayHasKey( 'message', $result );
		$this->assertNotEmpty( $result['message'], 'WARN should have descriptive message' );

		// Assert flags present
		$this->assertArrayHasKey( 'flags', $result );
		$flags = $result['flags'];
		$this->assertNotEmpty( $flags, 'WARN should have compliance flags' );

		// Assert confidence in reasonable range
		$this->assertArrayHasKey( 'confidence_score', $result );
		$this->assertGreaterThan( 0.7, $result['confidence_score'], 'WARN confidence should be reasonably high' );
	}

	/**
	 * Test compliance check with FAIL result.
	 *
	 * @test
	 */
	public function compliance_fail_detects_blacklisted_phrases() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=compliance_fail_response.json' );

		$validator = new ComplianceValidator();
		$result = $validator->validate( 'Get guaranteed results with our risk-free solution!', array(
			'channel' => 'linkedin',
			'phase_tag' => 'Attention',
		) );

		// Assert failed
		$this->assertArrayHasKey( 'passed', $result );
		$this->assertFalse( $result['passed'], 'Compliance check must fail for blacklisted phrases' );

		// Assert critical message
		$this->assertArrayHasKey( 'message', $result );
		$this->assertNotEmpty( $result['message'], 'FAIL must have error message' );
		$this->assertContainsCaseInsensitive( 'blocked', $result['message'], 'Message should indicate blocked content' );

		// Assert violation type or flags
		if ( isset( $result['flags'] ) ) {
			$flags = $result['flags'];
			$this->assertNotEmpty( $flags, 'FAIL should have compliance flags if present' );
		} else {
			// Rule-based validation uses violation_type instead
			$this->assertArrayHasKey( 'violation_type', $result, 'FAIL should have violation_type or flags' );
			$this->assertEquals( 'blacklist', $result['violation_type'], 'Should be blacklist violation' );
		}

		// Assert high confidence in violation
		$this->assertArrayHasKey( 'confidence_score', $result );
		$this->assertGreaterThanOrEqual( 0.95, $result['confidence_score'], 'FAIL should have very high confidence' );
	}

	/**
	 * Test complete generation response schema.
	 *
	 * @test
	 */
	public function complete_generation_response_validates_correctly() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_response.json' );

		$generator = new SmmaGenerator();
		$result = $generator->generate( array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'tone' => 'Authority',
			'num_variants' => 2,
			'generate_google_ads' => false,
		) );

		// Validate complete response
		$validation = $this->schema_validator->validate_generation_response( $result );
		$this->assertTrue(
			$validation === true,
			'Complete generation response failed validation: ' . ( is_wp_error( $validation ) ? $validation->get_error_message() : '' )
		);

		// Assert no schema validation errors
		$this->assertArrayNotHasKey( 'schema_validation_error', $result, 'Response should not have schema validation errors' );

		// Assert model information
		$this->assertArrayHasKey( 'model', $result );
		$this->assertNotEmpty( $result['model'], 'Model identifier should be present' );
	}

	/**
	 * Test fixture determinism and reproducibility.
	 *
	 * @test
	 */
	public function golden_fixtures_produce_deterministic_results() {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_response.json' );

		$generator = new SmmaGenerator();
		$input = array(
			'post_id' => 123,
			'phase_tag' => 'Attention',
			'num_variants' => 2,
			'generate_google_ads' => false,
		);

		// Generate twice
		$result1 = $generator->generate( $input );
		$result2 = $generator->generate( $input );

		// Assert identical results
		$this->assertEquals(
			$result1['linkedin_variants'][0]['variant_id'],
			$result2['linkedin_variants'][0]['variant_id'],
			'Fixture should produce deterministic variant IDs'
		);

		$this->assertEquals(
			$result1['linkedin_variants'][0]['text'],
			$result2['linkedin_variants'][0]['text'],
			'Fixture should produce identical text on repeated calls'
		);
	}

	/**
	 * Helper: Assert string contains substring (case-insensitive).
	 */
	private function assertContainsCaseInsensitive( string $needle, string $haystack, string $message = '' ) {
		$this->assertStringContainsString( strtolower( $needle ), strtolower( $haystack ), $message );
	}

	/**
	 * Helper: Assert string does not contain substring (case-insensitive).
	 */
	private function assertNotContainsCaseInsensitive( string $needle, string $haystack, string $message = '' ) {
		$this->assertStringNotContainsString( strtolower( $needle ), strtolower( $haystack ), $message );
	}
}
