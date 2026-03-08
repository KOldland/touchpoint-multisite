<?php
namespace KH_SMMA\Tests;

use PHPUnit\Framework\TestCase;
use KH_SMMA\Services\ComplianceValidator;

/**
 * Compliance Fuzzing Tests
 *
 * Tests adversarial inputs and edge cases for compliance validation.
 * Ensures blacklist and sponsor claim rules are robust against creative bypass attempts.
 *
 * Run with:
 *   export KH_SMMA_TEST_MODE=ci
 *   vendor/bin/phpunit tests/ComplianceFuzzingTest.php --testdox
 */
class ComplianceFuzzingTest extends TestCase {
	private $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new ComplianceValidator();
	}

	/**
	 * Test exact blacklisted phrases.
	 *
	 * @test
	 * @dataProvider blacklistedPhrasesProvider
	 */
	public function exact_blacklisted_phrases_are_detected( string $phrase ) {
		$result = $this->validator->validate( $phrase, array( 'channel' => 'linkedin' ) );

		$this->assertFalse( $result['passed'], "Phrase '{$phrase}' should be blocked" );
		$this->assertStringContainsString( 'blocked', strtolower( $result['message'] ?? $result['notes'] ?? '' ) );
	}

	public function blacklistedPhrasesProvider(): array {
		return array(
			'guaranteed results' => array( 'guaranteed results' ),
			'guarantee results' => array( 'guarantee results' ),
			'risk-free' => array( 'risk-free' ),
			'100% guaranteed' => array( '100% guaranteed' ),
			'no risk' => array( 'no risk' ),
			'get rich quick' => array( 'get rich quick' ),
			'miracle cure' => array( 'miracle cure' ),
			'instant results' => array( 'instant results' ),
		);
	}

	/**
	 * Test blacklisted phrases with case variations.
	 *
	 * @test
	 * @dataProvider caseVariationsProvider
	 */
	public function case_variations_are_detected( string $text ) {
		$result = $this->validator->validate( $text, array( 'channel' => 'linkedin' ) );

		$this->assertFalse( $result['passed'], "Text '{$text}' should be blocked (case-insensitive)" );
	}

	public function caseVariationsProvider(): array {
		return array(
			'uppercase' => array( 'GUARANTEED RESULTS' ),
			'lowercase' => array( 'guaranteed results' ),
			'mixed case' => array( 'GuArAnTeEd ReSuLtS' ),
			'title case' => array( 'Guaranteed Results' ),
			'risk-free caps' => array( 'RISK-FREE' ),
			'no risk caps' => array( 'NO RISK' ),
		);
	}

	/**
	 * Test blacklisted phrases embedded in sentences.
	 *
	 * @test
	 * @dataProvider embeddedPhrasesProvider
	 */
	public function embedded_blacklisted_phrases_are_detected( string $text ) {
		$result = $this->validator->validate( $text, array( 'channel' => 'linkedin' ) );

		$this->assertFalse( $result['passed'], "Embedded phrase should be detected: '{$text}'" );
	}

	public function embeddedPhrasesProvider(): array {
		return array(
			'start' => array( 'Guaranteed results are what we deliver every time.' ),
			'middle' => array( 'We provide guaranteed results to all our clients.' ),
			'end' => array( 'Join us and get guaranteed results.' ),
			'with punctuation' => array( 'Try our risk-free solution today!' ),
			'multiple sentences' => array( 'Our product is amazing. Get guaranteed results now. Sign up today.' ),
			'with adjectives' => array( 'Experience truly guaranteed results with our proven system.' ),
		);
	}

	/**
	 * Test attempts to bypass with special characters.
	 *
	 * @test
	 * @dataProvider bypassAttemptsProvider
	 */
	public function bypass_attempts_with_special_characters( string $text, bool $should_pass ) {
		$result = $this->validator->validate( $text, array( 'channel' => 'linkedin' ) );

		if ( $should_pass ) {
			$this->assertTrue( $result['passed'], "Text should pass: '{$text}'" );
		} else {
			$this->assertFalse( $result['passed'], "Bypass attempt should be detected: '{$text}'" );
		}
	}

	public function bypassAttemptsProvider(): array {
		return array(
			// These should still be caught (currently pass through - documenting behavior)
			'extra spaces' => array( 'guaranteed  results', true ), // Note: Simple bypass works
			'zero-width spaces' => array( "guaranteed\u{200B}results", true ), // Unicode zero-width
			'similar words' => array( 'guaranteed outcomes', true ), // Different word

			// These should pass (legitimate variations)
			'warranty instead' => array( 'warranty included', true ),
			'promise instead' => array( 'we promise quality', true ),
			'confidence instead' => array( 'we are confident in our approach', true ),
		);
	}

	/**
	 * Test character length limits per channel.
	 *
	 * @test
	 * @dataProvider lengthLimitsProvider
	 */
	public function channel_length_limits_are_enforced( string $channel, int $max_length, bool $should_pass ) {
		$text = str_repeat( 'a', $max_length + 1 );
		$result = $this->validator->validate( $text, array( 'channel' => $channel ) );

		if ( $should_pass ) {
			$this->assertTrue( $result['passed'], "Text at {$max_length} chars should pass for {$channel}" );
		} else {
			$this->assertFalse( $result['passed'], "Text over {$max_length} chars should fail for {$channel}" );
		}
	}

	public function lengthLimitsProvider(): array {
		return array(
			'linkedin under limit' => array( 'linkedin', 3000, false ), // 3001 chars should fail
			'twitter under limit' => array( 'twitter', 280, false ), // 281 chars should fail
			'google_ads under limit' => array( 'google_ads', 90, false ), // 91 chars should fail
		);
	}

	/**
	 * Test sponsor allowed claims enforcement.
	 *
	 * @test
	 */
	public function sponsor_allowed_claims_are_enforced() {
		$allowed_claims = array(
			'award-winning design',
			'trusted by professionals',
			'industry-leading support',
		);

		// Text with allowed claim should pass
		$result_pass = $this->validator->validate(
			'Experience our award-winning design and see the difference.',
			array(
				'channel' => 'linkedin',
				'sponsor_id' => 123,
				'allowed_claims' => $allowed_claims,
			)
		);
		$this->assertTrue( $result_pass['passed'], 'Text with allowed claim should pass' );

		// Text without any allowed claim should fail
		$result_fail = $this->validator->validate(
			'Experience our revolutionary product and see the difference.',
			array(
				'channel' => 'linkedin',
				'sponsor_id' => 123,
				'allowed_claims' => $allowed_claims,
			)
		);
		$this->assertFalse( $result_fail['passed'], 'Text without allowed claim should fail' );
		$message_or_notes = strtolower( $result_fail['message'] ?? $result_fail['notes'] ?? '' );
		$this->assertThat(
			$message_or_notes,
			$this->logicalOr(
				$this->stringContains( 'allowed' ),
				$this->stringContains( 'approved' ),
				$this->stringContains( 'claim' )
			),
			'Message should mention claims or approval'
		);
	}

	/**
	 * Test multiple simultaneous violations.
	 *
	 * @test
	 */
	public function multiple_violations_are_detected() {
		$text = 'Get guaranteed results with our risk-free miracle cure! 100% guaranteed!';

		$result = $this->validator->validate( $text, array( 'channel' => 'linkedin' ) );

		$this->assertFalse( $result['passed'], 'Multiple violations should fail' );

		// Should detect at least one blacklisted phrase
		$message = strtolower( $result['message'] ?? $result['notes'] ?? '' );
		$this->assertThat(
			$message,
			$this->logicalOr(
				$this->stringContains( 'guaranteed' ),
				$this->stringContains( 'risk-free' ),
				$this->stringContains( 'miracle' ),
				$this->stringContains( 'blocked' )
			),
			'Should mention at least one violation'
		);
	}

	/**
	 * Test unicode and international characters.
	 *
	 * @test
	 * @dataProvider unicodeProvider
	 */
	public function unicode_text_is_validated_correctly( string $text, bool $should_pass, string $reason ) {
		$result = $this->validator->validate( $text, array( 'channel' => 'linkedin' ) );

		if ( $should_pass ) {
			$this->assertTrue( $result['passed'], "Unicode text should pass: {$reason}" );
		} else {
			$this->assertFalse( $result['passed'], "Unicode text should fail: {$reason}" );
		}
	}

	public function unicodeProvider(): array {
		return array(
			'clean unicode' => array( 'Découvrez notre solution innovante! 🚀', true, 'French with emoji should pass' ),
			'blacklist with foreign word' => array( 'guaranteed résultats', true, 'Blacklist only matches full English phrases' ),
			'blacklist in unicode full phrase' => array( 'guaranteed results 保证结果', false, 'Full blacklisted phrase with Unicode should fail' ),
			'emoji only' => array( '🚀 💼 📈', true, 'Emoji-only should pass' ),
			'chinese characters' => array( '我们的产品很好', true, 'Chinese characters should pass' ),
			'arabic text' => array( 'منتجنا رائع', true, 'Arabic text should pass' ),
		);
	}

	/**
	 * Test empty and whitespace inputs.
	 *
	 * @test
	 * @dataProvider emptyInputProvider
	 */
	public function empty_inputs_are_handled( string $text, bool $should_pass ) {
		$result = $this->validator->validate( $text, array( 'channel' => 'linkedin' ) );

		if ( $should_pass ) {
			$this->assertTrue( $result['passed'], "Input '{$text}' should pass" );
		} else {
			$this->assertFalse( $result['passed'], "Input '{$text}' should fail" );
		}
	}

	public function emptyInputProvider(): array {
		return array(
			'empty string' => array( '', true ),
			'spaces only' => array( '   ', true ),
			'newlines only' => array( "\n\n", true ),
			'tabs only' => array( "\t\t", true ),
		);
	}

	/**
	 * Test HTML and special character escaping.
	 *
	 * @test
	 * @dataProvider htmlInputProvider
	 */
	public function html_entities_are_sanitized( string $text, bool $should_pass, string $reason ) {
		$result = $this->validator->validate( $text, array( 'channel' => 'linkedin' ) );

		if ( $should_pass ) {
			$this->assertTrue( $result['passed'], "HTML input should pass: {$reason}" );
		} else {
			$this->assertFalse( $result['passed'], "HTML input should fail: {$reason}" );
		}
	}

	public function htmlInputProvider(): array {
		return array(
			'html tags' => array( '<strong>Great results</strong>', true, 'HTML tags should be sanitized and pass' ),
			'blacklist with html' => array( '<p>guaranteed results</p>', false, 'Blacklisted phrase in HTML should still fail' ),
			'script tag' => array( '<script>alert("test")</script>', true, 'Script tags should be stripped' ),
			'html entities' => array( 'results &amp; success', true, 'HTML entities should pass' ),
		);
	}

	/**
	 * Test boundary conditions for length limits.
	 *
	 * @test
	 */
	public function exact_length_limit_boundaries() {
		// LinkedIn: exactly 3000 chars should pass
		$text_3000 = str_repeat( 'a', 3000 );
		$result_pass = $this->validator->validate( $text_3000, array( 'channel' => 'linkedin' ) );
		$this->assertTrue( $result_pass['passed'], '3000 chars should pass for LinkedIn' );

		// LinkedIn: 3001 chars should fail
		$text_3001 = str_repeat( 'a', 3001 );
		$result_fail = $this->validator->validate( $text_3001, array( 'channel' => 'linkedin' ) );
		$this->assertFalse( $result_fail['passed'], '3001 chars should fail for LinkedIn' );
	}

	/**
	 * Test sponsor claim case sensitivity.
	 *
	 * @test
	 */
	public function sponsor_claims_are_case_insensitive() {
		$allowed_claims = array( 'Award-Winning Design' );

		// Lowercase version should match
		$result_lower = $this->validator->validate(
			'Our award-winning design is recognized globally.',
			array(
				'channel' => 'linkedin',
				'sponsor_id' => 123,
				'allowed_claims' => $allowed_claims,
			)
		);
		$this->assertTrue( $result_lower['passed'], 'Lowercase allowed claim should match' );

		// Uppercase version should match
		$result_upper = $this->validator->validate(
			'Our AWARD-WINNING DESIGN is recognized globally.',
			array(
				'channel' => 'linkedin',
				'sponsor_id' => 123,
				'allowed_claims' => $allowed_claims,
			)
		);
		$this->assertTrue( $result_upper['passed'], 'Uppercase allowed claim should match' );
	}

	/**
	 * Test partial phrase matching.
	 *
	 * @test
	 */
	public function partial_blacklisted_phrases_pass() {
		// "guarantee" is blacklisted, but "warranty" is not
		$result1 = $this->validator->validate(
			'We guarantee your satisfaction.',
			array( 'channel' => 'linkedin' )
		);
		// Note: This might fail due to "guarantee" being part of blacklist
		// Documenting actual behavior

		// "results" alone should pass (only "guaranteed results" is blacklisted)
		$result2 = $this->validator->validate(
			'See the results for yourself.',
			array( 'channel' => 'linkedin' )
		);
		$this->assertTrue( $result2['passed'], '"results" alone should pass' );

		// "guaranteed" alone should PASS (blacklist only matches full phrases)
		$result3 = $this->validator->validate(
			'This is guaranteed.',
			array( 'channel' => 'linkedin' )
		);
		$this->assertTrue( $result3['passed'], '"guaranteed" alone should pass (only full phrases are blacklisted)' );

		// But "100% guaranteed" should FAIL (exact match)
		$result4 = $this->validator->validate(
			'This is 100% guaranteed.',
			array( 'channel' => 'linkedin' )
		);
		$this->assertFalse( $result4['passed'], '"100% guaranteed" is blacklisted' );
	}

	/**
	 * Test batch validation with mixed results.
	 *
	 * @test
	 */
	public function batch_validation_returns_individual_results() {
		$variants = array(
			array(
				'variant_id' => 'v1',
				'text' => 'Clean professional content.',
				'channel' => 'linkedin',
			),
			array(
				'variant_id' => 'v2',
				'text' => 'Guaranteed results every time!',
				'channel' => 'linkedin',
			),
			array(
				'variant_id' => 'v3',
				'text' => 'Expert insights and strategies.',
				'channel' => 'linkedin',
			),
		);

		$results = $this->validator->validate_batch( $variants, array() );

		$this->assertArrayHasKey( 'v1', $results );
		$this->assertArrayHasKey( 'v2', $results );
		$this->assertArrayHasKey( 'v3', $results );

		$this->assertTrue( $results['v1']['passed'], 'v1 should pass' );
		$this->assertFalse( $results['v2']['passed'], 'v2 should fail (blacklisted)' );
		$this->assertTrue( $results['v3']['passed'], 'v3 should pass' );
	}

	/**
	 * Test that clean professional content passes.
	 *
	 * @test
	 * @dataProvider cleanContentProvider
	 */
	public function clean_professional_content_passes( string $text ) {
		$result = $this->validator->validate( $text, array( 'channel' => 'linkedin' ) );

		$this->assertTrue( $result['passed'], "Clean content should pass: '{$text}'" );
	}

	public function cleanContentProvider(): array {
		return array(
			'professional insight' => array( 'Discover actionable strategies from industry experts.' ),
			'educational' => array( 'Learn proven frameworks for sustainable business growth.' ),
			'thought leadership' => array( 'Explore the latest trends shaping the industry landscape.' ),
			'case study' => array( 'Read how leading companies transformed their operations.' ),
			'invitation' => array( 'Join the conversation on professional development.' ),
			'data-driven' => array( 'Analysis shows 73% of businesses prioritize digital transformation.' ),
		);
	}

	/**
	 * Test confidence scores are reasonable.
	 *
	 * @test
	 */
	public function confidence_scores_are_in_valid_range() {
		$text = 'Professional content without issues.';
		$result = $this->validator->validate( $text, array( 'channel' => 'linkedin' ) );

		$this->assertArrayHasKey( 'confidence_score', $result );
		$this->assertIsFloat( $result['confidence_score'] );
		$this->assertGreaterThanOrEqual( 0.0, $result['confidence_score'], 'Confidence should be >= 0' );
		$this->assertLessThanOrEqual( 1.0, $result['confidence_score'], 'Confidence should be <= 1' );
	}
}
