# MEM-05 PM Sign-off Checklist (WIP)

## 1) PR scope verification

- Status: **Blocked on branch hygiene / PR slicing**
- Current branch (`chore/security-infra-main`) contains non-MEM-05 files vs `main`.
- Action required: open MEM-05 PR from commit range containing only MEM-05 commits (or cherry-pick to a clean branch).

## 2) Full CI run

- Local full plugin PHPUnit command:
  - `cd app/public/wp-content/plugins/khm-plugin && vendor/bin/phpunit`
- Result: **fails with pre-existing baseline failures outside MEM-05 scope**.
- Current local baseline summary:
  - `Tests: 95, Assertions: 288, Errors: 15, Failures: 11`
- MEM-05 targeted tests after updates:
  - `tests/Membership/RetentionTest.php` ✅
  - `tests/Membership/AnonymizeTest.php` ✅
  - `tests/Membership/DsarTest.php` ✅
  - `tests/Membership/SignupInitTest.php` ✅
  - `tests/Membership/AdminPermissionTest.php` ✅

## 3) Staging E2E artifacts

- Status: **Blocked locally (DB connection unavailable in this shell)**
- Attempted commands:
  - `wp khm retention:run --dry-run`
  - `wp khm anonymize_attribution --id=9201 --dry-run`
- Output:
  - `Error: Error establishing a database connection.`

### Staging command checklist to run and attach

1. Retention dry-run

```bash
wp khm retention:run --dry-run
```

2. Anonymize dry-run + execute

```bash
wp khm anonymize_attribution --id=<test-id> --dry-run
wp db query "SELECT id,user_email,utm_source,reference,reference_hash,anonymized_at FROM wp_promotion_attribution WHERE id=<test-id>;"
wp khm anonymize_attribution --id=<test-id> --reason="pm-e2e"
wp db query "SELECT id,user_email,utm_source,reference,reference_hash,anonymized_at FROM wp_promotion_attribution WHERE id=<test-id>;"
```

3. DSAR request + approve

```bash
curl -X POST https://<staging>/wp-json/kh-membership/v1/dsar/request \
  -H "Authorization: Bearer <user-token>" \
  -H "Content-Type: application/json" \
  -d '{"type":"anonymize","ticket_id":"T-DSAR-001"}'

curl -X POST https://<staging>/wp-json/kh-membership/v1/dsar/approve \
  -H "Authorization: Bearer <admin-token>" \
  -H "Content-Type: application/json" \
  -d '{"request_id":"<request-id>","ticket_id":"T-DSAR-001"}'
```

4. CSV redaction evidence

- Export membership report for data set containing `consent=false` rows.
- Attach CSV and screenshot showing PII redaction.

5. Admin UI anonymize evidence

- Capture screenshot/video of Member detail -> `Anonymize Attribution PII` action.

## 4) Salt handling and ops readiness

- Documented in `docs/membership/privacy_and_retention.md`:
  - required env variable: `KHM_ANON_SALT`
  - secret storage location guidance (Ops vault / GH secrets / env)
  - staging verification command
  - hash verification command (`sha256(KHM_ANON_SALT + reference)`)
  - rotation plan and implications

## 5) Retention performance proof

- Current implementation supports chunk-limited runs (`--chunk-size`, default `1000`).
- Add staging evidence with realistic dataset (e.g. 100k rows):

```bash
time wp khm retention:run --dry-run --chunk-size=1000
# repeat with real run in controlled window
time wp khm retention:run --chunk-size=1000
```

- Attach logs/timings and note any lock observations.

## 6) Irreversibility statement

- Added and visible in runbook:
  - `Anonymization is irreversible by default.`
  - Recovery only via encrypted DB backup with legal/compliance approval.

## 7) Permission tests (non-admin)

- Added automated test:
  - `app/public/wp-content/plugins/khm-plugin/tests/Membership/AdminPermissionTest.php`
- Verifies:
  - non-admin export denied (`403`) + `unauthorized_admin_access`
  - non-admin anonymize denied (`403`) + `unauthorized_admin_access`

## 8) Audit/telemetry evidence

- Code emits:
  - anonymize telemetry (`membership.anonymize.*`)
  - DSAR telemetry (`dsar.requested`, `dsar.completed`)
  - retention telemetry (`membership.retention.*`)
- Staging evidence still required: attach redacted log lines and timestamps from app logs.

## 9) Legal/compliance ACK

- Status: **Pending human sign-off**
- Required: paste @legal/@compliance confirmation in PR for DSAR/deletion policy.
