<?php
namespace KH_SMMA\Generator;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VariantGenerator {
	/** @var LLMResponseParser */
	private $parser;

	public function __construct( LLMResponseParser $parser = null ) {
		$this->parser = $parser ?? new LLMResponseParser();
	}

	/**
	 * @param array  $llm_response
	 * @param string $request_id
	 * @param string $platform
	 * @return array|WP_Error
	 */
	public function generate_from_response( array $llm_response, string $request_id, string $platform = 'linkedin' ) {
		$parsed = $this->parser->parse_variants( $llm_response, $request_id );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$variants = array();
		foreach ( $parsed['variants'] as $variant ) {
			$notes = strtoupper( (string) ( $variant['compliance_notes'] ?? 'OK' ) );
			$status = 'PASS';
			if ( strpos( $notes, 'FAIL' ) !== false ) {
				$status = 'FAIL';
			} elseif ( strpos( $notes, 'WARN' ) !== false ) {
				$status = 'WARN';
			}

			$hints = $variant['asset_hints'] ?? array();
			if ( isset( $hints['type'] ) ) {
				$hints = array( $hints );
			}

			$variants[] = array(
				'variant_id' => (string) ( $variant['variant_id'] ?? ( 'var_' . wp_generate_uuid4() ) ),
				'text' => (string) $variant['text'],
				'rationale' => (string) ( $variant['rationale'] ?? $variant['explainability'] ?? '' ),
				'asset_hints' => $hints,
				'platform' => (string) ( $variant['platform'] ?? $platform ),
				'compliance_status' => (string) ( $variant['compliance_status'] ?? $status ),
				'compliance_reason' => (string) ( $variant['compliance_reason'] ?? ( $status === 'PASS' ? '' : ( $variant['compliance_notes'] ?? '' ) ) ),
			);
		}

		return array(
			'variants' => $variants,
			'google_ad_draft' => is_array( $parsed['google_ad_draft'] ?? null ) ? $parsed['google_ad_draft'] : array(),
		);
	}
}
