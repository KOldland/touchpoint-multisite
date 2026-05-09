<?php

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

class AddBuyerIcpFieldsToThreads {

	public static function up(): bool {
		global $wpdb;

		$table = ConnectWorkflowMigration::threads_table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return false;
		}

		$columns = array(
			'buyer_sector'          => 'VARCHAR(100) NULL',
			'buyer_company_size'    => 'VARCHAR(50) NULL',
			'buyer_job_title'       => 'VARCHAR(150) NULL',
			'buyer_city'            => 'VARCHAR(100) NULL',
			'buyer_country'         => 'VARCHAR(100) NULL',
			'buyer_phone_encrypted' => 'LONGTEXT NULL',
			'buyer_linkedin'        => 'VARCHAR(255) NULL',
			'is_demo'               => 'TINYINT(1) NOT NULL DEFAULT 0',
		);

		foreach ( $columns as $name => $definition ) {
			if ( ! self::column_exists( $table, $name ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$definition}" );
			}
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
