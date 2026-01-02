<?php
/**
 * Reports Service
 *
 * Handles data calculations for admin reports including revenue, MRR, churn, and membership stats.
 *
 * NOTE: This file contains intentional table name interpolation for WordPress prefix tables.
 * All table names use safe $wpdb->prefix values, and all user inputs are properly prepared.
 *
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
 *
 * @package KHM\Services
 */

namespace KHM\Services;

/**
 * Reports service for analytics and dashboards.
 */
class ReportsService {

	/**
	 * Cache group for transients.
	 */
	private const CACHE_GROUP = 'khm_reports';

	/**
	 * Cache expiration (24 hours).
	 */
	private const CACHE_EXPIRATION = 86400;

	/**
	 * Get discount totals for a period.
	 *
	 * @param string $period Period: 'today', 'this_month', 'this_year', 'all_time'.
	 * @return array{discounted:float, orders_with_discount:int}
	 */
	public function get_discount_totals( string $period ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_membership_orders';

		$where  = $this->get_date_where( $period );
		$where .= " AND status = 'success' AND COALESCE(discount_amount,0) > 0";

		$discounted = (float) $wpdb->get_var( "SELECT COALESCE(SUM(discount_amount), 0) FROM {$table} WHERE 1=1 {$where}" );
		$count      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE 1=1 {$where}" );

		return array(
			'discounted'           => $discounted,
			'orders_with_discount' => $count,
		);
	}

