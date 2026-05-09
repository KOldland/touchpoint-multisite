<?php
/**
 * RFP Upsell Worker (Phase G)
 *
 * Daily WP-Cron job that finds RFP opportunities created ~30 days ago
 * and fires the upsell hook so email listeners can send a "How did it go?"
 * + upgrade prompt to the buyer.
 *
 * Safe to run multiple times — each opportunity is stamped with
 * rfp_upsell_sent_at on first send and skipped on subsequent runs.
 *
 * Register via: ( new ConnectRFPUpsellWorker() )->register();
 */

namespace KHM\Cron;

use KHM\Migrations\ConnectWorkflowMigration;

defined( 'ABSPATH' ) || exit;

class ConnectRFPUpsellWorker {

	public const HOOK            = 'khm_rfp_upsell_daily';
	public const WINDOW_DAYS_MIN = 28;
	public const WINDOW_DAYS_MAX = 32;
	public const CHUNK_SIZE      = 50;

	public function register(): void {
		add_action( 'init',       [ $this, 'schedule' ] );
		add_action( self::HOOK,   [ $this, 'run' ] );
	}

	public function schedule(): void {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// Offset by 2 hours from midnight so it doesn't compete with other daily jobs
			wp_schedule_event( strtotime( 'tomorrow 02:00:00' ), 'daily', self::HOOK );
		}
	}

	/**
	 * Main cron callback.
	 *
	 * Finds all RFP opportunities where:
	 *   - request_type = 'rfp_request'
	 *   - rfp_created_at is between (now - 32 days) and (now - 28 days)
	 *   - rfp_upsell_sent_at IS NULL  (not yet processed)
	 *
	 * For each, fires khm_rfp_upsell_trigger then stamps rfp_upsell_sent_at.
	 *
	 * @return array{ processed: int, skipped: int }
	 */
	public function run(): array {
		global $wpdb;

		$table = ConnectWorkflowMigration::opportunities_table_name();

		$min_cutoff = gmdate( 'Y-m-d H:i:s', strtotime( sprintf( '-%d days', self::WINDOW_DAYS_MAX ) ) );
		$max_cutoff = gmdate( 'Y-m-d H:i:s', strtotime( sprintf( '-%d days', self::WINDOW_DAYS_MIN ) ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, buyer_account_id, sponsor_id, rfp_created_at
			 FROM `{$table}`
			 WHERE request_type = 'rfp_request'
			   AND rfp_created_at BETWEEN %s AND %s
			   AND rfp_upsell_sent_at IS NULL
			 ORDER BY rfp_created_at ASC
			 LIMIT %d",
			$min_cutoff,
			$max_cutoff,
			self::CHUNK_SIZE
		) );

		if ( empty( $rows ) ) {
			return [ 'processed' => 0, 'skipped' => 0 ];
		}

		$processed = 0;
		$skipped   = 0;

		foreach ( $rows as $row ) {
			$opportunity_id  = (int) $row->id;
			$buyer_account_id = (int) ( $row->buyer_account_id ?: $row->sponsor_id );

			// Stamp first so a fatal inside the action doesn't retrigger
			$wpdb->update(
				$table,
				[ 'rfp_upsell_sent_at' => current_time( 'mysql' ) ],
				[ 'id' => $opportunity_id ],
				[ '%s' ],
				[ '%d' ]
			);

			if ( $wpdb->rows_affected > 0 ) {
				/**
				 * Fires when an RFP opportunity is ~30 days old.
				 * Listeners should send the buyer an upsell / experience survey email.
				 *
				 * @param int $opportunity_id
				 * @param int $buyer_account_id  WordPress user ID (may be sponsor_id if no account linked)
				 */
				do_action( 'khm_rfp_upsell_trigger', $opportunity_id, $buyer_account_id );
				$processed++;
			} else {
				$skipped++;
			}
		}

		return [ 'processed' => $processed, 'skipped' => $skipped ];
	}
}
