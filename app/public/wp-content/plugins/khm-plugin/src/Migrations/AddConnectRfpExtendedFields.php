<?php

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Migration: Add extended buyer-wizard fields to connect_rfps.
 *
 * New columns:
 *   challenge          – selected problem slug/label (TEXT)
 *   solution_types     – JSON array of solution slugs
 *   sector             – buyer's industry sector (e.g. 'retail')
 *   region             – preferred partner region (e.g. 'uk', 'europe')
 *   company_size_band  – band slug matching $valid_bands (e.g. '51-250')
 *   integrations       – JSON array of integration slugs
 *   integrations_other – free-text context for unlisted integrations
 *   delivery_model     – e.g. 'saas', 'on-prem', 'hybrid', 'private-cloud'
 *   engagement_model   – e.g. 'fixed-project', 'retained', 'ad-hoc-advisory'
 *   free_trial         – boolean: buyer wants a free-trial before commitment
 *   provider_type      – coarse provider preference from business-details step
 *   partner_posture    – partner preference: established-platform/specialist-best-of-breed/no-preference
 *   deployment_mode    – technical deployment mode: saas/hybrid/on-prem/private-cloud
 *   onboarding_style   – onboarding preference: self-serve/guided-onboarding/fully-managed
 *   installation_preference – hardware install preference
 *   proof_of_commitment – required/preferred/not-needed
 */
class AddConnectRfpExtendedFields {

	public static function up(): void {
		global $wpdb;

		$table = AddConnectBuyerDirectoryColumns::rfps_table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$columns = [
			'challenge'          => "ALTER TABLE `{$table}` ADD COLUMN `challenge` TEXT NULL",
			'solution_types'     => "ALTER TABLE `{$table}` ADD COLUMN `solution_types` LONGTEXT NULL",
			'sector'             => "ALTER TABLE `{$table}` ADD COLUMN `sector` VARCHAR(100) NULL",
			'region'             => "ALTER TABLE `{$table}` ADD COLUMN `region` VARCHAR(100) NULL",
			'company_size_band'  => "ALTER TABLE `{$table}` ADD COLUMN `company_size_band` VARCHAR(20) NULL",
			'integrations'       => "ALTER TABLE `{$table}` ADD COLUMN `integrations` LONGTEXT NULL",
			'integrations_other' => "ALTER TABLE `{$table}` ADD COLUMN `integrations_other` TEXT NULL",
			'delivery_model'     => "ALTER TABLE `{$table}` ADD COLUMN `delivery_model` VARCHAR(100) NULL",
			'engagement_model'   => "ALTER TABLE `{$table}` ADD COLUMN `engagement_model` VARCHAR(100) NULL",
			'free_trial'         => "ALTER TABLE `{$table}` ADD COLUMN `free_trial` TINYINT(1) NOT NULL DEFAULT 0",
			'provider_type'      => "ALTER TABLE `{$table}` ADD COLUMN `provider_type` VARCHAR(100) NULL",
			'partner_posture'    => "ALTER TABLE `{$table}` ADD COLUMN `partner_posture` VARCHAR(100) NULL",
			'deployment_mode'    => "ALTER TABLE `{$table}` ADD COLUMN `deployment_mode` VARCHAR(100) NULL",
			'onboarding_style'   => "ALTER TABLE `{$table}` ADD COLUMN `onboarding_style` VARCHAR(100) NULL",
			'installation_preference' => "ALTER TABLE `{$table}` ADD COLUMN `installation_preference` VARCHAR(100) NULL",
			'proof_of_commitment' => "ALTER TABLE `{$table}` ADD COLUMN `proof_of_commitment` VARCHAR(50) NULL",
		];

		foreach ( $columns as $column => $sql ) {
			if ( ! self::column_exists( $table, $column ) ) {
				$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
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
}
