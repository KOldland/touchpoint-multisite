<?php
/**
 * Tests for WebhooksController checkout.session.completed handling.
 *
 * @package KHM\Tests\Rest
 */

namespace KHM\Tests\Rest;

use KHM\Contracts\IdempotencyStoreInterface;
use KHM\Contracts\MembershipRepositoryInterface;
use KHM\Contracts\OrderRepositoryInterface;
use KHM\Contracts\WebhookVerifierInterface;
use KHM\Rest\WebhooksController;
use PHPUnit\Framework\TestCase;

class WebhooksCheckoutAssignTest extends TestCase {
	public function test_checkout_session_completed_assigns_membership(): void {
		$verifier = $this->createMock( WebhookVerifierInterface::class );
		$idempotency = $this->createMock( IdempotencyStoreInterface::class );
		$orders = $this->createMock( OrderRepositoryInterface::class );
		$memberships = $this->createMock( MembershipRepositoryInterface::class );

		$memberships
			->expects( $this->once() )
			->method( 'assign' )
			->with(
				123,
				456,
				$this->callback( function ( $options ) {
					return isset( $options['stripe_customer_id'], $options['stripe_subscription_id'] )
						&& $options['stripe_customer_id'] === 'cus_test'
						&& $options['stripe_subscription_id'] === 'sub_test';
				} )
			)
			->willReturn( (object) [] );

		$controller = new WebhooksController( $verifier, $idempotency, $orders, $memberships );

		$session = (object) [
			'mode' => 'subscription',
			'customer' => 'cus_test',
			'subscription' => 'sub_test',
			'metadata' => [
				'membership_level_id' => '456',
				'user_id' => '123',
			],
		];

		$reflection = new \ReflectionClass( $controller );
		$method = $reflection->getMethod( 'handle_checkout_session_completed' );
		$method->setAccessible( true );
		$method->invoke( $controller, $session );
	}
}
