<?php
namespace KH_SMMA\Telemetry;

use function add_action;
use function do_action;
use function register_shutdown_function;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OBS-06: Bounded FIFO in-memory event queue with sampling and async dispatch.
 *
 * Responsibilities:
 *  1. Accept events from EventEmitter::emit() and buffer them in memory,
 *     returning immediately so emit() never blocks request execution.
 *  2. Apply sampling to high-volume debug fields before hook dispatch
 *     (audit records are always complete — sampling only affects the
 *     kh_telemetry_event hook path).
 *  3. Flush all queued events at PHP shutdown via register_shutdown_function(),
 *     or on-demand when flush() is called directly (cron, tests).
 *  4. Route each event through TelemetryRetryService for retry/backoff and
 *     fallback buffer persistence on sink failure.
 *
 * Sampling rules:
 *  - sample_rate = 0.10  →  debug fields kept for 10% of events
 *  - Events matching NEVER_SAMPLE_PREFIXES are always dispatched intact
 *  - Debug fields: prompt_hash, asset_hint_details, debug_metadata
 *
 * Performance target: enqueue() overhead < 1 ms (no DB, no HTTP).
 */
class EventQueue {

	const MAX_SIZE              = 1000;
	const DEFAULT_SAMPLE_RATE   = 0.10;
	const FLUSH_CRON_HOOK       = 'kh_smma_telemetry_flush';

	/**
	 * Fields stripped from hook-dispatch payload at sample_rate probability.
	 * Audit records are never affected.
	 */
	const DEBUG_FIELDS = array( 'prompt_hash', 'asset_hint_details', 'debug_metadata' );

	/**
	 * Event name prefixes that are NEVER subject to sampling.
	 * Safety-critical events must always be complete.
	 */
	const NEVER_SAMPLE_PREFIXES = array(
		'schedule.',
		'variant.edit',
		'sponsor.approval',
		'membership.',
		'alert.',
	);

	/** @var array FIFO event queue */
	private array $queue = array();

	/** @var int */
	private int $max_size;

	/** @var float 0.0–1.0 probability of retaining debug fields */
	private float $sample_rate;

	/** @var bool Whether register_shutdown_function has been called */
	private bool $shutdown_registered = false;

	/** @var TelemetryRetryService|null */
	private $retry;

	/**
	 * @param TelemetryRetryService|null $retry       Retry/buffer service. If null, direct dispatch.
	 * @param int                        $max_size    Maximum queued events before oldest is evicted.
	 * @param float                      $sample_rate 0.0 = always strip debug; 1.0 = always keep.
	 */
	public function __construct(
		?TelemetryRetryService $retry = null,
		int $max_size = self::MAX_SIZE,
		float $sample_rate = self::DEFAULT_SAMPLE_RATE
	) {
		$this->retry       = $retry;
		$this->max_size    = max( 1, $max_size );
		$this->sample_rate = max( 0.0, min( 1.0, $sample_rate ) );
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Add an event to the queue.
	 *
	 * Returns immediately — no DB writes, no HTTP calls.
	 * If the queue is full the oldest entry is evicted (FIFO overflow).
	 *
	 * @param array $event Full canonical event envelope from EventEmitter.
	 */
	public function enqueue( array $event ): void {
		if ( count( $this->queue ) >= $this->max_size ) {
			array_shift( $this->queue ); // Evict oldest — FIFO overflow
		}
		$this->queue[] = $event;
		$this->ensure_shutdown_registered();
	}

	/**
	 * Flush all queued events through sampling and dispatch.
	 *
	 * Drains the queue atomically before dispatching so that events emitted
	 * from within a kh_telemetry_event listener go into the NEXT flush cycle.
	 */
	public function flush(): void {
		if ( empty( $this->queue ) ) {
			return;
		}

		$to_flush    = $this->queue;
		$this->queue = array();

		foreach ( $to_flush as $event ) {
			$sampled = $this->apply_sampling( $event );
			$this->dispatch( $sampled );
		}
	}

	/**
	 * Register the WP action hook for cron-triggered flushes.
	 */
	public function register(): void {
		add_action( self::FLUSH_CRON_HOOK, array( $this, 'flush' ) );
	}

	/**
	 * Current queue depth (for monitoring / tests).
	 */
	public function size(): int {
		return count( $this->queue );
	}

	/**
	 * True when the queue is empty.
	 */
	public function is_empty(): bool {
		return empty( $this->queue );
	}

	// -------------------------------------------------------------------------
	// Sampling (public for unit tests)
	// -------------------------------------------------------------------------

	/**
	 * Apply sampling to the hook-dispatch copy of an event.
	 *
	 * The audit record (written synchronously in EventEmitter) is never touched
	 * here — sampling only affects the kh_telemetry_event hook payload.
	 *
	 * @param array $event Event envelope.
	 * @return array Potentially stripped event.
	 */
	public function apply_sampling( array $event ): array {
		$event_name = (string) ( $event['event_name'] ?? '' );

		if ( $this->is_never_sampled( $event_name ) ) {
			return $event;
		}

		if ( ! $this->should_include_debug_fields() ) {
			foreach ( self::DEBUG_FIELDS as $field ) {
				unset( $event[ $field ] );
			}
		}

		return $event;
	}

	// -------------------------------------------------------------------------
	// Internals
	// -------------------------------------------------------------------------

	/**
	 * Determine whether debug fields should be retained for this dispatch.
	 * Extracted as a method so subclasses / tests can override deterministically.
	 */
	protected function should_include_debug_fields(): bool {
		if ( $this->sample_rate <= 0.0 ) {
			return false;
		}
		if ( $this->sample_rate >= 1.0 ) {
			return true;
		}
		return ( mt_rand( 1, 10000 ) <= (int) ( $this->sample_rate * 10000 ) );
	}

	/**
	 * True when sampling must not be applied to this event type.
	 */
	private function is_never_sampled( string $event_name ): bool {
		foreach ( self::NEVER_SAMPLE_PREFIXES as $prefix ) {
			if ( str_starts_with( $event_name, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Dispatch a single (sampled) event, routing through retry service if present.
	 */
	private function dispatch( array $event ): void {
		if ( $this->retry !== null ) {
			$this->retry->publish_with_retry(
				$event,
				static function ( array $e ): void {
					do_action( 'kh_telemetry_event', $e );
				}
			);
			return;
		}

		// No retry service — best-effort direct dispatch.
		try {
			do_action( 'kh_telemetry_event', $event );
		} catch ( \Throwable $e ) {
			// Non-blocking.
		}
	}

	/**
	 * Register a PHP shutdown function to flush the queue after the response
	 * has been sent.  Only registered once per queue instance.
	 */
	private function ensure_shutdown_registered(): void {
		if ( ! $this->shutdown_registered ) {
			register_shutdown_function( array( $this, 'flush' ) );
			$this->shutdown_registered = true;
		}
	}
}
