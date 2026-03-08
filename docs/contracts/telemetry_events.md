# Telemetry Event Registry

> **OBS-CARD-01** — Canonical event names and payload schemas.
> Do not invent event names outside this registry.  To add a new event,
> update this file in the same PR.
>
> **OBS-05** — Contract validation is automated in CI.  Any change to
> required fields must also update `tests/fixtures/telemetry/event_contracts.json`
> and `docs/contracts/telemetry_events_schema.json`.  The `telemetry-contract-check`
> CI job enforces this on every PR.

---

## Base envelope (all events)

```json
{
  "event_name": "<string>",
  "trace_id":   "<uuid-v4>",
  "timestamp":  "<unix-int>",
  "service":    "smma | mem"
}
```

---

## SMMA events

### `generate.request`
- **service:** `smma`
- **trigger:** POST `/kh-smma/v1/generate` received
- **payload fields:** `session_id`, `prompt_hash`, `variant_count_requested`
- **example:**
  ```json
  {
    "event_name": "generate.request",
    "trace_id": "d4e8f2a0-1234-4abc-8def-000000000001",
    "timestamp": 1741228800,
    "service": "smma",
    "session_id": "req_abc123",
    "prompt_hash": "e3b0c44...",
    "variant_count_requested": 2
  }
  ```

### `generate.response`
- **service:** `smma`
- **trigger:** Generator returns variants
- **payload fields:** `session_id`, `variant_count_generated`, `latency_ms`

### `compliance.check`
- **service:** `smma`
- **trigger:** Any compliance evaluation (generate-time or edit-time)
- **payload fields:** `variant_id`, `outcome` (`OK|WARN|FAIL`), `rules_matched`

### `variant.edit`
- **service:** `smma`
- **trigger:** POST `/kh-smma/v1/variant/{id}/edit` succeeds
- **payload fields:** `variant_id`, `editor_id`, `revision_id`, `deltas`

### `schedule.create`
- **service:** `smma`
- **trigger:** POST `/kh-smma/v1/schedule` creates a schedule row
- **payload fields:** `schedule_id`, `sponsor_id`, `approval_required`

### `schedule.dispatch`
- **service:** `smma`
- **trigger:** `kh_smma_schedule_status_changed` action fires after queue dispatch
- **payload fields:** `schedule_id`, `adapter`, `result` (`dispatched|exported|failed`)

---

## MEM events

### `membership.signup`
- **service:** `mem`
- **trigger:** `do_action('kh_mem_signup', $data)` from MEM signup flow
- **payload fields:** `user_id`, `tier`, `payment_status`, `attribution_id`
- **PII note:** `user_id` is an opaque integer — no email, name, or credentials.

### `promotion_attribution`
- **service:** `mem`
- **trigger:** `do_action('kh_mem_attribution', $data)` from MEM attribution flow
- **payload fields:** `schedule_id`, `sponsor_id`, `utm_source`, `utm_campaign`, `confidence_score`

---

## OBS events

### `alert.triggered`
- **service:** `obs`
- **trigger:** `AlertEvaluator::evaluate()` detects a threshold breach
- **payload fields:** `alert_type`, `severity` (`warning|critical`), plus type-specific context fields
- **alert types:** `compliance_fail_rate`, `queue_backlog`, `dispatch_errors`
- **consumer:** `ObservabilityDashboardPage` (active alerts panel)

---

## Contract validation (OBS-05)

### Machine-readable fixtures

| File | Purpose |
|------|---------|
| `tests/fixtures/telemetry/event_contracts.json` | Required keys per event type; drives `EventContractTest` data providers |
| `docs/contracts/telemetry_events_schema.json` | JSON Schema `$defs` for full envelope validation |

### CI enforcement — `telemetry-contract-check` job

The `telemetry-contract-check` job in `smma-ci.yml` runs on every PR that touches the SMMA plugin and:

1. **Validates `event_contracts.json`** — JSON syntax, `events` and `base_envelope` keys present.
2. **Validates `telemetry_events_schema.json`** — JSON syntax, `$defs` key present.
3. **Checks registry coverage** — all 8 canonical events must appear in `event_contracts.json`.
4. **Runs the full Telemetry test suite** — `tests/Telemetry/` including `EventContractTest`, `EventPipelineIntegrationTest`, and `EventSmokeTest`.
5. **Checks base envelope consistency** — `base_envelope.required` must match between fixture and schema.

### Adding a new event

1. Emit the event via `EventEmitter::emit()` in the relevant service.
2. Add the event to `tests/fixtures/telemetry/event_contracts.json` with its `required` keys, `types`, and `example`.
3. Add a `$defs` entry in `docs/contracts/telemetry_events_schema.json`.
4. Add an explicit named test in `EventContractTest::test_{event_name}_required_keys()`.
5. Add the event name to the `test_all_registry_events_covered_by_fixture()` registry list.
6. Update this file.

---

## Future events (reserved — not yet implemented)

| Event name | Planned bucket |
|---|---|
| `paid.manifest.dry_run` | PAID |
| `paid.manifest.execute` | PAID |
| `paid.reconciliation.run` | PAID |
