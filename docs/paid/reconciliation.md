# PAID-08 — Reconciliation & Admin Dashboard Runbook

## Overview

PAID-08 introduces run-level reconciliation: a named orchestration layer that ingests adapter execute/delivery results from `wp_kh_paid_reconciliations`, expands them into per-operation detail rows, classifies each as `matched | variance | unmatched`, surfaces variances to finance staff via an admin dashboard, and allows export as CSV ledgers.

This builds on PAID-04 (manifest-level reconciliation) without replacing it.

---

## Architecture

```
wp_kh_paid_reconciliations       (PAID-04, one row per execute() response)
         │
         ▼
ReconciliationService::start_run()  → wp_kh_paid_recon_runs
ReconciliationService::execute_run() → wp_kh_paid_recon_rows (per-operation)
ReconciliationService::export_run() → wp_kh_paid_recon_exports
         │
         ▼
Admin dashboard (PaidReconciliationPage)
WP-CLI (ReconcileCommand)
REST API (PaidReconciliationRunController)
```

---

## Database Tables

### wp_kh_paid_recon_runs

Run-level metadata. One row per named reconciliation run.

| Column | Type | Notes |
|--------|------|-------|
| `run_id` | VARCHAR(32) PK | `'run_' + sha256(initiator\|filters_hash\|ts)[0:12]` |
| `status` | VARCHAR(20) | `pending\|running\|completed\|failed` |
| `initiator` | VARCHAR(100) | WP user login, `'cli'`, or `'cron'` |
| `adapters` | TEXT | JSON array of channel slugs |
| `filters` | TEXT | JSON: `{sponsor_id, date_start, date_end}` |
| `total_rows` | INT UNSIGNED | Total detail rows across all operations |
| `matched_rows` | INT UNSIGNED | Rows within tolerance |
| `variance_rows` | INT UNSIGNED | Rows outside tolerance |
| `unmatched_rows` | INT UNSIGNED | expected=0 but actual>0 |
| `checksum` | VARCHAR(64) | SHA256 of sorted row_id array (audit integrity) |
| `run_at` | DATETIME | When the run was created |
| `completed_at` | DATETIME NULL | When the run finished |

### wp_kh_paid_recon_rows

Per-operation detail rows. One row per operation per source reconciliation.

| Column | Type | Notes |
|--------|------|-------|
| `row_id` | VARCHAR(40) PK | `'rrow_' + sha256(run_id\|provider_reference)[0:12]` |
| `run_id` | VARCHAR(32) | FK → `wp_kh_paid_recon_runs` |
| `reconciliation_id` | VARCHAR(32) | FK → `wp_kh_paid_reconciliations` |
| `provider_reference` | VARCHAR(200) | Operation ID on the ad platform |
| `sponsor_id` | VARCHAR(100) | |
| `schedule_id` | VARCHAR(100) | |
| `adapter` | VARCHAR(50) | e.g. `linkedin_sandbox`, `google_sandbox` |
| `expected_cost_cents` | BIGINT | `estimated_spend / op_count * 100` (integer cents) |
| `actual_cost_cents` | BIGINT | `actual_spend / op_count * 100` (integer cents) |
| `fees_cents` | BIGINT | Platform fees (reserved, currently 0) |
| `currency` | CHAR(3) | ISO 4217, default `AUD` |
| `variance_cents` | BIGINT | `actual - expected` (signed) |
| `variance_pct` | DECIMAL(8,4) | `abs(variance) / expected * 100` |
| `status` | VARCHAR(30) | `matched\|variance\|unmatched\|resolved` |
| `reconciled_at` | DATETIME | Row creation time |
| `resolved_at` | DATETIME NULL | When resolved |
| `resolver_id` | INT UNSIGNED NULL | WP user ID of resolver |
| `notes` | TEXT NULL | Resolution note |

### wp_kh_paid_recon_exports

Audit trail of CSV exports.

