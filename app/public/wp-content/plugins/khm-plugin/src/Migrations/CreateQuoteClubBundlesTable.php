<?php
/**
 * Quote Club Credit Bundles Migration
 *
 * Creates the wp_khm_qc_credit_bundles table (admin-defined purchasable credit
 * bundles) and the wp_khm_qc_bundle_purchases table (purchase audit trail).
 *
 * @package KHM\Migrations
 */

namespace KHM\Migrations;

class CreateQuoteClubBundlesTable {

	/**
	 * Create the bundle tables.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$bundles_table   = $wpdb->prefix . 'khm_qc_credit_bundles';
		$purchases_table = $wpdb->prefix . 'khm_qc_bundle_purchases';

		// Check if already created.
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$bundles_table}'" ) === $bundles_table ) {
			return;
		}

		$bundles_sql = "CREATE TABLE {$bundles_table} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			name varchar(200) NOT NULL,
			description text DEFAULT NULL,
			editorial_credits int NOT NULL DEFAULT 0,
			press_release_credits int NOT NULL DEFAULT 0,
			price_cents int NOT NULL DEFAULT 0,
			stripe_price_id varchar(191) DEFAULT NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY active_idx (active)
		) {$charset_collate};";

		$purchases_sql = "CREATE TABLE {$purchases_table} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint unsigned NOT NULL,
			sponsor_id bigint unsigned DEFAULT NULL,
			bundle_id bigint unsigned NOT NULL,
			stripe_session_id varchar(191) DEFAULT NULL,
			editorial_credits_added int NOT NULL DEFAULT 0,
			press_release_credits_added int NOT NULL DEFAULT 0,
			price_cents int NOT NULL DEFAULT 0,
			status enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY user_idx (user_id),
			KEY bundle_idx (bundle_id),
			KEY stripe_session_idx (stripe_session_id),
			KEY status_idx (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $bundles_sql );
		dbDelta( $purchases_sql );

		error_log( '[KHM Quote Club] Credit bundle tables created.' );
	}

	/**
	 * Drop the bundle tables (rollback).
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}khm_qc_bundle_purchases" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}khm_qc_credit_bundles" );

		error_log( '[KHM Quote Club] Credit bundle tables dropped.' );
	}
}
