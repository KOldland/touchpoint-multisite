<?php
/**
 * Connect Subscription Expiry Worker
 *
 * Daily WP-Cron job that expires cancelled or lapsed per-site subscriptions.
 *
 * Flow:
 *  1. Fetch all users who have `khm_connect_site_subscriptions` user meta.
 *  2. For each user, check each site entry:
 *     - Status is 'cancelled' (or 'active') AND expires_at <= NOW().
 *  3. Set status to 'expired'.
 *  4. Remove any `connect_providers` rows for the affected site / sponsor.
 *  5. Fire `khm_connect_site_subscription_expired` action.
 *
 * @package KHM\Cron
 */

namespace KHM\Cron;

use KHM\Connect\ConnectSubscriptionEndpoint;

defined( 'ABSPATH' ) || exit;

class ConnectSubscriptionExpiryWorker {

	public const HOOK = 'khm_connect_subscription_expiry_daily';

	public function register(): void {
		add_action( 'init',     [ $this, 'schedule' ] );
		add_action( self::HOOK, [ $this, 'run' ] );
	}

	public function schedule(): void {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// 04:00 AM — after commission worker at 03:00.
			wp_schedule_event( strtotime( 'tomorrow 04:00:00' ), 'daily', self::HOOK );
		}
	}

	/**
	 * Main cron callback.
	 *
	 * @return array{ expired: int, skipped: int }
	 */
	public function run(): array {
		global $wpdb;

		$expired = 0;
		$skipped = 0;
		$now_str = current_time( 'mysql', true );

		// Find all users with site subscription meta.
		$user_ids = $wpdb->get_col(
			"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'khm_connect_site_subscriptions'"
		);

		if ( empty( $user_ids ) ) {
			return [ 'expired' => 0, 'skipped' => 0 ];
		}

		// Pre-build: slug -> blog_id mapping via wp_blogs.
		$blog_path_to_slug = [];
		foreach ( ConnectSubscriptionEndpoint::SITE_BLOG_SLUGS as $slug => $paths ) {
			foreach ( $paths as $p ) {
				$blog_path_to_slug[ $p ] = $slug;
			}
		}

		$slug_to_blog_ids = $this->build_slug_to_blog_ids( $blog_path_to_slug );

		foreach ( $user_ids as $raw_user_id ) {
			$user_id = (int) $raw_user_id;

			// Find the sponsor_id for connect_providers lookup.
			$sponsor_id = (int) get_user_meta( $user_id, 'khm_sponsor_id', true );

			$site_subs = ConnectSubscriptionEndpoint::get_site_subs( $user_id );
			$changed   = false;

			foreach ( $site_subs as $slug => $sub ) {
				$status     = (string) ( $sub['status'] ?? '' );
				$expires_at = (string) ( $sub['expires_at'] ?? '' );

				// Only process non-expired entries that have actually expired.
				if ( $status === 'expired' || $status === 'inactive' || $status === 'pending' ) {
					$skipped++;
					continue;
				}
				if ( ! $expires_at || strtotime( $expires_at ) > strtotime( $now_str ) ) {
					$skipped++;
					continue;
				}

				// Mark as expired.
				$site_subs[ $slug ]['status']     = 'expired';
				$site_subs[ $slug ]['expired_at'] = $now_str;
				$changed = true;
				$expired++;

				// Remove connect_providers rows for this sponsor + blog(s).
				if ( $sponsor_id > 0 && isset( $slug_to_blog_ids[ $slug ] ) ) {
					$this->remove_provider_access( $sponsor_id, $slug_to_blog_ids[ $slug ] );
				}

				do_action( 'khm_connect_site_subscription_expired', $user_id, $slug, $sub );

				error_log( sprintf(
					'[KHM Expiry] Site subscription expired for user %d, site %s (was: %s, expires_at: %s)',
					$user_id, $slug, $status, $expires_at
				) );
			}

			if ( $changed ) {
				update_user_meta( $user_id, 'khm_connect_site_subscriptions', $site_subs );
			}
		}

		return [ 'expired' => $expired, 'skipped' => $skipped ];
	}

	/** Remove connect_providers access for a sponsor on given blog IDs. */
	private function remove_provider_access( int $sponsor_id, array $blog_ids ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'connect_providers';

		// Check the table exists before touching it.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			return;
		}

		foreach ( $blog_ids as $blog_id ) {
			$wpdb->update(
				$table,
				[ 'status' => 'expired' ],
				[ 'sponsor_id' => $sponsor_id, 'blog_id' => (int) $blog_id, 'status' => 'active' ],
				[ '%s' ],
				[ '%d', '%d', '%s' ]
			);
		}
	}

	/** Build { [slug]: [blog_id, ...] } from wp_blogs paths. */
	private function build_slug_to_blog_ids( array $blog_path_to_slug ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT blog_id, path FROM {$wpdb->blogs} WHERE blog_id > 1",
			ARRAY_A
		);

		$map = [];
		foreach ( (array) $rows as $row ) {
			$path_slug = trim( (string) ( $row['path'] ?? '' ), '/' );
			if ( isset( $blog_path_to_slug[ $path_slug ] ) ) {
				$site_slug = $blog_path_to_slug[ $path_slug ];
				$map[ $site_slug ][] = (int) $row['blog_id'];
			}
		}

		return $map;
	}
}
