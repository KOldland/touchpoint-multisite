<?php
namespace KH_SMMA\Admin;

use KH_SMMA\Security\CapabilityManager;
use KH_SMMA\Telemetry\TelemetryTraceService;
use KH_SMMA\Telemetry\TelemetryPayloadSanitizer;

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
use function wp_nonce_field;
use function wp_verify_nonce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OBS-08: Telemetry Debug admin page.
 *
 * Registered as a submenu under the SMMA dashboard.
 * Capability required: manage_observability (administrators only).
 * Page slug: kh-telemetry-debug
 *
 * Features:
 *  - Trace lookup form (by trace_id, schedule_id, or variant_id)
 *  - Chronological event timeline with key diagnostic fields
 *  - Privacy safeguards: payloads pass through TelemetryPayloadSanitizer
 */
class TelemetryDebugPage {

	const PAGE_SLUG  = 'kh-telemetry-debug';
	const NONCE_KEY  = 'kh_telemetry_debug_lookup';

	/** @var TelemetryTraceService */
	private $trace_service;

	public function __construct( TelemetryTraceService $trace_service ) {
		$this->trace_service = $trace_service;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
			'kh-smma-dashboard',
			esc_html__( 'Telemetry Debug', 'kh-smma' ),
			esc_html__( 'Telemetry Debug', 'kh-smma' ),
			CapabilityManager::CAP_MANAGE_OBSERVABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		if ( ! CapabilityManager::can_manage_observability() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
		}

		$lookup_type  = sanitize_text_field( $_GET['lookup_type'] ?? 'trace_id' );
		$lookup_value = sanitize_text_field( $_GET['lookup_value'] ?? '' );
		$nonce_valid  = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE_KEY );

		$timeline = array();
		$error    = '';

		if ( '' !== $lookup_value && $nonce_valid ) {
			switch ( $lookup_type ) {
				case 'schedule_id':
					$timeline = $this->trace_service->find_by_schedule_id( $lookup_value );
					break;
				case 'variant_id':
					$timeline = $this->trace_service->find_by_variant_id( $lookup_value );
					break;
				default: // trace_id
					$timeline = $this->trace_service->get_trace_timeline( $lookup_value );
					break;
			}

			if ( '' !== $lookup_value && empty( $timeline ) ) {
				$error = esc_html__( 'No events found for the given lookup value.', 'kh-smma' );
			}
		}

		$this->render_html( $lookup_type, $lookup_value, $timeline, $error );
	}

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	private function render_html( string $lookup_type, string $lookup_value, array $timeline, string $error ): void {
		$base_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Telemetry Debug', 'kh-smma' ); ?></h1>
			<p style="color:#666;"><?php esc_html_e( 'Look up a specific trace, schedule, or variant to view its full event timeline. All displayed payloads are PII-sanitized.', 'kh-smma' ); ?></p>

			<!-- Lookup form -->
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<?php wp_nonce_field( self::NONCE_KEY ); ?>
				<table class="form-table" style="width:auto;">
					<tr>
						<th scope="row"><label for="lookup_type"><?php esc_html_e( 'Lookup by', 'kh-smma' ); ?></label></th>
						<td>
							<select id="lookup_type" name="lookup_type">
								<option value="trace_id" <?php selected( $lookup_type, 'trace_id' ); ?>><?php esc_html_e( 'Trace ID', 'kh-smma' ); ?></option>
								<option value="schedule_id" <?php selected( $lookup_type, 'schedule_id' ); ?>><?php esc_html_e( 'Schedule ID', 'kh-smma' ); ?></option>
								<option value="variant_id" <?php selected( $lookup_type, 'variant_id' ); ?>><?php esc_html_e( 'Variant ID', 'kh-smma' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lookup_value"><?php esc_html_e( 'Value', 'kh-smma' ); ?></label></th>
						<td>
							<input type="text"
								id="lookup_value"
								name="lookup_value"
								value="<?php echo esc_attr( $lookup_value ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. trace-abc-123', 'kh-smma' ); ?>">
						</td>
					</tr>
				</table>
				<?php submit_button( esc_html__( 'Look Up', 'kh-smma' ), 'secondary', 'submit', false ); ?>
			</form>

			<?php if ( '' !== $error ) : ?>
				<div class="notice notice-warning inline" style="margin-top:16px;"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! empty( $timeline ) ) : ?>
				<hr style="margin:24px 0 16px;">
				<h2>
					<?php
					printf(
						esc_html__( 'Event Timeline — %d event(s) for %s: %s', 'kh-smma' ),
						count( $timeline ),
						esc_html( $lookup_type ),
						'<code>' . esc_html( $lookup_value ) . '</code>'
					);
					?>
				</h2>
				<table class="widefat striped" style="margin-bottom:16px;">
					<thead>
						<tr>
							<th style="width:160px;"><?php esc_html_e( 'Timestamp', 'kh-smma' ); ?></th>
							<th><?php esc_html_e( 'Event', 'kh-smma' ); ?></th>
							<th><?php esc_html_e( 'Trace ID', 'kh-smma' ); ?></th>
							<th><?php esc_html_e( 'Key Fields', 'kh-smma' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $timeline as $i => $event ) :
							$key_fields = $this->trace_service->extract_key_fields( $event );
							$fields_str = '';
							foreach ( $key_fields as $k => $v ) {
								if ( is_bool( $v ) ) {
									$v = $v ? 'true' : 'false';
								} elseif ( is_array( $v ) ) {
									$v = implode( ', ', $v );
								}
								$fields_str .= esc_html( $k ) . '=' . esc_html( (string) $v ) . '  ';
							}
							?>
							<tr>
								<td>
									<code style="font-size:11px;"><?php echo esc_html( $event['created_at'] ); ?></code>
								</td>
								<td><strong><code><?php echo esc_html( $event['event_name'] ); ?></code></strong></td>
								<td><code style="font-size:11px;"><?php echo esc_html( substr( $event['trace_id'], 0, 13 ) ); ?>…</code></td>
								<td><small style="color:#555;"><?php echo esc_html( rtrim( $fields_str ) ?: '—' ); ?></small></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<!-- Privacy notice -->
				<p style="color:#888;font-size:12px;">
					&#128274; <?php esc_html_e( 'Payloads are PII-sanitized. Personal data fields are replaced with [REDACTED] before display.', 'kh-smma' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
