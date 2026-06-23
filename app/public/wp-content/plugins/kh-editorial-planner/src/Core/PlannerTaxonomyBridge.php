<?php
/**
 * Planner Taxonomy Bridge
 *
 * Synchronises Top-Line Categories and SiteAudienceProfile pillars
 * with WordPress taxonomies (`tp_category`, `tp_pillar`) on posts.
 *
 * When the Author Agent persists a draft, this bridge auto-applies
 * the correct category and pillar terms based on the planner session,
 * making all planner-generated posts queryable by taxonomy.
 *
 * @package KH\Planner\Core
 */

namespace KH\Planner\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PlannerTaxonomyBridge {

	const PILLAR_TAXONOMY   = 'tp_pillar';
	const CATEGORY_TAXONOMY = 'tp_category';

	/**
	 * Hook into WordPress lifecycle.
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register_taxonomies' ], 5 );
		add_action( 'init', [ $this, 'sync_terms_from_store' ], 20 );
		add_action( 'init', [ $this, 'sync_pillars_from_profiles' ], 20 );

		// Auto-apply taxonomies when a post with pillar/category meta is saved.
		add_action( 'wp_after_insert_post', [ $this, 'maybe_apply_on_save' ], 20, 2 );

		// Also catch meta being set on existing posts (e.g. via allocation pipeline).
		add_action( 'added_post_meta',   [ $this, 'maybe_on_meta_update' ], 20, 4 );
		add_action( 'updated_post_meta', [ $this, 'maybe_on_meta_update' ], 20, 4 );
	}

	/**
	 * Register tp_pillar and tp_category taxonomies on post types.
	 */
	public function register_taxonomies(): void {
		$post_types = [ 'post', 'atomic_article' ];

		// --- tp_pillar ---
		if ( ! taxonomy_exists( self::PILLAR_TAXONOMY ) ) {
			register_taxonomy( self::PILLAR_TAXONOMY, $post_types, [
				'labels' => [
					'name'          => __( 'Pillars', 'kh-editorial-planner' ),
					'singular_name' => __( 'Pillar', 'kh-editorial-planner' ),
					'search_items'  => __( 'Search Pillars', 'kh-editorial-planner' ),
					'all_items'     => __( 'All Pillars', 'kh-editorial-planner' ),
					'edit_item'     => __( 'Edit Pillar', 'kh-editorial-planner' ),
					'update_item'   => __( 'Update Pillar', 'kh-editorial-planner' ),
					'add_new_item'  => __( 'Add New Pillar', 'kh-editorial-planner' ),
					'new_item_name' => __( 'New Pillar Name', 'kh-editorial-planner' ),
					'menu_name'     => __( 'Pillars', 'kh-editorial-planner' ),
				],
				'public'            => true,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'hierarchical'      => true,
				'query_var'         => 'tp_pillar',
				'rewrite'           => false,
			] );
		}

		// --- tp_category ---
		if ( ! taxonomy_exists( self::CATEGORY_TAXONOMY ) ) {
			register_taxonomy( self::CATEGORY_TAXONOMY, $post_types, [
				'labels' => [
					'name'          => __( 'Categories', 'kh-editorial-planner' ),
					'singular_name' => __( 'Category', 'kh-editorial-planner' ),
					'search_items'  => __( 'Search Categories', 'kh-editorial-planner' ),
					'all_items'     => __( 'All Categories', 'kh-editorial-planner' ),
					'edit_item'     => __( 'Edit Category', 'kh-editorial-planner' ),
					'update_item'   => __( 'Update Category', 'kh-editorial-planner' ),
					'add_new_item'  => __( 'Add New Category', 'kh-editorial-planner' ),
					'new_item_name' => __( 'New Category Name', 'kh-editorial-planner' ),
					'menu_name'     => __( 'Audience Categories', 'kh-editorial-planner' ),
				],
				'public'            => true,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'hierarchical'      => true,
				'query_var'         => 'tp_category',
				'rewrite'           => false,
			] );
		}
	}

