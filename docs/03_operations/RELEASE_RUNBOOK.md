# Phase 4 Release Runbook

## Purpose

Ship a reproducible release candidate, verify all gates, and prepare a signed artifact for canary rollout.

## Preflight

1. Confirm `integration/hardening` is green.
2. Confirm `phase4-ci-gates`, `phase4-security`, and `phase4-migrate-verify` are green.
3. Confirm `quoteclub-invite-ui` is green (Quote Club invite browser gate — required, defined in `mem-06-qa.yml`).
4. Confirm no unresolved production migration approvals remain.

## Build the RC

```bash
export RELEASE_GPG_KEY_ID=<gpg-key-id>
export RELEASE_GPG_PASSPHRASE='<passphrase>'
tools/release/create_rc.sh 20260308-rc1
```

Outputs:

- `artifacts/release-rc/<tag>/release-rc-<tag>.zip`
- `artifacts/release-rc/<tag>/release-rc-<tag>.zip.sha256`
- `artifacts/release-rc/<tag>/release-rc-<tag>.zip.asc` when GPG is configured

## Verification

1. Run `php scripts/mem_release_gate_check.php --environment=staging`.
2. Run `scripts/migrate_verify.sh --dry-run`.
3. Run the canary smoke workflow against staging.
4. Attach artifacts to the release PR and release page.

## Human approvals

- Production migrations: DBA/ops approval required before execution.
- Real Stripe production verification: payments owner approval required.
- Canary start: release manager approval required.

## Rollback pointer

If any release gate fails, do not tag production. Use [runbooks/canary_run.md](../runbooks/canary_run.md) for rollback once traffic has shifted.
