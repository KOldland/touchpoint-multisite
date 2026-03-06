# Sponsor Approval Observability (SA-07)

See also:
- `docs/sponsor/approval-runbook.md` for day-to-day approval operations, permissions, and troubleshooting workflows.

## Purpose

SA-07 adds structured telemetry and audit coverage for sponsor approval workflow actions so approvals are monitorable, traceable, and alertable.

## Lifecycle Telemetry Events

### sponsor.approval.requested

Emitted when a schedule enters pending approval state (including re-review transitions).

Payload:
- `trace_id`
- `schedule_id`
- `sponsor_id`
- `compliance_status`
- `approval_reason`
- `timestamp`

### sponsor.approval.approved

Emitted when a schedule is approved.

Payload:
- `trace_id`
- `schedule_id`
- `sponsor_id`
- `reviewer_user_id`
- `timestamp`

### sponsor.approval.rejected

Emitted when a schedule is rejected.

Payload:
- `trace_id`
- `schedule_id`
- `sponsor_id`
- `reviewer_user_id`
- `timestamp`

Reviewer notes are not sent in telemetry payloads.

## Audit Events

Approval lifecycle actions are also persisted via `AuditLogger::record_event()` for forensics and compliance:

- `sponsor.approval.requested`
- `sponsor.approval.approved`
- `sponsor.approval.rejected`

Audit payload includes:
- `schedule_id`
- `sponsor_id`
- `reviewer_user_id`
- `timestamp`
- `review_notes`

## Alert Signals

### Approval Backlog Alert

Telemetry event: `alert.approval_backlog`

Thresholds:
- `WARNING` when pending approvals > 10
- `CRITICAL` when pending approvals > 25

Payload:
- `trace_id`
- `pending_count`
- `severity`
- `timestamp`

### Sponsor Reject Spike Alert

Telemetry event: `alert.sponsor_reject_spike`

Evaluation window:
- last 10 sponsor decisions

Trigger:
- reject rate > 60%

Payload:
- `trace_id`
- `sponsor_id`
- `reject_count`
- `approval_count`
- `timestamp`

## Dashboard Metrics

Derived metrics for observability dashboard:
- pending approvals
- approval rate
- reject rate
- average approval latency

Source events:
- `sponsor.approval.requested`
- `sponsor.approval.approved`
- `sponsor.approval.rejected`

## Trace Correlation

All approval telemetry includes `trace_id`.

Trace is propagated from incoming approval requests where available (`X-Trace-Id`) and generated when absent.
