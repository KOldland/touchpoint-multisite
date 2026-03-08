# Approval Request: Canary Kickoff

Subject: [Approval] Phase 4 canary rollout kickoff

Summary:
We need approval to begin the Phase 4 canary rollout after release candidate build, staging migration verification, and staging smoke/load checks are complete.

Context:

- Merged PR: `#64` Production readiness foundations
- Merge commit: `d64f51fe2eced9cfc5338bf563a4a255f0756cb7`
- Release runbook: `docs/RELEASE_RUNBOOK.md`
- Canary runbook: `runbooks/canary_run.md`
- Canary report template: `artifacts/phase4/canary_report.md`

Preflight gates that must be green before kickoff:

1. RC artifact + checksum/signature built from `tools/release/create_rc.sh`
2. `phase4-ci-gates` green
3. `phase4-security` green
4. `phase4-migrate-verify` run successfully on staging/replica
5. staging load test and canary smoke artifacts reviewed

Planned rollout sequence:

1. Internal canary: staff / QA only, 2 hours
2. `5%` traffic canary, 24 hours
3. `25%` traffic canary, 24 hours
4. `100%` rollout if thresholds remain stable

STOP conditions:

- error rate > `2x` baseline
- p95 latency > `2x` baseline
- DLQ growth above threshold
- `membership.attribution.missing` > baseline + `5%`
- sustained `webhook.invalid_signature` alert

Rollback:

```bash
# feature-flag or weighted-routing rollback example
./tools/feature_flag/set_flag.sh khm_release_canary false
```

or redeploy the previous RC per `runbooks/canary_run.md`.

Evidence to review:

- PR: `https://github.com/KOldland/touchpoint-template/pull/64`
- canary report target: `artifacts/phase4/canary_report.md`
- signoff template: `artifacts/phase4/phase4_signoff.md`

Approval request:

Please reply `APPROVE` to allow canary kickoff once the preflight gates are attached, or `DECLINE` with the blocking concern.
