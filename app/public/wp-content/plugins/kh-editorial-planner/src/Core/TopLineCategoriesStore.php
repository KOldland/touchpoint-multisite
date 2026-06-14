<?php
/**
 * Top-Line Categories Store
 *
 * Persists top-line categories to the `dual_gpt_top_line_categories`
 * WordPress option (matching the legacy key for backward compatibility).
 *
 * Each category is a structured array with name, slug, research policy,
 * personas, sponsors, competitors, journals, etc.
 *
 * @package KH\Planner\Core
 * @since 0.3.0
 */

namespace KH\Planner\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TopLineCategoriesStore {

	const OPTION_KEY = 'dual_gpt_top_line_categories';

	/**
	 * Get all top-line categories.
	 *
	 * @return array
	 */
	public function get_all(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		return $stored;
	}

	/**
	 * Get a single category by slug.
	 *
	 * @param string $slug Category slug.
	 * @return array|null
	 */
	public function get_by_slug( string $slug ): ?array {
		$all = $this->get_all();
		foreach ( $all as $category ) {
			if ( ( $category['slug'] ?? '' ) === $slug ) {
				return $category;
			}
		}
		return null;
	}

	/**
	 * Save or update a category.
	 *
	 * Uses slug as the unique key. If a category with the same slug exists,
	 * it is replaced. Otherwise, appended.
	 *
	 * @param array $category Category data.
	 * @return bool
	 */
	public function save( array $category ): bool {
		$slug = sanitize_title( $category['slug'] ?? $category['name'] ?? '' );
		if ( empty( $slug ) ) {
			return false;
		}

		$category['slug'] = $slug;

		$all        = $this->get_all();
		$found      = false;
		$normalized = $this->normalize_category( $category );

		foreach ( $all as $i => $existing ) {
			if ( ( $existing['slug'] ?? '' ) === $slug ) {
				$all[ $i ] = $normalized;
				$found     = true;
				break;
			}
		}

		if ( ! $found ) {
			$all[] = $normalized;
		}

		return update_option( self::OPTION_KEY, $all, false );
	}

	/**
	 * Delete a category by slug.
	 *
	 * @param string $slug Category slug.
	 * @return bool
	 */
	public function delete( string $slug ): bool {
		$all = $this->get_all();
		$filtered = array_values( array_filter( $all, function ( $c ) use ( $slug ) {
			return ( $c['slug'] ?? '' ) !== $slug;
		} ) );
		return update_option( self::OPTION_KEY, $filtered, false );
	}

