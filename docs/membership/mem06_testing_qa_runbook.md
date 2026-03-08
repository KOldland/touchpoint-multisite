# MEM-06 Testing and QA Runbook

## Unit test matrix

Run targeted MEM-06 unit/membership tests:

```bash
cd app/public/wp-content/plugins/khm-plugin
vendor/bin/phpunit tests/Membership/SignupInitMatrixTest.php
vendor/bin/phpunit tests/Membership/StripeWebhookHandlerTest.php
vendor/bin/phpunit tests/Membership/StripeWebhookFixtureIntegrationTest.php
vendor/bin/phpunit tests/Membership/AnonymizeTest.php
vendor/bin/phpunit tests/Membership/RetentionTest.php
vendor/bin/phpunit tests/Membership/RetentionWorkerChunkTest.php
vendor/bin/phpunit tests/Membership/DsarTest.php
vendor/bin/phpunit tests/Membership/DsarExportPrivacyTest.php
vendor/bin/phpunit tests/Membership/AdminPermissionTest.php
```

## Signed fixture integration

```bash
cd app/public/wp-content/plugins/khm-plugin
php tests/helpers/stripe_signature.php --secret="$KH_STRIPE_WEBHOOK_SECRET" --payload=tests/fixtures/golden/checkout_session_completed.json
php tests/helpers/stripe_signature.php --secret="$KH_STRIPE_WEBHOOK_SECRET" --payload=tests/fixtures/golden/invoice_paid.json
vendor/bin/phpunit tests/Membership/StripeWebhookFixtureIntegrationTest.php
```

## Staging E2E checklist

- Landing submit (`signup-init`) with consent true and UTM payload.
- Trigger signed `checkout.session.completed` fixture.
- Verify attribution row in DB + member detail UI.
- Export CSV and verify `consent=false` redaction.
- Execute anonymize dry-run and run; collect before/after DB snippets.
- Execute retention dry-run and run; collect timings and chunk size.

Recommended artifact files:

- `smoke-telemetry.json`
- `smoke-log.txt`
- `db_before_after.sql`
- `export_redaction.csv`
- admin screenshots (`.png`)

## Accessibility tests

```bash
cd app/public/wp-content/plugins/khm-plugin/tests/UI
npm install
npx playwright install
PLAYWRIGHT_BASE_URL=https://staging.example.com npx playwright test landing_success_a11y.spec.js admin_membership_a11y.spec.js --reporter=list,html
```

## Retention performance dry-run sample

```bash
time wp khm retention:run --dry-run --chunk-size=1000
```

For seeded local perf-like sampling:

```bash
php -r 'require "app/public/wp-content/plugins/khm-plugin/tests/helpers/retention_fixture.php";'
```
