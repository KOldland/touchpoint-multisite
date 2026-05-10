<?php

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Migration: Add buyer-directory columns to connect_providers and create connect_rfqs table.
 *
 * New provider columns:
 *   hq_location            – free-text city/region
 *   pilot_scheme_available – boolean flag
 *   free_trial_available   – boolean flag
 *   trustpilot_rating      – decimal 0.0–5.0
 *   client_count_band      – enum-style string (1-50 | 50-250 | 250-1000 | 1000+)
 *   integrations           – JSON array of integration slugs
 *
 * New table: connect_rfqs
 */
class AddConnectBuyerDirectoryColumns {

	const RFQS_TABLE = 'connect_rfqs';

	public static function rfqs_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::RFQS_TABLE;
	}

	public static function up(): void {
		self::add_provider_columns();
		self::create_rfqs_table();
	}

	// ─── Provider columns ──────────────────────────────────────────────────────

	private static function add_provider_columns(): void {
		global $wpdb;

		$table = ConnectProvidersMigration::table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$columns = [
			'hq_location'            => "ALTER TABLE `{$table}` ADD COLUMN `hq_location` VARCHAR(100) NULL AFTER `website_url`",
			'pilot_scheme_available' => "ALTER TABLE `{$table}` ADD COLUMN `pilot_scheme_available` TINYINT(1) NOT NULL DEFAULT 0",
			'free_trial_available'   => "ALTER TABLE `{$table}` ADD COLUMN `free_trial_available` TINYINT(1) NOT NULL DEFAULT 0",
			'trustpilot_rating'      => "ALTER TABLE `{$table}` ADD COLUMN `trustpilot_rating` DECIMAL(3,1) NULL",
			'client_count_band'      => "ALTER TABLE `{$table}` ADD COLUMN `client_count_band` VARCHAR(20) NULL",
			'integrations'           => "ALTER TABLE `{$table}` ADD COLUMN `integrations` LONGTEXT NULL",
		];

		foreach ( $columns as $column => $sql ) {
			if ( ! self::column_exists( $table, $column ) ) {
				$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
	}

	// ─── connect_rfqs table ────────────────────────────────────────────────────

	private static function create_rfqs_table(): void {
		global $wpdb;

		$table = self::rfqs_table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT(20) UNSIGNED NOT NULL,
  expertise LONGTEXT NULL,
  industry LONGTEXT NULL,
  budget_min INT(11) NULL,
  budget_max INT(11) NULL,
  company_size INT(11) NULL,
  deployment_needed VARCHAR(100) NULL,
  pilot_required TINYINT(1) NOT NULL DEFAULT 0,
  criteria_priority_order LONGTEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY user_status (user_id, status),
  KEY created_at (created_at)
) {$charset_collate};";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( $sql );
	}

	// ─── Helpers ───────────────────────────────────────────────────────────────

	private static function column_exists( string $table, string $column ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				DB_NAME,
				$table,
				$column
			)
		);
	}
}
