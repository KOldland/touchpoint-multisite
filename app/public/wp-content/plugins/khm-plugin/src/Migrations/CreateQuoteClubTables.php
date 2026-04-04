<?php
/**
 * Quote Club Database Migration
 *
 * Creates tables for sponsor commentary submissions, saved searches,
 * and editorial credit tracking.
 *
 * @package KHM\Migrations
 */

namespace KHM\Migrations;

class CreateQuoteClubTables {

	/**
	 * Create all Quote Club tables
	 */
	public static function create_tables(): void {
		self::create_sponsor_commentary_table();
		self::create_saved_searches_table();
	}

	/**
	 * Drop all Quote Club tables
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$tables = [
			$wpdb->prefix . 'khm_sponsor_commentary',
			$wpdb->prefix . 'khm_saved_searches',
		];

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Create sponsor commentary table for quote club submissions
	 */
	private static function create_sponsor_commentary_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'khm_sponsor_commentary';

		// Check if table already exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			sponsor_id bigint unsigned NOT NULL,
			session_id varchar(191) DEFAULT NULL,
			post_id bigint unsigned DEFAULT NULL,
			question_id varchar(191) DEFAULT NULL,
			user_id bigint unsigned NOT NULL,
			commentary_text longtext NOT NULL,
			word_count int NOT NULL,
			credits_used int NOT NULL,
			status enum('pending_editorial','approved','rejected','published') NOT NULL DEFAULT 'pending_editorial',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY sponsor_idx (sponsor_id),
			KEY session_idx (session_id),
			KEY user_idx (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta( $sql );
	}

	/**
	 * Create saved searches table
	 */
	private static function create_saved_searches_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'khm_saved_searches';

		// Check if table already exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint unsigned NOT NULL,
			sponsor_id bigint unsigned DEFAULT NULL,
			name varchar(255) NOT NULL,
			query_json longtext NOT NULL,
			last_run_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY user_idx (user_id),
			KEY sponsor_idx (sponsor_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta( $sql );
	}
}
