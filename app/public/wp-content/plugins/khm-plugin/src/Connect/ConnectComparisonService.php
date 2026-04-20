<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

class ConnectComparisonService {

	const MIN_PROVIDERS = 2;

	const MAX_PROVIDERS = 5;

	private const DEFAULT_FIELDS = array(
		'deployment',
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
				$value = $this->normalize_field_value( $provider['comparison_fields'][ $field_key ] ?? '' );
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
			'name' => (string) ( $provider['name'] ?? '' ),
			'slug' => (string) ( $provider['slug'] ?? '' ),
			'description' => (string) ( $provider['description'] ?? '' ),
			'website_url' => (string) ( $provider['website_url'] ?? '' ),
			'commentary_enabled' => ! empty( $provider['commentary_enabled'] ),
			'ad_targeting_enabled' => ! empty( $provider['ad_targeting_enabled'] ),
		);
	}

	private function normalize_field_value( $value ): string {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( array_map( 'sanitize_text_field', $value ) ) );
		}

		return trim( sanitize_text_field( (string) $value ) );
	}

	private function humanize_key( string $key ): string {
		$key = str_replace( array( '-', '_' ), ' ', $key );

		return ucwords( trim( $key ) );
	}
}