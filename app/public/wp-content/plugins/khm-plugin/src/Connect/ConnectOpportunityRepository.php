<?php

namespace KHM\Connect;

use KHM\Migrations\ConnectWorkflowMigration;

defined( 'ABSPATH' ) || exit;

class ConnectOpportunityRepository {

	/**
	 * @param array<string,mixed> $signal
	 */
	public function create_from_scoring_signal( array $signal ): int {
		$actor_email = sanitize_email( (string) ( $signal['actor_email'] ?? '' ) );
		if ( '' === $actor_email ) {
			return 0;
		}

		$stage = sanitize_key( (string) ( $signal['stage'] ?? 'attention' ) );
		$tier  = sanitize_key( (string) ( $signal['commercial_tier'] ?? ConnectTiering::map_stage_to_tier( $stage ) ) );
		if ( '' === $tier ) {
			$tier = ConnectTiering::map_stage_to_tier( $stage );
		}

		$pricing = $this->resolve_pricing_snapshot( $signal, $tier );
		$tier    = (string) $pricing['tier'];

		$score = isset( $signal['person_score'] ) ? (float) $signal['person_score'] : 0.0;
		$date  = sanitize_text_field( (string) ( $signal['score_date'] ?? gmdate( 'Y-m-d' ) ) );

		$domain = '';
		if ( false !== strpos( $actor_email, '@' ) ) {
			$domain = strtolower( (string) substr( $actor_email, (int) strpos( $actor_email, '@' ) + 1 ) );
		}

		$dedupe_key = hash_hmac( 'sha256', strtolower( $actor_email ) . '|' . $tier . '|' . $date, wp_salt( 'auth' ) );

		global $wpdb;
		$table = ConnectWorkflowMigration::opportunities_table_name();

		$inserted = $wpdb->insert(
			$table,
			array(
				'blog_id'             => $this->current_blog_id(),
				'actor_email_hash'    => $this->hash_email( $actor_email ),
				'actor_email_domain'  => $domain,
				'company_domain'      => $this->normalize_nullable_string( (string) ( $signal['company_domain'] ?? $domain ) ),
				'sponsor_id'          => null,
				'provider_id'         => null,
				'internal_stage'      => $stage,
				'commercial_tier'     => $pricing['tier'],
				'person_score'        => round( $score, 2 ),
				'opportunity_status'  => 'detected',
				'pricing_model'       => $pricing['pricing_model'],
				'unit_price_cents'    => (int) $pricing['unit_price_cents'],
				'commission_eligible' => (int) $pricing['commission_eligible'],
				'dedupe_key'          => $dedupe_key,
				'source'              => sanitize_key( (string) ( $signal['source'] ?? 'foura_sql' ) ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		$opportunity_id = (int) $wpdb->insert_id;
		$this->log_consent_event(
			$opportunity_id,
			array(
				'event_key'    => 'opportunity_detected',
				'event_source' => 'scoring_engine',
				'actor_role'   => 'system',
				'payload'      => array(
					'internal_stage'  => $stage,
					'commercial_tier' => $pricing['tier'],
					'person_score'    => round( $score, 2 ),
				),
			)
		);

		return $opportunity_id;
	}

	public function find_latest_open_by_email_hash( string $buyer_email_hash ): ?array {
		$buyer_email_hash = sanitize_text_field( $buyer_email_hash );
		if ( '' === $buyer_email_hash ) {
			return null;
		}

		global $wpdb;
		$table = ConnectWorkflowMigration::opportunities_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE actor_email_hash = %s AND opportunity_status IN ('detected','offered','sponsor_accepted') ORDER BY id DESC LIMIT 1",
				$buyer_email_hash
			),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrate_opportunity( $row ) : null;
	}

	public function mark_status( int $opportunity_id, string $status, string $timestamp_field = '' ): bool {
		if ( $opportunity_id <= 0 ) {
			return false;
		}

		$status = sanitize_key( $status );
		global $wpdb;

		$data = array(
			'opportunity_status' => $status,
			'updated_at'         => current_time( 'mysql' ),
		);
		$formats = array( '%s', '%s' );

		if ( '' !== $timestamp_field ) {
			$data[ sanitize_key( $timestamp_field ) ] = current_time( 'mysql' );
			$formats[] = '%s';
		}

		$updated = $wpdb->update(
			ConnectWorkflowMigration::opportunities_table_name(),
			$data,
			array( 'id' => $opportunity_id ),
			$formats,
			array( '%d' )
		);

		return false !== $updated;
	}

	public function mark_sponsor_acceptance( int $opportunity_id, int $sponsor_id, int $provider_id ): bool {
		if ( $opportunity_id <= 0 || $sponsor_id <= 0 || $provider_id <= 0 ) {
			return false;
		}

		global $wpdb;
		$updated = $wpdb->update(
			ConnectWorkflowMigration::opportunities_table_name(),
			array(
				'sponsor_id'           => $sponsor_id,
				'provider_id'          => $provider_id,
				'opportunity_status'   => 'sponsor_accepted',
				'sponsor_accepted_at'  => current_time( 'mysql' ),
				'updated_at'           => current_time( 'mysql' ),
			),
			array( 'id' => $opportunity_id ),
			array( '%d', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return false;
		}

		$this->log_consent_event(
			$opportunity_id,
			array(
				'event_key'    => 'sponsor_opportunity_accepted',
				'event_source' => 'sponsor_portal',
				'actor_role'   => 'sponsor',
				'payload'      => array(
					'sponsor_id'  => $sponsor_id,
					'provider_id' => $provider_id,
				),
			)
		);

		return true;
	}

	public function get_by_id( int $opportunity_id ): ?array {
		if ( $opportunity_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . ConnectWorkflowMigration::opportunities_table_name() . " WHERE id = %d",
				$opportunity_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrate_opportunity( $row ) : null;
	}

	public function get_for_sponsor( int $opportunity_id, int $sponsor_id ): ?array {
		if ( $opportunity_id <= 0 || $sponsor_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . ConnectWorkflowMigration::opportunities_table_name() . " WHERE id = %d AND sponsor_id = %d",
				$opportunity_id,
				$sponsor_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrate_opportunity( $row ) : null;
	}

	public function get_inbox_for_sponsor( int $opportunity_id, int $sponsor_id ): ?array {
		if ( $opportunity_id <= 0 || $sponsor_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$table = ConnectWorkflowMigration::opportunities_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND ( sponsor_id = %d OR ( sponsor_id IS NULL AND opportunity_status IN ('detected','offered') ) )",
				$opportunity_id,
				$sponsor_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrate_opportunity( $row ) : null;
	}

	public function list_for_sponsor( int $sponsor_id ): array {
		if ( $sponsor_id <= 0 ) {
			return array();
		}

		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . ConnectWorkflowMigration::opportunities_table_name() . " WHERE sponsor_id = %d ORDER BY updated_at DESC, id DESC LIMIT 200",
				$sponsor_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'hydrate_opportunity' ), $rows );
	}

	public function list_inbox_for_sponsor( int $sponsor_id ): array {
		if ( $sponsor_id <= 0 ) {
			return array();
		}

		global $wpdb;
		$table = ConnectWorkflowMigration::opportunities_table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE sponsor_id = %d OR ( sponsor_id IS NULL AND opportunity_status IN ('detected','offered') ) ORDER BY sponsor_id = %d DESC, updated_at DESC, id DESC LIMIT 200",
				$sponsor_id,
				$sponsor_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'hydrate_opportunity' ), $rows );
	}

	/**
	 * @param array<string,mixed> $event
	 */
	public function log_consent_event( int $opportunity_id, array $event ): int {
		global $wpdb;

		$inserted = $wpdb->insert(
			ConnectWorkflowMigration::consent_events_table_name(),
			array(
				'blog_id'        => $this->current_blog_id(),
				'opportunity_id' => $opportunity_id > 0 ? $opportunity_id : null,
				'thread_id'      => isset( $event['thread_id'] ) ? (int) $event['thread_id'] : null,
				'handover_id'    => isset( $event['handover_id'] ) ? (int) $event['handover_id'] : null,
				'event_key'      => sanitize_key( (string) ( $event['event_key'] ?? 'unknown' ) ),
				'event_source'   => sanitize_key( (string) ( $event['event_source'] ?? 'system' ) ),
				'actor_role'     => sanitize_key( (string) ( $event['actor_role'] ?? 'system' ) ),
				'event_payload'  => wp_json_encode( (array) ( $event['payload'] ?? array() ) ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	private function hydrate_opportunity( array $row ): array {
		return array(
			'id'                 => (int) ( $row['id'] ?? 0 ),
			'blog_id'            => (int) ( $row['blog_id'] ?? 1 ),
			'actor_email_hash'   => (string) ( $row['actor_email_hash'] ?? '' ),
			'actor_email_domain' => (string) ( $row['actor_email_domain'] ?? '' ),
			'company_domain'     => (string) ( $row['company_domain'] ?? '' ),
			'sponsor_id'         => isset( $row['sponsor_id'] ) ? (int) $row['sponsor_id'] : 0,
			'provider_id'        => isset( $row['provider_id'] ) ? (int) $row['provider_id'] : 0,
			'internal_stage'     => (string) ( $row['internal_stage'] ?? '' ),
			'commercial_tier'    => (string) ( $row['commercial_tier'] ?? '' ),
			'person_score'       => (float) ( $row['person_score'] ?? 0 ),
			'opportunity_status' => (string) ( $row['opportunity_status'] ?? 'detected' ),
			'pricing_model'      => (string) ( $row['pricing_model'] ?? 'cpl' ),
			'unit_price_cents'   => (int) ( $row['unit_price_cents'] ?? 0 ),
			'commission_eligible'=> (int) ( $row['commission_eligible'] ?? 0 ),
			'created_at'         => (string) ( $row['created_at'] ?? '' ),
		);
	}

	private function hash_email( string $email ): string {
		$normalized = strtolower( trim( sanitize_email( $email ) ) );

		return hash_hmac( 'sha256', $normalized, wp_salt( 'auth' ) );
	}

	private function normalize_nullable_string( $value ): ?string {
		$value = trim( sanitize_text_field( (string) $value ) );

		return '' === $value ? null : $value;
	}

	/**
	 * @param array<string,mixed> $signal
	 * @return array{tier:string,pricing_model:string,unit_price_cents:int,commission_eligible:int}
	 */
	private function resolve_pricing_snapshot( array $signal, string $tier ): array {
		$pricing = ConnectTiering::pricing_snapshot( $tier );

		if ( ! isset( $signal['pricing_snapshot'] ) || ! is_array( $signal['pricing_snapshot'] ) ) {
			return $pricing;
		}

		$snapshot = $signal['pricing_snapshot'];

		if ( isset( $snapshot['tier'] ) ) {
			$candidate_tier = sanitize_key( (string) $snapshot['tier'] );
			if ( '' !== $candidate_tier ) {
				$pricing['tier'] = $candidate_tier;
			}
		}

		if ( isset( $snapshot['pricing_model'] ) ) {
			$candidate_model = sanitize_key( (string) $snapshot['pricing_model'] );
			if ( '' !== $candidate_model ) {
				$pricing['pricing_model'] = $candidate_model;
			}
		}

		if ( isset( $snapshot['unit_price_cents'] ) ) {
			$pricing['unit_price_cents'] = max( 0, (int) $snapshot['unit_price_cents'] );
		}

		if ( array_key_exists( 'commission_eligible', $snapshot ) ) {
			$pricing['commission_eligible'] = empty( $snapshot['commission_eligible'] ) ? 0 : 1;
		}

		return $pricing;
	}

	private function current_blog_id(): int {
		$current_blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;

		return max( 1, (int) apply_filters( 'khm_connect_current_blog_id', $current_blog_id ) );
	}
}
