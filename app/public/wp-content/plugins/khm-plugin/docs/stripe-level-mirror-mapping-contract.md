# Stripe Level Mirror Mapping Contract

This document defines the current Stripe -> WordPress level mapping used by `KHM\Services\StripeLevelMirrorMapping` and consumed by `KHM\Services\StripeLevelMirrorImporter` (scaffold).

## Level Table Mapping

| Stripe source | WP destination |
| --- | --- |
| `product.name` | `khm_membership_levels.name` |
| `product.description` | `khm_membership_levels.description` |
| `primary_price.unit_amount` | `khm_membership_levels.initial_payment` |
| `primary_price.unit_amount` (recurring only) | `khm_membership_levels.billing_amount` |
| `primary_price.recurring.interval_count` | `khm_membership_levels.cycle_number` |
| `primary_price.recurring.interval` | `khm_membership_levels.cycle_period` (`Day/Week/Month/Year`) |
| `product.metadata.trial_days` | `khm_membership_levels.trial_limit` |

## khm_level_meta Mapping

| Stripe source | WP destination |
| --- | --- |
| `product.id` | `khm_level_meta.stripe_product_id` |
| all product prices | `khm_level_meta.stripe_price_ids[currency][interval]` |
| `product.marketing_features[].name` fallback `product.description` fallback `product.metadata.marketing_feature_list` | `khm_level_meta.presentation.marketing_features[]` |
| `product.metadata.presentation_template` | `khm_level_meta.presentation.template` |
| `product.metadata.presentation_cta_text` | `khm_level_meta.presentation.cta_text` |
| `product.metadata.price_inclusive` | `khm_level_meta.presentation.price_inclusive` |
| `product.metadata.trial_days` | `khm_level_meta.commerce.trial_days` |
| primary recurring interval | `khm_level_meta.commerce.default_billing_interval` |
| `product.metadata.allow_promotion_codes` | `khm_level_meta.commerce.allow_promotion_codes` |
| `product.metadata.allow_guest_checkout` | `khm_level_meta.commerce.allow_guest_checkout` |
| `product.metadata.feature_credits` | `khm_level_meta.features.credits` |
| `product.metadata.feature_gifting` | `khm_level_meta.features.gifting` |
| `product.metadata.feature_portal` | `khm_level_meta.features.portal` |
| `product.metadata.feature_sponsor` | `khm_level_meta.features.sponsor` |
| `product.metadata.feature_forum` | `khm_level_meta.features.forum` |
| `product.metadata.feature_founder_badge` | `khm_level_meta.features.founder_badge` |
| `product.metadata.credits_monthly` | `khm_level_meta.credits.monthly` |
| `product.metadata.credits_rollover` | `khm_level_meta.credits.rollover` |

## Resolution Priority

1. Explicit level id argument.
2. Existing level mapped by `khm_level_meta.stripe_product_id`.
3. `product.metadata.wp_level_id`.
4. Existing level mapped by any associated Stripe price ID.
5. Create new level (except in dry-run mode).

## Current Status

- Mapping contract class and scaffold importer are implemented.
- Existing `StripeMarketingImporter` remains the active production importer.
- Next phase is wiring `StripeLevelMirrorImporter` into CLI/admin/webhook behind a feature flag and adding tests.

