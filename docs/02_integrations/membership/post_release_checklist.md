# Membership Post-Release Checklist (24h)

Use this as the acceptance report template after canary/full rollout.

## Release metadata

- Release ID:
- Commit(s):
- Window start/end (UTC):
- Operator:

## Stage outcomes

- [ ] Preflight gate passed (`scripts/mem_release_gate_check.php`)
- [ ] Canary completed (5%, 60-120 min) with no P0 alerts
- [ ] Full enable completed
- [ ] Rollback path verified (command tested)

## Signal checks (first 2h)

- `membership.attribution.created` expected range: ______
- `membership.attribution.missing` count: ______
- `membership.email.failed` rate (target <=1% or <=5/hour): ______
- `webhook.invalid_signature` count: ______
- DLQ growth (`khm_webhook_dead_letter`) baseline delta: ______

## 24-hour monitoring checks

- [ ] Hourly metric review completed
- [ ] No sustained P0/P1 anomalies
- [ ] Queue depth remained within expected envelope
- [ ] DLQ replay path executed successfully (if needed)
- [ ] Retention run completed and logs attached

## Operational command evidence

```bash
wp khm membership-email-control --mode=status
wp khm membership-webhook-dead-letters --last=50
wp khm membership-webhook-dead-letters-replay --all-open --limit=20
wp khm retention:run --dry-run --chunk-size=1000
wp khm retention:run --mode=anonymize --chunk-size=1000
```

## Artifact attachments

- `smoke-telemetry.json`
- `dlq_before.txt` / `dlq_after.txt`
- `email_queue_before.txt` / `email_queue_after.txt`
- `retention_dry_run.log`
- `retention_execute.log`
- `membership_health_dashboard_export.json` (optional)
- `anomaly_logs.txt` (if non-empty)

## Acceptance decision

- [ ] Accepted
- [ ] Accepted with follow-up actions
- [ ] Rejected / rollback required

Decision owner:

Follow-up actions + owners:
