<?php
/**
 * Migration: Add Engaged Workflow Fields
 *
 * Adds support for RFP requests and direct connections as distinct request types,
 * plus engaged option tracking for pricing model selection.
 */

namespace KHM\Migrations;

class AddEngagedWorkflowFields {

	/**
	 * Run the migration
	 */
	public static function up() {
		global $wpdb;

		$opportunities_table = ConnectWorkflowMigration::opportunities_table_name();
		$charset_collate     = $wpdb->get_charset_collate();

		// Add request_type column if not exists
		if ( ! static::column_exists( $opportunities_table, 'request_type' ) ) {
			$wpdb->query(
				"ALTER TABLE {$opportunities_table} 
				ADD COLUMN request_type VARCHAR(30) DEFAULT 'direct_connection' AFTER opportunity_status"
			);
			// Index for filtering by request type
			$wpdb->query(
				"ALTER TABLE {$opportunities_table} 
				ADD INDEX idx_request_type (request_type)"
			);
		}

		// Add rfp_metadata column if not exists
		if ( ! static::column_exists( $opportunities_table, 'rfp_metadata' ) ) {
			$wpdb->query(
				"ALTER TABLE {$opportunities_table} 
				ADD COLUMN rfp_metadata JSON DEFAULT NULL AFTER request_type"
			);
		}

		// Add engaged_option column if not exists
		if ( ! static::column_exists( $opportunities_table, 'engaged_option' ) ) {
			$wpdb->query(
				"ALTER TABLE {$opportunities_table} 
				ADD COLUMN engaged_option VARCHAR(30) DEFAULT NULL AFTER provider_id"
			);
		}

		return true;
	}

	/**
	 * Rollback the migration
	 */
	public static function down() {
		global $wpdb;

		$opportunities_table = ConnectWorkflowMigration::opportunities_table_name();

		$wpdb->query( "ALTER TABLE {$opportunities_table} DROP INDEX IF EXISTS idx_request_type" );
		$wpdb->query( "ALTER TABLE {$opportunities_table} DROP COLUMN IF EXISTS request_type" );
		$wpdb->query( "ALTER TABLE {$opportunities_table} DROP COLUMN IF EXISTS rfp_metadata" );
		$wpdb->query( "ALTER TABLE {$opportunities_table} DROP COLUMN IF EXISTS engaged_option" );

		return true;
	}

	/**
	 * Check if a column exists in a table
	 *
	 * @param string $table_name
	 * @param string $column_name
	 * @return bool
	 */
	private static function column_exists( $table_name, $column_name ) {
		global $wpdb;

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
				DB_NAME,
				$table_name,
				$column_name
			)
		);

		return ! empty( $result );
	}
}