	/**
	 * Sync TopLineCategoriesStore entries as tp_category terms.
	 *
	 * Creates or updates a tp_category term for each entry in the store,
	 * carrying research_policy as term meta. Idempotent — safe to run
	 * on every init since it checks term existence before creating.
	 *
	 * @todo Support multisite: accept $site_slug param so categories
	 *       resolve per-site rather than globally.
	 *
	 * @return int Number of synced categories.
	 */
	public function sync_terms_from_store(): int {
		if ( ! taxonomy_exists( self::CATEGORY_TAXONOMY ) ) {
			return 0;
		}

		if ( ! class_exists( '\KH\Planner\Core\TopLineCategoriesStore' ) ) {
			return 0;
		}

		$store      = new TopLineCategoriesStore();
		$all        = $store->get_all();
		$sync_count = 0;

		foreach ( $all as $category ) {
			$slug = $category['slug'] ?? '';
			$name = $category['name'] ?? $slug;
			if ( empty( $slug ) ) {
				continue;
			}

			$existing = term_exists( $slug, self::CATEGORY_TAXONOMY );
			if ( $existing ) {
				$updated = wp_update_term( (int) $existing['term_id'], self::CATEGORY_TAXONOMY, [
					'name' => $name,
					'slug' => $slug,
				] );
				if ( is_wp_error( $updated ) ) {
					continue;
				}
				$term_id = (int) $existing['term_id'];
			} else {
				$result = wp_insert_term( $name, self::CATEGORY_TAXONOMY, [ 'slug' => $slug ] );
				if ( is_wp_error( $result ) ) {
					continue;
				}
				$term_id = (int) $result['term_id'];
			}

			// Sync research_policy as term meta.
			$policy = $category['research_policy'] ?? [];
			if ( ! empty( $policy ) ) {
				update_term_meta( $term_id, 'tp_category_research_policy', $policy );
			}

			$sync_count++;
		}

		return $sync_count;
	}

	/**
	 * Auto-populate tp_pillar terms from SiteAudienceProfile.
	 *
	 * Creates a unique term per pillar per audience profile.
	 * Idempotent — safe to run on every init since it checks
	 * term existence before creating.
	 *
	 * @todo Support multisite: accept $site_slug param and pass to
	 *       SiteAudienceProfile so pillars resolve per-site.
	 *
	 * @return int Number of pillar terms created.
	 */
	public function sync_pillars_from_profiles(): int {
		if ( ! taxonomy_exists( self::PILLAR_TAXONOMY ) ) {
			return 0;
		}

		if ( ! class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
			return 0;
		}

		$created = 0;
		$audience_slugs = array_keys( \KH\Editorial\Services\SiteAudienceProfile::AUDIENCE );

		foreach ( $audience_slugs as $slug ) {
			$pillars = \KH\Editorial\Services\SiteAudienceProfile::get_pillars( $slug );
			if ( empty( $pillars ) ) {
				continue;
			}

			foreach ( $pillars as $pillar ) {
				$pillar_name = $pillar['name'] ?? '';
				$pillar_slug = sanitize_title( $pillar_name );
				if ( empty( $pillar_name ) || empty( $pillar_slug ) ) {
					continue;
				}

				$existing = term_exists( $pillar_slug, self::PILLAR_TAXONOMY );
				if ( ! $existing ) {
					$result = wp_insert_term( $pillar_name, self::PILLAR_TAXONOMY, [ 'slug' => $pillar_slug ] );
					if ( ! is_wp_error( $result ) ) {
						$created++;
					}
				}
			}
		}

		return $created;
	}

