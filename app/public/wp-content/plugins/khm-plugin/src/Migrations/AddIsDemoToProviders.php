<?php

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

class AddIsDemoToProviders {

	public static function up(): bool {
		global $wpdb;

		$table = ConnectProvidersMigration::table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return false;
		}

		if ( ! self::column_exists( $table, 'is_demo' ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `is_demo` TINYINT(1) NOT NULL DEFAULT 0" );
		}

		if ( ! self::index_exists( $table, 'idx_is_demo' ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD KEY `idx_is_demo` (`is_demo`)" );
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
