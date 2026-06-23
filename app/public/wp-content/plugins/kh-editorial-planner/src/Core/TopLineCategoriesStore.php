<?php
/**
 * Top-Line Categories Store
 *
 * Persists top-line categories to the `kh_planner_top_line_categories`
 * WordPress option. Default data seeds from SiteAudienceProfile on first use.
 *
 * @package KH\Planner\Core
 * @since 0.3.0
 */

namespace KH\Planner\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TopLineCategoriesStore {

	const OPTION_KEY = 'kh_planner_top_line_categories';

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
	 * Seed default categories from SiteAudienceProfile if the store is empty.
	 *
	 * Cycles through every audience profile in SiteAudienceProfile and
	 * builds a full category entry with name, slug, site_slug, pillars,
	 * target personas (from readers), and the standard research_policy defaults.
	 *
	 * After seeding, pillar data is already populated from the profile,
	 * so no separate pillar seeding step is needed.
	 *
	 * @return bool True if seeded, false if already had data.
	 */
	public function seed_if_empty(): bool {
		$existing = $this->get_all();
		if ( ! empty( $existing ) ) {
			return false;
		}

		if ( ! class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
			return false;
		}

		$profile_class = '\KH\Editorial\Services\SiteAudienceProfile';
		$audience_data = $profile_class::AUDIENCE;

		if ( empty( $audience_data ) ) {
			return false;
		}

		$categories = array();
		foreach ( $audience_data as $slug => $profile ) {
			$pillars = $profile['pillars'] ?? array();

			$categories[] = array(
				'slug'                => $slug,
				'name'                => $profile['label'] ?? ucfirst( $slug ),
				'channel'             => '', // Will be populated from CSV mapping later
				'pillars'             => $pillars,
				'target_personas'     => array(), // Will be populated from pillars if available
				'keywords'            => array(), // Will be populated from pillars if available
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
		}

		if ( empty( $categories ) ) {
			return false;
		}

		update_option( self::OPTION_KEY, $categories, false );
		return true;
	}

	/**
	 * Get pillars for a category by slug.
	 *
	 * Merges from SiteAudienceProfile if available, falling back to stored pillars.
	 *
	 * @param string $slug Category slug.
	 * @return array Array of pillar arrays, each with 'name' and 'focus' keys.
	 */
	public function get_pillars( string $slug ): array {
		$category = $this->get_by_slug( $slug );
		$stored_pillars = $category['pillars'] ?? array();

		// Use stored pillars (which can be customized) if present
		if ( ! empty( $stored_pillars ) ) {
			return $stored_pillars;
		}

		// Fall back to SiteAudienceProfile if the slug matches a known site
		if ( class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
			$profile_pillars = \KH\Editorial\Services\SiteAudienceProfile::get_pillars( $slug );
			if ( ! empty( $profile_pillars ) ) {
				return $profile_pillars;
			}
		}

		return array();
	}

	/**
	 * Auto-populate pillars from SiteAudienceProfile for any categories
	 * whose slugs match a known audience profile.
	 *
	 * @return int Number of categories updated.
	 */
	public function seed_pillars_from_profiles(): int {
		if ( ! class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
			return 0;
		}

		$all = $this->get_all();
		$updated = 0;

		foreach ( $all as $i => $category ) {
			$slug = $category['slug'] ?? '';
			if ( empty( $slug ) ) {
				continue;
			}

			$profile_pillars = \KH\Editorial\Services\SiteAudienceProfile::get_pillars( $slug );
			if ( ! empty( $profile_pillars ) ) {
				// Only set if not already populated
				$stored = $category['pillars'] ?? array();
				if ( empty( $stored ) ) {
					$all[ $i ]['pillars'] = $profile_pillars;
					$all[ $i ]['site_slug'] = $slug; // Keep site_slug for now, might be useful
					$updated++;
				}
			}
		}

		if ( $updated > 0 ) {
			update_option( self::OPTION_KEY, $all, false );
		}

		return $updated;
	}

	/**
	 * Normalize a category array with sensible defaults and mapping.
	 *
	 * @param array $category Raw category data.
	 * @return array
	 */
	private function normalize_category( array $category ): array {
		$slug = sanitize_title( $category['slug'] ?? $category['name'] ?? '' );

		// Determine site_slug — use explicit or infer from the category slug
		$site_slug = $category['site_slug'] ?? null;
		if ( ! $site_slug && class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
			// Check if this slug matches a known audience profile
			$profile = \KH\Editorial\Services\SiteAudienceProfile::get_profile( $slug );
			if ( $profile ) {
				$site_slug = $slug;
			}
		}

		$defaults = array(
			'slug'                => $slug,
			'name'                => '',
			'channel'             => '', // Mapped from Core Content Channel
			'pillars'             => array(), // Seeded from SiteAudienceProfile, overridden by CSV
			'target_personas'     => array(), // Aggregated from pillars, editable
			'keywords'            => array(), // Aggregated from pillars, editable
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

		// Auto-seed pillars from SiteAudienceProfile if empty and slug matches
		if ( empty( $merged['pillars'] ) && ! empty( $merged['site_slug'] ) && class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
			$profile_pillars = \KH\Editorial\Services\SiteAudienceProfile::get_pillars( $merged['site_slug'] );
			if ( ! empty( $profile_pillars ) ) {
				$merged['pillars'] = $profile_pillars;

				// Also populate target_personas and keywords from the newly seeded pillars
				$all_readers = [];
				$all_keywords = [];
				foreach ( $merged['pillars'] as $pillar ) {
					$all_readers = array_merge( $all_readers, $pillar['readers'] ?? [] );
					$all_keywords = array_merge( $all_keywords, $pillar['keywords'] ?? [] );
				}
				$merged['target_personas'] = array_values( array_unique( $all_readers ) );
				$merged['keywords'] = array_values( array_unique( $all_keywords ) );
			}
		}

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
	 * Get the pillar-to-channel mapping based on the provided data.
	 *
	 * @return array
	 */
	private function get_pillar_channel_mapping(): array {
		return [
			'aftermarket' => [
				'pillars' => [
					[
						'name' => 'Service Revenue Streams',
						'core_content_channels' => ['Aftermarket Profitability & Growth', 'Customer Loyalty & Brand Experience'],
					],
					[
						'name' => 'Servitization & Advanced Service Strategies',
						'core_content_channels' => ['Service Business Models & Servitization'],
					],
					[
						'name' => 'Installed Base Intelligence',
						'core_content_channels' => ['Aftermarket Digital Transformation'],
					],
					[
						'name' => 'Installed Base Lifecycle Management',
						'core_content_channels' => ['Installed Base Lifecycle Management'],
					],
				],
			],
			'pricing' => [
				'pillars' => [
					[
						'name' => 'Pricing Strategy & Tactics',
						'core_content_channels' => ['Commercial Strategy'],
					],
					[
						'name' => 'Price Optimization & Analytics',
						'core_content_channels' => ['Price & Margin Excellence', 'Revenue Intelligence'],
					],
					[
						'name' => 'Contract & Deal Management',
						'core_content_channels' => ['Rebate & Incentive Mgmt'],
					],
					[
						'name' => 'Value-Based Selling & Growth',
						'core_content_channels' => ['Demand Operations'],
					],
				],
			],
			'field-service' => [
				'pillars' => [
					[
						'name' => 'Field Service Delivery',
						'core_content_channels' => ['Maintenance Strategy & Execution', 'Service Delivery & Customer Experience'],
					],
					[
						'name' => 'Mobile Workforce Management',
						'core_content_channels' => ['Scheduling, Dispatch & Logistics', 'Workforce Operations & Talent'],
					],
					[
						'name' => 'Field Service Profitability',
						'core_content_channels' => ['Operational Visibility & KPIs'],
					],
					[
						'name' => 'Customer Experience & Success',
						'core_content_channels' => ['Service Delivery & Customer Experience'],
					],
				],
			],
			'spare-parts' => [
				'pillars' => [
					[
						'name' => 'Network Design & Infrastructure',
						'core_content_channels' => ['Distribution & Last-Mile Logistics', 'Strategic Sourcing & Supply Resilience'],
					],
					[
						'name' => 'Warehousing & Operations',
						'core_content_channels' => ['Warehouse Operations & Physical Footprint'],
					],
					[
						'name' => 'Inventory & Digital Tracking',
						'core_content_channels' => ['Inventory Planning & Demand Intelligence', 'Parts Discovery & Digital Enablement'],
					],
					[
						'name' => 'Lifecycle Service & Regulations',
						'core_content_channels' => ['Returns, Cores & Remanufacturing'],
					],
				],
			],
			'ecommerce' => [
				'pillars' => [
					[
						'name' => 'Digital Sales Channels',
						'core_content_channels' => ['Platform & Architecture'],
					],
					[
						'name' => 'B2B User Experience (UX)',
						'core_content_channels' => ['Customer Experience (CX) & Conversion'],
					],
					[
						'name' => 'eCommerce Tech Stack & Integration',
						'core_content_channels' => ['Payments, Fraud & Security', 'Supply Chain & Fulfillment'],
					],
					[
						'name' => 'Digital Customer Acquisition & Growth',
						'core_content_channels' => ['Data, AI & Analytics'],
					],
				],
			],
			'aerospace' => [
				'pillars' => [
					[
						'name' => 'Procurement & Engineering',
						'core_content_channels' => ['Production Backlogs'],
					],
					[
						'name' => 'Maintenance, Repair & Overhaul (MRO)',
						'core_content_channels' => ['Commercial MRO', 'Skills Gaps'],
					],
					[
						'name' => 'Digital Innovation & Tech',
						'core_content_channels' => ['Autonomous Systems'],
					],
					[
						'name' => 'Regulation & Growth',
						'core_content_channels' => ['Geopolitical/Regulatory', 'Decarbonization'],
					],
				],
			],
			'built-env' => [
				'pillars' => [
					[
						'name' => 'Construction & Development',
						'core_content_channels' => ['Digital Project Delivery', 'Transport & Connectivity Infrastructure', 'Health, Safety & Workforce Wellbeing'],
					],
					[
						'name' => 'Maintenance, Facilities & Asset Management',
						'core_content_channels' => ['Infrastructure & Physical Asset Management'],
					],
					[
						'name' => 'Smart Technology & Integration',
						'core_content_channels' => ['ASmart Buildings & Facilities Management'],
					],
					[
						'name' => 'Sustainability & Infrastructure Performance',
						'core_content_channels' => ['Circular Infrastructure & Materials'],
					],
				],
			],
			'industrial' => [
				'pillars' => [
					[
						'name' => 'Design & Engineering',
						'core_content_channels' => ['Electrification & Alternative Powertrains'],
					],
					[
						'name' => 'Manufacturing Operations',
						'core_content_channels' => ['Production Ramp-up & Supply Resilience', 'Operational Safety & Ergonomics'],
					],
					[
						'name' => 'Industrial Digitalization',
						'core_content_channels' => ['Connected Equipment & Industrial AI', 'Automation & Autonomous Systems'],
					],
					[
						'name' => 'Sustainability & Remanufacturing',
						'core_content_channels' => ['Service Lifecycle Management'],
					],
				],
			],
			'utilities' => [
				'pillars' => [
					[
						'name' => 'Design & Engineering',
						'core_content_channels' => ['Load Growth & Interconnection Queues'],
					],
					[
						'name' => 'Asset Operations & Network Management',
						'core_content_channels' => ['Grid Modernization & Resilience', 'Compliance, Safety & PFAS'],
					],
					[
						'name' => 'Digital Grid & Smart Metering',
						'core_content_channels' => ['AI & Intelligent Operations', 'Workforce Capability & AI Literacy'],
					],
					[
						'name' => 'Servitization & Sustainability Models',
						'core_content_channels' => ['Circular Resources & Recycling'],
					],
				],
			],
			'manufacturing' => [
				'pillars' => [
					[
						'name' => 'Advanced Production Systems (APS)',
						'core_content_channels' => ['Industry 5.0', 'Human-Machine Collaboration'],
					],
					[
						'name' => 'Supply Chain & Lifecycle Management',
						'core_content_channels' => ['Industrial Sustainability & Circularity'],
					],
					[
						'name' => 'Smart Factory & Digitalization',
						'core_content_channels' => ['Agentic AI & Industrial Intelligence'],
					],
					[
						'name' => 'Servitization & Business Model Innovation',
						'core_content_channels' => ['Servitization & XaaS Business Models'],
					],
				],
			],
		];
	}


	/**
	 * Get a prompt-ready audience context string, enriched with core content channels.
	 *
	 * Uses the main TopLineCategory entry if available, falling back to SiteAudienceProfile.
	 *
	 * @param string $slug Category slug.
	 * @return string Human-readable context paragraph, or empty string if slug not found.
	 */
	public function get_enriched_audience_context( string $slug ): string {
		$category = $this->get_by_slug( $slug );

		if ( ! $category && class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
			return \KH\Editorial\Services\SiteAudienceProfile::get_audience_context( $slug );
		}

		if ( ! $category ) {
			return '';
		}

		$pillar_lines = [];
		$all_readers = [];
		$all_keywords = [];
		$all_channels = [];

		$pillars = $category['pillars'] ?? [];

		foreach ( $pillars as $pillar ) {
			$pillar_name = $pillar['name'] ?? '';
			$pillar_focus = $pillar['focus'] ?? '';
			if ( ! empty( $pillar_name ) ) {
				$pillar_lines[] = "{$pillar_name} ({$pillar_focus})";
			}

			$all_readers = array_merge( $all_readers, $pillar['readers'] ?? [] );
			$all_keywords = array_merge( $all_keywords, $pillar['keywords'] ?? [] );

			// Append core content channels if they exist for this pillar
			if ( ! empty( $pillar['core_content_channels'] ) ) {
				$all_channels = array_merge( $all_channels, $pillar['core_content_channels'] );
			}
		}

		// Use category level fallbacks if pillar data is empty
		if ( empty( $all_readers ) && ! empty( $category['target_personas'] ) ) {
			$all_readers = $category['target_personas'];
		}
		if ( empty( $all_keywords ) && ! empty( $category['keywords'] ) ) {
			$all_keywords = $category['keywords'];
		}

		$unique_readers = implode( ', ', array_unique( $all_readers ) );
		$unique_keywords = implode( ', ', array_unique( $all_keywords ) );
		$pillars_text = implode( '; ', $pillar_lines );
		$unique_channels = implode( '; ', array_unique( $all_channels ) );

		$context = "This publication serves: {$unique_readers}. ";
		$context .= "Editorial coverage spans: {$pillars_text}. ";
		$context .= "Key topics include: {$unique_keywords}.";

		if ( ! empty( $unique_channels ) ) {
			$context .= " Core content channels include: {$unique_channels}.";
		} elseif ( ! empty( $category['channel'] ) ) {
			// Fallback to top-level channel if individual pillars don't have mapping
			$context .= " Core content channels include: {$category['channel']}.";
		}

		return $context;
	}
}
