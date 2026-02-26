<?php
/**
 * WP-CLI command for full Stripe -> Level mirror import.
 *
 * @package KHM\CLI
 */

namespace KHM\CLI;

use KHM\Services\StripeLevelMirrorImporter;
use KHM\Services\StripeMarketingImporter;
use WP_CLI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class ImportStripeLevelMirrorCommand {

	private StripeLevelMirrorImporter $importer;

	public function __construct( ?StripeLevelMirrorImporter $importer = null ) {
		$this->importer = $importer ?: new StripeLevelMirrorImporter();
	}

	/**
	 * Mirror a Stripe product into a KHM membership level (full mapping scaffold).
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
	 * : Force dry-run mode (default behavior).
	 *
	 * [--apply]
	 * : Persist changes. Without this flag, command runs in dry-run mode.
	 *
	 * ## EXAMPLES
	 *
	 *     wp khm import-stripe-level-mirror prod_XXXXX
	 *     wp khm import-stripe-level-mirror prod_XXXXX --level=10
	 *     wp khm import-stripe-level-mirror prod_XXXXX --level=10 --apply
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
		$apply = \WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );
		$dryRun = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) || ! $apply;

		if ( $dryRun ) {
			WP_CLI::line( WP_CLI::colorize( '%Y[DRY RUN]%n Full mirror payload will be resolved but not saved.' ) );
		}

		try {
			$result = $this->importer->importProductToLevel( $productId, $levelId, (bool) $dryRun, 'cli_mirror' );
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
			return;
		}

		$resolved = isset( $result['resolved'] ) && is_array( $result['resolved'] ) ? $result['resolved'] : [];
		$metaPayload = isset( $result['meta_payload'] ) && is_array( $result['meta_payload'] ) ? $result['meta_payload'] : [];
		$lines = [];
		if ( isset( $metaPayload['presentation']['marketing_features'] ) && is_array( $metaPayload['presentation']['marketing_features'] ) ) {
			$lines = $metaPayload['presentation']['marketing_features'];
		}

		WP_CLI::line( sprintf( 'Level: %d', (int) ( $result['level_id'] ?? 0 ) ) );
		WP_CLI::line( sprintf( 'Created: %s', ! empty( $result['created'] ) ? 'yes' : 'no' ) );
		WP_CLI::line( sprintf( 'Changed: %s', ! empty( $result['changed'] ) ? 'yes' : 'no' ) );
		WP_CLI::line( sprintf( 'Marketing lines: %d', count( $lines ) ) );
		if ( isset( $resolved['primary_price_id'] ) ) {
			WP_CLI::line( sprintf( 'Primary price: %s', (string) $resolved['primary_price_id'] ) );
		}
		if ( isset( $resolved['primary_price_amount'] ) ) {
			WP_CLI::line( sprintf( 'Primary amount: %s', (string) $resolved['primary_price_amount'] ) );
		}
		if ( isset( $resolved['primary_price_interval'] ) ) {
			WP_CLI::line( sprintf( 'Primary interval: %s', (string) $resolved['primary_price_interval'] ) );
		}

		if ( $dryRun ) {
			WP_CLI::success( 'Dry run completed.' );
			return;
		}

		WP_CLI::success( 'Stripe level mirror import completed.' );
	}
}

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'khm import-stripe-level-mirror', ImportStripeLevelMirrorCommand::class );
}

