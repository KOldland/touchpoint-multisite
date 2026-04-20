<?php

namespace KHM\Migrations;

class AddCommentaryConnectColumns {

	public static function run(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'khm_sponsor_commentary';

		if ( ! self::column_exists( $table, 'connect_provider_id' ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `connect_provider_id` BIGINT UNSIGNED NULL AFTER `post_id`" );
		}

		if ( ! self::column_exists( $table, 'connect_provider_snapshot' ) ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `connect_provider_snapshot` LONGTEXT NULL AFTER `connect_provider_id`" );
		}
	}

	private static function column_exists( string $table, string $column ): bool {
		global $wpdb;

		$result = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) );

		return ! empty( $result );
	}
}