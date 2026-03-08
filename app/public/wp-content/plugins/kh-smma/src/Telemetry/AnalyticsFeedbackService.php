<?php
namespace KH_SMMA\Telemetry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Telemetry analytics aggregation service.
 *
 * Listens to the kh_telemetry_event action fired by EventEmitter and
 * accumulates metrics in a WP option (fast, lock-free rolling counters).
 *
 * Every 5 minutes a cron job calls flush_snapshot() which:
 *  1. Computes the final metrics for the current window.
 *  2. Persists a snapshot row via MetricsSnapshotRepository.
 *  3. Resets the accumulator for the next window.
 *
 * This is DISTINCT from the legacy Services\AnalyticsFeedbackService which
 * tracks schedule status changes.  This service tracks telemetry events
 * across ALL workflows (generate, compliance, variant edit, schedule, MEM).
 *
 * Privacy: accumulator and snapshots contain only counts, distributions, and
 * latency metrics.  No PII, no raw text, no user identifiers.
 */
class AnalyticsFeedbackService {

	const ACCUMULATOR_KEY = 'kh_smma_telemetry_accumulator';
	const CRON_HOOK       = 'kh_smma_analytics_flush';

	/** @var MetricsSnapshotRepository */
	private $repo;

	public function __construct( MetricsSnapshotRepository $repo ) {
		$this->repo = $repo;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * OBS-06: also registers on EventQueue::FLUSH_CRON_HOOK so that a timely
	 * analytics snapshot is captured whenever the telemetry queue is flushed.
	 */
	public function register(): void {
		add_action( 'kh_telemetry_event',          array( $this, 'handle_event' ) );
		add_action( self::CRON_HOOK,                array( $this, 'flush_snapshot' ) );
		add_action( EventQueue::FLUSH_CRON_HOOK,    array( $this, 'maybe_flush_snapshot' ) );
	}

	/**
	 * Flush the analytics snapshot only when the accumulator has data.
	 * Called on kh_smma_telemetry_flush (OBS-06 queue flush cron).
	 */
	public function maybe_flush_snapshot(): void {
		$acc = $this->get_accumulator();
		// Only flush when at least one event has been accumulated this window.
		$has_data = (
			$acc['generate_requests']   > 0 ||
			$acc['variants_created']    > 0 ||
			$acc['compliance_ok']       > 0 ||
			$acc['compliance_fail']     > 0 ||
			$acc['schedule_created']    > 0 ||
			$acc['schedule_dispatched'] > 0 ||
			$acc['membership_signups']  > 0
		);
		if ( $has_data ) {
			$this->flush_snapshot();
		}
	}

	// -------------------------------------------------------------------------
	// Event accumulation
	// -------------------------------------------------------------------------

	/**
	 * Handle an incoming telemetry event and update the accumulator.
	 *
	 * Called synchronously from EventEmitter via do_action('kh_telemetry_event').
	 * Must not throw — any exception would bubble through EventEmitter's catch.
	 *
	 * @param array $event Full event envelope from EventEmitter.
	 */
	public function handle_event( array $event ): void {
		$event_name = (string) ( $event['event_name'] ?? '' );
		if ( '' === $event_name ) {
			return;
		}

		$acc = $this->get_accumulator();

		switch ( $event_name ) {
			case 'generate.request':
				$acc['generate_requests']++;
				break;

			case 'generate.response':
				$acc['variants_created'] += max( 0, (int) ( $event['variant_count_generated'] ?? 0 ) );
				$latency                  = (int) ( $event['latency_ms'] ?? 0 );
				if ( $latency > 0 ) {
					$acc['total_latency_ms'] += $latency;
					$acc['latency_count']++;
				}
				break;

			case 'variant.edit':
				$acc['variant_edits']++;
				break;

			case 'compliance.check':
				$outcome = strtoupper( (string) ( $event['outcome'] ?? 'OK' ) );
				if ( 'OK' === $outcome || 'PASS' === $outcome ) {
					$acc['compliance_ok']++;
				} elseif ( 'WARN' === $outcome ) {
					$acc['compliance_warn']++;
				} elseif ( 'FAIL' === $outcome ) {
					$acc['compliance_fail']++;
				}
				break;

			case 'schedule.create':
				$acc['schedule_created']++;
				break;

			case 'schedule.dispatch':
				$acc['schedule_dispatched']++;
				break;

			case 'membership.signup':
				$acc['membership_signups']++;
				break;

			case 'promotion_attribution':
				$acc['promotion_attributions']++;
				break;
		}

		$this->save_accumulator( $acc );
	}

	// -------------------------------------------------------------------------
	// Snapshot flush
	// -------------------------------------------------------------------------

	/**
	 * Compute metrics from the current accumulator, persist a snapshot, and
	 * reset the accumulator for the next window.
	 *
	 * Called by the kh_smma_analytics_flush cron event (every 5 minutes).
	 * May also be called directly for testing or on-demand export.
	 */
	public function flush_snapshot(): void {
		$acc = $this->get_accumulator();

		$metrics = $this->compute_metrics( $acc );

		$this->repo->write_snapshot( $metrics, (int) ( $acc['window_start'] ?: time() ) );

		// Fire action for downstream consumers (dashboards, alerts).
		do_action( 'kh_smma_analytics_snapshot_flushed', $metrics );

		// Reset accumulator for next window.
		$this->reset_accumulator();
	}

	/**
	 * Compute the final metrics array from a raw accumulator.
	 * Public to allow unit testing without DB writes.
	 *
	 * @param array $acc Raw accumulator values.
	 * @return array PII-free metrics snapshot.
	 */
	public function compute_metrics( array $acc ): array {
		$latency_count = max( 1, (int) ( $acc['latency_count'] ?? 0 ) );
		$total_latency = (int) ( $acc['total_latency_ms'] ?? 0 );

		return array(
			'window_start'             => gmdate( 'c', (int) ( $acc['window_start'] ?? time() ) ),
			'generate_requests'        => (int) ( $acc['generate_requests'] ?? 0 ),
			'variants_created'         => (int) ( $acc['variants_created'] ?? 0 ),
			'variant_edits'            => (int) ( $acc['variant_edits'] ?? 0 ),
			'compliance_ok'            => (int) ( $acc['compliance_ok'] ?? 0 ),
			'compliance_warn'          => (int) ( $acc['compliance_warn'] ?? 0 ),
			'compliance_fail'          => (int) ( $acc['compliance_fail'] ?? 0 ),
			'schedule_created'         => (int) ( $acc['schedule_created'] ?? 0 ),
			'schedule_dispatched'      => (int) ( $acc['schedule_dispatched'] ?? 0 ),
			'membership_signups'       => (int) ( $acc['membership_signups'] ?? 0 ),
			'promotion_attributions'   => (int) ( $acc['promotion_attributions'] ?? 0 ),
			'avg_generate_latency_ms'  => $latency_count > 0 && $total_latency > 0
				? (float) round( $total_latency / $latency_count, 1 )
				: 0.0,
		);
	}

	// -------------------------------------------------------------------------
	// Accumulator helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the current accumulator, initialised with defaults if empty.
	 */
	public function get_accumulator(): array {
		$raw = get_option( self::ACCUMULATOR_KEY, array() );

		$defaults = array(
			'window_start'           => time(),
			'generate_requests'      => 0,
			'variants_created'       => 0,
			'variant_edits'          => 0,
			'compliance_ok'          => 0,
			'compliance_warn'        => 0,
			'compliance_fail'        => 0,
			'schedule_created'       => 0,
			'schedule_dispatched'    => 0,
			'membership_signups'     => 0,
			'promotion_attributions' => 0,
			'total_latency_ms'       => 0,
			'latency_count'          => 0,
		);

		return is_array( $raw ) ? array_merge( $defaults, $raw ) : $defaults;
	}

	private function save_accumulator( array $acc ): void {
		update_option( self::ACCUMULATOR_KEY, $acc, false );
	}

	private function reset_accumulator(): void {
		update_option( self::ACCUMULATOR_KEY, array( 'window_start' => time() ), false );
	}
}
