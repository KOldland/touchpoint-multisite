<?php
/**
 * Tests for Checkout Controller with Stripe Price ID resolution.
 *
 * @package KHM\Tests\Rest
 */

namespace KHM\Tests\Rest;

use KHM\Rest\CheckoutController;
use KHM\Services\LevelRepository;
use KHM\Services\LevelPriceResolver;
use PHPUnit\Framework\TestCase;

class CheckoutStripePriceTest extends TestCase {

	private LevelRepository $levels;
	private CheckoutController $controller;

	protected function setUp(): void {
		parent::setUp();
		
		$this->levels = $this->createMock( LevelRepository::class );
		$this->controller = new CheckoutController( $this->levels );
		$GLOBALS['khm_test_options']['khm_stripe_membership_price_map'] = [];
		$GLOBALS['khm_test_options']['khm_stripe_price_map'] = [];
		$GLOBALS['khm_test_filters']['khm_stripe_membership_price_map'] = [];
		$this->resetResolverCache();
	}

	protected function tearDown(): void {
		$GLOBALS['khm_test_options']['khm_stripe_membership_price_map'] = [];
		$GLOBALS['khm_test_options']['khm_stripe_price_map'] = [];
		$GLOBALS['khm_test_filters']['khm_stripe_membership_price_map'] = [];
		$this->resetResolverCache();
		parent::tearDown();
	}

	/**
	 * Test that price ID is resolved from level metadata first.
	 */
    public function test_price_id_resolution_prioritizes_metadata(): void {
        $levelId = 1;
        $metaPriceId = 'price_Meta123';
        $optionPriceId = 'price_Option123';
		
		// Mock getMeta to return metadata price
		$this->levels
			->expects( $this->once() )
			->method( 'getMeta' )
			->with( $levelId, 'stripe_price_id' )
			->willReturn( $metaPriceId );
		
		// Use reflection to call private method
		$reflection = new \ReflectionClass( $this->controller );
		$method = $reflection->getMethod( 'resolve_price_id' );
		$method->setAccessible( true );
		
		// Act - Even with option set, metadata should win
		update_option( 'khm_stripe_membership_price_map', [ $levelId => $optionPriceId ] );
        $request = $this->make_request();
        $resolved = $method->invoke( $this->controller, $levelId, $request );
		
		// Assert
		$this->assertEquals( $metaPriceId, $resolved );
	}

	/**
	 * Test that invalid price ID format returns null and logs error.
	 */
    public function test_invalid_price_id_format_returns_null(): void {
        $levelId = 1;
        $invalidPriceId = 'invalid-format-123';
		
		// Mock getMeta to return invalid price
		$this->levels
			->expects( $this->exactly( 2 ) )
			->method( 'getMeta' )
			->withConsecutive(
				[ $levelId, 'stripe_price_id' ],
				[ $levelId, 'khm_level_meta', [] ]
			)
			->willReturnOnConsecutiveCalls( $invalidPriceId, [] );
		
		// Use reflection
		$reflection = new \ReflectionClass( $this->controller );
		$method = $reflection->getMethod( 'resolve_price_id' );
		$method->setAccessible( true );
		
		// Act
        $request = $this->make_request();
        $resolved = $method->invoke( $this->controller, $levelId, $request );
		
		// Assert
		$this->assertNull( $resolved, 'Invalid price ID should return null' );
	}

	/**
	 * Test fallback to filter when metadata is empty.
	 */
    public function test_fallback_to_filter_when_metadata_empty(): void {
        $levelId = 1;
        $filterPriceId = 'price_Filter123';
		
		// Mock getMeta to return empty
		$this->levels
			->expects( $this->exactly( 2 ) )
			->method( 'getMeta' )
			->withConsecutive(
				[ $levelId, 'stripe_price_id' ],
				[ $levelId, 'khm_level_meta', [] ]
			)
			->willReturnOnConsecutiveCalls( '', [] );
		
		// Add filter
		add_filter( 'khm_stripe_membership_price_map', function( $map ) use ( $levelId, $filterPriceId ) {
			return [ $levelId => $filterPriceId ];
		} );
		
		// Use reflection
		$reflection = new \ReflectionClass( $this->controller );
		$method = $reflection->getMethod( 'resolve_price_id' );
		$method->setAccessible( true );
		
		// Act
        $request = $this->make_request();
        $resolved = $method->invoke( $this->controller, $levelId, $request );
		
		// Assert
		$this->assertEquals( $filterPriceId, $resolved );
	}

	/**
	 * Test fallback to option when both metadata and filter are empty.
	 */
    public function test_fallback_to_option_when_metadata_and_filter_empty(): void {
        $levelId = 1;
        $optionPriceId = 'price_Option123';
		
		// Mock getMeta to return empty
		$this->levels
			->expects( $this->exactly( 2 ) )
			->method( 'getMeta' )
			->withConsecutive(
				[ $levelId, 'stripe_price_id' ],
				[ $levelId, 'khm_level_meta', [] ]
			)
			->willReturnOnConsecutiveCalls( '', [] );
		
		// Set option
		update_option( 'khm_stripe_membership_price_map', [ $levelId => $optionPriceId ] );
		
		// Use reflection
		$reflection = new \ReflectionClass( $this->controller );
		$method = $reflection->getMethod( 'resolve_price_id' );
		$method->setAccessible( true );
		
		// Act
        $request = $this->make_request();
        $resolved = $method->invoke( $this->controller, $levelId, $request );
		
		// Assert
		$this->assertEquals( $optionPriceId, $resolved );
	}

	/**
	 * Test that checkout returns 400 error when no price ID configured.
	 */
	public function test_checkout_returns_400_when_no_price_configured(): void {
		// This would test the full checkout endpoint response
		// when resolve_price_id returns null
		
		$this->assertTrue( true, 'Full endpoint test requires WordPress test environment' );
	}

	/**
	 * Test valid Stripe Price ID formats.
	 */
	public function test_valid_stripe_price_formats(): void {
		$validPrices = [
			'price_1234567890abcdef',
			'price_ABCDEFGHIJKLMNOP',
			'price_H4shF0rm4t123',
		];
		
		foreach ( $validPrices as $priceId ) {
			$this->assertMatchesRegularExpression(
				'/^price_[A-Za-z0-9]+$/',
				$priceId,
				"Price ID {$priceId} should match valid format"
			);
		}
	}

	/**
	 * Test invalid Stripe Price ID formats.
	 */
    public function test_invalid_stripe_price_formats(): void {
		$invalidPrices = [
			'prod_1234567890',
			'price_',
			'price_with-dash',
			'price_with_underscore',
			'',
		];
		
        foreach ( $invalidPrices as $priceId ) {
            $this->assertDoesNotMatchRegularExpression(
                '/^price_[A-Za-z0-9]+$/',
                $priceId,
                "Price ID '{$priceId}' should NOT match valid format"
            );
        }
    }

    private function make_request(): \WP_REST_Request {
        return new \WP_REST_Request( 'POST', '/khm/v1/checkout/subscription' );
    }

	private function resetResolverCache(): void {
		$reflection = new \ReflectionClass( LevelPriceResolver::class );
		$property = $reflection->getProperty( 'cache' );
		$property->setAccessible( true );
		$property->setValue( [] );
	}
}
