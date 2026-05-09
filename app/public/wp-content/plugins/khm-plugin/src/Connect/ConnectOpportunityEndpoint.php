<?php

namespace KHM\Connect;

use KHM\Services\SponsorAffinityService;
use KHM\Services\SponsorService;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

class ConnectOpportunityEndpoint {

	private ConnectOpportunityRepository $opportunities;

	private ConnectProviderRepository $providers;

	private ConnectIntroThreadRepository $threads;

	public function __construct(
		?ConnectOpportunityRepository $opportunities = null,
		?ConnectProviderRepository $providers = null,
		?ConnectIntroThreadRepository $threads = null
	) {
		$this->opportunities = $opportunities ?? new ConnectOpportunityRepository();
		$this->providers     = $providers ?? new ConnectProviderRepository();
		$this->threads       = $threads ?? new ConnectIntroThreadRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'khm/v1',
			'/connect/opportunities/mine',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_mine' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/opportunities/mine/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_mine' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/opportunities/mine/(?P<id>\d+)/accept',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'accept_mine' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return SponsorService::get_user_sponsor( get_current_user_id() ) !== null;
	}

	public function list_mine( WP_REST_Request $request ) {
		$sponsor_id = $this->resolve_sponsor_id();
		if ( is_wp_error( $sponsor_id ) ) {
			return $sponsor_id;
		}

		$rows = $this->opportunities->list_inbox_for_sponsor( (int) $sponsor_id );

		// Filter by request_type if provided
		$request_type_filter = $request->get_param( 'request_type' );
		if ( ! empty( $request_type_filter ) ) {
			$request_type_filter = sanitize_key( $request_type_filter );
			$rows = array_filter(
				$rows,
				function( $row ) use ( $request_type_filter ) {
					return ( $row['request_type'] ?? 'direct_connection' ) === $request_type_filter;
				}
			);
		}

		return rest_ensure_response(
			array(
				'success'       => true,
				'opportunities' => array_values( array_map( array( $this, 'format_anonymized_opportunity' ), $rows ) ),
			)
		);
	}

