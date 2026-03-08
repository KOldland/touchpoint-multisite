# Phase 4 Operator Checklist

Use this checklist after migration approval is granted. Do not run production migration, real Stripe validation, or canary traffic shift without the required human approvals.

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

## 6. Optional full membership sanity run

```bash
cd app/public/wp-content/plugins/khm-plugin
vendor/bin/phpunit --testsuite membership \
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
