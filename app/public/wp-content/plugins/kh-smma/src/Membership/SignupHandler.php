<?php
namespace KH_SMMA\Membership;

use KH_SMMA\Telemetry\EventEmitter;
use KH_SMMA\Telemetry\TraceContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles membership signup telemetry emission.
 *
 * MEM bucket: add business logic here when implementing the signup flow.
 * This class is intentionally thin — it emits the membership.signup event
 * and nothing else. PII fields (email, name) must NOT appear in the event
 * payload; use user_id (opaque integer) and masked attribution identifiers
 * only.
 *
 * Caller contract: invoke record_signup() after a successful signup
 * transaction has been committed.  Fire the hook:
 *
 *   do_action( 'kh_mem_signup', [
 *       'user_id'        => (int)   $user_id,
 *       'tier'           => (string) $tier,           // e.g. 'standard', 'premium'
 *       'payment_status' => (string) $payment_status, // 'paid' | 'trial' | 'free'
 *       'attribution_id' => (string) $attribution_id, // opaque promo ID
 *       'trace_id'       => (string) $trace_id,       // optional, caller-supplied
 *   ] );
 */
class SignupHandler {

	/** @var EventEmitter */
	private $emitter;

	public function __construct( EventEmitter $emitter ) {
		$this->emitter = $emitter;
	}

	/**
	 * Register WordPress action listener for kh_mem_signup.
	 */
	public function register(): void {
		add_action( 'kh_mem_signup', array( $this, 'handle_signup' ) );
	}

	/**
	 * Emit membership.signup telemetry event.
	 *
	 * @param array $data Keys: user_id, tier, payment_status, attribution_id, trace_id (opt).
	 */
	public function handle_signup( array $data ): void {
		if ( isset( $data['trace_id'] ) && '' !== (string) $data['trace_id'] ) {
			TraceContext::init( (string) $data['trace_id'] );
		}

		$this->emitter->emit( 'membership.signup', array(
			'user_id'        => (int) ( $data['user_id'] ?? 0 ),
			'tier'           => sanitize_text_field( (string) ( $data['tier'] ?? '' ) ),
			'payment_status' => sanitize_text_field( (string) ( $data['payment_status'] ?? '' ) ),
			'attribution_id' => sanitize_text_field( (string) ( $data['attribution_id'] ?? '' ) ),
			'service'        => 'mem',
		) );
	}

	/**
	 * Direct programmatic call (for use within MEM business logic).
	 */
	public function record_signup( int $user_id, string $tier, string $payment_status, string $attribution_id, string $trace_id = '' ): void {
		$this->handle_signup( compact( 'user_id', 'tier', 'payment_status', 'attribution_id', 'trace_id' ) );
	}
}
