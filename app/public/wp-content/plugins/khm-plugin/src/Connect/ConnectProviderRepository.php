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
			"SELECT * FROM {$table} WHERE status = %s AND blog_id IN (0, %d) AND is_demo = 0 ORDER BY name ASC",
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

	/**
	 * Filtered provider listing for the buyer-facing directory.
	 *
	 * Accepts optional filters:
	 *   blog_ids      – null = all sites; [] = no results; [2,5] = those blogs + portfolio (blog_id = 0)
	 *   provider_type – match provider_type column exactly
	 *   pilot_available – boolean; if true, only pilot_scheme_available = 1
	 *   free_trial    – boolean; if true, only free_trial_available = 1
	 *   budget_min    – buyer's minimum budget; provider's budget_max must be >= this
	 *   budget_max    – buyer's maximum budget; provider's budget_min must be <= this
	 *   company_size  – buyer's headcount; must fall within provider's company_size_min/max
	 *   deployment_mode – filtered in PHP (JSON column search)
	 *
	 * @param array<string,mixed> $filters
	 * @return array[]
	 */
	public function list_filtered( array $filters = [] ): array {
		global $wpdb;

		$table  = $wpdb->prefix . 'connect_providers';
		$where  = [ "status = 'active'", 'is_demo = 0' ];
		$params = [];

		// Blog / expertise filter
		$blog_ids = $filters['blog_ids'] ?? null; // null = no filter (all sites)
		if ( $blog_ids !== null ) {
			if ( empty( $blog_ids ) ) {
				// Filter requested but resolved to nothing — return empty set
				return [];
			}
			// Always include portfolio (blog_id = 0) alongside the requested site blog_ids
			$ids          = array_values( array_unique( array_merge( [ 0 ], array_map( 'intval', $blog_ids ) ) ) );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$where[]      = "blog_id IN ({$placeholders})";
			$params       = array_merge( $params, $ids );
		}

		if ( ! empty( $filters['provider_type'] ) ) {
			$where[]  = 'provider_type = %s';
			$params[] = sanitize_key( (string) $filters['provider_type'] );
		}

		if ( ! empty( $filters['pilot_available'] ) ) {
			$where[] = 'pilot_scheme_available = 1';
		}

		if ( ! empty( $filters['free_trial'] ) ) {
			$where[] = 'free_trial_available = 1';
		}

		// Budget overlap: provider must overlap the buyer's budget range
		if ( isset( $filters['budget_min'] ) && is_numeric( $filters['budget_min'] ) ) {
			$where[]  = '(budget_max IS NULL OR budget_max >= %d)';
			$params[] = (int) $filters['budget_min'];
		}
		if ( isset( $filters['budget_max'] ) && is_numeric( $filters['budget_max'] ) ) {
			$where[]  = '(budget_min IS NULL OR budget_min <= %d)';
			$params[] = (int) $filters['budget_max'];
		}

		// Company size must fall within provider's range
		if ( isset( $filters['company_size'] ) && is_numeric( $filters['company_size'] ) ) {
			$size     = (int) $filters['company_size'];
			$where[]  = '(company_size_min IS NULL OR company_size_min <= %d)';
			$where[]  = '(company_size_max IS NULL OR company_size_max >= %d)';
			$params[] = $size;
			$params[] = $size;
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY name ASC";

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$providers = array_map( [ $this, 'hydrate_row' ], $rows );

		// PHP-level deployment_mode filter (stored as JSON array)
		if ( ! empty( $filters['deployment_mode'] ) ) {
			$mode      = sanitize_key( (string) $filters['deployment_mode'] );
			$providers = array_values( array_filter(
				$providers,
				static fn( array $p ) => in_array( $mode, $p['deployment_modes'] ?? [], true )
			) );
		}

		return $providers;
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

	public function list_for_sponsor( int $sponsor_id ): array {
		if ( $sponsor_id <= 0 ) {
			return array();
		}

		global $wpdb;

		$table   = $wpdb->prefix . 'connect_providers';
		$blog_id = $this->current_blog_id();
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE sponsor_id = %d AND blog_id IN (0, %d) ORDER BY updated_at DESC, id DESC LIMIT 100",
				$sponsor_id,
				$blog_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'hydrate_row' ), $rows );
	}

	/**
	 * Return one provider row per network site (blog_id > 1) where this sponsor is active.
	 * Used for the subscription sites list in the portal.
	 */
	public function list_sites_for_sponsor( int $sponsor_id ): array {
		if ( $sponsor_id <= 0 ) {
			return array();
		}

		global $wpdb;

		$table = $wpdb->prefix . 'connect_providers';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, b.domain, b.path
				 FROM {$table} p
				 INNER JOIN {$wpdb->blogs} b ON b.blog_id = p.blog_id
				 WHERE p.sponsor_id = %d
				   AND p.status = %s
				   AND p.blog_id > 1
				 ORDER BY b.path ASC",
				$sponsor_id,
				'active'
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( function( $row ) {
			$hydrated = $this->hydrate_row( $row );
			$hydrated['site_domain'] = $row['domain'] ?? '';
			$hydrated['site_path']   = $row['path'] ?? '';
			return $hydrated;
		}, $rows );
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

		$company_size_min = $this->normalize_nullable_int( $data['company_size_min'] ?? null );
		$company_size_max = $this->normalize_nullable_int( $data['company_size_max'] ?? null );
		$budget_min       = $this->normalize_nullable_int( $data['budget_min'] ?? null );
		$budget_max       = $this->normalize_nullable_int( $data['budget_max'] ?? null );
		$onboarding_days  = $this->normalize_nullable_int( $data['onboarding_days'] ?? null );

		$trustpilot_rating = null;
		if ( isset( $data['trustpilot_rating'] ) && is_numeric( $data['trustpilot_rating'] ) ) {
			$rating = (float) $data['trustpilot_rating'];
			if ( $rating >= 0.0 && $rating <= 5.0 ) {
				$trustpilot_rating = round( $rating, 1 );
			}
		}

		$valid_bands = [ '1-50', '51-250', '251-500', '501-1000', '1001-2500', '2501-5000', '5000+' ];
		$client_count_band = null;
		if ( isset( $data['client_count_band'] ) && in_array( $data['client_count_band'], $valid_bands, true ) ) {
			$client_count_band = $data['client_count_band'];
		}

		return array(
			'blog_id'                => isset( $data['blog_id'] ) ? (int) $data['blog_id'] : ( isset( $existing['blog_id'] ) ? (int) $existing['blog_id'] : $this->current_blog_id() ),
			'sponsor_id'             => isset( $data['sponsor_id'] ) ? absint( $data['sponsor_id'] ) : null,
			'name'                   => $name,
			'slug'                   => $slug,
			'description'            => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
			'website_url'            => $this->normalize_url( (string) ( $data['website_url'] ?? '' ) ),
			'hq_location'            => isset( $data['hq_location'] ) ? sanitize_text_field( (string) $data['hq_location'] ) : null,
			'provider_type'          => sanitize_key( (string) ( $data['provider_type'] ?? '' ) ),
			'sweet_spot_summary'     => sanitize_textarea_field( (string) ( $data['sweet_spot_summary'] ?? '' ) ),
			'company_size_min'       => $company_size_min,
			'company_size_max'       => $company_size_max,
			'budget_min'             => $budget_min,
			'budget_max'             => $budget_max,
			'onboarding_days'        => $onboarding_days,
			'regions'                => wp_json_encode( $this->normalize_titles( $data['regions'] ?? array() ) ),
			'deployment_modes'       => wp_json_encode( $this->normalize_titles( $data['deployment_modes'] ?? array() ) ),
			'support_tiers'          => wp_json_encode( $this->normalize_titles( $data['support_tiers'] ?? array() ) ),
			'pilot_scheme_available' => ! empty( $data['pilot_scheme_available'] ) ? 1 : 0,
			'free_trial_available'   => ! empty( $data['free_trial_available'] ) ? 1 : 0,
			'trustpilot_rating'      => $trustpilot_rating,
			'client_count_band'      => $client_count_band,
			'integrations'           => wp_json_encode( $this->normalize_titles( $data['integrations'] ?? array() ) ),
			'status'                 => $status,
			'commentary_enabled'     => ! empty( $data['commentary_enabled'] ) ? 1 : 0,
			'ad_targeting_enabled'   => ! empty( $data['ad_targeting_enabled'] ) ? 1 : 0,
			'is_demo'                => ! empty( $data['is_demo'] ) ? 1 : 0,
			'titles'                 => wp_json_encode( $this->normalize_titles( $data['titles'] ?? array() ) ),
			'comparison_fields'      => wp_json_encode( $this->normalize_json_map( $data['comparison_fields'] ?? array() ) ),
			'match_rules'            => wp_json_encode( $this->normalize_json_map( $data['match_rules'] ?? array() ) ),
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

	private function normalize_nullable_int( $value ): ?int {
		if ( '' === $value || null === $value ) {
			return null;
		}

		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( '' === $value ) {
				return null;
			}
		}

		$normalized = is_numeric( $value ) ? (int) $value : null;

		if ( null === $normalized ) {
			return null;
		}

		return max( 0, $normalized );
	}

	private function hydrate_row( array $row ): array {
		return array(
			'id'                     => (int) ( $row['id'] ?? 0 ),
			'blog_id'                => (int) ( $row['blog_id'] ?? 1 ),
			'sponsor_id'             => isset( $row['sponsor_id'] ) ? (int) $row['sponsor_id'] : 0,
			'name'                   => (string) ( $row['name'] ?? '' ),
			'slug'                   => (string) ( $row['slug'] ?? '' ),
			'description'            => (string) ( $row['description'] ?? '' ),
			'website_url'            => (string) ( $row['website_url'] ?? '' ),
			'hq_location'            => isset( $row['hq_location'] ) ? (string) $row['hq_location'] : null,
			'provider_type'          => (string) ( $row['provider_type'] ?? '' ),
			'sweet_spot_summary'     => (string) ( $row['sweet_spot_summary'] ?? '' ),
			'company_size_min'       => isset( $row['company_size_min'] ) ? (int) $row['company_size_min'] : null,
			'company_size_max'       => isset( $row['company_size_max'] ) ? (int) $row['company_size_max'] : null,
			'budget_min'             => isset( $row['budget_min'] ) ? (int) $row['budget_min'] : null,
			'budget_max'             => isset( $row['budget_max'] ) ? (int) $row['budget_max'] : null,
			'onboarding_days'        => isset( $row['onboarding_days'] ) ? (int) $row['onboarding_days'] : null,
			'regions'                => $this->decode_json_array( $row['regions'] ?? '' ),
			'deployment_modes'       => $this->decode_json_array( $row['deployment_modes'] ?? '' ),
			'support_tiers'          => $this->decode_json_array( $row['support_tiers'] ?? '' ),
			'pilot_scheme_available' => ! empty( $row['pilot_scheme_available'] ),
			'free_trial_available'   => ! empty( $row['free_trial_available'] ),
			'trustpilot_rating'      => isset( $row['trustpilot_rating'] ) ? (float) $row['trustpilot_rating'] : null,
			'client_count_band'      => isset( $row['client_count_band'] ) ? (string) $row['client_count_band'] : null,
			'integrations'           => $this->decode_json_array( $row['integrations'] ?? '' ),
			'status'                 => (string) ( $row['status'] ?? 'inactive' ),
			'commentary_enabled'     => ! empty( $row['commentary_enabled'] ),
			'ad_targeting_enabled'   => ! empty( $row['ad_targeting_enabled'] ),
			'is_demo'                => ! empty( $row['is_demo'] ),
			'titles'                 => $this->decode_json_array( $row['titles'] ?? '' ),
			'comparison_fields'      => $this->decode_json_map( $row['comparison_fields'] ?? '' ),
			'match_rules'            => $this->decode_json_map( $row['match_rules'] ?? '' ),
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