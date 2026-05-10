<?php

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Migration: create connect_saved_searches table.
 *
 * Backs the buyer hub "Save Search" feature: passive bookmark of a wizard's
 * criteria payload, no broadcast to providers. Distinct from connect_rfqs
 * (which is a real RFQ). Idempotent on re-run.
 */
class CreateConnectSavedSearchesTable {

	const TABLE = 'connect_saved_searches';

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function up(): void {
		global $wpdb;

		$table = self::table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT(20) UNSIGNED NOT NULL,
  label VARCHAR(255) NOT NULL DEFAULT '',
  criteria_json LONGTEXT NOT NULL,
  last_matched_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY user_created (user_id, created_at)
) {$charset_collate};";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( $sql );
	}
}
