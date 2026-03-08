<?php

namespace KHM\Services;

use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;

class StripeMarketingImporter {

	private const IMPORT_LOCK_TTL = 45;
	public const PRODUCT_ID_REGEX = '/^prod_[A-Za-z0-9]+$/';

	private LevelRepository $levelRepo;
	private StripeMarketingImportAuditLogger $auditLogger;

	public function __construct( ?LevelRepository $levelRepo = null, ?StripeMarketingImportAuditLogger $auditLogger = null ) {
		$this->levelRepo = $levelRepo ?: new LevelRepository();
		$this->auditLogger = $auditLogger ?: new StripeMarketingImportAuditLogger();
	}

	public static function isStripeSdkAvailable(): bool {
		if ( class_exists( '\Stripe\Stripe' ) ) {
			return true;
		}

		$sdkInit = dirname( __DIR__, 2 ) . '/vendor/stripe/stripe-php/init.php';
		if ( file_exists( $sdkInit ) ) {
			require_once $sdkInit;
		}

		return class_exists( '\Stripe\Stripe' );
	}

	/**
	 * Import Stripe product marketing text to a level's khm_level_meta.presentation.marketing_features.
	 *
	 * @param string   $productId Stripe Product ID.
	 * @param int|null $levelId   Optional explicit level ID.
	 * @param bool     $dryRun    If true, parse and resolve only (no persistence).
	 * @param string   $source    Source context (cli|webhook|ajax|manual).
	 * @return array{level_id:int,lines:array<int,string>,dry_run:bool,changed:bool,created:bool,skipped_reason:?string,content_hash:string}
	 */
	public function importProductToLevel( string $productId, ?int $levelId = null, bool $dryRun = false, string $source = 'manual' ): array {
		$startedAt = microtime( true );
		$productId = trim( $productId );
		if ( $productId === '' ) {
			throw new \RuntimeException( 'Stripe product ID is required.' );
		}
		if ( ! self::isValidProductId( $productId ) ) {
			throw new \RuntimeException( 'Invalid Stripe product ID format. Expected prod_ followed by alphanumeric characters.' );
		}

		$source = sanitize_key( $source );
		if ( $source === '' ) {
			$source = 'manual';
		}

		$lockAcquired = false;
		$hadError = false;
		$result = [
			'level_id'      => (int) ( $levelId ?? 0 ),
			'lines'         => [],
			'dry_run'       => $dryRun,
			'changed'       => false,
			'created'       => false,
			'skipped_reason'=> null,
			'content_hash'  => '',
		];

		try {
			if ( ! $dryRun ) {
				$lockAcquired = $this->acquireImportLock( $productId );
				if ( ! $lockAcquired ) {
					$result['skipped_reason'] = 'locked';
					return $result;
				}
			}

			$product = $this->retrieveProduct( $productId );

			if ( ! $levelId ) {
				$levelId = $this->findLevelIdByProductId( $productId );
			}

			if ( ! $levelId ) {
				$metaLevel = $this->extractMetadataValue( $product->metadata ?? null, 'wp_level_id' );
				if ( $metaLevel !== null && $metaLevel !== '' ) {
					$levelId = (int) $metaLevel;
				} else {
					foreach ( $this->listPriceIdsForProduct( $productId ) as $priceId ) {
						$levelId = $this->findLevelIdByPriceId( $priceId );
						if ( $levelId ) {
							break;
						}
					}
				}
			}

			if ( ! $levelId ) {
				if ( $dryRun ) {
					throw new \RuntimeException( 'No WP level found for product ' . $productId . ' (dry-run does not create levels).' );
				}
				$created = $this->levelRepo->create(
					[
						'name' => sanitize_text_field( (string) ( $product->name ?? 'Stripe Imported Level' ) ),
						'description' => wp_kses_post( (string) ( $product->description ?? '' ) ),
						'allow_signups' => 1,
					],
					[
						'khm_level_meta' => [
							'stripe_product_id' => $productId,
						],
					]
				);
				if ( ! $created || empty( $created->id ) ) {
					throw new \RuntimeException( 'Failed to create WP level for product ' . $productId );
				}
				$levelId = (int) $created->id;
				$result['created'] = true;
			}

			$text = trim(
				(string) (
					$product->description
					?? $this->extractMetadataValue( $product->metadata ?? null, 'marketing_feature_list' )
					?? ''
				)
			);
			$lines = $this->parseLinesFromText( $text );
			$contentHash = sha1( wp_json_encode( array_values( $lines ) ) ?: '' );

			$result['level_id'] = (int) $levelId;
			$result['lines'] = $lines;
			$result['content_hash'] = $contentHash;

		if ( ! $dryRun ) {
			$level = $this->levelRepo->get( $levelId, true );
			if ( ! $level ) {
				throw new \RuntimeException( 'WP level not found for id ' . $levelId );
			}

			$prices = $this->listPricesForProduct( $productId );
			$mirror = $this->buildMirrorData( $product, $prices );

			$meta = $this->levelRepo->getMeta( $levelId, 'khm_level_meta', [] );
			if ( ! is_array( $meta ) ) {
				$meta = [];
			}
			if ( ! isset( $meta['presentation'] ) || ! is_array( $meta['presentation'] ) ) {
					$meta['presentation'] = [];
				}

				$existingLines = isset( $meta['presentation']['marketing_features'] ) && is_array( $meta['presentation']['marketing_features'] )
					? array_values( array_map( 'strval', $meta['presentation']['marketing_features'] ) )
					: [];
				$existingHash = sha1( wp_json_encode( $existingLines ) ?: '' );
				$currentProductId = isset( $meta['stripe_product_id'] ) ? (string) $meta['stripe_product_id'] : '';

				if ( $existingHash === $contentHash && $currentProductId === $productId ) {
					$result['skipped_reason'] = 'unchanged';
					return $result;
				}

			$meta['presentation']['marketing_features'] = $lines;
			if ( ! empty( $mirror['price_map'] ) ) {
				$meta['stripe_price_ids'] = $mirror['price_map'];
			}
			$meta['stripe_product_id'] = $productId;
			$payload = [
				'name' => $mirror['name'],
				'description' => $mirror['description'],
			];
			if ( $mirror['primary_amount'] !== null ) {
				if ( $mirror['primary_is_recurring'] ) {
					$payload['initial_payment'] = $mirror['primary_amount'];
					$payload['billing_amount'] = $mirror['primary_amount'];
					$payload['cycle_number'] = $mirror['cycle_number'] ?? 1;
					$payload['cycle_period'] = $mirror['cycle_period'] ?? 'Month';
				} else {
					$payload['initial_payment'] = $mirror['primary_amount'];
					$payload['billing_amount'] = 0.0;
					$payload['cycle_number'] = 0;
					$payload['cycle_period'] = 'Month';
				}
			}

			$this->levelRepo->update( $levelId, $payload, [ 'stripe_price_id' => $mirror['primary_price_id'], 'khm_level_meta' => $meta ] );
			$result['changed'] = true;
		}

			return $result;
		} catch ( \Throwable $e ) {
			$hadError = true;
			$this->audit( [
				'product_id' => $productId,
				'level_id' => isset( $levelId ) ? (int) $levelId : null,
				'source' => $source,
				'status' => 'error',
				'dry_run' => $dryRun,
				'lines_count' => is_array( $result['lines'] ) ? count( $result['lines'] ) : 0,
				'content_hash' => (string) ( $result['content_hash'] ?? '' ),
				'message' => $e->getMessage(),
				'duration_ms' => $this->durationMs( $startedAt ),
				'context' => [
					'skipped_reason' => $result['skipped_reason'],
				],
			] );
			throw $e;
		} finally {
			if ( ! $dryRun && $lockAcquired ) {
				$this->releaseImportLock( $productId );
			}

			if ( ! $hadError && $result['skipped_reason'] !== null ) {
				$this->audit( [
					'product_id' => $productId,
					'level_id' => isset( $result['level_id'] ) ? (int) $result['level_id'] : null,
					'source' => $source,
					'status' => 'skipped',
					'dry_run' => $dryRun,
					'lines_count' => is_array( $result['lines'] ) ? count( $result['lines'] ) : 0,
					'content_hash' => (string) ( $result['content_hash'] ?? '' ),
					'skipped_reason' => (string) $result['skipped_reason'],
					'duration_ms' => $this->durationMs( $startedAt ),
				] );
			} elseif ( ! $hadError && isset( $result['level_id'] ) && (int) $result['level_id'] > 0 ) {
				$this->audit( [
					'product_id' => $productId,
					'level_id' => (int) $result['level_id'],
					'source' => $source,
					'status' => ! empty( $result['changed'] ) ? 'imported' : 'resolved',
					'dry_run' => $dryRun,
					'lines_count' => is_array( $result['lines'] ) ? count( $result['lines'] ) : 0,
					'content_hash' => (string) ( $result['content_hash'] ?? '' ),
					'duration_ms' => $this->durationMs( $startedAt ),
				] );
			}
		}
	}

