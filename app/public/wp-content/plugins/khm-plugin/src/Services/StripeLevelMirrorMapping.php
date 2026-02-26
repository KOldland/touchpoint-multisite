<?php

namespace KHM\Services;

class StripeLevelMirrorMapping {

	/**
	 * Source-of-truth mapping contract for Stripe -> KHM level fields.
	 *
	 * @return array<string,mixed>
	 */
	public static function contract(): array {
		return [
			'level' => [
				'name' => 'product.name',
				'description' => 'product.description',
				'initial_payment' => 'primary_price.unit_amount',
				'billing_amount' => 'primary_price.unit_amount (recurring only)',
				'cycle_number' => 'primary_price.recurring.interval_count',
				'cycle_period' => 'primary_price.recurring.interval',
				'trial_limit' => 'product.metadata.trial_days',
			],
			'meta' => [
				'stripe_product_id' => 'product.id',
				'stripe_price_ids' => 'prices grouped by currency + interval',
				'presentation.marketing_features' => 'product.marketing_features[] -> description -> metadata.marketing_feature_list',
				'presentation.template' => 'product.metadata.presentation_template',
				'presentation.cta_text' => 'product.metadata.presentation_cta_text',
				'presentation.price_inclusive' => 'product.metadata.price_inclusive',
				'commerce.trial_days' => 'product.metadata.trial_days',
				'commerce.default_billing_interval' => 'primary recurring interval',
				'commerce.allow_promotion_codes' => 'product.metadata.allow_promotion_codes',
				'commerce.allow_guest_checkout' => 'product.metadata.allow_guest_checkout',
				'features.credits' => 'product.metadata.feature_credits',
				'features.gifting' => 'product.metadata.feature_gifting',
				'features.portal' => 'product.metadata.feature_portal',
				'features.sponsor' => 'product.metadata.feature_sponsor',
				'features.forum' => 'product.metadata.feature_forum',
				'features.founder_badge' => 'product.metadata.feature_founder_badge',
				'credits.monthly' => 'product.metadata.credits_monthly',
				'credits.rollover' => 'product.metadata.credits_rollover',
			],
		];
	}

