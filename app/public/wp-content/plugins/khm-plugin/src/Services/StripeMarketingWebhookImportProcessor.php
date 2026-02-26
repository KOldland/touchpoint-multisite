<?php

namespace KHM\Services;

class StripeMarketingWebhookImportProcessor {

	/** @var object */
	private $importer;
	private ?StripeMarketingImportDeadLetterStore $deadLetterStore;

	/**
	 * @param object|null $importer Importer implementing importProductToLevel().
	 */
	public function __construct( $importer = null, ?StripeMarketingImportDeadLetterStore $deadLetterStore = null ) {
		$this->importer = $importer ?: $this->resolveImporter();
		$this->deadLetterStore = $deadLetterStore ?: ( class_exists( StripeMarketingImportDeadLetterStore::class ) ? new StripeMarketingImportDeadLetterStore() : null );
	}

	/**
	 * Process one queued Stripe product.updated marketing import.
	 *
	 * @return array{status:string,attempt:int,max_attempts:int,message:string}
	 */
	public function process( string $productId, int $levelId = 0, int $attempt = 0 ): array {
		$productId = sanitize_text_field( trim( $productId ) );
		$levelId = max( 0, (int) $levelId );
		$attempt = max( 0, (int) $attempt );

		if ( $productId === '' ) {
			return [
				'status' => 'invalid',
				'attempt' => $attempt,
				'max_attempts' => 0,
				'message' => 'Missing product id',
			];
		}
		if ( ! StripeMarketingImporter::isValidProductId( $productId ) ) {
			return [
				'status' => 'invalid',
				'attempt' => $attempt,
				'max_attempts' => 0,
				'message' => 'Invalid product id format',
			];
		}

		$maxAttempts = (int) get_option( 'khm_stripe_marketing_import_max_attempts', 3 );
		$maxAttempts = max( 1, min( 10, $maxAttempts ) );

		try {
			$result = $this->importer->importProductToLevel( $productId, $levelId > 0 ? $levelId : null, false, 'webhook' );

			if ( ! empty( $result['skipped_reason'] ) && (string) $result['skipped_reason'] === 'locked' && ( $attempt + 1 ) < $maxAttempts ) {
				$delay = min( 300, 10 * ( 2 ** $attempt ) );
				$this->scheduleRetry( $productId, $levelId, $attempt + 1, $delay );
				do_action( 'khm_stripe_product_updated_import_retry_scheduled', $productId, $levelId, $attempt + 1, $delay, 'locked' );

				return [
					'status' => 'retry_scheduled',
					'attempt' => $attempt + 1,
					'max_attempts' => $maxAttempts,
					'message' => 'Import locked; retry scheduled',
				];
			}

			do_action( 'khm_stripe_product_updated_imported', $productId, $result['level_id'] ?? $levelId, $result );

			return [
				'status' => 'imported',
				'attempt' => $attempt,
				'max_attempts' => $maxAttempts,
				'message' => 'Imported successfully',
			];
		} catch ( \Throwable $e ) {
			if ( ( $attempt + 1 ) < $maxAttempts ) {
				$delay = min( 300, 30 * ( 2 ** $attempt ) );
				$this->scheduleRetry( $productId, $levelId, $attempt + 1, $delay );
				error_log( 'Stripe product.updated queued import failed (retry scheduled): ' . $e->getMessage() );
				do_action( 'khm_stripe_product_updated_import_retry_scheduled', $productId, $levelId, $attempt + 1, $delay, $e->getMessage() );

				return [
					'status' => 'retry_scheduled',
					'attempt' => $attempt + 1,
					'max_attempts' => $maxAttempts,
					'message' => $e->getMessage(),
				];
			}

			if ( $this->deadLetterStore ) {
				try {
					$this->deadLetterStore->insert( [
						'product_id' => $productId,
						'level_id' => $levelId,
						'source' => 'webhook',
						'attempts' => $attempt + 1,
						'error_message' => $e->getMessage(),
						'context' => [
							'max_attempts' => $maxAttempts,
						],
					] );
				} catch ( \Throwable $deadLetterError ) {
					error_log( 'Stripe marketing dead-letter insert failed: ' . $deadLetterError->getMessage() );
				}
			}

			error_log( 'Stripe product.updated queued import failed: ' . $e->getMessage() );
			do_action( 'khm_stripe_product_updated_import_failed', $productId, $levelId, $e );

			return [
				'status' => 'dead_lettered',
				'attempt' => $attempt + 1,
				'max_attempts' => $maxAttempts,
				'message' => $e->getMessage(),
			];
		}
	}

	private function scheduleRetry( string $productId, int $levelId, int $attempt, int $delay ): void {
		$hook = 'khm_import_stripe_marketing_product_updated';
		$args = [ $productId, $levelId, $attempt ];
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_single_event' ) ) {
			if ( ! wp_next_scheduled( $hook, $args ) ) {
				wp_schedule_single_event( time() + max( 1, $delay ), $hook, $args );
			}
		}
	}

	/**
	 * @return object
	 */
	private function resolveImporter() {
		$useMirror = function_exists( 'khm_use_stripe_level_mirror_importer' ) && khm_use_stripe_level_mirror_importer();
		if ( $useMirror && class_exists( StripeLevelMirrorImporter::class ) ) {
			return new StripeLevelMirrorImporter( new LevelRepository() );
		}

		return new StripeMarketingImporter( new LevelRepository() );
	}
}
