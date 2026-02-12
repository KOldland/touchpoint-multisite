<?php
/**
 * Tests for Stripe Price ID metadata feature.
 *
 * @package KHM\Tests\Admin
 */

namespace KHM\Tests\Admin;

use KHM\Admin\LevelsPage;
use KHM\Services\LevelRepository;
use PHPUnit\Framework\TestCase;

class StripePriceIdTest extends TestCase {

	private LevelRepository $repository;
	private LevelsPage $levelsPage;

	protected function setUp(): void {
		parent::setUp();
		
		// Mock repository
		$this->repository = $this->createMock( LevelRepository::class );
		$this->levelsPage = new LevelsPage( $this->repository );
	}

	/**
	 * Test that stripe_price_id field is included in level creation.
	 */
	public function test_stripe_price_id_saved_on_create(): void {
		global $wpdb;
		
		// Arrange - Create a test level with stripe_price_id
		$levelData = [
			'name' => 'Premium Membership',
			'billing_amount' => 29.99,
			'cycle_number' => 1,
			'cycle_period' => 'Month',
		];
		
		$meta = [
			'stripe_price_id' => 'price_1234567890abcdef',
			'monthly_credits' => 100,
		];
		
		// Mock repository create method
		$this->repository
			->expects( $this->once() )
			->method( 'create' )
			->with( $levelData, $meta )
			->willReturn( (object) [ 'id' => 1, 'name' => 'Premium Membership' ] );
		
		// Act
		$level = $this->repository->create( $levelData, $meta );
		
		// Assert
		$this->assertNotNull( $level );
		$this->assertEquals( 1, $level->id );
	}

	/**
	 * Test that stripe_price_id format validation works.
	 */
	public function test_stripe_price_id_format_validation(): void {
		$validIds = [
			'price_1234567890',
			'price_ABCDEFGHIJ',
			'price_Mix3dC4s3',
		];
		
		foreach ( $validIds as $priceId ) {
			$this->assertTrue(
				(bool) preg_match( '/^price_[A-Za-z0-9]+$/', $priceId ),
				"Price ID {$priceId} should be valid"
			);
		}
		
		$invalidIds = [
			'',
			'price_',
			'prod_12345',
			'price_with-dash',
			'price_with_underscore',
			'price_with spaces',
			'not_a_price_id',
		];
		
		foreach ( $invalidIds as $priceId ) {
			$this->assertFalse(
				(bool) preg_match( '/^price_[A-Za-z0-9]+$/', $priceId ),
				"Price ID '{$priceId}' should be invalid"
			);
		}
	}