	/**
	 * Bulk import categories from an array of rows.
	 *
	 * Each row is either a full category array or a flat CSV-derived array
	 * with keys like: Brand Title, Pref. Domain, Core Content Channel, etc.
	 *
	 * @param array $rows Raw rows to import.
	 * @return array { created_or_updated: int, skipped: int }
	 */
	public function import_rows( array $rows ): array {
		$created_or_updated = 0;
		$skipped            = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row ) ) {
				$skipped++;
				continue;
			}

			$category = $this->normalize_import_row( $row );
			if ( empty( $category['name'] ) || empty( $category['slug'] ) ) {
				$skipped++;
				continue;
			}

			if ( $this->save( $category ) ) {
				$created_or_updated++;
			} else {
				$skipped++;
			}
		}

		return array(
			'created_or_updated' => $created_or_updated,
			'skipped'            => $skipped,
		);
	}

	/**
	 * Import CSV text, parsing headers and rows.
	 *
	 * @param string $csv Raw CSV text.
	 * @return array { created_or_updated: int, skipped: int }
	 */
	public function import_csv( string $csv ): array {
		$lines = explode( "\n", trim( $csv ) );
		if ( count( $lines ) < 2 ) {
			return array( 'created_or_updated' => 0, 'skipped' => 0 );
		}

		$headers = str_getcsv( array_shift( $lines ) );
		$headers = array_map( 'trim', $headers );
		$rows    = array();

		foreach ( $lines as $line ) {
			$values = str_getcsv( $line );
			if ( count( $values ) < 1 ) {
				continue;
			}
			$row = array();
			foreach ( $headers as $i => $header ) {
				$row[ $header ] = $values[ $i ] ?? '';
			}
			$rows[] = $row;
		}

		return $this->import_rows( $rows );
	}

	/**
	 * Seed default categories if the store is empty.
	 *
	 * Reads from the legacy backup at archive_2026/08_data_backups/dual_gpt_categories_backup.json
	 * if it exists, otherwise uses built-in defaults.
	 *
	 * @return bool True if seeded, false if already had data.
	 */
	public function seed_if_empty(): bool {
		$existing = $this->get_all();
		if ( ! empty( $existing ) ) {
			return false;
		}

		// Try to load from backup
		$backup_path = WP_CONTENT_DIR . '/../../../../archive_2026/08_data_backups/dual_gpt_categories_backup.json';
		if ( file_exists( $backup_path ) ) {
			$json = file_get_contents( $backup_path );
			if ( $json ) {
				$categories = json_decode( $json, true );
				if ( is_array( $categories ) && ! empty( $categories ) ) {
					update_option( self::OPTION_KEY, $categories, false );
					return true;
				}
			}
		}

		// Built-in fallback defaults
		$defaults = array(
			array(
				'slug'         => 'manufacturing',
				'name'         => 'Manufacturing',
				'category_type'=> '',
				'pref_domain'  => '',
				'core_content_channel' => '',
				'target_personas' => array(),
				'target_sponsors' => array(),
				'key_competitors' => array(),
				'trade_associations' => array(),
				'academic_journals' => array(),
				'acronyms'         => array(),
				'cultural_lexicon' => array(),
				'key_speakers'     => array(),
				'subgroups'        => array(),
				'research_policy'  => array(
					'priority_domains'    => array( 'mckinsey.com', 'bain.com', 'gartner.com', 'idc.com' ),
					'blocked_domains'     => array( 'wikipedia.org', 'pinterest.com', 'reddit.com', 'quora.com' ),
					'blocked_keywords'    => array( 'chatgpt', 'gemini', 'claude', 'ai-generated', 'synthetic study' ),
					'preferred_sources'   => array(),
					'source_mix_minimums' => array(
						'academic'   => 1,
						'analyst'    => 1,
						'industry'   => 1,
						'case_study' => 1,
					),
					'recency_months'           => 36,
					'max_citations_per_org'    => 2,
					'min_priority_domains_hit' => 1,
				),
			),
			array(
				'slug'         => 'field-service',
				'name'         => 'Field Service',
				'category_type'=> '',
				'pref_domain'  => '',
				'core_content_channel' => '',
				'target_personas'      => array(),
				'target_sponsors'      => array(),
				'key_competitors'      => array(),
				'trade_associations'   => array(),
				'academic_journals'    => array(),
				'acronyms'             => array(),
				'cultural_lexicon'     => array(),
				'key_speakers'         => array(),
				'subgroups'            => array(),
				'research_policy'      => array(
					'priority_domains'    => array( 'mckinsey.com', 'bain.com', 'bcg.com', 'gartner.com', 'forrester.com', 'idc.com', 'fieldservicenews.com', 'servicecouncil.com', 'tsia.com', 'hbr.org', 'sloanreview.mit.edu' ),
					'blocked_domains'     => array( 'wikipedia.org', 'pinterest.com', 'reddit.com', 'quora.com' ),
					'blocked_keywords'    => array( 'chatgpt', 'gemini', 'claude', 'ai-generated', 'synthetic study' ),
					'preferred_sources'   => array( 'Journal of Service Management', 'International Journal of Operations & Production Management', 'Field Service Management', 'Service Industries Journal', 'Production and Operations Management', 'Gartner Magic Quadrant for Field Service Management', 'TSIA State of Field Services' ),
					'source_mix_minimums' => array(
						'academic'   => 2,
						'analyst'    => 2,
						'industry'   => 2,
						'case_study' => 2,
					),
					'recency_months'           => 36,
					'max_citations_per_org'    => 2,
					'min_priority_domains_hit' => 3,
				),
			),
		);

		update_option( self::OPTION_KEY, $defaults, false );
		return true;
	}

	/**
	 * Normalize a category array with sensible defaults.
	 *
	 * @param array $category Raw category data.
	 * @return array
	 */
	private function normalize_category( array $category ): array {
		$slug = sanitize_title( $category['slug'] ?? $category['name'] ?? '' );

		$defaults = array(
			'slug'                => $slug,
			'name'                => '',
			'category_type'       => '',
			'pref_domain'         => '',
			'core_content_channel'=> '',
			'target_personas'     => array(),
			'target_sponsors'     => array(),
			'key_competitors'     => array(),
			'trade_associations'  => array(),
			'academic_journals'   => array(),
			'acronyms'            => array(),
			'cultural_lexicon'    => array(),
			'key_speakers'        => array(),
			'subgroups'           => array(),
			'research_policy'     => array(
				'priority_domains'    => array(),
				'blocked_domains'     => array( 'wikipedia.org', 'pinterest.com', 'reddit.com', 'quora.com' ),
				'blocked_keywords'    => array( 'chatgpt', 'gemini', 'claude', 'ai-generated', 'synthetic study' ),
				'preferred_sources'   => array(),
				'source_mix_minimums' => array(
					'academic'   => 1,
					'analyst'    => 1,
					'industry'   => 1,
					'case_study' => 1,
				),
				'recency_months'           => 36,
				'max_citations_per_org'    => 2,
				'min_priority_domains_hit' => 1,
			),
		);

		$merged = array_merge( $defaults, $category );

		// Merge nested research_policy
		if ( isset( $category['research_policy'] ) && is_array( $category['research_policy'] ) ) {
			$merged['research_policy'] = array_merge( $defaults['research_policy'], $category['research_policy'] );
			if ( isset( $category['research_policy']['source_mix_minimums'] ) && is_array( $category['research_policy']['source_mix_minimums'] ) ) {
				$merged['research_policy']['source_mix_minimums'] = array_merge(
					$defaults['research_policy']['source_mix_minimums'],
					$category['research_policy']['source_mix_minimums']
				);
			}
		}

		// Ensure slug is always set
		$merged['slug'] = $slug;

		return $merged;
	}

	/**
	 * Convert a CSV-style row (flat headers) into a category array.
	 *
	 * @param array $row Flat associative array (e.g., from CSV import).
	 * @return array
	 */
	private function normalize_import_row( array $row ): array {
		$name = $row['Brand Title'] ?? $row['name'] ?? $row['Name'] ?? $row['Category Name'] ?? '';
		$slug = sanitize_title( $name );

		if ( empty( $name ) || empty( $slug ) ) {
			return array();
		}

		$category = array(
			'slug'                => $slug,
			'name'                => $name,
			'category_type'       => $row['Category Type'] ?? $row['category_type'] ?? '',
			'pref_domain'         => $row['Pref. Domain'] ?? $row['pref_domain'] ?? '',
			'core_content_channel'=> $row['Core Content Channel'] ?? $row['core_content_channel'] ?? '',
			'target_personas'     => $this->parse_csv_list( $row['Target Personas'] ?? $row['target_personas'] ?? '' ),
			'target_sponsors'     => $this->parse_csv_list( $row['Target Sponsors'] ?? $row['target_sponsors'] ?? '' ),
			'key_competitors'     => $this->parse_csv_list( $row['Key Competitors'] ?? $row['key_competitors'] ?? '' ),
			'trade_associations'  => $this->parse_csv_list( $row['Trade Associations'] ?? $row['trade_associations'] ?? '' ),
			'academic_journals'   => $this->parse_csv_list( $row['Academic Journals'] ?? $row['academic_journals'] ?? '' ),
			'acronyms'            => $this->parse_csv_list( $row['Acronyms'] ?? $row['acronyms'] ?? '' ),
			'cultural_lexicon'    => $this->parse_csv_list( $row['Cultural Lexicon'] ?? $row['cultural_lexicon'] ?? '' ),
			'key_speakers'        => $this->parse_csv_list( $row['Key Speakers'] ?? $row['key_speakers'] ?? '' ),
			'subgroups'           => $this->parse_csv_list( $row['Subgroups'] ?? $row['subgroups'] ?? '' ),
			'research_policy'     => array(
				'priority_domains'    => $this->parse_csv_list( $row['Priority Domains'] ?? $row['priority_domains'] ?? '' ),
				'blocked_domains'     => $this->parse_csv_list( $row['Blocked Domains'] ?? $row['blocked_domains'] ?? 'wikipedia.org,pinterest.com,reddit.com,quora.com' ),
				'blocked_keywords'    => $this->parse_csv_list( $row['Blocked Keywords'] ?? $row['blocked_keywords'] ?? 'chatgpt,gemini,claude,ai-generated,synthetic study' ),
				'preferred_sources'   => $this->parse_csv_list( $row['Preferred Sources'] ?? $row['preferred_sources'] ?? '' ),
				'source_mix_minimums' => array(
					'academic'   => (int) ( $row['Source Mix: Academic'] ?? $row['source_mix_academic'] ?? 1 ),
					'analyst'    => (int) ( $row['Source Mix: Analyst'] ?? $row['source_mix_analyst'] ?? 1 ),
					'industry'   => (int) ( $row['Source Mix: Industry'] ?? $row['source_mix_industry'] ?? 1 ),
					'case_study' => (int) ( $row['Source Mix: Case Study'] ?? $row['source_mix_case_study'] ?? 1 ),
				),
				'recency_months'           => (int) ( $row['Recency Months'] ?? $row['recency_months'] ?? 36 ),
				'max_citations_per_org'    => (int) ( $row['Max Citations Per Org'] ?? $row['max_citations_per_org'] ?? 2 ),
				'min_priority_domains_hit' => (int) ( $row['Min Priority Domains Hit'] ?? $row['min_priority_domains_hit'] ?? 1 ),
			),
		);

		return $this->normalize_category( $category );
	}

	/**
	 * Parse a comma-separated or array field into an array of strings.
	 *
	 * @param mixed $value String or array.
	 * @return array
	 */
	private function parse_csv_list( $value ): array {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'trim', $value ) ) );
		}

		$string = trim( (string) $value );
		if ( empty( $string ) ) {
			return array();
		}

		$parts = explode( ',', $string );
		return array_values( array_filter( array_map( 'trim', $parts ) ) );
	}
}