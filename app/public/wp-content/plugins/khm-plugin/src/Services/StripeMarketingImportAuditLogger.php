<?php

namespace KHM\Services;

class StripeMarketingImportAuditLogger {

	private string $tableName;
	private static bool $tableReady = false;

	public function __construct() {
		global $wpdb;
		$this->tableName = $wpdb->prefix . 'khm_stripe_marketing_import_audit';
	}

	public static function createTable(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_stripe_marketing_import_audit';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id varchar(255) NOT NULL,
			level_id bigint(20) unsigned NULL,
			source varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			dry_run tinyint(1) NOT NULL DEFAULT 0,
			lines_count smallint(5) unsigned NOT NULL DEFAULT 0,
			content_hash char(40) NOT NULL DEFAULT '',
			skipped_reason varchar(50) NOT NULL DEFAULT '',
			message text NULL,
			context longtext NULL,
			duration_ms int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY level_id (level_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function log( array $data ): void {
		global $wpdb;
		$this->ensureTableReady();

		$context = [];
		if ( isset( $data['context'] ) && is_array( $data['context'] ) ) {
			$context = $data['context'];
		}

		$wpdb->insert(
			$this->tableName,
			[
				'product_id'     => (string) ( $data['product_id'] ?? '' ),
				'level_id'       => isset( $data['level_id'] ) ? (int) $data['level_id'] : null,
				'source'         => (string) ( $data['source'] ?? 'unknown' ),
				'status'         => (string) ( $data['status'] ?? 'unknown' ),
				'dry_run'        => ! empty( $data['dry_run'] ) ? 1 : 0,
				'lines_count'    => (int) ( $data['lines_count'] ?? 0 ),
				'content_hash'   => (string) ( $data['content_hash'] ?? '' ),
				'skipped_reason' => (string) ( $data['skipped_reason'] ?? '' ),
				'message'        => isset( $data['message'] ) ? (string) $data['message'] : null,
				'context'        => ! empty( $context ) ? wp_json_encode( $context ) : null,
				'duration_ms'    => (int) ( $data['duration_ms'] ?? 0 ),
				'created_at'     => current_time( 'mysql', true ),
			],
			[
				'%s',
				'%d',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
			]
		);
	}

	public function cleanup( int $daysOld = 90 ): int {
		global $wpdb;
		$this->ensureTableReady();
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . max( 1, $daysOld ) . ' days' ) );
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->tableName} WHERE created_at < %s",
				$cutoff
			)
		);

		return (int) $deleted;
	}

	private function ensureTableReady(): void {
		if ( self::$tableReady ) {
			return;
		}

		global $wpdb;
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$this->tableName
			)
		);

		if ( $exists === $this->tableName ) {
			self::$tableReady = true;
			return;
		}

		self::createTable();
		self::$tableReady = true;
	}
}
