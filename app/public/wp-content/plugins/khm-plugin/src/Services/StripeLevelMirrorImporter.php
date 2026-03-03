<?php

namespace KHM\Services;

use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;

class StripeLevelMirrorImporter {

	private LevelRepository $levelRepo;
	private StripeMarketingImportAuditLogger $auditLogger;

	public function __construct( ?LevelRepository $levelRepo = null, ?StripeMarketingImportAuditLogger $auditLogger = null ) {
		$this->levelRepo = $levelRepo ?: new LevelRepository();
		$this->auditLogger = $auditLogger ?: new StripeMarketingImportAuditLogger();
	}

	/**
	 * Scaffold for full Stripe -> Level mirroring.
	 *
	 * @return array{
	 *   level_id:int,
	 *   created:bool,
	 *   changed:bool,
	 *   dry_run:bool,
	 *   product_id:string,
	 *   resolved:array<string,mixed>,
	 *   level_payload:array<string,mixed>,
	 *   meta_payload:array<string,mixed>
	 * }
	 */
	public function importProductToLevel( string $productId, ?int $levelId = null, bool $dryRun = false, string $source = 'manual' ): array {
		$productId = trim( $productId );
		if ( $productId === '' ) {
			throw new \RuntimeException( 'Stripe product ID is required.' );
		}
		if ( ! StripeMarketingImporter::isValidProductId( $productId ) ) {
			throw new \RuntimeException( 'Invalid Stripe product ID format.' );
		}
		if ( ! StripeMarketingImporter::isStripeSdkAvailable() ) {
			throw new \RuntimeException( 'Stripe SDK is missing. Run "composer install --no-dev" in wp-content/plugins/khm-plugin.' );
		}

		$startedAt = microtime( true );
		$product = $this->retrieveProduct( $productId );
		$prices = $this->listPricesForProduct( $productId );

		if ( ! $levelId ) {
			$levelId = $this->resolveLevelId( $productId, $prices, $product );
		}

		$created = false;
		if ( ! $levelId ) {
			if ( $dryRun ) {
				throw new \RuntimeException( 'No WP level resolved for product ' . $productId . ' in dry-run.' );
			}
			$createdLevel = $this->levelRepo->create(
				[
					'name' => sanitize_text_field( (string) ( $product->name ?? 'Stripe Imported Level' ) ),
					'description' => wp_kses_post( (string) ( $product->description ?? '' ) ),
					'allow_signups' => 1,
				],
				[
					'khm_level_meta' => [ 'stripe_product_id' => $productId ],
				]
			);
			if ( ! $createdLevel || empty( $createdLevel->id ) ) {
				throw new \RuntimeException( 'Failed to create level for Stripe product ' . $productId );
			}
			$levelId = (int) $createdLevel->id;
			$created = true;
		}

		$existingMeta = $this->levelRepo->getMeta( (int) $levelId, 'khm_level_meta', [] );
		$existingMeta = is_array( $existingMeta ) ? $existingMeta : [];

		$mapped = StripeLevelMirrorMapping::mapToLevelPayload( $product, $prices, $existingMeta );
		$levelPayload = $mapped['level_payload'];
		$metaPayload = $mapped['meta_payload'];
		$resolved = $mapped['resolved'];

		$changed = false;
		if ( ! $dryRun ) {
			$metaUpdate = [ 'khm_level_meta' => $metaPayload ];
			if ( ! empty( $resolved['primary_price_id'] ) ) {
				$metaUpdate['stripe_price_id'] = (string) $resolved['primary_price_id'];
			}
			$changed = $this->levelRepo->update( (int) $levelId, $levelPayload, $metaUpdate );
			if ( ! $changed ) {
				throw new \RuntimeException( 'Level update failed for level #' . (int) $levelId );
			}
		}

		$this->auditLogger->log( [
			'product_id' => $productId,
			'level_id' => (int) $levelId,
			'source' => sanitize_key( $source ) ?: 'manual',
			'status' => $dryRun ? 'resolved' : 'imported',
			'dry_run' => $dryRun,
			'lines_count' => is_array( $metaPayload['presentation']['marketing_features'] ?? null ) ? count( $metaPayload['presentation']['marketing_features'] ) : 0,
			'content_hash' => sha1( wp_json_encode( $metaPayload ) ?: '' ),
			'duration_ms' => (int) round( ( microtime( true ) - $startedAt ) * 1000 ),
			'context' => [
				'scaffold' => true,
				'created' => $created,
				'changed' => $changed,
			],
		] );

		return [
			'level_id' => (int) $levelId,
			'created' => $created,
			'changed' => $changed,
			'dry_run' => $dryRun,
			'product_id' => $productId,
			'resolved' => $resolved,
			'level_payload' => $levelPayload,
			'meta_payload' => $metaPayload,
		];
	}

	/**
	 * @return object
	 */
	private function retrieveProduct( string $productId ) {
		$secret = function_exists( 'khm_get_stripe_secret' )
			? (string) ( khm_get_stripe_secret( 'KH_STRIPE_SECRET_KEY' ) ?? '' )
			: '';
		if ( ! is_string( $secret ) || trim( $secret ) === '' ) {
			throw new \RuntimeException( 'Stripe secret key not configured.' );
		}

		try {
			Stripe::setApiKey( trim( $secret ) );
			return Product::retrieve( $productId );
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( 'Stripe product retrieval failed: ' . $e->getMessage() );
		}
	}

