<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

use KHM\Migrations\CreateConnectSavedSearchesTable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Buyer-facing Saved Search bookmarks.
 *
 * Distinct from RFQ — a saved search is a passive bookmark of the wizard's
 * criteria payload, never broadcast to providers. Re-running uses the same
 * matching path as the wizard's Find Matches action.
 *
 * GET    /khm/v1/connect/saved-searches/mine
 * POST   /khm/v1/connect/saved-searches
 * POST   /khm/v1/connect/saved-searches/{id}/run
 * DELETE /khm/v1/connect/saved-searches/{id}
 */
class ConnectSavedSearchEndpoint {

	private const MAX_PER_USER = 25;

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( 'khm/v1', '/connect/saved-searches/mine', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_mine' ],
			'permission_callback' => [ $this, 'require_logged_in' ],
		] );

		register_rest_route( 'khm/v1', '/connect/saved-searches', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'create' ],
			'permission_callback' => [ $this, 'require_logged_in' ],
			'args'                => [
				'label'    => [ 'type' => 'string', 'default' => '' ],
				'criteria' => [ 'type' => 'object', 'default' => [] ],
			],
		] );

		register_rest_route( 'khm/v1', '/connect/saved-searches/(?P<id>\d+)/run', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'run' ],
			'permission_callback' => [ $this, 'require_logged_in' ],
		] );

		register_rest_route( 'khm/v1', '/connect/saved-searches/(?P<id>\d+)', [
			'methods'             => 'DELETE',
			'callback'            => [ $this, 'delete' ],
			'permission_callback' => [ $this, 'require_logged_in' ],
		] );
	}

	public function require_logged_in(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', 'Authentication required.', [ 'status' => 401 ] );
		}
		return true;
	}

	// ─── Handlers ──────────────────────────────────────────────────────────────

	public function list_mine( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$table   = CreateConnectSavedSearchesTable::table_name();
		$user_id = get_current_user_id();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, label, criteria_json, last_matched_at, created_at, updated_at
				 FROM `{$table}`
				 WHERE user_id = %d
				 ORDER BY created_at DESC
				 LIMIT %d",
				$user_id,
				self::MAX_PER_USER
			),
			ARRAY_A
		);

		$items = array_map( [ $this, 'hydrate' ], $rows ?: [] );

		return new WP_REST_Response( [ 'saved_searches' => $items ], 200 );
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;
		$table   = CreateConnectSavedSearchesTable::table_name();
		$user_id = get_current_user_id();

		$count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d", $user_id )
		);
		if ( $count >= self::MAX_PER_USER ) {
			return new WP_Error(
				'saved_search_cap_reached',
				sprintf( 'You can keep at most %d saved searches. Delete one to save another.', self::MAX_PER_USER ),
				[ 'status' => 422 ]
			);
		}

		$label    = sanitize_text_field( (string) ( $request->get_param( 'label' ) ?? '' ) );
		$criteria = $request->get_param( 'criteria' );
		if ( ! is_array( $criteria ) ) {
			$criteria = [];
		}

		if ( '' === $label ) {
			$label = $this->derive_label( $criteria );
		}

		$now      = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			$table,
			[
				'user_id'       => $user_id,
				'label'         => $label,
				'criteria_json' => (string) wp_json_encode( $criteria ),
				'created_at'    => $now,
				'updated_at'    => $now,
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			return new WP_Error( 'saved_search_create_failed', 'Failed to save search.', [ 'status' => 500 ] );
		}

		$id  = (int) $wpdb->insert_id;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, label, criteria_json, last_matched_at, created_at, updated_at FROM `{$table}` WHERE id = %d", $id ),
			ARRAY_A
		);

		return new WP_REST_Response( [ 'saved_search' => $this->hydrate( $row ) ], 201 );
	}

	public function run( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;
		$table   = CreateConnectSavedSearchesTable::table_name();
		$user_id = get_current_user_id();
		$id      = (int) $request->get_param( 'id' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, label, criteria_json FROM `{$table}` WHERE id = %d AND user_id = %d LIMIT 1",
				$id,
				$user_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error( 'saved_search_not_found', 'Saved search not found.', [ 'status' => 404 ] );
		}

		$criteria = json_decode( (string) ( $row['criteria_json'] ?? '' ), true );
		if ( ! is_array( $criteria ) ) {
			$criteria = [];
		}

		$matches = $this->run_match( $criteria );

		// Stamp last_matched_at so the buyer can see freshness in the listing.
		$wpdb->update(
			$table,
			[ 'last_matched_at' => current_time( 'mysql', true ) ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);

		return new WP_REST_Response( [
			'saved_search_id' => $id,
			'criteria'        => $criteria,
			'matches'         => $matches,
		], 200 );
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;
		$table   = CreateConnectSavedSearchesTable::table_name();
		$user_id = get_current_user_id();
		$id      = (int) $request->get_param( 'id' );

		$deleted = $wpdb->delete(
			$table,
			[ 'id' => $id, 'user_id' => $user_id ],
			[ '%d', '%d' ]
		);

		if ( false === $deleted ) {
			return new WP_Error( 'saved_search_delete_failed', 'Failed to delete saved search.', [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'deleted' => (int) $deleted ], 200 );
	}

	// ─── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Run the buyer's saved criteria through the same matching path the wizard
	 * uses for ad-hoc Find Matches. Mirrors ConnectShortlistService usage.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function run_match( array $criteria ): array {
		$expertise = (array) ( $criteria['expertise'] ?? [] );
		$industry  = (array) ( $criteria['industry'] ?? [] );

		$blog_ids = null;
		if ( ! empty( $expertise ) || ! empty( $industry ) ) {
			$site_slugs = [];
			if ( ! empty( $expertise ) ) {
				$site_slugs = array_merge( $site_slugs, ConnectTaxonomy::site_slugs_for_expertise( $expertise ) );
			}
			if ( ! empty( $industry ) ) {
				$site_slugs = array_merge( $site_slugs, ConnectTaxonomy::site_slugs_for_industry( $industry ) );
			}
			$site_slugs = array_values( array_unique( $site_slugs ) );
			$blog_ids   = empty( $site_slugs ) ? [] : ConnectTaxonomy::blog_ids_for_site_slugs( $site_slugs );
		}

		$proof          = isset( $criteria['proof_of_commitment'] ) ? sanitize_key( (string) $criteria['proof_of_commitment'] ) : '';
		$needs_pilot    = in_array( $proof, [ 'pilot-expected', 'pilot-essential', 'required' ], true ) || ! empty( $criteria['pilot_required'] );
		$needs_trial    = in_array( $proof, [ 'free-test-expected', 'required', 'preferred' ], true ) || ! empty( $criteria['free_trial'] );
		$provider_type  = sanitize_key( (string) ( $criteria['provider_type'] ?? '' ) );

		$provider_repo = new ConnectProviderRepository();
		$providers     = $provider_repo->list_filtered( [
			'blog_ids'        => $blog_ids,
			'provider_type'   => in_array( $provider_type, [ 'platform', 'specialist' ], true ) ? $provider_type : null,
			'deployment_mode' => ! empty( $criteria['deployment_mode'] ) ? $criteria['deployment_mode'] : ( ! empty( $criteria['delivery_model'] ) ? $criteria['delivery_model'] : null ),
			'pilot_available' => $needs_pilot,
			'free_trial'      => $needs_trial,
			'budget_min'      => $criteria['budget_min'] ?? null,
			'budget_max'      => $criteria['budget_max'] ?? null,
			'company_size'    => $criteria['company_size'] ?? null,
		] );

		$company_sizes = [];
		if ( ! empty( $criteria['company_size_band'] ) ) {
			$company_sizes = [ (string) $criteria['company_size_band'] ];
		} elseif ( ! empty( $criteria['company_size'] ) ) {
			$company_sizes = [ (string) $criteria['company_size'] ];
		}

		$deployment = [];
		if ( ! empty( $criteria['deployment_mode'] ) ) {
			$deployment = [ (string) $criteria['deployment_mode'] ];
		} elseif ( ! empty( $criteria['delivery_model'] ) ) {
			$deployment = [ (string) $criteria['delivery_model'] ];
		}

		$sector_values = [];
		if ( ! empty( $criteria['sector'] ) ) {
			$sector_values = array_values(
				array_filter(
					array_map( 'sanitize_key', array_map( 'trim', explode( ',', (string) $criteria['sector'] ) ) )
				)
			);
		}

		$shortlist_criteria = [
			'industries'    => (array) ( $criteria['industry'] ?? [] ),
			'regions'       => ! empty( $criteria['region'] ) ? [ (string) $criteria['region'] ] : [],
			'company_sizes' => $company_sizes,
			'deployment'    => $deployment,
			'keywords'      => [],
			'budget'        => $criteria['budget_min'] ?? 0,
			'sector'        => $sector_values,
			'integrations'  => (array) ( $criteria['integrations'] ?? [] ),
		];

		$shortlist = new ConnectShortlistService();
		$ranked    = $shortlist->shortlist( $providers, $shortlist_criteria, '', PHP_INT_MAX );

		// Annotate with taxonomy tags + strip internal rules, mirroring the RFQ matches endpoint.
		return array_map( static function ( array $provider ) {
			$tags                    = ConnectTaxonomy::tags_for_blog_id( (int) $provider['blog_id'] );
			$provider['expertise_tags'] = $tags['expertise'];
			$provider['industry_tags']  = $tags['industries'];
			unset( $provider['match_rules'] );
			return $provider;
		}, $ranked );
	}

	private function derive_label( array $criteria ): string {
		$parts = [];
		if ( ! empty( $criteria['challenge'] ) ) {
			$parts[] = (string) $criteria['challenge'];
		} elseif ( ! empty( $criteria['expertise'] ) ) {
			$parts[] = implode( ', ', (array) $criteria['expertise'] );
		}
		if ( ! empty( $criteria['region'] ) ) {
			$parts[] = (string) $criteria['region'];
		}

		$label = implode( ' · ', array_filter( $parts ) );
		if ( '' === $label ) {
			$label = 'Untitled saved search';
		}

		return mb_substr( $label, 0, 200 );
	}

	private function hydrate( ?array $row ): array {
		if ( ! $row ) {
			return [];
		}
		$criteria = json_decode( (string) ( $row['criteria_json'] ?? '' ), true );
		return [
			'id'              => (int) ( $row['id'] ?? 0 ),
			'label'           => (string) ( $row['label'] ?? '' ),
			'criteria'        => is_array( $criteria ) ? $criteria : [],
			'last_matched_at' => isset( $row['last_matched_at'] ) ? (string) $row['last_matched_at'] : null,
			'created_at'      => (string) ( $row['created_at'] ?? '' ),
			'updated_at'      => (string) ( $row['updated_at'] ?? '' ),
		];
	}
}
