<?php
/**
 * Seed Readership Subscription Tiers
 *
 * Seeds the four canonical readership subscription tier slugs into the
 * khm_membership_tier_registry option and records a stable level_id map so
 * MembershipCheckoutHandler can resolve price IDs from TierRegistry.
 *
 * Tier slugs:
 *   readership_single_monthly   — single-site, billed monthly
 *   readership_single_annual    — single-site, billed annually
 *   readership_portfolio_monthly — full portfolio, billed monthly
 *   readership_portfolio_annual  — full portfolio, billed annually
 *
 * Price IDs must be configured after creation via the wp_options table or
 * the khm_membership_tier_registry filter. Placeholder values are set so the
 * registry entries are visible but price resolution will fail cleanly until
 * real Stripe price IDs are provided.
 *
 * Level ID map (khm_readership_level_id_map option):
 *   1 → readership_single_monthly
 *   2 → readership_single_annual
 *   3 → readership_portfolio_monthly
 *   4 → readership_portfolio_annual
 *
 * @package KHM\Migrations
 */

namespace KHM\Migrations;

class SeedReadershipTiers {

	/**
	 * Canonical level ID → tier slug map.
	 * These IDs are stable and must not be reassigned.
	 */
	public const LEVEL_MAP = [
		1 => 'readership_single_monthly',
		2 => 'readership_single_annual',
		3 => 'readership_portfolio_monthly',
		4 => 'readership_portfolio_annual',
	];

	/**
	 * Default tier definitions.
	 * price_id is intentionally empty; operators must configure real Stripe price IDs.
	 */
	private static function default_tiers(): array {
		return [
			'readership_single_monthly' => [
				'billing_interval' => 'month',
				'trial_days'       => 0,
				'trial_eligible'   => true,
				'credit_allowance' => 0,
				'price_id'         => '',
			],
			'readership_single_annual' => [
				'billing_interval' => 'year',
				'trial_days'       => 0,
				'trial_eligible'   => true,
				'credit_allowance' => 0,
				'price_id'         => '',
			],
			'readership_portfolio_monthly' => [
				'billing_interval' => 'month',
				'trial_days'       => 0,
				'trial_eligible'   => true,
				'credit_allowance' => 0,
				'price_id'         => '',
			],
			'readership_portfolio_annual' => [
				'billing_interval' => 'year',
				'trial_days'       => 0,
				'trial_eligible'   => true,
				'credit_allowance' => 0,
				'price_id'         => '',
			],
		];
	}

	/**
	 * Seed tier registry and level ID map.
	 * Safe to call on every activation — will not overwrite existing entries.
	 */
	public static function seed(): void {
		self::seed_tier_registry();
		self::seed_level_id_map();
	}

	/**
	 * Merge readership tier definitions into khm_membership_tier_registry.
	 * Existing entries are preserved; new keys are added.
	 */
	private static function seed_tier_registry(): void {
		$existing = get_option( 'khm_membership_tier_registry', [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		$changed = false;
		foreach ( self::default_tiers() as $slug => $definition ) {
			if ( ! isset( $existing[ $slug ] ) ) {
				$existing[ $slug ] = $definition;
				$changed           = true;
			}
		}

		if ( $changed ) {
			update_option( 'khm_membership_tier_registry', $existing, false );
			error_log( '[KHM] SeedReadershipTiers: tier registry updated with readership subscription tiers.' );
		}
	}

	/**
	 * Store the stable level_id → tier_slug map in khm_readership_level_id_map.
	 * This option is read by the khm_stripe_membership_price_map filter
	 * registered in ReadershiptTierConfig to route level IDs through TierRegistry.
	 */
	private static function seed_level_id_map(): void {
		$existing = get_option( 'khm_readership_level_id_map', null );
		if ( null === $existing ) {
			update_option( 'khm_readership_level_id_map', self::LEVEL_MAP, false );
			error_log( '[KHM] SeedReadershipTiers: level ID map created.' );
		}
	}
}
