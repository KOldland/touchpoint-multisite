<?php
/**
 * Editorial Credits Database Migration
 *
 * Adds editorial credits columns to wp_khm_user_credits table,
 * separating Editorial credits (Quote Club) from Subscriber credits (downloads).
 *
 * @package KHM\Migrations
 */

namespace KHM\Migrations;

class AddEditorialCredits {

	/**
	 * Add editorial credit columns to wp_khm_user_credits
	 */
	public static function add_columns(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'khm_user_credits';

		// Check if editorial_allocated_credits column already exists.
		$column_check = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = %s",
				$table_name,
				'editorial_allocated_credits'
			)
		);

		// If column exists, we've already run this migration.
		if ( ! empty( $column_check ) ) {
			return;
		}

		// Add editorial credit columns.
		$wpdb->query(
			"ALTER TABLE {$table_name}
			ADD COLUMN editorial_allocated_credits int NOT NULL DEFAULT 0 AFTER bonus_credits,
			ADD COLUMN editorial_bonus_credits int NOT NULL DEFAULT 0 AFTER editorial_allocated_credits,
			ADD COLUMN editorial_allocation_month varchar(7) DEFAULT NULL AFTER editorial_bonus_credits,
			ADD COLUMN press_release_credits tinyint unsigned NOT NULL DEFAULT 1 AFTER editorial_allocation_month,
			ADD COLUMN press_release_credits_used tinyint unsigned NOT NULL DEFAULT 0 AFTER press_release_credits"
		);

		error_log( '[KHM Quote Club] Editorial credits columns added to wp_khm_user_credits' );
	}

	/**
	 * Remove editorial credit columns (for rollback)
	 */
	public static function remove_columns(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'khm_user_credits';

		$wpdb->query(
			"ALTER TABLE {$table_name}
			DROP COLUMN IF EXISTS editorial_allocated_credits,
			DROP COLUMN IF EXISTS editorial_bonus_credits,
			DROP COLUMN IF EXISTS editorial_allocation_month,
			DROP COLUMN IF EXISTS press_release_credits,
			DROP COLUMN IF EXISTS press_release_credits_used"
		);

		error_log( '[KHM Quote Club] Editorial credits columns removed from wp_khm_user_credits' );
	}
}
