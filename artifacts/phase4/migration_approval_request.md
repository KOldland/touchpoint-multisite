# Approval Request: Production Migration

Subject: [Approval] Prod migration for khm-plugin / Phase 4 readiness

Summary:
We need approval to run the Phase 4 migration verification and any resulting production migration steps for the current `integration/hardening` release baseline.

Context:

- Merged PR: `#64` Production readiness foundations
- Merge commit: `d64f51fe2eced9cfc5338bf563a4a255f0756cb7`
- Runbook: `docs/MIGRATION_RUNBOOK.md`
- Verification script: `scripts/migrate_verify.sh`
- Verification SQL: `migrations/verify.sql`

Change scope:

- verify required webhook, email queue, and attribution schema state
- confirm `wp_khm_email_queue.idempotency_key` and `uniq_email_idempotency`
- confirm processed webhook / DLQ tables are present and queryable

Estimated impact:

- staging / replica verification: no production impact
- production migration risk: low for verification-only reads; any online DDL must use `ALGORITHM=INPLACE, LOCK=NONE` where supported
- if the platform does not support online DDL, stop and schedule an offline window

Requested execution window:

- staging replica verify: `YYYY-MM-DD HH:MM UTC`
- production window, if later approved: `YYYY-MM-DD HH:MM UTC`

Commands to run after approval:

```bash
export DB_HOST=<staging-or-prod-host>
export DB_NAME=<db-name>
export DB_USER=<db-user>
export DB_PASS='<db-pass>'

./scripts/migrate_verify.sh \
  --host "$DB_HOST" \
  --db "$DB_NAME" \
  --user "$DB_USER" \
  --pass "$DB_PASS" \
  > artifacts/phase4/migrate_verify_staging.log 2>&1
```

Rollback / backout:

1. Stop immediately if verification or migration reports lock contention or schema mismatch.
2. Use the rollback steps in `docs/MIGRATION_RUNBOOK.md`.
3. Restore from the pre-migration snapshot if any destructive or partial change occurs.

Evidence to review:

- PR: `https://github.com/KOldland/touchpoint-template/pull/64`
- artifact target: `artifacts/phase4/migrate_verify_staging.log`
- signoff template: `artifacts/phase4/phase4_signoff.md`

Approval request:

Please reply `APPROVE` to proceed with staging/replica verification, or `DECLINE` with the blocking concern.
