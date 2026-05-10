<?php

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Migration: Rename connect_rfps -> connect_rfqs and migrate request_type values.
 *
 * Idempotent and safe on fresh databases:
 *   - Fresh DB: no legacy table exists, so this is a no-op.
 *   - Legacy DB (table exists as connect_rfps): RENAME to connect_rfqs and
 *     update request_type='rfp_request' rows to 'rfq_request' on
 *     connect_opportunities and connect_intro_threads.
 *
 * Must run BEFORE AddConnectBuyerDirectoryColumns::up() so the table-create
 * step there sees connect_rfqs already in place and skips.
 */
class RenameRfpToRfq {

	public static function up(): void {
		self::rename_rfps_table();
		self::convert_request_type_values();
	}

	private static function rename_rfps_table(): void {
		global $wpdb;

		$new_table    = $wpdb->prefix . 'connect_rfqs';
		$legacy_table = $wpdb->prefix . 'connect_rfps';

		$new_exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) ) === $new_table;
		$legacy_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table ) ) === $legacy_table;

		if ( $legacy_exists && ! $new_exists ) {
			$wpdb->query( "RENAME TABLE `{$legacy_table}` TO `{$new_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return;
		}

		// Defensive: if both exist and new is empty, swap. If new has data, leave both
		// for manual reconciliation rather than risking data loss.
		if ( $legacy_exists && $new_exists ) {
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$new_table}`" ); // phpcs:ignore
			if ( 0 === $count ) {
				$wpdb->query( "DROP TABLE `{$new_table}`" ); // phpcs:ignore
				$wpdb->query( "RENAME TABLE `{$legacy_table}` TO `{$new_table}`" ); // phpcs:ignore
			}
		}
	}

	private static function convert_request_type_values(): void {
		global $wpdb;

		$tables = [
			$wpdb->prefix . 'connect_opportunities',
			$wpdb->prefix . 'connect_intro_threads',
		];

		foreach ( $tables as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				continue;
			}
			if ( ! self::column_exists( $table, 'request_type' ) ) {
				continue;
			}
			$wpdb->update(
				$table,
				[ 'request_type' => 'rfq_request' ],
				[ 'request_type' => 'rfp_request' ],
				[ '%s' ],
				[ '%s' ]
			);
		}
	}

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
