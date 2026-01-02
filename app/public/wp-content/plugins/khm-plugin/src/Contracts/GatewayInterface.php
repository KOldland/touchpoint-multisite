<?php
/**
 * Gateway Interface
 *
 * Defines the contract for payment gateway implementations.
 * All gateway adapters (Stripe, PayPal, Braintree, etc.) must implement this interface.
 *
 * @package KHM\Contracts
 */

namespace KHM\Contracts;

interface GatewayInterface {

    /**
     * Authorize a payment without capturing it.
     *
     * Used for verifying payment method validity or pre-authorizing charges.
     *
     * @param object $order Order object containing payment details
     * @return Result Result object with success/failure and transaction details
     */
    public function authorize( $order ): Result;

    /**
     * Charge a payment immediately.
     *
     * Used for one-time payments or initial subscription charges.
     *
     * @param object $order Order object containing payment details
     * @return Result Result object with success/failure and transaction ID
     */
    public function charge( $order ): Result;

    /**
     * Void a previously authorized payment.
     *
     * Cancels an authorization before it's captured.
     *
     * @param object $order Order object with payment_transaction_id
     * @return Result Result object with success/failure
     */
    public function void( $order ): Result;

    /**
     * Refund a captured payment.
     *
     * @param object $order Order object with payment_transaction_id
     * @param float|null $amount Amount to refund (null = full refund)
     * @return Result Result object with success/failure and refund transaction ID
     */
    public function refund( $order, ?float $amount = null ): Result;

    /**
     * Create a recurring subscription.
     *
     * @param object $order Order object with billing cycle details
     * @return Result Result object with success/failure and subscription ID
     */
    public function createSubscription( $order ): Result;

    /**
     * Update an existing subscription.
     *
     * Used for plan changes, payment method updates, etc.
     *
     * @param string $subscriptionId Gateway subscription ID
     * @param array $params Parameters to update (plan, payment_method, etc.)
     * @return Result Result object with success/failure
     */
    public function updateSubscription( string $subscriptionId, array $params ): Result;

    /**
     * Cancel a recurring subscription.
     *
     * @param string $subscriptionId Gateway subscription ID
     * @param bool $atPeriodEnd Cancel immediately or at billing period end
     * @return Result Result object with success/failure
     */
    public function cancelSubscription( string $subscriptionId, bool $atPeriodEnd = false ): Result;

    /**
     * Retrieve a customer record from the gateway.
     *
     * @param string $customerId Gateway customer ID
     * @return object|null Customer object or null if not found
     */
    public function getCustomer( string $customerId ): ?object;

    /**
     * Create a customer record in the gateway.
     *
     * @param object $user User object
     * @param array $paymentMethod Payment method details (token, card, etc.)
     * @return Result Result object with success/failure and customer ID
     */
    public function createCustomer( $user, array $paymentMethod ): Result;

    /**
     * Get the gateway's name/identifier.
     *
     * @return string Gateway identifier (e.g., 'stripe', 'paypal', 'braintree')
     */
    public function getGatewayName(): string;

    /**
     * Get the current environment (sandbox or production).
     *
     * @return string 'sandbox' or 'production'
     */
    public function getEnvironment(): string;

    /**
     * Set gateway credentials.
     *
     * @param array $credentials API keys, secrets, merchant IDs, etc.
     * @return void
     */
    public function setCredentials( array $credentials ): void;
}