	protected function parseLinesFromText( string $text ): array {
		if ( $text === '' ) {
			return [];
		}

		$raw   = preg_split( '/\r\n|\r|\n/', strip_tags( $text ) ) ?: [];
		$lines = [];

		foreach ( $raw as $row ) {
			$row = trim( (string) $row );
			if ( $row === '' ) {
				continue;
			}

			if ( strpos( $row, ',' ) !== false && strlen( $row ) < 400 ) {
				foreach ( explode( ',', $row ) as $part ) {
					$part = trim( $part );
					if ( $part !== '' ) {
						$lines[] = mb_substr( $part, 0, 500 );
					}
				}
			} else {
				$lines[] = mb_substr( $row, 0, 500 );
			}
		}

		return array_slice( $lines, 0, 50 );
	}

	/**
	 * @return object
	 */
	protected function retrieveProduct( string $productId ) {
		$secret = function_exists( 'khm_get_stripe_secret' )
			? (string) ( khm_get_stripe_secret( 'KH_STRIPE_SECRET_KEY' ) ?? '' )
			: '';
		if ( empty( $secret ) ) {
			throw new \RuntimeException( 'Stripe secret key not configured.' );
		}
		if ( ! self::isStripeSdkAvailable() ) {
			throw new \RuntimeException( 'Stripe SDK is missing. Run "composer install --no-dev" in wp-content/plugins/khm-plugin.' );
		}

		try {
			Stripe::setApiKey( (string) $secret );
			return Product::retrieve( $productId );
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( 'Stripe product retrieval failed: ' . $e->getMessage() );
		}
	}

