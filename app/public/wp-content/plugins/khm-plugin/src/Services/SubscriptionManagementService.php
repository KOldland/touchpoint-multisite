<?php

namespace KHM\Services;

use DateTime;
use KHM\Contracts\OrderRepositoryInterface;
use KHM\Contracts\MembershipRepositoryInterface;
use KHM\Gateways\StripeGateway;
use KHM\Contracts\Result;

class SubscriptionManagementService {
    private OrderRepositoryInterface $orders;
    private MembershipRepositoryInterface $memberships;

    public function __construct(
        OrderRepositoryInterface $orders,
        MembershipRepositoryInterface $memberships
    ) {
        $this->orders = $orders;
        $this->memberships = $memberships;
    }

    /**
     * Cancel a user's subscription for a given membership level.
     *
     * @param int $userId Current user ID
     * @param int $levelId Membership level ID
     * @param bool $atPeriodEnd If true, cancel at period end; otherwise cancel immediately
     * @return array [success => bool, message => string]
     */
    public function cancel(int $userId, int $levelId, bool $atPeriodEnd = true): array {
        // Find the most recent order for this user+level with a subscription ID
        $orders = $this->orders->findByUser($userId, [ 'membership_id' => $levelId, 'limit' => 50 ]);
        $subOrder = null;
        foreach ($orders as $o) {
            if (!empty($o->subscription_transaction_id)) { $subOrder = $o; break; }
        }

        if (!$subOrder) {
            return [ 'success' => false, 'message' => __('No active subscription found for this membership.', 'khm-membership') ];
        }

        $gateway = strtolower($subOrder->gateway ?? '');
        $subscriptionId = $subOrder->subscription_transaction_id;

        if ($gateway === 'stripe') {
            $gateway_instance = $this->make_stripe_gateway();
            if ( ! $gateway_instance ) {
                return [
                    'success' => false,
                    'message' => __( 'Stripe gateway is not configured.', 'khm-membership' ),
                ];
            }

            $result = $gateway_instance->cancelSubscription($subscriptionId, $atPeriodEnd);
            if ( ! $result->isSuccess() ) {
                $message = $result->getMessage() ?: __( 'Unable to cancel subscription at the gateway.', 'khm-membership' );
                return [ 'success' => false, 'message' => $message ];
            }

            if ($atPeriodEnd) {
                // Leave membership active; let Stripe webhooks flip to cancelled later.
                // Optionally we could set an end date if we retrieve the subscription period end.
                return [ 'success' => true, 'message' => __('Your subscription will be cancelled at the end of the current billing period.', 'khm-membership') ];
            }

            // Immediate cancel: mark membership cancelled right away
            $this->memberships->cancel($userId, $levelId, 'User-initiated immediate cancel');
            if (!empty($subOrder->id)) {
                $this->orders->updateStatus((int)$subOrder->id, 'cancelled', 'User-initiated immediate cancel');
            }

            return [ 'success' => true, 'message' => __('Your subscription has been cancelled.', 'khm-membership') ];
        }

        return [ 'success' => false, 'message' => __('Unsupported gateway for subscription management.', 'khm-membership') ];
    }

    /**
     * Reactivate a user's subscription (remove cancel_at_period_end).
     * Only makes sense if the subscription is still active at Stripe.
     */
    public function reactivate(int $userId, int $levelId): array {
        $orders = $this->orders->findByUser($userId, [ 'membership_id' => $levelId, 'limit' => 50 ]);
        $subOrder = null;
        foreach ($orders as $o) {
            if (!empty($o->subscription_transaction_id)) { $subOrder = $o; break; }
        }

        if (!$subOrder) {
            return [ 'success' => false, 'message' => __('No subscription found to reactivate.', 'khm-membership') ];
        }

        $gateway = strtolower($subOrder->gateway ?? '');
        $subscriptionId = $subOrder->subscription_transaction_id;

        if ($gateway === 'stripe') {
            $gateway_instance = $this->make_stripe_gateway();
            if ( ! $gateway_instance ) {
                return [
                    'success' => false,
                    'message' => __( 'Stripe gateway is not configured.', 'khm-membership' ),
                ];
            }

            $result = $gateway_instance->updateSubscription($subscriptionId, [ 'cancel_at_period_end' => false ]);
            if ( ! $result->isSuccess() ) {
                $message = $result->getMessage() ?: __( 'Unable to reactivate subscription at the gateway.', 'khm-membership' );
                return [ 'success' => false, 'message' => $message ];
            }

            // If membership had an end date set in the future, we can clear it to keep active.
            // For now, assume webhooks will keep status accurate.
            return [ 'success' => true, 'message' => __('Your subscription has been reactivated.', 'khm-membership') ];
        }

        return [ 'success' => false, 'message' => __('Unsupported gateway for subscription management.', 'khm-membership') ];
    }

