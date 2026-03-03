<?php

namespace KH_SMMA\Tests\Lib;

use KH_SMMA\Tests\MockLLMClient;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/MockLLMClient.php';
require_once dirname( __DIR__ ) . '/TestHelpers.php';

class MockLLMClientTest extends TestCase {
	/** @var array<string, string|false> */
	private $original_env = array();

	protected function setUp(): void {
		parent::setUp();
		$this->snapshot_env();
		putenv( 'KH_SMMA_TEST_MODE=ci' );
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=generate_awareness_ok.json' );
		putenv( 'OPENAI_API_KEY' );
		putenv( 'OPENAI_KEY' );
		putenv( 'ANTHROPIC_API_KEY' );
		putenv( 'ANTHROPIC_KEY' );
		putenv( 'DUAL_GPT_API_KEY' );
		putenv( 'LLM_API_KEY' );
	}

	protected function tearDown(): void {
		$this->restore_env();
		parent::tearDown();
	}

	private function snapshot_env(): void {
		$keys = array(
			'KH_SMMA_TEST_MODE',
			'KH_SMMA_GOLDEN_FIXTURE',
			'OPENAI_API_KEY',
			'OPENAI_KEY',
			'ANTHROPIC_API_KEY',
			'ANTHROPIC_KEY',
			'DUAL_GPT_API_KEY',
			'LLM_API_KEY',
		);

		foreach ( $keys as $key ) {
			$this->original_env[ $key ] = getenv( $key );
		}
	}

	private function restore_env(): void {
		foreach ( $this->original_env as $key => $value ) {
			if ( false === $value ) {
				putenv( $key );
			} else {
				putenv( $key . '=' . $value );
			}
		}
	}

	public function test_returns_fixture_when_in_ci_mode(): void {
		$client = new MockLLMClient();
		$response = $client->call( 'SMMA-AI', 'deterministic test' );

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'choices', $response );
		$this->assertSame( 'generate_awareness_ok.json', $response['_fixture'] );
	}

	public function test_generate_decodes_fixture_payload(): void {
		$client = new MockLLMClient();
		$decoded = $client->generate( array( 'prompt' => 'ignored in ci mode' ) );

		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'linkedin_variants', $decoded );
		$this->assertNotEmpty( $decoded['linkedin_variants'][0]['text'] ?? '' );
	}

	public function test_fails_if_fixture_missing_in_ci_mode(): void {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE=does_not_exist.json' );
		$client = new MockLLMClient();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Golden fixture not found' );
		$client->call( 'SMMA-AI', 'x' );
	}

	public function test_fails_if_fixture_not_set_in_ci_mode(): void {
		putenv( 'KH_SMMA_GOLDEN_FIXTURE' );
		$client = new MockLLMClient();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'KH_SMMA_TEST_MODE=ci requires KH_SMMA_GOLDEN_FIXTURE to be set.' );
		$client->call( 'SMMA-AI', 'x' );
	}

	public function test_fails_if_real_keys_present_in_ci_mode(): void {
		putenv( 'OPENAI_API_KEY=real_key_for_test_1234567890123' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'CI environment detected with real LLM API key' );
		new MockLLMClient();
	}

	public function test_normalize_fixture_helper_masks_volatile_fields(): void {
		$raw = array(
			'id' => 'evt_12345',
			'created' => 1711111111,
			'data' => array(
				'subscription_id' => 'sub_456',
				'published_at' => '2026-03-03T13:00:00Z',
			),
		);

		$normalized = normalize_fixture( $raw );

		$this->assertSame( '{{ID}}', $normalized['id'] );
		$this->assertSame( '{{UNIX_TS}}', $normalized['created'] );
		$this->assertSame( '{{ID}}', $normalized['data']['subscription_id'] );
		$this->assertSame( '{{ISO8601}}', $normalized['data']['published_at'] );
	}
}