	/**
	 * @return array<int,string>
	 */
	protected function listPriceIdsForProduct( string $productId ): array {
		return array_values(
			array_filter(
				array_map(
					static function ( $price ) {
						return isset( $price->id ) ? (string) $price->id : '';
					},
					$this->listPricesForProduct( $productId )
				),
				static fn( string $id ) => $id !== ''
			)
		);
	}

	/**
	 * @return array<int,object>
	 */
	protected function listPricesForProduct( string $productId ): array {
		$pricesOut = [];

		try {
			$prices = Price::all( [ 'product' => $productId, 'limit' => 100 ] );
			foreach ( $prices->data ?? [] as $price ) {
				if ( is_object( $price ) && ( ! isset( $price->active ) || $price->active ) ) {
					$pricesOut[] = $price;
				}
			}
		} catch ( \Throwable $e ) {
			// Intentionally ignore and continue with other matching strategies.
		}

		return $pricesOut;
	}

	/**
	 * @param array<int,object> $prices
	 * @return array{name:string,description:string,primary_price_id:string,primary_amount:?float,primary_is_recurring:bool,cycle_number:?int,cycle_period:?string,price_map:array<string,array<string,string>>}
	 */
	protected function buildMirrorData( object $product, array $prices ): array {
		$name = sanitize_text_field( trim( (string) ( $product->name ?? '' ) ) );
		$description = trim( (string) ( $product->description ?? '' ) );
		$description = wp_kses_post( $description );

		$priceMap = [];
		foreach ( $prices as $price ) {
			$id = isset( $price->id ) ? (string) $price->id : '';
			$currency = isset( $price->currency ) ? strtoupper( (string) $price->currency ) : '';
			if ( $id === '' || $currency === '' ) {
				continue;
			}
			$interval = 'one_time';
			if ( isset( $price->recurring ) && is_object( $price->recurring ) && isset( $price->recurring->interval ) ) {
				$interval = $this->normalizeIntervalKey( (string) $price->recurring->interval );
			}
			if ( $interval === 'one_time' ) {
				continue;
			}
			$priceMap[ $currency ][ $interval ] = $id;
		}

		$primary = $this->selectPrimaryPrice( $prices );
		$primaryPriceId = $primary && isset( $primary->id ) ? (string) $primary->id : '';
		$primaryAmount = null;
		$primaryRecurring = false;
		$cycleNumber = null;
		$cyclePeriod = null;

		if ( $primary && isset( $primary->unit_amount ) && is_numeric( $primary->unit_amount ) ) {
			$primaryAmount = round( ( (float) $primary->unit_amount ) / 100, 2 );
		}
		if ( $primary && isset( $primary->recurring ) && is_object( $primary->recurring ) ) {
			$primaryRecurring = true;
			$cycleNumber = isset( $primary->recurring->interval_count ) ? max( 1, (int) $primary->recurring->interval_count ) : 1;
			$cyclePeriod = $this->mapStripePeriodToLevelPeriod( (string) ( $primary->recurring->interval ?? 'month' ) );
		}

		return [
			'name' => $name,
			'description' => $description,
			'primary_price_id' => $primaryPriceId,
			'primary_amount' => $primaryAmount,
			'primary_is_recurring' => $primaryRecurring,
			'cycle_number' => $cycleNumber,
			'cycle_period' => $cyclePeriod,
			'price_map' => $priceMap,
		];
	}

