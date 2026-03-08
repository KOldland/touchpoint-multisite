# Membership Docs & Runbooks (MEM-07)

This directory is the canonical handover pack for builders, QA, and ops working on membership flow delivery:

`landing -> signup-init -> Stripe checkout -> webhook -> membership -> DSAR/retention -> reconciliation`

## Start here

- Builder/API implementation: [developer_guide.md](developer_guide.md)
- Staging deterministic E2E: [staging_e2e_runbook.md](staging_e2e_runbook.md)
- Ops production procedures: [ops_runbook.md](ops_runbook.md)
- Release rollout + rollback: [release_runbook.md](release_runbook.md)
- Post-release acceptance: [post_release_checklist.md](post_release_checklist.md)
- Performance and scaling runbook: [perf_runbook.md](perf_runbook.md)
- Disaster recovery runbook: [dr_runbook.md](dr_runbook.md)
- SLO definitions and breach policy: [slo.md](slo.md)
- Command quick reference: [commands.md](commands.md)
- Failure triage and rollback: [troubleshooting.md](troubleshooting.md)
- Privacy/legal controls: [privacy_and_retention.md](privacy_and_retention.md)

## Required templates

- PM sign-off: [pm_signoff_checklist.md](pm_signoff_checklist.md)
- Staging evidence paste-in: [staging_evidence_template.md](staging_evidence_template.md)

## Canonical contracts and fixtures

Use these as source of truth in implementation and tests:

- Signup init contract: [../contracts/signup-init.json](../contracts/signup-init.json)
- Landing success contract: [../contracts/landing_success.json](../contracts/landing_success.json)
- Membership attribution schema: [../contracts/membership_attribution.json](../contracts/membership_attribution.json)
- Membership scaling index plan: [../contracts/membership_scaling_indexes.json](../contracts/membership_scaling_indexes.json)
- Paid adapter manifest: [../contracts/paid_adapter_manifest.json](../contracts/paid_adapter_manifest.json)
- Paid reconciliation schema: [../contracts/paid_reconciliation.json](../contracts/paid_reconciliation.json)
- CIC golden contract: [../contracts/cic-01-golden-contract.json](../contracts/cic-01-golden-contract.json)
- Golden fixture inventory: [../contracts/golden-fixtures.md](../contracts/golden-fixtures.md)

Any contract/fixture changes must be mirrored in these docs in the same PR.