| Column | Type | Notes |
|--------|------|-------|
| `export_id` | VARCHAR(32) PK | `'exp_' + sha256(run_id\|user_id\|ts)[0:12]` |
| `run_id` | VARCHAR(32) | FK → `wp_kh_paid_recon_runs` |
| `user_id` | INT UNSIGNED | WP user ID |
| `row_count` | INT UNSIGNED | Number of data rows in the CSV |
| `checksum` | VARCHAR(64) | SHA256 of produced CSV content |
| `produced_at` | DATETIME | |

---

## Variance / Tolerance Algorithm

```
tolerance_pct       = config['reconciliation']['tolerance_pct']       // 2.0 default
tolerance_min_cents = config['reconciliation']['tolerance_min_cents']  // 100 default

abs_variance = abs(actual_cents - expected_cents)

tolerance_band = max(tolerance_min_cents, round(expected_cents * tolerance_pct / 100))

match (expected_cents, actual_cents):
  (0, 0)               → 'matched'
  (0, >0)              → 'unmatched'
  abs_variance <= band → 'matched'
  else                 → 'variance'
```

**Key properties:**
- Tolerance band is always at least `tolerance_min_cents` (default AUD $1.00) to prevent excessive variance flags on tiny spend amounts
- Per-adapter overrides: `config['reconciliation']['adapter_tolerances']['sftp'] = 3.0`
- Per-sponsor overrides: `config['reconciliation']['sponsor_tolerances']['sp_456'] = 1.0`
- Sponsor override takes priority over adapter override

---

## Configuration

In `config/paid_adapters.php`:

```php
'reconciliation' => [
    'tolerance_pct'       => 2.0,   // ±2% is "matched"
    'tolerance_min_cents' => 100,   // minimum band = AUD $1.00
    'adapter_tolerances'  => [],    // per-adapter: ['sftp' => 3.0]
    'sponsor_tolerances'  => [],    // per-sponsor: ['sp_456' => 1.0]
],
```

---

## Matching Rules

| Source status | `estimated_spend` | `actual_spend` | Result |
|--------------|-------------------|----------------|--------|
| Any | 0 | 0 | `matched` |
| Any | 0 | >0 | `unmatched` |
| reconciled / discrepancy | within tolerance band | within tolerance band | `matched` |
| discrepancy | outside tolerance band | outside tolerance band | `variance` |

Rows from multi-operation manifests have spend distributed evenly across all operations. If `operation_ids` is empty or null, a single synthetic row is created with `provider_reference = reconciliation_id`.

---

## Admin Dashboard

**Location:** WordPress Admin → KH Social → Reconciliation
**Capability required:** `manage_paid_adapters`

### Runs list view

- Table of all runs with status, row counts, and initiator
- Filter by status
- "Start New Run" form: `sponsor_id`, `adapter`, `date_start`, `date_end`

### Run detail view

- Summary stats: total / matched / variance / unmatched rows
- Run checksum (SHA256 of all row_ids — audit integrity)
- Filter detail rows by status, sponsor, adapter
- Inline resolve form for `variance` and `unmatched` rows
- Export CSV button (downloads all rows for the run)

### Resolving a variance row

1. Navigate to the run detail view
2. Filter by `variance` or `unmatched`
3. Enter a resolution note in the inline form and click **Resolve**
4. The row status changes to `resolved` and the note + resolver ID are persisted

Resolution is immutable — `resolved_at` and `resolver_id` cannot be overwritten after the fact.

---

## Admin Triage Playbook

### Routine reconciliation (weekly)

```bash
# WP-CLI (staging or production with read-only DB)
wp kh-smma paid:reconcile run \
  --sponsor_id=sp_456 \
  --date_start=2026-03-01 \
  --date_end=2026-03-07

# Check output: total= matched= variance= unmatched=
wp kh-smma paid:reconcile export --run_id=run_<id> > /tmp/recon_week.csv
```

