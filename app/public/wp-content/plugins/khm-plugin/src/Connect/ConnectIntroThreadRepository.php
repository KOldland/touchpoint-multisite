<?php

namespace KHM\Connect;

use KHM\Migrations\ConnectWorkflowMigration;

defined( 'ABSPATH' ) || exit;

class ConnectIntroThreadRepository {

	public function create_thread( array $data ): int {
		global $wpdb;

		$table = ConnectWorkflowMigration::threads_table_name();
		$inserted = $wpdb->insert(
			$table,
			array(
				'blog_id'               => $this->current_blog_id(),
				'provider_id'           => (int) $data['provider_id'],
				'sponsor_id'            => (int) $data['sponsor_id'],
				'session_id'            => $this->normalize_nullable_string( $data['session_id'] ?? '' ),
				'buyer_name'            => sanitize_text_field( (string) ( $data['buyer_name'] ?? '' ) ),
				'buyer_company'         => $this->normalize_nullable_string( $data['buyer_company'] ?? '' ),
				'buyer_email_encrypted' => $this->encrypt_email( (string) ( $data['buyer_email'] ?? '' ) ),
				'buyer_email_hash'      => $this->hash_email( (string) ( $data['buyer_email'] ?? '' ) ),
				'buyer_token'           => wp_generate_password( 48, false, false ),
				'status'                => 'open',
				'handover_status'       => 'not_started',
				'last_message_excerpt'  => $this->build_excerpt( (string) ( $data['message'] ?? '' ) ),
				'latest_message_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		$thread_id = (int) $wpdb->insert_id;
		$this->add_message(
			$thread_id,
			array(
				'sender_role'    => 'buyer',
				'sender_user_id' => get_current_user_id() ?: null,
				'message'        => (string) ( $data['message'] ?? '' ),
			)
		);

		return $thread_id;
	}

	public function add_message( int $thread_id, array $data ): int {
		if ( $thread_id <= 0 ) {
			return 0;
		}

		global $wpdb;

		$table = ConnectWorkflowMigration::messages_table_name();
		$inserted = $wpdb->insert(
			$table,
			array(
				'thread_id'      => $thread_id,
				'sender_role'    => sanitize_key( (string) ( $data['sender_role'] ?? '' ) ),
				'sender_user_id' => isset( $data['sender_user_id'] ) ? (int) $data['sender_user_id'] : null,
				'message'        => sanitize_textarea_field( (string) ( $data['message'] ?? '' ) ),
			),
			array( '%d', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		$this->touch_thread( $thread_id, (string) ( $data['message'] ?? '' ), (string) ( $data['sender_role'] ?? '' ) );

		return (int) $wpdb->insert_id;
	}

	public function get_thread( int $thread_id ): ?array {
		global $wpdb;

		$table = ConnectWorkflowMigration::threads_table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $thread_id ), ARRAY_A );

		return is_array( $row ) ? $this->hydrate_thread( $row ) : null;
	}

	public function get_thread_by_token( int $thread_id, string $buyer_token ): ?array {
		$thread = $this->get_thread( $thread_id );
		if ( ! is_array( $thread ) ) {
			return null;
		}

		if ( ! hash_equals( (string) ( $thread['buyer_token'] ?? '' ), $buyer_token ) ) {
			return null;
		}

		return $thread;
	}

	public function list_for_sponsor( int $sponsor_id ): array {
		if ( $sponsor_id <= 0 ) {
			return array();
		}

		global $wpdb;

		$threads_table  = ConnectWorkflowMigration::threads_table_name();
		$messages_table = ConnectWorkflowMigration::messages_table_name();
		$providers_table = $wpdb->prefix . 'connect_providers';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*, p.name AS provider_name,
				(SELECT COUNT(1) FROM {$messages_table} m WHERE m.thread_id = t.id) AS message_count
				FROM {$threads_table} t
				LEFT JOIN {$providers_table} p ON p.id = t.provider_id
				WHERE t.sponsor_id = %d
				ORDER BY t.latest_message_at DESC, t.id DESC
				LIMIT 100",
				$sponsor_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'hydrate_thread' ), $rows );
	}

