<?php
/**
 * Migration: Add Buyer Validation Fields to Opportunities
 *
 * Adds columns to connect_opportunities to support:
 * - Buyer account linkage (buyer_account_id → WordPress user)
 * - Buyer verification state (unverified / verified / rejected)
 * - Buyer validation badge visibility
 * - Active RFP count (enforces 3-RFP cap per buyer)
 * - RFP created_at (drives 30-day upsell trigger)
 */

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

class AddBuyerFieldsToOpportunities {

	public static function up(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'connect_opportunities';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return false;
		}

		$columns = [
			'buyer_account_id'             => "BIGINT(20) UNSIGNED NULL COMMENT 'WordPress user ID of the buyer'",
			'buyer_validation_status'      => "VARCHAR(30) NOT NULL DEFAULT 'unverified' COMMENT 'unverified|verified|rejected'",
			'buyer_validation_badge_visible' => "TINYINT(1) NOT NULL DEFAULT 0",
			'rfp_count_active'             => "INT NOT NULL DEFAULT 0 COMMENT 'Live RFPs for this buyer (max 3)'",
			'rfp_created_at'               => "DATETIME NULL COMMENT 'For 30-day upsell async trigger'",
		];

		foreach ( $columns as $name => $definition ) {
			if ( ! self::column_exists( $table, $name ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$definition}" );
			}
		}

		// Index buyer_account_id for cap checks and validation queries
		if ( ! self::index_exists( $table, 'buyer_account_id' ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD KEY `buyer_account_id` (`buyer_account_id`, `buyer_validation_status`)" );
		}

		// Index for 30-day upsell async job
		if ( ! self::index_exists( $table, 'rfp_created_at' ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD KEY `rfp_created_at` (`rfp_created_at`)" );
		}

		return true;
	}

	public static function down(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'connect_opportunities';

		$wpdb->query( "ALTER TABLE `{$table}` DROP KEY IF EXISTS `buyer_account_id`" );
		$wpdb->query( "ALTER TABLE `{$table}` DROP KEY IF EXISTS `rfp_created_at`" );

		$columns = [
			'buyer_account_id',
			'buyer_validation_status',
			'buyer_validation_badge_visible',
			'rfp_count_active',
			'rfp_created_at',
		];

		foreach ( $columns as $col ) {
			if ( self::column_exists( $table, $col ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `{$col}`" );
			}
		}

		return true;
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

	private static function index_exists( string $table, string $index_name ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s',
				DB_NAME,
				$table,
				$index_name
			)
		);
	}
}