	public function get_mine( WP_REST_Request $request ) {
		$sponsor_id = $this->resolve_sponsor_id();
		if ( is_wp_error( $sponsor_id ) ) {
			return $sponsor_id;
		}

		$opportunity = $this->opportunities->get_inbox_for_sponsor( (int) $request->get_param( 'id' ), (int) $sponsor_id );
		if ( ! is_array( $opportunity ) ) {
			return new WP_Error( 'connect_opportunity_not_found', __( 'Opportunity not found.', 'khm-membership' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'opportunity' => $this->format_anonymized_opportunity( $opportunity ),
			)
		);
	}

	public function accept_mine( WP_REST_Request $request ) {
		$sponsor_id = $this->resolve_sponsor_id();
		if ( is_wp_error( $sponsor_id ) ) {
			return $sponsor_id;
		}

		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();

		$provider_id = isset( $params['provider_id'] ) ? (int) $params['provider_id'] : 0;
		if ( $provider_id <= 0 ) {
			return new WP_Error( 'connect_provider_required', __( 'Provider is required to accept an opportunity.', 'khm-membership' ), array( 'status' => 400 ) );
		}

		$engaged_option = isset( $params['engaged_option'] ) ? sanitize_key( $params['engaged_option'] ) : null;
		if ( $engaged_option && ! in_array( $engaged_option, array( 'option_1', 'option_2' ), true ) ) {
			$engaged_option = null;
		}

		$provider = $this->providers->get_by_id( $provider_id );
		if ( ! is_array( $provider ) || (int) $provider['sponsor_id'] !== (int) $sponsor_id ) {
			return new WP_Error( 'connect_provider_forbidden', __( 'You cannot accept this opportunity with that provider.', 'khm-membership' ), array( 'status' => 403 ) );
		}

		$opportunity_id = (int) $request->get_param( 'id' );
		$opportunity = $this->opportunities->get_inbox_for_sponsor( $opportunity_id, (int) $sponsor_id );
		if ( ! is_array( $opportunity ) ) {
			return new WP_Error( 'connect_opportunity_not_found', __( 'Opportunity not found.', 'khm-membership' ), array( 'status' => 404 ) );
		}

		$request_type = (string) ( $opportunity['request_type'] ?? 'direct_connection' );

		if ( 'direct_connection' === $request_type ) {
			$outreach_cap    = (int) get_option( 'khm_connect_cold_outreach_cap', 3 );
			$active_outreach = $this->threads->count_active_direct_outreach_threads( (int) $sponsor_id );
			if ( $active_outreach >= $outreach_cap && 'sponsor_accepted' !== (string) ( $opportunity['opportunity_status'] ?? '' ) ) {
				return new WP_Error(
					'connect_outreach_limit_reached',
					/* translators: %d = configured cold-outreach cap */
					sprintf( __( 'You already have %d active cold outreach threads. Confirm or close one before starting another.', 'khm-membership' ), $outreach_cap ),
					array( 'status' => 409 )
				);
			}

			if ( $this->threads->has_recent_rejected_pair( $provider_id, (string) ( $opportunity['actor_email_hash'] ?? '' ), 90 ) ) {
				return new WP_Error(
					'connect_outreach_cooldown',
					__( 'This buyer was rejected recently. You can retry after the 90-day cooldown.', 'khm-membership' ),
					array( 'status' => 409 )
				);
			}
		}

		if ( ! in_array( (string) ( $opportunity['opportunity_status'] ?? '' ), array( 'detected', 'offered', 'sponsor_accepted' ), true ) ) {
			return new WP_Error( 'connect_opportunity_locked', __( 'Opportunity can no longer be accepted.', 'khm-membership' ), array( 'status' => 409 ) );
		}

		$updated = $this->opportunities->mark_sponsor_acceptance( $opportunity_id, (int) $sponsor_id, $provider_id, $engaged_option );
		if ( ! $updated ) {
			return new WP_Error( 'connect_opportunity_accept_failed', __( 'Unable to accept opportunity.', 'khm-membership' ), array( 'status' => 500 ) );
		}

		// Flow 3: Auto-create a sponsor-initiated intro thread so the conversation
		// lands in the Intro Inbox immediately. The buyer_token is stored for future
		// buyer claim. opening_message is optional; a default is used if omitted.
		$opening_message = sanitize_textarea_field( (string) ( $params['opening_message'] ?? '' ) );

		$existing_thread = $this->threads->get_thread_by_opportunity_id( $opportunity_id, (int) $sponsor_id );
		$thread_id       = is_array( $existing_thread ) ? (int) ( $existing_thread['id'] ?? 0 ) : 0;

		if ( 'rfp_request' === $request_type && $thread_id <= 0 && '' === $opening_message ) {
			return new WP_Error( 'connect_opening_message_required', __( 'A light proposal note is required before moving this RFP to Inbox.', 'khm-membership' ), array( 'status' => 422 ) );
		}

		if ( $thread_id <= 0 ) {
			$thread_id = $this->threads->create_sponsor_initiated_thread(
				array(
					'provider_id'      => $provider_id,
					'sponsor_id'       => (int) $sponsor_id,
					'opportunity_id'   => $opportunity_id,
					'request_type'     => $request_type,
					'actor_email_hash' => (string) ( $opportunity['actor_email_hash'] ?? '' ),
					'engaged_option'   => $engaged_option,
					'opening_message'  => $opening_message,
				)
			);
		}

		if ( 'direct_connection' === $request_type ) {
			do_action( 'khm_cold_outreach_accepted', $opportunity_id, (int) $sponsor_id, $provider_id, $thread_id );
		}

		$fresh = $this->opportunities->get_inbox_for_sponsor( $opportunity_id, (int) $sponsor_id );

		return rest_ensure_response(
			array(
				'success'     => true,
				'thread_id'   => $thread_id > 0 ? $thread_id : null,
				'opportunity' => is_array( $fresh ) ? $this->format_anonymized_opportunity( $fresh ) : null,
			)
		);
	}

	private function resolve_sponsor_id() {
		if ( current_user_can( 'manage_options' ) ) {
			$sponsor_id = absint( $_GET['sponsor_id'] ?? 0 );
			if ( $sponsor_id > 0 ) {
				return $sponsor_id;
			}
		}

		$sponsor = SponsorService::get_user_sponsor( get_current_user_id() );
		if ( ! is_array( $sponsor ) || empty( $sponsor['id'] ) ) {
			return new WP_Error( 'connect_sponsor_required', __( 'Sponsor account required.', 'khm-membership' ), array( 'status' => 403 ) );
		}

		return (int) $sponsor['id'];
	}

	private function format_anonymized_opportunity( array $row ): array {
		$rfp_meta = $row['rfp_metadata'] ?? null;
		if ( is_string( $rfp_meta ) ) {
			$rfp_meta = json_decode( $rfp_meta, true );
		}

		// Build anonymised buyer profile from rfp_metadata signal fields (segment/region/intent)
		// plus fallbacks derived from structured opportunity data.
		$anon_profile = array(
			'segment' => ( is_array( $rfp_meta ) && ! empty( $rfp_meta['segment'] ) )
				? (string) $rfp_meta['segment']
				: null,
			'region'  => ( is_array( $rfp_meta ) && ! empty( $rfp_meta['region'] ) )
				? (string) $rfp_meta['region']
				: null,
			'intent'  => ( is_array( $rfp_meta ) && ! empty( $rfp_meta['intent'] ) )
				? (string) $rfp_meta['intent']
				: null,
		);

		// Strip signal fields from the public rfp_metadata payload so they aren't duplicated.
		$public_rfp_meta = is_array( $rfp_meta )
			? array_diff_key( $rfp_meta, array_flip( [ 'segment', 'region', 'intent' ] ) )
			: null;

		$affinity_service = new SponsorAffinityService();
		$raw_signals      = is_array( $row['affinity_signals'] ?? null )
			? $row['affinity_signals']
			: array();
		$affinity_score   = $affinity_service->calculate_score( $raw_signals );
		$affinity_tier    = $affinity_service->resolve_tier( $affinity_score );
		$unit_price_cents = (int) ( $row['unit_price_cents'] ?? 0 );
		$affinity_payload = array(
			'score'                 => $affinity_score,
			'tier'                  => $affinity_tier['tier'] ?? null,
			'label'                 => $affinity_tier['label'] ?? null,
			'uplift'                => $affinity_tier['uplift'] ?? 1.00,
			'adjusted_price_cents'  => $affinity_tier
				? (int) round( $unit_price_cents * ( $affinity_tier['uplift'] ?? 1.00 ) )
				: $unit_price_cents,
			'signals'               => $raw_signals,
		);

		return array(
			'id'                  => (int) ( $row['id'] ?? 0 ),
			'commercial_tier'     => (string) ( $row['commercial_tier'] ?? '' ),
			'internal_stage'      => (string) ( $row['internal_stage'] ?? '' ),
			'person_score'        => round( (float) ( $row['person_score'] ?? 0 ) / 100, 4 ),
			'opportunity_status'  => (string) ( $row['opportunity_status'] ?? 'detected' ),
			'request_type'        => (string) ( $row['request_type'] ?? 'direct_connection' ),
			'rfp_metadata'        => $public_rfp_meta ?: null,
			'anonymised_profile'  => $anon_profile,
			'engaged_option'      => $row['engaged_option'] ? (string) $row['engaged_option'] : null,
			'pricing_model'       => (string) ( $row['pricing_model'] ?? 'cpl' ),
			'unit_price_cents'    => $unit_price_cents,
			'commission_eligible' => (bool) ( (int) ( $row['commission_eligible'] ?? 0 ) ),
			'provider_id'         => (int) ( $row['provider_id'] ?? 0 ),
			'created_at'          => (string) ( $row['created_at'] ?? '' ),
			'affinity'            => $affinity_payload,
		);
	}
}
