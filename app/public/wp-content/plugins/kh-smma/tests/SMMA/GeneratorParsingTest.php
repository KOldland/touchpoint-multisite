<?php

use KH_SMMA\Generator\VariantGenerator;
use KH_SMMA\Testing\MockLLMProvider;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Generator/LLMResponseParser.php';
require_once dirname( __DIR__, 2 ) . '/src/Generator/VariantGenerator.php';
require_once dirname( __DIR__, 2 ) . '/src/Testing/MockLLMProvider.php';

final class GeneratorParsingTest extends TestCase {
	public function test_valid_json_response_parses_variants(): void {
		$provider = new MockLLMProvider();
		$generator = new VariantGenerator();

		$response = $provider->generate( 'generate_awareness_ok.json' );
		$parsed = $generator->generate_from_response( $response, 'req_parse_ok' );

		$this->assertIsArray( $parsed );
		$this->assertArrayHasKey( 'variants', $parsed );
		$this->assertNotEmpty( $parsed['variants'] );
		$this->assertSame( 'var_gen_ok_1', $parsed['variants'][0]['variant_id'] );
	}

	public function test_invalid_json_response_is_rejected(): void {
		$generator = new VariantGenerator();
		$response = array(
			'choices' => array(
				array(
					'message' => array(
						'content' => '{"variants":',
					),
				),
			),
		);

		$parsed = $generator->generate_from_response( $response, 'req_parse_invalid_json' );
		$this->assertInstanceOf( WP_Error::class, $parsed );
		$this->assertArrayHasKey( 'SMMA_ERR_INVALID_LLM', $parsed->errors );
	}

	public function test_missing_fields_are_rejected(): void {
		$generator = new VariantGenerator();
		$response = array(
			'choices' => array(
				array(
					'message' => array(
						'content' => wp_json_encode(
							array(
								'variants' => array(
									array(
										'variant_id' => 'v_missing_fields',
										'text' => '',
										'rationale' => '',
										'asset_hints' => array(),
									),
								),
							)
						),
					),
				),
			),
		);

		$parsed = $generator->generate_from_response( $response, 'req_parse_missing' );
		$this->assertInstanceOf( WP_Error::class, $parsed );
		$this->assertArrayHasKey( 'SMMA_ERR_INVALID_LLM', $parsed->errors );
	}

	public function test_unexpected_schema_is_rejected(): void {
		$generator = new VariantGenerator();
		$response = array(
			'choices' => array(
				array(
					'message' => array(
						'content' => wp_json_encode(
							array(
								'foo' => 'bar',
							)
						),
					),
				),
			),
		);

		$parsed = $generator->generate_from_response( $response, 'req_parse_schema' );
		$this->assertInstanceOf( WP_Error::class, $parsed );
		$this->assertArrayHasKey( 'SMMA_ERR_INVALID_LLM', $parsed->errors );
	}
}