    /**
     * Pause an active subscription.
     */
    public function pause( int $userId, int $levelId, ?DateTime $resumeAt = null ): array {
        $orders = $this->orders->findByUser( $userId, [ 'membership_id' => $levelId, 'limit' => 50 ] );
        $subOrder = null;
        foreach ( $orders as $o ) {
            if ( ! empty( $o->subscription_transaction_id ) ) { $subOrder = $o; break; }
        }

        if ( ! $subOrder ) {
            return [ 'success' => false, 'message' => __( 'No active subscription found for this membership.', 'khm-membership' ) ];
        }

        $gateway = strtolower( $subOrder->gateway ?? '' );
        $subscriptionId = $subOrder->subscription_transaction_id;

        if ( 'stripe' === $gateway ) {
            $gateway_instance = $this->make_stripe_gateway();
            if ( ! $gateway_instance ) {
                return [
                    'success' => false,
                    'message' => __( 'Stripe gateway is not configured.', 'khm-membership' ),
                ];
            }

            $behavior = apply_filters( 'khm_stripe_pause_behavior', 'mark_uncollectible', $userId, $levelId );
            $params   = [
                'pause_collection' => [
                    'behavior' => $behavior,
                ],
            ];

            if ( $resumeAt ) {
                $params['pause_collection']['resume_at'] = $resumeAt->getTimestamp();
            }

            $result = $gateway_instance->updateSubscription( $subscriptionId, $params );
            if ( ! $result->isSuccess() ) {
                $message = $result->getMessage() ?: __( 'Unable to pause subscription at the gateway.', 'khm-membership' );
                return [ 'success' => false, 'message' => $message ];
            }

            $this->memberships->pause( $userId, $levelId, $resumeAt, __( 'Subscription paused.', 'khm-membership' ) );

            return [ 'success' => true, 'message' => __( 'Your subscription has been paused.', 'khm-membership' ) ];
        }

        return [ 'success' => false, 'message' => __( 'Unsupported gateway for subscription management.', 'khm-membership' ) ];
    }

    /**
     * Resume a paused subscription.
     */
    public function resume( int $userId, int $levelId ): array {
        $orders = $this->orders->findByUser( $userId, [ 'membership_id' => $levelId, 'limit' => 50 ] );
        $subOrder = null;
        foreach ( $orders as $o ) {
            if ( ! empty( $o->subscription_transaction_id ) ) { $subOrder = $o; break; }
        }

        if ( ! $subOrder ) {
            return [ 'success' => false, 'message' => __( 'No subscription found to resume.', 'khm-membership' ) ];
        }

        $gateway = strtolower( $subOrder->gateway ?? '' );
        $subscriptionId = $subOrder->subscription_transaction_id;

        if ( 'stripe' === $gateway ) {
            $gateway_instance = $this->make_stripe_gateway();
            if ( ! $gateway_instance ) {
                return [
                    'success' => false,
                    'message' => __( 'Stripe gateway is not configured.', 'khm-membership' ),
                ];
            }

            $result = $gateway_instance->updateSubscription( $subscriptionId, [ 'pause_collection' => null ] );
            if ( ! $result->isSuccess() ) {
                $message = $result->getMessage() ?: __( 'Unable to resume subscription at the gateway.', 'khm-membership' );
                return [ 'success' => false, 'message' => $message ];
            }

            $this->memberships->resume( $userId, $levelId, __( 'Subscription resumed.', 'khm-membership' ) );

            return [ 'success' => true, 'message' => __( 'Your subscription has been resumed.', 'khm-membership' ) ];
        }

        return [ 'success' => false, 'message' => __( 'Unsupported gateway for subscription management.', 'khm-membership' ) ];
    }

    /**
     * Build a Stripe gateway instance for subscription actions.
     */
    private function make_stripe_gateway(): ?StripeGateway {
        $filtered = apply_filters( 'khm_subscription_management_stripe_gateway', null, $this );
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

        $created = apply_filters( 'khm_subscription_management_stripe_gateway_created', $gateway, $credentials, $this );
        if ( $created instanceof StripeGateway ) {
            return $created;
        }

        return $gateway;
    }
}
