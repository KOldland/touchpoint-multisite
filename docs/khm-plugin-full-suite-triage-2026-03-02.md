# KHM Plugin Full-Suite CI Triage

Date: 2026-03-02  
Command:

```bash
vendor/bin/phpunit -c app/public/wp-content/plugins/khm-plugin/phpunit.xml
```

Raw output artifact:
- `logs/khm-plugin-phpunit-2026-03-02.txt`

## Summary
- Total: 75 tests, 175 assertions
- Result: 20 errors, 8 failures
- Scope status for current notifications/UX changes:
  - `tests/Membership/SignupEndpointTest.php` and `tests/Membership/StripeWebhookHandlerTest.php` are passing in targeted runs.

## Tracked Failure List (Owner + ETA)

1. `tests/GEO/SuggestionServiceTest.php` (6 errors)
- Symptom: `Patchwork\Exceptions\DefinedTooEarly` for `wp_json_encode`.
- Likely cause: test bootstrap order conflicts with BrainMonkey/Patchwork function redefinition.
- Owner: Platform Test Harness
- ETA target: 2026-03-04
- Tracking key: `TRIAGE-2026-03-02-01`

2. `tests/Services/StripeMarketingImporterReliabilityTest.php` and `tests/Services/StripeMarketingImporterTest.php` (2 errors)
- Symptom: `RuntimeException: WP level not found for id 22`.
- Likely cause: fixture/setup mismatch for expected WP membership level seeding.
- Owner: Stripe Marketing Import
- ETA target: 2026-03-04
- Tracking key: `TRIAGE-2026-03-02-02`

3. `tests/CLI/StripeMarketingDeadLettersReplayCommandTest.php` (1 error)
- Symptom: `Access to undeclared static property WP_CLI::$warnings`.
- Likely cause: WP_CLI mock/stub contract drift.
- Owner: CLI Tooling
- ETA target: 2026-03-03
- Tracking key: `TRIAGE-2026-03-02-03`

4. `tests/Admin/StripePriceIdTest.php` (2 errors)
- Symptoms:
  - `Class "WP_List_Table" not found`
  - mock return type mismatch for `MembershipLevel`
- Likely cause: missing admin test bootstrap includes and stale mock typing.
- Owner: Admin/Membership Levels
- ETA target: 2026-03-05
- Tracking key: `TRIAGE-2026-03-02-04`

5. `tests/Membership/AttributionEndpointTest.php` (5 errors)
- Symptom: `Array to string conversion` (from test bootstrap path).
- Likely cause: request/body or DB layer expectations diverged from endpoint handling.
- Owner: Membership API
- ETA target: 2026-03-04
- Tracking key: `TRIAGE-2026-03-02-05`

6. `tests/Membership/StatusEndpointTest.php` (4 errors, 1 failure)
- Symptoms:
  - `Class "WP_Mock" not found`
  - schema assertion on non-array response
  - expected status `active`, got `none`
- Likely cause: mixed mocking frameworks and stale fixtures.
- Owner: Membership API + Test Harness
- ETA target: 2026-03-05
- Tracking key: `TRIAGE-2026-03-02-06`

7. `tests/Sponsors/SponsorSchemaGateTest.php` (1 failure)
- Symptom: approval/justification gate assertion fails.
- Likely cause: schema gate behavior changed relative to test expectation.
- Owner: Sponsors Domain
- ETA target: 2026-03-04
- Tracking key: `TRIAGE-2026-03-02-07`

8. `tests/Services/StripeMarketingWebhookImportProcessorTest.php` (3 failures)
- Symptom: expected `retry_scheduled`/`dead_lettered`, got `invalid`.
- Likely cause: import processor state enum/validation changed.
- Owner: Stripe Marketing Webhooks
- ETA target: 2026-03-03
- Tracking key: `TRIAGE-2026-03-02-08`

9. `tests/Rest/CheckoutStripePriceTest.php` (3 failures)
- Symptoms:
  - `LevelRepository::getMeta` call count mismatches
  - expected option fallback but returns filter value
- Likely cause: resolver precedence/caching changed vs test assumptions.
- Owner: Checkout/Payments API
- ETA target: 2026-03-04
- Tracking key: `TRIAGE-2026-03-02-09`

## Release Impact Assessment
- These failures are pre-existing baseline instability outside the notifications/landing UX slice.
- For PM sign-off condition, this file serves as explicit triage tracking with ownership and ETA.
- Next step: create issue tickets from the tracking keys above and link ticket URLs back into this document.