	/**
	 * Auto-apply tp_category / tp_pillar terms when a post is saved
	 * with the relevant meta fields set.
	 *
	 * This replaces the orphaned `kh_planner_after_persist_draft` action
	 * and works for both planner-sourced posts and manually-created posts
	 * that have had their meta populated.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function maybe_apply_on_save( int $post_id, \WP_Post $post ): void {
		// Re-entrancy guard to prevent cascading via wp_set_object_terms triggers.
		static $already_running = [];
		if ( isset( $already_running[ $post_id ] ) ) {
			return;
		}
		$already_running[ $post_id ] = true;

		// Only apply to supported post types.
		if ( ! in_array( $post->post_type, [ 'post', 'atomic_article' ], true ) ) {
			unset( $already_running[ $post_id ] );
			return;
		}

		// Check if this post already has the meta that tells us what to apply.
		$audience_slug = get_post_meta( $post_id, 'kh_planner_audience_slug', true );
		$pillar_slug   = get_post_meta( $post_id, 'kh_planner_pillar_slug', true );
		$pillar_name   = get_post_meta( $post_id, 'kh_planner_pillar', true );

		if ( ! empty( $audience_slug ) || ! empty( $pillar_slug ) || ! empty( $pillar_name ) ) {
			$this->apply_terms_directly( $post_id, (string) $audience_slug, (string) $pillar_slug, (string) $pillar_name );
			unset( $already_running[ $post_id ] );
			return;
		}

		// Also check if this post has a linked planner session.
		$session_id = get_post_meta( $post_id, 'kh_planner_session_id', true );
		if ( ! $session_id ) {
			$session_id = get_post_meta( $post_id, '_planner_session_id', true );
		}
		if ( $session_id ) {
			$this->apply_taxonomies_to_post( $post_id, (int) $session_id );
		}

		unset( $already_running[ $post_id ] );
	}

	/**
	 * React when planner-related meta is added or updated on a post.
	 *
	 * Catches the allocation pipeline setting meta on cloned posts
	 * after the initial `wp_after_insert_post` hook may have fired.
	 *
	 * @param int    $meta_id  Meta ID (unused).
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key being set.
	 * @param mixed  $_meta_value Meta value (unused).
	 */
	public function maybe_on_meta_update( int $meta_id, int $post_id, string $meta_key, $_meta_value ): void {
		// Only care about our specific meta keys.
		$trigger_keys = [
			'kh_planner_audience_slug',
			'kh_planner_pillar_slug',
			'kh_planner_pillar',
			'kh_planner_session_id',
			'_planner_session_id',
		];

		if ( ! in_array( $meta_key, $trigger_keys, true ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, [ 'post', 'atomic_article' ], true ) ) {
			return;
		}

		// Flag to prevent re-entry in the same request.
		static $already_running = [];
		$key                    = $post_id . '|' . $meta_key;
		if ( isset( $already_running[ $key ] ) ) {
			return;
		}
		$already_running[ $key ] = true;

		// Re-read fresh meta and apply.
		$audience_slug = get_post_meta( $post_id, 'kh_planner_audience_slug', true );
		$pillar_slug   = get_post_meta( $post_id, 'kh_planner_pillar_slug', true );
		$pillar_name   = get_post_meta( $post_id, 'kh_planner_pillar', true );

		if ( ! empty( $audience_slug ) || ! empty( $pillar_slug ) || ! empty( $pillar_name ) ) {
			$this->apply_terms_directly( $post_id, $audience_slug, $pillar_slug, $pillar_name );
			unset( $already_running[ $key ] );
			return;
		}

		// Check for linked planner session.
		$session_id = get_post_meta( $post_id, 'kh_planner_session_id', true );
		if ( ! $session_id ) {
			$session_id = get_post_meta( $post_id, '_planner_session_id', true );
		}
		if ( $session_id ) {
			$this->apply_taxonomies_to_post( $post_id, (int) $session_id );
		}

		unset( $already_running[ $key ] );
	}

	/**
	 * Apply tp_category and tp_pillar terms to a post based on a planner session.
	 *
	 * Reads audience/pillar metadata from the planner session and resolves
	 * the corresponding taxonomy terms, creating them if they don't exist yet.
	 *
	 * Supports multisite: if the session post is not found on the current blog,
	 * falls back to the hub (blog_id=1) where planner_session CPTs live.
	 *
	 * @param int    $post_id     The WordPress post ID.
	 * @param int    $session_id  The planner session ID.
	 * @param string $pillar_slug Pillar slug (optional, read from session meta if empty).
	 * @return bool True if at least one taxonomy term was applied.
	 */
	public function apply_taxonomies_to_post( int $post_id, int $session_id, string $pillar_slug = '' ): bool {
		$audience_slug = '';
		$pillar_name   = '';
		$switched      = false;

		// If the planner session doesn't exist on the current blog, try the hub.
		$session_post = get_post( $session_id );
		if ( ! $session_post && is_multisite() ) {
			switch_to_blog( 1 );
			$switched = true;
			$session_post = get_post( $session_id );
		}

		if ( $session_post ) {
			$audience_slug = get_post_meta( $session_id, 'kh_planner_audience_slug', true );
			$pillar_name   = get_post_meta( $session_id, 'kh_planner_pillar', true );

			if ( empty( $pillar_slug ) ) {
				$pillar_slug = get_post_meta( $session_id, 'kh_planner_pillar_slug', true );
			}
		}

		if ( $switched ) {
			restore_current_blog();
		}

		$taxonomies_to_set = [];
		$applied           = false;

		// --- tp_category ---
		if ( ! empty( $audience_slug ) && taxonomy_exists( self::CATEGORY_TAXONOMY ) ) {
			$term = get_term_by( 'slug', $audience_slug, self::CATEGORY_TAXONOMY );

			if ( ! $term ) {
				// Fallback: resolve the proper display name from the store.
				$display_name = $this->get_category_display_name( $audience_slug );

				$result = wp_insert_term( $display_name, self::CATEGORY_TAXONOMY, [
					'slug' => $audience_slug,
				] );
				if ( ! is_wp_error( $result ) ) {
					$term = get_term( (int) $result['term_id'] );
				}
			}

			if ( $term instanceof \WP_Term ) {
				$taxonomies_to_set[ self::CATEGORY_TAXONOMY ] = [ $term->term_id ];
			}
		}

		// --- tp_pillar ---
		if ( ! empty( $pillar_slug ) && taxonomy_exists( self::PILLAR_TAXONOMY ) ) {
			$term = get_term_by( 'slug', $pillar_slug, self::PILLAR_TAXONOMY );

			if ( ! $term && ! empty( $pillar_name ) ) {
				$result = wp_insert_term( $pillar_name, self::PILLAR_TAXONOMY, [ 'slug' => $pillar_slug ] );
				if ( ! is_wp_error( $result ) ) {
					$term = get_term( (int) $result['term_id'] );
				}
			}

			if ( $term instanceof \WP_Term ) {
				$taxonomies_to_set[ self::PILLAR_TAXONOMY ] = [ $term->term_id ];
			}
		}

		// --- apply ---
		foreach ( $taxonomies_to_set as $tax => $term_ids ) {
			$result = wp_set_object_terms( $post_id, $term_ids, $tax, true );
			if ( ! is_wp_error( $result ) ) {
				$applied = true;
			}
		}

		return $applied;
	}

