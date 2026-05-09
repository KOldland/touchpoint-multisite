<?php
/**
 * Readership Tier Config
 *
 * Bridges the stable level_id → tier_slug → price_id chain so that
 * MembershipCheckoutHandler::resolve_stripe_price_id() can resolve prices
 * for readership subscription tiers through TierRegistry.
 *
 * Register via:
 *   new \KHM\Membership\ReadershiptTierConfig();
 *
 * @package KHM\Membership
 */

namespace KHM\Membership;

class ReadershiptTierConfig {

	public function __construct() {
		add_filter( 'khm_stripe_membership_price_map', [ $this, 'resolve_price' ], 10, 2 );
	}

	/**
	 * Resolve a Stripe price ID for readership tier level IDs.
	 *
	 * @param mixed $result  Current resolved value (null if not yet resolved).
	 * @param int   $level_id Membership level ID from the checkout request.
	 * @return string|null Stripe price ID, or passes through $result unchanged.
	 */
	public function resolve_price( $result, int $level_id ) {
		// Only act if nothing has resolved yet.
		if ( null !== $result ) {
			return $result;
		}

		$level_map = get_option( 'khm_readership_level_id_map', [] );
		if ( ! is_array( $level_map ) || ! isset( $level_map[ $level_id ] ) ) {
			return null;
		}

		$tier_slug = (string) $level_map[ $level_id ];
		$tier      = TierRegistry::get_tier( $tier_slug );
		if ( ! $tier || empty( $tier['price_id'] ) ) {
			return null;
		}

		return $tier['price_id'];
	}
}
