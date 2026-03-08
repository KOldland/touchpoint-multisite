<?php
namespace KH_SMMA\Telemetry;

use KH_SMMA\Services\AuditLogger;

use function add_action;
use function get_option;
use function update_option;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OBS-04: Alert evaluation service.
 *
 * Reads recent analytics snapshots and audit telemetry, evaluates alert
 * conditions, emits alert events, and records audit entries.
 *
 * Alert evaluation runs on the kh_smma_alert_evaluate cron hook, scheduled
 * every 5 minutes (reusing the kh_smma_five_minutes WP cron interval).
 *
 * Active alert state is persisted in the kh_smma_active_alerts WP option so
 * the dashboard can surface it without a fresh evaluation on every page load.
 *
 * This class is read-only with respect to business data. It never modifies
 * schedules, variants, compliance rules, or aggregation logic.
 */
class AlertEvaluator {

	const CRON_HOOK           = 'kh_smma_alert_evaluate';
	const ACTIVE_ALERTS_OPTION = 'kh_smma_active_alerts';

	// Alert type identifiers.
	const TYPE_COMPLIANCE_FAIL = 'compliance_fail_rate';
	const TYPE_QUEUE_BACKLOG   = 'queue_backlog';
	const TYPE_DISPATCH_ERRORS = 'dispatch_errors';

	// Severity levels.
	const SEV_WARNING  = 'warning';
	const SEV_CRITICAL = 'critical';

	// Threshold constants (public for testability).
	const COMPLIANCE_WARN_RATE   = 10.0; // percent — 2 consecutive snapshots
	const COMPLIANCE_CRIT_RATE   = 25.0; // percent — immediate
	const QUEUE_BACKLOG_WARN     = 20;   // absolute backlog count
	const DISPATCH_ERROR_WARN    = 5;    // failures in recent telemetry window

	/** @var MetricsSnapshotRepository */
	private $snapshots;

	/** @var EventEmitter */
	private $emitter;

	/** @var AuditLogger */
	private $audit;

	public function __construct(
		MetricsSnapshotRepository $snapshots,
		EventEmitter $emitter,
		AuditLogger $audit
	) {
		$this->snapshots = $snapshots;
		$this->emitter   = $emitter;
		$this->audit     = $audit;
	}

	/**
	 * Register the cron callback.
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'evaluate' ) );
	}

	/**
	 * Run all alert checks.
	 *
	 * Called by the cron hook. Results are stored in the active-alerts option
	 * and emitted as telemetry events + audit entries.
	 */
	public function evaluate(): void {
		$recent    = $this->snapshots->get_recent( 2 );
		$telemetry = $this->audit->get_recent_telemetry_events( 100 );

		$compliance = $this->check_compliance_fail_rate( $recent );
		$backlog    = $this->check_queue_backlog( $recent );
		$dispatch   = $this->check_dispatch_errors( $telemetry );

		$this->resolve_alert( self::TYPE_COMPLIANCE_FAIL, $compliance );
		$this->resolve_alert( self::TYPE_QUEUE_BACKLOG, $backlog );
		$this->resolve_alert( self::TYPE_DISPATCH_ERRORS, $dispatch );
	}

	// -------------------------------------------------------------------------
	// Individual alert checks (public for unit testability)
	// -------------------------------------------------------------------------