	/**
	 * Apply tp_category / tp_pillar terms directly from meta values.
	 *
	 * Shared by maybe_apply_on_save() and maybe_on_meta_update().
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $audience_slug Category slug (e.g. 'field-service').
	 * @param string $pillar_slug   Pillar slug (e.g. 'field-service-delivery').
	 * @param string $pillar_name   Pillar display name.
	 * @return bool True if at least one taxonomy term was applied.
	 */
	public function apply_terms_directly( int $post_id, string $audience_slug, string $pillar_slug, string $pillar_name ): bool {
		$taxonomies_to_set = [];
		$applied           = false;

		// --- tp_category ---
		if ( ! empty( $audience_slug ) && taxonomy_exists( self::CATEGORY_TAXONOMY ) ) {
			$term = get_term_by( 'slug', $audience_slug, self::CATEGORY_TAXONOMY );

			if ( ! $term ) {
				$display_name = $this->get_category_display_name( $audience_slug );
				$result       = wp_insert_term( $display_name, self::CATEGORY_TAXONOMY, [
					'slug' => $audience_slug,
				] );
				if ( ! is_wp_error( $result ) ) {
					$term = get_term( (int) $result['term_id'] );
				}
			}

			if ( $term instanceof \WP_Term ) {
				$taxonomies_to_set[ self::CATEGORY_TAXONOMY ] = [ $term->term_id ];
			}
		}

		// --- tp_pillar ---
		if ( ! empty( $pillar_slug ) && taxonomy_exists( self::PILLAR_TAXONOMY ) ) {
			$term = get_term_by( 'slug', $pillar_slug, self::PILLAR_TAXONOMY );

			if ( ! $term && ! empty( $pillar_name ) ) {
				$result = wp_insert_term( $pillar_name, self::PILLAR_TAXONOMY, [ 'slug' => $pillar_slug ] );
				if ( ! is_wp_error( $result ) ) {
					$term = get_term( (int) $result['term_id'] );
				}
			}

			if ( $term instanceof \WP_Term ) {
				$taxonomies_to_set[ self::PILLAR_TAXONOMY ] = [ $term->term_id ];
			}
		}

		// --- apply ---
		foreach ( $taxonomies_to_set as $tax => $term_ids ) {
			$result = wp_set_object_terms( $post_id, $term_ids, $tax, true );
			if ( ! is_wp_error( $result ) ) {
				$applied = true;
			}
		}

		return $applied;
	}

	/**
	 * Resolve a human-readable display name for a category slug.
	 *
	 * Looks up the TopLineCategoriesStore first; falls back to a
	 * title-cased transformation of the slug.
	 *
	 * @param string $slug Category slug.
	 * @return string Display name.
	 */
	private function get_category_display_name( string $slug ): string {
		if ( class_exists( '\KH\Planner\Core\TopLineCategoriesStore' ) ) {
			$store = new TopLineCategoriesStore();
			$all   = $store->get_all();

			foreach ( $all as $category ) {
				if ( ( $category['slug'] ?? '' ) === $slug && ! empty( $category['name'] ) ) {
					return $category['name'];
				}
			}
		}

		// Reasonable fallback: "field-service" -> "Field Service".
		return ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
	}
}