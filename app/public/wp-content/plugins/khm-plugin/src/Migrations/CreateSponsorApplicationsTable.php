<?php

namespace KHM\Migrations;

class CreateSponsorApplicationsTable {

	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = $wpdb->prefix . 'khm_sponsor_applications';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
			error_log( '[KHM Sponsors] Sponsor applications table already exists.' );
			return;
		}

		$sql = "CREATE TABLE `{$table}` (
			`id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`company_name`     VARCHAR(255) NOT NULL,
			`contact_name`     VARCHAR(255) NOT NULL,
			`contact_email`    VARCHAR(255) NOT NULL,
			`contact_phone`    VARCHAR(20) NULL,
			`sector`           VARCHAR(100) NOT NULL,
			`company_url`      TEXT NULL,
			`use_case`         TEXT NOT NULL,
			`message`          LONGTEXT NULL,
			`status`           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
			`rejection_reason` TEXT NULL,
			`sponsor_id`       BIGINT UNSIGNED NULL,
			`created_at`       DATETIME NOT NULL,
			`updated_at`       DATETIME NOT NULL,
			`reviewed_at`      DATETIME NULL,
			`reviewed_by`      BIGINT UNSIGNED NULL,
			PRIMARY KEY (`id`),
			KEY `status` (`status`),
			KEY `created_at` (`created_at`),
			KEY `sponsor_id` (`sponsor_id`),
			UNIQUE KEY `email_company` (`contact_email`, `company_name`)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		error_log( '[KHM Sponsors] Sponsor applications table created.' );
	}

	public static function drop_tables(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_applications';
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		error_log( '[KHM Sponsors] Sponsor applications table dropped.' );
	}
}
