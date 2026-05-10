<?php
/**
 * Migration: Create RFQ Support Tables
 *
 * Creates three new tables:
 *
 * 1. connect_discount_codes
 *    One-time buyer discount codes generated at handover, claimed post-close.
 *    Code claim is the trigger for the deferred commission debit.
 *
 * 2. connect_pending_commission_invoices
 *    Tracks each commission debit lifecycle: pending → disputed|charged|failed|cancelled.
 *    15-day auto-debit fires from auto_debit_date on intro threads.
 *
 * 3. connect_seller_rejection_cooldown
 *    90-day buyer cap: a seller cannot be presented the same buyer again within 90 days
 *    of rejecting them. Prevents gaming via repeated cold RFQs.
 */

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

class CreateRFPSupportTables {

	const DISCOUNT_CODES_TABLE  = 'connect_discount_codes';
	const INVOICES_TABLE        = 'connect_pending_commission_invoices';
	const COOLDOWN_TABLE        = 'connect_seller_rejection_cooldown';

	public static function run(): void {
		self::create_discount_codes_table();
		self::create_commission_invoices_table();
		self::create_rejection_cooldown_table();
	}

	// ─── Table name helpers ────────────────────────────────────────────────────

	public static function discount_codes_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::DISCOUNT_CODES_TABLE;
	}

	public static function invoices_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::INVOICES_TABLE;
	}

	public static function cooldown_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::COOLDOWN_TABLE;
	}

	// ─── Individual table creators ─────────────────────────────────────────────

	private static function create_discount_codes_table(): void {
		global $wpdb;

		$table           = self::discount_codes_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			return;
		}

		$sql = "CREATE TABLE `{$table}` (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(12) NOT NULL COMMENT 'Alphanumeric code generated at handover',
  thread_id BIGINT(20) UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  claimed_by_buyer_at DATETIME NULL COMMENT 'When buyer submitted the claim form',
  verified_for_commission_at DATETIME NULL COMMENT 'When admin verified the claim pre-debit',
  PRIMARY KEY (id),
  UNIQUE KEY code (code),
  KEY thread_id (thread_id)
) {$charset_collate};";

		self::run_schema( $sql );
	}

	private static function create_commission_invoices_table(): void {
		global $wpdb;

		$table           = self::invoices_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			return;
		}

		$sql = "CREATE TABLE `{$table}` (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id BIGINT(20) UNSIGNED NOT NULL,
  contract_ref VARCHAR(255) NULL COMMENT 'Uploaded contract reference from buyer claim form',
  commission_rate TINYINT UNSIGNED NOT NULL DEFAULT 15 COMMENT '5–25 representing %',
  commission_amount DECIMAL(10,2) NOT NULL COMMENT 'GBP value auto-calculated at claim time',
  auto_debit_date DATETIME NOT NULL COMMENT 'Day 15 post claim — Stripe fires here',
  status VARCHAR(30) NOT NULL DEFAULT 'pending' COMMENT 'pending|disputed|charged|failed|cancelled',
  stripe_charge_id VARCHAR(255) NULL,
  stripe_payment_intent_id VARCHAR(255) NULL,
  dispute_reason TEXT NULL,
  claimed_at DATETIME NOT NULL COMMENT 'When buyer claim form was submitted',
  settled_at DATETIME NULL COMMENT 'When charge succeeded or dispute was resolved',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY thread_id (thread_id),
  KEY auto_debit_date (auto_debit_date, status),
  KEY status (status)
) {$charset_collate};";

		self::run_schema( $sql );
	}

	private static function create_rejection_cooldown_table(): void {
		global $wpdb;

		$table           = self::cooldown_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			return;
		}

		$sql = "CREATE TABLE `{$table}` (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  seller_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Provider user ID',
  buyer_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Buyer WordPress user ID',
  rejected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL COMMENT 'rejected_at + 90 days',
  PRIMARY KEY (id),
  UNIQUE KEY seller_buyer (seller_id, buyer_id),
  KEY expires_at (expires_at)
) {$charset_collate};";

		self::run_schema( $sql );
	}

	// ─── Shared schema runner (dbDelta-safe) ───────────────────────────────────

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
