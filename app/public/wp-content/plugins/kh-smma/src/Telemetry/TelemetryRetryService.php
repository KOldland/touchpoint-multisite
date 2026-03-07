<?php
namespace KH_SMMA\Telemetry;

use wpdb;

use function add_action;
use function do_action;
use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OBS-06: Telemetry retry/backoff and fallback buffer persistence.
 *
 * Wraps a publisher callable with up to MAX_ATTEMPTS retries using
 * exponential backoff delays.  If all attempts are exhausted the event
 * is written to the wp_kh_smma_telemetry_buffer table for replay on the
 * next kh_smma_telemetry_replay cron run.
 *
 * Delays are injectable via constructor to keep unit tests fast.
 *
 * Pipeline position:
 *   EventQueue::flush()
 *       → TelemetryRetryService::publish_with_retry($event, $publisher)
 *           attempt 1  (0 ms delay)
 *           attempt 2  (250 ms delay on failure)
 *           attempt 3  (1 000 ms delay on failure)
 *           all fail → buffer_event() → kh_smma_telemetry_buffer
 *               ↑ replayed by replay_buffered_events() on cron
 */
class TelemetryRetryService {

	const MAX_ATTEMPTS          = 3;
	const BUFFER_TABLE_SUFFIX   = 'kh_smma_telemetry_buffer';
	const REPLAY_CRON_HOOK      = 'kh_smma_telemetry_replay';

	/**
	 * Microsecond delays before each attempt (index = attempt number, 0-based).
	 * attempt 0 → 0 µs (immediate)
	 * attempt 1 → 250 000 µs (250 ms)
	 * attempt 2 → 1 000 000 µs (1 s)
	 */
	const BACKOFF_DELAYS_US = array( 0, 250000, 1000000 );

	/** @var wpdb */
	private $db;

	/** @var string */
	private $table;

	/** @var int[] Microsecond delay per attempt */
	private $backoff_us;

	/** @var callable function(int $microseconds): void */
	private $sleep_fn;

	/**
	 * @param wpdb          $db         WordPress database.
	 * @param int[]|null    $backoff_us Override backoff delays (µs per attempt).
	 *                                  Pass [0, 0, 0] to disable sleeping in tests.
	 * @param callable|null $sleep_fn   Override sleep function for testing.
	 */
	public function __construct( wpdb $db, ?array $backoff_us = null, ?callable $sleep_fn = null ) {
		$this->db         = $db;
		$this->table      = $db->prefix . self::BUFFER_TABLE_SUFFIX;
		$this->backoff_us = $backoff_us ?? self::BACKOFF_DELAYS_US;
		$this->sleep_fn   = $sleep_fn  ?? static function ( int $us ): void {
			if ( $us > 0 ) {
				usleep( $us );
			}
		};
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Register the cron callback for buffered event replay.
	 */
	public function register(): void {
		add_action( self::REPLAY_CRON_HOOK, array( $this, 'replay_buffered_events' ) );
	}

	// -------------------------------------------------------------------------
	// Retry / publish
	// -------------------------------------------------------------------------

	/**
	 * Attempt to publish $event by calling $publisher, retrying with backoff.
	 *
	 * @param array    $event     Full telemetry event envelope.
	 * @param callable $publisher function(array $event): void — may throw on failure.
	 * @return bool True if published on any attempt; false if all attempts failed.
	 */
	public function publish_with_retry( array $event, callable $publisher ): bool {
		$attempts = min( self::MAX_ATTEMPTS, count( $this->backoff_us ) );

		for ( $i = 0; $i < $attempts; $i++ ) {
			$delay = (int) ( $this->backoff_us[ $i ] ?? 0 );
			if ( $delay > 0 ) {
				( $this->sleep_fn )( $delay );
			}

			try {
				$publisher( $event );
				return true;
			} catch ( \Throwable $e ) {
				// Continue to next attempt.
			}
		}

		// All attempts exhausted — persist to buffer for cron replay.
		$this->buffer_event( $event );
		return false;
	}

	// -------------------------------------------------------------------------
	// Buffer persistence
	// -------------------------------------------------------------------------

	/**
	 * Write an event to the telemetry buffer table.
	 *
	 * @param array $event Full event envelope.
	 */
	public function buffer_event( array $event ): void {
		try {
			$this->db->insert(
				$this->table,
				array(
					'trace_id'   => (string) ( $event['trace_id']   ?? '' ),
					'event_name' => (string) ( $event['event_name'] ?? 'unknown' ),
					'payload'    => wp_json_encode( $event ),
					'attempts'   => 0,
					'created_at' => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%s', '%s', '%s', '%d', '%s' )
			);
		} catch ( \Throwable $e ) {
			// Final fallback failed — telemetry is best-effort beyond the audit log.
		}
	}

	// -------------------------------------------------------------------------
	// Cron replay
	// -------------------------------------------------------------------------

	/**
	 * Replay buffered events.
	 *
	 * Called by the kh_smma_telemetry_replay cron hook.  Fetches up to 50
	 * rows that have not yet exhausted MAX_ATTEMPTS, attempts to publish each,
	 * and either deletes successful rows or increments their attempt counter.
	 *
	 * @param callable|null $publisher Override publisher for testing.
	 * @return int Number of events successfully replayed.
	 */
	public function replay_buffered_events( ?callable $publisher = null ): int {
		$publisher = $publisher ?? static function ( array $event ): void {
			do_action( 'kh_telemetry_event', $event );
		};

		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE attempts < %d ORDER BY created_at ASC LIMIT 50",
				self::MAX_ATTEMPTS
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$replayed = 0;
		foreach ( $rows as $row ) {
			$event = json_decode( (string) ( $row['payload'] ?? '{}' ), true ) ?: array();
			$id    = (int) ( $row['id'] ?? 0 );
			try {
				$publisher( $event );
				$this->delete_buffered_row( $id );
				$replayed++;
			} catch ( \Throwable $e ) {
				$this->increment_attempt( $id );
			}
		}

		return $replayed;
	}

	// -------------------------------------------------------------------------
	// Schema install
	// -------------------------------------------------------------------------

	/**
	 * Create the telemetry buffer table if it does not exist.
	 * Called from Plugin::activate().
	 */
	public function install(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset_collate = method_exists( $this->db, 'get_charset_collate' )
			? $this->db->get_charset_collate()
			: '';

		$sql = "CREATE TABLE {$this->table} (
			id         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			trace_id   varchar(64)  NOT NULL DEFAULT '',
			event_name varchar(128) NOT NULL DEFAULT '',
			payload    longtext     NOT NULL,
			attempts   tinyint(3) unsigned NOT NULL DEFAULT 0,
			created_at datetime     NOT NULL,
			PRIMARY KEY (id),
			KEY created_at (created_at)
		) {$charset_collate};";

		\dbDelta( $sql );
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	private function delete_buffered_row( int $id ): void {
		if ( $id <= 0 ) {
			return;
		}
		$this->db->query( $this->db->prepare( "DELETE FROM {$this->table} WHERE id = %d", $id ) );
	}

	private function increment_attempt( int $id ): void {
		if ( $id <= 0 ) {
			return;
		}
		$this->db->query( $this->db->prepare( "UPDATE {$this->table} SET attempts = attempts + 1 WHERE id = %d", $id ) );
	}

	/**
	 * Return the count of events currently in the buffer (for monitoring).
	 */
	public function buffered_count(): int {
		$count = $this->db->get_var( "SELECT COUNT(*) FROM {$this->table}" );
		return (int) $count;
	}
}
