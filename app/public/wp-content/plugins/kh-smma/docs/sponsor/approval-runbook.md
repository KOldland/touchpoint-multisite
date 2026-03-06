# Sponsor Approval Runbook (SA-09)

## Purpose

This runbook defines the day-to-day operating procedure for Sponsor Approval workflows so administrators and sponsor managers can consistently review, approve, reject, and reopen schedules.

## Approval Workflow

### Step 1 — Review Pending Approvals

Navigate to:

- Admin → Sponsor Approvals → Pending

The list shows schedules requiring review.

Displayed fields:

- `schedule_id`
- `sponsor`
- `compliance_status`
- `approval_reason`
- `created_at`

Badges indicate approval context:

- `Pending`
- `Re-review: Compliance Change`
- `Re-review: Claim Permission Change`

### Step 2 — Inspect Schedule Content

Open the schedule detail page and review:

- variant text
- asset hints
- compliance result
- rationale

Confirm claims and messaging align with sponsor guidelines before deciding.

### Step 3 — Approve or Reject

Available actions:

- Approve
- Reject

Approve effect:

- `approval_status = approved`
- `approved_by` is recorded
- `approved_at` is recorded
- schedule becomes dispatch-eligible

Reject effect:

- `approval_status = rejected`
- `rejected_by` is recorded
- `rejected_at` is recorded
- schedule is removed from active pending queue

## Common Reasons to Reject

### Compliance concerns

Examples:

- unsupported product claims
- restricted medical or financial language
- misleading messaging

### Sponsor brand guideline mismatch

Examples:

- incorrect tone
- brand voice mismatch
- prohibited phrasing

### Messaging quality issues

Examples:

- unclear messaging
- missing value proposition
- incorrect targeting

## Reopening a Schedule (Re-review)

Schedules may require re-review when:

- compliance rules change
- sponsor `allowed_claims` change
- variant content is edited

Reopen procedure:

1. Edit the variant.
2. Re-run compliance.
3. Confirm schedule returns to `approval_status = pending`.

UI indicator:

- `Re-review Required`

## Role Requirements

### Site Administrator

Capabilities:

- approve any schedule
- reject any schedule
- view all sponsors

Capability check:

- `manage_options`

### Sponsor Manager

Capabilities:

- approve schedules belonging to their sponsor
- reject schedules belonging to their sponsor

Restriction:

- cannot approve schedules for other sponsors

Ownership is verified from:

- `schedule.sponsor_id`

## Sponsor Scope Rules

Sponsor managers are scoped to:

- their sponsor schedules
- their sponsor pending approvals
- their sponsor approval history

Scope enforcement points:

- `ApprovalPermissionService`
- `SponsorApprovalController`
- `PendingApprovalsPage`

## Troubleshooting

### Schedule not appearing in Pending Approvals

Possible causes:

- `approval_required = false`
- `approval_status != pending`

Checks:

- schedule metadata
- compliance result

### Cannot approve schedule

Possible causes:

- `compliance_result == FAIL`
- user lacks approval permission

API error:

- `COMPLIANCE_FAIL_APPROVAL_BLOCKED`

Resolution:

1. Edit variant content.
2. Re-run compliance.
3. Submit for approval again.

### Approval backlog alerts

Triggered when:

- warning: pending approvals > 10
- critical: pending approvals > 25

Alert event:

- `alert.approval_backlog`

Operational check:

- observability dashboard alert stream and approval queue depth panels

## Telemetry Reference

Approval lifecycle events:

- `sponsor.approval.requested`
- `sponsor.approval.approved`
- `sponsor.approval.rejected`

These events power:

- approval metrics
- backlog alerts
- reject-rate monitoring
