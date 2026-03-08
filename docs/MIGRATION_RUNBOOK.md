# Phase 4 Migration Runbook

## Goal

Apply database migrations safely with dry-run verification, replica validation, and documented rollback.

## Required inputs

- staging or replica DB credentials
- approved maintenance window when a migration may lock a large table
- current backup / snapshot reference

## Dry-run

```bash
scripts/migrate_verify.sh --dry-run
```

## Replica verification

```bash
export DB_HOST=staging-replica
export DB_NAME=touchpoint
export DB_USER=readonly
export DB_PASS='***'
scripts/migrate_verify.sh --host "$DB_HOST" --db "$DB_NAME" --user "$DB_USER" --pass "$DB_PASS"
```

The verify phase runs `migrations/verify.sql` and writes:

- `artifacts/migrations/staging_dryrun.log`
- `artifacts/migrations/verify_output.txt`

## Lock-sensitive migration guidance

Use online DDL where supported:

```sql
ALTER TABLE khm_email_queue
  ALGORITHM=INPLACE,
  LOCK=NONE,
  ADD COLUMN idempotency_key VARCHAR(255) NULL,
  ADD UNIQUE INDEX uniq_email_idempotency (idempotency_key(191));
```

If the server does not support online DDL, stop and schedule an offline window.

## Rollback

1. Restore from the pre-migration snapshot if the migration was destructive or partially applied.
2. For additive indexes/columns, use the migration-specific down SQL.
3. Re-run `migrations/verify.sql` after rollback.

## Human approval gates

- any production migration that can lock a table
- any rollback on production
