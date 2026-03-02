<?php
/**
 * Tests for queued product.updated import behavior.
 *
 * @package KHM\Tests\Rest
 */

namespace {
	if ( ! isset( $GLOBALS['khm_test_scheduled_events'] ) ) {
		$GLOBALS['khm_test_scheduled_events'] = [];
	}
	if ( ! isset( $GLOBALS['khm_test_transients'] ) ) {
		$GLOBALS['khm_test_transients'] = [];
	}

	if ( ! function_exists( 'get_transient' ) ) {
		function get_transient( $key ) {
			return $GLOBALS['khm_test_transients'][ $key ] ?? false;
		}
	}

	if ( ! function_exists( 'set_transient' ) ) {
		function set_transient( $key, $value, $expiration = 0 ) {
			$GLOBALS['khm_test_transients'][ $key ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'wp_next_scheduled' ) ) {
		function wp_next_scheduled( $hook, $args = [] ) {
			foreach ( $GLOBALS['khm_test_scheduled_events'] as $event ) {
				if ( $event['hook'] === $hook && $event['args'] === $args ) {
					return $event['timestamp'];
				}
			}
			return false;
		}
	}

	if ( ! function_exists( 'wp_schedule_single_event' ) ) {
		function wp_schedule_single_event( $timestamp, $hook, $args = [], $wp_error = false ) {
			$GLOBALS['khm_test_scheduled_events'][] = [
				'timestamp' => (int) $timestamp,
				'hook' => (string) $hook,
				'args' => (array) $args,
			];
			return true;
		}
	}
}

namespace KHM\Tests\Rest {

use KHM\Contracts\IdempotencyStoreInterface;
use KHM\Contracts\MembershipRepositoryInterface;
use KHM\Contracts\OrderRepositoryInterface;
use KHM\Contracts\WebhookVerifierInterface;
use KHM\Rest\WebhooksController;
use PHPUnit\Framework\TestCase;

class WebhooksProductImportQueueTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['khm_test_scheduled_events'] = [];
		$GLOBALS['khm_test_transients'] = [];
	}

	public function test_product_updated_is_enqueued_once(): void {
		$controller = new WebhooksController(
			$this->createMock( WebhookVerifierInterface::class ),
			$this->createMock( IdempotencyStoreInterface::class ),
			$this->createMock( OrderRepositoryInterface::class ),
			$this->createMock( MembershipRepositoryInterface::class )
		);

		$product = (object) [
			'id' => 'prod_QUE123ABC1',
			'metadata' => (object) [ 'wp_level_id' => '33' ],
		];

		$ref = new \ReflectionClass( $controller );
		$method = $ref->getMethod( 'handle_product_updated' );
		$method->setAccessible( true );
		$method->invoke( $controller, $product );

		$this->assertCount( 1, $GLOBALS['khm_test_scheduled_events'] );
		$this->assertSame( 'khm_import_stripe_marketing_product_updated', $GLOBALS['khm_test_scheduled_events'][0]['hook'] );
		$this->assertSame( [ 'prod_QUE123ABC1', 33 ], $GLOBALS['khm_test_scheduled_events'][0]['args'] );
	}

	public function test_product_updated_debounces_with_transient_lock(): void {
		$controller = new WebhooksController(
			$this->createMock( WebhookVerifierInterface::class ),
			$this->createMock( IdempotencyStoreInterface::class ),
			$this->createMock( OrderRepositoryInterface::class ),
			$this->createMock( MembershipRepositoryInterface::class )
		);

		$product = (object) [
			'id' => 'prod_QUE123ABC2',
			'metadata' => (object) [ 'wp_level_id' => '77' ],
		];

		$ref = new \ReflectionClass( $controller );
		$method = $ref->getMethod( 'handle_product_updated' );
		$method->setAccessible( true );
		$method->invoke( $controller, $product );
		$method->invoke( $controller, $product );

		$this->assertCount( 1, $GLOBALS['khm_test_scheduled_events'] );
	}
}
}
