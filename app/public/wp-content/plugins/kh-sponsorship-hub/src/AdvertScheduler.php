<?php

namespace KHM\Sponsors;

/**
 * Handles scheduled auto-pausing of expired adverts and auto-activating
 * of scheduled adverts whose start_date has arrived.
 *
 * WP cron hook: khm_advert_schedule_check (runs hourly).
 */
class AdvertScheduler {

	const CRON_HOOK = 'khm_advert_schedule_check';

	public function register(): void {
		add_action( self::CRON_HOOK, [ $this, 'run' ] );
		add_action( 'init', [ $this, 'schedule' ] );
	}

	public function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}

	public static function deactivate(): void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	/**
	 * Runs on cron: auto-pause expired adverts; auto-activate scheduled start dates.
	 */
	public function run(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_adverts';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return;
		}

		$now = current_time( 'mysql', true );

		// Auto-pause approved adverts whose end_date has passed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$expired = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id, title FROM `{$table}` WHERE status = 'approved' AND end_date IS NOT NULL AND end_date <= %s",
				$now
			),
			ARRAY_A
		);

		foreach ( $expired as $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update(
				$table,
				[ 'status' => 'paused', 'updated_at' => $now ],
				[ 'id' => $row['id'] ]
			);
			// Notify sponsor.
			$user = get_user_by( 'id', $row['user_id'] );
			if ( $user ) {
				wp_mail(
					$user->user_email,
					'[QuoteClub] Your advert has expired',
					sprintf(
						"Your advert \"%s\" has reached its end date and has been paused.\n\nLog in to QuoteClub to extend or resubmit it.",
						$row['title']
					)
				);
			}
			error_log( sprintf( '[KHM Adverts] Auto-paused expired advert ID %d.', $row['id'] ) );
		}

		// Auto-approve (restore to approved) adverts that were pending/paused and whose start_date has arrived.
		// Only re-activates if the advert had previously been approved (we can't auto-approve fresh submissions).
		// We use a dedicated status 'scheduled' for this; adverts approved with a future start_date
		// are stored as approved but will only serve once start_date <= NOW inside serve_advert.
		// Nothing to do here beyond the serve-time gate — handled by SponsorAdvertController::serve_advert().

		// A/B weight auto-adjustment: for each placement, compute the average CTR of approved adverts
		// that have ≥100 impressions and nudge weight up (max 10) or down (min 1) by 1 step.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$placements = $wpdb->get_col( "SELECT DISTINCT placement FROM `{$table}` WHERE status = 'approved'" );
		$adjusted   = 0;
		foreach ( $placements as $placement ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$adverts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, impressions, clicks, weight FROM `{$table}` WHERE status = 'approved' AND placement = %s AND impressions >= 100",
					$placement
				),
				ARRAY_A
			);
			if ( empty( $adverts ) ) {
				continue;
			}
			// Compute placement average CTR.
			$total_imp = array_sum( array_column( $adverts, 'impressions' ) );
			$total_clk = array_sum( array_column( $adverts, 'clicks' ) );
			$avg_ctr   = $total_imp > 0 ? $total_clk / $total_imp : 0.0;

			foreach ( $adverts as $ad ) {
				$ad_ctr    = $ad['impressions'] > 0 ? $ad['clicks'] / $ad['impressions'] : 0.0;
				$new_weight = (int) $ad['weight'];
				if ( $ad_ctr > $avg_ctr * 1.1 ) {
					// Performing ≥10% above average — increase weight.
					$new_weight = min( 10, $new_weight + 1 );
				} elseif ( $ad_ctr < $avg_ctr * 0.9 && $avg_ctr > 0 ) {
					// Performing ≥10% below average — decrease weight.
					$new_weight = max( 1, $new_weight - 1 );
				}
				if ( $new_weight !== (int) $ad['weight'] ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->update( $table, [ 'weight' => $new_weight ], [ 'id' => $ad['id'] ], [ '%d' ], [ '%d' ] );
					$adjusted++;
				}
			}
		}

		error_log( sprintf( '[KHM Adverts] Schedule check complete. Expired: %d. Weight adjustments: %d.', count( $expired ), $adjusted ) );
	}
}
