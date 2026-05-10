<?php
/**
 * Migration: Add RFQ Response and Payment Fields to Intro Threads
 *
 * Adds columns to connect_intro_threads to support:
 * - Structured seller initial response (capability, cost, approach, timeline, lead contact)
 * - Seller commission rate choice (5–25%)
 * - Seller payment tracking (Stripe method, status, deferred debit date)
 * - Buyer discount code claim flow (code, claim timestamp)
 * - Commission settlement tracking
 * - Handover preference (diary_link, email_brief, external_portal)
 */

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

class AddRFPResponseFieldsToThreads {

	public static function up(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'connect_intro_threads';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return false;
		}

		$columns = [
			// Seller response
			'seller_initial_response'      => "JSON NULL",
			'seller_response_status'       => "VARCHAR(30) NOT NULL DEFAULT 'not_requested'",
			'seller_response_submitted_at' => "DATETIME NULL",
			'seller_commission_rate'       => "TINYINT UNSIGNED NULL COMMENT '5–25 representing %'",

			// Seller payment
			'seller_payment_method_id'     => "VARCHAR(255) NULL COMMENT 'Stripe Customer ID or payment intent ref'",
			'seller_payment_status'        => "VARCHAR(30) NOT NULL DEFAULT 'pending' COMMENT 'pending|authorized|debit_scheduled|charged|failed|disputed'",
			'seller_payment_timestamp'     => "DATETIME NULL",
			'auto_debit_date'              => "DATETIME NULL COMMENT 'When Stripe off-session charge fires (day 15 post claim)'",
			'commission_settled'           => "TINYINT(1) NOT NULL DEFAULT 0",

			// Buyer discount code
			'buyer_discount_code'          => "VARCHAR(12) NULL",
			'buyer_discount_claimed_at'    => "DATETIME NULL",

			// Handover
			'handover_preference'          => "VARCHAR(30) NULL COMMENT 'diary_link|email_brief|external_portal'",
		];

		foreach ( $columns as $name => $definition ) {
			if ( ! self::column_exists( $table, $name ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$definition}" );
			}
		}

		// Unique index on discount code for claim lookups
		if ( ! self::index_exists( $table, 'buyer_discount_code' ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD UNIQUE KEY `buyer_discount_code` (`buyer_discount_code`)" );
		}

		// Index for the daily deferred-debit async job
		if ( ! self::index_exists( $table, 'auto_debit_date' ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD KEY `auto_debit_date` (`auto_debit_date`, `seller_payment_status`)" );
		}

		return true;
	}

	public static function down(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'connect_intro_threads';

		$wpdb->query( "ALTER TABLE `{$table}` DROP KEY IF EXISTS `buyer_discount_code`" );
		$wpdb->query( "ALTER TABLE `{$table}` DROP KEY IF EXISTS `auto_debit_date`" );

		$columns = [
			'seller_initial_response',
			'seller_response_status',
			'seller_response_submitted_at',
			'seller_commission_rate',
			'seller_payment_method_id',
			'seller_payment_status',
			'seller_payment_timestamp',
			'auto_debit_date',
			'commission_settled',
			'buyer_discount_code',
			'buyer_discount_claimed_at',
			'handover_preference',
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
