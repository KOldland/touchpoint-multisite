<?php
/**
 * Webhooks REST Controller
 *
 * Provides REST endpoints for gateway webhooks (Stripe initial implementation).
 *
 * @package KHM\Rest
 */

namespace KHM\Rest;

use DateTime;
use DateTimeZone;
use KHM\Contracts\WebhookVerifierInterface;
use KHM\Contracts\IdempotencyStoreInterface;
use KHM\Contracts\OrderRepositoryInterface;
use KHM\Contracts\MembershipRepositoryInterface;

/**
 * REST controller for payment gateway webhooks (Stripe).
 */
class WebhooksController {

	/**
	 * Verifier for webhook signatures and event parsing.
	 *
	 * @var WebhookVerifierInterface
	 */
	private WebhookVerifierInterface $verifier;
	/**
	 * Idempotency store for processed webhook events.
	 *
	 * @var IdempotencyStoreInterface
	 */
	private IdempotencyStoreInterface $idempotency;
	/**
	 * Orders repository.
	 *
	 * @var OrderRepositoryInterface
	 */
	private OrderRepositoryInterface $orders;
	/**
	 * Memberships repository.
	 *
	 * @var MembershipRepositoryInterface
	 */
	private MembershipRepositoryInterface $memberships;

	/**
	 * Constructor.
	 *
	 * @param WebhookVerifierInterface      $verifier    Webhook verifier.
	 * @param IdempotencyStoreInterface     $idempotency Idempotency store.
	 * @param OrderRepositoryInterface      $orders      Orders repository.
	 * @param MembershipRepositoryInterface $memberships Memberships repository.
	 */
	public function __construct(
		WebhookVerifierInterface $verifier,
		IdempotencyStoreInterface $idempotency,
		OrderRepositoryInterface $orders,
		MembershipRepositoryInterface $memberships
	) {
		$this->verifier    = $verifier;
		$this->idempotency = $idempotency;
		$this->orders      = $orders;
		$this->memberships = $memberships;
	}

	/**
	 * Register REST routes for webhooks.
	 */
	public function register_routes(): void {
		register_rest_route(
			'khm/v1',
			'/webhooks/stripe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_stripe' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle incoming Stripe webhook.
	 *
	 * @param \WP_REST_Request $request Full request instance.
	 * @return \WP_REST_Response|\WP_Error REST response or error if validation fails.
	 */
	public function handle_stripe( $request ) {
		// Get webhook secret from options.
		$secret = get_option( 'khm_stripe_webhook_secret', '' );
		if ( empty( $secret ) ) {
			return new \WP_Error( 'khm_missing_secret', 'Stripe webhook is not configured (missing secret).', array( 'status' => 500 ) );
		}

		$payload = $request->get_body();
		// Collect all headers for signature extraction; normalize in verifier.
		$headers = $request->get_headers();

		// Verify signature.
		$verified_event = $this->verifier->verify( $payload, $headers, $secret );
		if ( ! $verified_event ) {
			return new \WP_Error( 'khm_invalid_signature', 'Invalid Stripe webhook signature.', array( 'status' => 400 ) );
		}

		try {
			// If verifier returned the parsed event, use it; otherwise parse manually.
			$event = is_object( $verified_event ) ? $verified_event : $this->verifier->parseEvent( $payload );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'khm_invalid_payload', $e->getMessage(), array( 'status' => 400 ) );
		}

		$eventId   = $this->verifier->getEventId( $event );
		$eventType = $this->verifier->getEventType( $event );

		if ( empty( $eventId ) ) {
			return new \WP_Error( 'khm_missing_event_id', 'Missing event id in payload.', array( 'status' => 400 ) );
		}

		// Idempotency check.
		if ( $this->idempotency->hasProcessed( $eventId ) ) {
			// Already processed; respond 200 to avoid retries.
			return new \WP_REST_Response(
				array(
					'ok'     => true,
					'status' => 'duplicate',
					'id'     => $eventId,
					'type'   => $eventType,
				),
				200
			);
		}

		// Dispatch to specific hooks for extensibility.
		$genericHook = 'khm_webhook_stripe';
		$typedHook   = 'khm_webhook_stripe_' . str_replace( array( '.', ':', '/' ), '_', strtolower( $eventType ) );

		/**
		 * Fires for any Stripe webhook before specific type hooks.
		 *
		 * @param object $event Parsed Stripe event.
		 */
		do_action( $genericHook, $event );

		/**
		 * Fires for a specific Stripe webhook type (dots replaced with underscores).
		 * Example: khm_webhook_stripe_invoice_payment_succeeded.
		 *
		 * @param object $event Parsed Stripe event.
		 */
		do_action( $typedHook, $event );

		try {
			// Built-in handlers for common Stripe events.
			$this->handle_stripe_event( $eventType, $event );

			// Mark processed (INSERT IGNORE will handle races).
			$this->idempotency->markProcessed( $eventId, 'stripe', array( 'type' => $eventType ) );

		} catch ( \Throwable $e ) {
			// Log and bubble a 500 so Stripe retries. Any partial side effects should be idempotent on retry.
			error_log( 'Stripe webhook handler error for event ' . $eventId . ': ' . $e->getMessage() );
			return new \WP_Error( 'khm_webhook_error', 'Webhook handling failed. Retry will occur.', array( 'status' => 500 ) );
		}

		return new \WP_REST_Response(
			array(
				'ok'     => true,
				'status' => 'processed',
				'id'     => $eventId,
				'type'   => $eventType,
			),
			200
		);
	}

	/**
	 * Built-in handling for common Stripe events: create/update orders and memberships.
	 *
	 * @param string $eventType Stripe event type.
	 * @param object $event     Parsed event object.
	 * @return void
	 */
	protected function handle_stripe_event( string $eventType, object $event ): void {
		$type = strtolower( $eventType );

		// Normalize data object.
		$obj = $event->data->object ?? (object) array();

		switch ( $type ) {
			case 'invoice.payment_succeeded':
				$this->handle_invoice_payment_succeeded( $obj );
				break;

			case 'invoice.finalized':
				$this->handle_invoice_finalized_or_updated( $obj );
				break;

			case 'invoice.updated':
				$this->handle_invoice_finalized_or_updated( $obj );
				break;

			case 'invoice.payment_failed':
				$this->handle_invoice_payment_failed( $obj );
				break;

			case 'customer.subscription.deleted':
			case 'customer.subscription.canceled':
				$this->handle_subscription_deleted( $obj );
				break;

			case 'customer.subscription.updated':
				$this->handle_subscription_updated( $obj );
				break;

			case 'charge.refunded':
				$this->handle_charge_refunded( $obj );
				break;

			case 'charge.failed':
				$this->handle_charge_failed( $obj );
				break;

			default:
				// No-op; extension hooks above can handle other types.
				break;
		}
	}

	/**
	 * Handle successful invoice payment events.
	 *
	 * @param object $invoice Stripe Invoice object.
	 * @return void
	 */
	protected function handle_invoice_payment_succeeded( object $invoice ): void {
		[$userId, $levelId] = $this->resolve_user_and_level( $invoice );
		if ( ! $userId || ! $levelId ) {
			do_action( 'khm_stripe_unresolved_context', 'invoice.payment_succeeded', $invoice );
			return;
		}

		$amount         = isset( $invoice->amount_paid ) ? ( (float) $invoice->amount_paid / 100.0 ) : 0.0;
		$subscriptionId = $invoice->subscription ?? null;
		$chargeId       = $invoice->charge ?? null;
		$txnId          = $chargeId ? $chargeId : ( $invoice->id ?? '' );

		// Ensure we don't duplicate order for the same txnId (separate from webhook idempotency).
		$existing = $txnId ? $this->orders->findByPaymentTransactionId( $txnId ) : null;

		// Extract discount metadata if present on invoice.
		$discountData = $this->extract_discount_metadata_from_invoice( $invoice );

		$data = array(
			'user_id'                     => (int) $userId,
			'membership_id'               => (int) $levelId,
			'total'                       => $amount,
			'gateway'                     => 'stripe',
			'status'                      => 'success',
			'payment_transaction_id'      => $txnId,
			'subscription_transaction_id' => $subscriptionId,
			'notes'                       => 'Stripe invoice payment succeeded',
		);

		// Merge in any discount metadata fields found.
		if ( ! empty( $discountData ) ) {
			$data = array_merge( $data, $discountData );
		}

		if ( $existing ) {
			$this->orders->update( (int) $existing->id, $data );
		} else {
			$this->orders->create( $data );
		}

		// Activate/extend membership.
		$this->memberships->assign(
			(int) $userId,
			(int) $levelId,
			array(
				'status' => 'active',
			)
		);

		do_action( 'khm_stripe_invoice_payment_succeeded_handled', $invoice, $data );
	}

	/**
	 * Handle invoice finalized/updated events to write back discount details before/without payment.
	 *
	 * @param object $invoice Stripe Invoice object.
	 * @return void
	 */
	protected function handle_invoice_finalized_or_updated( object $invoice ): void {
		$subscriptionId = $invoice->subscription ?? null;
		if ( ! $subscriptionId ) {
			return;
		}

		$discountData = $this->extract_discount_metadata_from_invoice( $invoice );
		if ( empty( $discountData ) ) {
			return;
		}

		// Try to update the most recent order for this subscription with discount details.
		$lastOrder = $this->orders->findLastBySubscriptionId( $subscriptionId );
		if ( $lastOrder ) {
			$this->orders->update( (int) $lastOrder->id, $discountData );
			do_action( 'khm_stripe_invoice_discount_updated', $invoice, $lastOrder->id, $discountData );
		}
	}

	/**
	 * Handle subscription updated events to reconcile recurring discount/trial metadata.
	 *
	 * @param object $subscription Stripe Subscription object.
	 * @return void
	 */
	protected function handle_subscription_updated( object $subscription ): void {
		$subscriptionId = $subscription->id ?? null;
		if ( ! $subscriptionId ) {
			return;
		}

		$update = array();

		// Discount on subscription (recurring vs once).
		if ( isset( $subscription->discount ) && isset( $subscription->discount->coupon ) ) {
			$update = array_merge( $update, $this->extract_discount_metadata_from_coupon( $subscription->discount->coupon, true ) );
		}

		// Trial information if present.
		if ( isset( $subscription->trial_end ) && isset( $subscription->trial_start ) ) {
			$trialSeconds = max( 0, (int) $subscription->trial_end - (int) $subscription->trial_start );
			$trialDays    = (int) floor( $trialSeconds / 86400 );
			$update['trial_days'] = $trialDays;
		}

		$lastOrder = $this->orders->findLastBySubscriptionId( $subscriptionId );
		if ( $lastOrder && ! empty( $update ) ) {
			$this->orders->update( (int) $lastOrder->id, $update );
			do_action( 'khm_stripe_subscription_updated_reconciled', $subscription, $lastOrder->id, $update );
		}

		[$userId, $levelId] = $this->resolve_user_and_level( $subscription );
		if ( ! $userId || ! $levelId ) {
			do_action( 'khm_stripe_unresolved_context', 'customer.subscription.updated', $subscription );
			return;
		}

		$billingUpdate = array();

		$plan = null;
		if ( isset( $subscription->plan ) ) {
			$plan = $subscription->plan;
		} elseif ( isset( $subscription->items->data[0]->plan ) ) {
			$plan = $subscription->items->data[0]->plan;
		} elseif ( isset( $subscription->items->data[0]->price ) ) {
			// Newer Stripe API returns price instead of plan.
			$plan = $subscription->items->data[0]->price;
		}

		if ( $plan ) {
			if ( isset( $plan->unit_amount ) ) {
				$billingUpdate['billing_amount'] = (float) $plan->unit_amount / 100.0;
			} elseif ( isset( $plan->amount ) ) {
				$billingUpdate['billing_amount'] = (float) $plan->amount / 100.0;
			}

			if ( isset( $plan->interval_count ) ) {
				$billingUpdate['cycle_number'] = (int) $plan->interval_count;
			}

			if ( isset( $plan->interval ) ) {
				$interval = strtolower( (string) $plan->interval );
				$billingUpdate['cycle_period'] = ucfirst( $interval );
			}
		}

		if ( ! empty( $billingUpdate ) ) {
			$this->memberships->updateBillingProfile( (int) $userId, (int) $levelId, $billingUpdate );
		}

		if ( isset( $subscription->current_period_end ) ) {
			$periodEnd = (int) $subscription->current_period_end;
			$periodEnd = max( 0, $periodEnd );
			if ( $periodEnd > 0 ) {
				$endDate = new DateTime( '@' . $periodEnd );
				$endDate->setTimezone( new DateTimeZone( 'UTC' ) );
				$this->memberships->updateEndDate( (int) $userId, (int) $levelId, $endDate );
			}
		}

		if ( isset( $subscription->status ) ) {
			$status = (string) $subscription->status;
			if ( in_array( $status, array( 'past_due', 'unpaid' ), true ) ) {
				$this->memberships->markPastDue( (int) $userId, (int) $levelId, 'Stripe subscription status ' . $status );
			} elseif ( in_array( $status, array( 'canceled', 'incomplete_expired' ), true ) ) {
				$this->memberships->cancel( (int) $userId, (int) $levelId, 'Stripe subscription cancelled' );
			} elseif ( in_array( $status, array( 'active', 'trialing' ), true ) ) {
				$this->memberships->setStatus( (int) $userId, (int) $levelId, 'active', 'Stripe subscription active' );
			}
		}

			do_action( 'khm_stripe_subscription_status_synchronised', $subscription, (int) $userId, (int) $levelId );
		}

	/**
	 * Extract discount metadata fields from an invoice object.
	 *
	 * @param object $invoice Stripe Invoice object.
	 * @return array<string,mixed> Order fields to update
	 */
	protected function extract_discount_metadata_from_invoice( object $invoice ): array {
		$meta = array();

		// total_discount_amounts is an array of {amount, discount}
		if ( isset( $invoice->total_discount_amounts ) && is_array( $invoice->total_discount_amounts ) ) {
			$total = 0.0;
			foreach ( $invoice->total_discount_amounts as $row ) {
				if ( isset( $row->amount ) ) {
					$total += ( (float) $row->amount / 100.0 );
				}
			}
			if ( $total > 0 ) {
				$meta['discount_amount'] = round( $total, 2 );
			}
		}

		// coupon/promotion code reference
		if ( isset( $invoice->discount ) && isset( $invoice->discount->coupon ) ) {
			$meta = array_merge( $meta, $this->extract_discount_metadata_from_coupon( $invoice->discount->coupon ) );
		}
		// Some API versions expose discounts[]
		if ( empty( $meta['discount_code'] ) && isset( $invoice->discounts ) && is_array( $invoice->discounts ) && isset( $invoice->discounts[0]->coupon ) ) {
			$meta = array_merge( $meta, $this->extract_discount_metadata_from_coupon( $invoice->discounts[0]->coupon ) );
		}

		return $meta;
	}

	/**
	 * Extract normalized discount metadata from a Stripe coupon object.
	 *
	 * @param object $coupon Stripe Coupon object.
	 * @param bool   $assumeRecurring Whether to mark recurring fields for subscription context.
	 * @return array<string,mixed>
	 */
	protected function extract_discount_metadata_from_coupon( object $coupon, bool $assumeRecurring = false ): array {
		$meta = array();
		$meta['discount_code'] = $coupon->id ?? null;

		if ( isset( $coupon->percent_off ) && $coupon->percent_off ) {
			$meta['recurring_discount_type'] = 'percent';
			$meta['recurring_discount_amount'] = (float) $coupon->percent_off;
		} elseif ( isset( $coupon->amount_off ) && $coupon->amount_off ) {
			$meta['recurring_discount_type'] = 'amount';
			$meta['recurring_discount_amount'] = round( (float) $coupon->amount_off / 100.0, 2 );
		}

		// Duration handling: once vs repeating/forever
		if ( isset( $coupon->duration ) ) {
			if ( $coupon->duration === 'once' ) {
				$meta['first_payment_only'] = 1;
			} else {
				// repeating or forever implies recurring discount beyond first payment
				// keep recurring_discount_type/amount as-is
			}
		}

		// If not recurring context and only a single discount applied, prefer discount_amount on invoice handler.
		if ( ! $assumeRecurring ) {
			unset( $meta['recurring_discount_type'], $meta['recurring_discount_amount'] );
		}

		// Cleanup nulls
		return array_filter( $meta, static function ( $v ) { return $v !== null; } );
	}

	/**
	 * Handle failed invoice payment events.
	 *
	 * @param object $invoice Stripe Invoice object.
	 * @return void
	 */
	protected function handle_invoice_payment_failed( object $invoice ): void {
		[$userId, $levelId] = $this->resolve_user_and_level( $invoice );
		if ( ! $userId || ! $levelId ) {
			do_action( 'khm_stripe_unresolved_context', 'invoice.payment_failed', $invoice );
			return;
		}

		$amount         = isset( $invoice->amount_due ) ? ( (float) $invoice->amount_due / 100.0 ) : 0.0;
		$subscriptionId = $invoice->subscription ?? null;
		$txnId          = $invoice->id ?? '';
		$failureCode    = '';
		$failureMessage = '';

		if ( isset( $invoice->last_payment_error ) ) {
			$error = $invoice->last_payment_error;
			if ( isset( $error->code ) ) {
				$failureCode = (string) $error->code;
			}
			if ( isset( $error->message ) ) {
				$failureMessage = (string) $error->message;
			} elseif ( isset( $error->decline_code ) ) {
				$failureMessage = (string) $error->decline_code;
			}
		}

		$notes = 'Stripe invoice payment failed';
		if ( $failureMessage ) {
			$notes .= ': ' . $failureMessage;
		}

		$existing = $txnId ? $this->orders->findByPaymentTransactionId( $txnId ) : null;

		$data = array(
			'user_id'                     => (int) $userId,
			'membership_id'               => (int) $levelId,
			'total'                       => $amount,
			'gateway'                     => 'stripe',
			'status'                      => 'failed',
			'payment_transaction_id'      => $txnId,
			'subscription_transaction_id' => $subscriptionId,
			'notes'                       => $notes,
			'failure_code'                => $failureCode ?: null,
			'failure_message'             => $failureMessage ?: null,
			'failure_at'                  => current_time( 'mysql', true ),
		);

		if ( $existing ) {
			$this->orders->update( (int) $existing->id, $data );
		} else {
			$this->orders->create( $data );
		}

		$this->memberships->markPastDue( (int) $userId, (int) $levelId, 'Stripe invoice payment failed' );

		do_action( 'khm_subscription_payment_failed', $invoice, (int) $userId, (int) $levelId, $data );
		do_action( 'khm_stripe_invoice_payment_failed_handled', $invoice, $data );
	}

	/**
	 * Handle subscription deleted/canceled events.
	 *
	 * @param object $subscription Stripe Subscription object.
	 * @return void
	 */
	protected function handle_subscription_deleted( object $subscription ): void {
		[$userId, $levelId] = $this->resolve_user_and_level( $subscription );
		if ( ! $userId || ! $levelId ) {
			do_action( 'khm_stripe_unresolved_context', 'customer.subscription.deleted', $subscription );
			return;
		}

		// Cancel membership.
		$this->memberships->cancel( (int) $userId, (int) $levelId, 'Stripe subscription deleted' );

		// Update last order for this subscription to cancelled.
		$subId = $subscription->id ?? '';
		if ( $subId ) {
			$lastOrder = $this->orders->findLastBySubscriptionId( $subId );
			if ( $lastOrder ) {
				$this->orders->updateStatus( (int) $lastOrder->id, 'cancelled', 'Subscription cancelled' );
			}
		}

		do_action( 'khm_stripe_subscription_deleted_handled', $subscription, $userId, $levelId );
	}

	/**
	 * Handle charge refunded events.
	 *
	 * @param object $charge Stripe Charge object.
	 * @return void
	 */
	protected function handle_charge_refunded( object $charge ): void {
		$chargeId = $charge->id ?? '';
		if ( ! $chargeId ) {
			return;
		}
		$order = $this->orders->findByPaymentTransactionId( $chargeId );
		if ( $order ) {
			$refundAmount = isset( $charge->amount_refunded ) ? ( (float) $charge->amount_refunded / 100.0 ) : 0.0;
			$reason       = '';

			if ( isset( $charge->refunds ) && isset( $charge->refunds->data ) && is_array( $charge->refunds->data ) && isset( $charge->refunds->data[0]->reason ) ) {
				$reason = (string) $charge->refunds->data[0]->reason;
			} elseif ( isset( $charge->reason ) ) {
				$reason = (string) $charge->reason;
			}

			$refundedAt = isset( $charge->created ) ? (int) $charge->created : time();
			$refundedAt = gmdate( 'Y-m-d H:i:s', $refundedAt );

			$notes = 'Charge refunded at Stripe';
			if ( $reason ) {
				$notes .= ': ' . $reason;
			}

			$this->orders->update(
				(int) $order->id,
				array(
					'status'        => 'refunded',
					'refund_amount' => $refundAmount,
					'refund_reason' => $reason ?: null,
					'refunded_at'   => $refundedAt,
					'notes'         => $notes,
				)
			);

			if ( $refundAmount >= (float) $order->total && ! empty( $order->membership_id ) ) {
				$this->memberships->cancel(
					(int) $order->user_id,
					(int) $order->membership_id,
					'Stripe refund processed'
				);
			}

			do_action( 'khm_stripe_charge_refunded_handled', $charge, $order->id );
			do_action( 'khm_subscription_refunded', $charge, $order );
		}
	}

	/**
	 * Handle failed charge events (typically initial checkout or off-cycle retries).
	 *
	 * @param object $charge Stripe Charge object.
	 * @return void
	 */
	protected function handle_charge_failed( object $charge ): void {
		[$userId, $levelId] = $this->resolve_user_and_level( $charge );
		if ( ! $userId || ! $levelId ) {
			do_action( 'khm_stripe_unresolved_context', 'charge.failed', $charge );
			return;
		}

		$amount         = isset( $charge->amount ) ? ( (float) $charge->amount / 100.0 ) : 0.0;
		$subscriptionId = $charge->subscription ?? null;
		$txnId          = $charge->id ?? '';
		$failureCode    = isset( $charge->failure_code ) ? (string) $charge->failure_code : '';
		$failureMessage = '';

		if ( isset( $charge->failure_message ) ) {
			$failureMessage = (string) $charge->failure_message;
		} elseif ( isset( $charge->outcome->seller_message ) ) {
			$failureMessage = (string) $charge->outcome->seller_message;
		}

		$notes = 'Stripe charge failed';
		if ( $failureMessage ) {
			$notes .= ': ' . $failureMessage;
		}

		$existing = $txnId ? $this->orders->findByPaymentTransactionId( $txnId ) : null;

		$data = array(
			'user_id'                     => (int) $userId,
			'membership_id'               => (int) $levelId,
			'total'                       => $amount,
			'gateway'                     => 'stripe',
			'status'                      => 'failed',
			'payment_transaction_id'      => $txnId,
			'subscription_transaction_id' => $subscriptionId,
			'notes'                       => $notes,
			'failure_code'                => $failureCode ?: null,
			'failure_message'             => $failureMessage ?: null,
			'failure_at'                  => current_time( 'mysql', true ),
		);

		if ( $existing ) {
			$this->orders->update( (int) $existing->id, $data );
		} else {
			$this->orders->create( $data );
		}

		$this->memberships->markPastDue( (int) $userId, (int) $levelId, 'Stripe charge failed' );

		// Reuse invoice failure hooks for downstream notifications.
		$customerEmail = null;
		if ( isset( $charge->billing_details ) && isset( $charge->billing_details->email ) ) {
			$customerEmail = (string) $charge->billing_details->email;
		} elseif ( isset( $charge->receipt_email ) ) {
			$customerEmail = (string) $charge->receipt_email;
		}

		$invoiceLike = (object) array(
			'id'             => $txnId,
			'amount_due'     => $charge->amount ?? 0,
			'customer_email' => $customerEmail,
			'metadata'       => $charge->metadata ?? null,
		);

		do_action( 'khm_subscription_payment_failed', $charge, (int) $userId, (int) $levelId, $data );
		do_action( 'khm_stripe_charge_failed_handled', $charge, $data );
		do_action( 'khm_stripe_invoice_payment_failed_handled', $invoiceLike, $data );
	}

	/**
	 * Attempt to resolve WP user ID and membership level ID from a Stripe object.
	 *
	 * - Checks metadata first (user_id, membership_id).
	 * - Allows filters to supply mapping based on customer/plan/price/product.
	 *
	 * @param object $obj Generic Stripe object which may contain metadata.
	 * @return array{0:int|null,1:int|null} Tuple of [user_id, level_id].
	 */
	protected function resolve_user_and_level( object $obj ): array {
		$userId  = null;
		$levelId = null;

		// From metadata.
		if ( isset( $obj->metadata ) ) {
			$meta = (array) $obj->metadata;
			if ( isset( $meta['user_id'] ) ) {
				$userId = (int) $meta['user_id']; }
			if ( isset( $meta['membership_id'] ) ) {
				$levelId = (int) $meta['membership_id']; }
		}

		// Fallback: allow filters to map from Stripe object.
		if ( ! $userId ) {
			$userId = apply_filters( 'khm_stripe_map_customer_to_user', null, $obj );
			if ( $userId !== null ) {
				$userId = (int) $userId; }
			// Fallback by email if present on object (e.g., invoice.customer_email).
			if ( ! $userId && isset( $obj->customer_email ) ) {
				$user = function_exists( 'get_user_by' ) ? get_user_by( 'email', $obj->customer_email ) : null;
				if ( $user && isset( $user->ID ) ) {
					$userId = (int) $user->ID;
				}
			}
		}

		if ( ! $levelId ) {
			// Try common invoice path plan->id like pmpro_level_3.
			$planId = null;
			if ( isset( $obj->lines->data[0]->plan->id ) ) {
				$planId = (string) $obj->lines->data[0]->plan->id;
				if ( preg_match( '/.*?(\d+)$/', $planId, $m ) ) {
					$levelId = (int) $m[1];
				}
			}
			if ( ! $levelId ) {
				$levelId = apply_filters( 'khm_stripe_map_plan_to_level', null, $obj, $planId );
				if ( $levelId !== null ) {
					$levelId = (int) $levelId; }
			}
		}

		return array( $userId ? $userId : null, $levelId ? $levelId : null );
	}
}
