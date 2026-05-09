<?php
/**
 * Connect Cold Outreach Charge Handler
 *
 * Listens on the `khm_connect_charge_cold_outreach` action fired by
 * ConnectOutreachChargingListener and executes an off-session Stripe
 * PaymentIntent using the seller's pre-registered payment method.
 *
 * @package KHM\Connect
 */

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

class ConnectColdOutreachChargeHandler {

	public function register(): void {
		add_action( 'khm_connect_charge_cold_outreach', [ $this, 'handle_charge' ], 10, 4 );
	}

	/**
	 * Execute an off-session charge against the seller's stored payment method.
	 *
	 * @param object $profile      Row from connect_seller_payment_profiles.
	 * @param int    $thread_id    connect_intro_threads ID for the cold outreach.
	 * @param string $tier         Commercial tier slug (premium/standard/exploratory).
	 * @param int    $sponsor_id   ID of the sponsor being charged.
	 */
	public function handle_charge( object $profile, int $thread_id, string $tier, int $sponsor_id ): void {
		$stripe_customer_id = isset( $profile->stripe_customer_id ) ? (string) $profile->stripe_customer_id : '';
		if ( empty( $stripe_customer_id ) ) {
			error_log( sprintf( '[KHM Connect Outreach] No stripe_customer_id for sponsor %d, thread %d — charge skipped.', $sponsor_id, $thread_id ) );
			return;
		}

		$amount   = $this->resolve_amount_cents( $tier );
		$currency = strtolower( (string) apply_filters( 'khm_connect_match_currency', get_option( 'khm_connect_match_currency', 'gbp' ) ) );

		if ( $amount <= 0 ) {
			error_log( sprintf( '[KHM Connect Outreach] No price configured for tier "%s", thread %d — charge skipped.', $tier, $thread_id ) );
			return;
		}

		// Enforce monthly spend limit.
		$spend_limit  = isset( $profile->spend_limit_monthly ) ? (int) $profile->spend_limit_monthly : 0;
		$spend_used   = isset( $profile->spend_used_current_month ) ? (int) $profile->spend_used_current_month : 0;
		if ( $spend_limit > 0 && ( $spend_used + $amount ) > $spend_limit ) {
			error_log( sprintf( '[KHM Connect Outreach] Spend limit would be exceeded for sponsor %d (limit %d, used %d, new %d) — charge skipped.', $sponsor_id, $spend_limit, $spend_used, $amount ) );
			do_action( 'khm_connect_spend_limit_exceeded', $sponsor_id, $thread_id, $spend_used, $spend_limit, $amount );
			return;
		}

		$stripe_key = function_exists( 'khm_get_stripe_secret' )
			? (string) ( khm_get_stripe_secret( 'KH_STRIPE_SECRET_KEY' ) ?? '' )
			: '';

		if ( empty( $stripe_key ) ) {
			error_log( '[KHM Connect Outreach] Stripe not configured — charge skipped.' );
			return;
		}

		try {
			\Stripe\Stripe::setApiKey( $stripe_key );

			// Retrieve the customer's default payment method for off-session charge.
			$customer = \Stripe\Customer::retrieve( $stripe_customer_id );
			$pm_id    = isset( $customer->invoice_settings->default_payment_method )
				? (string) $customer->invoice_settings->default_payment_method
				: '';

			if ( empty( $pm_id ) ) {
				error_log( sprintf( '[KHM Connect Outreach] No default payment method for customer %s (sponsor %d) — charge skipped.', $stripe_customer_id, $sponsor_id ) );
				do_action( 'khm_connect_charge_failed_no_payment_method', $sponsor_id, $thread_id );
				return;
			}

			$intent = \Stripe\PaymentIntent::create( [
				'amount'               => $amount,
				'currency'             => $currency,
				'customer'             => $stripe_customer_id,
				'payment_method'       => $pm_id,
				'off_session'          => true,
				'confirm'              => true,
				'metadata'             => [
					'purchase_type'  => 'cold_outreach',
					'thread_id'      => (string) $thread_id,
					'sponsor_id'     => (string) $sponsor_id,
					'tier'           => $tier,
				],
			] );

			if ( 'succeeded' === $intent->status ) {
				$this->record_charge_success( (int) ( $profile->id ?? 0 ), $amount, $intent->id, $thread_id );
				do_action( 'khm_connect_cold_outreach_charged', $sponsor_id, $thread_id, $intent->id, $amount );
				error_log( sprintf( '[KHM Connect Outreach] Charged sponsor %d £%.2f for thread %d (intent %s).', $sponsor_id, $amount / 100, $thread_id, $intent->id ) );
			} else {
				error_log( sprintf( '[KHM Connect Outreach] Charge incomplete for sponsor %d thread %d (status %s).', $sponsor_id, $thread_id, $intent->status ) );
				do_action( 'khm_connect_charge_failed', $sponsor_id, $thread_id, $intent->status );
			}
		} catch ( \Stripe\Exception\CardException $e ) {
			error_log( sprintf( '[KHM Connect Outreach] Card declined for sponsor %d thread %d: %s', $sponsor_id, $thread_id, $e->getMessage() ) );
			do_action( 'khm_connect_charge_failed_card_declined', $sponsor_id, $thread_id, $e->getMessage() );
		} catch ( \Exception $e ) {
			error_log( sprintf( '[KHM Connect Outreach] Charge exception for sponsor %d thread %d: %s', $sponsor_id, $thread_id, $e->getMessage() ) );
		}
	}

	private function resolve_amount_cents( string $tier ): int {
		$config = ConnectTiering::get_config();
		return isset( $config[ $tier ]['unit_price_cents'] ) ? (int) $config[ $tier ]['unit_price_cents'] : 0;
	}

	/**
	 * Increment spend_used_current_month on the seller payment profile.
	 */
	private function record_charge_success( int $profile_id, int $amount, string $intent_id, int $thread_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'connect_seller_payment_profiles';

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table}
			 SET spend_used_current_month = spend_used_current_month + %d,
			     updated_at = %s
			 WHERE id = %d",
			$amount,
			current_time( 'mysql', true ),
			$profile_id
		) );

		error_log( sprintf(
			'[KHM Connect Outreach] Recorded charge success: profile %d, amount %d, intent %s, thread %d',
			$profile_id, $amount, $intent_id, $thread_id
		) );
	}
}
