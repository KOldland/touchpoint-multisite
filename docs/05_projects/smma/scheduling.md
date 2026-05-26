# SMMA Scheduling

## Approval Enforcement

Dispatch/export eligibility is gated by approval metadata:

- `approval_required`
- `approval_status`

Dispatch is allowed only when:

- `approval_required = false`, or
- `approval_status = approved`

Dispatch is blocked when:

- `approval_required = true` and `approval_status = pending`
- `approval_status = rejected`

Blocked response shape:

```json
{
  "status": "blocked",
  "reason": "approval_required",
  "approval_status": "pending"
}
```

## Queue State Mapping

Queue visibility labels:

- pending approval → `Awaiting Approval`
- approved → `Ready`
- rejected → `Rejected`

## Re-evaluation

When approval changes from pending to approved:

- schedule eligibility is re-evaluated
- if schedule time has passed, status becomes `queued_for_execution`
- otherwise status remains queued for the scheduled time

Rejected schedules remain stored and blocked from automatic retry.

## Telemetry

Blocked attempts emit:

- `schedule.blocked`

Payload includes:

- `trace_id`
- `schedule_id`
- `reason=approval_required`
- `approval_status`
- `timestamp`
