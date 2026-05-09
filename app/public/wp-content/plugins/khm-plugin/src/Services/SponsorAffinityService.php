<?php

namespace KHM\Services;

/**
 * Calculates sponsor-specific affinity scores for buyers.
 *
 * Affinity is distinct from the 4A category intent score — it measures how
 * much a specific buyer has engaged with a specific sponsor's brand within
 * the platform. A buyer must have at least one sponsor-tagged content read
 * (≥5 affinity points) to be surfaced to that sponsor at all.
 *
 * Affinity tiers apply a CPL uplift on top of the base tier price:
 *   Base Affinity   (5–19pts):  no uplift
 *   Brand Recognition (20–44pts): +15%
 *   Brand Engagement  (45–74pts): +30%
 *   Brand Interest    (75+pts):   +50%
 */
class SponsorAffinityService {

	// --- Signal point values ---

	/** Points for first sponsor-tagged article read (eligibility threshold). */
	private const ARTICLE_FIRST        = 5;

	/** Points per additional article read (2nd–9th). */
	private const ARTICLE_PER_EXTRA    = 3;

	/** Bonus points for reading 10 or more articles. */
	private const ARTICLE_VOLUME_BONUS = 15;

	/** Points for first Connect profile view. */
	private const PROFILE_VIEW_FIRST   = 10;

	/** Additional points when Connect profile viewed 3+ times. */
	private const PROFILE_VIEW_REPEAT  = 15;

	/** Points for click-through to sponsor website. */
	private const WEBSITE_CLICK        = 15;

	/** Points for saving/bookmarking sponsor content (explicit intent). */
	private const BOOKMARK             = 25;

	/** Points for explicit opt-in or contact form with sponsor. */
	private const EXPLICIT_OPTIN       = 50;

	// --- Affinity tier thresholds ---

	private const TIER_BASE_MIN        = 5;
	private const TIER_RECOGNITION_MIN = 20;
	private const TIER_ENGAGEMENT_MIN  = 45;
	private const TIER_INTEREST_MIN    = 75;

	// --- CPL uplift multipliers ---

	private const UPLIFT_BASE        = 1.00;
	private const UPLIFT_RECOGNITION = 1.15;
	private const UPLIFT_ENGAGEMENT  = 1.30;
	private const UPLIFT_INTEREST    = 1.50;

	/**
	 * Calculate affinity score from a set of sponsor interaction signals.
	 *
	 * @param array{
	 *   articles_read?: int,
	 *   profile_views?: int,
	 *   website_clicks?: int,
	 *   bookmarks?: int,
	 *   explicit_optins?: int
	 * } $signals Raw interaction counts for a buyer/sponsor pair.
	 *
	 * @return int Total affinity points.
	 */
	public function calculate_score( array $signals ): int {
		$articles      = max( 0, (int) ( $signals['articles_read']   ?? 0 ) );
		$profile_views = max( 0, (int) ( $signals['profile_views']   ?? 0 ) );
		$web_clicks    = max( 0, (int) ( $signals['website_clicks']  ?? 0 ) );
		$bookmarks     = max( 0, (int) ( $signals['bookmarks']       ?? 0 ) );
		$optins        = max( 0, (int) ( $signals['explicit_optins'] ?? 0 ) );

		$score = 0;

		// Article reads
		if ( $articles >= 1 ) {
			$score += self::ARTICLE_FIRST;
			$extra  = min( $articles - 1, 8 ); // 2nd–9th articles
			$score += $extra * self::ARTICLE_PER_EXTRA;
			if ( $articles >= 10 ) {
				$score += self::ARTICLE_VOLUME_BONUS;
			}
		}

		// Connect profile views
		if ( $profile_views >= 1 ) {
			$score += self::PROFILE_VIEW_FIRST;
			if ( $profile_views >= 3 ) {
				$score += self::PROFILE_VIEW_REPEAT;
			}
		}

		// Website click-throughs
		if ( $web_clicks >= 1 ) {
			$score += self::WEBSITE_CLICK;
		}

		// Bookmarks / saves (higher intent than a click)
		if ( $bookmarks >= 1 ) {
			$score += self::BOOKMARK;
		}

		// Explicit opt-in / contact form
		if ( $optins >= 1 ) {
			$score += self::EXPLICIT_OPTIN;
		}

		return $score;
	}

	/**
	 * Determine affinity tier from a points score.
	 *
	 * Returns null when below eligibility threshold (buyer should not be surfaced).
	 *
	 * @param int $score Affinity points.
	 * @return array{tier: string, label: string, uplift: float}|null
	 */
	public function resolve_tier( int $score ): ?array {
		if ( $score < self::TIER_BASE_MIN ) {
			return null; // below eligibility threshold
		}

		if ( $score >= self::TIER_INTEREST_MIN ) {
			return array(
				'tier'   => 'brand_interest',
				'label'  => 'Brand Interest',
				'uplift' => self::UPLIFT_INTEREST,
			);
		}

		if ( $score >= self::TIER_ENGAGEMENT_MIN ) {
			return array(
				'tier'   => 'brand_engagement',
				'label'  => 'Brand Engagement',
				'uplift' => self::UPLIFT_ENGAGEMENT,
			);
		}

		if ( $score >= self::TIER_RECOGNITION_MIN ) {
			return array(
				'tier'   => 'brand_recognition',
				'label'  => 'Brand Recognition',
				'uplift' => self::UPLIFT_RECOGNITION,
			);
		}

		return array(
			'tier'   => 'base_affinity',
			'label'  => 'Base Affinity',
			'uplift' => self::UPLIFT_BASE,
		);
	}

	/**
	 * Apply affinity uplift to a base CPL price in pence.
	 *
	 * @param int   $base_price_cents Base unit price in pence (e.g. 37500 for £375).
	 * @param float $uplift           Uplift multiplier from resolve_tier().
	 * @return int  Adjusted price in pence, rounded to nearest whole penny.
	 */
	public function apply_uplift( int $base_price_cents, float $uplift ): int {
		return (int) round( $base_price_cents * $uplift );
	}

	/**
	 * Full pipeline: signals → score → tier → adjusted price.
	 *
	 * @param array<string,int> $signals        Raw interaction counts.
	 * @param int               $base_price_cents Base CPL in pence.
	 * @return array{
	 *   affinity_score: int,
	 *   affinity_tier: string|null,
	 *   affinity_label: string|null,
	 *   uplift: float,
	 *   adjusted_price_cents: int,
	 *   eligible: bool
	 * }
	 */
	public function evaluate( array $signals, int $base_price_cents ): array {
		$score = $this->calculate_score( $signals );
		$tier  = $this->resolve_tier( $score );

		if ( $tier === null ) {
			return array(
				'affinity_score'       => $score,
				'affinity_tier'        => null,
				'affinity_label'       => null,
				'uplift'               => 0.0,
				'adjusted_price_cents' => $base_price_cents,
				'eligible'             => false,
			);
		}

		return array(
			'affinity_score'       => $score,
			'affinity_tier'        => $tier['tier'],
			'affinity_label'       => $tier['label'],
			'uplift'               => $tier['uplift'],
			'adjusted_price_cents' => $this->apply_uplift( $base_price_cents, $tier['uplift'] ),
			'eligible'             => true,
		);
	}
}
