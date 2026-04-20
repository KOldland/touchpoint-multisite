<?php

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

class ConnectProvidersMigration {

	const TABLE = 'connect_providers';

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	public static function run(): void {
		global $wpdb;

		$table            = self::table_name();
		$charset_collate  = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  blog_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
  sponsor_id BIGINT(20) UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(200) NOT NULL,
  description LONGTEXT NULL,
  website_url VARCHAR(255) NULL,
  provider_type VARCHAR(100) NULL,
  sweet_spot_summary TEXT NULL,
  company_size_min INT(11) NULL,
  company_size_max INT(11) NULL,
  budget_min INT(11) NULL,
  budget_max INT(11) NULL,
  onboarding_days INT(11) NULL,
  regions LONGTEXT NULL,
  deployment_modes LONGTEXT NULL,
  support_tiers LONGTEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  commentary_enabled TINYINT(1) NOT NULL DEFAULT 0,
  ad_targeting_enabled TINYINT(1) NOT NULL DEFAULT 0,
  titles LONGTEXT NULL,
  comparison_fields LONGTEXT NULL,
  match_rules LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY slug_per_blog (blog_id, slug),
  KEY status_by_blog (blog_id, status),
  KEY sponsor_by_blog (blog_id, sponsor_id)
) {$charset_collate};";

    if ( ! method_exists( $wpdb, 'tables' ) || ! defined( 'WP_CONTENT_DIR' ) ) {
      $wpdb->query( $sql );
      return;
    }

    if ( function_exists( 'dbDelta' ) ) {
      dbDelta( $sql );
      return;
    }

    $upgrade_file = defined( 'ABSPATH' ) ? ABSPATH . 'wp-admin/includes/upgrade.php' : '';
    if ( '' !== $upgrade_file && file_exists( $upgrade_file ) ) {
      require_once $upgrade_file;
      if ( function_exists( 'dbDelta' ) ) {
        dbDelta( $sql );
        return;
      }
    }

    $wpdb->query( $sql );
	}
}