# MEM-05 PM Gate Response (Copy/Paste for PR)

> MEM-07 docs/runbook references for current process:
>
> - [docs/membership/README.md](docs/membership/README.md)
> - [docs/membership/developer_guide.md](docs/membership/developer_guide.md)
> - [docs/membership/staging_e2e_runbook.md](docs/membership/staging_e2e_runbook.md)
> - [docs/membership/ops_runbook.md](docs/membership/ops_runbook.md)
> - [docs/membership/commands.md](docs/membership/commands.md)
> - [docs/membership/troubleshooting.md](docs/membership/troubleshooting.md)
> - [docs/membership/pm_signoff_checklist.md](docs/membership/pm_signoff_checklist.md)
> - [docs/membership/staging_evidence_template.md](docs/membership/staging_evidence_template.md)

## MEM-05 status summary

Implemented and pushed: consent gating, anonymization, retention worker, DSAR endpoints/workflow, admin + CLI wiring, contract/docs updates, and privacy tests.

### MEM-05 commit set (for PR scope)

- `e407c97` — core MEM-05 implementation
- `1ce36b1` — anonymize command compatibility wrapper
- `2d0e268` — PM feedback follow-up (retention dry-run, permission tests, runbook updates)

> Note: this branch contains additional non-MEM historical changes vs `main`. For strict scope, create PR from a clean branch with only the three commits above.

---

## PM required gates

### 1) PR scope verification

- [x] MEM-05 commit set identified (see above)
- [ ] PR file list contains only MEM-05 files (pending PR slicing/cherry-pick branch)
- [ ] Any unrelated files moved to separate PR

### 2) Full CI run

- [x] Full local plugin suite executed (`vendor/bin/phpunit`)
- [x] Baseline result captured: `Tests: 95, Assertions: 288, Errors: 15, Failures: 11`
- [ ] CI job links attached in PR
- [ ] Any failing jobs marked as pre-existing + linked to tracking ticket

Current local baseline (non-MEM-05 failures): includes GEO patchwork/bootstrap ordering, Stripe marketing importer expectations, checkout price resolver expectations, attribution endpoint assertions, and status endpoint suite setup.

### 3) Staging E2E artifacts

- [ ] `wp khm retention:run --dry-run` output attached
- [ ] `wp khm anonymize_attribution --id=<test-id> --dry-run` output attached
- [ ] anonymize execute output + DB before/after query attached
- [ ] DSAR request/approve trace + ZIP artifact link attached
- [ ] audit log lines for `dsar.requested` and `dsar.completed` attached
- [ ] CSV export showing `consent=false` redaction attached
- [ ] admin UI anonymize screenshots/video attached

Artifact command block used on staging:

```bash
# retention preview
wp khm retention:run --dry-run

# anonymize preview + execute + DB before/after
wp khm anonymize_attribution --id=<test-id> --dry-run
wp db query "SELECT id,user_email,utm_source,reference,reference_hash,anonymized_at FROM wp_promotion_attribution WHERE id=<test-id>;"
wp khm anonymize_attribution --id=<test-id> --reason="pm-e2e"
wp db query "SELECT id,user_email,utm_source,reference,reference_hash,anonymized_at FROM wp_promotion_attribution WHERE id=<test-id>;"
```

### 4) Salt handling & ops readiness

- [x] `KHM_ANON_SALT` documented as env-only (not in repo)
- [x] expected location documented (Ops vault / GH secrets / env)
- [x] staging salt-presence verification command documented
- [x] hash-generation verification command documented
- [x] salt rotation implications + plan documented

Reference: docs runbook updated in [docs/membership/privacy_and_retention.md](docs/membership/privacy_and_retention.md).

### 5) Retention performance proof

- [x] chunked behavior documented (`--chunk-size`, default `1000`)
- [ ] 100k-row timing evidence attached from staging/local perf run
- [ ] lock behavior note attached

Suggested evidence commands:

```bash
time wp khm retention:run --dry-run --chunk-size=1000
# controlled run window
time wp khm retention:run --chunk-size=1000
```

### 6) Anonymize irreversibility statement

- [x] explicit irreversible-by-default statement added
- [x] reversible path defined only via encrypted DB backup + legal/compliance approval

Reference: [docs/membership/privacy_and_retention.md](docs/membership/privacy_and_retention.md).

### 7) Role & permission tests

- [x] non-admin anonymize/export denial test added
- [x] asserts 403 behavior + `unauthorized_admin_access` logging
- [x] test passes

Test file: [app/public/wp-content/plugins/khm-plugin/tests/Membership/AdminPermissionTest.php](app/public/wp-content/plugins/khm-plugin/tests/Membership/AdminPermissionTest.php)

### 8) Audit & telemetry evidence

- [x] telemetry/audit emission implemented for anonymize, retention, DSAR flows
- [ ] staging log excerpts with timestamps attached for:
  - `membership.anonymize.succeeded`
  - `membership.retention.*`
  - `dsar.requested`
  - `dsar.completed`

### 9) DSAR policy/legal sign-off

- [ ] @legal/@compliance ACK comment attached in PR
- [x] policy documented: anonymize by default; hard delete requires sign-off/ticket

---

## Required checklist (PM sign-off block)

- [ ] PR only contains MEM-05 files
- [ ] Full CI run links (all required jobs) attached
- [ ] Staging E2E artifacts attached (retention, anonymize, DSAR, CSV redaction, UI evidence)
- [x] `KHM_ANON_SALT` documented and Ops location noted
- [ ] Retention perf evidence (100k rows) attached
- [x] Permission tests included and passing
- [ ] Audit/telemetry sample logs attached
- [ ] Legal/compliance ACK attached
- [x] Runbook updated: [docs/membership/privacy_and_retention.md](docs/membership/privacy_and_retention.md)

---

## Staged rollout / rollback note

- [x] staged rollout sequence documented (staging dry-run -> small anonymize batch -> telemetry verify -> production window)
- [x] rollback posture documented (stop further anonymization; restore only from approved DB backup)