	/**
	 * @return array<int,object>
	 */
	private function listPricesForProduct( string $productId ): array {
		$out = [];
		try {
			$prices = Price::all( [ 'product' => $productId, 'limit' => 100 ] );
			foreach ( $prices->data ?? [] as $price ) {
				if ( is_object( $price ) ) {
					$out[] = $price;
				}
			}
		} catch ( \Throwable $e ) {
			// Keep mapping resilient when Stripe price list call is unavailable.
		}
		return $out;
	}

	/**
	 * @param array<int,object> $prices
	 */
	private function resolveLevelId( string $productId, array $prices, object $product ): ?int {
		$byProductId = $this->findLevelIdByProductId( $productId );
		if ( $byProductId ) {
			return $byProductId;
		}

		// Optional legacy mapping path: disabled by default to avoid manual setup.
		if ( $this->allowLegacyWpLevelIdMapping() ) {
			$metaLevel = $this->extractMetadataValue( $product->metadata ?? null, 'wp_level_id' );
			if ( is_string( $metaLevel ) && $metaLevel !== '' ) {
				return (int) $metaLevel;
			}
		}

		$priceMatchedLevelIds = [];
		foreach ( $prices as $price ) {
			$priceId = isset( $price->id ) ? (string) $price->id : '';
			if ( $priceId === '' ) {
				continue;
			}
			foreach ( $this->findLevelIdsByPriceId( $priceId ) as $candidateLevelId ) {
				$priceMatchedLevelIds[ (int) $candidateLevelId ] = true;
			}
		}
		$uniquePriceMatches = array_keys( $priceMatchedLevelIds );
		if ( count( $uniquePriceMatches ) === 1 ) {
			return (int) $uniquePriceMatches[0];
		}
		if ( count( $uniquePriceMatches ) > 1 ) {
			throw new \RuntimeException(
				'Ambiguous Stripe price mapping for product ' . $productId .
				'. Multiple levels matched by price IDs: ' . implode( ', ', array_map( 'strval', $uniquePriceMatches ) )
			);
		}

		return null;
	}

	private function findLevelIdByProductId( string $productId ): ?int {
		foreach ( $this->levelRepo->all( true ) as $level ) {
			$levelMeta = is_array( $level->meta ?? null ) ? $level->meta : [];
			$khmMeta = $levelMeta['khm_level_meta'] ?? [];
			if ( is_string( $khmMeta ) ) {
				$decoded = json_decode( $khmMeta, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$khmMeta = $decoded;
				}
			}
			if ( is_array( $khmMeta ) && isset( $khmMeta['stripe_product_id'] ) && (string) $khmMeta['stripe_product_id'] === $productId ) {
				return (int) $level->id;
			}
		}
		return null;
	}

	/**
	 * @return array<int,int>
	 */
	private function findLevelIdsByPriceId( string $priceId ): array {
		$matches = [];
		foreach ( $this->levelRepo->all( true ) as $level ) {
			$levelMeta = is_array( $level->meta ?? null ) ? $level->meta : [];
			if ( isset( $levelMeta['stripe_price_id'] ) && (string) $levelMeta['stripe_price_id'] === $priceId ) {
				$matches[] = (int) $level->id;
			}

			$khmMeta = $levelMeta['khm_level_meta'] ?? [];
			if ( is_string( $khmMeta ) ) {
				$decoded = json_decode( $khmMeta, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$khmMeta = $decoded;
				}
			}
			if ( ! is_array( $khmMeta ) || ! isset( $khmMeta['stripe_price_ids'] ) || ! is_array( $khmMeta['stripe_price_ids'] ) ) {
				continue;
			}
			foreach ( $khmMeta['stripe_price_ids'] as $intervalMap ) {
				if ( ! is_array( $intervalMap ) ) {
					continue;
				}
				foreach ( $intervalMap as $candidatePriceId ) {
					if ( is_string( $candidatePriceId ) && $candidatePriceId === $priceId ) {
						$matches[] = (int) $level->id;
					}
				}
			}
		}
		return array_values( array_unique( array_map( 'intval', $matches ) ) );
	}

	/**
	 * @param mixed $metadata
	 */
	private function extractMetadataValue( $metadata, string $key ): ?string {
		if ( is_array( $metadata ) && array_key_exists( $key, $metadata ) ) {
			return is_scalar( $metadata[ $key ] ) ? (string) $metadata[ $key ] : null;
		}
		if ( is_object( $metadata ) && isset( $metadata->{$key} ) ) {
			return is_scalar( $metadata->{$key} ) ? (string) $metadata->{$key} : null;
		}
		return null;
	}

	private function allowLegacyWpLevelIdMapping(): bool {
		$enabled = false;
		if ( defined( 'KHM_STRIPE_LEGACY_WP_LEVEL_ID_MAPPING' ) ) {
			$enabled = (bool) KHM_STRIPE_LEGACY_WP_LEVEL_ID_MAPPING;
		} else {
			$enabled = (bool) get_option( 'khm_stripe_legacy_wp_level_id_mapping', false );
		}

		return (bool) apply_filters( 'khm_allow_legacy_wp_level_id_mapping', $enabled );
	}
}