	/**
	 * Compliance fail-rate alert.
	 *
	 * Triggers (warning) when compliance_fail / total > 10% in the two most
	 * recent consecutive snapshots.  Escalates to critical when > 25% in the
	 * latest snapshot alone.
	 *
	 * @param array $recent  Two most recent snapshot rows (newest first).
	 * @return array|null    Alert descriptor, or null when no alert.
	 */
	public function check_compliance_fail_rate( array $recent ): ?array {
		if ( empty( $recent ) ) {
			return null;
		}

		$latest_metrics = $recent[0]['metrics'] ?? array();
		$latest_rate    = $this->compliance_fail_rate( $latest_metrics );

		// Critical — single snapshot over 25%.
		if ( $latest_rate > self::COMPLIANCE_CRIT_RATE ) {
			return array(
				'alert_type'     => self::TYPE_COMPLIANCE_FAIL,
				'severity'       => self::SEV_CRITICAL,
				'fail_rate'      => $latest_rate,
				'snapshot_time'  => $recent[0]['created_at'] ?? '',
				'metrics_context' => $this->metrics_context( $latest_metrics ),
			);
		}

		// Warning — both of the last two snapshots exceed 10%.
		if ( $latest_rate > self::COMPLIANCE_WARN_RATE && count( $recent ) >= 2 ) {
			$prev_metrics = $recent[1]['metrics'] ?? array();
			$prev_rate    = $this->compliance_fail_rate( $prev_metrics );
			if ( $prev_rate > self::COMPLIANCE_WARN_RATE ) {
				return array(
					'alert_type'     => self::TYPE_COMPLIANCE_FAIL,
					'severity'       => self::SEV_WARNING,
					'fail_rate'      => $latest_rate,
					'snapshot_time'  => $recent[0]['created_at'] ?? '',
					'metrics_context' => $this->metrics_context( $latest_metrics ),
				);
			}
		}

		return null;
	}

	/**
	 * Queue backlog alert.
	 *
	 * Triggers (warning) when backlog (schedule_created − schedule_dispatched)
	 * exceeds 20 in the latest snapshot.
	 *
	 * @param array $recent  Recent snapshot rows (newest first).
	 * @return array|null
	 */
	public function check_queue_backlog( array $recent ): ?array {
		if ( empty( $recent ) ) {
			return null;
		}

		$metrics    = $recent[0]['metrics'] ?? array();
		$created    = (int) ( $metrics['schedule_created']    ?? 0 );
		$dispatched = (int) ( $metrics['schedule_dispatched'] ?? 0 );
		$backlog    = max( 0, $created - $dispatched );

		if ( $backlog > self::QUEUE_BACKLOG_WARN ) {
			return array(
				'alert_type'      => self::TYPE_QUEUE_BACKLOG,
				'severity'        => self::SEV_WARNING,
				'backlog_size'    => $backlog,
				'dispatch_latency' => 0, // Not calculated without per-event timestamps
				'snapshot_time'   => $recent[0]['created_at'] ?? '',
				'metrics_context' => $this->metrics_context( $metrics ),
			);
		}

		return null;
	}

	/**
	 * Dispatch error alert.
	 *
	 * Counts recent schedule.dispatch events in the audit telemetry feed where
	 * result == 'failed'.  Triggers (warning) when > 5 failures are present
	 * in the most recent batch of telemetry.
	 *
	 * @param array $recent_telemetry  Recent decoded audit rows.
	 * @return array|null
	 */
	public function check_dispatch_errors( array $recent_telemetry ): ?array {
		$failure_count = 0;
		$adapters      = array();
		$snapshot_time = '';

		foreach ( $recent_telemetry as $row ) {
			$d          = is_array( $row->decoded_details ?? null ) ? $row->decoded_details : array();
			$event_name = (string) ( $d['event_name'] ?? '' );
			$payload    = is_array( $d['payload']    ?? null ) ? $d['payload']    : array();

			if ( 'schedule.dispatch' !== $event_name ) {
				continue;
			}

			$result = (string) ( $payload['result'] ?? '' );
			if ( 'failed' !== $result ) {
				continue;
			}

			$failure_count++;
			$adapter = (string) ( $payload['adapter'] ?? 'unknown' );
			if ( ! in_array( $adapter, $adapters, true ) ) {
				$adapters[] = $adapter;
			}
			if ( '' === $snapshot_time ) {
				$snapshot_time = (string) ( $row->created_at ?? '' );
			}
		}

		if ( $failure_count > self::DISPATCH_ERROR_WARN ) {
			return array(
				'alert_type'     => self::TYPE_DISPATCH_ERRORS,
				'severity'       => self::SEV_WARNING,
				'failure_count'  => $failure_count,
				'adapter'        => implode( ', ', $adapters ) ?: 'unknown',
				'snapshot_time'  => $snapshot_time,
				'metrics_context' => array( 'failure_count' => $failure_count ),
			);
		}

		return null;
	}

