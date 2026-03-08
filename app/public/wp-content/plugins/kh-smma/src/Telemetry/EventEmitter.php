<?php
namespace KH_SMMA\Telemetry;

use KH_SMMA\Services\AuditLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central telemetry event emitter.
 *
 * OBS-06 update: emit() is now non-blocking.
 * OBS-07 update: payload is sanitized (PII masked, unknown fields stripped)
 *                before audit persistence and dispatch.
 *
 * Responsibilities:
 *  1. Sanitize caller payload (OBS-07) — mask PII, strip unknown fields.
 *  2. Build the canonical event envelope (event_name, trace_id, timestamp, service).
 *  3. Persist synchronously via AuditLogger — audit records are ALWAYS complete,
 *     regardless of queue or sampling state.
 *  4. Enqueue the event in EventQueue for async dispatch after the response is
 *     sent (via PHP shutdown handler or WP cron flush).  When no queue is
 *     configured the event is dispatched directly (backward-compatible).
 *
 * Performance target: emit() overhead < 5 ms (sanitize + audit write + array append).
 */
class EventEmitter {

	/** @var AuditLogger */
	private $audit;

	/** @var EventQueue|null */
	private $queue;

	/** @var TelemetryPayloadSanitizer|null */
	private $sanitizer;

	/**
	 * @param AuditLogger                    $audit     Synchronous audit persistence (always runs).
	 * @param EventQueue|null                $queue     Optional async dispatch queue (OBS-06).
	 *                                                  If null, falls back to synchronous do_action.
	 * @param TelemetryPayloadSanitizer|null $sanitizer Optional PII sanitizer (OBS-07).
	 *                                                  If null, payload is passed through unsanitized
	 *                                                  (backward-compatible, not recommended for production).
	 */
	public function __construct(
		AuditLogger $audit,
		?EventQueue $queue = null,
		?TelemetryPayloadSanitizer $sanitizer = null
	) {
		$this->audit     = $audit;
		$this->queue     = $queue;
		$this->sanitizer = $sanitizer;
	}

	/**
	 * Emit a named telemetry event.
	 *
	 * @param string $event_name Canonical event name (e.g. "generate.request").
	 * @param array  $payload    Event-specific fields. PII is automatically
	 *                           masked when a sanitizer is configured.
	 */
	public function emit( string $event_name, array $payload ): void {
		$trace_id  = TraceContext::require_current();
		$timestamp = time();

		// OBS-07: Sanitize payload before building envelope.
		//         Audit records and dispatch both receive the sanitized version.
		if ( $this->sanitizer !== null ) {
			$payload = $this->sanitizer->sanitize( $payload );
		}

		// Build canonical envelope — caller payload fills contextual fields.
		$event = array_merge(
			array(
				'service' => $payload['service'] ?? 'smma',
			),
			$payload,
			array(
				'event_name' => $event_name,
				'trace_id'   => $trace_id,
				'timestamp'  => $timestamp,
			)
		);

		// Step 1 — Audit persistence (synchronous, guaranteed, 100% complete).
		//           Sampling and queue failures never affect this record.
		try {
			$this->audit->record_event( $trace_id, $event_name, $timestamp, $event );
		} catch ( \Throwable $e ) {
			// Swallow — telemetry must never break business logic.
		}

		// Step 2 — Async dispatch: enqueue for post-response flush.
		//           Falls back to synchronous dispatch when no queue is wired.
		if ( $this->queue !== null ) {
			try {
				$this->queue->enqueue( $event );
			} catch ( \Throwable $e ) {
				// Swallow.
			}
		} else {
			// Backward-compat: direct synchronous dispatch (no queue configured).
			try {
				do_action( 'kh_telemetry_event', $event );
			} catch ( \Throwable $e ) {
				// Swallow.
			}
		}

		// Step 3 — Back-compat shim for existing kh_smma_telemetry_event listeners.
		try {
			do_action( 'kh_smma_telemetry_event', $event_name, $payload );
		} catch ( \Throwable $e ) {
			// Swallow.
		}
	}
}