	/**
	 * Build normalized payloads for LevelRepository::create/update.
	 *
	 * @param object            $product
	 * @param array<int,object> $prices
	 * @param array<string,mixed> $existingMeta
	 * @return array{level_payload:array<string,mixed>,meta_payload:array<string,mixed>,resolved:array<string,mixed>}
	 */
	public static function mapToLevelPayload( object $product, array $prices, array $existingMeta = [] ): array {
		$meta = is_array( $existingMeta ) ? $existingMeta : [];

		if ( ! isset( $meta['features'] ) || ! is_array( $meta['features'] ) ) {
			$meta['features'] = [];
		}
		if ( ! isset( $meta['commerce'] ) || ! is_array( $meta['commerce'] ) ) {
			$meta['commerce'] = [];
		}
		if ( ! isset( $meta['presentation'] ) || ! is_array( $meta['presentation'] ) ) {
			$meta['presentation'] = [];
		}
		if ( ! isset( $meta['credits'] ) || ! is_array( $meta['credits'] ) ) {
			$meta['credits'] = [];
		}

		$primary = self::selectPrimaryPrice( $prices );
		$primaryAmount = self::priceAmount( $primary );
		$recurring = self::isRecurring( $primary );
		$interval = self::priceInterval( $primary );
		$intervalCount = self::priceIntervalCount( $primary );

		$meta['stripe_product_id'] = isset( $product->id ) ? (string) $product->id : '';
		$meta['stripe_price_ids'] = self::buildPriceMap( $prices );

		$meta['presentation']['marketing_features'] = self::extractMarketingFeatures( $product );
		$meta['presentation']['template'] = self::metadataString( $product, 'presentation_template', (string) ( $meta['presentation']['template'] ?? 'full' ) );
		$meta['presentation']['cta_text'] = self::metadataString( $product, 'presentation_cta_text', (string) ( $meta['presentation']['cta_text'] ?? '' ) );
		$meta['presentation']['price_inclusive'] = self::metadataBool( $product, 'price_inclusive', (bool) ( $meta['presentation']['price_inclusive'] ?? true ) );

		$meta['commerce']['trial_days'] = self::metadataInt( $product, 'trial_days', (int) ( $meta['commerce']['trial_days'] ?? 0 ) );
		$meta['commerce']['default_billing_interval'] = $interval !== '' ? self::normalizeIntervalKey( $interval ) : (string) ( $meta['commerce']['default_billing_interval'] ?? 'monthly' );
		$meta['commerce']['allow_promotion_codes'] = self::metadataBool( $product, 'allow_promotion_codes', (bool) ( $meta['commerce']['allow_promotion_codes'] ?? true ) );
		$meta['commerce']['allow_guest_checkout'] = self::metadataBool( $product, 'allow_guest_checkout', (bool) ( $meta['commerce']['allow_guest_checkout'] ?? false ) );

		$featureKeys = [ 'credits', 'gifting', 'portal', 'sponsor', 'forum', 'founder_badge' ];
		foreach ( $featureKeys as $key ) {
			$meta['features'][ $key ] = self::metadataBool( $product, 'feature_' . $key, (bool) ( $meta['features'][ $key ] ?? false ) );
		}

		$meta['credits']['monthly'] = self::metadataInt( $product, 'credits_monthly', (int) ( $meta['credits']['monthly'] ?? 0 ) );
		$meta['credits']['rollover'] = self::metadataBool( $product, 'credits_rollover', (bool) ( $meta['credits']['rollover'] ?? false ) );

		$levelPayload = [
			'name' => sanitize_text_field( trim( (string) ( $product->name ?? '' ) ) ),
			'description' => wp_kses_post( (string) ( $product->description ?? '' ) ),
			'allow_signups' => 1,
			'trial_limit' => (int) $meta['commerce']['trial_days'],
		];

		if ( $primaryAmount !== null ) {
			$levelPayload['initial_payment'] = $primaryAmount;
			$levelPayload['billing_amount'] = $recurring ? $primaryAmount : 0.0;
		}
		if ( $recurring ) {
			$levelPayload['cycle_number'] = max( 1, $intervalCount );
			$levelPayload['cycle_period'] = self::mapStripePeriodToLevelPeriod( $interval );
		}

		return [
			'level_payload' => $levelPayload,
			'meta_payload' => $meta,
			'resolved' => [
				'primary_price_id' => isset( $primary->id ) ? (string) $primary->id : '',
				'primary_price_interval' => $interval,
				'primary_price_amount' => $primaryAmount,
				'primary_price_recurring' => $recurring,
			],
		];
	}

	/**
	 * @param array<int,object> $prices
	 * @return array<string,array<string,string>>
	 */
	private static function buildPriceMap( array $prices ): array {
		$map = [];
		foreach ( $prices as $price ) {
			$id = isset( $price->id ) ? (string) $price->id : '';
			$currency = isset( $price->currency ) ? strtoupper( (string) $price->currency ) : '';
			if ( $id === '' || $currency === '' ) {
				continue;
			}
			$interval = self::priceInterval( $price );
			if ( $interval === '' ) {
				continue;
			}
			$map[ $currency ][ self::normalizeIntervalKey( $interval ) ] = $id;
		}
		return $map;
	}

