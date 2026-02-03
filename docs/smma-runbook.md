# SMMA Runbook

## Prereqs
- PHP 8.1+
- Composer 2+
- WordPress admin access (edit_posts)

## Feature Flags
- Enable SMMA:
  - Admin UI: KH Social → Feature Flags → Enable SMMA
  - Or option: kh_smma_feature_flags = {"smma": true, "smma_paid_adapters": false}

## Sponsor Setup
- Create Sponsors under Ad Units → Sponsors
- Link ad-campaign terms to Sponsor Record ID (ad-campaign edit screen)

## GEO Sponsor Mapping
- Optional per-post override: post meta _khm_geo_sponsor_map
- Optional global map: option khm_geo_sponsor_map
- REST lookup: /wp-json/khm-seo/v1/geo-sponsor?post_id=123&geo=GB

## SMMA Generate & Schedule
- Generate variants: POST /wp-json/kh-smma/v1/generate
- Schedule: POST /wp-json/kh-smma/v1/schedule
- Approval: POST admin-post.php?action=kh_smma_approve_schedule

## Manual Export Fallback
- If paid adapters disabled, schedules move to awaiting_manual_export with _kh_smma_export_bundle

## CI / Local Commands
- Install tooling: composer install --no-interaction --no-progress
- Run tests: vendor/bin/phpunit -c phpunit.xml

## Smoke Checks
- Boost Visibility page shows Promotion Planner and Pending Approvals
- Create schedule → status transitions: pending → processing → awaiting_manual_export

## Troubleshooting
- Missing phpunit: ensure composer install completed
- REST nonces: send X-WP-Nonce header for SMMA endpoints
- Paid adapters: enable smma_paid_adapters flag and ensure tokens are stored
