<?php
namespace KH_SMMA\Admin;

use KH_SMMA\Security\CapabilityManager;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Telemetry\AlertEvaluator;
use KH_SMMA\Telemetry\MetricsSnapshotRepository;

use function add_action;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function sanitize_text_field;
use function wp_die;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OBS-03/04: Observability Dashboard admin page.
 *
 * Registered under the existing SMMA admin menu.
 * Capability required: view_observability (editors + admins)
 * Page slug: kh-observability
 *
 * When ?trace_id= is present the page delegates to TelemetryTracePage for a
 * drill-down view of a single workflow trace.
 *
 * All data is read-only.  This page never writes to any table.
 */
class ObservabilityDashboardPage {

	const PAGE_SLUG = 'kh-observability';

	/** @var MetricsSnapshotRepository */
	private $snapshots;

	/** @var AuditLogger */
	private $audit;

	/** @var AlertEvaluator|null */
	private $alert_evaluator;

	public function __construct( MetricsSnapshotRepository $snapshots, AuditLogger $audit, ?AlertEvaluator $alert_evaluator = null ) {
		$this->snapshots       = $snapshots;
		$this->audit           = $audit;
		$this->alert_evaluator = $alert_evaluator;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
			'kh-smma-dashboard',
			esc_html__( 'Observability', 'kh-smma' ),
			esc_html__( 'Observability', 'kh-smma' ),
			CapabilityManager::CAP_VIEW_OBSERVABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		if ( ! CapabilityManager::can_view_observability() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
		}

		// Trace drill-down view.
		$trace_id = sanitize_text_field( $_GET['trace_id'] ?? '' );
		if ( '' !== $trace_id ) {
			$trace_page = new TelemetryTracePage( $this->audit );
			$trace_page->render( $trace_id );
			return;
		}

		$data = $this->get_dashboard_data();
		$this->render_dashboard( $data );
	}

	// -------------------------------------------------------------------------
	// Data layer (public — testable without rendering)
	// -------------------------------------------------------------------------

	/**
	 * Assemble all data needed to render the dashboard.
	 * Returns a structured array; never echoes HTML.
	 *
	 * @return array{
	 *   latest: array|null,
	 *   recent: array,
	 *   throughput: array,
	 *   compliance: array,
	 *   scheduling: array,
	 *   membership: array,
	 *   recent_traces: array,
	 *   active_alerts: array,
	 *   alert_history: array
	 * }
	 */
	public function get_dashboard_data(): array {
		$latest = $this->snapshots->get_latest();
		$recent = $this->snapshots->get_recent( 12 ); // ~1 hour

		$metrics = $latest['metrics'] ?? array();

		// --- Throughput ---
		$throughput = array(
			'generate_requests'   => (int) ( $metrics['generate_requests']   ?? 0 ),
			'variants_created'    => (int) ( $metrics['variants_created']    ?? 0 ),
			'variant_edits'       => (int) ( $metrics['variant_edits']       ?? 0 ),
			'schedule_created'    => (int) ( $metrics['schedule_created']    ?? 0 ),
			'schedule_dispatched' => (int) ( $metrics['schedule_dispatched'] ?? 0 ),
		);

		// --- Latency ---
		$avg_latency_ms = (float) ( $metrics['avg_generate_latency_ms'] ?? 0.0 );

		// P95 estimate: rough heuristic (1.65× average for normally distributed latency).
		$p95_latency_ms = $avg_latency_ms > 0 ? round( $avg_latency_ms * 1.65, 1 ) : 0.0;

		$latency = array(
			'avg_ms' => $avg_latency_ms,
			'p95_ms' => $p95_latency_ms,
		);

		// --- Compliance outcomes ---
		$ok   = (int) ( $metrics['compliance_ok']   ?? 0 );
		$warn = (int) ( $metrics['compliance_warn']  ?? 0 );
		$fail = (int) ( $metrics['compliance_fail']  ?? 0 );
		$total_compliance = max( 1, $ok + $warn + $fail );

		$compliance = array(
			'ok'       => $ok,
			'warn'     => $warn,
			'fail'     => $fail,
			'ok_pct'   => round( $ok   / $total_compliance * 100, 1 ),
			'warn_pct' => round( $warn / $total_compliance * 100, 1 ),
			'fail_pct' => round( $fail / $total_compliance * 100, 1 ),
		);

		// --- Scheduling / queue ---
		$created    = (int) ( $metrics['schedule_created']    ?? 0 );
		$dispatched = (int) ( $metrics['schedule_dispatched'] ?? 0 );

		$scheduling = array(
			'created'    => $created,
			'dispatched' => $dispatched,
			'backlog'    => max( 0, $created - $dispatched ),
		);

		// --- Membership / attribution ---
		$membership = array(
			'signups'        => (int) ( $metrics['membership_signups']     ?? 0 ),
			'attributions'   => (int) ( $metrics['promotion_attributions'] ?? 0 ),
		);

		// --- Recent audit traces ---
		$recent_traces = $this->audit->get_recent_telemetry_events( 10 );

		// --- OBS-04: Alerts ---
		$active_alerts = $this->alert_evaluator ? $this->alert_evaluator->get_active_alerts()    : array();
		$alert_history = $this->alert_evaluator ? $this->alert_evaluator->get_alert_history( 10 ) : array();

		return array(
			'latest'         => $latest,
			'recent'         => $recent,
			'throughput'     => $throughput,
			'latency'        => $latency,
			'compliance'     => $compliance,
			'scheduling'     => $scheduling,
			'membership'     => $membership,
			'recent_traces'  => $recent_traces,
			'active_alerts'  => $active_alerts,
			'alert_history'  => $alert_history,
		);
	}