	public function list_messages( int $thread_id ): array {
		global $wpdb;

		$table = ConnectWorkflowMigration::messages_table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE thread_id = %d ORDER BY created_at ASC, id ASC", $thread_id ),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( array $row ): array {
				return array(
					'id'             => (int) ( $row['id'] ?? 0 ),
					'thread_id'      => (int) ( $row['thread_id'] ?? 0 ),
					'sender_role'    => (string) ( $row['sender_role'] ?? '' ),
					'sender_user_id' => isset( $row['sender_user_id'] ) ? (int) $row['sender_user_id'] : 0,
					'message'        => (string) ( $row['message'] ?? '' ),
					'created_at'     => (string) ( $row['created_at'] ?? '' ),
				);
			},
			$rows
		);
	}

	public function list_milestones( int $thread_id ): array {
		if ( $thread_id <= 0 ) {
			return array();
		}

		global $wpdb;

		$table = ConnectWorkflowMigration::milestones_table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE thread_id = %d ORDER BY event_at ASC, id ASC", $thread_id ),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( array $row ): array {
				$payload = json_decode( (string) ( $row['payload'] ?? '' ), true );

				return array(
					'id'          => (int) ( $row['id'] ?? 0 ),
					'thread_id'   => (int) ( $row['thread_id'] ?? 0 ),
					'handover_id' => isset( $row['handover_id'] ) ? (int) $row['handover_id'] : 0,
					'event_key'   => (string) ( $row['event_key'] ?? '' ),
					'event_status'=> (string) ( $row['event_status'] ?? '' ),
					'payload'     => is_array( $payload ) ? $payload : array(),
					'event_at'    => (string) ( $row['event_at'] ?? '' ),
				);
			},
			$rows
		);
	}

	public function request_handover( int $thread_id ): ?array {
		$thread = $this->get_thread( $thread_id );
		if ( ! is_array( $thread ) ) {
			return null;
		}

		$existing = $this->get_handover_for_thread( $thread_id );
		if ( is_array( $existing ) ) {
			return $existing;
		}

		global $wpdb;

		$table = ConnectWorkflowMigration::handovers_table_name();
		$inserted = $wpdb->insert(
			$table,
			array(
				'thread_id'           => $thread_id,
				'provider_id'         => (int) $thread['provider_id'],
				'sponsor_id'          => (int) $thread['sponsor_id'],
				'buyer_email_hash'    => (string) $thread['buyer_email_hash'],
				'buyer_company'       => $this->normalize_nullable_string( $thread['buyer_company'] ?? '' ),
				'status'              => 'buyer_requested',
				'buyer_requested_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return null;
		}

		$wpdb->update(
			ConnectWorkflowMigration::threads_table_name(),
			array(
				'handover_status' => 'buyer_requested',
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $thread_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$handover_id = (int) $wpdb->insert_id;
		$this->add_milestone_event( $thread_id, $handover_id, 'handover_requested', array( 'source' => 'buyer' ) );

		return $this->get_handover( $handover_id );
	}

	public function confirm_handover( int $handover_id ): bool {
		$handover = $this->get_handover( $handover_id );
		if ( ! is_array( $handover ) ) {
			return false;
		}

		global $wpdb;
		$confirmed_at = current_time( 'mysql' );

		$updated = $wpdb->update(
			ConnectWorkflowMigration::handovers_table_name(),
			array(
				'status'               => 'confirmed',
				'sponsor_confirmed_at' => $confirmed_at,
				'attribution_starts_at'=> $confirmed_at,
			),
			array( 'id' => $handover_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return false;
		}

		$wpdb->update(
			ConnectWorkflowMigration::threads_table_name(),
			array(
				'handover_status' => 'confirmed',
				'updated_at'      => $confirmed_at,
			),
			array( 'id' => (int) $handover['thread_id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$this->add_milestone_event( (int) $handover['thread_id'], $handover_id, 'handover_confirmed', array( 'source' => 'sponsor' ) );

		return true;
	}

	public function get_handover_for_thread( int $thread_id ): ?array {
		global $wpdb;

		$table = ConnectWorkflowMigration::handovers_table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE thread_id = %d", $thread_id ), ARRAY_A );

		return is_array( $row ) ? $this->hydrate_handover( $row ) : null;
	}

	public function get_handover( int $handover_id ): ?array {
		global $wpdb;

		$table = ConnectWorkflowMigration::handovers_table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $handover_id ), ARRAY_A );

		return is_array( $row ) ? $this->hydrate_handover( $row ) : null;
	}

	public function decrypt_buyer_email( array $thread ): string {
		return $this->decrypt_email( (string) ( $thread['buyer_email_encrypted'] ?? '' ) );
	}

	private function touch_thread( int $thread_id, string $message, string $sender_role ): void {
		global $wpdb;

		$status = 'buyer' === $sender_role ? 'open' : 'sponsor_replied';
		$wpdb->update(
			ConnectWorkflowMigration::threads_table_name(),
			array(
				'latest_message_at'    => current_time( 'mysql' ),
				'last_message_excerpt' => $this->build_excerpt( $message ),
				'status'               => $status,
			),
			array( 'id' => $thread_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	private function add_milestone_event( int $thread_id, int $handover_id, string $event_key, array $payload = array() ): void {
		global $wpdb;

		$wpdb->insert(
			ConnectWorkflowMigration::milestones_table_name(),
			array(
				'thread_id'    => $thread_id,
				'handover_id'  => $handover_id > 0 ? $handover_id : null,
				'event_key'    => sanitize_key( $event_key ),
				'event_status' => 'completed',
				'payload'      => wp_json_encode( $payload ),
				'event_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
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

	private function decrypt_email( string $encoded ): string {
		$encoded = trim( $encoded );
		if ( '' === $encoded || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$binary = base64_decode( $encoded, true );
		if ( ! is_string( $binary ) || strlen( $binary ) <= 16 ) {
			return '';
		}

		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = substr( $binary, 0, 16 );
		$ciphertext = substr( $binary, 16 );

		$plaintext = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		return is_string( $plaintext ) ? sanitize_email( $plaintext ) : '';
	}

	private function build_excerpt( string $message ): string {
		$message = trim( sanitize_textarea_field( $message ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 160 ) : substr( $message, 0, 160 );
	}

	private function normalize_nullable_string( $value ): ?string {
		$value = trim( sanitize_text_field( (string) $value ) );

		return '' === $value ? null : $value;
	}

	private function current_blog_id(): int {
		$current_blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;

		return max( 1, (int) apply_filters( 'khm_connect_current_blog_id', $current_blog_id ) );
	}

	private function hydrate_thread( array $row ): array {
		return array(
			'id'                   => (int) ( $row['id'] ?? 0 ),
			'blog_id'              => (int) ( $row['blog_id'] ?? 1 ),
			'provider_id'          => (int) ( $row['provider_id'] ?? 0 ),
			'provider_name'        => (string) ( $row['provider_name'] ?? '' ),
			'sponsor_id'           => (int) ( $row['sponsor_id'] ?? 0 ),
			'session_id'           => (string) ( $row['session_id'] ?? '' ),
			'buyer_name'           => (string) ( $row['buyer_name'] ?? '' ),
			'buyer_company'        => (string) ( $row['buyer_company'] ?? '' ),
			'buyer_email_encrypted'=> (string) ( $row['buyer_email_encrypted'] ?? '' ),
			'buyer_email_hash'     => (string) ( $row['buyer_email_hash'] ?? '' ),
			'buyer_token'          => (string) ( $row['buyer_token'] ?? '' ),
			'status'               => (string) ( $row['status'] ?? 'open' ),
			'handover_status'      => (string) ( $row['handover_status'] ?? 'not_started' ),
			'last_message_excerpt' => (string) ( $row['last_message_excerpt'] ?? '' ),
			'latest_message_at'    => (string) ( $row['latest_message_at'] ?? '' ),
			'message_count'        => isset( $row['message_count'] ) ? (int) $row['message_count'] : 0,
			'created_at'           => (string) ( $row['created_at'] ?? '' ),
		);
	}

	private function hydrate_handover( array $row ): array {
		return array(
			'id'                  => (int) ( $row['id'] ?? 0 ),
			'thread_id'           => (int) ( $row['thread_id'] ?? 0 ),
			'provider_id'         => (int) ( $row['provider_id'] ?? 0 ),
			'sponsor_id'          => (int) ( $row['sponsor_id'] ?? 0 ),
			'buyer_email_hash'    => (string) ( $row['buyer_email_hash'] ?? '' ),
			'buyer_company'       => (string) ( $row['buyer_company'] ?? '' ),
			'status'              => (string) ( $row['status'] ?? 'buyer_requested' ),
			'buyer_requested_at'  => (string) ( $row['buyer_requested_at'] ?? '' ),
			'sponsor_confirmed_at'=> (string) ( $row['sponsor_confirmed_at'] ?? '' ),
			'attribution_starts_at'=> (string) ( $row['attribution_starts_at'] ?? '' ),
		);
	}
}