<?php
/**
 * WP-CLI command to import Stripe product marketing features into level metadata.
 *
 * @package KHM\CLI
 */

namespace KHM\CLI;

use KHM\Services\StripeMarketingImporter;
use WP_CLI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class ImportStripeMarketingCommand {

	private StripeMarketingImporter $importer;

	public function __construct( ?StripeMarketingImporter $importer = null ) {
		$this->importer = $importer ?: new StripeMarketingImporter();
	}

	/**
	 * Import marketing feature copy from a Stripe product.
	 *
	 * ## OPTIONS
	 *
	 * <product_id>
	 * : Stripe product id (e.g. prod_12345).
	 *
	 * [--level=<id>]
	 * : Optional explicit membership level ID.
	 *
	 * [--dry-run]
	 * : Parse/resolve only without persisting.
	 *
	 * ## EXAMPLES
	 *
	 *     wp khm import-stripe-marketing prod_XXXXX --level=10 --dry-run
	 *     wp khm import-stripe-marketing prod_XXXXX --level=10
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$productId = isset( $args[0] ) ? trim( (string) $args[0] ) : '';
		if ( $productId === '' ) {
			WP_CLI::error( 'A Stripe product ID is required.' );
		}
		if ( ! StripeMarketingImporter::isValidProductId( $productId ) ) {
			WP_CLI::error( 'Invalid Stripe product ID format. Expected prod_ followed by alphanumeric characters.' );
		}

		$levelId = isset( $assoc_args['level'] ) ? (int) $assoc_args['level'] : null;
		$dryRun  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( $dryRun ) {
			WP_CLI::line( WP_CLI::colorize( '%Y[DRY RUN]%n No metadata will be updated.' ) );
		}

		try {
			$result = $this->importer->importProductToLevel( $productId, $levelId, (bool) $dryRun, 'cli' );
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
			return;
		}

		WP_CLI::line( sprintf( 'Level: %d', (int) $result['level_id'] ) );
		WP_CLI::line( sprintf( 'Imported lines: %d', count( $result['lines'] ) ) );
		if ( ! empty( $result['skipped_reason'] ) ) {
			WP_CLI::line( sprintf( 'Skipped: %s', (string) $result['skipped_reason'] ) );
		}
		foreach ( $result['lines'] as $line ) {
			WP_CLI::line( ' - ' . $line );
		}

		if ( $dryRun ) {
			WP_CLI::success( 'Dry run completed.' );
			return;
		}

		WP_CLI::success( 'Stripe marketing features imported successfully.' );
	}
}

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'khm import-stripe-marketing', ImportStripeMarketingCommand::class );
}
