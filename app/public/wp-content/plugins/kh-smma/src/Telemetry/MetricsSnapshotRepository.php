<?php
namespace KH_SMMA\Telemetry;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists and retrieves analytics metrics snapshots.
 *
 * Each snapshot represents a 5-minute window of aggregated telemetry.
 * Snapshots power dashboards and are the durable record for historical
 * metrics — distinct from the rolling in-memory accumulator held in a
 * WP option by AnalyticsFeedbackService.
 *
 * Schema (wp_kh_smma_analytics_snapshots):
 *   snapshot_id   BIGINT UNSIGNED AUTO_INCREMENT PK
 *   window_start  DATETIME NOT NULL              (start of accumulation window)
 *   created_at    DATETIME NOT NULL              (when the snapshot was flushed)
 *   metrics_json  LONGTEXT NOT NULL              (JSON-encoded metrics array)
 */
class MetricsSnapshotRepository {

	const TABLE_SUFFIX = 'kh_smma_analytics_snapshots';

	/** @var wpdb */
	private $db;

	/** @var string */
	private $table;

	public function __construct( wpdb $db ) {
		$this->db    = $db;
		$this->table = $this->db->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create the snapshots table if it does not exist.
	 */
	public function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->db->get_charset_collate();
		$sql             = "CREATE TABLE {$this->table} (
			snapshot_id  bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			window_start datetime NOT NULL,
			created_at   datetime NOT NULL,
			metrics_json longtext NOT NULL,
			PRIMARY KEY  (snapshot_id),
			KEY window_start (window_start)
		) {$charset_collate};";

		\dbDelta( $sql );
	}

	/**
	 * Persist a metrics snapshot.
	 *
	 * @param array  $metrics     Aggregated metrics (PII-free counts / distributions).
	 * @param int    $window_start Unix timestamp of the accumulation window start.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function write_snapshot( array $metrics, int $window_start ) {
		$now = function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );

		$result = $this->db->insert(
			$this->table,
			array(
				'window_start' => gmdate( 'Y-m-d H:i:s', $window_start ),
				'created_at'   => $now,
				'metrics_json' => wp_json_encode( $metrics ),
			),
			array( '%s', '%s', '%s' )
		);

		return false !== $result ? (int) $this->db->insert_id : false;
	}

	/**
	 * Retrieve the most recently written snapshot.
	 *
	 * @return array|null  Decoded snapshot row, or null if none exists.
	 */
	public function get_latest(): ?array {
		$row = $this->db->get_row(
			"SELECT * FROM {$this->table} ORDER BY snapshot_id DESC LIMIT 1",
			ARRAY_A
		);

		if ( empty( $row ) ) {
			return null;
		}

		$row['metrics'] = json_decode( $row['metrics_json'], true ) ?: array();
		return $row;
	}

	/**
	 * Retrieve the N most recent snapshots, newest first.
	 *
	 * @param int $limit Maximum rows to return (default 10).
	 * @return array
	 */
	public function get_recent( int $limit = 10 ): array {
		$limit = max( 1, min( 100, $limit ) );
		$rows  = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} ORDER BY snapshot_id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$row['metrics'] = json_decode( $row['metrics_json'], true ) ?: array();
		}

		return $rows;
	}
}
