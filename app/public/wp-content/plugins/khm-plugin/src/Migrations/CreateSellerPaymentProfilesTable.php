<?php
/**
 * Migration: Create Seller Payment Profiles Table
 *
 * Creates connect_seller_payment_profiles to support:
 * - Stripe Customer pre-registration (one-click payments)
 * - Seller-configurable monthly spend limits
 * - Monthly spend usage tracking
 * - Card fallback flag (Stripe Elements if no pre-registered method)
 */

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

class CreateSellerPaymentProfilesTable {

	const TABLE = 'connect_seller_payment_profiles';

	public static function run(): void {
		global $wpdb;

		$table           = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		// Only create if it doesn't already exist
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			return;
		}

		$sql = "CREATE TABLE `{$table}` (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  seller_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'WordPress user ID of the seller/provider',
  stripe_customer_id VARCHAR(255) NULL COMMENT 'Stripe Customer ID for off-session charges',
  payment_auth_granted_at DATETIME NULL COMMENT 'When seller authorized off-session billing',
  spend_limit_monthly DECIMAL(10,2) NOT NULL DEFAULT 500.00 COMMENT 'Seller-set monthly cap in GBP',
  spend_used_current_month DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Accumulated charges this calendar month',
  spend_reset_at DATETIME NULL COMMENT 'Last time monthly spend was reset',
  card_enabled_fallback TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Allow Stripe Elements if no pre-registered method',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY seller_id (seller_id),
  KEY stripe_customer_id (stripe_customer_id)
) {$charset_collate};";

		self::run_schema( $sql );
	}

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	private static function run_schema( string $sql ): void {
		global $wpdb;

		if ( function_exists( 'dbDelta' ) ) {
			dbDelta( $sql );
			return;
		}

		$upgrade_file = defined( 'ABSPATH' ) ? ABSPATH . 'wp-admin/includes/upgrade.php' : '';
		if ( '' !== $upgrade_file && file_exists( $upgrade_file ) ) {
			require_once $upgrade_file;
			if ( function_exists( 'dbDelta' ) ) {
				dbDelta( $sql );
				return;
			}
		}

		$wpdb->query( $sql );
	}
}
