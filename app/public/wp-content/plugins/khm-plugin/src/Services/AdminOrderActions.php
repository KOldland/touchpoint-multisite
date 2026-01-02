<?php
namespace KHM\Services;

use KHM\Contracts\EmailServiceInterface;
use KHM\Contracts\OrderRepositoryInterface;
use KHM\Gateways\StripeGateway;

/**
 * Handles admin-triggered order actions (resend receipt, manual refunds).
 */
class AdminOrderActions {

	private OrderRepositoryInterface $orders;
	private EmailServiceInterface $email;

	public function __construct( OrderRepositoryInterface $orders, EmailServiceInterface $email ) {
		$this->orders = $orders;
		$this->email  = $email;
	}

	public function register(): void {
		add_action( 'khm_order_resend_receipt', [ $this, 'handle_resend_receipt' ] );
		add_action( 'khm_order_refund_recorded', [ $this, 'handle_refund_recorded' ], 10, 4 );
	}

	/**
	 * Send the invoice email to the member (and admin copy) when requested via the admin UI.
	 *
	 * @param int $order_id Order identifier.
	 * @return void
	 */
	public function handle_resend_receipt( int $order_id ): void {
		$order = $this->orders->getWithRelations( $order_id );

		if ( ! $order ) {
			$found = $this->orders->find( $order_id );
			if ( ! $found ) {
				return;
			}
			$order = (array) $found;
		}

		$user_id = (int) ( $order['user_id'] ?? 0 );
		$user    = $user_id ? get_userdata( $user_id ) : null;
		$email   = $user ? $user->user_email : ( $order['user_email'] ?? '' );

		if ( empty( $email ) ) {
			return;
		}

		$level_name = $order['level_name'] ?? __( 'Membership', 'khm-membership' );
		$total      = (float) ( $order['total'] ?? 0.0 );

		$data = [
			'user_name'         => $user ? $user->display_name : ( $order['display_name'] ?? '' ),
			'user_email'        => $email,
			'user_login'        => $user ? $user->user_login : ( $order['user_login'] ?? '' ),
			'user_id'           => $user_id,
			'level_name'        => $level_name,
			'level_id'          => (int) ( $order['membership_id'] ?? 0 ),
			'amount'            => $total,
			'formatted_amount'  => $this->format_price( $total ),
			'due_today'         => $total,
			'formatted_due'     => $this->format_price( $total ),
			'invoice_id'        => $order['payment_transaction_id'] ?? $order['code'] ?? '',
			'billing_reason'    => 'manual_admin',
			'discount_summary'  => $this->build_discount_summary( $order ),
			'trial_summary'     => $this->build_trial_summary( $order ),
			'recurring_summary' => $this->build_recurring_summary( $order ),
			'sitename'          => get_bloginfo( 'name' ),
			'siteurl'           => home_url(),
			'account_url'       => home_url( '/account/' ),
			'order_url'         => admin_url( 'admin.php?page=khm-orders' ),
			'date'              => current_time( 'mysql', true ),
		];

		$subject = sprintf( __( 'Your receipt for %s', 'khm-membership' ), $level_name );

		$this->email
			->setSubject( $subject )
			->send( 'invoice', $email, apply_filters( 'khm_order_receipt_email_data', $data, $order ) );

		$admin_email = get_option( 'admin_email' );
		if ( $admin_email ) {
			$admin_subject = sprintf( __( 'Payment received for %s', 'khm-membership' ), $data['user_name'] ?: $email );
			$this->email
				->setSubject( $admin_subject )
				->send( 'invoice_admin', $admin_email, $data );
		}
	}

