<?php

use KH_SMMA\Generator\LLMResponseParser;
use KH_SMMA\Generator\VariantGenerator;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Generator/LLMResponseParser.php';
require_once dirname( __DIR__, 2 ) . '/src/Generator/VariantGenerator.php';

class VariantGeneratorTest extends TestCase {
	public function test_valid_json_response_is_parsed(): void {
		$fixture = json_decode( (string) file_get_contents( __DIR__ . '/../fixtures/smma/generator_response.json' ), true );
		$generator = new VariantGenerator( new LLMResponseParser() );
		$result = $generator->generate_from_response( $fixture, 'req_1', 'linkedin' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'variants', $result );
		$this->assertSame( 'var_fixture_1', $result['variants'][0]['variant_id'] );
		$this->assertSame( 'PASS', $result['variants'][0]['compliance_status'] );
		$this->assertSame( 'image', $result['variants'][0]['asset_hints'][0]['type'] );
	}

	public function test_invalid_json_response_is_rejected(): void {
		$response = array(
			'choices' => array(
				array(
					'message' => array(
						'content' => '{invalid json',
					),
				),
			),
		);

		$generator = new VariantGenerator( new LLMResponseParser() );
		$result = $generator->generate_from_response( $response, 'req_bad', 'linkedin' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertArrayHasKey( 'SMMA_ERR_INVALID_LLM', $result->errors );
	}

	public function test_missing_required_fields_are_rejected(): void {
		$response = array(
			'choices' => array(
				array(
					'message' => array(
						'content' => wp_json_encode(
							array(
								'variants' => array(
									array(
										'variant_id' => 'var_2',
										'text' => 'Hello',
										'asset_hints' => array(),
									),
								),
							)
						),
					),
				),
			),
		);

		$generator = new VariantGenerator( new LLMResponseParser() );
		$result = $generator->generate_from_response( $response, 'req_missing', 'linkedin' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertArrayHasKey( 'SMMA_ERR_INVALID_LLM', $result->errors );
	}
}
