<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

class ConnectProviderRepository {

	public function get_by_id( int $provider_id ): ?array {
		global $wpdb;

		$table   = $wpdb->prefix . 'connect_providers';
		$blog_id = $this->current_blog_id();
		$query   = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND blog_id IN (0, %d)", $provider_id, $blog_id );
		$row   = $wpdb->get_row( $query, ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		$row_blog_id = isset( $row['blog_id'] ) ? (int) $row['blog_id'] : 1;
		if ( ! in_array( $row_blog_id, array( 0, $blog_id ), true ) ) {
			return null;
		}

		return $this->hydrate_row( $row );
	}

	public function list_active( string $title_context = '' ): array {
		global $wpdb;

		$table   = $wpdb->prefix . 'connect_providers';
		$blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;

		$query = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = %s AND blog_id IN (0, %d) ORDER BY name ASC",
			'active',
			$blog_id
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$providers = array_map( array( $this, 'hydrate_row' ), $rows );

		if ( '' === $title_context ) {
			return $providers;
		}

		$title_context = $this->normalize_slug( $title_context );

		return array_values(
			array_filter(
				$providers,
				static function ( array $provider ) use ( $title_context ): bool {
					if ( empty( $provider['titles'] ) ) {
						return true;
					}

					return in_array( $title_context, $provider['titles'], true );
				}
			)
		);
	}

	public function list_all(): array {
		global $wpdb;

		$table   = $wpdb->prefix . 'connect_providers';
		$blog_id = $this->current_blog_id();
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE blog_id IN (0, %d) ORDER BY updated_at DESC LIMIT 500",
				$blog_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$allowed_rows = array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $blog_id ): bool {
					$row_blog_id = isset( $row['blog_id'] ) ? (int) $row['blog_id'] : 1;

					return in_array( $row_blog_id, array( 0, $blog_id ), true );
				}
			)
		);

		return array_map( array( $this, 'hydrate_row' ), $allowed_rows );
	}

	public function save( array $data ): int {
		global $wpdb;

		$table      = $wpdb->prefix . 'connect_providers';
		$provider_id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		if ( $provider_id > 0 ) {
			$existing = $this->get_by_id( $provider_id );
			if ( ! is_array( $existing ) ) {
				return 0;
			}

			$payload = $this->normalize_save_payload( $data, $existing );
			$wpdb->update( $table, $payload, array( 'id' => $provider_id, 'blog_id' => (int) $existing['blog_id'] ) );
			return $provider_id;
		}

		$payload = $this->normalize_save_payload( $data );

		$wpdb->insert( $table, $payload );

		return (int) $wpdb->insert_id;
	}

	public function delete( int $provider_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'connect_providers';
		$existing = $this->get_by_id( $provider_id );
		if ( ! is_array( $existing ) ) {
			return false;
		}

		return false !== $wpdb->delete( $table, array( 'id' => $provider_id, 'blog_id' => (int) $existing['blog_id'] ) );
	}

	private function normalize_save_payload( array $data, ?array $existing = null ): array {
		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		$slug = $this->normalize_slug( (string) ( $data['slug'] ?? $name ) );
		$status = (string) ( $data['status'] ?? 'active' );
		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			$status = 'active';
		}

		return array(
			'blog_id'              => isset( $data['blog_id'] ) ? (int) $data['blog_id'] : ( isset( $existing['blog_id'] ) ? (int) $existing['blog_id'] : $this->current_blog_id() ),
			'sponsor_id'           => isset( $data['sponsor_id'] ) ? absint( $data['sponsor_id'] ) : null,
			'name'                 => $name,
			'slug'                 => $slug,
			'description'          => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
			'website_url'          => $this->normalize_url( (string) ( $data['website_url'] ?? '' ) ),
			'status'               => $status,
			'commentary_enabled'   => ! empty( $data['commentary_enabled'] ) ? 1 : 0,
			'ad_targeting_enabled' => ! empty( $data['ad_targeting_enabled'] ) ? 1 : 0,
			'titles'               => wp_json_encode( $this->normalize_titles( $data['titles'] ?? array() ) ),
			'comparison_fields'    => wp_json_encode( $this->normalize_json_map( $data['comparison_fields'] ?? array() ) ),
			'match_rules'          => wp_json_encode( $this->normalize_json_map( $data['match_rules'] ?? array() ) ),
		);
	}

	private function normalize_titles( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[,\n|]/', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_unique(
					array_map( array( $this, 'normalize_slug' ), $value )
				)
			)
		);
	}

	private function normalize_json_map( $value ): array {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				$value = $decoded;
			} else {
				$value = array();
			}
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $value as $key => $item ) {
			$normalized_key = sanitize_key( (string) $key );
			if ( '' === $normalized_key ) {
				continue;
			}

			if ( is_array( $item ) ) {
				$normalized[ $normalized_key ] = array_values(
					array_filter(
						array_map(
							static function ( $nested_item ): string {
								return sanitize_text_field( (string) $nested_item );
							},
							$item
						)
					)
				);
				continue;
			}

			$normalized[ $normalized_key ] = sanitize_text_field( (string) $item );
		}

		return $normalized;
	}

	private function current_blog_id(): int {
		$current_blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;

		return max( 1, (int) apply_filters( 'khm_connect_current_blog_id', $current_blog_id ) );
	}

	private function normalize_url( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$sanitized = filter_var( $value, FILTER_SANITIZE_URL );

		return is_string( $sanitized ) ? $sanitized : '';
	}

	private function hydrate_row( array $row ): array {
		return array(
			'id'                   => (int) ( $row['id'] ?? 0 ),
			'blog_id'              => (int) ( $row['blog_id'] ?? 1 ),
			'sponsor_id'           => isset( $row['sponsor_id'] ) ? (int) $row['sponsor_id'] : 0,
			'name'                 => (string) ( $row['name'] ?? '' ),
			'slug'                 => (string) ( $row['slug'] ?? '' ),
			'description'          => (string) ( $row['description'] ?? '' ),
			'website_url'          => (string) ( $row['website_url'] ?? '' ),
			'status'               => (string) ( $row['status'] ?? 'inactive' ),
			'commentary_enabled'   => ! empty( $row['commentary_enabled'] ),
			'ad_targeting_enabled' => ! empty( $row['ad_targeting_enabled'] ),
			'titles'               => $this->decode_json_array( $row['titles'] ?? '' ),
			'comparison_fields'    => $this->decode_json_map( $row['comparison_fields'] ?? '' ),
			'match_rules'          => $this->decode_json_map( $row['match_rules'] ?? '' ),
		);
	}

	private function decode_json_array( $value ): array {
		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $item ): string {
						return self::normalize_slug( (string) $item );
					},
					$decoded
				)
			)
		);
	}

	private static function normalize_slug( string $value ): string {
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( (string) $value, '-' );
	}

	private function decode_json_map( $value ): array {
		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );

		return is_array( $decoded ) ? $decoded : array();
	}
}