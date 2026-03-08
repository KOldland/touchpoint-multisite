# Phase 4 Operator Checklist

Use this checklist after migration approval is granted. Do not run production migration, real Stripe validation, or canary traffic shift without the required human approvals.

Current staging target:

- `http://touchpoint5stg.wpenginepowered.com`

## 1. Refresh the release branch

```bash
git fetch origin
git checkout integration/hardening
git pull origin integration/hardening
```

## 2. Build the release candidate

```bash
export RELEASE_GPG_KEY_ID="<gpg-key-id>"
export RELEASE_GPG_PASSPHRASE="<gpg-passphrase>"

tools/release/create_rc.sh rc-$(date +%Y%m%d-%H%M%S)
```

Verify:

- RC zip exists
- SHA256 file exists
- ASCII-armored signature exists when GPG is configured

If no local GPG key is available, use the `phase4-release-rc` workflow with release secrets configured in GitHub Actions and attach the workflow artifact instead of a locally signed zip.

## 3. Run staging / replica migration verification

```bash
export DB_HOST="<staging-replica-host>"
export DB_NAME="<db-name>"
export DB_USER="<db-user>"
export DB_PASS="<db-pass>"

./scripts/migrate_verify.sh \
  --host "$DB_HOST" \
  --db "$DB_NAME" \
  --user "$DB_USER" \
  --pass "$DB_PASS" \
  > artifacts/phase4/migrate_verify_staging.log 2>&1
```

Verify:

- `migrations/verify.sql` reports expected schema/index state
- no lock warnings or connection errors in the log

### WP Engine / SSH tunnel fallback

If the database is only reachable from the staging host and exposes MySQL on `127.0.0.1:3306`, create a local tunnel first.

Terminal 1:

```bash
ssh -p 2222 -N \
  -L 3307:127.0.0.1:3306 \
  touchpoint5stg-1@touchpoint5stg.sftp.wpengine.com
```

Terminal 2:

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

Notes:

- this tunnel method is required because the current `scripts/migrate_verify.sh` accepts `--host` but does not yet expose a separate `--port` argument
- if you prefer to use the script unchanged, bind the tunnel to local port `3306` instead of `3307`, but only if local `3306` is free
- rotate the database password after use because it was exposed during coordination

## 4. Run staging load and canary smoke

```bash
mkdir -p artifacts/phase4

k6 run tests/perf/k6/webhook_throughput.js \
  --out json=artifacts/phase4/k6_webhook_throughput.json

k6 run tests/perf/k6/canary_smoke.js \
  --out json=artifacts/phase4/k6_canary_smoke.json
```

Verify:

- p95 / p99 latency within agreed thresholds
- no abnormal DLQ or queue growth during smoke

If `k6` is not installed locally, run the GitHub workflows instead:

```bash
gh workflow run load-test.yml -f base_url="http://touchpoint5stg.wpenginepowered.com" -f vus="50" -f duration="2m"
gh workflow run canary-smoke.yml -f base_url="http://touchpoint5stg.wpenginepowered.com"
```

## 5. Run security checks

```bash
scripts/secret-scan.sh > artifacts/phase4/secret_scan.log 2>&1

cd app/public/wp-content/plugins/khm-plugin
composer audit > ../../../../../artifacts/phase4/composer_audit.log 2>&1 || true
cd ../../../../../
```

Verify:

- secret scan passes cleanly
- any composer audit findings are reviewed and triaged before canary

If `composer audit` cannot reach Packagist from the local machine, use the `phase4-security` workflow result as the authoritative audit artifact.

## 6. Optional full membership sanity run

```bash
cd app/public/wp-content/plugins/khm-plugin
vendor/bin/phpunit --testsuite membership \
  > ../../../../../artifacts/phase4/phpunit_membership_final.log 2>&1
cd ../../../../../
```

If the `membership` testsuite alias resolves to no tests in the local environment, run the full plugin suite instead:

```bash
cd app/public/wp-content/plugins/khm-plugin
vendor/bin/phpunit \
  > ../../../../../artifacts/phase4/phpunit_membership_final.log 2>&1
cd ../../../../../
```

## 7. Publish the release candidate

```bash
ls -lh artifacts/release-rc/*

# Example for tag rc-20260308-120000
gh release create rc-20260308-120000 \
  artifacts/release-rc/rc-20260308-120000/release-rc-rc-20260308-120000.zip \
  artifacts/release-rc/rc-20260308-120000/release-rc-rc-20260308-120000.zip.sha256 \
  artifacts/release-rc/rc-20260308-120000/release-rc-rc-20260308-120000.zip.asc \
  --title "RC 20260308-120000" \
  --notes-file docs/RELEASE_RUNBOOK.md
```

## 8. Required artifacts to attach

- `artifacts/phase4/migrate_verify_staging.log`
- `artifacts/phase4/k6_webhook_throughput.json`
- `artifacts/phase4/k6_canary_smoke.json`
- `artifacts/phase4/secret_scan.log`
- `artifacts/phase4/composer_audit.log`
- `artifacts/phase4/phpunit_membership_final.log`
- `artifacts/phase4/canary_report.md`
- `artifacts/phase4/phase4_signoff.md`
- `artifacts/release-rc/<tag>/release-rc-<tag>.zip`
- `artifacts/release-rc/<tag>/release-rc-<tag>.zip.sha256`
- `artifacts/release-rc/<tag>/release-rc-<tag>.zip.asc`

## 9. Pass criteria

- migration verify completes without schema errors or lock warnings
- load and canary smoke stay within thresholds
- secret scan passes
- RC artifact, checksum, and signature exist

## 10. Approval boundaries

Do not proceed without explicit approval for:

- production migration execution
- any test using real Stripe production secrets
- canary traffic shift in production
