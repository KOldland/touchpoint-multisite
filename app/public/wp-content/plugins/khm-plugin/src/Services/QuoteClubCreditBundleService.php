<?php
/**
 * Quote Club Credit Bundle Service
 *
 * CRUD and fulfilment logic for sponsor-purchasable credit bundles.
 * Bundles are admin-defined and can add editorial credits and/or press-release
 * credits to a sponsor's balance when purchased via Stripe checkout.
 *
 * @package KHM\Services
 */

namespace KHM\Services;

class QuoteClubCreditBundleService {

	private CreditService $credits;

	public function __construct( CreditService $credits ) {
		$this->credits = $credits;
	}

	// -------------------------------------------------------------------------
	// Bundle CRUD (admin-facing)
	// -------------------------------------------------------------------------

	/**
	 * List all bundles, optionally filtered to active-only.
	 *
	 * @param bool $active_only When true only returns active = 1 bundles.
	 * @return array<int, object>
	 */
	public function list_bundles( bool $active_only = true ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_qc_credit_bundles';

		if ( $active_only ) {
			return $wpdb->get_results( "SELECT * FROM {$table} WHERE active = 1 ORDER BY price_cents ASC" );
		}

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY price_cents ASC" );
	}

	/**
	 * Retrieve a single bundle by ID.
	 *
	 * @param int $bundle_id
	 * @return object|null
	 */
	public function get_bundle( int $bundle_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_qc_credit_bundles';

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
			$bundle_id
		) ) ?: null;
	}

	/**
	 * Create a new credit bundle definition.
	 *
	 * @param array{
	 *   name: string,
	 *   description?: string,
	 *   editorial_credits: int,
	 *   press_release_credits: int,
	 *   price_cents: int,
	 *   stripe_price_id?: string,
	 *   active?: int,
	 * } $data
	 * @return int|false Inserted bundle ID or false on failure.
	 */
	public function create_bundle( array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_qc_credit_bundles';

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$table,
			[
				'name'                  => sanitize_text_field( $data['name'] ?? '' ),
				'description'           => sanitize_textarea_field( $data['description'] ?? '' ),
				'editorial_credits'     => max( 0, (int) ( $data['editorial_credits'] ?? 0 ) ),
				'press_release_credits' => max( 0, (int) ( $data['press_release_credits'] ?? 0 ) ),
				'price_cents'           => max( 0, (int) ( $data['price_cents'] ?? 0 ) ),
				'stripe_price_id'       => sanitize_text_field( $data['stripe_price_id'] ?? '' ),
				'active'                => isset( $data['active'] ) ? (int) (bool) $data['active'] : 1,
				'created_at'            => $now,
				'updated_at'            => $now,
			],
			[ '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s' ]
		);

		return $inserted !== false ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an existing bundle.
	 *
	 * @param int   $bundle_id
	 * @param array $data Partial update; only provided keys are changed.
	 * @return bool
	 */
	public function update_bundle( int $bundle_id, array $data ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_qc_credit_bundles';

		$allowed = [
			'name'                  => '%s',
			'description'           => '%s',
			'editorial_credits'     => '%d',
			'press_release_credits' => '%d',
			'price_cents'           => '%d',
			'stripe_price_id'       => '%s',
			'active'                => '%d',
		];

		$fields  = [];
		$formats = [];

		foreach ( $allowed as $key => $fmt ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$fields[ $key ] = $data[ $key ];
			$formats[]      = $fmt;
		}

		if ( empty( $fields ) ) {
			return false;
		}

		$fields['updated_at'] = current_time( 'mysql' );
		$formats[]            = '%s';

		$result = $wpdb->update(
			$table,
			$fields,
			[ 'id' => $bundle_id ],
			$formats,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Soft-delete a bundle by setting active = 0.
	 *
	 * @param int $bundle_id
	 * @return bool
	 */
	public function deactivate_bundle( int $bundle_id ): bool {
		return $this->update_bundle( $bundle_id, [ 'active' => 0 ] );
	}

	// -------------------------------------------------------------------------
	// Purchase / fulfilment
	// -------------------------------------------------------------------------

	/**
	 * Record a pending purchase (call when Stripe checkout session is created).
	 *
	 * @param int    $user_id
	 * @param int    $bundle_id
	 * @param string $stripe_session_id
	 * @param int    $sponsor_id  0 if unknown at creation time.
	 * @return int|false Purchase record ID or false.
	 */
	public function record_pending_purchase( int $user_id, int $bundle_id, string $stripe_session_id, int $sponsor_id = 0 ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'khm_qc_bundle_purchases';
		$bundle = $this->get_bundle( $bundle_id );

		if ( ! $bundle ) {
			return false;
		}

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$table,
			[
				'user_id'                     => $user_id,
				'sponsor_id'                  => $sponsor_id ?: null,
				'bundle_id'                   => $bundle_id,
				'stripe_session_id'           => $stripe_session_id,
				'editorial_credits_added'     => 0,
				'press_release_credits_added' => 0,
				'price_cents'                 => (int) $bundle->price_cents,
				'status'                      => 'pending',
				'created_at'                  => $now,
				'updated_at'                  => $now,
			],
			[ '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s' ]
		);

		return $inserted !== false ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Fulfil a purchase: add credits to the user and mark the purchase completed.
	 * This is idempotent — calling it twice on the same session_id is safe.
	 *
	 * @param string $stripe_session_id Stripe checkout.session.id from webhook.
	 * @return bool True if fulfilled (or already was), false on error.
	 */
	public function fulfil_purchase( string $stripe_session_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'khm_qc_bundle_purchases';

		$purchase = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE stripe_session_id = %s LIMIT 1",
			$stripe_session_id
		) );

		if ( ! $purchase ) {
			return false;
		}

		if ( $purchase->status === 'completed' ) {
			return true; // idempotent
		}

		$bundle = $this->get_bundle( (int) $purchase->bundle_id );
		if ( ! $bundle ) {
			return false;
		}

		$user_id  = (int) $purchase->user_id;
		$ed_creds = (int) $bundle->editorial_credits;
		$pr_creds = (int) $bundle->press_release_credits;

		// Add editorial credits as bonus credits.
		if ( $ed_creds > 0 ) {
			$this->credits->addEditorialBonusCredits( $user_id, $ed_creds, 'bundle_purchase:' . $bundle->id );
		}

		// Add press release credits directly to the user's row.
		if ( $pr_creds > 0 ) {
			$this->add_press_release_credits( $user_id, $pr_creds );
		}

		// Mark purchase as completed.
		$wpdb->update(
			$table,
			[
				'editorial_credits_added'     => $ed_creds,
				'press_release_credits_added' => $pr_creds,
				'status'                      => 'completed',
				'updated_at'                  => current_time( 'mysql' ),
			],
			[ 'id' => (int) $purchase->id ],
			[ '%d', '%d', '%s', '%s' ],
			[ '%d' ]
		);

		do_action( 'khm_qc_bundle_purchase_fulfilled', $user_id, (int) $bundle->id, $ed_creds, $pr_creds );

		return true;
	}

	/**
	 * Mark a purchase as failed.
	 *
	 * @param string $stripe_session_id
	 * @return bool
	 */
	public function mark_failed( string $stripe_session_id ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'khm_qc_bundle_purchases',
			[ 'status' => 'failed', 'updated_at' => current_time( 'mysql' ) ],
			[ 'stripe_session_id' => $stripe_session_id ],
			[ '%s', '%s' ],
			[ '%s' ]
		);

		return $result !== false;
	}

	/**
	 * Retrieve a user's purchase history.
	 *
	 * @param int $user_id
	 * @param int $limit
	 * @return array
	 */
	public function get_purchase_history( int $user_id, int $limit = 20 ): array {
		global $wpdb;
		$pt = $wpdb->prefix . 'khm_qc_bundle_purchases';
		$bt = $wpdb->prefix . 'khm_qc_credit_bundles';

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT p.*, b.name AS bundle_name
			 FROM {$pt} p
			 LEFT JOIN {$bt} b ON b.id = p.bundle_id
			 WHERE p.user_id = %d
			 ORDER BY p.created_at DESC
			 LIMIT %d",
			$user_id,
			$limit
		) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Add press release credits directly to the current-month row.
	 * Called by fulfil_purchase when a bundle includes press-release credits.
	 */
	private function add_press_release_credits( int $user_id, int $amount ): void {
		global $wpdb;
		$table         = $wpdb->prefix . 'khm_user_credits';
		$current_month = date( 'Y-m' );

		// Ensure a row exists.
		$this->credits->allocateMonthlyEditorialCredits( $user_id );

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table}
			 SET press_release_credits = press_release_credits + %d,
			     updated_at            = %s
			 WHERE user_id = %d AND allocation_month = %s",
			$amount,
			current_time( 'mysql' ),
			$user_id,
			$current_month
		) );
	}
}
