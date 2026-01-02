<?php

namespace KHM\Services\Stripe;

/**
 * Adapter interface for Stripe calls made during payment method management.
 */
interface PaymentMethodStripeAdapterInterface {
	public function setApiKey( string $secret ): void;
	/**
	 * @param array<string,mixed> $payload
	 * @return mixed
	 */
	public function createSetupIntent( array $payload );
	/**
	 * @return mixed
	 */
	public function retrieveSubscription( string $subscriptionId );
	public function attachPaymentMethod( string $paymentMethodId, string $customerId ): void;
	/**
	 * @param array<string,mixed> $attributes
	 */
	public function updateCustomer( string $customerId, array $attributes );
	/**
	 * @param array<string,mixed> $attributes
	 * @return mixed
	 */
	public function updateSubscription( string $subscriptionId, array $attributes );
	/**
	 * @return mixed
	 */
	public function retrievePaymentMethod( string $paymentMethodId );
}