### Variance investigation

1. Export the CSV: `wp kh-smma paid:reconcile export --run_id=<id>`
2. Filter rows by `status=variance` in the CSV or admin UI
3. Cross-reference `provider_reference` (operation ID) with the ad platform's reporting dashboard
4. If discrepancy is a platform rounding difference: resolve with note `"platform rounding – within policy"`
5. If discrepancy is a billing error: escalate to finance and do not resolve until refund/adjustment is issued

### Unmatched rows

`unmatched` means `expected=0` but the adapter reported actual spend. This indicates a campaign ran outside the planned window or an operation was created outside the manifest. Investigate before resolving.

---

## CLI Commands

```bash
# Start and execute a run immediately
wp kh-smma paid:reconcile run \
  --sponsor_id=sp_456 \
  --adapter=linkedin_sandbox \
  --date_start=2026-03-01 \
  --date_end=2026-03-07

# Dry-run: create the run record but skip execute_run()
wp kh-smma paid:reconcile run \
  --sponsor_id=sp_456 \
  --dry_run

# Export run to CSV (writes recon_run_<id>.csv in current directory)
wp kh-smma paid:reconcile export --run_id=run_abc001234
```

---

## REST API Routes

All routes require `manage_paid_adapters` capability.

| Method | Route | Description |
|--------|-------|-------------|
| `POST` | `/kh-smma/v1/recon/runs` | Start + execute a run |
| `GET` | `/kh-smma/v1/recon/runs` | List runs (filter: status, initiator, date_start, date_end) |
| `GET` | `/kh-smma/v1/recon/runs/{run_id}` | Get a single run |
| `GET` | `/kh-smma/v1/recon/runs/{run_id}/rows` | Get detail rows (filter: status, sponsor_id, adapter) |
| `POST` | `/kh-smma/v1/recon/runs/{run_id}/rows/{row_id}/resolve` | Resolve a row (`note` in body) |
| `GET` | `/kh-smma/v1/recon/runs/{run_id}/export` | Download CSV |

**POST /recon/runs parameters:**
- `sponsor_id` (string)
- `adapters` (array of strings)
- `date_start`, `date_end` (date strings)
- `dry_run` (bool) — creates run but skips execute

---

## Telemetry Events

| Event | Trigger |
|-------|---------|
| `paid_recon.run.started` | `start_run()` |
| `paid_recon.run.completed` | `execute_run()` success |
| `paid_recon.run.failed` | `execute_run()` exception |
| `paid_recon.row.variance` | variance row created during execute |
| `paid_recon.row.resolved` | `resolve_row()` called |
| `paid_recon.export.created` | `export_run()` success |

All events are logged via `AuditLogger::log(event, context)`.

### Action hooks

```php
// Fires when a run completes successfully.
do_action( 'kh_paid_recon_run_completed', $run_row );

// Fires for each variance row created.
do_action( 'kh_paid_recon_row_variance', $recon_row );
```

Wire email/Slack alerts to these hooks in `functions.php` or a separate plugin:

```php
add_action( 'kh_paid_recon_row_variance', function ( $row ) {
    // send_slack_alert( "#finance-alerts", "Variance detected: {$row['provider_reference']}" );
} );
```

---

## Manual Adjustment Playbook

If a row needs financial adjustment (not just resolution):

1. Resolve the row with note referencing the adjustment ticket
2. Use the existing PAID-05 adjustment flow via `/kh-smma/v1/adjust` (FinanceReconciliationPage → Adjustments)
3. Link the adjustment `reconciliation_id` to the reconciliation row being adjusted
4. Re-run the reconciliation after the adjustment posts to verify the new actual spend matches

---

## Verification

