# Canary Runbook

## Stages

1. Preflight (0%)
2. Internal canary (staff only)
3. 5% traffic canary
4. 25% traffic canary
5. 100% rollout

## Preflight checklist

- RC artifact + signature produced
- `phase4-ci-gates` green
- `phase4-security` green
- migration verification complete
- baseline SLO dashboards captured

## Execute canary

```bash
# example commands – replace with your deploy platform tooling
export CANARY_PERCENT=5
./deploy --env production --artifact release-rc-<tag>.zip --canary-percent "$CANARY_PERCENT"
```

## Checks every 15 minutes

- error rate not > 2x baseline
- p95 latency not > 2x baseline
- DLQ growth flat
- `membership.attribution.missing` not > baseline + 5%
- email queue backlog stable

## Stop / rollback conditions

- any threshold above trips
- unexplained payment failures
- invalid signature alert sustained for 5m+

## Rollback

1. Flip traffic back to the previous RC.
2. Disable any new feature flags.
3. Replay safe DLQ events after rollback.
4. Record incident timeline and open post-mortem.
