<?php
/**
 * Stripe Gateway
 *
 * Payment gateway implementation for Stripe.
 * Supports charges, subscriptions, refunds, and SCA (Strong Customer Authentication).
 *
 * @package KHM\Gateways
 */

namespace KHM\Gateways;

use KHM\Contracts\GatewayInterface;
use KHM\Contracts\Result;

class StripeGateway implements GatewayInterface {

    private string $secretKey;
    private string $publishableKey;
    private string $environment;
    private string $apiVersion = '2023-10-16';

    public function __construct( array $credentials = [] ) {
        if ( ! empty($credentials) ) {
            $this->setCredentials($credentials);
        }
    }

    /**
     * Authorize a payment without capturing it.
     */
    public function authorize( $order ): Result {
        try {
            $this->loadStripeLibrary();

            $params = [
                'amount' => $this->toStripeAmount($order->total),
                'currency' => $order->currency ?? 'usd',
                'capture' => false,
                'description' => $this->getOrderDescription($order),
                'metadata' => $this->getMetadata($order),
            ];

            // Add payment method or source
            if ( ! empty($order->payment_method_id) ) {
                $params['payment_method'] = $order->payment_method_id;
                $params['confirm'] = true;
            } elseif ( ! empty($order->stripe_token) ) {
                $params['source'] = $order->stripe_token;
            } else {
                return Result::failure('No payment method provided', 'missing_payment_method');
            }

            // Add customer if available
            if ( ! empty($order->stripe_customer_id) ) {
                $params['customer'] = $order->stripe_customer_id;
            }

            $params = apply_filters('khm_stripe_authorize_params', $params, $order);

            $charge = \Stripe\PaymentIntent::create($params);

            return Result::success('Payment authorized', [
                'transaction_id' => $charge->id,
                'status' => $charge->status,
                'charge' => $charge,
            ]);

        } catch ( \Stripe\Exception\CardException $e ) {
            return Result::failure($e->getMessage(), 'card_error');
        } catch ( \Exception $e ) {
            error_log('Stripe authorize error: ' . $e->getMessage());
            return Result::failure('Payment authorization failed', 'gateway_error');
        }
    }

    /**
     * Charge a payment immediately.
     */
    public function charge( $order ): Result {
        try {
            $this->loadStripeLibrary();

            $params = [
                'amount' => $this->toStripeAmount($order->total),
                'currency' => $order->currency ?? 'usd',
                'description' => $this->getOrderDescription($order),
                'metadata' => $this->getMetadata($order),
            ];

            // Add payment method or source
            if ( ! empty($order->payment_method_id) ) {
                $params['payment_method'] = $order->payment_method_id;
                $params['confirm'] = true;
            } elseif ( ! empty($order->stripe_token) ) {
                $params['source'] = $order->stripe_token;
            } else {
                return Result::failure('No payment method provided', 'missing_payment_method');
            }

            // Add customer if available
            if ( ! empty($order->stripe_customer_id) ) {
                $params['customer'] = $order->stripe_customer_id;
            }

            $params = apply_filters('khm_stripe_charge_params', $params, $order);

            do_action('khm_gateway_before_charge', $order, 'stripe');

            $paymentIntent = \Stripe\PaymentIntent::create($params);

            do_action('khm_gateway_after_charge', $order, 'stripe', $paymentIntent);

            return Result::success('Payment successful', [
                'transaction_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'payment_intent' => $paymentIntent,
            ]);

        } catch ( \Stripe\Exception\CardException $e ) {
            return Result::failure($e->getMessage(), 'card_declined');
        } catch ( \Exception $e ) {
            error_log('Stripe charge error: ' . $e->getMessage());
            return Result::failure('Payment failed', 'gateway_error');
        }
    }

    /**
     * Void a previously authorized payment.
     */
    public function void( $order ): Result {
        if ( empty($order->payment_transaction_id) ) {
            return Result::failure('No transaction ID provided', 'missing_transaction_id');
        }

        try {
            $this->loadStripeLibrary();

            $paymentIntent = \Stripe\PaymentIntent::retrieve($order->payment_transaction_id);
            $paymentIntent->cancel();

            return Result::success('Payment voided', [
                'transaction_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
            ]);

        } catch ( \Exception $e ) {
            error_log('Stripe void error: ' . $e->getMessage());
            return Result::failure('Void failed', 'gateway_error');
        }
    }

