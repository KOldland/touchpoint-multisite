<?php

namespace KHM\Migrations;

class CreateSponsorAdvertsTable {

	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = $wpdb->prefix . 'khm_sponsor_adverts';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
			error_log( '[KHM Adverts] Sponsor adverts table already exists.' );
			return;
		}

		$sql = "CREATE TABLE `{$table}` (
			`id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`sponsor_id`    BIGINT UNSIGNED NOT NULL,
			`user_id`       BIGINT UNSIGNED NOT NULL,
			`title`         VARCHAR(255) NOT NULL DEFAULT '',
			`placement`     ENUM('commentary','press-release','overview','sidebar') NOT NULL DEFAULT 'commentary',
			`media_url`     TEXT NULL,
			`media_id`      BIGINT UNSIGNED NULL,
			`click_url`     TEXT NULL,
			`alt_text`      VARCHAR(255) NULL,
			`status`        ENUM('draft','pending','approved','rejected','paused') NOT NULL DEFAULT 'draft',
			`rejection_reason` TEXT NULL,
			`impressions`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
			`clicks`        BIGINT UNSIGNED NOT NULL DEFAULT 0,
			`weight`        TINYINT UNSIGNED NOT NULL DEFAULT 5,
			`created_at`    DATETIME NOT NULL,
			`updated_at`    DATETIME NOT NULL,
			PRIMARY KEY (`id`),
			KEY `sponsor_id` (`sponsor_id`),
			KEY `status` (`status`),
			KEY `placement` (`placement`),
			KEY `created_at` (`created_at`)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		error_log( '[KHM Adverts] Sponsor adverts table created.' );
	}

	public static function drop_tables(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_adverts';
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		error_log( '[KHM Adverts] Sponsor adverts table dropped.' );
	}
}