	/**
	 * @param array<int,object> $prices
	 * @return object|null
	 */
	private static function selectPrimaryPrice( array $prices ): ?object {
		$monthly = null;
		$annual = null;
		$firstRecurring = null;
		$firstOneTime = null;
		foreach ( $prices as $price ) {
			if ( self::isRecurring( $price ) ) {
				$interval = self::priceInterval( $price );
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

	/**
	 * @return array<int,string>
	 */
	private static function extractMarketingFeatures( object $product ): array {
		$lines = [];
		$marketingFeatures = $product->marketing_features ?? null;
		if ( is_array( $marketingFeatures ) ) {
			foreach ( $marketingFeatures as $feature ) {
				if ( is_object( $feature ) && isset( $feature->name ) ) {
					$candidate = sanitize_text_field( (string) $feature->name );
					if ( $candidate !== '' ) {
						$lines[] = $candidate;
					}
				} elseif ( is_array( $feature ) && isset( $feature['name'] ) ) {
					$candidate = sanitize_text_field( (string) $feature['name'] );
					if ( $candidate !== '' ) {
						$lines[] = $candidate;
					}
				}
			}
		}

		if ( ! empty( $lines ) ) {
			return array_slice( array_values( array_unique( $lines ) ), 0, 50 );
		}

		$text = trim( (string) ( $product->description ?? '' ) );
		if ( $text === '' ) {
			$text = self::metadataString( $product, 'marketing_feature_list', '' );
		}
		if ( $text === '' ) {
			return [];
		}

		$raw = preg_split( '/\r\n|\r|\n/', strip_tags( $text ) ) ?: [];
		foreach ( $raw as $row ) {
			$row = trim( (string) $row );
			if ( $row === '' ) {
				continue;
			}
			if ( strpos( $row, ',' ) !== false && strlen( $row ) < 400 ) {
				foreach ( explode( ',', $row ) as $part ) {
					$part = sanitize_text_field( trim( $part ) );
					if ( $part !== '' ) {
						$lines[] = mb_substr( $part, 0, 500 );
					}
				}
			} else {
				$lines[] = mb_substr( sanitize_text_field( $row ), 0, 500 );
			}
		}

		return array_slice( array_values( array_unique( $lines ) ), 0, 50 );
	}

	private static function isRecurring( ?object $price ): bool {
		return is_object( $price ) && isset( $price->recurring ) && is_object( $price->recurring );
	}

	private static function priceInterval( ?object $price ): string {
		if ( ! self::isRecurring( $price ) ) {
			return '';
		}
		return strtolower( trim( (string) ( $price->recurring->interval ?? '' ) ) );
	}

	private static function priceIntervalCount( ?object $price ): int {
		if ( ! self::isRecurring( $price ) ) {
			return 1;
		}
		return max( 1, (int) ( $price->recurring->interval_count ?? 1 ) );
	}

	private static function priceAmount( ?object $price ): ?float {
		if ( ! is_object( $price ) || ! isset( $price->unit_amount ) || ! is_numeric( $price->unit_amount ) ) {
			return null;
		}
		return round( ( (float) $price->unit_amount ) / 100, 2 );
	}

	private static function metadataString( object $product, string $key, string $default = '' ): string {
		$value = self::metadataValue( $product, $key );
		if ( is_scalar( $value ) ) {
			$clean = trim( (string) $value );
			return $clean !== '' ? $clean : $default;
		}
		return $default;
	}

	private static function metadataBool( object $product, string $key, bool $default = false ): bool {
		$value = self::metadataValue( $product, $key );
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_scalar( $value ) ) {
			$normalized = strtolower( trim( (string) $value ) );
			if ( in_array( $normalized, [ '1', 'true', 'yes', 'y', 'on' ], true ) ) {
				return true;
			}
			if ( in_array( $normalized, [ '0', 'false', 'no', 'n', 'off' ], true ) ) {
				return false;
			}
		}
		return $default;
	}

	private static function metadataInt( object $product, string $key, int $default = 0 ): int {
		$value = self::metadataValue( $product, $key );
		if ( is_numeric( $value ) ) {
			return max( 0, (int) $value );
		}
		return max( 0, $default );
	}

	/**
	 * @return mixed
	 */
	private static function metadataValue( object $product, string $key ) {
		$metadata = $product->metadata ?? null;
		if ( is_array( $metadata ) && array_key_exists( $key, $metadata ) ) {
			return $metadata[ $key ];
		}
		if ( is_object( $metadata ) && isset( $metadata->{$key} ) ) {
			return $metadata->{$key};
		}
		return null;
	}

	private static function normalizeIntervalKey( string $interval ): string {
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

	private static function mapStripePeriodToLevelPeriod( string $period ): string {
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
}

