<?php

namespace KHM\Services\Stripe;

class PaymentMethodStripeAdapter implements PaymentMethodStripeAdapterInterface {

	public function setApiKey( string $secret ): void {
		$this->maybeLoadLibrary();
		\Stripe\Stripe::setApiKey( $secret );
	}

	public function createSetupIntent( array $payload ) {
		$this->maybeLoadLibrary();
		return \Stripe\SetupIntent::create( $payload );
	}

	public function retrieveSubscription( string $subscriptionId ) {
		$this->maybeLoadLibrary();
		return \Stripe\Subscription::retrieve( $subscriptionId );
	}

	public function attachPaymentMethod( string $paymentMethodId, string $customerId ): void {
		$this->maybeLoadLibrary();
		\Stripe\PaymentMethod::attach(
			$paymentMethodId,
			[
				'customer' => $customerId,
			]
		);
	}

	public function updateCustomer( string $customerId, array $attributes ) {
		$this->maybeLoadLibrary();
		return \Stripe\Customer::update( $customerId, $attributes );
	}

	public function updateSubscription( string $subscriptionId, array $attributes ) {
		$this->maybeLoadLibrary();
		return \Stripe\Subscription::update( $subscriptionId, $attributes );
	}

	public function retrievePaymentMethod( string $paymentMethodId ) {
		$this->maybeLoadLibrary();
		return \Stripe\PaymentMethod::retrieve( $paymentMethodId );
	}

	private function maybeLoadLibrary(): void {
		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			require_once dirname( __DIR__, 3 ) . '/vendor/stripe/stripe-php/init.php';
		}
	}
}
