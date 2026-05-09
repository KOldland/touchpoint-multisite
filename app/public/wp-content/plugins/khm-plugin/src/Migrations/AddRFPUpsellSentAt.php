<?php
/**
 * Migration: Add RFP Upsell Sent Timestamp to Opportunities
 *
 * Adds rfp_upsell_sent_at to connect_opportunities so the 30-day
 * upsell cron job can mark each opportunity as processed and avoid
 * sending the upsell email more than once per RFP.
 */

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

class AddRFPUpsellSentAt {

	public static function up(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'connect_opportunities';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return false;
		}

		if ( ! self::column_exists( $table, 'rfp_upsell_sent_at' ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `rfp_upsell_sent_at` DATETIME NULL COMMENT 'Stamped when 30-day upsell email is queued'" );
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
}