	/**
	 * Attempt to issue a Stripe refund when recorded in the admin UI.
	 *
	 * @param int   $order_id Order identifier.
	 * @param float $amount   Refund amount.
	 * @param string $reason  Refund reason.
	 * @param array|object $orderData Order data.
	 * @return void
	 */
	public function handle_refund_recorded( int $order_id, float $amount, string $reason, $orderData ): void {
		$order = is_array( $orderData ) ? $orderData : (array) $orderData;

		$gateway = strtolower( $order['gateway'] ?? '' );
		$transaction_id = $order['payment_transaction_id'] ?? '';

		if ( 'stripe' !== $gateway || empty( $transaction_id ) ) {
			return;
		}

		$stripe = $this->make_stripe_gateway();
		if ( ! $stripe ) {
			return;
		}

		$order_object = (object) [
			'payment_transaction_id' => $transaction_id,
		];

		$result = $stripe->refund( $order_object, $amount );

		if ( $result->isSuccess() ) {
			$refund_id = $result->get( 'refund_id' );
			$notes     = $order['notes'] ?? '';
			$message   = sprintf(
				/* translators: 1: refund amount, 2: refund id */
				__( 'Stripe refund processed: %1$s (Refund ID: %2$s)', 'khm-membership' ),
				$this->format_price( $amount ),
				$refund_id
			);
			$new_notes = $notes ? $notes . "\n\n" . $message : $message;
			$this->orders->updateNotes( $order_id, $new_notes );
			do_action( 'khm_order_gateway_refunded', $order_id, $refund_id, $amount, $reason );
		} else {
			do_action( 'khm_order_gateway_refund_failed', $order_id, $result );
		}
	}

	private function build_discount_summary( array $order ): string {
		$code   = $order['discount_code'] ?? '';
		$amount = isset( $order['discount_amount'] ) ? (float) $order['discount_amount'] : 0.0;

		if ( $code && $amount > 0 ) {
			return sprintf(
				/* translators: 1: coupon code, 2: formatted amount */
				__( 'Discount %1$s applied: -%2$s', 'khm-membership' ),
				$code,
				$this->format_price( $amount )
			);
		}

		return __( '—', 'khm-membership' );
	}

	private function build_trial_summary( array $order ): string {
		$days   = isset( $order['trial_days'] ) ? (int) $order['trial_days'] : 0;
		$amount = isset( $order['trial_amount'] ) ? (float) $order['trial_amount'] : 0.0;

		if ( $days > 0 ) {
			return $amount > 0
				? sprintf( __( 'Paid trial: %1$d days (%2$s due today)', 'khm-membership' ), $days, $this->format_price( $amount ) )
				: sprintf( __( 'Free trial: %d days', 'khm-membership' ), $days );
		}

		return __( '—', 'khm-membership' );
	}

	private function build_recurring_summary( array $order ): string {
		$type   = $order['recurring_discount_type'] ?? '';
		$amount = isset( $order['recurring_discount_amount'] ) ? (float) $order['recurring_discount_amount'] : 0.0;

		if ( $type && $amount > 0 ) {
			if ( 'percent' === $type ) {
				return sprintf( __( 'Recurring discount: %s%% off each renewal', 'khm-membership' ), number_format( $amount, 2 ) );
			}

			return sprintf( __( 'Recurring discount: %s off each renewal', 'khm-membership' ), $this->format_price( $amount ) );
		}

		return __( '—', 'khm-membership' );
	}

	private function make_stripe_gateway(): ?StripeGateway {
		$filtered = apply_filters( 'khm_admin_order_actions_stripe_gateway', null, $this );
		if ( $filtered instanceof StripeGateway ) {
			return $filtered;
		}

		$secret = get_option( 'khm_stripe_secret_key', '' );
		if ( empty( $secret ) ) {
			return null;
		}

		$credentials = [
			'secret_key'      => $secret,
			'publishable_key' => get_option( 'khm_stripe_publishable_key', '' ),
			'environment'     => get_option( 'khm_stripe_environment', 'sandbox' ),
		];

		$gateway = new StripeGateway( $credentials );

		$custom_gateway = apply_filters( 'khm_admin_order_actions_stripe_gateway_created', $gateway, $credentials, $this );
		if ( $custom_gateway instanceof StripeGateway ) {
			return $custom_gateway;
		}

		return $gateway;
	}

	private function format_price( float $amount ): string {
		if ( function_exists( 'khm_format_price' ) ) {
			return khm_format_price( $amount );
		}

		return '$' . number_format( $amount, 2 );
	}
}
