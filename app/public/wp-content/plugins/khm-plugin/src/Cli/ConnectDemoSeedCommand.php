<?php

namespace KHM\CLI;

use KHM\Seeds\ConnectDemoSeeder;
use WP_CLI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class ConnectDemoSeedCommand {

	/**
	 * Seed real demo Connect provider/thread data into the database.
	 *
	 * ## OPTIONS
	 *
	 * [--sponsor_id=<id>]
	 * : Optional sponsor ID to attach demo provider and threads to.
	 *
	 * ## EXAMPLES
	 *
	 *     wp khm connect demo-seed
	 *     wp khm connect demo-seed --sponsor_id=12
	 *
	 * @when after_wp_load
	 */
	public function demo_seed( array $args, array $assoc_args ): void {
		$sponsor_id = isset( $assoc_args['sponsor_id'] ) ? (int) $assoc_args['sponsor_id'] : 0;
		$seeder = new ConnectDemoSeeder();
		$result = $seeder->seed( $sponsor_id );

		if ( empty( $result['success'] ) ) {
			WP_CLI::error( (string) ( $result['error'] ?? 'Demo seeding failed.' ) );
		}

		WP_CLI::line( sprintf( 'Sponsor ID: %d', (int) ( $result['sponsor_id'] ?? 0 ) ) );
		WP_CLI::line( sprintf( 'Demo provider ID: %d', (int) ( $result['provider_id'] ?? 0 ) ) );
		WP_CLI::line( sprintf( 'Seeded demo threads: %d', (int) ( $result['seeded_thread_count'] ?? 0 ) ) );

		$thread_ids = isset( $result['seeded_thread_ids'] ) && is_array( $result['seeded_thread_ids'] ) ? $result['seeded_thread_ids'] : array();
		if ( ! empty( $thread_ids ) ) {
			WP_CLI::line( 'Thread IDs: ' . implode( ', ', array_map( 'intval', $thread_ids ) ) );
		}

		WP_CLI::success( 'Connect demo data seeded.' );
	}
}
