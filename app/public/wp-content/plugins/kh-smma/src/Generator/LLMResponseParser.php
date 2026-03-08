<?php
namespace KH_SMMA\Generator;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLMResponseParser {
	/**
	 * Parse and validate raw LLM response payload.
	 *
	 * @param array  $llm_response LLM API envelope.
	 * @param string $request_id   Request correlation ID.
	 * @return array|WP_Error
	 */
	public function parse_variants( array $llm_response, string $request_id ) {
		$content = $llm_response['choices'][0]['message']['content'] ?? '';
		$decoded = json_decode( (string) $content, true );

		if ( ! is_array( $decoded ) ) {
			return $this->error( $request_id, 'LLM returned malformed JSON' );
		}

		if ( ! isset( $decoded['variants'] ) && isset( $decoded['linkedin_variants'] ) ) {
			$decoded['variants'] = $decoded['linkedin_variants'];
		}

		$variants = $decoded['variants'] ?? null;
		if ( ! is_array( $variants ) ) {
			return $this->error( $request_id, 'LLM response missing variants array' );
		}

		foreach ( $variants as $index => $variant ) {
			if ( ! is_array( $variant ) ) {
				return $this->error( $request_id, "Variant {$index} is not an object" );
			}

			if ( '' === trim( (string) ( $variant['text'] ?? '' ) ) ) {
				return $this->error( $request_id, "Variant {$index} missing text" );
			}
			if ( '' === trim( (string) ( $variant['rationale'] ?? $variant['explainability'] ?? '' ) ) ) {
				return $this->error( $request_id, "Variant {$index} missing rationale" );
			}

			$asset_hints = $variant['asset_hints'] ?? array();
			if ( ! is_array( $asset_hints ) ) {
				return $this->error( $request_id, "Variant {$index} has invalid asset_hints" );
			}
			foreach ( $asset_hints as $hint_index => $hint ) {
				if ( ! is_array( $hint ) ) {
					return $this->error( $request_id, "Variant {$index} asset_hints[{$hint_index}] invalid" );
				}
				$type = (string) ( $hint['type'] ?? '' );
				$description = (string) ( $hint['description'] ?? '' );
				if ( ! in_array( $type, array( 'image', 'video', 'graphic' ), true ) ) {
					return $this->error( $request_id, "Variant {$index} asset_hints[{$hint_index}] invalid type" );
				}
				if ( '' === trim( $description ) ) {
					return $this->error( $request_id, "Variant {$index} asset_hints[{$hint_index}] missing description" );
				}
			}
		}

		return $decoded;
	}

	private function error( string $request_id, string $reason ): WP_Error {
		return new WP_Error(
			'SMMA_ERR_INVALID_LLM',
			'Invalid generator response',
			array(
				'error_type' => 'invalid_generator_response',
				'error_message' => 'LLM response malformed. Please regenerate variants.',
				'reason' => $reason,
				'generator_request_id' => $request_id,
			)
		);
	}
}
