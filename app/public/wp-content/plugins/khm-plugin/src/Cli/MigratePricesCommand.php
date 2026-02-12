<?php
/**
 * WP-CLI command to migrate Stripe price IDs from option to level metadata.
 *
 * @package KHM\CLI
 */

namespace KHM\CLI;

use KHM\Services\LevelRepository;
use WP_CLI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Migrate Stripe Price IDs to membership level metadata.
 */
class MigratePricesCommand {

	private LevelRepository $levels;

	public function __construct( ?LevelRepository $levels = null ) {
		$this->levels = $levels ?: new LevelRepository();
	}

	/**
	 * Migrate Stripe price IDs from khm_stripe_price_map option to level metadata.
	 *
	 * This command reads the khm_stripe_membership_price_map option (and legacy
	 * khm_stripe_price_map) and copies each price ID to the corresponding level's
	 * stripe_price_id metadata field.
	 *
	 * If the option contains an array with `stripe_price_ids`, the mapping will be
	 * stored in khm_level_meta.stripe_price_ids instead.
	 * Useful for migrating from the old option-based system to the new metadata-based system.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Preview changes without saving them.
	 *
	 * [--overwrite]
	 * : Overwrite existing stripe_price_id metadata values.
	 *
	 * [--backup]
	 * : Store a rollback snapshot in an option before writing changes.
	 *
	 * [--backup-key=<key>]
	 * : Custom option name to store the rollback snapshot (implies --backup).
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview migration
	 *     wp khm migrate-prices --dry-run
	 *
	 *     # Perform migration
	 *     wp khm migrate-prices
	 *
	 *     # Overwrite existing values
	 *     wp khm migrate-prices --overwrite
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		$dry_run   = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$overwrite = WP_CLI\Utils\get_flag_value( $assoc_args, 'overwrite', false );
		$backup_key = (string) WP_CLI\Utils\get_flag_value( $assoc_args, 'backup-key', '' );
		$backup_enabled = $backup_key !== '' ? true : WP_CLI\Utils\get_flag_value( $assoc_args, 'backup', false );

		if ( $dry_run ) {
			WP_CLI::line( WP_CLI::colorize( '%Y[DRY RUN MODE]%n No changes will be saved.' ) );
			WP_CLI::line( '' );
		}

		// Get the price map from option (and legacy fallback)
		$source_option = 'khm_stripe_membership_price_map';
		$map = get_option( $source_option, [] );
		if ( empty( $map ) || ! is_array( $map ) ) {
			$source_option = 'khm_stripe_price_map';
			$map = get_option( $source_option, [] );
		}

		if ( empty( $map ) || ! is_array( $map ) ) {
			WP_CLI::warning( 'No price mappings found in khm_stripe_membership_price_map option.' );
			return;
		}

		WP_CLI::line( sprintf( 'Found %d price mappings in option.', count( $map ) ) );
		WP_CLI::line( '' );

		$migrated = 0;
		$skipped  = 0;
		$errors   = 0;
		$backup = null;
		if ( $backup_enabled ) {
			if ( $backup_key === '' ) {
				$backup_key = 'khm_migrate_prices_backup_' . gmdate( 'Ymd_His' );
			}
			$backup = [
				'created_at'   => gmdate( 'c' ),
				'source_option'=> $source_option,
				'entries'      => [],
			];
		}

		foreach ( $map as $level_id => $price_id ) {
			if ( is_array( $price_id ) && isset( $price_id['stripe_price_ids'] ) ) {
				$this->migrate_nested_price_map( (int) $level_id, $price_id['stripe_price_ids'], $dry_run, $overwrite, $migrated, $skipped, $errors, $backup );
				continue;
			}
			$level_id = (int) $level_id;
			$price_id = trim( (string) $price_id );

			// Validate level exists
			$level = $this->levels->get( $level_id, true );
			if ( ! $level ) {
				WP_CLI::warning( sprintf( 'Level ID %d not found. Skipping...', $level_id ) );
				$skipped++;
				continue;
			}

			// Check if already has a price ID
			$existing = $this->levels->getMeta( $level_id, 'stripe_price_id' );
			if ( ! empty( $existing ) && ! $overwrite ) {
				WP_CLI::line( sprintf(
					'Level #%d ("%s") already has price ID: %s (use --overwrite to replace)',
					$level_id,
					$level->name,
					$existing
				) );
				$skipped++;
				continue;
			}

			// Validate price ID format
			if ( ! preg_match( '/^price_[A-Za-z0-9]+$/', $price_id ) ) {
				WP_CLI::warning( sprintf(
					'Invalid Stripe Price ID format for level #%d: %s',
					$level_id,
					$price_id
				) );
				$errors++;
				continue;
			}

			if ( is_array( $backup ) ) {
				$this->add_backup_entry( $backup, $level_id );
			}

			// Update the metadata
			if ( ! $dry_run ) {
				$success = $this->levels->updateMeta( $level_id, 'stripe_price_id', $price_id );
				if ( ! $success ) {
					WP_CLI::error( sprintf(
						'Failed to update metadata for level #%d',
						$level_id
					), false );
					$errors++;
					continue;
				}
			}

			WP_CLI::success( sprintf(
				'Level #%d ("%s"): %s → stripe_price_id metadata',
				$level_id,
				$level->name,
				$price_id
			) );

			$migrated++;
		}

		if ( is_array( $backup ) && ! empty( $backup['entries'] ) ) {
			if ( $dry_run ) {
				WP_CLI::line( '' );
				WP_CLI::line( WP_CLI::colorize( '%Y[DRY RUN]%n Rollback snapshot would be stored in option:' ) );
				WP_CLI::line( '  ' . $backup_key );
			} else {
				update_option( $backup_key, $backup, false );
				WP_CLI::line( '' );
				WP_CLI::success( sprintf( 'Rollback snapshot saved to option: %s', $backup_key ) );
			}
		}

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%GMigration Summary:%n' ) );
		WP_CLI::line( sprintf( '  Migrated: %d', $migrated ) );
		WP_CLI::line( sprintf( '  Skipped:  %d', $skipped ) );
		WP_CLI::line( sprintf( '  Errors:   %d', $errors ) );

		if ( $dry_run ) {
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( '%Y[DRY RUN]%n No changes were saved. Run without --dry-run to apply.' ) );
		} elseif ( $migrated > 0 ) {
			WP_CLI::line( '' );
			WP_CLI::success( sprintf( 'Successfully migrated %d price IDs to level metadata.', $migrated ) );
		}
	}

	/**
	 * Migrate nested stripe_price_ids map into khm_level_meta.
	 *
	 * @param int $level_id
	 * @param array $price_map
	 * @param bool $dry_run
	 * @param bool $overwrite
	 * @param int $migrated
	 * @param int $skipped
	 * @param int $errors
	 * @return void
	 */
	private function migrate_nested_price_map( int $level_id, array $price_map, bool $dry_run, bool $overwrite, int &$migrated, int &$skipped, int &$errors, ?array &$backup ): void {
		$level = $this->levels->get( $level_id, true );
		if ( ! $level ) {
			WP_CLI::warning( sprintf( 'Level ID %d not found. Skipping...', $level_id ) );
			$skipped++;
			return;
		}

		$current_meta = $this->levels->getMeta( $level_id, 'khm_level_meta', [] );
		if ( is_string( $current_meta ) ) {
			$decoded = json_decode( $current_meta, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$current_meta = $decoded;
			}
		}
		if ( ! is_array( $current_meta ) ) {
			$current_meta = [];
		}

		if ( isset( $current_meta['stripe_price_ids'] ) && ! $overwrite ) {
			WP_CLI::line( sprintf(
				'Level #%d ("%s") already has stripe_price_ids map (use --overwrite to replace)',
				$level_id,
				$level->name
			) );
			$skipped++;
			return;
		}

		if ( is_array( $backup ) ) {
			$this->add_backup_entry( $backup, $level_id );
		}

		$current_meta['stripe_price_ids'] = $price_map;

		if ( ! $dry_run ) {
			$success = $this->levels->updateMeta( $level_id, 'khm_level_meta', $current_meta );
			if ( ! $success ) {
				WP_CLI::error( sprintf( 'Failed to update khm_level_meta for level #%d', $level_id ), false );
				$errors++;
				return;
			}
		}

		WP_CLI::success( sprintf(
			'Level #%d ("%s"): stripe_price_ids map → khm_level_meta',
			$level_id,
			$level->name
		) );
		$migrated++;
	}

	private function add_backup_entry( array &$backup, int $level_id ): void {
		if ( isset( $backup['entries'][ $level_id ] ) ) {
			return;
		}

		$backup['entries'][ $level_id ] = [
			'stripe_price_id' => $this->levels->getMeta( $level_id, 'stripe_price_id' ),
			'khm_level_meta'  => $this->levels->getMeta( $level_id, 'khm_level_meta' ),
		];
	}
}

// Register the command
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'khm migrate-prices', MigratePricesCommand::class );
}
