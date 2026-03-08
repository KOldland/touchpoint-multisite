# Approval Safety (SA-06)

## Purpose

SA-06 prevents stale or unsafe approvals from being executed when compliance outcomes or sponsor claim permissions change after a schedule was approved.

## Safety Triggers

### 1) Compliance change re-review

If an approved schedule's compliance state changes, approval is automatically invalidated.

Trigger conditions:
- `compliance_status` changed from `last_approved_compliance_status`
- OR `ruleset_version` changed from `last_approved_ruleset_version`

State update:
- `approval_status = pending`
- `approval_required = true`
- `approval_reason = compliance_changed`

### 2) Sponsor claim permission change re-review

If sponsor `allowed_claims` removes claims still referenced by approved schedules, impacted schedules are re-reviewed.

Detection:
- Compare previous vs current allowed claims
- Find schedules containing removed claims
- Mark impacted schedules for re-review

State update:
- `approval_status = pending`
- `approval_required = true`
- `approval_reason = sponsor_claim_change`

## Compliance FAIL approval blocking

Approvals are blocked when schedule compliance is `FAIL`.

API error:
- `COMPLIANCE_FAIL_APPROVAL_BLOCKED`
- Message: `Schedules with compliance FAIL cannot be approved. Variant must be edited and pass compliance before approval.`

UI behavior:
- Approve button is disabled for FAIL rows
- Tooltip/message: `Compliance failure detected. Variant must be edited and pass compliance before approval.`

## Impacted Schedule Detection

Repository method:
- `findSchedulesImpactedByClaimChange( sponsor_id, removed_claims )`

Returns:
- `schedule_id`
- `sponsor_id`
- `variant_id`
- `approval_status`
- `compliance_status`

## Admin Visibility

Pending approvals table surfaces:
- `approval_reason`
- `last_approved_by`
- `last_approved_at`

Re-review badges:
- `Re-review: Compliance Change`
- `Re-review: Claim Permission Change`

## Audit Events

- `schedule.re_review_required`
- `approval.blocked_compliance_fail`
- `schedule.claim_permission_change`

Required fields:
- `schedule_id`
- `actor`
- `reason`
- `timestamp`

## Telemetry Events

- `sponsor.approval.revoked`
- `approval.blocked`
- `schedule.re_review_required`

Payload fields:
- `trace_id`
- `schedule_id`
- `reason`
- `timestamp`

Reviewer notes are excluded from telemetry payloads.
