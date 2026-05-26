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

## Card1 Generate Smoke
- `export KH_SMMA_TEST_MODE=ci`
- `export KH_SMMA_GOLDEN_FIXTURE=generate_awareness_ok.json`
- `cd app/public/wp-content/plugins/kh-smma && vendor/bin/phpunit tests/Card1ApiTest.php`
- `php scripts/verify_golden_fixtures.php`
- `curl -sS -X POST http://localhost/wp-json/kh-smma/v1/generate -H "Content-Type: application/json" -H "X-WP-Nonce: <nonce>" -H "Idempotency-Key: 11111111-1111-1111-1111-111111111111" -d '{"post_id":101,"blocks_summary":"Summary","num_variants":1,"geo_targets":["US"],"consent":true}'`

## Card1 DB Verification
- `wp db query "SHOW TABLES LIKE 'wp_smma_generate_requests'; SHOW TABLES LIKE 'wp_variants'; SHOW TABLES LIKE 'wp_variant_revisions'; SHOW TABLES LIKE 'wp_smma_schedules'; SHOW TABLES LIKE 'wp_smma_schedule_queue';"`
- `wp db query "SHOW INDEX FROM wp_variant_revisions; SHOW INDEX FROM wp_smma_schedules; SHOW INDEX FROM wp_smma_schedule_queue;"`
- `wp db query "SELECT request_id,post_id,status,created_at FROM wp_smma_generate_requests ORDER BY created_at DESC LIMIT 5;"`
- `wp db query "SELECT variant_id,approval_status,latest_revision_id,created_at FROM wp_variants ORDER BY created_at DESC LIMIT 5;"`
- `wp db query "SELECT revision_id,variant_id,compliance_status,created_at FROM wp_variant_revisions ORDER BY created_at DESC LIMIT 5;"`
- `wp db query "SELECT schedule_id,variant_id,sponsor_id,status,idempotency_key,created_at FROM wp_smma_schedules ORDER BY created_at DESC LIMIT 5;"`
- `wp db query "SELECT queue_id,schedule_id,status,attempt_count,last_error,created_at FROM wp_smma_schedule_queue ORDER BY created_at DESC LIMIT 5;"`
