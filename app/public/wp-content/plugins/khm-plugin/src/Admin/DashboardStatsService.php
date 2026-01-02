<?php

namespace KHM\Admin;

/**
 * DashboardStatsService
 *
 * Provides quick metrics for the admin dashboard.
 */
class DashboardStatsService {

	/**
	 * Retrieve dashboard statistics.
	 *
	 * @return array<string,mixed>
	 */
	public function get_stats(): array {
		global $wpdb;

		$memberships_table = $wpdb->prefix . 'khm_memberships_users';
		$orders_table      = $wpdb->prefix . 'khm_membership_orders';

		$active_members = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$memberships_table} WHERE status = 'active'"
		);

		$new_members_7 = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$memberships_table} WHERE startdate >= %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
			)
		);

		$revenue_30 = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(total),0) FROM {$orders_table} WHERE status = 'success' AND timestamp >= %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			)
		);

		$pending_orders = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$orders_table} WHERE status = 'pending'"
		);

		$failed_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table} WHERE status = 'failed' AND timestamp >= %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
			)
		);

		return [
			'active_members'  => $active_members,
			'new_members_7'   => $new_members_7,
			'revenue_30'      => round( $revenue_30, 2 ),
			'pending_orders'  => $pending_orders,
			'failed_week'     => $failed_week,
		];
	}
}
