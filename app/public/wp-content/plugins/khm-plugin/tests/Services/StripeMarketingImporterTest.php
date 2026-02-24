<?php
/**
 * Tests for StripeMarketingImporter.
 *
 * @package KHM\Tests\Services
 */

namespace KHM\Tests\Services;

use KHM\Services\LevelRepository;
use KHM\Services\StripeMarketingImporter;
use PHPUnit\Framework\TestCase;

class StripeMarketingImporterTest extends TestCase {

	public function test_import_product_updates_marketing_features_meta(): void {
		$repo = $this->createMock( LevelRepository::class );
		$repo
			->expects( $this->once() )
			->method( 'getMeta' )
			->with( 22, 'khm_level_meta', [] )
			->willReturn( [ 'presentation' => [ 'template' => 'compact' ] ] );
		$repo
			->expects( $this->once() )
			->method( 'updateMeta' )
			->with(
				22,
				'khm_level_meta',
				$this->callback(
					function ( $meta ) {
						return isset( $meta['presentation']['marketing_features'] )
							&& $meta['presentation']['marketing_features'] === [ 'Feature one', 'Feature two', 'Feature three' ];
					}
				)
			)
			->willReturn( true );

		$importer = new class( $repo ) extends StripeMarketingImporter {
			protected function retrieveProduct( string $productId ) {
				return (object) [
					'id' => $productId,
					'description' => "Feature one\nFeature two, Feature three",
					'metadata' => (object) [ 'wp_level_id' => '22' ],
				];
			}

			protected function listPriceIdsForProduct( string $productId ): array {
				return [];
			}
		};

		$result = $importer->importProductToLevel( 'prod_123' );
		$this->assertSame( 22, $result['level_id'] );
		$this->assertSame( [ 'Feature one', 'Feature two', 'Feature three' ], $result['lines'] );
	}
}
