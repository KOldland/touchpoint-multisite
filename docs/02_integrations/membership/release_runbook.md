# Membership Release Runbook (MEM-08)

## Goal

Safely release membership changes with staged activation and runbook-driven rollback.

## Release stages and timeboxes

1. **Flag-off preflight (15-30 min)**
2. **Smoke + gate checks (20-40 min)**
3. **Canary (5% internal, 60-120 min)**
4. **Full enable + soak (2-24 hr monitoring)**

## Stage 1: Flag-off preflight

```bash
wp khm membership-email-control --mode=status
wp khm membership-email-control --mode=disable --reason="mem08-preflight"
```

Run release gate:

```bash
php scripts/mem_release_gate_check.php --environment=production
```

Gate includes:

- golden fixture verification
- membership smoke tests
- retention sanity tests

## Stage 2: Smoke and baseline capture

Baseline snapshots:

```bash
wp khm membership-webhook-dead-letters --last=50 > artifacts/dlq_before.txt
wp db query "SELECT id,template,status,attempt_count,created_at FROM wp_khm_email_queue ORDER BY id DESC LIMIT 50;" > artifacts/email_queue_before.txt
```

Controlled smoke (production-safe or staging-full-load):

- Execute 500-1000 synthetic/controlled conversions.
- Capture telemetry and queue behavior.

## Stage 3: Canary enable (5%)

```bash
wp khm membership-email-control --mode=canary --canary-percent=5 --reason="mem08-canary-start"
wp khm membership-email-control --mode=status
```

Monitor for 60-120 minutes:

- `membership.attribution.created` at expected rate
- `membership.attribution.missing` low/flat
- `membership.email.failed` <= 1% or <= 5/hour
- `webhook.invalid_signature` no sustained spikes
- DLQ growth flat within baseline

If canary stable, proceed to full.

## Stage 4: Full enable

```bash
wp khm membership-email-control --mode=enable --reason="mem08-full-enable"
wp khm membership-email-control --mode=status
```

Collect 24-hour monitoring evidence using [post_release_checklist.md](post_release_checklist.md).

## Emergency rollback

Immediate rollback command:

```bash
wp khm membership-email-control --mode=rollback --reason="mem08-rollback-incident"
```

Then run:

```bash
wp khm membership-webhook-dead-letters --last=50
wp khm membership-webhook-dead-letters-replay --all-open --limit=20
```

If data privacy action is required:

```bash
wp khm anonymize_attribution --id=<ID> --reason="incident-response"
```

## Retention and lock-safety checks under load

```bash
time wp khm retention:run --dry-run --chunk-size=1000
time wp khm retention:run --mode=anonymize --chunk-size=1000
```

No lock escalation or long-blocking query patterns should be observed.

## Required release artifacts

- `smoke-telemetry.json`
- `dlq_before.txt` / `dlq_after.txt`
- `email_queue_before.txt` / `email_queue_after.txt`
- `retention_execute.log`
- admin screenshots for release toggles
- anomaly log bundle (if any)
