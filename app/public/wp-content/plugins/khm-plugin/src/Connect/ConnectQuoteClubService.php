<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

class ConnectQuoteClubService {

	private ConnectProviderRepository $providers;

	private ConnectShortlistService $shortlist_service;

	public function __construct( ?ConnectProviderRepository $providers = null, ?ConnectShortlistService $shortlist_service = null ) {
		$this->providers = $providers ?? new ConnectProviderRepository();
		$this->shortlist_service = $shortlist_service ?? new ConnectShortlistService();
	}

	public function match_for_session( array $session_context, string $title_context = '', int $limit = 3 ): array {
		$providers = array_values(
			array_filter(
				$this->providers->list_active( $title_context ),
				static function ( array $provider ): bool {
					return ! empty( $provider['commentary_enabled'] );
				}
			)
		);

		$criteria = $this->build_criteria_from_session( $session_context );
		$matches  = $this->shortlist_service->shortlist( $providers, $criteria, $title_context, max( 1, $limit ) );

		return array_map( array( $this, 'summarize_provider' ), $matches );
	}

	public function get_provider_snapshot( int $provider_id, string $title_context = '' ): ?array {
		$provider = $this->providers->get_by_id( $provider_id );
		if ( ! is_array( $provider ) || empty( $provider['commentary_enabled'] ) ) {
			return null;
		}

		if ( '' !== $title_context && ! empty( $provider['titles'] ) && ! in_array( $this->normalize_slug( $title_context ), $provider['titles'], true ) ) {
			return null;
		}

		return $this->summarize_provider( $provider );
	}

	private function build_criteria_from_session( array $session_context ): array {
		$topics = isset( $session_context['topics'] ) && is_array( $session_context['topics'] ) ? $session_context['topics'] : array();
		$portfolio = (string) ( $session_context['portfolio'] ?? '' );
		$title = (string) ( $session_context['title'] ?? '' );
		$key_messages = (string) ( $session_context['key_messages'] ?? '' );

		$keywords = array_merge(
			$this->tokenize_keywords( $title ),
			$this->tokenize_keywords( $key_messages ),
			$this->tokenize_keywords( $portfolio )
		);

		return array(
			'industries' => $topics,
			'keywords' => array_values( array_unique( $keywords ) ),
		);
	}

	private function tokenize_keywords( string $value ): array {
		$value = strtolower( trim( $value ) );
		if ( '' === $value ) {
			return array();
		}

		$parts = preg_split( '/[^a-z0-9]+/', $value );

		return array_values(
			array_filter(
				array_map(
					static function ( $part ): string {
						return trim( (string) $part );
					},
					(array) $parts
				),
				static function ( string $part ): bool {
					return strlen( $part ) >= 3;
				}
			)
		);
	}

	private function summarize_provider( array $provider ): array {
		return array(
			'id' => (int) ( $provider['id'] ?? 0 ),
			'provider_id' => (int) ( $provider['id'] ?? 0 ),
			'name' => (string) ( $provider['name'] ?? '' ),
			'slug' => (string) ( $provider['slug'] ?? '' ),
			'description' => (string) ( $provider['description'] ?? '' ),
			'website_url' => (string) ( $provider['website_url'] ?? '' ),
			'website' => (string) ( $provider['website_url'] ?? '' ),
			'sponsor_id' => (int) ( $provider['sponsor_id'] ?? 0 ),
			'provider_type' => (string) ( $provider['provider_type'] ?? '' ),
			'sweet_spot_summary' => (string) ( $provider['sweet_spot_summary'] ?? '' ),
			'regions' => array_values( (array) ( $provider['regions'] ?? array() ) ),
			'deployment_modes' => array_values( (array) ( $provider['deployment_modes'] ?? array() ) ),
			'support_tiers' => array_values( (array) ( $provider['support_tiers'] ?? array() ) ),
			'company_size_min' => isset( $provider['company_size_min'] ) ? (int) $provider['company_size_min'] : null,
			'company_size_max' => isset( $provider['company_size_max'] ) ? (int) $provider['company_size_max'] : null,
			'budget_min' => isset( $provider['budget_min'] ) ? (int) $provider['budget_min'] : null,
			'budget_max' => isset( $provider['budget_max'] ) ? (int) $provider['budget_max'] : null,
			'onboarding_days' => isset( $provider['onboarding_days'] ) ? (int) $provider['onboarding_days'] : null,
			'comparison_summary' => is_array( $provider['comparison_summary'] ?? null ) ? $provider['comparison_summary'] : array(),
			'match_reasons' => is_array( $provider['match_reasons'] ?? null ) ? $provider['match_reasons'] : array(),
			'score' => isset( $provider['score'] ) ? (float) $provider['score'] : 0.0,
		);
	}

	private function normalize_slug( string $value ): string {
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( (string) $value, '-' );
	}
}