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

### SSH tunnel path for WP Engine-style hosts

When the database only listens on the remote host loopback address, tunnel it locally first:

```bash
ssh -p 2222 -N \
  -L 3307:127.0.0.1:3306 \
  touchpoint5stg-1@touchpoint5stg.sftp.wpengine.com
```

Then run verification directly through the tunnel:

```bash
read -s STAGING_DB_PASS
MYSQL_PWD="$STAGING_DB_PASS" \
mysql --protocol=TCP \
  -h 127.0.0.1 \
  -P 3307 \
  -u touchpoint5stg \
  wp_touchpoint5stg \
  < migrations/verify.sql \
  > artifacts/phase4/migrate_verify_staging.log 2>&1
```

If you want to keep using `scripts/migrate_verify.sh` unchanged, bind the tunnel to local port `3306` and pass `--host 127.0.0.1`.

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
