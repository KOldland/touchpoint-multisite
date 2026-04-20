<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

class ConnectShortlistService {

	const DEFAULT_LIMIT = 5;

	public function shortlist( array $providers, array $criteria, string $title_context = '', int $limit = self::DEFAULT_LIMIT ): array {
		$normalized_criteria = $this->normalize_criteria( $criteria );
		$title_context       = $this->normalize_slug( $title_context );
		$scored              = array();

		foreach ( $providers as $provider ) {
			$score = $this->score_provider( $provider, $normalized_criteria, $title_context );
			if ( $score['score'] <= 0 ) {
				continue;
			}

			$provider['score']       = $score['score'];
			$provider['match_reasons'] = $score['reasons'];
			$provider['comparison_summary'] = $this->build_comparison_summary( $provider );
			$scored[]                = $provider;
		}

		usort(
			$scored,
			static function ( array $left, array $right ): int {
				if ( $left['score'] === $right['score'] ) {
					return strcmp( $left['name'], $right['name'] );
				}

				return $right['score'] <=> $left['score'];
			}
		);

		return array_slice( $scored, 0, max( 1, $limit ) );
	}

	public function score_provider( array $provider, array $criteria, string $title_context = '' ): array {
		$rules   = is_array( $provider['match_rules'] ?? null ) ? $provider['match_rules'] : array();
		$score   = 0.0;
		$reasons = array();

		$score += $this->score_term_set( $criteria['industries'], $rules['industries'] ?? array(), 25, 'Industry fit', $reasons );
		$score += $this->score_term_set( $criteria['regions'], $rules['regions'] ?? array(), 20, 'Regional fit', $reasons );
		$score += $this->score_term_set( $criteria['company_sizes'], $rules['company_sizes'] ?? array(), 15, 'Company-size fit', $reasons );
		$score += $this->score_term_set( $criteria['deployment'], $rules['deployment'] ?? array(), 15, 'Deployment fit', $reasons );
		$score += $this->score_budget( $criteria['budget'], $rules, $reasons );
		$score += $this->score_keyword_fit( $criteria['keywords'], $rules['keywords'] ?? array(), $reasons );

		if ( '' !== $title_context ) {
			$title_weights = is_array( $rules['title_weights'] ?? null ) ? $rules['title_weights'] : array();
			if ( isset( $title_weights[ $title_context ] ) ) {
				$weight = max( 0, (float) $title_weights[ $title_context ] );
				$score *= 1 + min( 1.5, $weight );
				$reasons[] = sprintf( 'Title affinity boost for %s', $title_context );
			}
		}

		return array(
			'score'   => round( $score, 2 ),
			'reasons' => array_values( array_unique( $reasons ) ),
		);
	}

	private function normalize_criteria( array $criteria ): array {
		return array(
			'industries'    => $this->normalize_list( $criteria['industries'] ?? array() ),
			'regions'       => $this->normalize_list( $criteria['regions'] ?? array() ),
			'company_sizes' => $this->normalize_list( $criteria['company_sizes'] ?? array() ),
			'deployment'    => $this->normalize_list( $criteria['deployment'] ?? array() ),
			'keywords'      => $this->normalize_keywords( $criteria['keywords'] ?? array() ),
			'budget'        => isset( $criteria['budget'] ) ? (float) $criteria['budget'] : 0.0,
		);
	}

	private function normalize_list( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[,|]/', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$normalized = array_map(
			static function ( $item ): string {
				return self::normalize_slug( (string) $item );
			},
			$value
		);

		return array_values( array_filter( array_unique( $normalized ) ) );
	}

	private static function normalize_slug( string $value ): string {
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( (string) $value, '-' );
	}

	private function normalize_keywords( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[,|]/', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$normalized = array_map(
			static function ( $item ): string {
				return strtolower( trim( sanitize_text_field( (string) $item ) ) );
			},
			$value
		);

		return array_values( array_filter( array_unique( $normalized ) ) );
	}

	private function score_term_set( array $wanted, $available, float $points, string $label, array &$reasons ): float {
		$available = $this->normalize_list( $available );
		if ( empty( $wanted ) || empty( $available ) ) {
			return 0.0;
		}

		$matches = array_intersect( $wanted, $available );
		if ( empty( $matches ) ) {
			return 0.0;
		}

		$coverage = count( $matches ) / max( 1, count( $wanted ) );
		$reasons[] = sprintf( '%s matched: %s', $label, implode( ', ', $matches ) );

		return round( $points * $coverage, 2 );
	}

	private function score_budget( float $budget, array $rules, array &$reasons ): float {
		if ( $budget <= 0 ) {
			return 0.0;
		}

		$min = isset( $rules['budget_min'] ) ? (float) $rules['budget_min'] : 0.0;
		$max = isset( $rules['budget_max'] ) ? (float) $rules['budget_max'] : 0.0;

		if ( $min <= 0 && $max <= 0 ) {
			return 0.0;
		}

		if ( $min > 0 && $budget < $min ) {
			return 0.0;
		}

		if ( $max > 0 && $budget > $max ) {
			return 5.0;
		}

		$reasons[] = 'Budget aligned';

		return 15.0;
	}

	private function score_keyword_fit( array $keywords, $available, array &$reasons ): float {
		$available = $this->normalize_keywords( $available );
		if ( empty( $keywords ) || empty( $available ) ) {
			return 0.0;
		}

		$matches = array_intersect( $keywords, $available );
		if ( empty( $matches ) ) {
			return 0.0;
		}

		$reasons[] = sprintf( 'Keyword overlap: %s', implode( ', ', $matches ) );

		return min( 10.0, count( $matches ) * 5.0 );
	}

	private function build_comparison_summary( array $provider ): array {
		$fields   = is_array( $provider['comparison_fields'] ?? null ) ? $provider['comparison_fields'] : array();
		$summary  = array();

		foreach ( array( 'deployment', 'pricing_model', 'support_model', 'implementation_time' ) as $key ) {
			if ( isset( $fields[ $key ] ) && '' !== (string) $fields[ $key ] ) {
				$summary[ $key ] = (string) $fields[ $key ];
			}
		}

		return $summary;
	}
}