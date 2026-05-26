# Membership Performance Runbook (MEM-09)

## Objective

Validate membership flow performance and resilience at production-like scale:

`landing -> signup-init -> webhook -> attribution -> reconciliation`

## 1) Baseline setup

- Ensure staging has recent MEM schema and webhook/email workers enabled.
- Apply index migration:

```bash
wp eval-file migrations/20260304_add_indexes_attribution.php
```

- Confirm release gate is passing first:

```bash
php scripts/mem_release_gate_check.php --environment=staging
```

## 2) Load test matrix

Run incremental throughput targets using `tests/perf/membership_load_test.php`.

### 100/s target

```bash
php tests/perf/membership_load_test.php \
  --base-url="https://staging.example.com" \
  --requests=6000 \
  --concurrency=100 \
  --mode=end_to_end \
  --out=artifacts/mem09-load-100
```

### 500/s target

```bash
php tests/perf/membership_load_test.php \
  --base-url="https://staging.example.com" \
  --requests=30000 \
  --concurrency=500 \
  --mode=end_to_end \
  --out=artifacts/mem09-load-500
```

### 1k/s target

```bash
php tests/perf/membership_load_test.php \
  --base-url="https://staging.example.com" \
  --requests=60000 \
  --concurrency=1000 \
  --mode=landing_only \
  --out=artifacts/mem09-load-1000
```

Artifacts produced:

- `membership_load_results.csv`
- `membership_load_summary.json`

## 3) EXPLAIN plans (before/after index migration)

Critical report query:

```sql
EXPLAIN SELECT p.id, p.schedule_id, p.sponsor_id, p.user_id, p.user_email, p.created_at
FROM wp_promotion_attribution p
WHERE p.schedule_id = 123
  AND DATE(p.created_at) >= '2026-01-01'
ORDER BY p.created_at DESC, p.id DESC
LIMIT 50 OFFSET 0;
```

Webhook status query:

```sql
EXPLAIN SELECT event_id, status, created_at
FROM wp_khm_processed_webhook_events
WHERE status = 'failed'
ORDER BY created_at DESC
LIMIT 100;
```

Reconciliation query:

```sql
EXPLAIN SELECT reconciliation_id, status, discrepancy_percent, created_at
FROM wp_kh_paid_reconciliations
WHERE status IN ('discrepancy','error')
ORDER BY created_at DESC
LIMIT 100;
```

Capture explain outputs in artifacts:

- `explain_before.sql`
- `explain_after.sql`

## 4) Retention performance simulation

Seed + run 100k rows:

```bash
wp eval-file scripts/retention_perf_simulator.php -- --rows=100000 --chunk-size=1000 --mode=anonymize
```

Sample output includes:

- batch count
- total duration
- memory delta
- updated row totals

## 5) Queue and DLQ pressure checks

```bash
wp db query "SELECT status, COUNT(*) c FROM wp_khm_email_queue GROUP BY status ORDER BY c DESC;"
wp khm membership-webhook-dead-letters --last=200
```

DLQ stress recovery proof:

```bash
wp khm membership-webhook-dead-letters-replay --all-open --limit=50
```

## 6) Acceptance baseline targets

Set initial SLO baselines per staging hardware:

- `signup-init` p95 under 250ms
- webhook queue admission p99 under 500ms
- webhook processing p99 under 2000ms
- `membership.email.failed` <= 1% and <= 5/hour

Adjust in [slo.md](slo.md) after first full benchmark run.

## 7) Required artifacts for MEM-09 sign-off

- `membership_load_results.csv` (each scale)
- `membership_load_summary.json` (each scale)
- `explain_before.sql` + `explain_after.sql`
- `retention_perf_output.json`
- `dlq_stress_recovery.log`
- `email_queue_pressure.log`
