<?php
/**
 * WP-CLI command to replay Stripe marketing dead-letter entries.
 *
 * @package KHM\CLI
 */

namespace KHM\CLI;

use KHM\Services\StripeMarketingImportDeadLetterStore;
use KHM\Services\StripeMarketingImporter;
use WP_CLI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class StripeMarketingDeadLettersReplayCommand {

	private StripeMarketingImporter $importer;
	private StripeMarketingImportDeadLetterStore $store;

	public function __construct( ?StripeMarketingImporter $importer = null, ?StripeMarketingImportDeadLetterStore $store = null ) {
		$this->importer = $importer ?: new StripeMarketingImporter();
		$this->store = $store ?: new StripeMarketingImportDeadLetterStore();
	}

	/**
	 * Replay dead-lettered Stripe marketing imports.
	 *
	 * ## OPTIONS
	 *
	 * [--id=<id>]
	 * : Replay a specific dead-letter row ID.
	 *
	 * [--all-unresolved]
	 * : Replay unresolved dead letters.
	 *
	 * [--limit=<n>]
	 * : Max unresolved rows to replay when using --all-unresolved.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--dry-run]
	 * : Resolve and parse without saving or marking resolved.
	 *
	 * ## EXAMPLES
	 *
	 *     wp khm stripe-marketing-dead-letters-replay --id=12
	 *     wp khm stripe-marketing-dead-letters-replay --all-unresolved --limit=50
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$id = isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : 0;
		$all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all-unresolved', false );
		$dryRun = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 20;
		$limit = max( 1, min( 200, $limit ) );

		if ( $id <= 0 && ! $all ) {
			WP_CLI::error( 'Specify either --id=<id> or --all-unresolved.' );
		}

		$rows = [];
		if ( $id > 0 ) {
			$row = $this->store->getById( $id );
			if ( ! $row ) {
				WP_CLI::error( sprintf( 'Dead-letter id %d not found.', $id ) );
			}
			$rows[] = $row;
		} else {
			$rows = $this->store->getUnresolved( $limit );
			if ( empty( $rows ) ) {
				WP_CLI::warning( 'No unresolved dead letters found.' );
				return;
			}
		}

		$success = 0;
		$failed = 0;
		$skipped = 0;

		foreach ( $rows as $row ) {
			$rowId = (int) ( $row['id'] ?? 0 );
			$productId = isset( $row['product_id'] ) ? (string) $row['product_id'] : '';
			$levelId = isset( $row['level_id'] ) ? (int) $row['level_id'] : 0;

			if ( $productId === '' || $rowId <= 0 ) {
				$skipped++;
				WP_CLI::warning( sprintf( 'Skipping malformed dead-letter row id=%d', $rowId ) );
				continue;
			}

			try {
				$result = $this->importer->importProductToLevel( $productId, $levelId > 0 ? $levelId : null, (bool) $dryRun, 'replay' );
				if ( $dryRun ) {
					$success++;
					WP_CLI::line( sprintf( '[DRY-RUN] id=%d product=%s level=%d status=ok', $rowId, $productId, (int) ( $result['level_id'] ?? $levelId ) ) );
					continue;
				}

				if ( ! $this->store->markResolved( $rowId ) ) {
					WP_CLI::warning( sprintf( 'Replay succeeded but failed to mark resolved for id=%d', $rowId ) );
				}

				$success++;
				WP_CLI::success( sprintf( 'Replayed dead-letter id=%d product=%s level=%d', $rowId, $productId, (int) ( $result['level_id'] ?? $levelId ) ) );
			} catch ( \Throwable $e ) {
				$failed++;
				WP_CLI::warning( sprintf( 'Replay failed id=%d product=%s: %s', $rowId, $productId, $e->getMessage() ) );
			}
		}

		WP_CLI::line( '' );
		WP_CLI::line( 'Replay Summary:' );
		WP_CLI::line( sprintf( '  Success: %d', $success ) );
		WP_CLI::line( sprintf( '  Failed:  %d', $failed ) );
		WP_CLI::line( sprintf( '  Skipped: %d', $skipped ) );

		if ( $failed > 0 ) {
			WP_CLI::warning( 'Some dead-letter replays failed.' );
			return;
		}

		WP_CLI::success( 'Dead-letter replay completed.' );
	}
}

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'khm stripe-marketing-dead-letters-replay', StripeMarketingDeadLettersReplayCommand::class );
}
