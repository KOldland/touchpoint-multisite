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
		$score += $this->score_term_set( $criteria['regions'], $this->merge_term_sources( $rules['regions'] ?? array(), $provider['regions'] ?? array() ), 20, 'Regional fit', $reasons );
		$score += $this->score_company_size_fit( $criteria['company_sizes'], $rules['company_sizes'] ?? array(), $provider, $reasons );
		$score += $this->score_term_set( $criteria['deployment'], $this->merge_term_sources( $rules['deployment'] ?? array(), $provider['deployment_modes'] ?? array() ), 15, 'Deployment fit', $reasons );
		$score += $this->score_budget( $criteria['budget'], $rules, $reasons );
		$score += $this->score_typed_budget( $criteria['budget'], $provider, $reasons );
		$score += $this->score_keyword_fit( $criteria['keywords'], $rules['keywords'] ?? array(), $reasons );
		$score += $this->score_term_set( $criteria['sector'], $this->merge_term_sources( $rules['industries'] ?? array(), $provider['industries'] ?? array() ), 10, 'Sector fit', $reasons );
		$score += $this->score_integration_fit( $criteria['integrations'], $provider['integrations'] ?? array(), $reasons );

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
			'sector'        => $this->normalize_list( $criteria['sector'] ?? array() ),
			'integrations'  => $this->normalize_list( $criteria['integrations'] ?? array() ),
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

	private function merge_term_sources( $primary, $secondary ): array {
		return array_values( array_unique( array_merge( $this->normalize_list( $primary ), $this->normalize_list( $secondary ) ) ) );
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

	private function score_typed_budget( float $budget, array $provider, array &$reasons ): float {
		if ( $budget <= 0 ) {
			return 0.0;
		}

		$min = isset( $provider['budget_min'] ) ? (float) $provider['budget_min'] : 0.0;
		$max = isset( $provider['budget_max'] ) ? (float) $provider['budget_max'] : 0.0;

		if ( $min <= 0 && $max <= 0 ) {
			return 0.0;
		}

		if ( $min > 0 && $budget < $min ) {
			return 0.0;
		}

		if ( $max > 0 && $budget > $max ) {
			return 4.0;
		}

		$reasons[] = 'Typed budget range aligned';

		return 10.0;
	}

	private function score_company_size_fit( array $requested_sizes, $rule_sizes, array $provider, array &$reasons ): float {
		$score = $this->score_term_set( $requested_sizes, $rule_sizes, 15, 'Company-size fit', $reasons );
		if ( $score > 0 ) {
			return $score;
		}

		$provider_buckets = $this->company_size_buckets_for_provider( $provider );
		if ( empty( $requested_sizes ) || empty( $provider_buckets ) ) {
			return 0.0;
		}

		$matches = array_intersect( $requested_sizes, $provider_buckets );
		if ( empty( $matches ) ) {
			return 0.0;
		}

		$reasons[] = sprintf( 'Typed company-size fit: %s', implode( ', ', $matches ) );

		return round( 15 * ( count( $matches ) / max( 1, count( $requested_sizes ) ) ), 2 );
	}

	private function company_size_buckets_for_provider( array $provider ): array {
		$min = isset( $provider['company_size_min'] ) ? (int) $provider['company_size_min'] : 0;
		$max = isset( $provider['company_size_max'] ) ? (int) $provider['company_size_max'] : 0;

		if ( $min <= 0 && $max <= 0 ) {
			return array();
		}

		$range_max = $max > 0 ? $max : 9999999;
		$buckets   = array();

		$bands = array(
			'1-50'      => array( 1, 50 ),
			'51-250'    => array( 51, 250 ),
			'251-500'   => array( 251, 500 ),
			'501-1000'  => array( 501, 1000 ),
			'1001-2500' => array( 1001, 2500 ),
			'2501-5000' => array( 2501, 5000 ),
			'5000+'     => array( 5001, 9999999 ),
		);

		foreach ( $bands as $label => $range ) {
			if ( $min <= $range[1] && $range_max >= $range[0] ) {
				$buckets[] = $label;
			}
		}

		return $buckets;
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

	private function score_integration_fit( array $wanted, $available, array &$reasons ): float {
		$available = $this->normalize_list( $available );
		if ( empty( $wanted ) || empty( $available ) ) {
			return 0.0;
		}

		$matches = array_intersect( $wanted, $available );
		if ( empty( $matches ) ) {
			return 0.0;
		}

		$reasons[] = sprintf( 'Integration overlap: %s', implode( ', ', $matches ) );

		return min( 5.0, count( $matches ) * 2.5 );
	}

	private function build_comparison_summary( array $provider ): array {
		$fields   = is_array( $provider['comparison_fields'] ?? null ) ? $provider['comparison_fields'] : array();
		$summary  = array();

		foreach ( array( 'deployment', 'pricing_model', 'support_model', 'implementation_time' ) as $key ) {
			if ( isset( $fields[ $key ] ) && '' !== (string) $fields[ $key ] ) {
				$summary[ $key ] = (string) $fields[ $key ];
			}
		}

		if ( ! empty( $provider['provider_type'] ) ) {
			$summary['provider_type'] = (string) $provider['provider_type'];
		}

		if ( ! empty( $provider['deployment_modes'] ) ) {
			$summary['deployment_modes'] = implode( ', ', (array) $provider['deployment_modes'] );
		}

		if ( ! empty( $provider['support_tiers'] ) ) {
			$summary['support_tiers'] = implode( ', ', (array) $provider['support_tiers'] );
		}

		if ( ! empty( $provider['onboarding_days'] ) ) {
			$summary['onboarding_days'] = (int) $provider['onboarding_days'] . ' days';
		}

		return $summary;
	}
}