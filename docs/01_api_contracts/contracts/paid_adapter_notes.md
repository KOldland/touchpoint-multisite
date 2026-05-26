# Paid Adapter Notes & Runbook

Owner: @paid-adapter-team
Last updated: 2026-03-03 (PAID-02)

## Overview

All paid adapters (ManualExportAdapter, LinkedInSandboxAdapter, GoogleSandboxAdapter, etc.)
implement `PaidAdapterContract` and share the same manifest input / execute response shapes
defined in the JSON Schemas at:

- `docs/contracts/paid_adapter_manifest.json` — manifest input (dry_run and execute)
- `docs/contracts/paid_adapter_execute.json` — execute response
- `docs/contracts/paid_reconciliation.json` — reconciliation row (PAID-04)

## Idempotency

Every manifest **must** include `meta.idempotency_key` (UUID v4).

```
execute($manifest):
  1. Check store for idempotency_key
  2. If found → return cached response immediately (no duplicate work)
  3. If not found → execute, store response, return response
```

**kh-smma adapters** use `AdapterIdempotencyStore` (WP options, `kh_paid_idem_{md5(key)}`).
**khm-plugin adapters** use `DatabaseIdempotencyStore` (DB-backed, `khm_webhook_events` table).

If an `execute()` throws `AdapterExecutionException`, the idempotency key is **NOT** consumed —
the caller may retry after resolving the underlying issue.

To simulate idempotency locally:
```bash
# Run execute twice with the same key — second call must return same response
export KH_SMMA_TEST_MODE=ci
vendor/bin/phpunit tests/Paid/ManualExportAdapterTest.php --filter test_execute_idempotency
```

## Manual Export Bundle Format

`ManualExportAdapter::execute()` stores the full manifest to a WP option:
```
kh_paid_bundle_{manifest_id}
```

In production, ops should retrieve the bundle, build the ad placement file
(CSV/JSON), and submit manually to the ad platform. After manual execution,
mark the schedule as complete and trigger reconciliation.

Ops bundle keys for manual processing:
- `manifest_id` — identifier for reconciliation
- `operations[]` — list of ops with type, channel, creative, bid, targeting
- `meta.sponsor_id` — for spend attribution
- `meta.schedule_id` — links back to the SMMA schedule
- `meta.idempotency_key` — required for reconciliation dedup

## Partial Failure & Retry Flow

When `execute()` returns `status: partial_success`:
1. Check `operation_results[]` for entries with `result: failed`
2. For each failed op, check `error.retryable`
3. If `retryable: true` — rebuild a new manifest containing only the failed ops
   with a **new** `idempotency_key` (don't reuse the original) and call `execute()` again
4. If `retryable: false` — escalate to ops; do not retry automatically
5. After successful retry, submit a reconciliation correction

## Adding a New Adapter (kh-smma)

1. Create `src/Adapters/YourAdapter.php` implementing `PaidAdapterContract`
   (or extending `PaidAdapterBase` if you need the shared helper methods)
2. Implement `dry_run(array $manifest, array $opts = []): array`
   — must be deterministic and return `manifest_id`, `operations[]`, `total_estimated_spend`, `currency`, `timestamp`
3. Implement `execute(array $manifest, array $opts = []): array`
   — inject `AdapterIdempotencyStore`, check key before executing, store after
   — call `AuditLogger::log('paid_adapter.execute', [...])` on every execute
   — return response conforming to `paid_adapter_execute.json`
4. Add golden fixtures: `paid_adapter_dry_run_manifest.json` (input) + `paid_adapter_dry_run_response.json`
5. Add unit tests in `tests/Paid/YourAdapterTest.php`
6. Get `golden-owner-approved` label on fixture PRs (CIC policy)

## Reconciliation (PAID-04)

After `execute()` returns, the caller should trigger `PaidReconciliationService` (PAID-04):
```
reconcile($manifest_id, $execute_response, $dry_run_response)
  → creates reconciliation_row with estimated vs actual spend
  → emits telemetry paid_adapter.reconciled
  → updates sponsor spend meta in kh-ad-manager
```

Discrepancy threshold alert fires when `|discrepancy_percent| > 10%`.

## Telemetry Events

| Event | When | Key fields |
|---|---|---|
| `paid_adapter.dry_run` | on dry_run() | manifest_id, adapter, sponsor_id, estimated_spend |
| `paid_adapter.execute` | on execute() | manifest_id, adapter, estimated_spend, currency, idempotency_key |
| `paid_adapter.reconciled` | on reconcile | reconciliation_id, discrepancy_percent, status |
