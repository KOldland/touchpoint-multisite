<?php

namespace KHM\Seeds;

use KHM\Connect\ConnectIntroThreadRepository;
use KHM\Connect\ConnectProviderRepository;
use KHM\Migrations\ConnectWorkflowMigration;
use KHM\Sponsors\SponsorMigration;

defined( 'ABSPATH' ) || exit;

class ConnectDemoSeeder {

	private ConnectProviderRepository $providers;

	private ConnectIntroThreadRepository $threads;

	public function __construct( ?ConnectProviderRepository $providers = null, ?ConnectIntroThreadRepository $threads = null ) {
		$this->providers = $providers ?? new ConnectProviderRepository();
		$this->threads   = $threads ?? new ConnectIntroThreadRepository();
	}

	public function seed( int $sponsor_id = 0 ): array {
		$sponsor_id = $sponsor_id > 0 ? $sponsor_id : $this->resolve_default_sponsor_id();
		if ( $sponsor_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'No sponsor record found. Create a sponsor first or pass --sponsor_id=<id>.',
			);
		}

		$provider_id = $this->upsert_demo_provider( $sponsor_id );
		if ( $provider_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'Unable to create or update the demo provider record.',
			);
		}

		$definitions = $this->thread_definitions();
		$thread_ids = array();

		foreach ( $definitions as $definition ) {
			$thread_id = $this->upsert_demo_thread( $provider_id, $sponsor_id, $definition );
			if ( $thread_id > 0 ) {
				$thread_ids[] = $thread_id;
			}
		}

		return array(
			'success'            => true,
			'sponsor_id'         => $sponsor_id,
			'provider_id'        => $provider_id,
			'seeded_thread_ids'  => $thread_ids,
			'seeded_thread_count'=> count( $thread_ids ),
		);
	}

	private function upsert_demo_provider( int $sponsor_id ): int {
		global $wpdb;

		$table   = $wpdb->prefix . 'connect_providers';
		$blog_id = $this->current_blog_id();
		$slug    = 'fieldsync-demo-seller';

		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE slug = %s AND blog_id IN (0, %d) ORDER BY id DESC LIMIT 1",
				$slug,
				$blog_id
			)
		);

		$payload = array(
			'blog_id'              => $blog_id,
			'sponsor_id'           => $sponsor_id,
			'name'                 => 'FieldSync Platform',
			'slug'                 => $slug,
			'description'          => 'Field service and workforce management platform for enterprise operations teams.',
			'website_url'          => 'https://fieldsync.example.local',
			'provider_type'        => 'platform',
			'sweet_spot_summary'   => 'Built for utility, facilities, and field operations teams standardizing dispatch, SLA performance, and workforce visibility.',
			'company_size_min'     => 200,
			'company_size_max'     => 3000,
			'budget_min'           => 50000,
			'budget_max'           => 300000,
			'onboarding_days'      => 45,
			'regions'              => wp_json_encode( array( 'uk', 'europe' ) ),
			'deployment_modes'     => wp_json_encode( array( 'cloud', 'hybrid' ) ),
			'support_tiers'        => wp_json_encode( array( 'standard', 'premium' ) ),
			'status'               => 'active',
			'commentary_enabled'   => 1,
			'ad_targeting_enabled' => 1,
			'is_demo'              => 1,
			'titles'               => wp_json_encode( array( 'head-of-operations', 'operations-director', 'vp-service-operations', 'digital-transformation-lead' ) ),
			'comparison_fields'    => wp_json_encode( array() ),
			'match_rules'          => wp_json_encode( array() ),
		);

		$formats = array(
			'%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s',
		);

		if ( $existing_id > 0 ) {
			$updated = $wpdb->update( $table, $payload, array( 'id' => $existing_id ), $formats, array( '%d' ) );
			if ( false === $updated ) {
				return 0;
			}

			return $existing_id;
		}

		$inserted = $wpdb->insert( $table, $payload, $formats );
		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	private function upsert_demo_thread( int $provider_id, int $sponsor_id, array $definition ): int {
		global $wpdb;

		$table = ConnectWorkflowMigration::threads_table_name();
		$blog_id = $this->current_blog_id();
		$email_hash = $this->hash_email( (string) $definition['buyer_email'] );

		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE sponsor_id = %d AND provider_id = %d AND session_id = %s ORDER BY id DESC LIMIT 1",
				$sponsor_id,
				$provider_id,
				(string) $definition['session_id']
			)
		);

		$thread_data = array(
			'provider_id'        => $provider_id,
			'sponsor_id'         => $sponsor_id,
			'session_id'         => (string) ( $definition['session_id'] ?? 'demo_session' ),
			'request_type'       => (string) ( $definition['request_type'] ?? 'direct_connection' ),
			'buyer_name'         => (string) $definition['buyer_name'],
			'buyer_company'      => (string) $definition['buyer_company'],
			'buyer_email'        => (string) $definition['buyer_email'],
			'buyer_phone'        => (string) ( $definition['buyer_phone'] ?? '' ),
			'buyer_linkedin'     => (string) ( $definition['buyer_linkedin'] ?? '' ),
			'buyer_sector'       => (string) $definition['buyer_sector'],
			'buyer_company_size' => (string) $definition['buyer_company_size'],
			'buyer_job_title'    => (string) $definition['buyer_job_title'],
			'buyer_city'         => (string) ( $definition['buyer_city'] ?? '' ),
			'buyer_country'      => (string) $definition['buyer_country'],
			'engaged_option'     => isset( $definition['engaged_option'] ) ? (string) $definition['engaged_option'] : null,
			'message'            => (string) $definition['opening_message'],
			'is_demo'            => true,
		);

		$thread_id = $existing_id > 0 ? $existing_id : $this->threads->create_thread( $thread_data );
		if ( $thread_id <= 0 ) {
			return 0;
		}

		if ( $existing_id > 0 ) {
			$wpdb->update(
				$table,
				array(
					'buyer_name'            => (string) $definition['buyer_name'],
					'buyer_company'         => (string) $definition['buyer_company'],
					'buyer_email_encrypted' => $this->encrypt_email( (string) $definition['buyer_email'] ),
					'buyer_phone_encrypted' => $this->encrypt_phone( (string) ( $definition['buyer_phone'] ?? '' ) ),
					'buyer_email_hash'      => $email_hash,
					'buyer_sector'          => (string) $definition['buyer_sector'],
					'buyer_company_size'    => (string) $definition['buyer_company_size'],
					'buyer_job_title'       => (string) $definition['buyer_job_title'],
					'buyer_city'            => (string) ( $definition['buyer_city'] ?? '' ),
					'buyer_country'         => (string) $definition['buyer_country'],
					'buyer_linkedin'        => (string) ( $definition['buyer_linkedin'] ?? '' ),
					'request_type'          => (string) ( $definition['request_type'] ?? 'direct_connection' ),
					'engaged_option'        => isset( $definition['engaged_option'] ) ? (string) $definition['engaged_option'] : null,
					'is_demo'               => 1,
					'updated_at'            => current_time( 'mysql' ),
				),
				array( 'id' => $thread_id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
		}

		$this->reset_messages( $thread_id, (array) ( $definition['messages'] ?? array() ) );
		$this->ensure_handover_status( $thread_id, (string) ( $definition['handover_status'] ?? 'buyer_requested' ) );
		$this->apply_response_state( $thread_id, $definition );

		return $thread_id;
	}

	private function reset_messages( int $thread_id, array $messages ): void {
		global $wpdb;

		$messages_table = ConnectWorkflowMigration::messages_table_name();
		$wpdb->delete( $messages_table, array( 'thread_id' => $thread_id ), array( '%d' ) );

		$last_excerpt = '';
		$last_message_at = current_time( 'mysql' );

		foreach ( $messages as $message ) {
			$sender_role = sanitize_key( (string) ( $message['sender_role'] ?? 'buyer' ) );
			$body = sanitize_textarea_field( (string) ( $message['message'] ?? '' ) );
			$created_at = sanitize_text_field( (string) ( $message['created_at'] ?? current_time( 'mysql' ) ) );

			if ( '' === $body ) {
				continue;
			}

			$wpdb->insert(
				$messages_table,
				array(
					'thread_id'      => $thread_id,
					'sender_role'    => $sender_role,
					'sender_user_id' => null,
					'message'        => $body,
					'created_at'     => $created_at,
				),
				array( '%d', '%s', '%d', '%s', '%s' )
			);

			$last_excerpt = function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 160 ) : substr( $body, 0, 160 );
			$last_message_at = $created_at;
		}

		if ( '' !== $last_excerpt ) {
			$wpdb->update(
				ConnectWorkflowMigration::threads_table_name(),
				array(
					'last_message_excerpt' => $last_excerpt,
					'latest_message_at'    => $last_message_at,
				),
				array( 'id' => $thread_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	private function ensure_handover_status( int $thread_id, string $status ): void {
		if ( 'confirmed' === $status ) {
			$handover = $this->threads->request_handover( $thread_id );
			if ( is_array( $handover ) && ! empty( $handover['id'] ) ) {
				$this->threads->confirm_handover( (int) $handover['id'] );
			}

			return;
		}

		if ( 'buyer_requested' === $status ) {
			$this->threads->request_handover( $thread_id );
		}
	}

	private function apply_response_state( int $thread_id, array $definition ): void {
		global $wpdb;

		$threads_table = ConnectWorkflowMigration::threads_table_name();
		$status = (string) ( $definition['seller_response_status'] ?? 'not_requested' );
		$response = isset( $definition['seller_initial_response'] ) && is_array( $definition['seller_initial_response'] )
			? wp_json_encode( $definition['seller_initial_response'] )
			: null;
		$commission_rate = isset( $definition['seller_commission_rate'] ) ? (int) $definition['seller_commission_rate'] : null;

		$wpdb->update(
			$threads_table,
			array(
				'seller_response_status' => $status,
				'seller_initial_response'=> $response,
				'seller_commission_rate' => $commission_rate,
			),
			array( 'id' => $thread_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);
	}

	private function resolve_default_sponsor_id(): int {
		global $wpdb;

		$table = SponsorMigration::sponsors_table_name();
		return (int) $wpdb->get_var( "SELECT id FROM {$table} ORDER BY id ASC LIMIT 1" );
	}

	private function current_blog_id(): int {
		$current_blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;

		return max( 1, (int) apply_filters( 'khm_connect_current_blog_id', $current_blog_id ) );
	}

	private function hash_email( string $email ): string {
		$normalized = strtolower( trim( sanitize_email( $email ) ) );
		return hash_hmac( 'sha256', $normalized, wp_salt( 'auth' ) );
	}

	private function encrypt_email( string $email ): string {
		$email = trim( sanitize_email( $email ) );
		if ( '' === $email || ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}

		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = random_bytes( 16 );
		$ciphertext = openssl_encrypt( $email, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return '';
		}

		return base64_encode( $iv . $ciphertext );
	}

	private function encrypt_phone( string $phone ): string {
		$phone = trim( sanitize_text_field( $phone ) );
		if ( '' === $phone || ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}

		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = random_bytes( 16 );
		$ciphertext = openssl_encrypt( $phone, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return '';
		}

		return base64_encode( $iv . $ciphertext );
	}

	private function thread_definitions(): array {
		$now = current_time( 'timestamp' );

		return array(
			array(
				'session_id'          => 'demo_rfp_fieldsync',
				'request_type'        => 'rfp_request',
				'buyer_name'          => 'Sarah Chen',
				'buyer_company'       => 'TechFlow Services Ltd',
				'buyer_email'         => 'sarah.chen+demo@techflow.local',
				'buyer_phone'         => '+447700900101',
				'buyer_linkedin'      => 'https://www.linkedin.com/in/sarah-chen-demo',
				'buyer_sector'        => 'Utilities',
				'buyer_company_size'  => '501-1000',
				'buyer_job_title'     => 'Head of Field Operations',
				'buyer_city'          => 'Manchester',
				'buyer_country'       => 'United Kingdom',
				'opening_message'     => 'We are evaluating a field service platform for SLA tracking, mobile workflows, and dispatch optimization across three regions.',
				'handover_status'     => 'buyer_requested',
				'seller_response_status' => 'submitted',
				'seller_commission_rate' => 12,
				'seller_initial_response' => array(
					'capability' => 'Phased rollout with field app adoption playbook, dispatch automation, and SLA dashboarding.',
					'cost_range' => 'GBP 60k to 90k implementation + annual license',
					'approach'   => 'Discovery sprint, pilot in one region, then phased deployment.',
					'timeline'   => '8 to 10 weeks',
				),
				'messages' => array(
					array(
						'sender_role' => 'buyer',
						'created_at'  => gmdate( 'Y-m-d H:i:s', $now - 14400 ),
						'message'     => 'Can you share your recommended implementation approach and likely budget envelope?',
					),
					array(
						'sender_role' => 'sponsor',
						'created_at'  => gmdate( 'Y-m-d H:i:s', $now - 10800 ),
						'message'     => 'Yes. We can run a 2-week discovery and 6-week implementation with staged deployment.',
					),
					array(
						'sender_role' => 'buyer',
						'created_at'  => gmdate( 'Y-m-d H:i:s', $now - 7200 ),
						'message'     => 'Looks good. Please complete the full RFP pack once handover is accepted: https://example.local/rfp-pack/FS-203',
					),
				),
			),
			array(
				'session_id'          => 'demo_active_match_ops',
				'request_type'        => 'direct_connection',
				'engaged_option'      => 'option_1',
				'buyer_name'          => 'Marcus Webb',
				'buyer_company'       => 'Meridian Facilities Management',
				'buyer_email'         => 'marcus.webb+demo@meridian.local',
				'buyer_phone'         => '+447700900102',
				'buyer_linkedin'      => 'https://www.linkedin.com/in/marcus-webb-demo',
				'buyer_sector'        => 'Facilities Management',
				'buyer_company_size'  => '201-500',
				'buyer_job_title'     => 'Operations Director',
				'buyer_city'          => 'Leeds',
				'buyer_country'       => 'United Kingdom',
				'opening_message'     => 'We need better engineer scheduling and first-time-fix reporting for nationwide maintenance contracts.',
				'handover_status'     => 'buyer_requested',
				'messages' => array(
					array(
						'sender_role' => 'buyer',
						'created_at'  => gmdate( 'Y-m-d H:i:s', $now - 9600 ),
						'message'     => 'Interested. Share your proposed onboarding approach and expected team requirements.',
					),
					array(
						'sender_role' => 'sponsor',
						'created_at'  => gmdate( 'Y-m-d H:i:s', $now - 3600 ),
						'message'     => 'We typically run a discovery workshop, then pilot with one regional team before scaling.',
					),
				),
			),
			array(
				'session_id'          => 'demo_inbound_transform',
				'request_type'        => 'direct_connection',
				'buyer_name'          => 'Priya Sharma',
				'buyer_company'       => 'Atlas Telecoms Infrastructure',
				'buyer_email'         => 'priya.sharma+demo@atlas.local',
				'buyer_phone'         => '+447700900103',
				'buyer_linkedin'      => 'https://www.linkedin.com/in/priya-sharma-demo',
				'buyer_sector'        => 'Telecoms',
				'buyer_company_size'  => '1001-2000',
				'buyer_job_title'     => 'Digital Transformation Lead',
				'buyer_city'          => 'Birmingham',
				'buyer_country'       => 'United Kingdom',
				'opening_message'     => 'We are consolidating multiple legacy workforce tools and need a migration-safe rollout plan.',
				'handover_status'     => 'buyer_requested',
				'messages' => array(
					array(
						'sender_role' => 'buyer',
						'created_at'  => gmdate( 'Y-m-d H:i:s', $now - 8400 ),
						'message'     => 'Could we book a 30-minute exploratory call?',
					),
					array(
						'sender_role' => 'sponsor',
						'created_at'  => gmdate( 'Y-m-d H:i:s', $now - 2400 ),
						'message'     => 'Absolutely. We can share an agenda and rollout framework once handover is confirmed.',
					),
				),
			),
			array(
				'session_id'          => 'demo_confirmed_handover',
				'request_type'        => 'direct_connection',
				'buyer_name'          => 'James Holloway',
				'buyer_company'       => 'Vertex Energy Services',
				'buyer_email'         => 'james.holloway+demo@vertex.local',
				'buyer_phone'         => '+447700900104',
				'buyer_linkedin'      => 'https://www.linkedin.com/in/james-holloway-demo',
				'buyer_sector'        => 'Energy Services',
				'buyer_company_size'  => '501-1000',
				'buyer_job_title'     => 'VP of Service Operations',
				'buyer_city'          => 'Bristol',
				'buyer_country'       => 'United Kingdom',
				'opening_message'     => 'We are planning a nationwide rollout for field productivity and contractor compliance tracking.',
				'handover_status'     => 'confirmed',
				'messages' => array(
					array(
						'sender_role' => 'buyer',
						'created_at'  => gmdate( 'Y-m-d H:i:s', $now - 7200 ),
						'message'     => 'Can we move this forward with direct stakeholder contacts?',
					),
					array(
						'sender_role' => 'sponsor',
						'created_at'  => gmdate( 'Y-m-d H:i:s', $now - 1800 ),
						'message'     => 'Yes, handover confirmed. We can align procurement and implementation teams this week.',
					),
				),
			),
		);
	}
}