	/**
	 * @param array<int,object> $prices
	 * @return object|null
	 */
	protected function selectPrimaryPrice( array $prices ): ?object {
		$monthly = null;
		$annual = null;
		$firstRecurring = null;
		$firstOneTime = null;

		foreach ( $prices as $price ) {
			$recurring = isset( $price->recurring ) && is_object( $price->recurring );
			if ( $recurring ) {
				$interval = isset( $price->recurring->interval ) ? (string) $price->recurring->interval : '';
				if ( $interval === 'month' && $monthly === null ) {
					$monthly = $price;
				}
				if ( $interval === 'year' && $annual === null ) {
					$annual = $price;
				}
				if ( $firstRecurring === null ) {
					$firstRecurring = $price;
				}
			} elseif ( $firstOneTime === null ) {
				$firstOneTime = $price;
			}
		}

		return $monthly ?: $annual ?: $firstRecurring ?: $firstOneTime;
	}

	protected function normalizeIntervalKey( string $interval ): string {
		$key = sanitize_key( $interval );
		if ( $key === 'month' ) {
			return 'monthly';
		}
		if ( $key === 'year' ) {
			return 'annual';
		}
		if ( $key === 'week' ) {
			return 'weekly';
		}
		if ( $key === 'day' ) {
			return 'daily';
		}

		return $key !== '' ? $key : 'monthly';
	}

	protected function mapStripePeriodToLevelPeriod( string $period ): string {
		$key = strtolower( trim( $period ) );
		if ( $key === 'day' ) {
			return 'Day';
		}
		if ( $key === 'week' ) {
			return 'Week';
		}
		if ( $key === 'year' ) {
			return 'Year';
		}

		return 'Month';
	}

	/**
	 * @param mixed $metadata
	 */
	protected function extractMetadataValue( $metadata, string $key ): ?string {
		if ( is_array( $metadata ) && array_key_exists( $key, $metadata ) ) {
			return is_scalar( $metadata[ $key ] ) ? (string) $metadata[ $key ] : null;
		}

		if ( is_object( $metadata ) && isset( $metadata->{$key} ) ) {
			return is_scalar( $metadata->{$key} ) ? (string) $metadata->{$key} : null;
		}

		return null;
	}

	protected function findLevelIdByPriceId( string $priceId ): ?int {
		$priceId = trim( $priceId );
		if ( $priceId === '' ) {
			return null;
		}

		$levels = $this->levelRepo->all( true );
		foreach ( $levels as $level ) {
			$levelMeta = is_array( $level->meta ?? null ) ? $level->meta : [];

			if ( isset( $levelMeta['stripe_price_id'] ) && (string) $levelMeta['stripe_price_id'] === $priceId ) {
				return (int) $level->id;
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
						return (int) $level->id;
					}
				}
			}
		}

		return null;
	}

	protected function findLevelIdByProductId( string $productId ): ?int {
		$productId = trim( $productId );
		if ( $productId === '' ) {
			return null;
		}

		$levels = $this->levelRepo->all( true );
		foreach ( $levels as $level ) {
			$levelMeta = is_array( $level->meta ?? null ) ? $level->meta : [];
			$khmMeta = $levelMeta['khm_level_meta'] ?? [];
			if ( is_string( $khmMeta ) ) {
				$decoded = json_decode( $khmMeta, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$khmMeta = $decoded;
				}
			}
			if ( ! is_array( $khmMeta ) ) {
				continue;
			}
			if ( isset( $khmMeta['stripe_product_id'] ) && (string) $khmMeta['stripe_product_id'] === $productId ) {
				return (int) $level->id;
			}
		}

		return null;
	}

	protected function acquireImportLock( string $productId ): bool {
		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
			return true;
		}

		$key = $this->lockKey( $productId );
		$existing = (int) get_transient( $key );
		if ( $existing > 0 && ( time() - $existing ) < self::IMPORT_LOCK_TTL ) {
			return false;
		}

		set_transient( $key, time(), self::IMPORT_LOCK_TTL );
		return true;
	}

	protected function releaseImportLock( string $productId ): void {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( $this->lockKey( $productId ) );
		}
	}

	protected function lockKey( string $productId ): string {
		return 'khm_stripe_marketing_import_lock_' . md5( $productId );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	protected function audit( array $data ): void {
		try {
			$this->auditLogger->log( $data );
		} catch ( \Throwable $e ) {
			error_log( 'Stripe marketing audit log failed: ' . $e->getMessage() );
		}
	}

	protected function durationMs( float $startedAt ): int {
		return (int) round( max( 0, ( microtime( true ) - $startedAt ) * 1000 ) );
	}

	public static function isValidProductId( string $productId ): bool {
		return (bool) preg_match( self::PRODUCT_ID_REGEX, trim( $productId ) );
	}
}