```bash
cd app/public/wp-content/plugins/kh-smma

# Unit tests (12 new PAID-08 tests)
vendor/bin/phpunit tests/Paid/ReconciliationRunServiceTest.php --testdox

# Integration tests (4 new PAID-08 tests)
vendor/bin/phpunit tests/integration/PaidReconciliationIntegrationTest.php --testdox

# Full PAID suite (71 existing + 16 new = 87 tests)
vendor/bin/phpunit \
  tests/Paid/ tests/integration/ \
  tests/PaidAdaptersTest.php tests/ManualExportAdapterTest.php \
  --testdox

# Golden fixtures still pass
php scripts/verify_golden_fixtures.php

# Smoke test still passes
php tests/smoke/paid_adapter_smoke.php

# WP-CLI dry-run (staging only — requires WP installed)
wp kh-smma paid:reconcile run --sponsor_id=sp_456 --dry_run
```

---

## Release Note — PAID Bucket Closeout

### Summary

The PAID bucket is complete across eight incremental tickets (PAID-01 through PAID-08 plus closeout). The system now covers the full paid advertising lifecycle: manifest creation → sandbox dry_run/execute → manifest-level reconciliation → run-level reconciliation (PAID-08) → settlement → delivery (SFTP + API) → CSV export for finance triage.

Phase closeout adds: sandbox safety tests verifying no live credentials are read in CI; RBAC gating tests for all PAID controllers; a secure Manual Export download endpoint; a dev helper script for local adapter inspection; telemetry coverage tests asserting all audit events emit required fields; a discrepancy alert signoff smoke; and a full 6-phase E2E signoff smoke with evidence JSON artifact.

### Staging verification commands

```bash
# 1. Full test suite (120 tests)
cd app/public/wp-content/plugins/kh-smma
vendor/bin/phpunit tests/Paid/ tests/integration/ \
  tests/PaidAdaptersTest.php tests/ManualExportAdapterTest.php --testdox

# 2. Golden fixtures (21 fixtures)
php scripts/verify_golden_fixtures.php

# 3. Discrepancy alert smoke
php scripts/paid04_signoff_smoke.php

# 4. Full E2E signoff smoke (produces paid_end_to_end_evidence.json)
php scripts/paid_end_to_end_smoke.php

# 5. WP-CLI recon run (staging with WP installed)
wp kh-smma paid:reconcile run \
  --sponsor_id=sp_456 \
  --date_start=2026-03-01 \
  --date_end=2026-03-07

# 6. REST API test (requires authenticated session)
curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"sponsor_id":"sp_456","date_start":"2026-03-01","date_end":"2026-03-07"}' \
  https://staging.example.com/wp-json/kh-smma/v1/recon/runs | jq .

# 7. Manual export bundle download
curl -s \
  -H "Authorization: Bearer <token>" \
  https://staging.example.com/wp-json/kh-smma/v1/manual-export/man_001 | jq .
```

### Post-deploy SQL checks

```sql
-- Confirm new PAID-08 tables exist
SHOW TABLES LIKE 'wp_kh_paid_recon_%';
-- Expected: wp_kh_paid_recon_runs, wp_kh_paid_recon_rows, wp_kh_paid_recon_exports

-- Confirm no orphaned run rows (all runs completed or failed)
SELECT status, COUNT(*) FROM wp_kh_paid_recon_runs GROUP BY status;

-- Confirm variance rows are resolvable
SELECT COUNT(*) FROM wp_kh_paid_recon_rows WHERE status = 'variance' AND resolved_at IS NULL;
```

### PR artifact checklist

- [ ] `golden-summary.json` uploaded (all 21 fixtures pass)
- [ ] `paid_end_to_end_evidence.json` uploaded (all 6 phases pass)
- [ ] `paid_reconciliation_summary.json` uploaded (16 reconciliation tests pass)
- [ ] CI report: 120 tests pass on PHP 8.1 and 8.2
- [ ] Sandbox safety step: no live secrets consumed
- [ ] Signoff smoke job: green in `integration:paid-adapters` workflow
