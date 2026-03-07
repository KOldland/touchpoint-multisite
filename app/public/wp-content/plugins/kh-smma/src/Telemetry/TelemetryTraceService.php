<?php
namespace KH_SMMA\Telemetry;

use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Telemetry\TelemetryPayloadSanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OBS-08: Fetch, format, and search telemetry trace timelines.
 *
 * Provides read-only access to audit log telemetry events, ordered
 * chronologically, with optional PII masking via TelemetryPayloadSanitizer.
 *
 * Supports lookup by:
 *  - trace_id  — direct correlation ID
 *  - schedule_id — returns all traces that contain a matching schedule_id
 *  - variant_id  — returns all traces that contain a matching variant_id
 */
class TelemetryTraceService {

	/** @var AuditLogger */
	private $audit;

	/** @var TelemetryPayloadSanitizer|null */
	private $sanitizer;

	public function __construct( AuditLogger $audit, ?TelemetryPayloadSanitizer $sanitizer = null ) {
		$this->audit     = $audit;
		$this->sanitizer = $sanitizer;
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Return a chronological timeline of events for a given trace_id.
	 *
	 * Each item in the returned array is a formatted event map:
	 *   trace_id, event_name, timestamp, created_at, payload (sanitized)
	 *
	 * @param  string $trace_id UUID correlation ID.
	 * @return array  Ordered list of formatted event maps; empty if not found.
	 */
	public function get_trace_timeline( string $trace_id ): array {
		if ( '' === trim( $trace_id ) ) {
			return array();
		}

		$rows = $this->audit->get_events_by_trace( $trace_id );
		return $this->format_rows( $rows );
	}

	/**
	 * Search for traces that include events referencing a schedule_id.
	 *
	 * Returns a flat list of formatted events across all matching traces,
	 * ordered by audit log row id ascending.
	 *
	 * @param  string $schedule_id Schedule identifier to search for.
	 * @return array  Formatted events; empty if none found.
	 */
	public function find_by_schedule_id( string $schedule_id ): array {
		if ( '' === trim( $schedule_id ) ) {
			return array();
		}

		$recent = $this->audit->get_recent_telemetry_events( 100 );
		return $this->filter_by_payload_field( $recent, 'schedule_id', $schedule_id );
	}

	/**
	 * Search for traces that include events referencing a variant_id.
	 *
	 * @param  string $variant_id Variant identifier to search for.
	 * @return array  Formatted events; empty if none found.
	 */
	public function find_by_variant_id( string $variant_id ): array {
		if ( '' === trim( $variant_id ) ) {
			return array();
		}

		$recent = $this->audit->get_recent_telemetry_events( 100 );
		return $this->filter_by_payload_field( $recent, 'variant_id', $variant_id );
	}

	/**
	 * Extract key diagnostic fields from a formatted event map.
	 *
	 * Useful for timeline rendering — extracts the most contextually
	 * meaningful fields for display without exposing raw payloads.
	 *
	 * @param  array $event Formatted event map from get_trace_timeline().
	 * @return array  Associative array of key→value diagnostic fields.
	 */
	public function extract_key_fields( array $event ): array {
		$payload = $event['payload'] ?? array();
		$fields  = array();

		$candidates = array(
			'outcome', 'result', 'latency_ms', 'channel', 'adapter',
			'schedule_id', 'variant_id', 'sponsor_id', 'editor_id',
			'violations', 'passed', 'rules_matched', 'tier',
			'attribution_id', 'confidence_score',
		);

		foreach ( $candidates as $key ) {
			if ( isset( $payload[ $key ] ) ) {
				$fields[ $key ] = $payload[ $key ];
			}
		}

		return $fields;
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Format raw audit rows into event timeline maps.
	 *
	 * @param  array $rows Raw audit log rows from AuditLogger.
	 * @return array  Formatted event maps.
	 */
	private function format_rows( array $rows ): array {
		$timeline = array();

		foreach ( $rows as $row ) {
			$decoded = $row->decoded_details ?? array();
			if ( ! is_array( $decoded ) ) {
				continue;
			}

			$payload = is_array( $decoded['payload'] ?? null ) ? $decoded['payload'] : array();

			// Apply PII sanitizer if configured.
			if ( $this->sanitizer !== null ) {
				$payload = $this->sanitizer->sanitize( $payload );
			}

			$timeline[] = array(
				'trace_id'   => (string) ( $decoded['trace_id']   ?? '' ),
				'event_name' => (string) ( $decoded['event_name'] ?? '' ),
				'timestamp'  => (int)    ( $decoded['timestamp']  ?? 0 ),
				'created_at' => (string) ( $row->created_at       ?? '' ),
				'payload'    => $payload,
			);
		}

		return $timeline;
	}

	/**
	 * Filter a list of raw audit rows by a payload field value.
	 *
	 * @param  array  $rows       Raw audit rows from AuditLogger.
	 * @param  string $field      Payload field key to match.
	 * @param  string $value      Expected field value.
	 * @return array  Formatted events matching the filter.
	 */
	private function filter_by_payload_field( array $rows, string $field, string $value ): array {
		$matching = array();

		foreach ( $rows as $row ) {
			$decoded = $row->decoded_details ?? array();
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			$payload = is_array( $decoded['payload'] ?? null ) ? $decoded['payload'] : array();
			if ( (string) ( $payload[ $field ] ?? '' ) === $value ) {
				$matching[] = $row;
			}
		}

		// Reverse to get ascending order (get_recent_telemetry_events returns DESC).
		return $this->format_rows( array_reverse( $matching ) );
	}
}
