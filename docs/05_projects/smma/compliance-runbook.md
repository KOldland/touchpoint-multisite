# SMMA Compliance Runbook

## Purpose

Provide a practical operating guide for compliance evaluation in the SMMA pipeline,
including rule definitions, severity levels, demo verification steps, and
troubleshooting guidance.

## Scope

This runbook covers compliance behavior for:

- generated variants
- edited variant revisions
- scheduling eligibility gates

It does not cover sponsor approval UI implementation or paid adapter execution.

## Rule Definitions

Deterministic phrase rules are defined in:

- `app/public/wp-content/plugins/kh-smma/src/Compliance/BannedPhraseRules.php`

Rule engine orchestration is implemented in:

- `app/public/wp-content/plugins/kh-smma/src/Compliance/ComplianceRuleEngine.php`
- `app/public/wp-content/plugins/kh-smma/src/Compliance/ComplianceService.php`

Default blocked phrase rules include:

- `guaranteed results`
- `risk-free returns`
- `unlimited growth`
- `guaranteed leads`

Decision precedence:

1. Deterministic rule match that is configured as block -> `FAIL`
2. AI contextual review may classify borderline language as `WARN`
3. Otherwise -> `OK`

Deterministic `FAIL` is authoritative and cannot be downgraded by AI review.

## Severity Levels

| Severity | Meaning | Scheduling Behavior | Operator Action |
|---|---|---|---|
| `OK` | compliant output | scheduling allowed | proceed normally |
| `WARN` | questionable language/context | schedule may be created as pending approval | send through sponsor/admin approval flow |
| `FAIL` | banned or restricted content detected | scheduling blocked | revise content and rerun compliance |

## Stored Compliance Metadata

Compliance metadata is persisted with variant state/revisions and read by scheduling
gates:

- `compliance_status`
- `compliance_reason`
- `matched_rules`
- `ai_review_summary`
- `checked_at`

## Demo Steps (Compliance Focus)

### 1) Generate a variant

- Call `POST /wp-json/kh-smma/v1/generate`
- Confirm variant payload includes compliance fields.

Expected:

- telemetry emitted for generation request/response
- variant includes compliance metadata fields

### 2) Trigger a `FAIL` case

- Edit variant text to include a banned phrase (for example `guaranteed results`).
- Call variant edit endpoint (`POST /wp-json/kh-smma/v1/variant/{variant_id}/edit`).

Expected:

- compliance reevaluates to `FAIL`
- scheduling attempt returns block error (`COMPLIANCE_FAIL`)
- audit entry records failed compliance gate

### 3) Trigger a `WARN` case

- Edit variant to include borderline claim wording without deterministic banned phrase.
- Save edit and re-run compliance via edit flow.

Expected:

- compliance status becomes `WARN`
- schedule create returns pending approval path
- approval metadata persists on schedule (`approval_required=true`, `approval_status=pending`)

### 4) Validate `OK` path

- Edit variant to neutral compliant text.
- Re-save and verify compliance returns `OK`.

Expected:

- scheduling allowed
- schedule created with approved/normal path

## Operator Guidance

- Treat deterministic `FAIL` as immediate hard block.
- Do not override blocked output in dispatch flow.
- For `WARN`, route to sponsor/admin approval and ensure approval metadata is
  present before dispatch eligibility checks.
- If compliance output appears inconsistent, verify fixture/test mode settings
  before investigating live behavior.

## Troubleshooting

| Issue | Symptoms | Likely Cause | Resolution |
|---|---|---|---|
| Unexpected `FAIL` | schedule blocked with compliance error | banned phrase rule matched | inspect `matched_rules`, remove restricted language, save new revision |
| Expected `WARN` returned `OK` | no approval gate applied | AI compliance checker not active or input changed | verify compliance service config and submitted text |
| Missing compliance metadata | UI badge absent or stale | stale variant payload or revision persistence issue | confirm latest revision saved and repository returns compliance fields |
| Schedule blocked despite approval | dispatch still blocked | schedule still `pending`/`rejected` | confirm `approval_status=approved` on the schedule record |

## Quick Validation Commands

From plugin root (`app/public/wp-content/plugins/kh-smma`):

```bash
vendor/bin/phpunit tests/SMMA/ComplianceServiceTest.php
vendor/bin/phpunit tests/SMMA/ComplianceSchedulingTest.php
vendor/bin/phpunit tests/SMMA/WorkflowSmokeTest.php
```

For deterministic local mode:

```bash
export KH_SMMA_TEST_MODE=ci
```

## Related Docs

- `docs/smma/compliance.md`
- `docs/smma/scheduling.md`
- `docs/smma/demo-runbook.md`
- `docs/smma/testing.md`
