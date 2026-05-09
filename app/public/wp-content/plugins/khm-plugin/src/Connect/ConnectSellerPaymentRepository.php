<?php
/**
 * Seller Payment Profile Repository
 *
 * Manages Stripe Customer pre-registration data for sellers and
 * tracks monthly spend limits/usage for commission enforcement.
 */

namespace KHM\Connect;

use KHM\Migrations\CreateSellerPaymentProfilesTable;

defined( 'ABSPATH' ) || exit;

class ConnectSellerPaymentRepository {

	// ─── Read ──────────────────────────────────────────────────────────────────

	public function get_by_seller_id( int $seller_id ): ?object {
		global $wpdb;

		$table = CreateSellerPaymentProfilesTable::table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE seller_id = %d LIMIT 1", $seller_id )
		);

		return $row ?: null;
	}

	public function find_by_stripe_customer( string $stripe_customer_id ): ?object {
		global $wpdb;

		$table = CreateSellerPaymentProfilesTable::table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE stripe_customer_id = %s LIMIT 1", $stripe_customer_id )
		);

		return $row ?: null;
	}

	// ─── Write ─────────────────────────────────────────────────────────────────

	/**
	 * Upsert a seller's payment profile.
	 * Call after a Stripe SetupIntent is confirmed for the seller.
	 *
	 * @param int    $seller_id
	 * @param string $stripe_customer_id  Stripe Customer ID (cus_xxx)
	 * @return bool
	 */
	public function save_stripe_customer( int $seller_id, string $stripe_customer_id ): bool {
		global $wpdb;

		$table = CreateSellerPaymentProfilesTable::table_name();

		$existing = $this->get_by_seller_id( $seller_id );

		if ( $existing ) {
			$result = $wpdb->update(
				$table,
				[
					'stripe_customer_id'       => sanitize_text_field( $stripe_customer_id ),
					'payment_auth_granted_at'  => current_time( 'mysql' ),
				],
				[ 'seller_id' => $seller_id ],
				[ '%s', '%s' ],
				[ '%d' ]
			);
		} else {
			$result = $wpdb->insert(
				$table,
				[
					'seller_id'               => $seller_id,
					'stripe_customer_id'      => sanitize_text_field( $stripe_customer_id ),
					'payment_auth_granted_at' => current_time( 'mysql' ),
					'spend_limit_monthly'     => 500.00,
					'spend_used_current_month' => 0.00,
					'card_enabled_fallback'   => 1,
				],
				[ '%d', '%s', '%s', '%f', '%f', '%d' ]
			);
		}

		return false !== $result;
	}

	/**
	 * Update the seller's monthly spend limit from their dashboard.
	 *
	 * @param int   $seller_id
	 * @param float $limit  GBP value. Minimum 0, maximum 10,000.
	 * @return bool
	 */
	public function update_spend_limit( int $seller_id, float $limit ): bool {
		global $wpdb;

		$table = CreateSellerPaymentProfilesTable::table_name();
		$limit = max( 0.0, min( 10000.0, $limit ) );

		$result = $wpdb->update(
			$table,
			[ 'spend_limit_monthly' => $limit ],
			[ 'seller_id'          => $seller_id ],
			[ '%f' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Atomically increment the monthly spend counter.
	 * Returns false if the charge would exceed the seller's monthly limit.
	 *
	 * @param int   $seller_id
	 * @param float $amount   GBP amount to add
	 * @return bool  true = OK to charge, false = limit exceeded
	 */
	public function reserve_spend( int $seller_id, float $amount ): bool {
		global $wpdb;

		$table   = CreateSellerPaymentProfilesTable::table_name();
		$profile = $this->get_by_seller_id( $seller_id );

		if ( ! $profile ) {
			return false;
		}

		$new_total = (float) $profile->spend_used_current_month + $amount;

		if ( $new_total > (float) $profile->spend_limit_monthly ) {
			return false;
		}

		$wpdb->update(
			$table,
			[ 'spend_used_current_month' => $new_total ],
			[ 'seller_id'               => $seller_id ],
			[ '%f' ],
			[ '%d' ]
		);

		return true;
	}

	/**
	 * Reset monthly spend counter (called by a scheduled job on 1st of each month).
	 *
	 * @param int $seller_id
	 * @return bool
	 */
	public function reset_monthly_spend( int $seller_id ): bool {
		global $wpdb;

		$table  = CreateSellerPaymentProfilesTable::table_name();
		$result = $wpdb->update(
			$table,
			[
				'spend_used_current_month' => 0.00,
				'spend_reset_at'           => current_time( 'mysql' ),
			],
			[ 'seller_id' => $seller_id ],
			[ '%f', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Check whether the seller has exceeded their monthly spend cap.
	 *
	 * @param int   $seller_id
	 * @param float $pending_amount  Amount we are about to charge
	 * @return bool  true = within limit
	 */
	public function within_spend_limit( int $seller_id, float $pending_amount ): bool {
		$profile = $this->get_by_seller_id( $seller_id );

		if ( ! $profile ) {
			return false;
		}

		return ( (float) $profile->spend_used_current_month + $pending_amount ) <= (float) $profile->spend_limit_monthly;
	}

	/**
	 * Check whether the seller has a valid Stripe Customer registered.
	 *
	 * @param int $seller_id
	 * @return bool
	 */
	public function has_payment_method( int $seller_id ): bool {
		$profile = $this->get_by_seller_id( $seller_id );

		return $profile && ! empty( $profile->stripe_customer_id ) && ! empty( $profile->payment_auth_granted_at );
	}
}
