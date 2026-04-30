<?php

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

class ConnectWorkflowMigration {

	const THREADS_TABLE       = 'connect_intro_threads';
	const MESSAGES_TABLE      = 'connect_intro_messages';
	const HANDOVERS_TABLE     = 'connect_handovers';
	const MILESTONES_TABLE    = 'connect_milestone_events';
	const OPPORTUNITIES_TABLE = 'connect_opportunities';
	const CONSENT_EVENTS_TABLE = 'connect_consent_events';

	public static function run(): void {
		self::create_threads_table();
		self::create_messages_table();
		self::create_handovers_table();
		self::create_milestones_table();
		self::create_consent_events_table();
	}

	public static function opportunities_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::OPPORTUNITIES_TABLE;
	}

	public static function consent_events_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::CONSENT_EVENTS_TABLE;
	}

	public static function threads_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::THREADS_TABLE;
	}

	public static function messages_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::MESSAGES_TABLE;
	}

	public static function handovers_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::HANDOVERS_TABLE;
	}

	public static function milestones_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::MILESTONES_TABLE;
	}

	private static function create_threads_table(): void {
		global $wpdb;

		$table           = self::threads_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  blog_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
  provider_id BIGINT(20) UNSIGNED NOT NULL,
  sponsor_id BIGINT(20) UNSIGNED NOT NULL,
  session_id VARCHAR(191) NULL,
  buyer_name VARCHAR(255) NOT NULL,
  buyer_company VARCHAR(255) NULL,
  buyer_email_encrypted LONGTEXT NULL,
  buyer_email_hash VARCHAR(64) NOT NULL,
  buyer_token VARCHAR(64) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'open',
  handover_status VARCHAR(30) NOT NULL DEFAULT 'not_started',
  last_message_excerpt TEXT NULL,
  latest_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY provider_sponsor (provider_id, sponsor_id),
  KEY sponsor_status (sponsor_id, status),
  KEY buyer_email_hash (buyer_email_hash),
  KEY buyer_token (buyer_token),
  KEY latest_message_at (latest_message_at)
) {$charset_collate};";

		self::run_schema( $sql );
	}

	private static function create_messages_table(): void {
		global $wpdb;

		$table           = self::messages_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id BIGINT(20) UNSIGNED NOT NULL,
  sender_role VARCHAR(20) NOT NULL,
  sender_user_id BIGINT(20) UNSIGNED NULL,
  message LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY thread_id (thread_id),
  KEY sender_role (sender_role),
  KEY created_at (created_at)
) {$charset_collate};";

		self::run_schema( $sql );
	}

	private static function create_handovers_table(): void {
		global $wpdb;

		$table           = self::handovers_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id BIGINT(20) UNSIGNED NOT NULL,
  provider_id BIGINT(20) UNSIGNED NOT NULL,
  sponsor_id BIGINT(20) UNSIGNED NOT NULL,
  buyer_email_hash VARCHAR(64) NOT NULL,
  buyer_company VARCHAR(255) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'buyer_requested',
  buyer_requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sponsor_confirmed_at DATETIME NULL,
  attribution_starts_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_thread_handover (thread_id),
  KEY sponsor_status (sponsor_id, status),
  KEY attribution_starts_at (attribution_starts_at)
) {$charset_collate};";

		self::run_schema( $sql );
	}

	private static function create_milestones_table(): void {
		global $wpdb;

		$table           = self::milestones_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  handover_id BIGINT(20) UNSIGNED NULL,
  thread_id BIGINT(20) UNSIGNED NULL,
  event_key VARCHAR(50) NOT NULL,
  event_status VARCHAR(20) NOT NULL DEFAULT 'completed',
  payload LONGTEXT NULL,
  event_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY handover_id (handover_id),
  KEY thread_id (thread_id),
  KEY event_key (event_key),
  KEY event_at (event_at)
) {$charset_collate};";

		self::run_schema( $sql );
	}

	private static function create_consent_events_table(): void {
		global $wpdb;

		$table           = self::consent_events_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  blog_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
  opportunity_id BIGINT(20) UNSIGNED DEFAULT NULL,
  thread_id BIGINT(20) UNSIGNED DEFAULT NULL,
  handover_id BIGINT(20) UNSIGNED DEFAULT NULL,
  event_key VARCHAR(50) NOT NULL,
  event_source VARCHAR(50) NOT NULL DEFAULT 'system',
  actor_role VARCHAR(30) NOT NULL DEFAULT 'system',
  event_payload LONGTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY blog_id (blog_id),
  KEY opportunity_id (opportunity_id),
  KEY thread_id (thread_id),
  KEY event_key (event_key)
) {$charset_collate};";

		self::run_schema( $sql );
	}

	private static function run_schema( string $sql ): void {
		global $wpdb;

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