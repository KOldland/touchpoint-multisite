<?php
/**
 * Tests for WebhooksController product.updated wiring.
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

class WebhooksProductImportTest extends TestCase {

	public function test_handle_product_updated_event_dispatches_to_handler(): void {
		$controller = new class(
			$this->createMock( WebhookVerifierInterface::class ),
			$this->createMock( IdempotencyStoreInterface::class ),
			$this->createMock( OrderRepositoryInterface::class ),
			$this->createMock( MembershipRepositoryInterface::class )
		) extends WebhooksController {
			public ?object $capturedProduct = null;

			protected function handle_product_updated( object $product ): void {
				$this->capturedProduct = $product;
			}
		};

		$event = (object) [
			'data' => (object) [
				'object' => (object) [
					'id' => 'prod_123',
				],
			],
		];

		$ref = new \ReflectionClass( $controller );
		$method = $ref->getMethod( 'handle_stripe_event' );
		$method->setAccessible( true );
		$method->invoke( $controller, 'product.updated', $event );

		$this->assertNotNull( $controller->capturedProduct );
		$this->assertSame( 'prod_123', $controller->capturedProduct->id );
	}
}
