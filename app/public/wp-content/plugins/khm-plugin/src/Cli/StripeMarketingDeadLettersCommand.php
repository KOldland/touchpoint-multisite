<?php
/**
 * WP-CLI command to inspect Stripe marketing dead-letter rows.
 *
 * @package KHM\CLI
 */

namespace KHM\CLI;

use WP_CLI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class StripeMarketingDeadLettersCommand {

	/**
	 * Show recent Stripe marketing dead-letter entries.
	 *
	 * ## OPTIONS
	 *
	 * [--last=<n>]
	 * : Number of rows to return.
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--product=<product_id>]
	 * : Filter by Stripe product ID.
	 *
	 * [--level=<level_id>]
	 * : Filter by level ID.
	 *
	 * [--source=<source>]
	 * : Filter by source (default: webhook).
	 *
	 * [--resolved=<0|1>]
	 * : Filter by resolved status (1 = resolved, 0 = unresolved).
	 *
	 * [--format=<format>]
	 * : Output format: table, csv, json, yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp khm stripe-marketing-dead-letters --last=50
	 *     wp khm stripe-marketing-dead-letters --resolved=0 --format=json
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'khm_stripe_marketing_import_dead_letters';
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);

		if ( $exists !== $table ) {
			WP_CLI::warning( 'Stripe marketing dead-letter table does not exist yet.' );
			return;
		}

		$last = isset( $assoc_args['last'] ) ? (int) $assoc_args['last'] : 50;
		$last = max( 1, min( 500, $last ) );

		$where = [];
		$params = [];

		if ( ! empty( $assoc_args['product'] ) ) {
			$where[] = 'product_id = %s';
			$params[] = sanitize_text_field( (string) $assoc_args['product'] );
		}

		if ( ! empty( $assoc_args['level'] ) ) {
			$where[] = 'level_id = %d';
			$params[] = (int) $assoc_args['level'];
		}

		if ( ! empty( $assoc_args['source'] ) ) {
			$where[] = 'source = %s';
			$params[] = sanitize_key( (string) $assoc_args['source'] );
		}

		if ( isset( $assoc_args['resolved'] ) ) {
			$resolved = (int) $assoc_args['resolved'];
			if ( $resolved === 1 ) {
				$where[] = 'resolved_at IS NOT NULL';
			} elseif ( $resolved === 0 ) {
				$where[] = 'resolved_at IS NULL';
			}
		}

		$sql = "SELECT id, created_at, resolved_at, product_id, level_id, source, attempts, error_message FROM {$table}";
		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY id DESC LIMIT %d';
		$params[] = $last;

		$prepared = $wpdb->prepare( $sql, ...$params );
		$rows = $wpdb->get_results( $prepared, ARRAY_A );
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			WP_CLI::warning( 'No matching dead-letter rows found.' );
			return;
		}

		$items = array_map(
			static function ( array $row ): array {
				return [
					'id' => (int) ( $row['id'] ?? 0 ),
					'created_at' => (string) ( $row['created_at'] ?? '' ),
					'resolved_at' => (string) ( $row['resolved_at'] ?? '' ),
					'product_id' => (string) ( $row['product_id'] ?? '' ),
					'level_id' => isset( $row['level_id'] ) ? (int) $row['level_id'] : 0,
					'source' => (string) ( $row['source'] ?? '' ),
					'attempts' => (int) ( $row['attempts'] ?? 0 ),
					'error' => (string) ( $row['error_message'] ?? '' ),
				];
			},
			$rows
		);

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		$fields = [ 'id', 'created_at', 'resolved_at', 'product_id', 'level_id', 'source', 'attempts', 'error' ];
		\WP_CLI\Utils\format_items( $format, $items, $fields );
	}
}

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'khm stripe-marketing-dead-letters', StripeMarketingDeadLettersCommand::class );
}
