<?php
namespace KH_SMMA\Telemetry;

use wpdb;

use function add_action;
use function wp_schedule_event;
use function wp_next_scheduled;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OBS-07: Telemetry credential management and retention cleanup.
 *
 * Responsibilities:
 *  1. Load telemetry sink credentials from environment variables.
 *     Credentials are never stored in the DB or source code.
 *  2. Run the kh_smma_telemetry_cleanup cron job that enforces retention
 *     policies by deleting stale records from telemetry tables.
 *
 * Environment variables consumed:
 *   SMMA_TELEMETRY_API_KEY  — API key for external telemetry sink.
 *   SMMA_TELEMETRY_ENDPOINT — Endpoint URL for telemetry forwarding.
 *
 * Retention policy (days):
 *   Telemetry buffer events:  30
 *   Analytics snapshots:      90
 *   Audit log events:        365
 */
class TelemetryConfigService {

	const CRON_HOOK = 'kh_smma_telemetry_cleanup';

	const RETENTION_TELEMETRY_DAYS  = 30;
	const RETENTION_SNAPSHOTS_DAYS  = 90;
	const RETENTION_AUDIT_DAYS      = 365;

	const ENV_API_KEY  = 'SMMA_TELEMETRY_API_KEY';
	const ENV_ENDPOINT = 'SMMA_TELEMETRY_ENDPOINT';

	/** @var wpdb */
	private $db;

	/** @var EventEmitter|null  For emitting telemetry.config.updated events. */
	private $emitter;

	/**
	 * @param wpdb              $db      WordPress database.
	 * @param EventEmitter|null $emitter Optional emitter for security events.
	 */
	public function __construct( wpdb $db, ?EventEmitter $emitter = null ) {
		$this->db      = $db;
		$this->emitter = $emitter;
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Register the cleanup cron callback.
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'run_cleanup' ) );
	}

	// -------------------------------------------------------------------------
	// Credential access
	// -------------------------------------------------------------------------

	/**
	 * Return the telemetry sink API key from the environment.
	 * Returns empty string if not configured.
	 */
	public function get_api_key(): string {
		return $this->read_env( self::ENV_API_KEY );
	}

	/**
	 * Return the telemetry sink endpoint URL from the environment.
	 * Returns empty string if not configured.
	 */
	public function get_endpoint(): string {
		return $this->read_env( self::ENV_ENDPOINT );
	}

	/**
	 * True when both API key and endpoint are configured.
	 */
	public function is_configured(): bool {
		return $this->get_api_key() !== '' && $this->get_endpoint() !== '';
	}

	// -------------------------------------------------------------------------
	// Security event emission
	// -------------------------------------------------------------------------

	/**
	 * Emit a telemetry.config.updated security event.
	 *
	 * Call this whenever a credential or telemetry configuration is changed
	 * (e.g., from an admin settings page or CLI command).
	 *
	 * @param int    $user_id     ID of the user who made the change.
	 * @param string $change_type Human-readable description (e.g. "api_key_rotated").
	 */
	public function emit_config_update( int $user_id, string $change_type ): void {
		if ( $this->emitter === null ) {
			return;
		}
		try {
			$this->emitter->emit( 'telemetry.config.updated', array(
				'user_id'     => $user_id,
				'change_type' => $change_type,
				'service'     => 'smma',
			) );
		} catch ( \Throwable $e ) {
			// Swallow — config update events are best-effort.
		}
	}

	// -------------------------------------------------------------------------
	// Retention cleanup
	// -------------------------------------------------------------------------

	/**
	 * Delete stale records from telemetry tables according to retention policy.
	 *
	 * Called by the kh_smma_telemetry_cleanup cron hook (daily).
	 *
	 * @return array{telemetry_buffer: int, snapshots: int, audit_log: int}
	 *   Number of rows deleted per table.
	 */
	public function run_cleanup(): array {
		$deleted = array(
			'telemetry_buffer' => 0,
			'snapshots'        => 0,
			'audit_log'        => 0,
		);

		$deleted['telemetry_buffer'] = $this->delete_older_than(
			$this->db->prefix . 'kh_smma_telemetry_buffer',
			self::RETENTION_TELEMETRY_DAYS
		);

		$deleted['snapshots'] = $this->delete_older_than(
			$this->db->prefix . 'kh_smma_analytics_snapshots',
			self::RETENTION_SNAPSHOTS_DAYS
		);

		$deleted['audit_log'] = $this->delete_older_than(
			$this->db->prefix . 'kh_smma_audit_log',
			self::RETENTION_AUDIT_DAYS
		);

		return $deleted;
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Delete rows older than $days from the given table.
	 * Table must have a `created_at` DATETIME column.
	 *
	 * @param string $table Fully-qualified table name (with prefix).
	 * @param int    $days  Retention window in days.
	 * @return int Number of rows deleted (0 on failure).
	 */
	private function delete_older_than( string $table, int $days ): int {
		try {
			$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
			$result = $this->db->query(
				$this->db->prepare(
					"DELETE FROM {$table} WHERE created_at < %s LIMIT 500",
					$cutoff
				)
			);
			return is_int( $result ) ? $result : 0;
		} catch ( \Throwable $e ) {
			return 0;
		}
	}

	/**
	 * Read an environment variable, trying $_SERVER then getenv() as fallback.
	 *
	 * @param string $name Variable name.
	 * @return string Value, or empty string if not set.
	 */
	private function read_env( string $name ): string {
		if ( isset( $_SERVER[ $name ] ) && is_string( $_SERVER[ $name ] ) ) {
			return $_SERVER[ $name ];
		}
		$val = getenv( $name );
		return is_string( $val ) ? $val : '';
	}
}
