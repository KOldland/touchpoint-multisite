<?php
/**
 * Reliability tests for StripeMarketingImporter.
 *
 * @package KHM\Tests\Services
 */

namespace KHM\Tests\Services;

use KHM\Services\LevelRepository;
use KHM\Services\StripeMarketingImportAuditLogger;
use KHM\Services\StripeMarketingImporter;
use KHM\Models\MembershipLevel;
use PHPUnit\Framework\TestCase;

class StripeMarketingImporterReliabilityTest extends TestCase {

	public function test_import_skips_when_content_unchanged(): void {
		$repo = $this->createMock( LevelRepository::class );
		$repo
			->expects( $this->once() )
			->method( 'get' )
			->with( 22, true )
			->willReturn( new MembershipLevel( [ 'id' => 22, 'name' => 'Premium' ] ) );
		$repo
			->expects( $this->once() )
			->method( 'getMeta' )
			->with( 22, 'khm_level_meta', [] )
			->willReturn(
				[
					'presentation' => [
						'marketing_features' => [ 'Feature one', 'Feature two' ],
					],
					'stripe_product_id' => 'prod_same1',
				]
			);

		$repo
			->expects( $this->never() )
			->method( 'update' );

		$auditLogger = $this->createMock( StripeMarketingImportAuditLogger::class );
		$auditLogger->method( 'log' );

		$importer = new class( $repo, $auditLogger ) extends StripeMarketingImporter {
			protected function retrieveProduct( string $productId ) {
				return (object) [
					'id' => $productId,
					'description' => "Feature one\nFeature two",
					'metadata' => (object) [ 'wp_level_id' => '22' ],
				];
			}

			protected function listPriceIdsForProduct( string $productId ): array {
				return [];
			}

			protected function acquireImportLock( string $productId ): bool {
				return true;
			}

			protected function releaseImportLock( string $productId ): void {
				// no-op
			}

			protected function audit( array $data ): void {
				// no-op for deterministic tests
			}
		};

		$result = $importer->importProductToLevel( 'prod_same1', null, false, 'cli' );
		$this->assertSame( 'unchanged', $result['skipped_reason'] );
		$this->assertFalse( $result['changed'] );
	}

	public function test_import_skips_when_lock_is_not_acquired(): void {
		$repo = $this->createMock( LevelRepository::class );
		$repo->expects( $this->never() )->method( 'getMeta' );

		$auditLogger = $this->createMock( StripeMarketingImportAuditLogger::class );
		$auditLogger->method( 'log' );

		$importer = new class( $repo, $auditLogger ) extends StripeMarketingImporter {
			protected function acquireImportLock( string $productId ): bool {
				return false;
			}

			protected function retrieveProduct( string $productId ) {
				throw new \RuntimeException( 'Should not be called when lock fails.' );
			}

			protected function audit( array $data ): void {
				// no-op for deterministic tests
			}
		};

		$result = $importer->importProductToLevel( 'prod_locked1', 10, false, 'webhook' );
		$this->assertSame( 'locked', $result['skipped_reason'] );
		$this->assertFalse( $result['changed'] );
	}
}
