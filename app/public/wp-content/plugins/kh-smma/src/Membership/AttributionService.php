<?php
namespace KH_SMMA\Membership;

use KH_SMMA\Telemetry\EventEmitter;
use KH_SMMA\Telemetry\TraceContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records promotion attribution telemetry events.
 *
 * MEM bucket: add conversion attribution business logic here.
 * This class emits promotion_attribution events only.  No PII (emails, names)
 * must appear in the payload.  UTM parameters are contextual/campaign-level
 * and are considered safe to record.
 *
 * Hook contract:
 *
 *   do_action( 'kh_mem_attribution', [
 *       'schedule_id'      => (string) $schedule_id,
 *       'sponsor_id'       => (string) $sponsor_id,
 *       'utm_source'       => (string) $utm_source,
 *       'utm_campaign'     => (string) $utm_campaign,
 *       'confidence_score' => (float)  $confidence_score, // 0.0–1.0
 *       'trace_id'         => (string) $trace_id,         // optional
 *   ] );
 */
class AttributionService {

	/** @var EventEmitter */
	private $emitter;

	public function __construct( EventEmitter $emitter ) {
		$this->emitter = $emitter;
	}

	/**
	 * Register WordPress action listener for kh_mem_attribution.
	 */
	public function register(): void {
		add_action( 'kh_mem_attribution', array( $this, 'handle_attribution' ) );
	}

	/**
	 * Emit promotion_attribution telemetry event.
	 *
	 * @param array $data Keys: schedule_id, sponsor_id, utm_source, utm_campaign,
	 *                    confidence_score, trace_id (opt).
	 */
	public function handle_attribution( array $data ): void {
		if ( isset( $data['trace_id'] ) && '' !== (string) $data['trace_id'] ) {
			TraceContext::init( (string) $data['trace_id'] );
		}

		$this->emitter->emit( 'promotion_attribution', array(
			'schedule_id'      => sanitize_text_field( (string) ( $data['schedule_id'] ?? '' ) ),
			'sponsor_id'       => sanitize_text_field( (string) ( $data['sponsor_id'] ?? '' ) ),
			'utm_source'       => sanitize_text_field( (string) ( $data['utm_source'] ?? '' ) ),
			'utm_campaign'     => sanitize_text_field( (string) ( $data['utm_campaign'] ?? '' ) ),
			'confidence_score' => (float) ( $data['confidence_score'] ?? 0.0 ),
			'service'          => 'mem',
		) );
	}

	/**
	 * Direct programmatic call (for use within MEM business logic).
	 */
	public function record_attribution( string $schedule_id, string $sponsor_id, string $utm_source, string $utm_campaign, float $confidence_score, string $trace_id = '' ): void {
		$this->handle_attribution( compact( 'schedule_id', 'sponsor_id', 'utm_source', 'utm_campaign', 'confidence_score', 'trace_id' ) );
	}
}