	// -------------------------------------------------------------------------
	// HTML rendering
	// -------------------------------------------------------------------------

	private function render_dashboard( array $data ): void {
		$latest        = $data['latest'];
		$t             = $data['throughput'];
		$l             = $data['latency'];
		$c             = $data['compliance'];
		$s             = $data['scheduling'];
		$m             = $data['membership'];
		$traces        = $data['recent_traces'];
		$active_alerts = $data['active_alerts']  ?? array();
		$alert_history = $data['alert_history']  ?? array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Observability Dashboard', 'kh-smma' ); ?></h1>

			<?php if ( ! $latest ) : ?>
				<div class="notice notice-info"><p><?php esc_html_e( 'No analytics snapshots yet. Snapshots are generated every 5 minutes once telemetry events are flowing.', 'kh-smma' ); ?></p></div>
			<?php else : ?>
				<p style="color:#666;">
					<?php
					printf(
						esc_html__( 'Data from snapshot at %s (window start: %s)', 'kh-smma' ),
						esc_html( $latest['created_at'] ?? '—' ),
						esc_html( $latest['window_start'] ?? '—' )
					);
					?>
				</p>
			<?php endif; ?>

			<!-- System Activity -->
			<h2><?php esc_html_e( 'System Activity', 'kh-smma' ); ?></h2>
			<table class="widefat striped" style="width:auto;margin-bottom:16px;">
				<thead>
					<tr><th><?php esc_html_e( 'Metric', 'kh-smma' ); ?></th><th><?php esc_html_e( 'Value (last window)', 'kh-smma' ); ?></th></tr>
				</thead>
				<tbody>
					<tr><td><?php esc_html_e( 'Generate requests', 'kh-smma' ); ?></td><td><strong><?php echo esc_html( $t['generate_requests'] ); ?></strong></td></tr>
					<tr><td><?php esc_html_e( 'Variants created', 'kh-smma' ); ?></td><td><strong><?php echo esc_html( $t['variants_created'] ); ?></strong></td></tr>
					<tr><td><?php esc_html_e( 'Variant edits', 'kh-smma' ); ?></td><td><strong><?php echo esc_html( $t['variant_edits'] ); ?></strong></td></tr>
					<tr><td><?php esc_html_e( 'Avg generate latency', 'kh-smma' ); ?></td><td><strong><?php echo esc_html( $l['avg_ms'] ); ?> ms</strong></td></tr>
					<tr><td><?php esc_html_e( 'P95 latency (estimate)', 'kh-smma' ); ?></td><td><strong><?php echo esc_html( $l['p95_ms'] ); ?> ms</strong></td></tr>
				</tbody>
			</table>

			<!-- Content Safety -->
			<h2><?php esc_html_e( 'Content Safety — Compliance Outcomes', 'kh-smma' ); ?></h2>
			<table class="widefat striped" style="width:auto;margin-bottom:16px;">
				<thead>
					<tr><th><?php esc_html_e( 'Outcome', 'kh-smma' ); ?></th><th><?php esc_html_e( 'Count', 'kh-smma' ); ?></th><th><?php esc_html_e( 'Rate', 'kh-smma' ); ?></th></tr>
				</thead>
				<tbody>
					<tr><td style="color:#0a7c42;">&#10003; OK</td><td><?php echo esc_html( $c['ok'] ); ?></td><td><?php echo esc_html( $c['ok_pct'] ); ?>%</td></tr>
					<tr><td style="color:#d69c17;">&#9888; WARN</td><td><?php echo esc_html( $c['warn'] ); ?></td><td><?php echo esc_html( $c['warn_pct'] ); ?>%</td></tr>
					<tr><td style="color:#c0392b;">&#10007; FAIL</td><td><?php echo esc_html( $c['fail'] ); ?></td><td><?php echo esc_html( $c['fail_pct'] ); ?>%</td></tr>
				</tbody>
			</table>

			<!-- Scheduling -->
			<h2><?php esc_html_e( 'Scheduling', 'kh-smma' ); ?></h2>
			<table class="widefat striped" style="width:auto;margin-bottom:16px;">
				<thead>
					<tr><th><?php esc_html_e( 'Metric', 'kh-smma' ); ?></th><th><?php esc_html_e( 'Value', 'kh-smma' ); ?></th></tr>
				</thead>
				<tbody>
					<tr><td><?php esc_html_e( 'Schedules created', 'kh-smma' ); ?></td><td><strong><?php echo esc_html( $s['created'] ); ?></strong></td></tr>
					<tr><td><?php esc_html_e( 'Schedules dispatched', 'kh-smma' ); ?></td><td><strong><?php echo esc_html( $s['dispatched'] ); ?></strong></td></tr>
					<tr><td><?php esc_html_e( 'Queue backlog estimate', 'kh-smma' ); ?></td><td><strong><?php echo esc_html( $s['backlog'] ); ?></strong></td></tr>
				</tbody>
			</table>

			<!-- Business Metrics -->
			<h2><?php esc_html_e( 'Business Metrics', 'kh-smma' ); ?></h2>
			<table class="widefat striped" style="width:auto;margin-bottom:16px;">
				<thead>
					<tr><th><?php esc_html_e( 'Metric', 'kh-smma' ); ?></th><th><?php esc_html_e( 'Value', 'kh-smma' ); ?></th></tr>
				</thead>
				<tbody>
					<tr><td><?php esc_html_e( 'Membership signups', 'kh-smma' ); ?></td><td><strong><?php echo esc_html( $m['signups'] ); ?></strong></td></tr>
					<tr><td><?php esc_html_e( 'Promotion attributions', 'kh-smma' ); ?></td><td><strong><?php echo esc_html( $m['attributions'] ); ?></strong></td></tr>
				</tbody>
			</table>

			<!-- OBS-04: System Alerts -->
			<h2><?php esc_html_e( 'System Alerts', 'kh-smma' ); ?></h2>
			<?php if ( empty( $active_alerts ) ) : ?>
				<div class="notice notice-success inline" style="margin-bottom:16px;"><p><?php esc_html_e( 'No active alerts. All systems operating normally.', 'kh-smma' ); ?></p></div>
			<?php else : ?>
				<?php foreach ( $active_alerts as $alert_type => $alert ) :
					$severity  = (string) ( $alert['severity'] ?? 'warning' );
					$css_class = 'critical' === $severity ? 'notice-error' : 'notice-warning';
					$icon      = 'critical' === $severity ? '&#10007;' : '&#9888;';
					$label     = esc_html( str_replace( '_', ' ', $alert_type ) );
					?>
					<div class="notice <?php echo esc_attr( $css_class ); ?> inline" style="margin-bottom:8px;">
						<p>
							<strong><?php echo $icon; ?> <?php echo esc_html( strtoupper( $severity ) ); ?>:</strong>
							<?php echo esc_html( $label ); ?>
							<?php if ( ! empty( $alert['snapshot_time'] ) ) : ?>
								&nbsp;<span style="color:#666;">— <?php esc_html_e( 'Last triggered:', 'kh-smma' ); ?> <?php echo esc_html( $alert['snapshot_time'] ); ?></span>
							<?php endif; ?>
						</p>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<!-- Alert History -->
			<?php if ( ! empty( $alert_history ) ) : ?>
				<h3><?php esc_html_e( 'Alert History (last 10)', 'kh-smma' ); ?></h3>
				<table class="widefat striped" style="margin-bottom:16px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Timestamp', 'kh-smma' ); ?></th>
							<th><?php esc_html_e( 'Alert Type', 'kh-smma' ); ?></th>
							<th><?php esc_html_e( 'Severity', 'kh-smma' ); ?></th>
							<th><?php esc_html_e( 'Metrics Context', 'kh-smma' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $alert_history as $row ) :
							$d        = is_array( $row->decoded_details ) ? $row->decoded_details : array();
							$payload  = is_array( $d['payload'] ?? null ) ? $d['payload'] : array();
							$a_type   = sanitize_text_field( (string) ( $payload['alert_type'] ?? '—' ) );
							$a_sev    = sanitize_text_field( (string) ( $payload['severity']   ?? '—' ) );
							$a_ctx    = is_array( $payload['metrics_context'] ?? null )
								? implode( ', ', array_map(
									fn( $k, $v ) => esc_html( $k ) . '=' . esc_html( (string) $v ),
									array_keys( $payload['metrics_context'] ),
									$payload['metrics_context']
								) )
								: '—';
							?>
							<tr>
								<td><?php echo esc_html( $row->created_at ); ?></td>
								<td><code><?php echo esc_html( $a_type ); ?></code></td>
								<td><?php echo esc_html( strtoupper( $a_sev ) ); ?></td>
								<td><small><?php echo esc_html( $a_ctx ); ?></small></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<!-- Diagnostics: Recent Audit Traces -->
			<h2><?php esc_html_e( 'Diagnostics — Recent Audit Traces', 'kh-smma' ); ?></h2>
			<table class="widefat" style="margin-bottom:16px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Timestamp', 'kh-smma' ); ?></th>
						<th><?php esc_html_e( 'Trace ID', 'kh-smma' ); ?></th>
						<th><?php esc_html_e( 'Event', 'kh-smma' ); ?></th>
						<th><?php esc_html_e( 'Variant / Schedule', 'kh-smma' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $traces ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No telemetry events recorded yet.', 'kh-smma' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $traces as $row ) :
							$d          = is_array( $row->decoded_details ) ? $row->decoded_details : array();
							$trace_id   = sanitize_text_field( (string) ( $d['trace_id']   ?? '' ) );
							$event_name = sanitize_text_field( (string) ( $d['event_name'] ?? '—' ) );
							$payload    = is_array( $d['payload'] ?? null ) ? $d['payload'] : array();
							$variant_id = sanitize_text_field( (string) ( $payload['variant_id']  ?? '' ) );
							$schedule_id = sanitize_text_field( (string) ( $payload['schedule_id'] ?? '' ) );
							$context    = array_filter( array( $variant_id, $schedule_id ) );
							$trace_url  = add_query_arg( array(
								'page'     => self::PAGE_SLUG,
								'trace_id' => $trace_id,
							), admin_url( 'admin.php' ) );
							?>
							<tr>
								<td><?php echo esc_html( $row->created_at ); ?></td>
								<td>
									<?php if ( $trace_id ) : ?>
										<a href="<?php echo esc_url( $trace_url ); ?>"><code><?php echo esc_html( substr( $trace_id, 0, 13 ) . '…' ); ?></code></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html( $event_name ); ?></code></td>
								<td><?php echo esc_html( implode( ' / ', $context ) ?: '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
