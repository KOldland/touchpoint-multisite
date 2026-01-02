<?php

namespace KHM\Rest;

use KHM\Services\CpEventIngestionService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller that ingests canonical 4A events from multiple connectors.
 */
class FourAIngestionController {

	private CpEventIngestionService $service;

	/**
	 * @param CpEventIngestionService|null $service Optional ingestion service override for tests.
	 */
	public function __construct( ?CpEventIngestionService $service = null ) {
		$this->service = $service ?: new CpEventIngestionService();
	}

	/**
	 * Wire REST routes.
	 */
	public function register(): void {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'khm/v1',
					'/ingest/ga4',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'ingest_ga4' ),
						'permission_callback' => '__return_true',
					)
				);

				register_rest_route(
					'khm/v1',
					'/ingest/email',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'ingest_email' ),
						'permission_callback' => '__return_true',
					)
				);

				register_rest_route(
					'khm/v1',
					'/ingest/webinar',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'ingest_webinar' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}

	/**
	 * Handle GA4 payloads.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function ingest_ga4( WP_REST_Request $request ) {
		$auth = $this->authorize_request( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			return new WP_Error( 'khm_ga4_empty', __( 'Empty GA4 payload.', 'khm-membership' ), array( 'status' => 400 ) );
		}

		$entries = $this->normalize_event_batch( $params );
		$count   = 0;
		$errors  = array();

		foreach ( $entries as $index => $entry ) {
			$canonical = $this->map_ga4_event( $entry );
			if ( is_wp_error( $canonical ) ) {
				$errors[] = array( 'index' => $index, 'error' => $canonical->get_error_message() );
				continue;
			}

			try {
				$this->service->store_event( $canonical );
				$count++;
			} catch ( \Throwable $e ) {
				$errors[] = array( 'index' => $index, 'error' => $e->getMessage() );
			}
		}

		$this->service->record_ingestion(
			'ga4',
			empty( $errors ),
			empty( $errors ) ? null : array( 'errors' => $errors )
		);

		$status = empty( $errors ) ? 201 : 207;

		return new WP_REST_Response(
			array(
				'stored' => $count,
				'errors' => $errors,
			),
			$status
		);
	}

	/**
	 * Handle ESP webhook payloads (SendGrid, Mailgun, etc.)
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function ingest_email( WP_REST_Request $request ) {
		$auth = $this->authorize_request( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			return new WP_Error( 'khm_email_empty', __( 'Empty email webhook payload.', 'khm-membership' ), array( 'status' => 400 ) );
		}

		$entries = $this->normalize_event_batch( $params );
		$count   = 0;
		$errors  = array();

		foreach ( $entries as $index => $entry ) {
			$canonical = $this->map_email_event( $entry );
			if ( is_wp_error( $canonical ) ) {
				$errors[] = array( 'index' => $index, 'error' => $canonical->get_error_message() );
				continue;
			}

			try {
				$this->service->store_event( $canonical );
				$count++;
			} catch ( \Throwable $e ) {
				$errors[] = array( 'index' => $index, 'error' => $e->getMessage() );
			}
		}

		$this->service->record_ingestion(
			'esp',
			empty( $errors ),
			empty( $errors ) ? null : array( 'errors' => $errors )
		);

		return new WP_REST_Response(
			array(
				'stored' => $count,
				'errors' => $errors,
			),
			empty( $errors ) ? 201 : 207
		);
	}

	/**
	 * Handle webinar attendance payloads.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function ingest_webinar( WP_REST_Request $request ) {
		$auth = $this->authorize_request( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			return new WP_Error( 'khm_webinar_empty', __( 'Empty webinar payload.', 'khm-membership' ), array( 'status' => 400 ) );
		}

		$entries = $this->normalize_event_batch( $params );
		$count   = 0;
		$errors  = array();

		foreach ( $entries as $index => $entry ) {
			$canonical = $this->map_webinar_event( $entry );
			if ( is_wp_error( $canonical ) ) {
				$errors[] = array( 'index' => $index, 'error' => $canonical->get_error_message() );
				continue;
			}

			try {
				$this->service->store_event( $canonical );
				$count++;
			} catch ( \Throwable $e ) {
				$errors[] = array( 'index' => $index, 'error' => $e->getMessage() );
			}
		}

		$this->service->record_ingestion(
			'webinar',
			empty( $errors ),
			empty( $errors ) ? null : array( 'errors' => $errors )
		);

		return new WP_REST_Response(
			array(
				'stored' => $count,
				'errors' => $errors,
			),
			empty( $errors ) ? 201 : 207
		);
	}

	/**
	 * Normalize incoming payload to an array of events.
	 *
	 * @param mixed $payload Payload from request.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_event_batch( $payload ): array {
		if ( isset( $payload['events'] ) && is_array( $payload['events'] ) ) {
			return array_values( $payload['events'] );
		}

		if ( is_array( $payload ) && array_keys( $payload ) === range( 0, count( $payload ) - 1 ) ) {
			return $payload;
		}

		return array( (array) $payload );
	}

	/**
	 * Verify ingestion token (if configured).
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return true|WP_Error
	 */
	private function authorize_request( WP_REST_Request $request ) {
		$token = $this->get_ingest_token();
		if ( empty( $token ) ) {
			return true;
		}

		$provided = (string) $request->get_header( 'x-khm-ingest-key' );
		if ( empty( $provided ) ) {
			$provided = (string) $request->get_param( 'token' );
		}

		if ( empty( $provided ) || ! hash_equals( $token, $provided ) ) {
			return new WP_Error( 'khm_ingest_forbidden', __( 'Invalid ingest token.', 'khm-membership' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Map GA4 event to canonical schema.
	 *
	 * @param array<string,mixed> $entry Raw GA4 event.
	 * @return array<string,mixed>|WP_Error
	 */
	private function map_ga4_event( array $entry ) {
		$event_name = strtolower( (string) ( $entry['event_name'] ?? '' ) );
		if ( empty( $event_name ) ) {
			return new WP_Error( 'khm_ga4_missing_name', __( 'event_name is required for GA4 ingestion.', 'khm-membership' ) );
		}

		$timestamp = $entry['event_timestamp'] ?? $entry['occurred_at'] ?? time();
		if ( is_string( $timestamp ) && is_numeric( $timestamp ) ) {
			$timestamp = (int) $timestamp;
		}
		if ( is_numeric( $timestamp ) && strlen( (string) $timestamp ) > 11 ) {
			$timestamp = (int) floor( (int) $timestamp / 1000 );
		}

		$user_props = $entry['user_properties'] ?? array();
		$event_params = $entry['event_params'] ?? array();

		$email = $this->extract_user_property( $user_props, 'email' );
		if ( empty( $email ) && isset( $entry['actor']['email'] ) ) {
			$email = $entry['actor']['email'];
		}

		$touchpoint = $this->map_touchpoint_from_ga4( $event_name, $event_params );

		return array(
			'event_id'           => $entry['event_id'] ?? wp_generate_uuid4(),
			'occurred_at'        => $timestamp,
			'actor_email'        => $this->normalize_email( $email ),
			'actor_name'         => $this->normalize_string( $this->extract_user_property( $user_props, 'name' ) ?? ( $entry['actor']['name'] ?? null ) ),
			'company_domain'     => $this->derive_company_domain( $entry['company_domain'] ?? null, $email ),
			'source'             => 'ga4',
			'touchpoint'         => $touchpoint,
			'stage_hint'         => $entry['stage_hint'] ?? null,
			'depth_scroll'       => $this->extract_numeric_param( $event_params, 'percent_scrolled' ),
			'depth_dwell_sec'    => $this->extract_numeric_param( $event_params, 'engagement_time_msec', 1000 ),
			'depth_pct_complete' => $this->extract_numeric_param( $event_params, 'video_percent' ),
			'topic_tax'          => $this->collect_topics_from_ga4( $entry, $event_params ),
			'metadata'           => $entry,
		);
	}

	/**
	 * Map ESP events to canonical schema.
	 *
	 * @param array<string,mixed> $entry Raw ESP event.
	 * @return array<string,mixed>|WP_Error
	 */
	private function map_email_event( array $entry ) {
		$email = $entry['email'] ?? $entry['actor']['email'] ?? null;
		if ( empty( $email ) ) {
			return new WP_Error( 'khm_email_missing_actor', __( 'Email address is required for ESP ingestion.', 'khm-membership' ) );
		}

		$type       = strtolower( (string) ( $entry['event'] ?? $entry['type'] ?? 'email_event' ) );
		$touchpoint = $this->map_touchpoint_from_esp( $type );
		$timestamp  = $entry['timestamp'] ?? $entry['occurred_at'] ?? time();

		return array(
			'event_id'           => $entry['event_id'] ?? wp_generate_uuid4(),
			'occurred_at'        => $timestamp,
			'actor_email'        => $this->normalize_email( $email ),
			'actor_name'         => $this->normalize_string( $entry['name'] ?? $entry['actor']['name'] ?? null ),
			'company_domain'     => $this->derive_company_domain( $entry['company_domain'] ?? null, $email ),
			'source'             => 'esp',
			'touchpoint'         => $touchpoint,
			'stage_hint'         => $entry['stage_hint'] ?? null,
			'depth_dwell_sec'    => ! empty( $entry['dwell_seconds'] ) ? (float) $entry['dwell_seconds'] : null,
			'depth_pct_complete' => ! empty( $entry['pct_complete'] ) ? (float) $entry['pct_complete'] : null,
			'topic_tax'          => $entry['topic_tax'] ?? $entry['categories'] ?? null,
			'metadata'           => $entry,
		);
	}

	/**
	 * Map webinar attendance events.
	 *
	 * @param array<string,mixed> $entry Raw webinar event.
	 * @return array<string,mixed>|WP_Error
	 */
	private function map_webinar_event( array $entry ) {
		$email = $entry['participant']['email'] ?? $entry['email'] ?? null;
		if ( empty( $email ) ) {
			return new WP_Error( 'khm_webinar_missing_actor', __( 'Participant email is required for webinar ingestion.', 'khm-membership' ) );
		}

		$join_time    = $entry['join_time'] ?? $entry['occurred_at'] ?? time();
		$leave_time   = $entry['leave_time'] ?? null;
		$attendance   = (float) ( $entry['attendance_pct'] ?? 0 );
		$touchpoint   = ( $attendance >= 80 ) ? 'webinar_full_attend' : ( $attendance >= 40 ? 'webinar_partial_attend' : 'webinar_interest' );
		$dwell_seconds = null;
		if ( $join_time && $leave_time ) {
			$dwell_seconds = max( 0, strtotime( (string) $leave_time ) - strtotime( (string) $join_time ) );
		}

		return array(
			'event_id'           => $entry['event_id'] ?? wp_generate_uuid4(),
			'occurred_at'        => $join_time,
			'actor_email'        => $this->normalize_email( $email ),
			'actor_name'         => $this->normalize_string( $entry['participant']['name'] ?? $entry['name'] ?? null ),
			'company_domain'     => $this->derive_company_domain( $entry['company_domain'] ?? null, $email ),
			'source'             => 'webinar',
			'touchpoint'         => $touchpoint,
			'stage_hint'         => $entry['stage_hint'] ?? 'diagnosis',
			'depth_dwell_sec'    => $dwell_seconds,
			'depth_pct_complete' => $attendance,
			'topic_tax'          => $entry['topic_tax'] ?? array_filter( array( $entry['topic'] ?? null, $entry['webinar_id'] ?? null ) ),
			'metadata'           => $entry,
		);
	}

	private function map_touchpoint_from_ga4( string $event_name, array $params ): string {
		$map = array(
			'scroll_complete'  => 'article_complete',
			'form_submit'      => 'form_demo_request',
			'video_complete'   => 'knowledge_base_completion',
			'file_download'    => 'download_asset',
			'search'           => 'copilot_query',
			'sign_up'          => 'workshop_signup',
			'purchase'         => 'one_to_one_meeting',
			'generate_lead'    => 'form_demo_request',
			'page_view'        => 'article_partial',
		);

		if ( isset( $map[ $event_name ] ) ) {
			return $map[ $event_name ];
		}

		// Allow GA4 to pass explicit touchpoint param.
		if ( isset( $params['touchpoint']['value'] ) ) {
			return sanitize_key( (string) $params['touchpoint']['value'] );
		}

		return 'article_partial';
	}

	/**
	 * Resolve the configured ingestion token (constant > env > option).
	 *
	 * @return string
	 */
	private function get_ingest_token(): string {
		if ( defined( 'KHM_4A_INGEST_TOKEN' ) && KHM_4A_INGEST_TOKEN ) {
			return (string) KHM_4A_INGEST_TOKEN;
		}

		$env = getenv( 'KHM_4A_INGEST_TOKEN' );
		if ( ! empty( $env ) ) {
			return (string) $env;
		}

		return (string) get_option( 'khm_4a_ingest_token', '' );
	}

	private function map_touchpoint_from_esp( string $event_type ): string {
		switch ( $event_type ) {
			case 'open':
			case 'email_open':
				return 'newsletter_click';
			case 'reply':
			case 'email_reply':
				return 'email_reply_long';
			case 'click':
			case 'email_click':
				return 'newsletter_click';
			case 'bounce':
			case 'spamreport':
				return 'email_error';
			default:
				return 'email_event';
		}
	}

	private function collect_topics_from_ga4( array $entry, array $params ): ?array {
		$topics = array();

		if ( ! empty( $entry['topic_tax'] ) && is_array( $entry['topic_tax'] ) ) {
			$topics = array_merge( $topics, $entry['topic_tax'] );
		}
		if ( ! empty( $entry['content_group'] ) ) {
			$topics[] = $entry['content_group'];
		}
		if ( ! empty( $entry['page_category'] ) ) {
			$topics[] = $entry['page_category'];
		}
		if ( isset( $params['page_category']['value'] ) ) {
			$topics[] = $params['page_category']['value'];
		}
		if ( isset( $params['content_group']['value'] ) ) {
			$topics[] = $params['content_group']['value'];
		}

		$topics = array_filter(
			array_map(
				function ( $topic ) {
					return $this->normalize_string( $topic );
				},
				$topics
			)
		);

		return empty( $topics ) ? null : array_values( array_unique( $topics ) );
	}

	private function extract_user_property( $props, string $key ) {
		if ( ! is_array( $props ) ) {
			return null;
		}

		if ( isset( $props[ $key ]['value'] ) ) {
			return $props[ $key ]['value'];
		}

		if ( isset( $props[ $key ] ) && is_scalar( $props[ $key ] ) ) {
			return $props[ $key ];
		}

		return null;
	}

	private function extract_numeric_param( $params, string $key, int $divisor = 1 ): ?float {
		if ( ! is_array( $params ) ) {
			return null;
		}

		if ( isset( $params[ $key ]['value'] ) && is_numeric( $params[ $key ]['value'] ) ) {
			return (float) $params[ $key ]['value'] / $divisor;
		}

		if ( isset( $params[ $key ] ) && is_numeric( $params[ $key ] ) ) {
			return (float) $params[ $key ] / $divisor;
		}

		return null;
	}

	private function normalize_email( ?string $email ): ?string {
		if ( empty( $email ) ) {
			return null;
		}

		$email = sanitize_email( $email );
		return $email ?: null;
	}

	private function normalize_string( $value ): ?string {
		if ( empty( $value ) ) {
			return null;
		}

		return sanitize_text_field( (string) $value );
	}

	private function derive_company_domain( $provided, ?string $email ): ?string {
		if ( ! empty( $provided ) ) {
			return sanitize_text_field( (string) $provided );
		}

		if ( empty( $email ) || false === strpos( $email, '@' ) ) {
			return null;
		}

		return substr( strrchr( $email, '@' ), 1 );
	}
}
