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

		error_log( sprintf( '[KHM Adverts] Schedule check complete. Expired: %d.', count( $expired ) ) );
	}
}
