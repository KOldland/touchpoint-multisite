# SMMA Demo Runbook

## Purpose

This runbook covers the full SMMA demo workflow from generation to export, with
expected outcomes and verification points.

## Prerequisites

- WordPress local environment running with `kh-smma` enabled
- Authenticated editor/admin user
- SMMA REST routes available under `/wp-json/kh-smma/v1`
- Deterministic test mode optional for local demo checks:
  - `export KH_SMMA_TEST_MODE=ci`

## Demo Flow

### Step 1: Generate Variants

Trigger:

- `POST /wp-json/kh-smma/v1/generate`

Expected:

- variants returned in response
- telemetry events:
  - `generate.request`
  - `generate.response`

Verify:

- variants visible in editor grid
- response contains `request_id` and `variants[]`

### Step 2: Edit Variant

Action:

- edit one variant in editor UI (or call variant edit API)

Expected:

- `variant.edit` telemetry event
- revision persisted with editor metadata

Verify:

- revision visible in variant history
- response includes `revision_id`

### Step 3: Compliance Check

Action:

- compliance evaluated during generate/edit flow

Outcomes:

- `OK` -> scheduling allowed
- `WARN` -> `approval_required=true`
- `FAIL` -> scheduling blocked

Verify:

- compliance badge and reason visible
- FAIL variants cannot be scheduled

### Step 4: Schedule Variant

Trigger:

- `POST /wp-json/kh-smma/v1/schedule`

Expected:

- `schedule.create` telemetry event
- schedule persisted with approval/compliance metadata

Verify:

- schedule appears in admin queue/list views
- response includes `schedule_id`

### Step 5: Approval (if required)

Condition:

- schedule created with pending approval (typically WARN compliance path)

Action:

- approve schedule via approval workflow endpoint/UI

Expected:

- `approval_status=approved`

Verify:

- schedule becomes dispatch-eligible
- queue label updates to `Ready`

### Step 6: Dispatch / Export

Action:

- trigger manual export flow

Expected:

- export bundle generated
- zip bundle metadata available for download

Verify bundle contents:

- `manifest.json`
- `variant_text.txt`
- zip file `schedule_export_{schedule_id}.zip`

## Telemetry Verification

Confirm these events in observability/audit views:

- `generate.request`
- `generate.response`
- `variant.edit`
- `schedule.create`
- `schedule.dispatch`

## CI / Smoke Verification for Demo Confidence

- run smoke test:
  - `vendor/bin/phpunit tests/SMMA/WorkflowSmokeTest.php`
- expected result:
  - `PASS`
