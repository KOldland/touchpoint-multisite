<?php
declare( strict_types=1 );

namespace KH_SMMA\Testing;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MockLLMProvider {
	private string $fixture_dir;

	public function __construct( ?string $fixture_dir = null ) {
		$this->fixture_dir = $fixture_dir ?: dirname( __DIR__, 2 ) . '/tests/fixtures/smma/generation';
	}

	/**
	 * Return a deterministic LLM envelope from fixture content.
	 *
	 * @return array<string,mixed>
	 */
	public function generate( string $fixture_name ): array {
		$path = rtrim( $this->fixture_dir, '/\\' ) . DIRECTORY_SEPARATOR . $fixture_name;
		if ( ! file_exists( $path ) ) {
			throw new RuntimeException( "MockLLM fixture not found: {$fixture_name}" );
		}

		$decoded = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $decoded ) ) {
			throw new RuntimeException( "Invalid MockLLM fixture JSON: {$fixture_name}" );
		}

		return array(
			'choices' => array(
				array(
					'message' => array(
						'content' => wp_json_encode( $decoded ),
					),
				),
			),
			'model' => 'mock-llm-provider',
		);
	}
}
