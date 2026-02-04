<?php
namespace KH_SMMA\Tests;

use KH_SMMA\Services\ComplianceValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ComplianceValidator service.
 *
 * Tests cover:
 * - Rule-based validation (blacklist, length, allowed claims)
 * - AI-powered validation (mocked responses)
 * - Edge cases and error handling
 */
class ComplianceValidatorTest extends TestCase {
	private ComplianceValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new ComplianceValidator();

		// Mock WordPress functions if not in WordPress environment
		if ( ! function_exists( '__' ) ) {
			function __( $text, $domain ) {
				return $text;
			}
		}
		if ( ! function_exists( 'stripos' ) ) {
			// PHP has this natively, but for completeness
		}
	}

	/**
	 * Test 1: Rule-based pass
	 * Input: text without any disallowed phrase
	 * Expect: passed=true, compliance_notes='OK'
	 */
	public function test_rule_based_pass(): void {
		$text = 'This is a completely normal promotional text about our product features.';
		$context = array(
			'channel' => 'linkedin',
			'phase_tag' => 'Attention',
		);

		$result = $this->validator->validate( $text, $context );

		$this->assertTrue( $result['passed'], 'Expected validation to pass for clean text' );
		$this->assertStringContainsString( 'OK', $result['notes'], 'Expected OK in compliance notes' );
		$this->assertArrayHasKey( 'confidence_score', $result );
		$this->assertGreaterThan( 0.0, $result['confidence_score'] );
	}

	/**
	 * Test 2: Blacklist phrase detection - guaranteed results
	 */
	public function test_blacklist_guaranteed_results(): void {
		$text = 'Our product delivers guaranteed results in just 30 days!';
		$context = array( 'channel' => 'linkedin' );

		$result = $this->validator->validate( $text, $context );

		$this->assertFalse( $result['passed'], 'Expected validation to fail for blacklisted phrase' );
		$this->assertStringContainsString( 'guaranteed results', strtolower( $result['message'] ) );
		$this->assertEquals( 'blacklist', $result['violation_type'] ?? '' );
		$this->assertEquals( 1.0, $result['confidence_score'] );
	}

	/**
	 * Test 3: Blacklist phrase detection - risk-free
	 */
	public function test_blacklist_risk_free(): void {
		$text = 'Try our risk-free trial today!';
		$context = array( 'channel' => 'linkedin' );

		$result = $this->validator->validate( $text, $context );

		$this->assertFalse( $result['passed'] );
		$this->assertStringContainsString( 'risk-free', strtolower( $result['message'] ) );
		$this->assertEquals( 'blacklist', $result['violation_type'] ?? '' );
	}

	/**
	 * Test 4: Blacklist phrase detection - 100% guaranteed
	 */
	public function test_blacklist_100_percent_guaranteed(): void {
		$text = 'We offer a 100% guaranteed solution.';
		$context = array( 'channel' => 'linkedin' );

		$result = $this->validator->validate( $text, $context );

		$this->assertFalse( $result['passed'] );
		$this->assertEquals( 'blacklist', $result['violation_type'] ?? '' );
	}

	/**
	 * Test 5: Channel length limit - LinkedIn pass
	 */
	public function test_channel_length_linkedin_pass(): void {
		$text = str_repeat( 'A', 2999 ); // Just under LinkedIn's 3000 limit
		$context = array( 'channel' => 'linkedin' );

		$result = $this->validator->validate( $text, $context );

		$this->assertTrue( $result['passed'], 'Expected validation to pass for text under length limit' );
	}

	/**
	 * Test 6: Channel length limit - LinkedIn fail
	 */
	public function test_channel_length_linkedin_fail(): void {
		$text = str_repeat( 'A', 3001 ); // Over LinkedIn's 3000 limit
		$context = array( 'channel' => 'linkedin' );

		$result = $this->validator->validate( $text, $context );

		$this->assertFalse( $result['passed'] );
		$this->assertEquals( 'length', $result['violation_type'] ?? '' );
		$this->assertStringContainsString( '3000', $result['message'] );
	}

	/**
	 * Test 7: Channel length limit - Twitter
	 */
	public function test_channel_length_twitter(): void {
		$text = str_repeat( 'A', 281 ); // Over Twitter's 280 limit
		$context = array( 'channel' => 'twitter' );

		$result = $this->validator->validate( $text, $context );

		$this->assertFalse( $result['passed'] );
		$this->assertEquals( 'length', $result['violation_type'] ?? '' );
		$this->assertStringContainsString( '280', $result['message'] );
	}

	/**
	 * Test 8: Exact match violation - allowed claims
	 * Sponsor allowed_claims: ["X does Y"]. Text includes "X does Y"
	 * Expect: passed=true (claim is present)
	 */
	public function test_exact_match_allowed_claim_present(): void {
		$text = 'Our product X does Y better than anyone else in the market.';
		$context = array(
			'channel' => 'linkedin',
			'sponsor_id' => 123,
			'allowed_claims' => array( 'X does Y' ),
		);

		$result = $this->validator->validate( $text, $context );

		$this->assertTrue( $result['passed'], 'Expected validation to pass when allowed claim is present' );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertArrayHasKey( 'rule_check', $result['details'] );
		// Check that rule-based validation passed (allowed claim was found)
		$this->assertTrue( $result['details']['rule_check']['passed'], 'Expected rule check to pass when allowed claim present' );
		$this->assertStringContainsString( 'ok', strtolower( $result['details']['rule_check']['notes'] ) );
	}

	/**
	 * Test 9: Missing allowed claim
	 * Sponsor allowed_claims: ["X does Y"]. Text does NOT include claim.
	 * Expect: passed=false, notes cite missing claim
	 */
	public function test_missing_allowed_claim(): void {
		$text = 'Our product is amazing and will transform your business.';
		$context = array(
			'channel' => 'linkedin',
			'sponsor_id' => 123,
			'allowed_claims' => array( 'certified solution', 'industry leader' ),
		);

		$result = $this->validator->validate( $text, $context );

		$this->assertFalse( $result['passed'], 'Expected validation to fail when required claim is missing' );
		$this->assertEquals( 'missing_claim', $result['violation_type'] ?? '' );
		$this->assertStringContainsString( 'approved claim', strtolower( $result['message'] ) );
	}

	/**
	 * Test 10: Fuzzy match / variant claim
	 * Allowed claim ["X does Y"]. Text includes "X can do Y"
	 * Current implementation uses exact stripos, so this should fail.
	 * Future: implement fuzzy matching with threshold.
	 */
	public function test_fuzzy_match_claim_variant(): void {
		$text = 'Our solution X can do Y effectively.';
		$context = array(
			'channel' => 'linkedin',
			'sponsor_id' => 123,
			'allowed_claims' => array( 'X does Y' ),
		);

		$result = $this->validator->validate( $text, $context );

		// Current implementation: exact match only, so this should fail
		$this->assertFalse( $result['passed'], 'Current implementation requires exact match' );
		$this->assertEquals( 'missing_claim', $result['violation_type'] ?? '' );

		// TODO: Implement fuzzy matching with Levenshtein or similar
		// When implemented, this test should pass with a WARN flag
	}

	/**
	 * Test 11: No sponsor context - no claim validation
	 */
	public function test_no_sponsor_no_claim_validation(): void {
		$text = 'Our product is great!';
		$context = array(
			'channel' => 'linkedin',
			// No sponsor_id, so no claim validation
		);

		$result = $this->validator->validate( $text, $context );

		$this->assertTrue( $result['passed'], 'Expected validation to pass when no sponsor context' );
	}

	/**
	 * Test 12: Empty allowed claims array
	 */
	public function test_empty_allowed_claims(): void {
		$text = 'Our product is the best on the market.';
		$context = array(
			'channel' => 'linkedin',
			'sponsor_id' => 123,
			'allowed_claims' => array(), // Empty array
		);

		$result = $this->validator->validate( $text, $context );

		$this->assertTrue( $result['passed'], 'Expected validation to pass with empty allowed_claims' );
	}

	/**
	 * Test 13: Case-insensitive blacklist matching
	 */
	public function test_case_insensitive_blacklist(): void {
		$text = 'Get GUARANTEED RESULTS now!';
		$context = array( 'channel' => 'linkedin' );

		$result = $this->validator->validate( $text, $context );

		$this->assertFalse( $result['passed'], 'Expected case-insensitive blacklist match' );
		$this->assertEquals( 'blacklist', $result['violation_type'] ?? '' );
	}

	/**
	 * Test 14: Case-insensitive allowed claim matching
	 */
	public function test_case_insensitive_allowed_claim(): void {
		$text = 'We are the INDUSTRY LEADER in this space.';
		$context = array(
			'channel' => 'linkedin',
			'sponsor_id' => 123,
			'allowed_claims' => array( 'industry leader' ),
		);

		$result = $this->validator->validate( $text, $context );

		$this->assertTrue( $result['passed'], 'Expected case-insensitive claim matching' );
	}

	/**
	 * Test 15: Multiple blacklist phrases in text
	 */
	public function test_multiple_blacklist_phrases(): void {
		$text = 'Guaranteed results with no risk and instant results!';
		$context = array( 'channel' => 'linkedin' );

		$result = $this->validator->validate( $text, $context );

		$this->assertFalse( $result['passed'] );
		$this->assertEquals( 'blacklist', $result['violation_type'] ?? '' );
		// Should fail on first match
	}

	/**
	 * Test 16: Batch validation
	 */
	public function test_batch_validation(): void {
		$variants = array(
			array(
				'variant_id' => 'v1',
				'text' => 'Clean promotional text.',
				'channel' => 'linkedin',
			),
			array(
				'variant_id' => 'v2',
				'text' => 'This has guaranteed results!',
				'channel' => 'linkedin',
			),
			array(
				'variant_id' => 'v3',
				'text' => str_repeat( 'A', 3001 ),
				'channel' => 'linkedin',
			),
		);

		$results = $this->validator->validate_batch( $variants );

		$this->assertCount( 3, $results );
		$this->assertTrue( $results['v1']['passed'] );
		$this->assertFalse( $results['v2']['passed'] );
		$this->assertFalse( $results['v3']['passed'] );
		$this->assertEquals( 'blacklist', $results['v2']['violation_type'] ?? '' );
		$this->assertEquals( 'length', $results['v3']['violation_type'] ?? '' );
	}

	/**
	 * Test 17: All blacklist phrases
	 */
	public function test_all_blacklist_phrases(): void {
		$blacklist_phrases = array(
			'guaranteed results',
			'guarantee results',
			'risk-free',
			'100% guaranteed',
			'no risk',
			'get rich quick',
			'miracle cure',
			'instant results',
		);

		foreach ( $blacklist_phrases as $phrase ) {
			$text = "Check out this amazing offer with {$phrase} for everyone!";
			$context = array( 'channel' => 'linkedin' );

			$result = $this->validator->validate( $text, $context );

			$this->assertFalse(
				$result['passed'],
				"Expected validation to fail for blacklist phrase: {$phrase}"
			);
			$this->assertEquals( 'blacklist', $result['violation_type'] ?? '' );
		}
	}

	/**
	 * Test 18: Whitespace and punctuation in claims
	 */
	public function test_claim_with_extra_whitespace(): void {
		$text = 'We are the industry  leader  with extra spacing.';
		$context = array(
			'channel' => 'linkedin',
			'sponsor_id' => 123,
			'allowed_claims' => array( 'industry leader' ),
		);

		$result = $this->validator->validate( $text, $context );

		// Current implementation should find "industry" but full phrase might not match
		// This test documents current behavior
		$this->assertFalse( $result['passed'], 'Extra whitespace prevents exact match' );
	}

	/**
	 * Test 19: Unicode and multibyte character length
	 */
	public function test_unicode_character_length(): void {
		$text = str_repeat( '日本語', 1000 ); // 3000 characters
		$context = array( 'channel' => 'linkedin' );

		$result = $this->validator->validate( $text, $context );

		$this->assertTrue( $result['passed'], 'Expected mb_strlen to handle unicode correctly' );
	}

	/**
	 * Test 20: Channel defaults to linkedin
	 */
	public function test_default_channel_linkedin(): void {
		$text = str_repeat( 'A', 2999 );
		$context = array(); // No channel specified

		$result = $this->validator->validate( $text, $context );

		$this->assertTrue( $result['passed'], 'Expected default channel to be linkedin with 3000 limit' );
	}

	/**
	 * Test 21: AI validation skip when unavailable
	 * Note: This tests the fallback behavior when LLM is not available
	 */
	public function test_ai_validation_skip_when_unavailable(): void {
		// When Dual_GPT class doesn't exist, AI validation should skip gracefully
		$text = 'Normal promotional text.';
		$context = array( 'channel' => 'linkedin' );

		$result = $this->validator->validate( $text, $context );

		// Should pass with rule-based validation only
		$this->assertTrue( $result['passed'] );

		// In production, check for SKIP note in AI validation details
		if ( isset( $result['details']['ai_check'] ) ) {
			$this->assertStringContainsString( 'SKIP', $result['details']['ai_check']['notes'] );
		}
	}

	/**
	 * Test 22: Regex-safe allowed claims
	 * Ensure special regex characters in claims don't cause errors
	 */
	public function test_regex_safe_allowed_claims(): void {
		$text = 'Our product (X) costs $100 and delivers [Y] results.';
		$context = array(
			'channel' => 'linkedin',
			'sponsor_id' => 123,
			'allowed_claims' => array( 'product (X)', 'costs $100' ),
		);

		$result = $this->validator->validate( $text, $context );

		// Current implementation uses stripos (safe), should find match
		$this->assertTrue( $result['passed'], 'Expected regex-safe claim matching' );
	}

	/**
	 * Test 23: Empty text validation
	 */
	public function test_empty_text(): void {
		$text = '';
		$context = array( 'channel' => 'linkedin' );

		$result = $this->validator->validate( $text, $context );

		// Empty text should pass (no blacklist, under length)
		$this->assertTrue( $result['passed'] );
	}

	/**
	 * Test 24: Very long text for Facebook
	 */
	public function test_facebook_long_text_pass(): void {
		$text = str_repeat( 'A', 63000 ); // Under Facebook's 63206 limit
		$context = array( 'channel' => 'facebook' );

		$result = $this->validator->validate( $text, $context );

		$this->assertTrue( $result['passed'] );
	}

	/**
	 * Test 25: Confidence scores are present and valid
	 */
	public function test_confidence_scores(): void {
		$text = 'Normal promotional text.';
		$context = array( 'channel' => 'linkedin' );

		$result = $this->validator->validate( $text, $context );

		$this->assertArrayHasKey( 'confidence_score', $result );
		$this->assertIsFloat( $result['confidence_score'] );
		$this->assertGreaterThanOrEqual( 0.0, $result['confidence_score'] );
		$this->assertLessThanOrEqual( 1.0, $result['confidence_score'] );
	}
}
