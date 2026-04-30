<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

/**
 * ConnectTiering
 *
 * Manages the mapping of buyer-journey stages to commercial tiers, and provides
 * the pricing snapshot (model, unit price, commission eligibility) used when
 * creating or enriching Connect opportunities.
 *
 * Tier defaults are stored as a single WordPress option (`khm_connect_pricing`)
 * so they can be edited via the Connect Pricing admin panel without a deployment.
 */
class ConnectTiering {

	/** Option key used to persist tier pricing config. */
	const OPTION_KEY = 'khm_connect_pricing';

	/**
	 * Canonical tiers in descending commercial value order.
	 *
	 * @var string[]
	 */
	const TIERS = array( 'premium', 'standard', 'exploratory' );

	/**
	 * Mapping of internal buyer-journey stage slugs to a commercial tier.
	 * Stages not listed here fall back to 'exploratory'.
	 *
	 * @var array<string,string>
	 */
	const STAGE_TIER_MAP = array(
		'solution'  => 'premium',
		'diagnosis' => 'standard',
		'attention' => 'exploratory',
	);

	/**
	 * Built-in baseline defaults used when no saved config exists.
	 * unit_price_cents is in fractional dollars (cents).
	 *
	 * @return array<string,array{pricing_model:string,unit_price_cents:int,commission_eligible:int,engaged_acv_cents:int,engaged_commission_rate:float}>
	 */
	public static function baseline_defaults(): array {
		return array(
			'premium'     => array(
				'pricing_model'         => 'cpl',
				'unit_price_cents'      => 15000,  // $150.00
				'commission_eligible'   => 1,
				'engaged_acv_cents'     => 1200000, // $12,000
				'engaged_commission_rate' => 0.10,  // 10 %
			),
			'standard'    => array(
				'pricing_model'         => 'cpl',
				'unit_price_cents'      => 7500,   // $75.00
				'commission_eligible'   => 1,
				'engaged_acv_cents'     => 600000,  // $6,000
				'engaged_commission_rate' => 0.08,
			),
			'exploratory' => array(
				'pricing_model'         => 'cpl',
				'unit_price_cents'      => 2500,   // $25.00
				'commission_eligible'   => 0,
				'engaged_acv_cents'     => 0,
				'engaged_commission_rate' => 0.0,
			),
		);
	}

	/**
	 * Load tier pricing config from the database, merging over baseline defaults.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_config(): array {
		$saved    = get_option( self::OPTION_KEY, array() );
		$defaults = self::baseline_defaults();

		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return $defaults;
		}

		$config = array();
		foreach ( self::TIERS as $tier ) {
			$config[ $tier ] = array_merge(
				$defaults[ $tier ],
				is_array( $saved[ $tier ] ?? null ) ? $saved[ $tier ] : array()
			);
		}

		return $config;
	}

	/**
	 * Save tier pricing config to the database.
	 *
	 * @param array<string,array<string,mixed>> $config
	 */
	public static function save_config( array $config ): void {
		$sanitized = array();

		foreach ( self::TIERS as $tier ) {
			$row = is_array( $config[ $tier ] ?? null ) ? $config[ $tier ] : array();

			$model = sanitize_key( (string) ( $row['pricing_model'] ?? 'cpl' ) );
			$sanitized[ $tier ] = array(
				'pricing_model'           => '' !== $model ? $model : 'cpl',
				'unit_price_cents'        => max( 0, (int) ( $row['unit_price_cents'] ?? 0 ) ),
				'commission_eligible'     => empty( $row['commission_eligible'] ) ? 0 : 1,
				'engaged_acv_cents'       => max( 0, (int) ( $row['engaged_acv_cents'] ?? 0 ) ),
				'engaged_commission_rate' => min( 1.0, max( 0.0, (float) ( $row['engaged_commission_rate'] ?? 0.0 ) ) ),
			);
		}

		update_option( self::OPTION_KEY, $sanitized, false );
	}

	/**
	 * Map an internal buyer-journey stage slug to a commercial tier slug.
	 *
	 * @param string $stage e.g. 'solution', 'diagnosis', 'attention'
	 * @return string One of 'premium', 'standard', 'exploratory'
	 */
	public static function map_stage_to_tier( string $stage ): string {
		$stage = sanitize_key( $stage );

		return self::STAGE_TIER_MAP[ $stage ] ?? 'exploratory';
	}

	/**
	 * Return a pricing snapshot array for the given tier.
	 *
	 * This is the canonical source of pricing data used when creating a
	 * Connect opportunity from a scoring signal.
	 *
	 * @param string $tier
	 * @return array{tier:string,pricing_model:string,unit_price_cents:int,commission_eligible:int}
	 */
	public static function pricing_snapshot( string $tier ): array {
		$tier       = sanitize_key( $tier );
		$valid_tier = in_array( $tier, self::TIERS, true ) ? $tier : 'exploratory';
		$config     = self::get_config();
		$row        = $config[ $valid_tier ];

		return array(
			'tier'               => $valid_tier,
			'pricing_model'      => (string) ( $row['pricing_model'] ?? 'cpl' ),
			'unit_price_cents'   => (int) ( $row['unit_price_cents'] ?? 0 ),
			'commission_eligible'=> (int) ( $row['commission_eligible'] ?? 0 ),
		);
	}

	/**
	 * Return engaged-tier defaults (ACV and commission rate) for the given tier.
	 *
	 * @param string $tier
	 * @return array{engaged_acv_cents:int,engaged_commission_rate:float}
	 */
	public static function engaged_defaults( string $tier ): array {
		$tier       = sanitize_key( $tier );
		$valid_tier = in_array( $tier, self::TIERS, true ) ? $tier : 'exploratory';
		$config     = self::get_config();
		$row        = $config[ $valid_tier ];

		return array(
			'engaged_acv_cents'       => (int) ( $row['engaged_acv_cents'] ?? 0 ),
			'engaged_commission_rate' => (float) ( $row['engaged_commission_rate'] ?? 0.0 ),
		);
	}
}
