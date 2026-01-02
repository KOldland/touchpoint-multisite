<?php
/**
 * Webhook Email Notifications
 *
 * Handles email notifications triggered by webhook events.
 * Listens to webhook action hooks and sends appropriate emails to members and admins.
 *
 * @package KHM\Services
 */

namespace KHM\Services;

use KHM\Contracts\EmailServiceInterface;
use KHM\Contracts\OrderRepositoryInterface;

/**
 * Email notification handler for webhook events.
 */
class WebhookEmailNotifications {

	/**
	 * Email service.
	 *
	 * @var EmailServiceInterface
	 */
	private EmailServiceInterface $email;

	/**
	 * Orders repository.
	 *
	 * @var OrderRepositoryInterface
	 */
	private OrderRepositoryInterface $orders;

	/**
	 * Constructor.
	 *
	 * @param EmailServiceInterface    $email  Email service.
	 * @param OrderRepositoryInterface $orders Orders repository.
	 */
	public function __construct(
		EmailServiceInterface $email,
		OrderRepositoryInterface $orders
	) {
		$this->email  = $email;
		$this->orders = $orders;
	}

	/**
	 * Register all webhook email notification hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Payment failed notifications.
		add_action( 'khm_stripe_invoice_payment_failed_handled', array( $this, 'on_payment_failed' ), 10, 2 );

		// Payment succeeded (invoice/renewal) notifications.
		add_action( 'khm_stripe_invoice_payment_succeeded_handled', array( $this, 'on_payment_succeeded' ), 10, 2 );

		// Subscription deleted notifications.
		add_action( 'khm_stripe_subscription_deleted_handled', array( $this, 'on_subscription_deleted' ), 10, 3 );

		// Charge refunded notifications.
		add_action( 'khm_stripe_charge_refunded_handled', array( $this, 'on_charge_refunded' ), 10, 2 );
	}

	/**
	 * Handle successful invoice payment notifications.
	 *
	 * Sends invoice/renewal emails to member and admin on successful payment.
	 *
	 * @param object $invoice Stripe invoice object.
	 * @param array  $data    Order data from webhook handler.
	 * @return void
	 */
	public function on_payment_succeeded( object $invoice, array $data ): void {
		$user_id = $data['user_id'] ?? 0;
		if ( ! $user_id ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$level_id = (int) ( $data['membership_id'] ?? 0 );
		$level    = null;
		if ( $level_id ) {
			global $wpdb;
			$level = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}khm_membership_levels WHERE id = %d",
					$level_id
				)
			);
		}

		$amount_paid = isset( $invoice->amount_paid ) ? ( (float) $invoice->amount_paid / 100.0 ) : 0.0;
		$billing_reason = isset( $invoice->billing_reason ) ? (string) $invoice->billing_reason : '';
		$template = in_array( $billing_reason, array( 'subscription_cycle', 'subscription_threshold' ), true ) ? 'renewal' : 'invoice';

		// Attempt to find the most recent order for this subscription to inherit discount/trial metadata.
		$last_order = null;
		$subscription_id = $data['subscription_transaction_id'] ?? '';
		if ( ! empty( $subscription_id ) ) {
			$last_order = $this->orders->findLastBySubscriptionId( $subscription_id );
		}

		// Discount details from invoice.
		$coupon_code = '';
		$savings     = 0.0;
		if ( isset( $invoice->discount->coupon ) ) {
			$coupon = $invoice->discount->coupon;
			// Prefer coupon name then id.
			$coupon_code = isset( $coupon->name ) && $coupon->name ? (string) $coupon->name : ( (string) ( $coupon->id ?? '' ) );
			if ( isset( $invoice->amount_discount ) ) {
				$savings = (float) $invoice->amount_discount / 100.0;
			} elseif ( isset( $coupon->amount_off ) ) {
				$savings = (float) $coupon->amount_off / 100.0;
			} elseif ( isset( $coupon->percent_off ) && isset( $invoice->amount_subtotal ) ) {
				$savings = ( (float) $invoice->amount_subtotal * (float) $coupon->percent_off ) / 10000.0;
			}
		}

		// Build email data.
		$email_data = array(
			'user_name'          => $user->display_name,
			'user_email'         => $user->user_email,
			'user_login'         => $user->user_login,
			'user_id'            => $user_id,
			'level_name'         => $level->name ?? __( 'Membership', 'khm-membership' ),
			'level_id'           => $level_id,
			'amount'             => $amount_paid,
			'formatted_amount'   => $this->format_price( $amount_paid ),
			'due_today'          => $amount_paid,
			'formatted_due'      => $this->format_price( $amount_paid ),
			'invoice_id'         => $invoice->id ?? '',
			'billing_reason'     => $billing_reason,
			'coupon_code'        => $coupon_code,
			'savings'            => $savings,
			'formatted_savings'  => $this->format_price( $savings ),
			'sitename'           => get_bloginfo( 'name' ),
			'siteurl'            => home_url(),
			'account_url'        => home_url( '/account/' ),
			'order_url'          => admin_url( 'admin.php?page=khm-orders' ),
			'date'               => gmdate( 'Y-m-d H:i:s' ),
			'discount_summary'   => '',
			'trial_summary'      => '',
			'recurring_summary'  => '',
		);

		if ( $coupon_code && $savings > 0 ) {
			$email_data['discount_summary'] = sprintf(
				/* translators: 1: coupon code, 2: formatted amount */
				__( 'Discount %1$s applied: -%2$s', 'khm-membership' ),
				esc_html( $coupon_code ),
				$email_data['formatted_savings']
			);
		}

		// Inherit trial/recurring details from last order if available.
		if ( $last_order ) {
			if ( ! empty( $last_order->trial_days ) ) {
				$trial_amount = (float) ( $last_order->trial_amount ?? 0.0 );
				$email_data['trial_summary'] = $trial_amount > 0
					? sprintf( __( 'Paid trial: %d days (%s due today)', 'khm-membership' ), (int) $last_order->trial_days, $this->format_price( $trial_amount ) )
					: sprintf( __( 'Free trial: %d days', 'khm-membership' ), (int) $last_order->trial_days );
			}

			if ( ! empty( $last_order->recurring_discount_type ) && (float) ( $last_order->recurring_discount_amount ?? 0 ) > 0 ) {
				if ( $last_order->recurring_discount_type === 'percent' ) {
					$email_data['recurring_summary'] = sprintf(
						__( 'Recurring discount: %s%% off each renewal', 'khm-membership' ),
						number_format( (float) $last_order->recurring_discount_amount, 2 )
					);
				} else {
					$email_data['recurring_summary'] = sprintf(
						__( 'Recurring discount: %s off each renewal', 'khm-membership' ),
						$this->format_price( (float) $last_order->recurring_discount_amount )
					);
				}
			}
		}

		// Send member email.
		$member_subject = ( 'renewal' === $template )
			? sprintf( __( 'Your renewal receipt for %s', 'khm-membership' ), $email_data['level_name'] )
			: sprintf( __( 'Your payment receipt for %s', 'khm-membership' ), $email_data['level_name'] );

		$this->email
			->setSubject( $member_subject )
			->send( $template, $user->user_email, $email_data );

		// Send admin email copy.
		$admin_email = get_option( 'admin_email' );
		$admin_subject = ( 'renewal' === $template )
			? sprintf( __( 'Renewal payment received for %s', 'khm-membership' ), $user->display_name )
			: sprintf( __( 'Payment received for %s', 'khm-membership' ), $user->display_name );

		$this->email
			->setSubject( $admin_subject )
			->send( $template . '_admin', $admin_email, $email_data );

		do_action( 'khm_payment_succeeded_email_sent', $user, $invoice, $template, $email_data );
	}

	/**
	 * Handle payment failed notifications.
	 *
	 * Sends billing failure emails to both member and admin when a payment fails.
	 *
	 * @param object $invoice Stripe invoice object.
	 * @param array  $data    Order data array.
	 * @return void
	 */
	public function on_payment_failed( object $invoice, array $data ): void {
		$user_id = $data['user_id'] ?? 0;
		if ( ! $user_id ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Get order if it exists.
		$order = null;
		if ( ! empty( $data['payment_transaction_id'] ) ) {
			$order = $this->orders->findByPaymentTransactionId( $data['payment_transaction_id'] );
		}

		$level_id = (int) ( $data['membership_id'] ?? 0 );
		$level    = null;
		if ( $level_id ) {
			global $wpdb;
			$level = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}khm_membership_levels WHERE id = %d",
					$level_id
				)
			);
		}

		// Prepare email data.
		$email_data = array(
			'user_name'        => $user->display_name,
			'user_email'       => $user->user_email,
			'user_login'       => $user->user_login,
			'level_name'       => $level->name ?? __( 'Membership', 'khm-membership' ),
			'level_id'         => $level_id,
			'subscription_id'  => $data['subscription_transaction_id'] ?? '',
			'amount'           => $data['total'] ?? 0.0,
			'formatted_amount' => $this->format_price( $data['total'] ?? 0.0 ),
			'invoice_id'       => $invoice->id ?? '',
			'date'             => gmdate( 'Y-m-d H:i:s' ),
			'sitename'         => get_bloginfo( 'name' ),
			'siteurl'          => home_url(),
			'billing_url'      => home_url( '/account/' ), // TODO: Make this configurable.
			'failure_reason'   => $data['failure_message'] ?? '',
			'failure_code'     => $data['failure_code'] ?? '',
			'member_edit_url'  => admin_url( 'user-edit.php?user_id=' . $user_id ),
		);

		if ( $order ) {
			$email_data['order_id']   = $order->id;
			$email_data['order_code'] = $order->code;
		}

		// Send member email.
		$this->email
			->setSubject( __( 'Payment Failed - Action Required', 'khm-membership' ) )
			->send( 'billing_failure', $user->user_email, $email_data );

		// Send admin email.
		$admin_email = get_option( 'admin_email' );
		$this->email
			->setSubject(
				sprintf(
					/* translators: %s: User display name */
					__( 'Payment Failed for %s', 'khm-membership' ),
					$user->display_name
				)
			)
			->send( 'billing_failure_admin', $admin_email, $email_data );

		do_action( 'khm_payment_failed_email_sent', $user, $invoice, $order );
	}

	/**
	 * Handle subscription deleted notifications.
	 *
	 * Sends cancellation notification to admin when a subscription is deleted at Stripe.
	 *
	 * @param object $subscription Stripe subscription object.
	 * @param int    $user_id      User ID.
	 * @param int    $level_id     Membership level ID.
	 * @return void
	 */
	public function on_subscription_deleted( object $subscription, int $user_id, int $level_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Get level info.
		global $wpdb;
		$level = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}khm_membership_levels WHERE id = %d",
				$level_id
			)
		);

		$email_data = array(
			'user_name'        => $user->display_name,
			'user_email'       => $user->user_email,
			'user_login'       => $user->user_login,
			'user_id'          => $user_id,
			'level_name'       => $level->name ?? __( 'Unknown Level', 'khm-membership' ),
			'level_id'         => $level_id,
			'subscription_id'  => $subscription->id ?? '',
			'date'             => gmdate( 'Y-m-d H:i:s' ),
			'sitename'         => get_bloginfo( 'name' ),
			'siteurl'          => home_url(),
			'member_edit_url'  => admin_url( 'user-edit.php?user_id=' . $user_id ),
		);

		// Send admin notification (Stripe-initiated cancellation is unusual).
		$admin_email = get_option( 'admin_email' );
		$this->email
			->setSubject(
				sprintf(
					/* translators: %s: User display name */
					__( 'Stripe Subscription Deleted for %s', 'khm-membership' ),
					$user->display_name
				)
			)
			->send( 'subscription_deleted_admin', $admin_email, $email_data );

		do_action( 'khm_subscription_deleted_email_sent', $user, $subscription, $level_id );
	}

	/**
	 * Handle charge refunded notifications.
	 *
	 * Sends refund notification to admin when a charge is refunded at Stripe.
	 *
	 * @param object $charge   Stripe charge object.
	 * @param int    $order_id Order ID.
	 * @return void
	 */
	public function on_charge_refunded( object $charge, int $order_id ): void {
		$order = $this->orders->find( $order_id );
		if ( ! $order ) {
			return;
		}

		$user = get_userdata( $order->user_id );
		if ( ! $user ) {
			return;
		}

		$refund_amount = 0.0;
		if ( isset( $charge->amount_refunded ) ) {
			$refund_amount = (float) $charge->amount_refunded / 100.0;
		}

		$email_data = array(
			'user_name'        => $user->display_name,
			'user_email'       => $user->user_email,
			'user_login'       => $user->user_login,
			'user_id'          => $user->ID,
			'order_id'         => $order_id,
			'order_code'       => $order->code,
			'order_total'      => $order->total,
			'formatted_total'  => $this->format_price( $order->total ),
			'refund_amount'    => $refund_amount,
			'formatted_refund' => $this->format_price( $refund_amount ),
			'charge_id'        => $charge->id ?? '',
			'date'             => gmdate( 'Y-m-d H:i:s' ),
			'sitename'         => get_bloginfo( 'name' ),
			'siteurl'          => home_url(),
			'order_url'        => admin_url( 'admin.php?page=khm-orders&action=view&id=' . $order_id ),
		);

		// Send admin notification.
		$admin_email = get_option( 'admin_email' );
		$this->email
			->setSubject(
				sprintf(
					/* translators: %s: Order code */
					__( 'Charge Refunded for Order %s', 'khm-membership' ),
					$order->code
				)
			)
			->send( 'charge_refunded_admin', $admin_email, $email_data );

		do_action( 'khm_charge_refunded_email_sent', $user, $charge, $order );
	}

	/**
	 * Format price for display.
	 *
	 * @param float $amount Amount to format.
	 * @return string Formatted price.
	 */
	private function format_price( float $amount ): string {
		// TODO: Use currency settings from membership level or settings.
		$currency = get_option( 'khm_currency', 'USD' );
		$symbol   = '$'; // Default to USD symbol.

		switch ( $currency ) {
			case 'EUR':
				$symbol = '€';
				break;
			case 'GBP':
				$symbol = '£';
				break;
			case 'JPY':
				$symbol = '¥';
				break;
		}

		return $symbol . number_format( $amount, 2 );
	}
}
