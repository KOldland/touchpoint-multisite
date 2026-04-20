<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

class ConnectComparisonService {

	const MIN_PROVIDERS = 2;

	const MAX_PROVIDERS = 5;

	private const DEFAULT_FIELDS = array(
		'provider_type',
		'deployment',
		'deployment_modes',
		'support_tiers',
		'onboarding_days',
		'budget_range',
		'company_size_range',
		'sweet_spot_summary',
		'pricing_model',
		'support_model',
		'implementation_time',
		'contract_term',
		'integrations',
	);

	public function build_matrix( array $providers, array $requested_fields = array() ): array {
		$providers = array_values( $providers );
		$field_keys = $this->resolve_field_keys( $providers, $requested_fields );
		$rows = array();

		foreach ( $field_keys as $field_key ) {
			$row = array(
				'key' => $field_key,
				'label' => $this->humanize_key( $field_key ),
				'values' => array(),
			);

			$has_value = false;
			foreach ( $providers as $provider ) {
				$value = $this->resolve_provider_field_value( $provider, $field_key );
				if ( '' !== $value ) {
					$has_value = true;
				}

				$row['values'][] = array(
					'provider_id' => (int) ( $provider['id'] ?? 0 ),
					'provider_slug' => (string) ( $provider['slug'] ?? '' ),
					'value' => $value,
				);
			}

			if ( $has_value ) {
				$rows[] = $row;
			}
		}

		return array(
			'provider_count' => count( $providers ),
			'providers' => array_map( array( $this, 'summarize_provider' ), $providers ),
			'rows' => $rows,
		);
	}

	public function validate_provider_count( array $provider_ids ): bool {
		$count = count( array_unique( array_map( 'intval', $provider_ids ) ) );

		return $count >= self::MIN_PROVIDERS && $count <= self::MAX_PROVIDERS;
	}

	private function resolve_field_keys( array $providers, array $requested_fields ): array {
		if ( ! empty( $requested_fields ) ) {
			$requested_fields = array_values(
				array_filter(
					array_unique(
						array_map( 'sanitize_key', $requested_fields )
					)
				)
			);

			if ( ! empty( $requested_fields ) ) {
				return $requested_fields;
			}
		}

		$available = array();
		foreach ( $providers as $provider ) {
			$fields = is_array( $provider['comparison_fields'] ?? null ) ? $provider['comparison_fields'] : array();
			foreach ( array_keys( $fields ) as $key ) {
				$key = sanitize_key( (string) $key );
				if ( '' !== $key ) {
					$available[ $key ] = true;
				}
			}

			foreach ( self::DEFAULT_FIELDS as $default_key ) {
				if ( '' !== $this->resolve_provider_field_value( $provider, $default_key ) ) {
					$available[ $default_key ] = true;
				}
			}
		}

		$ordered = array();
		foreach ( self::DEFAULT_FIELDS as $field_key ) {
			if ( isset( $available[ $field_key ] ) ) {
				$ordered[] = $field_key;
				unset( $available[ $field_key ] );
			}
		}

		$remaining = array_keys( $available );
		sort( $remaining );

		return array_merge( $ordered, $remaining );
	}

	private function summarize_provider( array $provider ): array {
		return array(
			'id' => (int) ( $provider['id'] ?? 0 ),
			'provider_id' => (int) ( $provider['id'] ?? 0 ),
			'name' => (string) ( $provider['name'] ?? '' ),
			'slug' => (string) ( $provider['slug'] ?? '' ),
			'description' => (string) ( $provider['description'] ?? '' ),
			'website_url' => (string) ( $provider['website_url'] ?? '' ),
			'provider_type' => (string) ( $provider['provider_type'] ?? '' ),
			'commentary_enabled' => ! empty( $provider['commentary_enabled'] ),
			'ad_targeting_enabled' => ! empty( $provider['ad_targeting_enabled'] ),
		);
	}

	private function resolve_provider_field_value( array $provider, string $field_key ): string {
		$field_key = sanitize_key( $field_key );
		$fields    = is_array( $provider['comparison_fields'] ?? null ) ? $provider['comparison_fields'] : array();

		if ( isset( $fields[ $field_key ] ) ) {
			$value = $this->normalize_field_value( $fields[ $field_key ] );
			if ( '' !== $value ) {
				return $value;
			}
		}

		switch ( $field_key ) {
			case 'provider_type':
				return $this->normalize_field_value( $provider['provider_type'] ?? '' );
			case 'sweet_spot_summary':
			case 'fit_notes':
				return $this->normalize_field_value( $provider['sweet_spot_summary'] ?? '' );
			case 'regions':
				return $this->normalize_field_value( $provider['regions'] ?? array() );
			case 'deployment':
			case 'deployment_modes':
				return $this->normalize_field_value( $provider['deployment_modes'] ?? array() );
			case 'support_model':
			case 'support_tiers':
				return $this->normalize_field_value( $provider['support_tiers'] ?? array() );
			case 'implementation_time':
			case 'onboarding':
			case 'onboarding_days':
				return ! empty( $provider['onboarding_days'] ) ? (int) $provider['onboarding_days'] . ' days' : '';
			case 'budget_range':
				return $this->format_range( $provider['budget_min'] ?? null, $provider['budget_max'] ?? null, '$' );
			case 'company_size_range':
				return $this->format_range( $provider['company_size_min'] ?? null, $provider['company_size_max'] ?? null, '' );
			case 'overview':
				return $this->normalize_field_value( $provider['description'] ?? '' );
		}

		return '';
	}

	private function normalize_field_value( $value ): string {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( array_map( 'sanitize_text_field', $value ) ) );
		}

		return trim( sanitize_text_field( (string) $value ) );
	}

	private function format_range( $minValue, $maxValue, string $prefix ): string {
		$min = is_numeric( $minValue ) ? (int) $minValue : 0;
		$max = is_numeric( $maxValue ) ? (int) $maxValue : 0;

		if ( $min <= 0 && $max <= 0 ) {
			return '';
		}

		if ( $min > 0 && $max > 0 ) {
			return sprintf( '%s%d - %s%d', $prefix, $min, $prefix, $max );
		}

		if ( $min > 0 ) {
			return sprintf( '%s%d+', $prefix, $min );
		}

		return sprintf( 'Up to %s%d', $prefix, $max );
	}

	private function humanize_key( string $key ): string {
		$key = str_replace( array( '-', '_' ), ' ', $key );

		return ucwords( trim( $key ) );
	}
}