    /**
     * Refund a captured payment.
     */
    public function refund( $order, ?float $amount = null ): Result {
        if ( empty($order->payment_transaction_id) ) {
            return Result::failure('No transaction ID provided', 'missing_transaction_id');
        }

        try {
            $this->loadStripeLibrary();

            $params = [
                'payment_intent' => $order->payment_transaction_id,
            ];

            if ( $amount !== null ) {
                $params['amount'] = $this->toStripeAmount($amount);
            }

            $params = apply_filters('khm_stripe_refund_params', $params, $order, $amount);

            $refund = \Stripe\Refund::create($params);

            return Result::success('Refund processed', [
                'refund_id' => $refund->id,
                'amount' => $this->fromStripeAmount($refund->amount),
                'status' => $refund->status,
            ]);

        } catch ( \Exception $e ) {
            error_log('Stripe refund error: ' . $e->getMessage());
            return Result::failure('Refund failed', 'gateway_error');
        }
    }

    /**
     * Create a recurring subscription.
     */
    public function createSubscription( $order ): Result {
        try {
            $this->loadStripeLibrary();

            // Get or create customer
            $customerResult = $this->ensureCustomer($order);
            if ( $customerResult->isFailure() ) {
                return $customerResult;
            }

            $customerId = $customerResult->get('customer_id');

            // Get or create price
            $priceId = $this->getOrCreatePrice($order);
            if ( ! $priceId ) {
                return Result::failure('Failed to create subscription price', 'price_error');
            }

            $params = [
                'customer' => $customerId,
                'items' => [ [ 'price' => $priceId ] ],
                'metadata' => $this->getMetadata($order),
            ];

            // Add payment method if provided
            if ( ! empty($order->payment_method_id) ) {
                $params['default_payment_method'] = $order->payment_method_id;
            }

            // Add trial period if applicable
            if ( ! empty($order->trial_days) ) {
                $params['trial_period_days'] = (int) $order->trial_days;
                
                // If trial has a specific amount, we need to handle this differently
                // Stripe doesn't support "paid trials" directly, so we skip the trial
                // and apply discount to first payment instead
                if ( ! empty($order->trial_amount) && $order->trial_amount > 0 ) {
                    unset($params['trial_period_days']);
                    // First payment discount will be handled by coupon below
                }
            }

            // Handle recurring discounts with Stripe coupons
            if ( ! empty($order->recurring_discount_type) && ! empty($order->recurring_discount_amount) ) {
                $coupon = $this->createOrGetCoupon($order);
                if ( $coupon ) {
                    $params['coupon'] = $coupon;
                }
            } elseif ( ! empty($order->discount_first_payment_only) && ! empty($order->discount_amount) ) {
                // First payment only discount - create a one-time coupon
                $coupon = $this->createOneTimeDiscountCoupon($order);
                if ( $coupon ) {
                    $params['coupon'] = $coupon;
                }
            }

            $params = apply_filters('khm_stripe_subscription_params', $params, $order);

            $subscription = \Stripe\Subscription::create($params);

            return Result::success('Subscription created', [
                'subscription_id' => $subscription->id,
                'customer_id' => $customerId,
                'status' => $subscription->status,
                'subscription' => $subscription,
            ]);

        } catch ( \Exception $e ) {
            error_log('Stripe subscription error: ' . $e->getMessage());
            return Result::failure('Subscription creation failed', 'gateway_error');
        }
    }

    /**
     * Update an existing subscription.
     */
    public function updateSubscription( string $subscriptionId, array $params ): Result {
        try {
            $this->loadStripeLibrary();

            $subscription = \Stripe\Subscription::retrieve($subscriptionId);

            foreach ( $params as $key => $value ) {
                $subscription->{$key} = $value;
            }

            $subscription->save();

            return Result::success('Subscription updated', [
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
            ]);

        } catch ( \Exception $e ) {
            error_log('Stripe subscription update error: ' . $e->getMessage());
            return Result::failure('Subscription update failed', 'gateway_error');
        }
    }

