# PM Sign-off Checklist (MEM-07)

Use this checklist in the PR before final PM approval.

## Documentation completeness

- [ ] Developer guide covers signup-init, webhook, landing-success, DSAR, reconciliation.
- [ ] At least one full flow example (landing -> checkout -> webhook -> membership) is present.
- [ ] Error mapping table (`MBR_ERR_*`) includes friendly message, retryable flag, UI mapping.
- [ ] Accessibility checklist and expected attributes are documented.
- [ ] Contracts and golden fixtures are linked from docs.

## QA reproducibility

- [ ] Staging runbook followed end-to-end at least once.
- [ ] Evidence attached using [staging_evidence_template.md](staging_evidence_template.md).
- [ ] Playwright/axe pass output attached.
- [ ] DB/audit artifacts attached (`db_before.sql`, `db_after.sql`, `audit_log.txt`, etc).

## Ops readiness

- [ ] Ops runbook contains DLQ replay, retention, anonymize, DSAR, reconciliation operations.
- [ ] Alert actions are documented for required signals.
- [ ] Salt rotation steps and impact are documented.
- [ ] Rollback and post-mortem checklist present.

## Legal/compliance

- [ ] Required legal note included verbatim:

> Legal has reviewed DSAR flows and approves anonymize-by-default; deletion requires sign-off.

- [ ] Legal/compliance ticket linked: `COMP-____`
- [ ] DSAR export retention statement reviewed and accepted.

## Final approval

- PM approver:
- Date:
- Notes:
