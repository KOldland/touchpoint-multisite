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
				'callback'            => function( $request ) {
					return $this->handle_stripe( $request, 'all' );
				},
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'khm/v1',
			'/webhooks/stripe/marketing',
			array(
				'methods'             => 'POST',
				'callback'            => function( $request ) {
					return $this->handle_stripe( $request, 'marketing' );
				},
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'khm/v1',
			'/webhooks/stripe/billing',
			array(
				'methods'             => 'POST',
				'callback'            => function( $request ) {
					return $this->handle_stripe( $request, 'billing' );
				},
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
	public function handle_stripe( $request, string $scope = 'all' ) {
		$ip = $this->get_request_ip( $request );

		$secret = $this->resolveWebhookSecret( $scope );
		if ( empty( $secret ) ) {
			return new \WP_Error( 'khm_missing_secret', 'Stripe webhook is not configured (missing secret).', array( 'status' => 500 ) );
		}

		$payload = $request->get_body();
		$this->emit_webhook_telemetry(
			'webhook.received',
			array(
				'ip'    => $ip,
				'path'  => '/wp-json/khm/v1/webhooks/stripe',
				'scope' => $scope,
			)
		);

		$block = $this->get_active_ip_block( $ip );
		if ( $block['blocked'] ) {
			$this->emit_webhook_telemetry(
				'webhook.rate_limit.blocked',
				array(
					'ip'    => $ip,
					'scope' => $scope,
					'path'  => '/wp-json/khm/v1/webhooks/stripe',
					'code'  => 429,
					'reason'=> (string) $block['reason'],
				)
			);
			return $this->build_rate_limited_response( (int) $block['retry_after'], (string) $block['reason'] );
		}

		// Collect all headers for signature extraction; normalize in verifier.
		$headers = method_exists( $request, 'get_headers' ) ? (array) $request->get_headers() : array();

		// Verify signature.
		$verified_event = $this->verifier->verify( $payload, $headers, $secret );
		if ( ! $verified_event ) {
			$badsig = $this->increment_badsig_counter( $ip );
			$this->emit_webhook_telemetry(
				'webhook.invalid_signature',
				array(
					'ip'    => $ip,
					'scope' => $scope,
					'path'  => '/wp-json/khm/v1/webhooks/stripe',
					'count' => $badsig,
					'code'  => 400,
				)
			);
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

		$throttle = $this->increment_ip_rate_counter( $ip );
		if ( $throttle['limited'] ) {
			$this->emit_webhook_telemetry(
				'webhook.rate_limit.exceeded',
				array(
					'ip'         => $ip,
					'scope'      => $scope,
					'path'       => '/wp-json/khm/v1/webhooks/stripe',
					'event_type' => $eventType,
					'count'      => (int) $throttle['count'],
					'limit'      => (int) $throttle['limit'],
					'code'       => 429,
				)
			);
			return $this->build_rate_limited_response( (int) $throttle['retry_after'], 'rate_limit_exceeded' );
		}

		if ( ! $this->isEventAllowedForScope( $eventType, $scope ) ) {
			$this->emit_webhook_telemetry(
				'webhook.processed',
				array(
					'ip'         => $ip,
					'path'       => '/wp-json/khm/v1/webhooks/stripe',
					'event_type' => $eventType,
					'code'       => 200,
					'status'     => 'ignored',
				)
			);
			return new \WP_REST_Response(
				array(
					'ok'     => true,
					'status' => 'ignored',
					'id'     => $eventId,
					'type'   => $eventType,
					'scope'  => $scope,
				),
				200
			);
		}

		// Idempotency check.
		if ( $this->idempotency->hasProcessed( $eventId ) ) {
			// Already processed; respond 200 to avoid retries.
			$this->emit_webhook_telemetry(
				'webhook.processed',
				array(
					'ip'         => $ip,
					'path'       => '/wp-json/khm/v1/webhooks/stripe',
					'event_type' => $eventType,
					'code'       => 200,
					'status'     => 'duplicate',
				)
			);
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

		$this->emit_webhook_telemetry(
			'webhook.processed',
			array(
				'ip'         => $ip,
				'path'       => '/wp-json/khm/v1/webhooks/stripe',
				'event_type' => $eventType,
				'code'       => 200,
				'status'     => 'processed',
			)
		);

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

	private function resolveWebhookSecret( string $scope ): string {
		$scope = strtolower( trim( $scope ) );
		if ( $scope === 'marketing' ) {
			return $this->resolve_secret_value(
				array( 'KH_STRIPE_WEBHOOK_SECRET_MARKETING', 'KH_STRIPE_WEBHOOK_SECRET' )
			);
		}

		if ( $scope === 'billing' ) {
			return $this->resolve_secret_value(
				array( 'KH_STRIPE_WEBHOOK_SECRET_BILLING', 'KH_STRIPE_WEBHOOK_SECRET' )
			);
		}

		return $this->resolve_secret_value( array( 'KH_STRIPE_WEBHOOK_SECRET' ) );
	}

	private function isEventAllowedForScope( string $eventType, string $scope ): bool {
		$eventType = strtolower( $eventType );
		if ( $scope === 'marketing' ) {
			return in_array( $eventType, [ 'product.updated', 'product.created' ], true );
		}

		if ( $scope === 'billing' ) {
			return ! in_array( $eventType, [ 'product.updated', 'product.created' ], true );
		}

		return true;
	}

	private function resolve_secret_value( array $keys ): string {
		foreach ( $keys as $key ) {
			if ( function_exists( 'khm_get_stripe_secret' ) ) {
				$secret = (string) ( khm_get_stripe_secret( $key ) ?? '' );
				if ( $secret !== '' ) {
					return $secret;
				}
			}
		}

		return '';
	}

	private function get_request_ip( $request ): string {
		$ipHeaders = array(
			'x-forwarded-for',
			'cf-connecting-ip',
			'x-real-ip',
			'client-ip',
		);

		foreach ( $ipHeaders as $header ) {
			$value = $this->request_header( $request, $header );
			if ( $value === '' ) {
				continue;
			}

			$candidate = trim( explode( ',', $value )[0] ?? '' );
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		if ( isset( $_SERVER['REMOTE_ADDR'] ) && filter_var( (string) $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP ) ) {
			return (string) $_SERVER['REMOTE_ADDR'];
		}

		return 'unknown';
	}

	private function request_header( $request, string $name ): string {
		if ( method_exists( $request, 'get_header' ) ) {
			$value = (string) $request->get_header( $name );
			if ( $value !== '' ) {
				return $value;
			}
		}

		if ( method_exists( $request, 'get_headers' ) ) {
			$headers = (array) $request->get_headers();
			$lower   = strtolower( $name );
			foreach ( $headers as $k => $v ) {
				if ( strtolower( (string) $k ) !== $lower ) {
					continue;
				}
				if ( is_array( $v ) ) {
					return isset( $v[0] ) ? (string) $v[0] : '';
				}
				return (string) $v;
			}
		}

		return '';
	}

	private function should_enable_rate_limit_test_mode(): bool {
		$raw = function_exists( 'khm_get_stripe_secret' )
			? (string) ( khm_get_stripe_secret( 'KHM_WEBHOOK_RATE_LIMIT_TEST_MODE' ) ?? '' )
			: '';
		return in_array( strtolower( $raw ), array( '1', 'true', 'yes', 'on' ), true );
	}

	private function get_rate_limit_config(): array {
		$testMode = $this->should_enable_rate_limit_test_mode();

		$defaults = array(
			'requests_per_minute' => 60,
			'badsig_threshold'    => 10,
			'badsig_window'       => 60,
			'block_base_ttl'      => 60,
			'block_max_ttl'       => 3600,
		);

		if ( $testMode ) {
			$defaults['requests_per_minute'] = 5;
			$defaults['badsig_threshold']    = 3;
			$defaults['block_base_ttl']      = 15;
			$defaults['block_max_ttl']       = 120;
		}

		$config = array(
			'requests_per_minute' => (int) get_option( 'khm_webhook_rate_limit_per_minute', $defaults['requests_per_minute'] ),
			'badsig_threshold'    => (int) get_option( 'khm_webhook_badsig_threshold', $defaults['badsig_threshold'] ),
			'badsig_window'       => (int) get_option( 'khm_webhook_badsig_window', $defaults['badsig_window'] ),
			'block_base_ttl'      => (int) get_option( 'khm_webhook_block_base_ttl', $defaults['block_base_ttl'] ),
			'block_max_ttl'       => (int) get_option( 'khm_webhook_block_max_ttl', $defaults['block_max_ttl'] ),
		);

		foreach ( $config as $key => $value ) {
			$config[ $key ] = max( 1, (int) $value );
		}

		return $config;
	}

	private function get_active_ip_block( string $ip ): array {
		$blockKey   = 'khm_webhook_block:' . md5( $ip );
		$blockUntil = (int) get_transient( $blockKey );
		$now        = time();

		if ( $blockUntil > $now ) {
			return array(
				'blocked'     => true,
				'retry_after' => max( 1, $blockUntil - $now ),
				'reason'      => 'ip_blocked',
			);
		}

		return array(
			'blocked'     => false,
			'retry_after' => 0,
			'reason'      => '',
		);
	}

	private function increment_badsig_counter( string $ip ): int {
		$config     = $this->get_rate_limit_config();
		$window     = max( 1, (int) $config['badsig_window'] );
		$counterKey = 'khm_webhook_badsig:' . md5( $ip );
		$count      = (int) get_transient( $counterKey );
		$count++;
		set_transient( $counterKey, $count, $window );

		$total = (int) get_transient( 'khm_webhook_badsig_total' );
		set_transient( 'khm_webhook_badsig_total', $total + 1, 3600 );

		if ( $count > (int) $config['badsig_threshold'] ) {
			$this->apply_progressive_block( $ip, 'invalid_signature' );
		}

		return $count;
	}

	private function increment_ip_rate_counter( string $ip ): array {
		$config   = $this->get_rate_limit_config();
		$limit    = max( 1, (int) $config['requests_per_minute'] );
		$window   = 60;
		$bucketTs = (int) floor( time() / $window ) * $window;
		$key      = 'khm_webhook_rate:' . md5( $ip ) . ':' . $bucketTs;
		$count    = (int) get_transient( $key );
		$count++;
		set_transient( $key, $count, $window + 5 );

		if ( $count > $limit ) {
			$retryAfter = max( 1, ( $bucketTs + $window ) - time() );
			$this->apply_progressive_block( $ip, 'rate_limit_exceeded' );
			return array(
				'limited'     => true,
				'count'       => $count,
				'limit'       => $limit,
				'retry_after' => $retryAfter,
			);
		}

		return array(
			'limited'     => false,
			'count'       => $count,
			'limit'       => $limit,
			'retry_after' => 0,
		);
	}

	private function apply_progressive_block( string $ip, string $reason ): void {
		$config    = $this->get_rate_limit_config();
		$levelKey  = 'khm_webhook_block_level:' . md5( $ip );
		$level     = (int) get_transient( $levelKey );
		$level     = max( 1, $level + 1 );
		$base      = max( 1, (int) $config['block_base_ttl'] );
		$max       = max( $base, (int) $config['block_max_ttl'] );
		$ttl       = min( $max, (int) ( $base * ( 5 ** ( $level - 1 ) ) ) );
		$blockTill = time() + $ttl;

		set_transient( $levelKey, $level, $max );
		set_transient( 'khm_webhook_block:' . md5( $ip ), $blockTill, $ttl );

		$this->emit_webhook_telemetry(
			'webhook.rate_limit.blocked',
			array(
				'ip'          => $ip,
				'path'        => '/wp-json/khm/v1/webhooks/stripe',
				'reason'      => $reason,
				'block_level' => $level,
				'block_ttl'   => $ttl,
				'code'        => 429,
			)
		);
	}

	private function build_rate_limited_response( int $retryAfter, string $reason ): \WP_REST_Response {
		$response = new \WP_REST_Response(
			array(
				'ok'          => false,
				'error'       => 'rate_limited',
				'reason'      => $reason,
				'retry_after' => max( 1, $retryAfter ),
			),
			429
		);

		if ( method_exists( $response, 'header' ) ) {
			$response->header( 'Retry-After', (string) max( 1, $retryAfter ) );
		}

		return $response;
	}

	private function emit_webhook_telemetry( string $metric, array $context = array() ): void {
		do_action( 'khm_membership_webhook_telemetry', $metric, $context );
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

			case 'checkout.session.completed':
				$this->handle_checkout_session_completed( $obj );
				break;

			case 'product.updated':
				$this->handle_product_updated( $obj );
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

	/**
	 * Handle Stripe Checkout Session completed events for subscriptions.
	 *
	 * @param object $session Stripe Checkout Session object.
	 * @return void
	 */
	protected function handle_checkout_session_completed( object $session ): void {
		if ( isset( $session->mode ) && strtolower( (string) $session->mode ) !== 'subscription' ) {
			return;
		}

		$meta    = isset( $session->metadata ) ? (array) $session->metadata : array();
		$levelId = (int) ( $meta['membership_level_id'] ?? $meta['membership_id'] ?? 0 );
		$userId  = (int) ( $meta['user_id'] ?? 0 );
		$guestEmail = $this->extract_checkout_session_email( $session, $meta );
		$createAccount = ! empty( $meta['create_account'] ) && in_array( (string) $meta['create_account'], array( '1', 'true', 'yes' ), true );

		if ( ! $levelId ) {
			do_action( 'khm_stripe_unresolved_context', 'checkout.session.completed', $session );
			return;
		}

		if ( function_exists( 'khm_get_membership_level' ) ) {
			$level = khm_get_membership_level( $levelId );
			if ( ! $level ) {
				do_action( 'khm_stripe_unresolved_context', 'checkout.session.completed', $session );
				return;
			}
		}

		if ( ! $userId && $guestEmail ) {
			$user = get_user_by( 'email', $guestEmail );
			if ( $user && isset( $user->ID ) ) {
				$userId = (int) $user->ID;
			}
		}

		if ( ! $userId && $guestEmail ) {
			$userId = $this->create_user_from_email( $guestEmail, $meta, $createAccount );
		}

		if ( ! $userId ) {
			do_action( 'khm_stripe_unresolved_context', 'checkout.session.completed', $session );
			return;
		}

		$this->apply_checkout_profile_meta( (int) $userId, $meta );

		$status = $this->resolve_subscription_status_from_session( $session );

		// Extract Stripe IDs for storage
		$customerId     = $session->customer ?? null;
		$subscriptionId = $session->subscription ?? null;

		$assignOptions = array(
			'status' => $status,
		);

		$appliedPromo = isset( $meta['khm_applied_promo'] ) ? sanitize_text_field( (string) $meta['khm_applied_promo'] ) : '';
		$appliedPromoCode = isset( $meta['khm_applied_promo_code'] ) ? sanitize_text_field( (string) $meta['khm_applied_promo_code'] ) : '';
		$stripePromotionCode = isset( $meta['khm_stripe_promotion_code'] ) ? sanitize_text_field( (string) $meta['khm_stripe_promotion_code'] ) : '';
		$appliedPromoType = isset( $meta['khm_applied_promo_type'] ) ? sanitize_text_field( (string) $meta['khm_applied_promo_type'] ) : '';
		$appliedPromoAmount = isset( $meta['khm_applied_promo_amount'] ) ? (float) $meta['khm_applied_promo_amount'] : 0.0;
		if ( $appliedPromo !== '' ) {
			$assignOptions['applied_promo'] = $appliedPromo;
		}
		if ( $appliedPromoCode !== '' ) {
			$assignOptions['applied_promo_code'] = $appliedPromoCode;
		}
		if ( $stripePromotionCode !== '' ) {
			$assignOptions['stripe_promotion_code'] = $stripePromotionCode;
		}
		if ( $appliedPromoType !== '' ) {
			$assignOptions['applied_promo_type'] = $appliedPromoType;
		}
		if ( $appliedPromoAmount > 0 ) {
			$assignOptions['applied_promo_amount'] = $appliedPromoAmount;
		}

		// Add Stripe IDs if available (will be stored via user meta in persist_stripe_ids)
		if ( $customerId ) {
			$assignOptions['stripe_customer_id'] = (string) $customerId;
		}
		if ( $subscriptionId ) {
			$assignOptions['stripe_subscription_id'] = (string) $subscriptionId;
		}

		$this->memberships->assign(
			(int) $userId,
			(int) $levelId,
			$assignOptions
		);

		$this->persist_stripe_ids( (int) $userId, $session );

		do_action( 'khm_stripe_checkout_session_completed_handled', $session, (int) $userId, (int) $levelId, $status );
	}

	/**
	 * Handle Stripe product.updated events by syncing marketing copy into level metadata.
	 *
	 * @param object $product Stripe Product object.
	 * @return void
	 */
	protected function handle_product_updated( object $product ): void {
		$productId = isset( $product->id ) ? trim( (string) $product->id ) : '';
		if ( $productId === '' ) {
			return;
		}
		if ( ! \KHM\Services\StripeMarketingImporter::isValidProductId( $productId ) ) {
			error_log( 'Stripe product.updated ignored due to invalid product id format: ' . $productId );
			return;
		}

		$meta = isset( $product->metadata ) ? $product->metadata : null;
		$levelId = null;
		if ( is_object( $meta ) && isset( $meta->wp_level_id ) ) {
			$levelId = (int) $meta->wp_level_id;
		} elseif ( is_array( $meta ) && isset( $meta['wp_level_id'] ) ) {
			$levelId = (int) $meta['wp_level_id'];
		}

		$args = [ $productId, (int) ( $levelId ?? 0 ) ];
		$hook = 'khm_import_stripe_marketing_product_updated';
		$queueKey = 'khm_stripe_marketing_queue_lock_' . md5( $productId );
		$recentlyQueued = function_exists( 'get_transient' ) ? (int) get_transient( $queueKey ) : 0;
		if ( $recentlyQueued > 0 && ( time() - $recentlyQueued ) < 10 ) {
			return;
		}

		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_single_event' ) ) {
			return;
		}

		if ( ! wp_next_scheduled( $hook, $args ) ) {
			wp_schedule_single_event( time() + 5, $hook, $args );
		}
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $queueKey, time(), 15 );
		}
		do_action( 'khm_stripe_product_updated_import_queued', $productId, $levelId );
	}

	/**
	 * Extract customer email from a checkout session.
	 *
	 * @param object $session Stripe Checkout Session object.
	 * @param array<string,mixed> $metadata Session metadata.
	 * @return string
	 */
	private function extract_checkout_session_email( object $session, array $metadata = array() ): string {
		if ( isset( $metadata['guest_email'] ) ) {
			$email = sanitize_email( (string) $metadata['guest_email'] );
			if ( $email && is_email( $email ) ) {
				return $email;
			}
		}
		if ( isset( $session->customer_details ) && isset( $session->customer_details->email ) ) {
			return (string) $session->customer_details->email;
		}
		if ( isset( $session->customer_email ) ) {
			return (string) $session->customer_email;
		}
		return '';
	}

	/**
	 * Create a WordPress user for a checkout session email.
	 *
	 * @param string $email
	 * @param array<string,mixed> $metadata
	 * @param bool $createAccount
	 * @return int|null
	 */
	private function create_user_from_email( string $email, array $metadata = array(), bool $createAccount = false ): ?int {
		$email = sanitize_email( $email );
		if ( ! $email || ! is_email( $email ) ) {
			return null;
		}

		$existing = email_exists( $email );
		if ( $existing ) {
			return (int) $existing;
		}

		$password = wp_generate_password();
		$userId   = wp_create_user( $email, $password, $email );
		if ( is_wp_error( $userId ) ) {
			error_log( 'Stripe checkout user create error: ' . $userId->get_error_message() );
			return null;
		}
		$wpUser = new \WP_User( (int) $userId );
		if ( $wpUser->exists() && empty( $wpUser->roles ) ) {
			$wpUser->set_role( get_option( 'default_role', 'subscriber' ) );
		}

		$this->apply_checkout_profile_meta( (int) $userId, $metadata );
		if ( $createAccount ) {
			update_user_meta( (int) $userId, 'khm_guest_account', 1 );
			update_user_meta( (int) $userId, 'khm_guest_created_at', time() );
			update_user_meta( (int) $userId, 'khm_guest_origin', 'stripe_checkout' );
			$this->send_password_set_email( (int) $userId, $email );
		}

		return (int) $userId;
	}

	/**
	 * Apply profile fields from checkout metadata into user meta.
	 *
	 * @param int $userId
	 * @param array<string,mixed> $metadata
	 * @return void
	 */
	private function apply_checkout_profile_meta( int $userId, array $metadata ): void {
		$firstName = sanitize_text_field( (string) ( $metadata['profile_first_name'] ?? '' ) );
		$lastName = sanitize_text_field( (string) ( $metadata['profile_last_name'] ?? '' ) );
		$mobile = sanitize_text_field( (string) ( $metadata['profile_mobile'] ?? '' ) );
		$jobTitle = sanitize_text_field( (string) ( $metadata['profile_job_title'] ?? '' ) );
		$company = sanitize_text_field( (string) ( $metadata['profile_company'] ?? '' ) );
		$marketingOptInProvided = array_key_exists( 'profile_marketing_optin', $metadata );
		$marketingOptIn = ! empty( $metadata['profile_marketing_optin'] );

		if ( $firstName !== '' ) {
			update_user_meta( $userId, 'first_name', $firstName );
		}
		if ( $lastName !== '' ) {
			update_user_meta( $userId, 'last_name', $lastName );
		}
		if ( $mobile !== '' ) {
			update_user_meta( $userId, 'mobile', $mobile );
		}
		if ( $jobTitle !== '' ) {
			update_user_meta( $userId, 'job_title', $jobTitle );
		}
		if ( $company !== '' ) {
			update_user_meta( $userId, 'company', $company );
		}
		if ( $marketingOptInProvided ) {
			update_user_meta( $userId, 'marketing_opt_in', $marketingOptIn ? 1 : 0 );
		}
	}

	/**
	 * Send secure password set email for account claim.
	 *
	 * @param int $userId
	 * @param string $email
	 * @return void
	 */
	private function send_password_set_email( int $userId, string $email ): void {
		$user = get_user_by( 'id', $userId );
		if ( ! $user || ! isset( $user->user_login ) ) {
			return;
		}

		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			wp_new_user_notification( $userId, null, 'user' );
			return;
		}

		$resetLink = network_site_url(
			'wp-login.php?action=rp&key=' . rawurlencode( (string) $key ) . '&login=' . rawurlencode( (string) $user->user_login ),
			'login'
		);

		$subject = __( 'Set your password', 'khm-membership' );
		$message = sprintf(
			/* translators: %s password setup URL */
			__( "Thanks for your purchase. We've created an account for you.\nSet your password here: %s", 'khm-membership' ),
			$resetLink
		);

		wp_mail( $email, $subject, $message );
	}

	/**
	 * Determine subscription status for membership assignment.
	 *
	 * @param object $session Stripe Checkout Session object.
	 * @return string
	 */
	private function resolve_subscription_status_from_session( object $session ): string {
		$subscriptionId = $session->subscription ?? '';
		if ( ! $subscriptionId ) {
			return 'active';
		}

		$subscription = $this->retrieve_stripe_subscription( (string) $subscriptionId );
		if ( $subscription && isset( $subscription->status ) ) {
			$status = (string) $subscription->status;
			if ( $status === 'trialing' ) {
				return 'trialing';
			}
			if ( $status === 'active' ) {
				return 'active';
			}
		}

		return 'active';
	}

	/**
	 * Retrieve subscription details from Stripe.
	 *
	 * @param string $subscriptionId
	 * @return object|null
	 */
	private function retrieve_stripe_subscription( string $subscriptionId ): ?object {
		$secret = function_exists( 'khm_get_stripe_secret' )
			? (string) ( khm_get_stripe_secret( 'KH_STRIPE_SECRET_KEY' ) ?? '' )
			: '';
		if ( empty( $secret ) ) {
			return null;
		}

		if ( ! class_exists( '\\Stripe\\Stripe' ) ) {
			require_once dirname( __DIR__, 2 ) . '/vendor/stripe/stripe-php/init.php';
		}

		try {
			\Stripe\Stripe::setApiKey( $secret );
			\Stripe\Stripe::setApiVersion( '2023-10-16' );
			return \Stripe\Subscription::retrieve( $subscriptionId );
		} catch ( \Throwable $e ) {
			error_log( 'Stripe subscription retrieve error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Persist Stripe customer and subscription ids for the user.
	 *
	 * @param int    $userId
	 * @param object $session
	 * @return void
	 */
	private function persist_stripe_ids( int $userId, object $session ): void {
		$customerId     = $session->customer ?? null;
		$subscriptionId = $session->subscription ?? null;

		if ( $customerId ) {
			update_user_meta( $userId, 'stripe_customer_id', (string) $customerId );
		}
		if ( $subscriptionId ) {
			update_user_meta( $userId, 'stripe_subscription_id', (string) $subscriptionId );
		}
	}
}
