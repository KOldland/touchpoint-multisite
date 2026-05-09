<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * Listens for khm_cold_outreach_accepted and triggers upfront billing for
 * direct-connection (cold outreach) intro threads.
 *
 * Downstream Stripe integration should hook on `khm_connect_charge_cold_outreach`.
 */
class ConnectOutreachChargingListener {

	private ConnectSellerPaymentRepository $payment_profiles;

	public function __construct( ?ConnectSellerPaymentRepository $payment_profiles = null ) {
		$this->payment_profiles = $payment_profiles ?? new ConnectSellerPaymentRepository();
	}

	public function register(): void {
		add_action( 'khm_cold_outreach_accepted', array( $this, 'handle_cold_outreach_accepted' ), 10, 4 );
	}

	/**
	 * Fires when a seller accepts a direct-connection match opportunity.
	 *
	 * @param int $opportunity_id
	 * @param int $sponsor_id
	 * @param int $provider_id
	 * @param int $thread_id
	 */
	public function handle_cold_outreach_accepted( int $opportunity_id, int $sponsor_id, int $provider_id, int $thread_id ): void {
		$profile = $this->payment_profiles->get_by_seller_id( $provider_id );

		if ( ! is_array( $profile ) ) {
			// No payment profile on file — log so admin can action manually.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf(
				'[KHM Connect] Cold outreach accepted (opportunity=%d, provider=%d, thread=%d) — no payment profile found, charge skipped.',
				$opportunity_id,
				$provider_id,
				$thread_id
			) );
			return;
		}

		/**
		 * Fires when an upfront cold-outreach charge should be processed.
		 *
		 * @param array $profile        Seller payment profile row from connect_seller_payment_profiles.
		 * @param int   $opportunity_id Accepted opportunity ID.
		 * @param int   $sponsor_id     Sponsor (seller user) ID.
		 * @param int   $provider_id    Connect provider ID.
		 * @param int   $thread_id      Newly created intro thread ID.
		 */
		do_action( 'khm_connect_charge_cold_outreach', $profile, $opportunity_id, $sponsor_id, $provider_id, $thread_id );
	}
}