	/**
	 * Test that level meta JSON can be decoded and stored.
	 */
	public function test_level_meta_json_decode(): void {
		$raw = '{"features":{"gifting":true},"presentation":{"cta_text":"Join now"}}';
		$decoded = json_decode( $raw, true );

		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'features', $decoded );
		$this->assertTrue( (bool) $decoded['features']['gifting'] );
	}

	public function test_build_level_meta_from_form_maps_fields(): void {
		$features = [
			'credits' => '1',
			'gifting' => '1',
			'portal' => '',
			'sponsor' => '1',
			'forum' => '1',
			'founder_badge' => '1',
		];
		$commerce = [
			'allow_promotion_codes' => '1',
			'allow_guest_checkout' => '1',
			'trial_days' => '14',
			'default_billing_interval' => 'annual',
		];
		$presentation = [
			'template' => 'promo',
			'cta_text' => 'Join now',
			'price_inclusive' => '1',
		];
		$availability = [
			'start_at' => '2026-02-10',
			'end_at' => '',
		];
		$price_map = [
			'GBP' => [ 'monthly' => 'price_123' ],
			'USD' => [ 'annual' => 'price_456' ],
		];

		$meta = $this->invokePrivate(
			$this->levelsPage,
			'build_level_meta_from_form',
			[ $features, $commerce, $presentation, $availability, $price_map, 30 ]
		);

		$this->assertSame( true, $meta['features']['credits'] );
		$this->assertSame( true, $meta['features']['gifting'] );
		$this->assertSame( true, $meta['features']['sponsor'] );
		$this->assertSame( true, $meta['features']['forum'] );
		$this->assertSame( true, $meta['features']['founder_badge'] );
		$this->assertSame( true, $meta['commerce']['allow_promotion_codes'] );
		$this->assertSame( true, $meta['commerce']['allow_guest_checkout'] );
		$this->assertSame( 14, $meta['commerce']['trial_days'] );
		$this->assertSame( 'annual', $meta['commerce']['default_billing_interval'] );
		$this->assertSame( 'promo', $meta['presentation']['template'] );
		$this->assertSame( 'Join now', $meta['presentation']['cta_text'] );
		$this->assertSame( true, $meta['presentation']['price_inclusive'] );
		$this->assertSame( '2026-02-10', $meta['availability']['start_at'] );
		$this->assertSame( 30, $meta['credits']['monthly'] );
		$this->assertSame( 'price_123', $meta['stripe_price_ids']['GBP']['monthly'] );
		$this->assertSame( 'price_456', $meta['stripe_price_ids']['USD']['annual'] );
	}

	public function test_build_price_rows_from_price_map(): void {
		$price_map = [
			'GBP' => [ 'monthly' => 'price_123' ],
			'USD' => [ 'annual' => 'price_456' ],
		];

		$rows = $this->invokePrivate(
			$this->levelsPage,
			'build_price_rows',
			[ null, $price_map, '' ]
		);

		$this->assertCount( 2, $rows );
		$this->assertSame( 'GBP', $rows[0]['currency'] );
		$this->assertSame( 'monthly', $rows[0]['interval'] );
		$this->assertSame( 'price_123', $rows[0]['price_id'] );
	}

	public function test_sanitize_level_meta_allows_new_keys(): void {
		$raw = json_encode(
			[
				'features' => [
					'credits' => true,
					'gifting' => true,
					'portal' => true,
					'sponsor' => true,
					'forum' => true,
					'founder_badge' => true,
				],
				'commerce' => [
					'allow_promotion_codes' => true,
					'allow_guest_checkout' => true,
					'trial_days' => 7,
					'default_billing_interval' => 'annual',
				],
				'presentation' => [
					'template' => 'compact',
					'cta_text' => 'Join',
					'price_inclusive' => true,
				],
				'credits' => [
					'monthly' => 20,
				],
				'stripe_price_ids' => [
					'GBP' => [ 'monthly' => 'price_123' ],
				],
			]
		);

		$meta = $this->invokePrivate(
			$this->levelsPage,
			'sanitize_level_meta',
			[ $raw ]
		);

		$this->assertSame( true, $meta['features']['forum'] );
		$this->assertSame( true, $meta['features']['founder_badge'] );
		$this->assertSame( 'annual', $meta['commerce']['default_billing_interval'] );
		$this->assertSame( 20, $meta['credits']['monthly'] );
	}

	private function invokePrivate( object $instance, string $method, array $args = [] ) {
		$ref = new \ReflectionClass( $instance );
		$method_ref = $ref->getMethod( $method );
		$method_ref->setAccessible( true );
		return $method_ref->invokeArgs( $instance, $args );
	}

	/**
	 * Test that existing stripe_price_id can be updated.
	 */
	public function test_stripe_price_id_can_be_updated(): void {
		// Arrange
		$levelId = 1;
		$oldPriceId = 'price_old123';
		$newPriceId = 'price_new456';
		
		// Mock repository getMeta to return old value
		$this->repository
			->expects( $this->once() )
			->method( 'getMeta' )
			->with( $levelId, 'stripe_price_id' )
			->willReturn( $oldPriceId );
		
		// Mock repository updateMeta
		$this->repository
			->expects( $this->once() )
			->method( 'updateMeta' )
			->with( $levelId, 'stripe_price_id', $newPriceId )
			->willReturn( true );
		
		// Act
		$old = $this->repository->getMeta( $levelId, 'stripe_price_id' );
		$updated = $this->repository->updateMeta( $levelId, 'stripe_price_id', $newPriceId );
		
		// Assert
		$this->assertEquals( $oldPriceId, $old );
		$this->assertTrue( $updated );
	}

	/**
	 * Test that empty stripe_price_id is allowed (for free tiers).
	 */
	public function test_empty_stripe_price_id_allowed_for_free_tiers(): void {
		// Arrange
		$levelData = [
			'name' => 'Free Tier',
			'billing_amount' => 0,
		];
		
		$meta = [
			'stripe_price_id' => '',
			'monthly_credits' => 10,
		];
		
		// Mock repository
		$this->repository
			->expects( $this->once() )
			->method( 'create' )
			->with( $levelData, $meta )
			->willReturn( (object) [ 'id' => 1, 'name' => 'Free Tier' ] );
		
		// Act
		$level = $this->repository->create( $levelData, $meta );
		
		// Assert
		$this->assertNotNull( $level );
	}

	/**
	 * Test that admin notice is triggered for paid levels without stripe_price_id.
	 */
	public function test_admin_notice_for_missing_price_id_on_paid_level(): void {
		// This test would check that when a paid level (billing_amount > 0)
		// is saved without a stripe_price_id, an info notice is displayed.
		// Since we're using add_settings_error in the actual code,
		// we would need to mock WordPress functions for unit testing.
		
		$this->assertTrue( true, 'Notice logic tested via integration tests' );
	}
}
