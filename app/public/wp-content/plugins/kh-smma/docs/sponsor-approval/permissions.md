# Sponsor Approval Permissions (SA-05)

## Overview

SA-05 introduces role-based authorization for Sponsor Approval actions while preserving existing workflow and persistence semantics.

The permission model enforces access in both UI and API paths:

- Site Admin users can review and approve/reject any sponsor's schedules.
- Sponsor Manager users can only review and approve/reject schedules for their assigned sponsor.
- All other users can view only what they are allowed to access and cannot execute approval actions.

## Capability Model

Permission checks are implemented by `ApprovalPermissionService`.

### Site Admin

- Capability: `manage_options`
- Access: Full access across all sponsors

### Sponsor Manager

- Capabilities: `manage_sponsors` OR `edit_schedules`
- Required scope: assigned sponsor ID via user meta (`assigned_sponsor_id`)
- Access: Only schedules where `schedule.sponsor_id === assigned_sponsor_id`

### Unauthorized Users

- Access: Cannot approve/reject schedules
- UI: Action controls hidden or disabled with explanatory tooltip/message
- API: Returns `403` with `APPROVAL_PERMISSION_DENIED`

## Enforcement Points

### API Controller

`src/API/SponsorApprovalController.php`

- `list_schedules()`
  - Applies sponsor scope using `enforce_sponsor_scope()`.
  - Adds per-row `can_approve` and `permission_message`.
- `approve_schedules()` and `reject_schedules()`
  - Performs per-schedule authorization before persistence.
  - Mixed bulk payloads return partial summary:
    - `approved` (or rejected equivalent)
    - `skipped`
    - `errors[]` with `permission_denied` entries

### Admin UI

`src/Admin/PendingApprovalsPage.php`, `src/Admin/ApprovalListTable.php`, `assets/js/sponsor-approval.js`

- Filters initial page query by assigned sponsor for sponsor managers.
- Restricts row/bulk controls when user is out of scope.
- Propagates server permissions to client rendering (`khSmmaApproval.permissions`).
- Shows a consistent denial message: `You do not have permission to approve schedules for this sponsor.`

## Audit & Telemetry

Permission-denied attempts are tracked for observability and compliance.

- Audit event: `sponsor.approval.permission_denied`
- Telemetry event: `kh_smma_telemetry_event` with name `sponsor.approval.permission_denied`
- Payload includes:
  - `schedule_id`
  - `reviewer_user_id`
  - `status` (requested transition)
  - `reason`

## Notes

- SA-05 does not change approval status lifecycle rules.
- SA-05 does not alter compliance dispatch, scheduling semantics, or persistence transitions.
