<?php
namespace KH_SMMA\Admin;

use KH_SMMA\Services\AuditLogger;

use function admin_url;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OBS-03: Telemetry trace inspector.
 *
 * Renders a drill-down view of all telemetry events for a single trace_id.
 * Called by ObservabilityDashboardPage when ?trace_id= is present.
 *
 * This class is read-only and never writes to any table.
 */
class TelemetryTracePage {

	/** @var AuditLogger */
	private $audit;

	public function __construct( AuditLogger $audit ) {
		$this->audit = $audit;
	}

	/**
	 * Render the trace view for a given trace_id.
	 * Events are displayed in ascending order (workflow sequence).
	 *
	 * @param string $trace_id UUID v4.
	 */
	public function render( string $trace_id ): void {
		$events   = $this->audit->get_events_by_trace( $trace_id );
		$back_url = add_query_arg( array( 'page' => ObservabilityDashboardPage::PAGE_SLUG ), admin_url( 'admin.php' ) );
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Telemetry Trace', 'kh-smma' ); ?>
				<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action"><?php esc_html_e( '&larr; Back to Dashboard', 'kh-smma' ); ?></a>
			</h1>

			<p>
				<strong><?php esc_html_e( 'Trace ID:', 'kh-smma' ); ?></strong>
				<code><?php echo esc_html( $trace_id ); ?></code>
			</p>

			<?php if ( empty( $events ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							esc_html__( 'No telemetry events found for trace ID %s.', 'kh-smma' ),
							'<code>' . esc_html( $trace_id ) . '</code>'
						);
						?>
					</p>
				</div>
			<?php else : ?>
				<p style="color:#666;">
					<?php printf( esc_html__( '%d event(s) found.', 'kh-smma' ), count( $events ) ); ?>
				</p>

				<table class="widefat" style="margin-bottom:16px;">
					<thead>
						<tr>
							<th>#</th>
							<th><?php esc_html_e( 'Timestamp', 'kh-smma' ); ?></th>
							<th><?php esc_html_e( 'Event', 'kh-smma' ); ?></th>
							<th><?php esc_html_e( 'Service', 'kh-smma' ); ?></th>
							<th><?php esc_html_e( 'Key Fields', 'kh-smma' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $events as $i => $row ) :
							$d          = is_array( $row->decoded_details ) ? $row->decoded_details : array();
							$event_name = (string) ( $d['event_name'] ?? '—' );
							$payload    = is_array( $d['payload'] ?? null ) ? $d['payload'] : array();
							$service    = (string) ( $payload['service'] ?? '—' );
							$key_fields = $this->extract_key_fields( $event_name, $payload );
							?>
							<tr>
								<td><?php echo esc_html( $i + 1 ); ?></td>
								<td><code><?php echo esc_html( $row->created_at ); ?></code></td>
								<td><strong><code><?php echo esc_html( $event_name ); ?></code></strong></td>
								<td><?php echo esc_html( $service ); ?></td>
								<td><?php echo esc_html( $key_fields ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Build a compact human-readable summary of the most relevant payload fields
	 * for a given event type.  Excludes PII — only IDs, outcomes, counts.
	 */
	public function extract_key_fields( string $event_name, array $payload ): string {
		$parts = array();

		switch ( $event_name ) {
			case 'generate.request':
				if ( ! empty( $payload['session_id'] ) ) {
					$parts[] = 'session=' . $payload['session_id'];
				}
				if ( isset( $payload['variant_count_requested'] ) ) {
					$parts[] = 'requested=' . (int) $payload['variant_count_requested'];
				}
				break;

			case 'generate.response':
				if ( isset( $payload['variant_count_generated'] ) ) {
					$parts[] = 'generated=' . (int) $payload['variant_count_generated'];
				}
				if ( isset( $payload['latency_ms'] ) ) {
					$parts[] = 'latency=' . (int) $payload['latency_ms'] . 'ms';
				}
				break;

			case 'compliance.check':
				if ( ! empty( $payload['variant_id'] ) ) {
					$parts[] = 'variant=' . $payload['variant_id'];
				}
				if ( ! empty( $payload['outcome'] ) ) {
					$parts[] = 'outcome=' . $payload['outcome'];
				}
				break;

			case 'variant.edit':
				if ( ! empty( $payload['variant_id'] ) ) {
					$parts[] = 'variant=' . $payload['variant_id'];
				}
				if ( ! empty( $payload['revision_id'] ) ) {
					$parts[] = 'revision=' . $payload['revision_id'];
				}
				break;

			case 'schedule.create':
			case 'schedule.dispatch':
				if ( ! empty( $payload['schedule_id'] ) ) {
					$parts[] = 'schedule=' . $payload['schedule_id'];
				}
				if ( ! empty( $payload['result'] ) ) {
					$parts[] = 'result=' . $payload['result'];
				}
				break;

			case 'membership.signup':
				if ( isset( $payload['tier'] ) ) {
					$parts[] = 'tier=' . $payload['tier'];
				}
				if ( isset( $payload['payment_status'] ) ) {
					$parts[] = 'payment=' . $payload['payment_status'];
				}
				break;

			case 'promotion_attribution':
				if ( ! empty( $payload['utm_source'] ) ) {
					$parts[] = 'utm_source=' . $payload['utm_source'];
				}
				if ( isset( $payload['confidence_score'] ) ) {
					$parts[] = 'confidence=' . $payload['confidence_score'];
				}
				break;
		}

		return implode( ', ', $parts ) ?: '—';
	}
}
