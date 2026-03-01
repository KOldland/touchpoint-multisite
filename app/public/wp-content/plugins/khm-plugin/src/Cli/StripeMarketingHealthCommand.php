<?php
/**
 * WP-CLI command to show Stripe marketing sync health metrics.
 *
 * @package KHM\CLI
 */

namespace KHM\CLI;

use WP_CLI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class StripeMarketingHealthCommand {

	/**
	 * Show queue, lock, audit, and dead-letter health signals.
	 *
	 * ## OPTIONS
	 *
	 * [--hours=<n>]
	 * : Lookback window in hours for audit/dead-letter metrics.
	 * ---
	 * default: 24
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format: table, json, yaml, csv.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp khm stripe-marketing-health
	 *     wp khm stripe-marketing-health --hours=48 --format=json
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		global $wpdb;

		$hours = isset( $assoc_args['hours'] ) ? (int) $assoc_args['hours'] : 24;
		$hours = max( 1, min( 720, $hours ) );
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $hours * HOUR_IN_SECONDS ) );

		$metrics = [];
		$metrics[] = [ 'metric' => 'window_hours', 'value' => (string) $hours ];
		$metrics[] = [ 'metric' => 'max_attempts', 'value' => (string) max( 1, (int) get_option( 'khm_stripe_marketing_import_max_attempts', 3 ) ) ];

		$queue = $this->collectQueueMetrics();
		$metrics[] = [ 'metric' => 'queue_backlog', 'value' => (string) $queue['count'] ];
		$metrics[] = [ 'metric' => 'queue_oldest_eta_utc', 'value' => $queue['oldest_eta'] ];
		$metrics[] = [ 'metric' => 'queue_max_attempt_seen', 'value' => (string) $queue['max_attempt'] ];

		$lockCounts = $this->collectLockMetrics();
		$metrics[] = [ 'metric' => 'active_import_locks', 'value' => (string) $lockCounts['import'] ];
		$metrics[] = [ 'metric' => 'active_queue_locks', 'value' => (string) $lockCounts['queue'] ];

		$auditTable = $wpdb->prefix . 'khm_stripe_marketing_import_audit';
		if ( $this->tableExists( $auditTable ) ) {
			$statuses = [ 'imported', 'skipped', 'error', 'resolved' ];
			foreach ( $statuses as $status ) {
				$count = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$auditTable} WHERE created_at >= %s AND status = %s",
						$since,
						$status
					)
				);
				$metrics[] = [ 'metric' => 'audit_' . $status, 'value' => (string) $count ];
			}
		} else {
			$metrics[] = [ 'metric' => 'audit_table', 'value' => 'missing' ];
		}

		$deadTable = $wpdb->prefix . 'khm_stripe_marketing_import_dead_letters';
		if ( $this->tableExists( $deadTable ) ) {
			$unresolved = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$deadTable} WHERE resolved_at IS NULL" );
			$recent = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$deadTable} WHERE created_at >= %s",
					$since
				)
			);
			$oldest = (string) $wpdb->get_var( "SELECT created_at FROM {$deadTable} WHERE resolved_at IS NULL ORDER BY created_at ASC LIMIT 1" );
			$metrics[] = [ 'metric' => 'dead_letters_unresolved', 'value' => (string) $unresolved ];
			$metrics[] = [ 'metric' => 'dead_letters_recent', 'value' => (string) $recent ];
			$metrics[] = [ 'metric' => 'dead_letters_oldest_unresolved_utc', 'value' => $oldest !== '' ? $oldest : '-' ];
		} else {
			$metrics[] = [ 'metric' => 'dead_letter_table', 'value' => 'missing' ];
		}

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		\WP_CLI\Utils\format_items( $format, $metrics, [ 'metric', 'value' ] );
	}

	private function tableExists( string $table ): bool {
		global $wpdb;
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);

		return $exists === $table;
	}

	/**
	 * @return array{count:int,oldest_eta:string,max_attempt:int}
	 */
	private function collectQueueMetrics(): array {
		$cron = get_option( 'cron', [] );
		if ( ! is_array( $cron ) ) {
			return [
				'count' => 0,
				'oldest_eta' => '-',
				'max_attempt' => 0,
			];
		}

		$count = 0;
		$oldest = null;
		$maxAttempt = 0;
		$hook = 'khm_import_stripe_marketing_product_updated';

		foreach ( $cron as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) || ! isset( $hooks[ $hook ] ) || ! is_array( $hooks[ $hook ] ) ) {
				continue;
			}
			foreach ( $hooks[ $hook ] as $event ) {
				$count++;
				$ts = (int) $timestamp;
				if ( $oldest === null || $ts < $oldest ) {
					$oldest = $ts;
				}
				$eventArgs = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : [];
				$attempt = isset( $eventArgs[2] ) ? (int) $eventArgs[2] : 0;
				if ( $attempt > $maxAttempt ) {
					$maxAttempt = $attempt;
				}
			}
		}

		return [
			'count' => $count,
			'oldest_eta' => $oldest ? gmdate( 'Y-m-d H:i:s', $oldest ) : '-',
			'max_attempt' => $maxAttempt,
		];
	}

	/**
	 * @return array{import:int,queue:int}
	 */
	private function collectLockMetrics(): array {
		global $wpdb;
		$optionsTable = $wpdb->options;

		$importCount = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$optionsTable} WHERE option_name LIKE %s",
				'_transient_khm_stripe_marketing_import_lock_%'
			)
		);
		$queueCount = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$optionsTable} WHERE option_name LIKE %s",
				'_transient_khm_stripe_marketing_queue_lock_%'
			)
		);

		return [
			'import' => $importCount,
			'queue' => $queueCount,
		];
	}
}

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'khm stripe-marketing-health', StripeMarketingHealthCommand::class );
}
