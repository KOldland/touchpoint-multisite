<?php
namespace KH_SMMA\Tests;

/**
 * Mock LLM Client for testing.
 *
 * Loads golden responses from fixtures instead of making real API calls.
 * Usage in tests:
 *   - Set KH_SMMA_GOLDEN_FIXTURE env var to specify which fixture to load
 *   - Use MockLLMClient instead of Dual_GPT\Dual_GPT_LLM_Client
 *
 * CI Safety:
 *   - When KH_SMMA_TEST_MODE=ci, fails if real API keys are detected
 */
class MockLLMClient {
	private $fixture_dir;
	private $model_name = 'mock-gpt-4-turbo';

	public function __construct() {
		$this->fixture_dir = dirname( __FILE__ ) . '/fixtures/golden/';

		// CI Safety: Fail fast if running in CI with real API keys
		if ( $this->is_ci_mode() && $this->has_real_api_key() ) {
			throw new \Exception(
				'CRITICAL: CI environment detected with real LLM API key. ' .
				'Tests must use golden stubs only. Remove API key from CI environment.'
			);
		}
	}

	/**
	 * Check if running in CI mode.
	 *
	 * @return bool True if in CI mode
	 */
	private function is_ci_mode(): bool {
		return getenv( 'KH_SMMA_TEST_MODE' ) === 'ci' ||
		       getenv( 'CI' ) === 'true' ||
		       getenv( 'GITHUB_ACTIONS' ) === 'true';
	}

	/**
	 * Check if real API key is present.
	 *
	 * @return bool True if real API key detected
	 */
	private function has_real_api_key(): bool {
		$suspects = array(
			'OPENAI_API_KEY',
			'ANTHROPIC_API_KEY',
			'DUAL_GPT_API_KEY',
			'LLM_API_KEY',
		);

		foreach ( $suspects as $key ) {
			$value = getenv( $key );
			if ( ! empty( $value ) && strlen( $value ) > 10 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Mock has_api_key check.
	 *
	 * @return bool Always true in mock mode
	 */
	public function has_api_key(): bool {
		return true;
	}

	/**
	 * Get model name.
	 *
	 * @return string Mock model name
	 */
	public function get_model_name(): string {
		return $this->model_name;
	}

	/**
	 * Mock LLM call that returns golden fixture data.
	 *
	 * @param string $system System prompt (ignored in mock)
	 * @param string $user User prompt (ignored in mock)
	 * @param array  $options Call options (ignored in mock)
	 * @return array Golden response data
	 */
	public function call( string $system, string $user, array $options = array() ): array {
		// Determine which fixture to load based on environment or prompt content
		$fixture_name = $this->determine_fixture( $system, $user );
		$fixture_path = $this->fixture_dir . $fixture_name;

		if ( ! file_exists( $fixture_path ) ) {
			// Fallback to default generate response
			$fixture_path = $this->fixture_dir . 'generate_response.json';
		}

		$content = file_get_contents( $fixture_path );
		$response = json_decode( $content, true );

		if ( ! $response ) {
			throw new \Exception( "Failed to parse golden fixture: {$fixture_name}" );
		}

		// Add metadata for telemetry tracking
		$response['_mock'] = true;
		$response['_fixture'] = $fixture_name;
		$response['_prompt_hash'] = $this->hash_prompt( $system, $user );

		return $response;
	}

	/**
	 * Determine which fixture to load based on prompts or environment.
	 *
	 * @param string $system System prompt
	 * @param string $user User prompt
	 * @return string Fixture filename
	 */
	private function determine_fixture( string $system, string $user ): string {
		// Allow explicit fixture selection via env var
		$explicit = getenv( 'KH_SMMA_GOLDEN_FIXTURE' );
		if ( ! empty( $explicit ) ) {
			return $explicit;
		}

		// Auto-detect based on prompt content
		if ( stripos( $system, 'compliance validator' ) !== false ) {
			// Compliance validation prompt
			if ( stripos( $user, 'guaranteed' ) !== false || stripos( $user, 'risk-free' ) !== false ) {
				return 'compliance_warn_response.json';
			}
			return 'compliance_pass_response.json';
		}

		// Default to generate response
		return 'generate_response.json';
	}

	/**
	 * Generate hash of prompts for telemetry.
	 *
	 * @param string $system System prompt
	 * @param string $user User prompt
	 * @return string Hash of combined prompts
	 */
	private function hash_prompt( string $system, string $user ): string {
		return hash( 'sha256', $system . '|' . $user );
	}

	/**
	 * Generate hash of response for telemetry.
	 *
	 * @param array $response LLM response
	 * @return string Hash of response content
	 */
	public static function hash_response( array $response ): string {
		$content = $response['choices'][0]['message']['content'] ?? '';
		return hash( 'sha256', $content );
	}
}

/**
 * Helper function to inject mock client in tests.
 *
 * Usage in tests:
 *   KH_SMMA\Tests\inject_mock_llm_client();
 */
function inject_mock_llm_client() {
	if ( ! defined( 'KH_SMMA_USE_MOCK_LLM' ) ) {
		define( 'KH_SMMA_USE_MOCK_LLM', true );
	}

	// Override class_exists check for Dual_GPT
	if ( ! class_exists( '\\Dual_GPT\\Dual_GPT_LLM_Client' ) ) {
		class_alias( '\\KH_SMMA\\Tests\\MockLLMClient', '\\Dual_GPT\\Dual_GPT_LLM_Client' );
	}
}
