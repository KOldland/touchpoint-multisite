<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

use KHM\Migrations\ConnectWorkflowMigration;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Buyer-facing RFQ and request endpoints. Requires active KHM membership.
 *
 * POST   /khm/v1/connect/rfq                          – create an RFQ (alias: /connect/rfp)
 * GET    /khm/v1/connect/rfq/{id}/matches             – ranked provider matches for an RFQ
 * POST   /khm/v1/connect/directory/{provider_id}/request – send a direct or RFQ-backed request
 */
class ConnectRfqEndpoint {

	private const MAX_ACTIVE_RFQS     = 3;
	private const MAX_ACTIVE_REQUESTS = 3;

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		$create_args = [
			'expertise'               => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'default' => [] ],
			'industry'                => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'default' => [] ],
			'budget_min'              => [ 'type' => 'integer', 'minimum' => 0 ],
			'budget_max'              => [ 'type' => 'integer', 'minimum' => 0 ],
			'company_size'            => [ 'type' => 'integer', 'minimum' => 1 ],
			'provider_type'           => [ 'type' => 'string' ],
			'partner_posture'         => [ 'type' => 'string' ],
			'deployment_needed'       => [ 'type' => 'string' ],
			'deployment_mode'         => [ 'type' => 'string' ],
			'onboarding_style'        => [ 'type' => 'string' ],
			'installation_preference' => [ 'type' => 'string' ],
			'proof_of_commitment'     => [ 'type' => 'string' ],
			'pilot_required'          => [ 'type' => 'boolean', 'default' => false ],
			'criteria_priority_order' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'default' => [] ],
			'challenge'               => [ 'type' => 'string' ],
			'solution_types'          => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'default' => [] ],
			'sector'                  => [ 'type' => 'string' ],
			'region'                  => [ 'type' => 'string' ],
			'company_size_band'       => [ 'type' => 'string' ],
			'integrations'            => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'default' => [] ],
			'integrations_other'      => [ 'type' => 'string' ],
			'delivery_model'          => [ 'type' => 'string' ],
			'engagement_model'        => [ 'type' => 'string' ],
			'free_trial'              => [ 'type' => 'boolean', 'default' => false ],
		];

		// Register /rfq (primary) and /rfp (legacy alias) so cached JS, external bookmarks,
		// and in-flight integrations keep working through the rename window.
		foreach ( [ 'rfq', 'rfp' ] as $slug ) {
			register_rest_route( 'khm/v1', "/connect/{$slug}", [
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_rfq' ],
				'permission_callback' => [ $this, 'require_active_member' ],
				'args'                => $create_args,
			] );

			register_rest_route( 'khm/v1', "/connect/{$slug}/(?P<id>\\d+)/matches", [
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_matches' ],
				'permission_callback' => [ $this, 'require_active_member' ],
				'args'                => [
					'sort' => [ 'type' => 'string', 'enum' => [ 'best_match', 'best_price' ], 'default' => 'best_match' ],
				],
			] );
		}

		register_rest_route( 'khm/v1', '/connect/directory/(?P<provider_id>\d+)/request', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'send_request' ],
			'permission_callback' => [ $this, 'require_active_member' ],
			'args'                => [
				'rfq_id' => [ 'type' => 'integer', 'minimum' => 1 ],
				'rfp_id' => [ 'type' => 'integer', 'minimum' => 1 ],
			],
		] );
	}

	// ─── Permission ────────────────────────────────────────────────────────────

	public function require_active_member(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', 'Authentication required.', [ 'status' => 401 ] );
		}

		if ( ! $this->user_has_active_membership( get_current_user_id() ) ) {
			return new WP_Error( 'rest_forbidden', 'An active KHM membership is required.', [ 'status' => 403 ] );
		}

		return true;
	}

	// ─── Handlers ──────────────────────────────────────────────────────────────

	/**
	 * POST /khm/v1/connect/rfq (alias: /khm/v1/connect/rfp)
	 * Create a new RFQ for the authenticated buyer.
	 */
	public function create_rfq( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$repo    = new ConnectRfqRepository();

		if ( $repo->count_active_for_user( $user_id ) >= self::MAX_ACTIVE_RFQS ) {
			return new WP_Error(
				'rfq_cap_reached',
				sprintf( 'You can have at most %d active RFQs at a time.', self::MAX_ACTIVE_RFQS ),
				[ 'status' => 422 ]
			);
		}

		$id = $repo->create( [
			'user_id'                 => $user_id,
			'expertise'               => (array) ( $request->get_param( 'expertise' ) ?: [] ),
			'industry'                => (array) ( $request->get_param( 'industry' ) ?: [] ),
			'budget_min'              => $request->get_param( 'budget_min' ),
			'budget_max'              => $request->get_param( 'budget_max' ),
			'company_size'            => $request->get_param( 'company_size' ),
			'provider_type'           => $request->get_param( 'provider_type' ),
			'partner_posture'         => $request->get_param( 'partner_posture' ),
			'deployment_needed'       => $request->get_param( 'deployment_needed' ) ?: '',
			'deployment_mode'         => $request->get_param( 'deployment_mode' ),
			'onboarding_style'        => $request->get_param( 'onboarding_style' ),
			'installation_preference' => $request->get_param( 'installation_preference' ),
			'proof_of_commitment'     => $request->get_param( 'proof_of_commitment' ),
			'pilot_required'          => (bool) $request->get_param( 'pilot_required' ),
			'criteria_priority_order' => (array) ( $request->get_param( 'criteria_priority_order' ) ?: [] ),
			'challenge'               => $request->get_param( 'challenge' ),
			'solution_types'          => (array) ( $request->get_param( 'solution_types' ) ?: [] ),
			'sector'                  => $request->get_param( 'sector' ),
			'region'                  => $request->get_param( 'region' ),
			'company_size_band'       => $request->get_param( 'company_size_band' ),
			'integrations'            => (array) ( $request->get_param( 'integrations' ) ?: [] ),
			'integrations_other'      => $request->get_param( 'integrations_other' ),
			'delivery_model'          => $request->get_param( 'delivery_model' ),
			'engagement_model'        => $request->get_param( 'engagement_model' ),
			'free_trial'              => (bool) $request->get_param( 'free_trial' ),
		] );

		if ( $id <= 0 ) {
			return new WP_Error( 'rfq_create_failed', 'Failed to save RFQ.', [ 'status' => 500 ] );
		}

		$created = $repo->get( $id );
		return new WP_REST_Response( [ 'rfq' => $created, 'rfp' => $created ], 201 );
	}

	/**
	 * GET /khm/v1/connect/rfq/{id}/matches (alias: /khm/v1/connect/rfp/{id}/matches)
	 * Return providers ranked against the RFQ criteria.
	 */
	public function get_matches( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$repo    = new ConnectRfqRepository();
		$rfq     = $repo->get_for_user( $user_id, (int) $request->get_param( 'id' ) );

		if ( ! $rfq ) {
			return new WP_Error( 'rfq_not_found', 'RFQ not found.', [ 'status' => 404 ] );
		}

		// Resolve matching blog_ids from RFQ expertise + industry filters
		$blog_ids = $this->blog_ids_from_rfq( $rfq );

		$proof_of_commitment = isset( $rfq['proof_of_commitment'] ) ? sanitize_key( (string) $rfq['proof_of_commitment'] ) : '';
		$needs_pilot = in_array( $proof_of_commitment, [ 'pilot-expected', 'pilot-essential', 'required' ], true ) || ! empty( $rfq['pilot_required'] );
		$needs_trial = in_array( $proof_of_commitment, [ 'free-test-expected', 'required', 'preferred' ], true ) || ! empty( $rfq['free_trial'] );

		$provider_type = $this->map_posture_to_provider_type( (string) ( $rfq['partner_posture'] ?? '' ) );
		if ( '' === $provider_type ) {
			$provider_type = sanitize_key( (string) ( $rfq['provider_type'] ?? '' ) );
		}

		$provider_repo = new ConnectProviderRepository();
		$providers     = $provider_repo->list_filtered( [
			'blog_ids'        => $blog_ids,
			'provider_type'   => in_array( $provider_type, [ 'platform', 'specialist' ], true ) ? $provider_type : null,
			'deployment_mode' => ! empty( $rfq['deployment_mode'] ) ? $rfq['deployment_mode'] : ( ! empty( $rfq['delivery_model'] ) ? $rfq['delivery_model'] : null ),
			'pilot_available' => $needs_pilot,
			'free_trial'      => $needs_trial,
			'budget_min'      => $rfq['budget_min'],
			'budget_max'      => $rfq['budget_max'],
			'company_size'    => $rfq['company_size'],
		] );

		$company_sizes = [];
		if ( ! empty( $rfq['company_size_band'] ) ) {
			$company_sizes = [ $rfq['company_size_band'] ];
		} elseif ( ! empty( $rfq['company_size'] ) ) {
			$company_sizes = [ (string) $rfq['company_size'] ];
		}

		$deployment = [];
		if ( ! empty( $rfq['deployment_mode'] ) ) {
			$deployment = [ $rfq['deployment_mode'] ];
		} elseif ( ! empty( $rfq['delivery_model'] ) ) {
			$deployment = [ $rfq['delivery_model'] ];
		} elseif ( ! empty( $rfq['deployment_needed'] ) ) {
			$deployment = [ $rfq['deployment_needed'] ];
		}

		$sector_values = [];
		if ( ! empty( $rfq['sector'] ) ) {
			$sector_values = array_values(
				array_filter(
					array_map( 'sanitize_key', array_map( 'trim', explode( ',', (string) $rfq['sector'] ) ) )
				)
			);
		}

		$criteria = [
			'industries'    => $rfq['industry'],
			'regions'       => ! empty( $rfq['region'] ) ? [ $rfq['region'] ] : [],
			'company_sizes' => $company_sizes,
			'deployment'    => $deployment,
			'keywords'      => [],
			'budget'        => $rfq['budget_min'] ?? 0,
			'sector'        => $sector_values,
			'integrations'  => $rfq['integrations'] ?? [],
		];

		$shortlist = new ConnectShortlistService();
		$ranked    = $shortlist->shortlist( $providers, $criteria, '', PHP_INT_MAX );

		$sort = sanitize_key( (string) ( $request->get_param( 'sort' ) ?: 'best_match' ) );
		if ( $sort === 'best_price' ) {
			usort(
				$ranked,
				static fn( array $a, array $b ) => ( $a['budget_min'] ?? PHP_INT_MAX ) <=> ( $b['budget_min'] ?? PHP_INT_MAX )
			);
		}

		// Annotate with taxonomy tags
		$ranked = array_map( function ( array $provider ) {
			$tags                    = ConnectTaxonomy::tags_for_blog_id( (int) $provider['blog_id'] );
			$provider['expertise_tags'] = $tags['expertise'];
			$provider['industry_tags']  = $tags['industries'];
			// Strip internal rules from buyer-facing output
			unset( $provider['match_rules'] );
			return $provider;
		}, $ranked );

		return new WP_REST_Response( [ 'rfq_id' => $rfq['id'], 'matches' => array_values( $ranked ) ], 200 );
	}

	private function map_posture_to_provider_type( string $posture ): string {
		$posture = sanitize_key( $posture );
		if ( 'established-platform' === $posture ) {
			return 'platform';
		}
		if ( 'specialist-best-of-breed' === $posture ) {
			return 'specialist';
		}

		return '';
	}

	/**
	 * POST /khm/v1/connect/directory/{provider_id}/request
	 * Send a direct or RFQ-backed intro request to a provider.
	 */
	public function send_request( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id     = get_current_user_id();
		$provider_id = (int) $request->get_param( 'provider_id' );
		// Accept either rfq_id (current) or rfp_id (legacy) for the rename window.
		$rfq_id      = (int) ( $request->get_param( 'rfq_id' ) ?: $request->get_param( 'rfp_id' ) ?: 0 );

		// Enforce active request cap
		if ( $this->count_active_requests( $user_id ) >= self::MAX_ACTIVE_REQUESTS ) {
			return new WP_Error(
				'request_cap_reached',
				sprintf( 'You have reached the maximum of %d active outreach requests.', self::MAX_ACTIVE_REQUESTS ),
				[ 'status' => 422 ]
			);
		}

		$user         = get_userdata( $user_id );
		$buyer_email  = $user ? $user->user_email : '';

		if ( '' === $buyer_email ) {
			return new WP_Error( 'buyer_email_missing', 'Could not resolve buyer email.', [ 'status' => 500 ] );
		}

		$request_type = $rfq_id > 0 ? 'rfq_request' : 'direct_connection';
		$rfq_meta     = [];

		if ( $rfq_id > 0 ) {
			$rfq_repo = new ConnectRfqRepository();
			$rfq      = $rfq_repo->get_for_user( $user_id, $rfq_id );
			if ( $rfq ) {
				$rfq_meta = $rfq;
			}
		}

		$opp_repo = new ConnectOpportunityRepository();
		$opp_id   = $opp_repo->create_engaged_opportunity( [
			'actor_email'  => $buyer_email,
			'request_type' => $request_type,
			'rfq_metadata' => $rfq_meta,
			'provider_id'  => $provider_id,
			'source'       => 'buyer_directory',
		] );

		if ( ! $opp_id ) {
			return new WP_Error( 'request_failed', 'Failed to create request.', [ 'status' => 500 ] );
		}

		// Link the WP user to the opportunity for cap tracking
		global $wpdb;
		$wpdb->update(
			ConnectWorkflowMigration::opportunities_table_name(),
			[ 'buyer_account_id' => $user_id ],
			[ 'id' => $opp_id ],
			[ '%d' ],
			[ '%d' ]
		);

		return new WP_REST_Response( [ 'opportunity_id' => $opp_id ], 201 );
	}

	// ─── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Translate RFQ expertise + industry into blog_ids.
	 * Returns null when no filter set (all sites).
	 *
	 * @return int[]|null
	 */
	private function blog_ids_from_rfq( array $rfq ): ?array {
		$expertise = (array) ( $rfq['expertise'] ?? [] );
		$industry  = (array) ( $rfq['industry'] ?? [] );

		if ( empty( $expertise ) && empty( $industry ) ) {
			return null;
		}

		$site_slugs = [];

		if ( ! empty( $expertise ) ) {
			$site_slugs = array_merge( $site_slugs, ConnectTaxonomy::site_slugs_for_expertise( $expertise ) );
		}

		if ( ! empty( $industry ) ) {
			$site_slugs = array_merge( $site_slugs, ConnectTaxonomy::site_slugs_for_industry( $industry ) );
		}

		$site_slugs = array_values( array_unique( $site_slugs ) );

		if ( empty( $site_slugs ) ) {
			return [];
		}

		return ConnectTaxonomy::blog_ids_for_site_slugs( $site_slugs );
	}

	/**
	 * Count active (non-closed) requests for a user, identified by buyer_account_id.
	 * Falls back to zero if the column doesn't exist yet.
	 */
	private function count_active_requests( int $user_id ): int {
		global $wpdb;
		$table = ConnectWorkflowMigration::opportunities_table_name();

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}`
			 WHERE buyer_account_id = %d
			   AND opportunity_status NOT IN ('closed', 'rejected', 'cancelled')",
			$user_id
		) );

		return (int) $count;
	}

	/**
	 * Check if the user has an active KHM membership row.
	 */
	private function user_has_active_membership( int $user_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'user_membership';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return false;
		}

		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d AND status IN ('active', 'trial', 'trialing')",
			$user_id
		) );
	}
}
