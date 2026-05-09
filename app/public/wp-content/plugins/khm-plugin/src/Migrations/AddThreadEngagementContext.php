<?php
/**
 * Migration: Add Engagement Context to Intro Threads
 *
 * Adds three columns to connect_intro_threads table to track:
 * - opportunity_id: Foreign key to parent opportunity (for deduping and context)
 * - request_type: 'direct_connection' or 'rfp_request' (workflow type)
 * - engaged_option: 'option_1' or 'option_2' (pricing option selected for engaged tier)
 *
 * @package KHM\Migrations
 */

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

class AddThreadEngagementContext {

	public static function up(): bool {
		global $wpdb;

		$table = 'wp_connect_intro_threads';
		$charset_collate = $wpdb->get_charset_collate();

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return false;
		}

		// Add columns if they don't already exist
		$columns_to_add = array(
			'opportunity_id' => "BIGINT(20) UNSIGNED NULL AFTER `sponsor_id`",
			'request_type'   => "VARCHAR(30) DEFAULT 'direct_connection' AFTER `session_id`",
			'engaged_option' => "VARCHAR(30) NULL AFTER `buyer_token`"
		);

		foreach ( $columns_to_add as $column_name => $column_def ) {
			if ( ! self::column_exists( $table, $column_name ) ) {
				$sql = "ALTER TABLE {$table} ADD COLUMN {$column_name} {$column_def}";
				if ( false === $wpdb->query( $sql ) ) {
					return false;
				}
			}
		}

		// Add index on opportunity_id for filtering
		if ( ! self::index_exists( $table, 'opportunity_id' ) ) {
			$sql = "ALTER TABLE {$table} ADD INDEX `opportunity_id` (`opportunity_id`)";
			$wpdb->query( $sql );
		}

		// Add index on request_type for filtering
		if ( ! self::index_exists( $table, 'request_type' ) ) {
			$sql = "ALTER TABLE {$table} ADD INDEX `request_type` (`request_type`)";
			$wpdb->query( $sql );
		}

		return true;
	}

	public static function down(): bool {
		global $wpdb;

		$table = 'wp_connect_intro_threads';

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return false;
		}

		// Drop columns if they exist
		$columns_to_drop = array( 'opportunity_id', 'request_type', 'engaged_option' );

		foreach ( $columns_to_drop as $column_name ) {
			if ( self::column_exists( $table, $column_name ) ) {
				$sql = "ALTER TABLE {$table} DROP COLUMN {$column_name}";
				if ( false === $wpdb->query( $sql ) ) {
					return false;
				}
			}
		}

		return true;
	}

	private static function column_exists( string $table, string $column ): bool {
		global $wpdb;

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = %s",
				$table,
				$column
			)
		);

		return ! empty( $result );
	}

	private static function index_exists( string $table, string $index ): bool {
		global $wpdb;

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_NAME = %s AND INDEX_NAME = %s",
				$table,
				$index
			)
		);

		return ! empty( $result );
	}
}
