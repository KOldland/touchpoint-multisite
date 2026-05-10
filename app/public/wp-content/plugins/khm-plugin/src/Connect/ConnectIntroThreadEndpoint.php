<?php

namespace KHM\Connect;

use KHM\Sponsors\SponsorMigration;
use KHM\Services\SponsorService;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

class ConnectIntroThreadEndpoint {

	private ConnectIntroThreadRepository $threads;

	private ConnectProviderRepository $providers;

	private ConnectOpportunityRepository $opportunities;

	public function __construct( ?ConnectIntroThreadRepository $threads = null, ?ConnectProviderRepository $providers = null, ?ConnectOpportunityRepository $opportunities = null ) {
		$this->threads        = $threads ?? new ConnectIntroThreadRepository();
		$this->providers      = $providers ?? new ConnectProviderRepository();
		$this->opportunities  = $opportunities ?? new ConnectOpportunityRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'khm/v1',
			'/connect/intro-threads',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_thread' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/intro-threads/(?P<id>\d+)/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'buyer_get_status' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/intro-threads/(?P<id>\d+)/handover',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'buyer_request_handover' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/intro-threads/mine',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_mine' ),
				'permission_callback' => array( $this, 'check_sponsor_permission' ),
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/intro-threads/mine/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_mine' ),
				'permission_callback' => array( $this, 'check_sponsor_permission' ),
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/intro-threads/mine/(?P<id>\d+)/reply',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reply_mine' ),
				'permission_callback' => array( $this, 'check_sponsor_permission' ),
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/intro-threads/mine/(?P<id>\d+)/handover/confirm',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'confirm_handover' ),
				'permission_callback' => array( $this, 'check_sponsor_permission' ),
			)
		);

		register_rest_route(
			'khm/v1',
			'/connect/intro-threads/mine/(?P<id>\d+)/commission',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_commission_rate' ),
				'permission_callback' => array( $this, 'check_sponsor_permission' ),
			)
		);
	}

	public function check_sponsor_permission(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return SponsorService::get_user_sponsor( get_current_user_id() ) !== null;
	}

	public function create_thread( WP_REST_Request $request ) {
		$params     = $this->get_json_params( $request );
		$provider_id = isset( $params['provider_id'] ) ? (int) $params['provider_id'] : 0;
		$provider   = $provider_id > 0 ? $this->providers->get_by_id( $provider_id ) : null;

		if ( ! is_array( $provider ) || 'active' !== (string) ( $provider['status'] ?? 'inactive' ) ) {
			return new WP_Error( 'connect_intro_provider_not_found', __( 'Provider not available for intros.', 'khm-membership' ), array( 'status' => 404 ) );
		}

		$buyer_name  = sanitize_text_field( (string) ( $params['buyer_name'] ?? '' ) );
		$buyer_email = sanitize_email( (string) ( $params['buyer_email'] ?? '' ) );
		$buyer_sector = sanitize_text_field( (string) ( $params['buyer_sector'] ?? '' ) );
		$buyer_company_size = sanitize_text_field( (string) ( $params['buyer_company_size'] ?? '' ) );
		$buyer_job_title = sanitize_text_field( (string) ( $params['buyer_job_title'] ?? '' ) );
		$buyer_country = sanitize_text_field( (string) ( $params['buyer_country'] ?? '' ) );
		$message     = sanitize_textarea_field( (string) ( $params['message'] ?? '' ) );

		if ( '' === $buyer_name || '' === $buyer_email || ! is_email( $buyer_email ) || '' === $buyer_sector || '' === $buyer_company_size || '' === $buyer_job_title || '' === $buyer_country || '' === $message ) {
			return new WP_Error( 'connect_intro_invalid_payload', __( 'Name, email, sector, company size, job title, country, and message are required.', 'khm-membership' ), array( 'status' => 400 ) );
		}

		// Fetch opportunity context (request_type and engaged_option) if opportunity_id provided
		$opportunity_id = isset( $params['opportunity_id'] ) ? (int) $params['opportunity_id'] : 0;
		$request_type = 'direct_connection';
		$engaged_option = null;

		if ( $opportunity_id > 0 ) {
			$opportunity = $this->opportunities->get_by_id( $opportunity_id );
			if ( is_array( $opportunity ) ) {
				$request_type = (string) ( $opportunity['request_type'] ?? 'direct_connection' );
				$engaged_option = $opportunity['engaged_option'] ? (string) $opportunity['engaged_option'] : null;
			}
		}

		$thread_id = $this->threads->create_thread(
			array(
				'opportunity_id' => $opportunity_id,
				'provider_id'   => $provider_id,
				'sponsor_id'    => (int) ( $provider['sponsor_id'] ?? 0 ),
				'session_id'    => sanitize_text_field( (string) ( $params['session_id'] ?? '' ) ),
				'request_type'  => $request_type,
				'buyer_name'    => $buyer_name,
				'buyer_company' => sanitize_text_field( (string) ( $params['buyer_company'] ?? '' ) ),
				'buyer_email'   => $buyer_email,
				'buyer_phone'   => sanitize_text_field( (string) ( $params['buyer_phone'] ?? '' ) ),
				'buyer_linkedin' => esc_url_raw( (string) ( $params['buyer_linkedin'] ?? '' ) ),
				'buyer_sector' => $buyer_sector,
				'buyer_company_size' => $buyer_company_size,
				'buyer_job_title' => $buyer_job_title,
				'buyer_city' => sanitize_text_field( (string) ( $params['buyer_city'] ?? '' ) ),
				'buyer_country' => $buyer_country,
				'engaged_option'=> $engaged_option,
				'message'       => $message,
			)
		);

		$thread = $thread_id > 0 ? $this->threads->get_thread( $thread_id ) : null;
		if ( ! is_array( $thread ) ) {
			return new WP_Error( 'connect_intro_create_failed', __( 'Unable to create intro request.', 'khm-membership' ), array( 'status' => 500 ) );
		}

		$this->maybe_notify_sponsor( $provider, $thread, $message );
		$this->maybe_notify_buyer_ack( $provider, $thread );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => (int) ( $thread['id'] ?? 0 ),
				'thread'  => $this->format_thread_summary( $thread, $provider ),
				'buyer_token' => (string) ( $thread['buyer_token'] ?? '' ),
			)
		);
	}

	public function buyer_get_status( WP_REST_Request $request ) {
		$buyer_token = $this->resolve_buyer_token( $request );
		$thread      = $this->threads->get_thread_by_token( (int) $request->get_param( 'id' ), $buyer_token );

		if ( ! is_array( $thread ) ) {
			return new WP_Error( 'connect_thread_forbidden', __( 'Thread not found.', 'khm-membership' ), array( 'status' => 404 ) );
		}

		$handover = $this->threads->get_handover_for_thread( (int) $thread['id'] );
		$messages = $this->threads->list_messages( (int) $thread['id'] );
		$messages = $this->filter_handover_gated_messages( $messages, (string) ( $thread['handover_status'] ?? 'not_started' ) );
		$milestones = $this->threads->list_milestones( (int) $thread['id'] );

		return rest_ensure_response(
			array(
				'thread_id'              => (int) $thread['id'],
				'status'                 => $thread['status'],
				'handover_status'        => $thread['handover_status'],
				'seller_response_status' => (string) ( $thread['seller_response_status'] ?? 'not_requested' ),
				'message_count'          => (int) $thread['message_count'],
				'thread'                 => $this->format_thread_summary( $thread ),
				'messages'               => $messages,
				'milestones'             => $milestones,
				'handover'               => is_array( $handover ) ? $handover : null,
			)
		);
	}

	public function buyer_request_handover( WP_REST_Request $request ) {
		$params = $this->get_json_params( $request );
		$thread = $this->threads->get_thread_by_token( (int) $request->get_param( 'id' ), $this->resolve_buyer_token( $request, $params ) );

		if ( ! is_array( $thread ) ) {
			return new WP_Error( 'connect_handover_forbidden', __( 'Handover request is not valid.', 'khm-membership' ), array( 'status' => 403 ) );
		}

		$handover = $this->threads->request_handover( (int) $thread['id'] );
		if ( ! is_array( $handover ) ) {
			return new WP_Error( 'connect_handover_create_failed', __( 'Unable to create handover request.', 'khm-membership' ), array( 'status' => 500 ) );
		}

		$provider = $this->providers->get_by_id( (int) $thread['provider_id'] );
		$this->maybe_notify_sponsor_handover_request( is_array( $provider ) ? $provider : array(), $thread, $handover );

		return rest_ensure_response(
			array(
				'success'  => true,
				'handover' => $handover,
			)
		);
	}

	public function list_mine( WP_REST_Request $request ) {
		$sponsor_id = $this->resolve_sponsor_id();
		if ( is_wp_error( $sponsor_id ) ) {
			return $sponsor_id;
		}

		$threads = $this->threads->list_for_sponsor( (int) $sponsor_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'threads' => array_values(
					array_map(
						function ( array $thread ): array {
							return $this->format_thread_summary( $thread, null, true );
						},
						$threads
					)
				),
			)
		);
	}

	public function get_mine( WP_REST_Request $request ) {
		$thread = $this->resolve_sponsor_thread( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		$messages = $this->threads->list_messages( (int) $thread['id'] );
		$messages = $this->filter_handover_gated_messages( $messages, (string) ( $thread['handover_status'] ?? 'not_started' ) );
		$handover = $this->threads->get_handover_for_thread( (int) $thread['id'] );
		$milestones = $this->threads->list_milestones( (int) $thread['id'] );

		return rest_ensure_response(
			array(
				'success'  => true,
				'thread'   => $this->format_thread_summary( $thread, null, true ),
				'messages' => $messages,
				'milestones' => $milestones,
				'handover' => $handover,
			)
		);
	}

	public function reply_mine( WP_REST_Request $request ) {
		$thread = $this->resolve_sponsor_thread( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		$params  = $this->get_json_params( $request );
		$message = sanitize_textarea_field( (string) ( $params['message'] ?? '' ) );
		if ( '' === $message ) {
			return new WP_Error( 'connect_intro_reply_required', __( 'Reply message is required.', 'khm-membership' ), array( 'status' => 400 ) );
		}

		$message_id = $this->threads->add_message(
			(int) $thread['id'],
			array(
				'sender_role'    => 'sponsor',
				'sender_user_id' => get_current_user_id(),
				'message'        => $message,
			)
		);

		if ( $message_id <= 0 ) {
			return new WP_Error( 'connect_intro_reply_failed', __( 'Unable to send sponsor reply.', 'khm-membership' ), array( 'status' => 500 ) );
		}

		$updated_thread = $this->threads->get_thread( (int) $thread['id'] );
		$this->maybe_notify_buyer_reply( is_array( $updated_thread ) ? $updated_thread : $thread, $message );

		return $this->get_mine( $request );
	}

	public function confirm_handover( WP_REST_Request $request ) {
		$thread = $this->resolve_sponsor_thread( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		$is_inbound_connection = 'rfq_request' !== (string) ( $thread['request_type'] ?? 'direct_connection' )
			&& empty( $thread['engaged_option'] );
		if ( $is_inbound_connection && empty( $thread['seller_commission_rate'] ) ) {
			return new WP_Error( 'connect_commission_required', __( 'Set a platform commission rate before confirming handover for this inbound connection.', 'khm-membership' ), array( 'status' => 422 ) );
		}

		$handover = $this->threads->get_handover_for_thread( (int) $thread['id'] );
		if ( ! is_array( $handover ) || 'buyer_requested' !== (string) ( $handover['status'] ?? '' ) ) {
			return new WP_Error( 'connect_handover_not_ready', __( 'There is no buyer-requested handover to confirm.', 'khm-membership' ), array( 'status' => 400 ) );
		}

		if ( ! $this->threads->confirm_handover( (int) $handover['id'] ) ) {
			return new WP_Error( 'connect_handover_confirm_failed', __( 'Unable to confirm handover.', 'khm-membership' ), array( 'status' => 500 ) );
		}

		$updated_thread = $this->threads->get_thread( (int) $thread['id'] );
		$updated_handover = $this->threads->get_handover_for_thread( (int) $thread['id'] );
		$this->maybe_notify_buyer_handover_confirmed( is_array( $updated_thread ) ? $updated_thread : $thread, is_array( $updated_handover ) ? $updated_handover : $handover );

		return rest_ensure_response(
			array(
				'success'  => true,
				'thread'   => $this->format_thread_summary( is_array( $updated_thread ) ? $updated_thread : $thread, null, true ),
				'handover' => is_array( $updated_handover ) ? $updated_handover : $handover,
			)
		);
	}

	public function set_commission_rate( WP_REST_Request $request ) {
		$thread = $this->resolve_sponsor_thread( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $thread ) ) {
			return $thread;
		}

		if ( 'rfq_request' === (string) ( $thread['request_type'] ?? 'direct_connection' ) || ! empty( $thread['engaged_option'] ) ) {
			return new WP_Error( 'connect_commission_not_allowed', __( 'Commission can only be set here for inbound connection threads.', 'khm-membership' ), array( 'status' => 400 ) );
		}

		$params = $this->get_json_params( $request );
		$rate   = isset( $params['commission_rate'] ) ? (int) $params['commission_rate'] : 0;
		if ( $rate < 5 || $rate > 25 ) {
			return new WP_Error( 'connect_commission_invalid', __( 'Commission rate must be between 5 and 25.', 'khm-membership' ), array( 'status' => 422 ) );
		}

		if ( ! $this->threads->set_seller_commission_rate( (int) $thread['id'], $rate ) ) {
			return new WP_Error( 'connect_commission_update_failed', __( 'Unable to save commission rate.', 'khm-membership' ), array( 'status' => 500 ) );
		}

		$updated_thread = $this->threads->get_thread( (int) $thread['id'] );

		return rest_ensure_response(
			array(
				'success'         => true,
				'commission_rate' => $rate,
				'thread'          => $this->format_thread_summary( is_array( $updated_thread ) ? $updated_thread : $thread, null, true ),
			)
		);
	}

	private function resolve_sponsor_thread( int $thread_id ) {
		$thread = $this->threads->get_thread( $thread_id );
		if ( ! is_array( $thread ) ) {
			return new WP_Error( 'connect_thread_not_found', __( 'Intro thread not found.', 'khm-membership' ), array( 'status' => 404 ) );
		}

		if ( current_user_can( 'manage_options' ) ) {
			return $thread;
		}

		$sponsor_id = $this->resolve_sponsor_id();
		if ( is_wp_error( $sponsor_id ) ) {
			return $sponsor_id;
		}

		if ( (int) $thread['sponsor_id'] !== (int) $sponsor_id ) {
			return new WP_Error( 'connect_thread_forbidden', __( 'You cannot access this intro thread.', 'khm-membership' ), array( 'status' => 403 ) );
		}

		return $thread;
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

	private function get_json_params( WP_REST_Request $request ): array {
		$params = $request->get_json_params();

		return is_array( $params ) ? $params : array();
	}

	private function resolve_buyer_token( WP_REST_Request $request, ?array $params = null ): string {
		$params = is_array( $params ) ? $params : $this->get_json_params( $request );
		$candidate = $params['buyer_token'] ?? $params['token'] ?? $request->get_param( 'buyer_token' ) ?? $request->get_param( 'token' ) ?? '';

		return sanitize_text_field( (string) $candidate );
	}

	private function filter_handover_gated_messages( array $messages, string $handover_status ): array {
		if ( 'confirmed' === $handover_status ) {
			return $messages;
		}

		return array_map(
			static function ( $message ) {
				if ( ! is_array( $message ) || empty( $message['message'] ) ) {
					return $message;
				}

				$sanitized = preg_replace( '/https?:\/\/\S+(?:rfq-pack|\.local)\S*/i', '[RFQ link available after handover acceptance]', (string) $message['message'] );
				$message['message'] = trim( preg_replace( '/\s+/', ' ', (string) $sanitized ) );

				return $message;
			},
			$messages
		);
	}

	private function format_thread_summary( array $thread, ?array $provider = null, bool $for_sponsor = false ): array {
		if ( ! is_array( $provider ) && ! empty( $thread['provider_id'] ) ) {
			$provider = $this->providers->get_by_id( (int) $thread['provider_id'] );
		}

		$handover_status = (string) ( $thread['handover_status'] ?? 'not_started' );
		$can_view_identity = ! $for_sponsor || 'confirmed' === $handover_status;
		$buyer_email = $can_view_identity ? $this->threads->decrypt_buyer_email( $thread ) : '';
		$buyer_phone = $can_view_identity ? $this->threads->decrypt_buyer_phone( $thread ) : '';

		return array(
			'id'                      => (int) ( $thread['id'] ?? 0 ),
			'provider_id'             => (int) ( $thread['provider_id'] ?? 0 ),
			'provider_name'           => (string) ( $thread['provider_name'] ?? ( $provider['name'] ?? '' ) ),
			'buyer_name'              => $can_view_identity ? (string) ( $thread['buyer_name'] ?? '' ) : '',
			'buyer_company'           => $can_view_identity ? (string) ( $thread['buyer_company'] ?? '' ) : '',
			'buyer_email'             => $can_view_identity ? sanitize_email( $buyer_email ) : '',
			'buyer_phone'             => $can_view_identity ? sanitize_text_field( $buyer_phone ) : '',
			'buyer_linkedin'          => $can_view_identity ? (string) ( $thread['buyer_linkedin'] ?? '' ) : '',
			'buyer_sector'            => (string) ( $thread['buyer_sector'] ?? '' ),
			'buyer_company_size'      => (string) ( $thread['buyer_company_size'] ?? '' ),
			'buyer_job_title'         => (string) ( $thread['buyer_job_title'] ?? '' ),
			'buyer_city'              => (string) ( $thread['buyer_city'] ?? '' ),
			'buyer_country'           => (string) ( $thread['buyer_country'] ?? '' ),
			'is_demo'                 => ! empty( $thread['is_demo'] ),
			'identity_visible'        => $can_view_identity,
			'status'                  => (string) ( $thread['status'] ?? 'open' ),
			'handover_status'         => $handover_status,
			'request_type'            => (string) ( $thread['request_type'] ?? 'direct_connection' ),
			'seller_response_status'  => (string) ( $thread['seller_response_status'] ?? 'not_requested' ),
			'seller_initial_response' => is_array( $thread['seller_initial_response'] ?? null ) ? $thread['seller_initial_response'] : null,
			'seller_commission_rate'  => isset( $thread['seller_commission_rate'] ) ? (int) $thread['seller_commission_rate'] : null,
			'engaged_option'          => (string) ( $thread['engaged_option'] ?? '' ),
			'commercial_tier'         => (string) ( $thread['commercial_tier'] ?? '' ),
			'last_message_excerpt'    => (string) ( $thread['last_message_excerpt'] ?? '' ),
			'latest_message_at'       => (string) ( $thread['latest_message_at'] ?? '' ),
			'message_count'           => isset( $thread['message_count'] ) ? (int) $thread['message_count'] : 0,
			'created_at'              => (string) ( $thread['created_at'] ?? '' ),
			'provider'                => is_array( $provider ) ? array(
				'id'          => (int) ( $provider['id'] ?? 0 ),
				'name'        => (string) ( $provider['name'] ?? '' ),
				'website_url' => (string) ( $provider['website_url'] ?? '' ),
			) : null,
		);
	}

	private function maybe_notify_sponsor( array $provider, array $thread, string $message ): void {
		$recipient = $this->resolve_sponsor_contact_email( (int) ( $provider['sponsor_id'] ?? 0 ) );
		if ( '' === $recipient ) {
			return;
		}

		$subject = sprintf( 'New Connect intro request for %s', (string) ( $provider['name'] ?? 'your offering' ) );
		$body    = sprintf(
			"A new Connect intro opportunity has been created.\n\nProvider: %s\nMessage:\n%s\n\nBuyer identity remains hidden until handover is confirmed. Review and reply in the Quote Club Connect inbox.",
			(string) ( $provider['name'] ?? '' ),
			$message
		);

		wp_mail( $recipient, $subject, $body );
	}

	private function maybe_notify_buyer_ack( array $provider, array $thread ): void {
		$recipient = $this->threads->decrypt_buyer_email( $thread );
		if ( '' === $recipient ) {
			return;
		}

		$subject = sprintf( 'Your Connect intro request for %s', (string) ( $provider['name'] ?? 'provider' ) );
		$body    = sprintf(
			"Thanks for requesting an intro to %s.\n\nYour request is now in the sponsor inbox and replies will be relayed through the platform until handover is confirmed.",
			(string) ( $provider['name'] ?? 'the provider' )
		);

		wp_mail( $recipient, $subject, $body );
	}

	private function maybe_notify_buyer_reply( array $thread, string $message ): void {
		$recipient = $this->threads->decrypt_buyer_email( $thread );
		if ( '' === $recipient ) {
			return;
		}

		$subject = sprintf( 'New reply from %s', (string) ( $thread['provider_name'] ?? 'your provider' ) );
		$body    = "You have a new Connect reply:\n\n" . $message . "\n\nReply handling remains platform-mediated until handover is confirmed.";

		wp_mail( $recipient, $subject, $body );
	}

	private function maybe_notify_sponsor_handover_request( array $provider, array $thread, array $handover ): void {
		$recipient = $this->resolve_sponsor_contact_email( (int) ( $thread['sponsor_id'] ?? 0 ) );
		if ( '' === $recipient ) {
			return;
		}

		$subject = sprintf( 'Buyer requested handover for %s', (string) ( $provider['name'] ?? 'your offering' ) );
		$body    = sprintf( "The buyer has requested direct handover for %s. Review the thread in Quote Club Connect and confirm if you want to proceed.", (string) ( $provider['name'] ?? 'the offering' ) );

		wp_mail( $recipient, $subject, $body );
	}

	private function maybe_notify_buyer_handover_confirmed( array $thread, array $handover ): void {
		$recipient = $this->threads->decrypt_buyer_email( $thread );
		if ( '' === $recipient ) {
			return;
		}

		$subject = sprintf( 'Handover confirmed with %s', (string) ( $thread['provider_name'] ?? 'provider' ) );
		$body    = sprintf( "Your handover is confirmed. Attribution now starts on %s.", (string) ( $handover['attribution_starts_at'] ?? current_time( 'mysql' ) ) );

		wp_mail( $recipient, $subject, $body );
	}

	private function resolve_sponsor_contact_email( int $sponsor_id ): string {
		if ( $sponsor_id <= 0 ) {
			return '';
		}

		global $wpdb;
		$table = SponsorMigration::sponsors_table_name();
		$sponsor = $wpdb->get_row( $wpdb->prepare( "SELECT contact_email, primary_contact_email, team_members FROM {$table} WHERE id = %d", $sponsor_id ), ARRAY_A );

		if ( ! is_array( $sponsor ) ) {
			return '';
		}

		$members = json_decode( (string) ( $sponsor['team_members'] ?? '' ), true );
		if ( is_array( $members ) ) {
			foreach ( $members as $member ) {
				$email = sanitize_email( (string) ( $member['work_email'] ?? '' ) );
				if ( '' !== $email ) {
					return $email;
				}
			}
		}

		$primary = sanitize_email( (string) ( $sponsor['primary_contact_email'] ?? '' ) );
		if ( '' !== $primary ) {
			return $primary;
		}

		return sanitize_email( (string) ( $sponsor['contact_email'] ?? '' ) );
	}
}