# PAID-07 — CI Integration & Golden Fixture Governance

## Overview

PAID-07 adds end-to-end CI validation for all paid adapters (LinkedIn, Google, ManualExport, SFTP delivery, API delivery). Every push touching paid adapter source, fixtures, or contracts triggers the `integration:paid-adapters` workflow.

---

## CI Workflow

**File:** `.github/workflows/integration-paid-adapters.yml`

| Job | What it does |
|-----|-------------|
| `paid-tests` | PHPUnit PAID unit + integration + legacy tests + sandbox safety step (PHP 8.1 & 8.2) |
| `golden-fixtures` | Checksum verification, JSON validation, secret scan, golden check script |
| `reconciliation-tests` | PAID-08 unit (12) + integration (4) reconciliation run tests |
| `smoke-test` | End-to-end sandbox flow: dry_run → execute → reconcile → settle → deliver |
| `signoff-smoke` | Full 6-phase E2E signoff smoke with evidence JSON artifact |
| `report` | Step summary with pass/fail counts |

**Triggers:** pushes and PRs to `main`/`staging` that touch:
- `src/Adapters/**`, `src/Reconciliation/**`
- `tests/Paid/**`, `tests/integration/**`, `tests/smoke/**`
- `tests/fixtures/golden/**`
- `docs/contracts/paid_*.json`
- `scripts/verify_golden_fixtures.php`, `scripts/paid_adapter_golden_check.php`

---

## Running Locally

### Full PAID test suite (71 tests)

```bash
cd app/public/wp-content/plugins/kh-smma

# Unit + integration + legacy
vendor/bin/phpunit \
  tests/Paid/ tests/integration/ \
  tests/PaidAdaptersTest.php tests/ManualExportAdapterTest.php \
  --testdox

# Single test file
vendor/bin/phpunit tests/Paid/SettlementDeliveryTest.php --testdox
vendor/bin/phpunit tests/integration/SettlementAccountingIntegrationTest.php --testdox
```

### Golden fixture verification (checksums + metadata)

```bash
php scripts/verify_golden_fixtures.php
```

Expected: `Golden fixture verification passed.`

### Golden check (adapter output vs fixture diff)

```bash
php scripts/paid_adapter_golden_check.php
```

Checks each registered adapter against its stored fixture. Volatile fields (`timestamp`, `delivered_at`, `acked_at`) are stripped before comparison.

```bash
# Run a single fixture only
php scripts/paid_adapter_golden_check.php --fixture=google_sandbox_dry_run_response

# Update diverging fixtures (review diff first)
php scripts/paid_adapter_golden_check.php --write
```

Produces `golden-summary.json` in the repo root with per-fixture pass/fail results.

### Smoke test (end-to-end sandbox)

```bash
cd app/public/wp-content/plugins/kh-smma
php tests/smoke/paid_adapter_smoke.php
```

Runs the full sandbox flow: manifest generation → dry_run → execute → reconcile → settle → SFTP deliver + ACK → API deliver + ACK.

---

## Golden Fixture Governance

### Fixture inventory (as of PAID-07)

| Fixture | Adapter | Method | Prompt version |
|---------|---------|--------|----------------|
| `paid_adapter_dry_run_manifest.json` | (generic) | input | paid-01 |
| `paid_adapter_dry_run_response.json` | (generic) | dry_run | paid-01 |
| `paid_adapter_execute_response.json` | (generic) | execute | paid-01 |
| `google_sandbox_dry_run_manifest.json` | Google | input | paid-03 |
| `google_sandbox_execute_response.json` | Google | execute | paid-03 |
| `linkedin_sandbox_execute_response.json` | LinkedIn | execute | paid-03 |
| `sftp_delivery_execute_response.json` | SFTP | execute | paid-06 |
| `api_delivery_execute_response.json` | API | execute | paid-06 |
| `google_sandbox_dry_run_response.json` | Google | dry_run | paid-07 |
| `linkedin_sandbox_dry_run_response.json` | LinkedIn | dry_run | paid-07 |
| `manual_export_dry_run_response.json` | ManualExport | dry_run | paid-07 |
| `manual_export_execute_response.json` | ManualExport | execute | paid-07 |
| `sftp_delivery_dry_run_response.json` | SFTP | dry_run | paid-07 |

All fixtures live in `tests/fixtures/golden/` and must have a `.meta.json` sidecar.

### Meta sidecar structure

```json
{
  "version": "1.0.0",
  "prompt_hash": "<sha256-of-fixture-file>",
  "prompt_version": "paid-07",
  "created_at": "2026-03-04T00:00:00Z",
  "author": "@paid-adapter-team",
  "checksum": "<sha256-of-fixture-file>",
  "labels": ["golden-owner-approved"],
  "notes": "Human-readable notes about this fixture."
}
```

Rules:
- `checksum` **must** equal `sha256(fixture-file-bytes)` — verified by `verify_golden_fixtures.php`
- `author` must start with `@`
- `labels` must contain `"golden-owner-approved"` before merging
- No secrets (Stripe keys, SFTP passwords, Bearer tokens, AWS keys) in fixture body — scanned in CI

### Updating a fixture

**Never edit fixture JSON by hand.** Use the regeneration helper instead:

