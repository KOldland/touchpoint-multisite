<?php

namespace KHM\Services;

use KHM\Contracts\OrderRepositoryInterface;
use KHM\Services\Stripe\PaymentMethodStripeAdapter;
use KHM\Services\Stripe\PaymentMethodStripeAdapterInterface;

class PaymentMethodService {
    private OrderRepositoryInterface $orders;

    public function __construct(OrderRepositoryInterface $orders)
    {
        $this->orders = $orders;
    }

    /**
     * Create a SetupIntent for the Stripe customer associated with the user's active subscription
     * so they can update their default payment method.
     *
     * @return array { success: bool, client_secret?: string, publishable_key?: string, message?: string }
     */
    public function createSetupIntent(int $userId, int $levelId): array {
        // Find subscription order
        $orders = $this->orders->findByUser($userId, [ 'membership_id' => $levelId, 'limit' => 50 ]);
        $subOrder = null;
        foreach ($orders as $o) {
            if (!empty($o->subscription_transaction_id)) { $subOrder = $o; break; }
        }

        if (!$subOrder) {
            return [ 'success' => false, 'message' => __('No active subscription found for this membership.', 'khm-membership') ];
        }

        $gateway = strtolower($subOrder->gateway ?? '');
        if ($gateway !== 'stripe') {
            return [ 'success' => false, 'message' => __('Unsupported gateway for payment method updates.', 'khm-membership') ];
        }

        $secret = get_option('khm_stripe_secret_key', '');
        $publishable = get_option('khm_stripe_publishable_key', '');

        if (empty($secret) || empty($publishable)) {
            return [ 'success' => false, 'message' => __('Stripe keys are not configured.', 'khm-membership') ];
        }

        $stripe = $this->get_stripe_adapter();
        $stripe->setApiKey( $secret );

        try {
            $subscription = $stripe->retrieveSubscription($subOrder->subscription_transaction_id);
            $customerId   = $this->extract_value( $subscription, 'customer' );
            if (!$customerId) {
                return [ 'success' => false, 'message' => __('Could not resolve Stripe customer.', 'khm-membership') ];
            }

            $intent = $stripe->createSetupIntent([
                'customer' => $customerId,
                'usage' => 'off_session',
                // Optionally restrict to card
                'payment_method_types' => [ 'card' ],
                'metadata' => [
                    'user_id' => $userId,
                    'membership_id' => $levelId,
                ],
            ]);

            return [
                'success' => true,
                'client_secret' => $this->extract_value( $intent, 'client_secret' ),
                'publishable_key' => $publishable,
            ];
        } catch (\Exception $e) {
            error_log('Stripe SetupIntent error: ' . $e->getMessage());
            return [ 'success' => false, 'message' => __('Failed to create Setup Intent.', 'khm-membership') ];
        }
    }

    /**
     * Attach a payment method to the customer and set it as default for the subscription.
     */
    public function applyPaymentMethod(int $userId, int $levelId, string $paymentMethodId): array {
        $orders = $this->orders->findByUser($userId, [ 'membership_id' => $levelId, 'limit' => 50 ]);
        $subOrder = null;
        foreach ($orders as $o) {
            if (!empty($o->subscription_transaction_id)) { $subOrder = $o; break; }
        }

        if (!$subOrder) {
            return [ 'success' => false, 'message' => __('No active subscription found for this membership.', 'khm-membership') ];
        }

        $gateway = strtolower($subOrder->gateway ?? '');
        if ($gateway !== 'stripe') {
            return [ 'success' => false, 'message' => __('Unsupported gateway for payment method updates.', 'khm-membership') ];
        }

        $secret = get_option('khm_stripe_secret_key', '');
        if (empty($secret)) {
            return [ 'success' => false, 'message' => __('Stripe keys are not configured.', 'khm-membership') ];
        }

        $stripe = $this->get_stripe_adapter();
        $stripe->setApiKey( $secret );

        try {
            $subscription = $stripe->retrieveSubscription($subOrder->subscription_transaction_id);
            $customerId   = $this->extract_value( $subscription, 'customer' );
            if (!$customerId) {
                return [ 'success' => false, 'message' => __('Could not resolve Stripe customer.', 'khm-membership') ];
            }

            // Attach PM to customer
            $stripe->attachPaymentMethod($paymentMethodId, $customerId);

            // Set as default for invoices
            $stripe->updateCustomer($customerId, [
                'invoice_settings' => [ 'default_payment_method' => $paymentMethodId ],
            ]);

            // Also set on the subscription to take effect immediately
            $stripe->updateSubscription($this->extract_value( $subscription, 'id' ), [
                'default_payment_method' => $paymentMethodId,
                // For immediate retry if previous invoice is open
                // 'proration_behavior' => 'none' // keep billing unchanged
            ]);

            // Optionally update latest order card brand/last4 if available via PaymentMethod retrieval
            try {
                $pm = $stripe->retrievePaymentMethod($paymentMethodId);
                $card = $this->extract_value( $pm, 'card' );
                if ($card) {
                    $brand = $this->extract_value( $card, 'brand' );
                    $last4 = $this->extract_value( $card, 'last4' );
                    $expMonth = $this->extract_value( $card, 'exp_month' );
                    $expYear = $this->extract_value( $card, 'exp_year' );

                    $this->orders->update((int)$subOrder->id, [
                        'cardtype' => $brand,
                        'accountnumber' => $last4 ? '************' . $last4 : null,
                        'expirationmonth' => $expMonth,
                        'expirationyear' => $expYear,
                    ]);
                }
            } catch (\Exception $ignore) {}

            return [ 'success' => true, 'message' => __('Payment method updated.', 'khm-membership') ];
        } catch (\Stripe\Exception\CardException $e) {
            return [ 'success' => false, 'message' => $e->getMessage() ];
        } catch (\Exception $e) {
            error_log('Stripe update payment method error: ' . $e->getMessage());
            return [ 'success' => false, 'message' => __('Failed to update payment method.', 'khm-membership') ];
        }
    }

    private function get_stripe_adapter(): PaymentMethodStripeAdapterInterface {
        $adapter = apply_filters( 'khm_payment_method_stripe_adapter', null, $this );
        if ( $adapter instanceof PaymentMethodStripeAdapterInterface ) {
            return $adapter;
        }

        return new PaymentMethodStripeAdapter();
    }

    /**
     * Safely extract a value from an object/array.
     *
     * @param mixed  $source Source object or array.
     * @param string $key    Key/property to extract.
     * @return mixed|null
     */
    private function extract_value( $source, string $key ) {
        if ( is_array( $source ) && array_key_exists( $key, $source ) ) {
            return $source[ $key ];
        }

        if ( is_object( $source ) && isset( $source->$key ) ) {
            return $source->$key;
        }

        return null;
    }
}
