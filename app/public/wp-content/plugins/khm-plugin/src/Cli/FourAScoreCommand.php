<?php

namespace KHM\Cli;

use KHM\Services\FourAScoringService;
use WP_CLI;
use WP_CLI_Command;

/**
 * WP-CLI integration for running the 4A scoring job.
 */
class FourAScoreCommand extends WP_CLI_Command {

	/**
	 * Recompute person & company scores.
	 *
	 * ## OPTIONS
	 *
	 * [--window=<seconds>]
	 * : Only actors/companies touched within this window (seconds) are recomputed.
	 * ---
	 * default: 7200
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp khm-4a recompute
	 *     wp khm-4a recompute --window=86400
	 *
	 * @param array $args Positional args.
	 * @param array $assoc_args Associative args.
	 */
	public function recompute( $args, $assoc_args ): void {
		$window = (int) ( $assoc_args['window'] ?? 7200 );
		if ( $window <= 0 ) {
			$window = 7200;
		}

		WP_CLI::log( sprintf( 'Running 4A scorer (window=%ds)...', $window ) );

		$service = new FourAScoringService();
		$result  = $service->run( $window );

		WP_CLI::success(
			sprintf(
				'Updated %d people, %d companies.',
				$result['actors'] ?? 0,
				$result['companies'] ?? 0
			)
		);
	}
}
