<?php
/**
 * WP-CLI command to inspect Stripe marketing import audit rows.
 *
 * @package KHM\CLI
 */

namespace KHM\CLI;

use WP_CLI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class StripeMarketingAuditCommand {

	/**
	 * Show recent Stripe marketing import audit entries.
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
	 * [--status=<status>]
	 * : Filter by status (imported|skipped|error|resolved).
	 *
	 * [--format=<format>]
	 * : Output format: table, csv, json, yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp khm stripe-marketing-audit --last=50
	 *     wp khm stripe-marketing-audit --product=prod_123 --status=error --last=20
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'khm_stripe_marketing_import_audit';
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);

		if ( $exists !== $table ) {
			WP_CLI::warning( 'Stripe marketing audit table does not exist yet.' );
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

		if ( ! empty( $assoc_args['status'] ) ) {
			$where[] = 'status = %s';
			$params[] = sanitize_key( (string) $assoc_args['status'] );
		}

		$sql = "SELECT id, created_at, product_id, level_id, source, status, dry_run, lines_count, skipped_reason, duration_ms, message, content_hash FROM {$table}";
		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY id DESC LIMIT %d';
		$params[] = $last;

		$prepared = $wpdb->prepare( $sql, ...$params );
		$rows = $wpdb->get_results( $prepared, ARRAY_A );
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			WP_CLI::warning( 'No matching audit rows found.' );
			return;
		}

		$items = array_map(
			static function ( array $row ): array {
				return [
					'id' => (int) ( $row['id'] ?? 0 ),
					'created_at' => (string) ( $row['created_at'] ?? '' ),
					'product_id' => (string) ( $row['product_id'] ?? '' ),
					'level_id' => isset( $row['level_id'] ) ? (int) $row['level_id'] : 0,
					'source' => (string) ( $row['source'] ?? '' ),
					'status' => (string) ( $row['status'] ?? '' ),
					'dry_run' => ! empty( $row['dry_run'] ) ? 1 : 0,
					'lines' => (int) ( $row['lines_count'] ?? 0 ),
					'skipped' => (string) ( $row['skipped_reason'] ?? '' ),
					'ms' => (int) ( $row['duration_ms'] ?? 0 ),
					'message' => (string) ( $row['message'] ?? '' ),
					'hash' => (string) ( $row['content_hash'] ?? '' ),
				];
			},
			$rows
		);

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		$fields = [ 'id', 'created_at', 'product_id', 'level_id', 'source', 'status', 'dry_run', 'lines', 'skipped', 'ms', 'message', 'hash' ];
		\WP_CLI\Utils\format_items( $format, $items, $fields );
	}
}

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'khm stripe-marketing-audit', StripeMarketingAuditCommand::class );
}