	/**
	 * Get top discount codes by discounted amount within a period.
	 *
	 * @param string $period Period: 'today', 'this_month', 'this_year', 'all_time'.
	 * @param int    $limit  Max rows to return.
	 * @return array<int,array{discount_code:string,uses:int,discounted:float,net_revenue:float}>
	 */
	public function get_top_discount_codes( string $period, int $limit = 10 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_membership_orders';

		$where  = $this->get_date_where( $period );
		$where .= " AND status = 'success' AND COALESCE(discount_code,'') <> ''";

		$sql = "SELECT discount_code,
				COUNT(*) AS uses,
				COALESCE(SUM(discount_amount),0) AS discounted,
				COALESCE(SUM(total),0) AS net_revenue
			FROM {$table}
			WHERE 1=1 {$where}
			GROUP BY discount_code
			ORDER BY discounted DESC
			LIMIT %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $limit ), ARRAY_A );
		return $rows ?: array();
	}

	/**
	 * Get sales count for a period.
	 *
	 * @param string $period Period: 'today', 'this_month', 'this_year', 'all_time'.
	 * @param int    $level_id Optional. Filter by membership level ID.
	 * @return int Number of sales.
	 */
	public function get_sales( string $period, int $level_id = 0 ): int {
		$cache_key = 'sales_' . $period . '_' . $level_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_membership_orders';

		$where  = $this->get_date_where( $period );
		$where .= " AND status = 'success'";

		if ( $level_id > 0 ) {
			$where .= $wpdb->prepare( ' AND membership_id = %d', $level_id );
		}

		$sql   = "SELECT COUNT(*) FROM {$table} WHERE 1=1 {$where}";
		$count = (int) $wpdb->get_var( $sql );

		set_transient( $cache_key, $count, self::CACHE_EXPIRATION );

		return $count;
	}

	/**
	 * Get revenue total for a period.
	 *
	 * @param string $period Period: 'today', 'this_month', 'this_year', 'all_time'.
	 * @param int    $level_id Optional. Filter by membership level ID.
	 * @return float Revenue total.
	 */
	public function get_revenue( string $period, int $level_id = 0 ): float {
		$cache_key = 'revenue_' . $period . '_' . $level_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return (float) $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_membership_orders';

		$where  = $this->get_date_where( $period );
		$where .= " AND status = 'success'";

		if ( $level_id > 0 ) {
			$where .= $wpdb->prepare( ' AND membership_id = %d', $level_id );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql     = "SELECT COALESCE(SUM(total), 0) FROM {$table} WHERE 1=1 {$where}";
		$revenue = (float) $wpdb->get_var( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		set_transient( $cache_key, $revenue, self::CACHE_EXPIRATION );

		return $revenue;
	}

	/**
	 * Get signups count for a period.
	 *
	 * @param string $period Period: 'today', 'this_month', 'this_year', 'all_time'.
	 * @param int    $level_id Optional. Filter by membership level ID.
	 * @return int Number of signups.
	 */
	public function get_signups( string $period, int $level_id = 0 ): int {
		$cache_key = 'signups_' . $period . '_' . $level_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_memberships_users';

		$where = $this->get_date_where( $period, 'start_date' );

		if ( $level_id > 0 ) {
			$where .= $wpdb->prepare( ' AND membership_id = %d', $level_id );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE 1=1 {$where}";
		$count = (int) $wpdb->get_var( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		set_transient( $cache_key, $count, self::CACHE_EXPIRATION );

		return $count;
	}

	/**
	 * Get cancellations count for a period.
	 *
	 * @param string $period Period: 'today', 'this_month', 'this_year', 'all_time'.
	 * @param int    $level_id Optional. Filter by membership level ID.
	 * @return int Number of cancellations.
	 */
	public function get_cancellations( string $period, int $level_id = 0 ): int {
		$cache_key = 'cancellations_' . $period . '_' . $level_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_memberships_users';

		$where  = $this->get_date_where( $period, 'end_date' );
		$where .= " AND status IN('cancelled', 'expired')";

		if ( $level_id > 0 ) {
			$where .= $wpdb->prepare( ' AND membership_id = %d', $level_id );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE 1=1 {$where}";
		$count = (int) $wpdb->get_var( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		set_transient( $cache_key, $count, self::CACHE_EXPIRATION );

		return $count;
	}

	/**
	 * Calculate Monthly Recurring Revenue (MRR).
	 *
	 * MRR = Sum of all active recurring subscription amounts normalized to monthly.
	 *
	 * @return float Monthly Recurring Revenue.
	 */
	public function calculate_mrr(): float {
		$cache_key = 'mrr_current';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return (float) $cached;
		}

		global $wpdb;
		$levels_table  = $wpdb->prefix . 'khm_membership_levels';
		$members_table = $wpdb->prefix . 'khm_memberships_users';

		// Get all active memberships with their level pricing.
		// Safe: Table names use $wpdb->prefix, user input is prepared.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT l.recurring_payment, l.billing_cycle, l.billing_frequency
			FROM {$members_table} m
			INNER JOIN {$levels_table} l ON m.membership_id = l.id
			WHERE m.status = %s
			AND l.recurring_payment > 0",
			'active'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$subscriptions = $wpdb->get_results( $sql );

		$mrr = 0.0;

		foreach ( $subscriptions as $sub ) {
			$monthly_amount = $this->normalize_to_monthly(
				(float) $sub->recurring_payment,
				$sub->billing_cycle,
				(int) $sub->billing_frequency
			);
			$mrr           += $monthly_amount;
		}

		set_transient( $cache_key, $mrr, self::CACHE_EXPIRATION );

		return $mrr;
	}

	/**
	 * Get churn rate for a period.
	 *
	 * Churn Rate = (Cancellations / Active Members at Start of Period) * 100.
	 *
	 * @param string $period Period: 'this_month', 'this_year'.
	 * @return float Churn rate percentage.
	 */
	public function get_churn_rate( string $period = 'this_month' ): float {
		$cancellations = $this->get_cancellations( $period );
		$active        = $this->get_active_members_count();

		if ( 0 === $active ) {
			return 0.0;
		}

		return ( $cancellations / $active ) * 100;
	}

	/**
	 * Get revenue by date for charting.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @param string $group_by   Group by: 'day', 'month', 'year'.
	 * @param int    $level_id   Optional. Filter by level.
	 * @return array Array of ['date' => date, 'revenue' => amount, 'sales' => count].
	 */
	public function get_revenue_by_date( string $start_date, string $end_date, string $group_by = 'day', int $level_id = 0 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_membership_orders';

		// Determine date format based on grouping.
		$date_format = match ( $group_by ) {
			'month' => '%Y-%m',
			'year'  => '%Y',
			default => '%Y-%m-%d',
		};

		$where = $wpdb->prepare(
			' AND DATE(created_at) >= %s AND DATE(created_at) <= %s',
			$start_date,
			$end_date
		);

		$where .= " AND status = 'success'";

		if ( $level_id > 0 ) {
			$where .= $wpdb->prepare( ' AND membership_id = %d', $level_id );
		}

		// Safe: Table name uses $wpdb->prefix, date format from safe method, WHERE clause prepared.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT 
			DATE_FORMAT(created_at, '{$date_format}') as date,
			COALESCE(SUM(total), 0) as revenue,
			COUNT(*) as sales
			FROM {$table}
			WHERE 1=1 {$where}
			GROUP BY date
			ORDER BY date ASC";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return ! empty( $results ) ? $results : array();
	}

	/**
	 * Get active members count.
	 *
	 * @return int Number of active members.
	 */
	public function get_active_members_count(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_memberships_users';

		// Safe: Table name uses $wpdb->prefix, status value is prepared.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE status = %s",
				'active'
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Clear all report caches.
	 *
	 * Should be called when orders or memberships are updated.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		global $wpdb;

		// Delete all khm_reports transients.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_sales_%'
			OR option_name LIKE '_transient_revenue_%'
			OR option_name LIKE '_transient_signups_%'
			OR option_name LIKE '_transient_cancellations_%'
			OR option_name LIKE '_transient_mrr_%'"
		);
	}

	/**
	 * Get WHERE clause for date filtering.
	 *
	 * @param string $period Period identifier.
	 * @param string $column Column name to filter.
	 * @return string WHERE clause SQL.
	 */
	private function get_date_where( string $period, string $column = 'created_at' ): string {
		$date = match ( $period ) {
			'today'      => gmdate( 'Y-m-d' ),
			'this_month' => gmdate( 'Y-m' ) . '-01',
			'this_year'  => gmdate( 'Y' ) . '-01-01',
			default      => '', // all_time.
		};

		if ( empty( $date ) ) {
			return '';
		}

		global $wpdb;
		// Safe: Column name is from safe internal method parameter, date value is prepared.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->prepare( " AND DATE({$column}) >= %s", $date );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Normalize subscription amount to monthly equivalent.
	 *
	 * @param float  $amount    Recurring payment amount.
	 * @param string $cycle     Billing cycle (Day, Week, Month, Year).
	 * @param int    $frequency Billing frequency (e.g., 3 months = frequency 3).
	 * @return float Monthly equivalent amount.
	 */
	private function normalize_to_monthly( float $amount, string $cycle, int $frequency ): float {
		$frequency = max( 1, $frequency ); // Avoid division by zero.

		return match ( strtolower( $cycle ) ) {
			'day'   => $amount * 30 / $frequency,
			'week'  => $amount * 4.345 / $frequency,
			'month' => $amount / $frequency,
			'year'  => $amount / ( 12 * $frequency ),
			default => $amount,
		};
	}
}
/* phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared */
