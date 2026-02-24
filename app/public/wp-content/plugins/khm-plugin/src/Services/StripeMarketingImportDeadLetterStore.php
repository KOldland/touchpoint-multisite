<?php

namespace KHM\Services;

class StripeMarketingImportDeadLetterStore {

	private string $tableName;
	private static bool $tableReady = false;

	public function __construct() {
		global $wpdb;
		$this->tableName = $wpdb->prefix . 'khm_stripe_marketing_import_dead_letters';
	}

	public static function createTable(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_stripe_marketing_import_dead_letters';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id varchar(255) NOT NULL,
			level_id bigint(20) unsigned NULL,
			source varchar(50) NOT NULL,
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			error_message text NOT NULL,
			context longtext NULL,
			created_at datetime NOT NULL,
			resolved_at datetime NULL,
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY level_id (level_id),
			KEY source (source),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function insert( array $data ): void {
		global $wpdb;
		$this->ensureTableReady();

		$context = [];
		if ( isset( $data['context'] ) && is_array( $data['context'] ) ) {
			$context = $data['context'];
		}

		$wpdb->insert(
			$this->tableName,
			[
				'product_id'    => (string) ( $data['product_id'] ?? '' ),
				'level_id'      => isset( $data['level_id'] ) ? (int) $data['level_id'] : null,
				'source'        => (string) ( $data['source'] ?? 'webhook' ),
				'attempts'      => (int) ( $data['attempts'] ?? 0 ),
				'error_message' => (string) ( $data['error_message'] ?? 'Unknown failure' ),
				'context'       => ! empty( $context ) ? wp_json_encode( $context ) : null,
				'created_at'    => current_time( 'mysql', true ),
			],
			[
				'%s',
				'%d',
				'%s',
				'%d',
				'%s',
				'%s',
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
				"DELETE FROM {$this->tableName} WHERE created_at < %s AND resolved_at IS NOT NULL",
				$cutoff
			)
		);

		return (int) $deleted;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function getById( int $id ): ?array {
		global $wpdb;
		$this->ensureTableReady();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$row['context'] = isset( $row['context'] ) ? json_decode( (string) $row['context'], true ) : [];
		if ( ! is_array( $row['context'] ) ) {
			$row['context'] = [];
		}

		return $row;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function getUnresolved( int $limit = 100 ): array {
		global $wpdb;
		$this->ensureTableReady();

		$limit = max( 1, min( 500, $limit ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tableName} WHERE resolved_at IS NULL ORDER BY id ASC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		foreach ( $rows as &$row ) {
			$row['context'] = isset( $row['context'] ) ? json_decode( (string) $row['context'], true ) : [];
			if ( ! is_array( $row['context'] ) ) {
				$row['context'] = [];
			}
		}
		unset( $row );

		return $rows;
	}

	public function markResolved( int $id ): bool {
		global $wpdb;
		$this->ensureTableReady();

		$updated = $wpdb->update(
			$this->tableName,
			[ 'resolved_at' => current_time( 'mysql', true ) ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);

		return $updated !== false;
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
