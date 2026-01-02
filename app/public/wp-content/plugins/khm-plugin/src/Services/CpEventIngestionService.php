<?php

namespace KHM\Services;

/**
 * Persists canonical prospect intelligence events and maintains ingestion logs.
 */
class CpEventIngestionService {

	/**
	 * WordPress database handler.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Fully-qualified events table name.
	 *
	 * @var string
	 */
	private string $events_table;

	/**
	 * Fully-qualified ingestion log table name.
	 *
	 * @var string
	 */
	private string $logs_table;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $db Optional wpdb override (useful for testing).
	 */
	public function __construct( ?\wpdb $db = null ) {
		global $wpdb;

		$this->wpdb = $db ?: $wpdb;
		if ( ! $this->wpdb instanceof \wpdb ) {
			throw new \RuntimeException( 'Global $wpdb instance is required for ingestion service.' );
		}

		$this->events_table = $this->resolve_table_name( 'cp_events' );
		$this->logs_table   = $this->resolve_table_name( 'cp_ingestion_logs' );
	}

	/**
	 * Store a canonical event row.
	 *
	 * @param array<string,mixed> $event Event payload.
	 * @return int Inserted event ID.
	 */
	public function store_event( array $event ): int {
		$data = wp_parse_args(
			$event,
			array(
				'event_id'            => wp_generate_uuid4(),
				'occurred_at'         => gmdate( 'Y-m-d H:i:s' ),
				'ingested_at'         => gmdate( 'Y-m-d H:i:s' ),
				'actor_email'         => null,
				'actor_name'          => null,
				'company_domain'      => null,
				'source'              => 'unknown',
				'touchpoint'          => 'unknown',
				'stage_hint'          => null,
				'depth_scroll'        => null,
				'depth_dwell_sec'     => null,
				'depth_pct_complete'  => null,
				'topic_tax'           => null,
				'rep_involved'        => null,
				'metadata'            => null,
			)
		);

		$insert = array(
			'event_id'           => $data['event_id'],
			'occurred_at'        => $this->normalize_datetime( $data['occurred_at'] ),
			'ingested_at'        => $this->normalize_datetime( $data['ingested_at'] ),
			'actor_email'        => $this->maybe_string( $data['actor_email'] ),
			'actor_name'         => $this->maybe_string( $data['actor_name'] ),
			'company_domain'     => $this->maybe_string( $data['company_domain'] ),
			'source'             => sanitize_key( $data['source'] ),
			'touchpoint'         => sanitize_key( $data['touchpoint'] ),
			'stage_hint'         => $this->maybe_string( $data['stage_hint'] ),
			'depth_scroll'       => $this->maybe_decimal( $data['depth_scroll'] ),
			'depth_dwell_sec'    => $this->maybe_decimal( $data['depth_dwell_sec'] ),
			'depth_pct_complete' => $this->maybe_decimal( $data['depth_pct_complete'] ),
			'topic_tax'          => $this->maybe_json( $data['topic_tax'] ),
			'rep_involved'       => $this->maybe_string( $data['rep_involved'] ),
			'metadata'           => $this->maybe_json( $data['metadata'] ),
		);

		$formats = array(
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%f',
			'%f',
			'%f',
			'%s',
			'%s',
			'%s',
		);

		$this->wpdb->insert( $this->events_table, $insert, $formats );

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Record ingestion status for a connector.
	 *
	 * @param string               $source       Connector identifier (ga4, esp, webinar).
	 * @param bool                 $success      Whether the ingestion batch succeeded.
	 * @param array<string,mixed>? $error_detail Optional error payload.
	 */
	public function record_ingestion( string $source, bool $success, ?array $error_detail = null ): void {
		if ( empty( $this->logs_table ) ) {
			return;
		}

		$source_slug = sanitize_key( $source );
		$now         = gmdate( 'Y-m-d H:i:s' );

		$existing = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, event_count_24h, updated_at FROM {$this->logs_table} WHERE source = %s",
				$source_slug
			)
		);

		$count = 0;
		if ( $existing ) {
			$count = (int) $existing->event_count_24h;
			if ( isset( $existing->updated_at ) && strtotime( (string) $existing->updated_at ) < ( time() - DAY_IN_SECONDS ) ) {
				$count = 0;
			}
		}

		if ( $success ) {
			$count++;
		}

		$log_data = array(
			'source'        => $source_slug,
			'last_success'  => $success ? $now : null,
			'last_error'    => $success ? null : $now,
			'error_payload' => $success ? null : wp_json_encode( $error_detail ),
			'event_count_24h' => $count,
			'updated_at'      => $now,
		);

		if ( $existing ) {
			$this->wpdb->update(
				$this->logs_table,
				array(
					'last_success'    => $log_data['last_success'],
					'last_error'      => $log_data['last_error'],
					'error_payload'   => $log_data['error_payload'],
					'event_count_24h' => $count,
					'updated_at'      => $now,
				),
				array( 'id' => $existing->id ),
				array( '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			$this->wpdb->insert(
				$this->logs_table,
				array(
					'source'          => $source_slug,
					'last_success'    => $log_data['last_success'],
					'last_error'      => $log_data['last_error'],
					'error_payload'   => $log_data['error_payload'],
					'event_count_24h' => $count,
					'created_at'      => $now,
					'updated_at'      => $now,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Resolve the actual table name, preferring the WordPress prefix if that table exists.
	 *
	 * @param string $base Base table name without prefix.
	 * @return string Table name to use in queries.
	 */
	private function resolve_table_name( string $base ): string {
		$prefixed = $this->wpdb->prefix . $base;
		$found    = $this->wpdb->get_var(
			$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $prefixed )
		);
		if ( $found === $prefixed ) {
			return $prefixed;
		}

		$bare = $this->wpdb->get_var(
			$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $base )
		);
		if ( $bare === $base ) {
			return $base;
		}

		// Default to prefixed for future creates.
		return $prefixed;
	}

	/**
	 * Normalize various date inputs into UTC MySQL datetime format.
	 *
	 * @param string|int|null $value Input date/time.
	 * @return string
	 */
	private function normalize_datetime( $value ): string {
		if ( is_numeric( $value ) ) {
			return gmdate( 'Y-m-d H:i:s', (int) $value );
		}

		$timestamp = strtotime( (string) $value );
		if ( false === $timestamp ) {
			$timestamp = time();
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Return sanitized string or null.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private function maybe_string( $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Return numeric decimal or null.
	 *
	 * @param mixed $value Raw value.
	 * @return float|null
	 */
	private function maybe_decimal( $value ): ?float {
		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			return (float) $value;
		}

		return null;
	}

	/**
	 * Encode arrays/objects as JSON, otherwise pass strings through.
	 *
	 * @param mixed $value Value to encode.
	 * @return string|null
	 */
	private function maybe_json( $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return wp_json_encode( $value );
	}
}
