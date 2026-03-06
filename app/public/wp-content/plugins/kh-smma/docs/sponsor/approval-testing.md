# Sponsor Approval Testing (SA-08)

## Scope

SA-08 adds QA coverage for sponsor approval workflow behavior without changing runtime logic.

Covered areas:

- Approval decision persistence metadata and audit logging.
- WARN schedule visibility in pending approvals.
- Dispatch eligibility transition from blocked (`pending`) to allowed (`approved`).
- Smoke path coverage for approval + manual export bundle availability + telemetry sequence.

## Test Files

- `tests/SA/ApprovalPersistenceTest.php`
- `tests/SA/ApprovalWorkflowIntegrationTest.php`
- `tests/SA/ApprovalWorkflowSmokeTest.php`
- Fixture: `tests/fixtures/sponsor/approval_workflow_cases.json`

## What Each Test Verifies

### ApprovalPersistenceTest

- `approveSchedule()` updates approval metadata (`approved_by`, `approved_at`, `approval_required=0`, compliance snapshot).
- `rejectSchedule()` updates rejection metadata (`rejected_by`, `rejected_at`).
- Decision audit entries include expected `review_notes` in logger payload.

### ApprovalWorkflowIntegrationTest

- A `WARN` schedule with `approval_status=pending` remains visible in the approval queue.
- `DispatchEligibilityService` blocks pending schedules with `APPROVAL_REQUIRED`.
- After sponsor approval, dispatch eligibility is recalculated as allowed with queue label `Ready`.

### ApprovalWorkflowSmokeTest

- End-to-end QA path through:
  1. `schedule.create` telemetry signal.
  2. sponsor approval transition.
  3. manual export bundle create/download API path.
  4. `schedule.dispatch` telemetry signal.
- Telemetry sequence ordering includes `schedule.create` → `sponsor.approval.approved` → `export.bundle.created` → `schedule.dispatch`.

## Run

```bash
vendor/bin/phpunit tests/SA
```

## Notes

- Tests are deterministic and fixture-driven.
- SA-08 intentionally avoids runtime behavior changes and only extends QA assets.