	// -------------------------------------------------------------------------
	// Alert state (public for dashboard access)
	// -------------------------------------------------------------------------

	/**
	 * Return current active alerts (as stored in WP option after last evaluate()).
	 *
	 * @return array  Keyed by alert_type.
	 */
	public function get_active_alerts(): array {
		$stored = get_option( self::ACTIVE_ALERTS_OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Return up to $limit recent alert.triggered audit events.
	 *
	 * @param int $limit 1–50.
	 * @return array Decoded audit rows.
	 */
	public function get_alert_history( int $limit = 10 ): array {
		$limit  = max( 1, min( 50, $limit ) );
		$recent = $this->audit->get_recent_telemetry_events( 200 );

		$history = array();
		foreach ( $recent as $row ) {
			$d = is_array( $row->decoded_details ?? null ) ? $row->decoded_details : array();
			if ( 'alert.triggered' !== ( $d['event_name'] ?? '' ) ) {
				continue;
			}
			$history[] = $row;
			if ( count( $history ) >= $limit ) {
				break;
			}
		}

		return $history;
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Persist alert to WP option and emit alert event + audit entry.
	 * Clears the entry when $alert is null (condition resolved).
	 */
	private function resolve_alert( string $type, ?array $alert ): void {
		if ( null === $alert ) {
			$this->clear_active_alert( $type );
			return;
		}

		$this->set_active_alert( $type, $alert );
		$this->emit_alert( $alert );
	}

	/**
	 * Emit the alert as a telemetry event and record an audit entry.
	 * All exceptions are swallowed — alert failures must never break evaluation.
	 */
	private function emit_alert( array $alert ): void {
		try {
			$payload = array_merge( $alert, array( 'service' => 'obs' ) );
			$this->emitter->emit( 'alert.triggered', $payload );
		} catch ( \Throwable $e ) {
			// Non-blocking.
		}
	}

	/**
	 * Upsert the active alert entry for $type.
	 */
	private function set_active_alert( string $type, array $alert ): void {
		$active          = $this->get_active_alerts();
		$active[ $type ] = array_merge( $alert, array( 'updated_at' => time() ) );
		update_option( self::ACTIVE_ALERTS_OPTION, $active, false );
	}

	/**
	 * Remove the active alert entry for $type when condition clears.
	 */
	private function clear_active_alert( string $type ): void {
		$active = $this->get_active_alerts();
		if ( isset( $active[ $type ] ) ) {
			unset( $active[ $type ] );
			update_option( self::ACTIVE_ALERTS_OPTION, $active, false );
		}
	}

	/**
	 * Build a compact metrics summary for audit context (PII-free counts only).
	 */
	private function metrics_context( array $metrics ): array {
		$keys = array(
			'compliance_ok', 'compliance_warn', 'compliance_fail',
			'schedule_created', 'schedule_dispatched',
			'generate_requests', 'variants_created',
		);
		$ctx = array();
		foreach ( $keys as $k ) {
			if ( isset( $metrics[ $k ] ) ) {
				$ctx[ $k ] = (int) $metrics[ $k ];
			}
		}
		return $ctx;
	}

	/**
	 * Calculate compliance fail rate (0–100) for a metrics array.
	 */
	private function compliance_fail_rate( array $metrics ): float {
		$ok   = (int) ( $metrics['compliance_ok']   ?? 0 );
		$warn = (int) ( $metrics['compliance_warn']  ?? 0 );
		$fail = (int) ( $metrics['compliance_fail']  ?? 0 );
		$total = $ok + $warn + $fail;
		if ( $total === 0 ) {
			return 0.0;
		}
		return round( $fail / $total * 100, 2 );
	}
}
