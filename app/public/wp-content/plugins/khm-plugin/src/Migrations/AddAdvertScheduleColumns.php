<?php

namespace KHM\Migrations;

/**
 * Adds start_date and end_date columns to wp_khm_sponsor_adverts.
 * Idempotent — checks for column existence before running ALTER.
 */
class AddAdvertScheduleColumns {

	public static function run(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_adverts';

		// Silently skip if the table doesn't exist yet.
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return;
		}

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! in_array( 'start_date', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `start_date` DATETIME NULL DEFAULT NULL AFTER `weight`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			error_log( '[KHM Adverts] Added start_date column.' );
		}

		if ( ! in_array( 'end_date', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `end_date` DATETIME NULL DEFAULT NULL AFTER `start_date`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			error_log( '[KHM Adverts] Added end_date column.' );
		}
	}
}
