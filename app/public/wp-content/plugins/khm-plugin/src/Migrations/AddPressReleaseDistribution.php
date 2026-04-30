<?php

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Adds distribution_site_ids column to khm_press_releases table.
 *
 * distribution_site_ids is stored as a JSON array of WP blog IDs that the
 * sponsor has requested their press release to be distributed to.
 * An empty array (or NULL) means publish to the current site only.
 */
class AddPressReleaseDistribution {

	public static function add_columns(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'khm_press_releases';

		// Idempotent: skip if the column already exists.
		$cols = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'distribution_site_ids'", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! empty( $cols ) ) {
			return;
		}

		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `distribution_site_ids` TEXT NULL COMMENT 'JSON array of blog IDs for multisite distribution'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		error_log( '[KHM] AddPressReleaseDistribution: distribution_site_ids column added.' );
	}
}