```bash
# 1. Understand what changed (adapter code or expected shape)
php scripts/paid_adapter_golden_check.php --fixture=google_sandbox_dry_run_response

# 2. Regenerate with governance flag
php scripts/regenerate_paid_fixture.php \
  --fixture=google_sandbox_dry_run_response \
  --confirmed

# 3. Review the diff
git diff tests/fixtures/golden/google_sandbox_dry_run_response.json

# 4. Retain 'golden-owner-approved' label in .meta.json, then commit
git add tests/fixtures/golden/google_sandbox_dry_run_response.{json,meta.json}
git commit -m "fix(paid-07): update google dry_run fixture after adapter change"
```

`regenerate_paid_fixture.php` will:
1. Run the adapter in sandbox mode with canonical inputs
2. Strip volatile fields (`timestamp`, `delivered_at`, `acked_at`)
3. Write the normalised fixture JSON
4. Compute SHA256 and update the `.meta.json` sidecar

### Adding a new fixture

1. Add the adapter call to `$registry` in `regenerate_paid_fixture.php`
2. Run `php scripts/regenerate_paid_fixture.php --fixture=<name> --confirmed`
3. Add the fixture name to the `$required` array in `scripts/verify_golden_fixtures.php`
4. Add it to the `fixtures` array in `docs/contracts/cic-01-golden-contract.json`
5. Add it to the `$checks` map in `scripts/paid_adapter_golden_check.php`
6. Commit all five changes together

---

## Volatile Field Normalisation

The golden check and regeneration scripts strip these fields before comparison, since they vary between runs:

| Field | Reason |
|-------|--------|
| `timestamp` | Current time at adapter execution |
| `delivered_at` | Delivery timestamp |
| `acked_at` | ACK timestamp |

If a fixture contains one of these fields at the top level or inside `operations[]`, it is removed during normalisation. This ensures deterministic fixture comparison even if the test clock drifts.

---

## Artefacts

The `golden-fixtures` CI job uploads `golden-summary.json` as an artefact (retained 30 days). This file contains per-fixture pass/fail status and any diffs for diagnosing divergence.

---

## Environment Variables (CI)

| Variable | Value in CI | Purpose |
|----------|-------------|---------|
| `KH_AD_ADAPTER_MODE` | `sandbox` | Forces sandbox adapters (no real API calls) |
| `KH_SMMA_TEST_MODE` | `ci` | Enables CI-specific determinism in adapters |
| `KH_SFTP_HOST` | `''` | Must be empty in CI |
| `KH_SFTP_USER` | `''` | Must be empty in CI |
| `KH_ACCOUNTING_API_URL` | `''` | Must be empty in CI |
| `KH_AD_DELIVERY_ADAPTER` | `sftp` | Default delivery adapter for smoke test |

The workflow asserts that `KH_SFTP_HOST`, `KH_SFTP_USER`, and `KH_ACCOUNTING_API_URL` are empty before running tests.

---

## Test Counts (as of PAID Bucket Closeout)

| Suite | Tests | Source |
|-------|-------|--------|
| PAID unit (PAID-03 to PAID-07) | 71 | `tests/Paid/` (PAID-03 through PAID-07 files) |
| PAID-08 reconciliation unit | 12 | `tests/Paid/ReconciliationRunServiceTest.php` |
| PAID-08 reconciliation integration | 4 | `tests/integration/PaidReconciliationIntegrationTest.php` |
| Legacy PAID adapter tests | 6 | `tests/PaidAdaptersTest.php`, `tests/ManualExportAdapterTest.php` |
| Sandbox safety tests | 10 | `tests/Paid/SandboxSafetyTest.php` |
| RBAC security tests | 8 | `tests/Paid/RbacSecurityTest.php` |
| ManualExport controller unit | 5 | `tests/Paid/ManualExportControllerTest.php` |
| ManualExport download integration | 2 | `tests/integration/ManualExportDownloadIntegrationTest.php` |
| Telemetry coverage tests | 8 | `tests/Paid/TelemetryCoverageTest.php` |
| **Total PAID tests** | **126** | All `tests/Paid/` + `tests/integration/` + legacy |
| Golden fixture check | 9 registered adapters | `scripts/paid_adapter_golden_check.php` |
| Smoke test | 1 (end-to-end PAID-07) | `tests/smoke/paid_adapter_smoke.php` |
| Signoff smoke | 1 (6-phase E2E) | `scripts/paid_end_to_end_smoke.php` |

### CI job: reconciliation-tests (PAID-08)

Added in PAID-08. Runs PHP 8.1, needs `paid-tests`, gates `smoke-test`.

```
tests/Paid/ReconciliationRunServiceTest.php        (12 unit tests)
tests/integration/PaidReconciliationIntegrationTest.php (4 integration tests)
```

Uploads `paid_reconciliation_summary.json` artifact (30-day retention, if present).

---

## Troubleshooting

### `checksum mismatch` in verify_golden_fixtures.php

The `.meta.json` checksum does not match the fixture file. This means the fixture was edited without running the regeneration script.

Fix:
```bash
php scripts/paid_adapter_golden_check.php --write
php scripts/verify_golden_fixtures.php
```

### `output diverged from fixture` in golden check

Adapter code changed its output shape. Review whether this is intentional:
- If intentional: run `regenerate_paid_fixture.php --confirmed` and update the contract if the schema changed
- If unintentional: revert the adapter code change

### Smoke test fails on `settlement row in DB`

The `SettlementWorker::run()` call requires at least one unsettled reconciliation. The smoke test seeds one via `SmokeWpdb::store_unsettled()`. If this fails, check that `SettlementWorker` is reading from the correct in-memory store suffix.
