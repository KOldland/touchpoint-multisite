<?php
/**
 * Buyer Validation Service
 *
 * Handles buyer identity verification, RFP active-count cap (max 3),
 * and validation badge visibility.
 *
 * Validation statuses:
 *   unverified  – default, no badge shown
 *   pending     – buyer submitted verification docs, awaiting admin review
 *   verified    – admin approved, badge shown
 *   rejected    – admin rejected, cannot re-submit for 90 days
 */

namespace KHM\Connect;

use KHM\Migrations\ConnectWorkflowMigration;

defined( 'ABSPATH' ) || exit;

class ConnectBuyerValidationService {

	const MAX_ACTIVE_RFPS = 3;

	// ─── Validation status ─────────────────────────────────────────────────────

	/**
	 * Get a buyer's validation status from any of their opportunities.
	 * (Status is per buyer, not per opportunity.)
	 *
	 * @param int $buyer_account_id  WordPress user ID
	 * @return string  unverified|pending|verified|rejected
	 */
	public function get_status( int $buyer_account_id ): string {
		global $wpdb;

		$table = ConnectWorkflowMigration::opportunities_table_name();

		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT buyer_validation_status FROM `{$table}` WHERE buyer_account_id = %d ORDER BY id DESC LIMIT 1",
				$buyer_account_id
			)
		);

		return $status ?: 'unverified';
	}

	/**
	 * Update buyer validation status across all their opportunities.
	 * Also controls badge visibility.
	 *
	 * @param int    $buyer_account_id
	 * @param string $status  unverified|pending|verified|rejected
	 * @return bool
	 */
	public function set_status( int $buyer_account_id, string $status ): bool {
		global $wpdb;

		if ( ! in_array( $status, [ 'unverified', 'pending', 'verified', 'rejected' ], true ) ) {
			return false;
		}

		$table       = ConnectWorkflowMigration::opportunities_table_name();
		$badge_visible = ( 'verified' === $status ) ? 1 : 0;

		$result = $wpdb->update(
			$table,
			[
				'buyer_validation_status'      => $status,
				'buyer_validation_badge_visible' => $badge_visible,
			],
			[ 'buyer_account_id' => $buyer_account_id ],
			[ '%s', '%d' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	// ─── RFP cap ───────────────────────────────────────────────────────────────

	/**
	 * How many active RFPs does this buyer currently have?
	 *
	 * @param int $buyer_account_id
	 * @return int
	 */
	public function count_active_rfps( int $buyer_account_id ): int {
		global $wpdb;

		$table = ConnectWorkflowMigration::opportunities_table_name();

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE buyer_account_id = %d AND request_type = 'rfp_request' AND opportunity_status NOT IN ('closed', 'archived', 'expired')",
				$buyer_account_id
			)
		);

		return $count;
	}

	/**
	 * Check whether the buyer can open another RFP (cap = 3).
	 *
	 * @param int $buyer_account_id
	 * @return bool
	 */
	public function can_open_rfp( int $buyer_account_id ): bool {
		return $this->count_active_rfps( $buyer_account_id ) < self::MAX_ACTIVE_RFPS;
	}

	/**
	 * Increment the buyer's active RFP count and stamp rfp_created_at on
	 * the newly created opportunity.
	 *
	 * @param int $opportunity_id
	 * @param int $buyer_account_id
	 * @return bool
	 */
	public function record_new_rfp( int $opportunity_id, int $buyer_account_id ): bool {
		global $wpdb;

		$table = ConnectWorkflowMigration::opportunities_table_name();

		// Stamp this opportunity as RFP-owned by this buyer
		$wpdb->update(
			$table,
			[
				'buyer_account_id' => $buyer_account_id,
				'rfp_created_at'   => current_time( 'mysql' ),
			],
			[ 'id' => $opportunity_id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		// Sync the denormalised rfp_count_active on all of this buyer's rows
		$this->sync_rfp_count( $buyer_account_id );

		return true;
	}

	/**
	 * Decrement the active RFP count when an opportunity closes.
	 * Called after opportunity_status is updated to closed/archived/expired.
	 *
	 * @param int $buyer_account_id
	 * @return void
	 */
	public function on_rfp_closed( int $buyer_account_id ): void {
		$this->sync_rfp_count( $buyer_account_id );
	}

	// ─── Admin approval queue ──────────────────────────────────────────────────

	/**
	 * Return all buyer accounts with status = 'pending', ordered by oldest first.
	 *
	 * @return array<object>
	 */
	public function get_pending_approvals(): array {
		global $wpdb;

		$table = ConnectWorkflowMigration::opportunities_table_name();

		return $wpdb->get_results(
			"SELECT DISTINCT buyer_account_id, buyer_validation_status, MIN(created_at) as submitted_at
			 FROM `{$table}`
			 WHERE buyer_validation_status = 'pending'
			 AND buyer_account_id IS NOT NULL
			 GROUP BY buyer_account_id
			 ORDER BY submitted_at ASC"
		) ?: [];
	}

	// ─── Badge visibility ──────────────────────────────────────────────────────

	/**
	 * Is the buyer's validation badge visible to sellers?
	 *
	 * @param int $buyer_account_id
	 * @return bool
	 */
	public function badge_is_visible( int $buyer_account_id ): bool {
		global $wpdb;

		$table = ConnectWorkflowMigration::opportunities_table_name();

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT buyer_validation_badge_visible FROM `{$table}` WHERE buyer_account_id = %d LIMIT 1",
				$buyer_account_id
			)
		);
	}

	// ─── Internal ──────────────────────────────────────────────────────────────

	private function sync_rfp_count( int $buyer_account_id ): void {
		global $wpdb;

		$table = ConnectWorkflowMigration::opportunities_table_name();
		$count = $this->count_active_rfps( $buyer_account_id );

		$wpdb->update(
			$table,
			[ 'rfp_count_active' => $count ],
			[ 'buyer_account_id' => $buyer_account_id ],
			[ '%d' ],
			[ '%d' ]
		);
	}
}