    /**
     * Cancel a recurring subscription.
     */
    public function cancelSubscription( string $subscriptionId, bool $atPeriodEnd = false ): Result {
        try {
            $this->loadStripeLibrary();

            $subscription = \Stripe\Subscription::retrieve($subscriptionId);

            if ( $atPeriodEnd ) {
                $subscription->cancel_at_period_end = true;
                $subscription->save();
            } else {
                $subscription->cancel();
            }

            return Result::success('Subscription cancelled', [
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
            ]);

        } catch ( \Exception $e ) {
            error_log('Stripe subscription cancel error: ' . $e->getMessage());
            return Result::failure('Subscription cancellation failed', 'gateway_error');
        }
    }

    /**
     * Retrieve a customer record from Stripe.
     */
    public function getCustomer( string $customerId ): ?object {
        try {
            $this->loadStripeLibrary();
            return \Stripe\Customer::retrieve($customerId);
        } catch ( \Exception $e ) {
            error_log('Stripe get customer error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a customer record in Stripe.
     */
    public function createCustomer( $user, array $paymentMethod ): Result {
        try {
            $this->loadStripeLibrary();

            $params = [
                'email' => $user->user_email,
                'name' => $user->display_name,
                'metadata' => [
                    'user_id' => $user->ID,
                    'username' => $user->user_login,
                ],
            ];

            if ( ! empty($paymentMethod['payment_method_id']) ) {
                $params['payment_method'] = $paymentMethod['payment_method_id'];
                $params['invoice_settings'] = [
                    'default_payment_method' => $paymentMethod['payment_method_id'],
                ];
            }

            $params = apply_filters('khm_stripe_customer_params', $params, $user, $paymentMethod);

            $customer = \Stripe\Customer::create($params);

            return Result::success('Customer created', [
                'customer_id' => $customer->id,
                'customer' => $customer,
            ]);

        } catch ( \Exception $e ) {
            error_log('Stripe create customer error: ' . $e->getMessage());
            return Result::failure('Customer creation failed', 'gateway_error');
        }
    }

    /**
     * Get the gateway's name/identifier.
     */
    public function getGatewayName(): string {
        return 'stripe';
    }

    /**
     * Get the current environment.
     */
    public function getEnvironment(): string {
        return $this->environment;
    }

    /**
     * Set gateway credentials.
     */
    public function setCredentials( array $credentials ): void {
        $this->secretKey = $credentials['secret_key'] ?? '';
        $this->publishableKey = $credentials['publishable_key'] ?? '';
        $this->environment = $credentials['environment'] ?? 'production';

        if ( ! empty($this->secretKey) ) {
            $this->loadStripeLibrary();
            \Stripe\Stripe::setApiKey($this->secretKey);
            \Stripe\Stripe::setApiVersion($this->apiVersion);
        }
    }

    /**
     * Load Stripe PHP library.
     */
    private function loadStripeLibrary(): void {
        if ( ! class_exists('\Stripe\Stripe') ) {
            require_once dirname(__DIR__, 2) . '/vendor/stripe/stripe-php/init.php';
        }
    }

    /**
     * Convert amount to Stripe format (cents).
     */
    private function toStripeAmount( float $amount ): int {
        return (int) round($amount * 100);
    }

    /**
     * Convert Stripe amount (cents) to dollars.
     */
    private function fromStripeAmount( int $amount ): float {
        return $amount / 100;
    }

    /**
     * Get order description for Stripe.
     */
    private function getOrderDescription( object $order ): string {
        $desc = 'Membership Order';

        if ( ! empty($order->membership_level_name) ) {
            $desc .= ' - ' . $order->membership_level_name;
        }

        if ( ! empty($order->code) ) {
            $desc .= ' (' . $order->code . ')';
        }

        return apply_filters('khm_stripe_charge_description', $desc, $order);
    }

    /**
     * Get metadata for Stripe objects.
     */
    private function getMetadata( object $order ): array {
        $metadata = [
            'order_id' => $order->id ?? 0,
            'order_code' => $order->code ?? '',
            'user_id' => $order->user_id ?? 0,
            'membership_id' => $order->membership_id ?? 0,
        ];

        return apply_filters('khm_stripe_metadata', $metadata, $order);
    }

    /**
     * Ensure customer exists in Stripe.
     */
    private function ensureCustomer( object $order ): Result {
        // Check if customer ID already exists
        if ( ! empty($order->stripe_customer_id) ) {
            $customer = $this->getCustomer($order->stripe_customer_id);
            if ( $customer ) {
                return Result::success('Customer found', [ 'customer_id' => $customer->id ]);
            }
        }

        // Create new customer
        $user = get_userdata($order->user_id);
        if ( ! $user ) {
            return Result::failure('User not found', 'user_not_found');
        }

        $paymentMethod = [];
        if ( ! empty($order->payment_method_id) ) {
            $paymentMethod['payment_method_id'] = $order->payment_method_id;
        }

        return $this->createCustomer($user, $paymentMethod);
    }

    /**
     * Get or create a Stripe Price for the subscription.
     */
    private function getOrCreatePrice( object $order ): ?string {
        try {
            $this->loadStripeLibrary();

            // Check if price ID is already provided
            if ( ! empty($order->stripe_price_id) ) {
                return $order->stripe_price_id;
            }

            // Create a new price
            $params = [
                'unit_amount' => $this->toStripeAmount($order->billing_amount ?? $order->total),
                'currency' => $order->currency ?? 'usd',
                'recurring' => [
                    'interval' => strtolower($order->billing_period ?? 'month'),
                    'interval_count' => $order->billing_frequency ?? 1,
                ],
                'product_data' => [
                    'name' => $order->membership_level_name ?? 'Membership',
                ],
            ];

            $params = apply_filters('khm_stripe_price_params', $params, $order);

            $price = \Stripe\Price::create($params);

            return $price->id;

        } catch ( \Exception $e ) {
            error_log('Stripe price creation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create or get a Stripe coupon for recurring discounts.
     *
     * @param object $order Order object with discount information.
     * @return string|null Coupon ID or null on failure.
     */
    private function createOrGetCoupon( object $order ): ?string {
        try {
            $this->loadStripeLibrary();

            // Create a unique coupon ID based on the discount code
            $couponId = 'khm_' . sanitize_key( $order->discount_code ?? 'discount' ) . '_recurring';

            // Try to retrieve existing coupon
            try {
                $coupon = \Stripe\Coupon::retrieve( $couponId );
                return $coupon->id;
            } catch ( \Stripe\Exception\InvalidRequestException $e ) {
                // Coupon doesn't exist, create it
            }

            // Create new coupon
            $params = [
                'id' => $couponId,
                'duration' => 'forever', // Apply to all future invoices
                'name' => 'Recurring Discount: ' . ( $order->discount_code ?? 'Discount' ),
            ];

            if ( $order->recurring_discount_type === 'percent' ) {
                $params['percent_off'] = (float) $order->recurring_discount_amount;
            } elseif ( $order->recurring_discount_type === 'amount' ) {
                $params['amount_off'] = $this->toStripeAmount( $order->recurring_discount_amount );
                $params['currency'] = $order->currency ?? 'usd';
            }

            $coupon = \Stripe\Coupon::create( $params );

            return $coupon->id;

        } catch ( \Exception $e ) {
            error_log( 'Stripe coupon creation error: ' . $e->getMessage() );
            return null;
        }
    }

    /**
     * Create a one-time discount coupon for first payment only.
     *
     * @param object $order Order object with discount information.
     * @return string|null Coupon ID or null on failure.
     */
    private function createOneTimeDiscountCoupon( object $order ): ?string {
        try {
            $this->loadStripeLibrary();

            // Create a unique coupon ID
            $couponId = 'khm_' . sanitize_key( $order->discount_code ?? 'discount' ) . '_' . time();

            // Create one-time coupon
            $params = [
                'id' => $couponId,
                'duration' => 'once', // Apply only to first invoice
                'name' => 'First Payment Discount: ' . ( $order->discount_code ?? 'Discount' ),
            ];

            // Calculate discount based on original values before discount was applied
            $original_subtotal = ( $order->subtotal ?? 0 ) + ( $order->discount_amount ?? 0 );
            $discount_percent = $original_subtotal > 0 ? ( ( $order->discount_amount ?? 0 ) / $original_subtotal ) * 100 : 0;

            if ( $discount_percent > 0 && $discount_percent <= 100 ) {
                $params['percent_off'] = round( $discount_percent, 2 );
            } else {
                $params['amount_off'] = $this->toStripeAmount( $order->discount_amount ?? 0 );
                $params['currency'] = $order->currency ?? 'usd';
            }

            $coupon = \Stripe\Coupon::create( $params );

            return $coupon->id;

        } catch ( \Exception $e ) {
            error_log( 'Stripe first payment coupon creation error: ' . $e->getMessage() );
            return null;
        }
    }
}

