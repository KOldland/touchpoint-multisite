# Sponsor Approval UI (SA-01)

## Access

- WordPress admin route: `/wp-admin/admin.php?page=smma-pending-approvals`
- Menu path: `KH Social` → `Pending Approvals`
- Required permissions (any one):
  - `manage_sponsors`
  - `edit_schedules`
  - `administrator`

## Default View

- The table defaults to **pending** schedules only.
- Columns:
  - `schedule_id`
  - `post_title`
  - `sponsor_name`
  - `submitter`
  - `requested_schedule_date`
  - `approval_status`

## Filters

- Sponsor dropdown
- Status (`pending`, `approved`, `rejected`, `all`)
- Date range (`from`, `to`)
- Search (by `schedule_id` or `post_title`)

Search and filter updates are handled through async list refresh from the sponsor approvals API.

## Review Actions

- Per-row actions:
  - `Approve`
  - `Reject`
- Both actions open a review modal containing:
  - reviewer notes textarea
  - confirm
  - cancel

## Bulk Review

- Select rows using checkboxes.
- Bulk controls:
  - `Approve Selected`
  - `Reject Selected`
- Bulk uses the same review modal; reviewer notes are optional.

## Telemetry

- Event emitted when review modal opens:
  - `sponsor.approval.review_started`
- Payload:
  - `schedule_id`
  - `reviewer_user_id`
  - `timestamp`

Approval/rejection persistence is intentionally out-of-scope for SA-01 and is implemented in later cards.

## SA-02 Approval Persistence

- Approval decisions are persisted on schedule meta.
- Approve writes:
  - `_kh_smma_approval_status=approved`
  - `_kh_smma_approved_by`
  - `_kh_smma_approved_at`
  - `_kh_smma_review_notes`
- Reject writes:
  - `_kh_smma_approval_status=rejected`
  - `_kh_smma_rejected_by`
  - `_kh_smma_rejected_at`
  - `_kh_smma_review_notes`

### State Transitions

- Allowed:
  - `pending -> approved`
  - `pending -> rejected`
- Blocked:
  - `approved -> rejected`
  - `rejected -> approved` (requires manual reset)

Invalid transitions return structured API errors and do not mutate metadata.

Example invalid transition response:
- `error: INVALID_APPROVAL_TRANSITION`

### Audit Entries

- Approval decision: `sponsor.approval.approved`
- Rejection decision: `sponsor.approval.rejected`

Each audit entry includes:
- `trace_id`
- `schedule_id`
- `reviewer_id`
- `review_notes`
- `timestamp`

### Telemetry Entries

- `sponsor.approval.approved`
- `sponsor.approval.rejected`

Telemetry payload includes:
- `trace_id`
- `schedule_id`
- `reviewer_id`
- `timestamp`

Reviewer notes are intentionally excluded from telemetry and retained in audit logs only.

## SA-03 Approval Audit & History

### Schedule Detail Page

- Admin route: `/wp-admin/admin.php?page=kh-smma-schedule-detail&schedule_id={id}`
- Displays read-only schedule context and an **Approval History** timeline.
- Timeline events are shown latest-first.

### History Source

- REST endpoint: `GET /wp-json/kh-smma/v1/sponsor-approvals/history?schedule_id={id}`
- Timeline rows are sourced from audit events for the schedule:
  - `sponsor.approval.review_started` → `submitted`
  - `sponsor.approval.approved` → `approved`
  - `sponsor.approval.rejected` → `rejected`
- Returned event payload includes:
  - `event`
  - `action`
  - `trace_id`
  - `schedule_id`
  - `reviewer_id`
  - `timestamp`
  - `notes`

### History View Telemetry

- Event emitted when history is viewed:
  - `sponsor.approval.history_viewed`
- Telemetry payload includes:
  - `trace_id`
  - `schedule_id`
  - `viewer_user_id`
  - `timestamp`

Reviewer notes are excluded from telemetry and remain available only in audit history.

## SA-04 Approval Notifications

### Trigger Point

- Notification dispatch is side-effect-only and runs **after** approval persistence completes.
- Source event hook:
  - `kh_smma_sponsor_approval_decision_persisted`
- Notification service:
  - `src/Notifications/ApprovalNotificationService.php`

Approval state transitions and persistence rules are unchanged by SA-04.

### Notification Channels

- In-app notifications:
  - Stored on schedule meta (`_kh_smma_in_app_notifications`)
  - Visible on Schedule Detail page under **In-App Notifications**
  - Fields:
    - `schedule_id`
    - `decision`
    - `reviewer_id`
    - `timestamp`
- Email notifications:
  - Sent to schedule owner and editor
  - Optional sponsor contact recipient when configured

### Email Templates

- Approval:
  - Subject: `Schedule Approved`
  - Body includes:
    - `Schedule ID`
    - `Reviewer`
    - `Approved At`
    - dispatch eligibility note
- Rejection:
  - Subject: `Schedule Rejected`
  - Body includes:
    - `Schedule ID`
    - `Reviewer`
    - `Reason` (review notes)
    - revise/resubmit guidance

### Notification Telemetry

- Approval notification event:
  - `sponsor.notification.approval_sent`
- Rejection notification event:
  - `sponsor.notification.rejection_sent`

Telemetry payload:
- `trace_id`
- `schedule_id`
- `recipient_type`
- `timestamp`

No PII is included in telemetry payloads.

### Notification Audit Events

- Audit event:
  - `sponsor.notification.sent`

Audit details include:
- `schedule_id`
- `notification_type`
- `recipient_type`
- `timestamp`
- `trace_id`

### Duplicate Suppression

- Duplicate replay suppression uses an idempotency guard keyed by:
  - `schedule_id`
  - `decision`
  - `trace_id`
- If the same event replay is received again, notification side effects are skipped.
