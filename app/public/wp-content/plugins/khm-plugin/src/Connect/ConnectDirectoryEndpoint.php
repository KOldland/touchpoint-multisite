<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Public buyer-facing provider directory endpoint.
 *
 * GET /khm/v1/connect/directory
 *
 * No authentication required for browsing. Accepts filter params and returns
 * provider cards annotated with expertise/industry tags derived from ConnectTaxonomy.
 */
class ConnectDirectoryEndpoint {

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( 'khm/v1', '/connect/directory', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_providers' ],
			'permission_callback' => '__return_true',
			'args'                => $this->args(),
		] );
	}

	public function list_providers( WP_REST_Request $request ): WP_REST_Response {
		$blog_ids = $this->resolve_blog_ids( $request );

		// If filters were provided but resolved to no matching sites, return empty
		$has_filter = ! empty( $request->get_param( 'expertise' ) ) || ! empty( $request->get_param( 'industry' ) );
		if ( $has_filter && $blog_ids !== null && empty( $blog_ids ) ) {
			return new WP_REST_Response( [ 'providers' => [] ], 200 );
		}

		$repo    = new ConnectProviderRepository();
		$filters = [
			'blog_ids'        => $blog_ids,
			'provider_type'   => $request->get_param( 'provider_type' ) ?: null,
			'pilot_available' => (bool) $request->get_param( 'pilot_available' ),
			'free_trial'      => (bool) $request->get_param( 'free_trial' ),
			'budget_min'      => $request->get_param( 'budget_min' ),
			'budget_max'      => $request->get_param( 'budget_max' ),
			'company_size'    => $request->get_param( 'company_size' ),
			'deployment_mode' => $request->get_param( 'deployment_mode' ) ?: null,
		];

		$providers = $repo->list_filtered( $filters );

		$cards = array_values( array_map( [ $this, 'build_card' ], $providers ) );

		return new WP_REST_Response( [ 'providers' => $cards ], 200 );
	}

	// ─── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Translate expertise/industry filter params to a list of blog_ids.
	 * Returns null when no expertise/industry filter is set (show all).
	 *
	 * @return int[]|null
	 */
	private function resolve_blog_ids( WP_REST_Request $request ): ?array {
		$expertise_slugs = (array) ( $request->get_param( 'expertise' ) ?: [] );
		$industry_slugs  = (array) ( $request->get_param( 'industry' ) ?: [] );

		if ( empty( $expertise_slugs ) && empty( $industry_slugs ) ) {
			return null;
		}

		$site_slugs = [];

		if ( ! empty( $expertise_slugs ) ) {
			$site_slugs = array_merge(
				$site_slugs,
				ConnectTaxonomy::site_slugs_for_expertise( $expertise_slugs )
			);
		}

		if ( ! empty( $industry_slugs ) ) {
			$site_slugs = array_merge(
				$site_slugs,
				ConnectTaxonomy::site_slugs_for_industry( $industry_slugs )
			);
		}

		$site_slugs = array_values( array_unique( $site_slugs ) );

		if ( empty( $site_slugs ) ) {
			return [];
		}

		return ConnectTaxonomy::blog_ids_for_site_slugs( $site_slugs );
	}

	/**
	 * Build a buyer-safe provider card (no internal match_rules).
	 */
	private function build_card( array $provider ): array {
		$tags = ConnectTaxonomy::tags_for_blog_id( (int) $provider['blog_id'] );

		return [
			'id'                     => $provider['id'],
			'blog_id'                => $provider['blog_id'],
			'name'                   => $provider['name'],
			'slug'                   => $provider['slug'],
			'description'            => $provider['description'],
			'website_url'            => $provider['website_url'],
			'provider_type'          => $provider['provider_type'],
			'sweet_spot_summary'     => $provider['sweet_spot_summary'],
			'hq_location'            => $provider['hq_location'],
			'company_size_min'       => $provider['company_size_min'],
			'company_size_max'       => $provider['company_size_max'],
			'budget_min'             => $provider['budget_min'],
			'budget_max'             => $provider['budget_max'],
			'onboarding_days'        => $provider['onboarding_days'],
			'regions'                => $provider['regions'],
			'deployment_modes'       => $provider['deployment_modes'],
			'support_tiers'          => $provider['support_tiers'],
			'pilot_scheme_available' => $provider['pilot_scheme_available'],
			'free_trial_available'   => $provider['free_trial_available'],
			'trustpilot_rating'      => $provider['trustpilot_rating'],
			'client_count_band'      => $provider['client_count_band'],
			'integrations'           => $provider['integrations'],
			'expertise_tags'         => $tags['expertise'],
			'industry_tags'          => $tags['industries'],
		];
	}

	private function args(): array {
		return [
			'expertise'       => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'default' => [] ],
			'industry'        => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'default' => [] ],
			'provider_type'   => [ 'type' => 'string' ],
			'deployment_mode' => [ 'type' => 'string' ],
			'pilot_available' => [ 'type' => 'boolean', 'default' => false ],
			'free_trial'      => [ 'type' => 'boolean', 'default' => false ],
			'budget_min'      => [ 'type' => 'integer', 'minimum' => 0 ],
			'budget_max'      => [ 'type' => 'integer', 'minimum' => 0 ],
			'company_size'    => [ 'type' => 'integer', 'minimum' => 1 ],
		];
	}
}
