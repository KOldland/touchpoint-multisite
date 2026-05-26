# OBS — Event Emission & Trace Correlation

## Overview

This document describes the telemetry event emission system introduced in **OBS-CARD-01**.
It is an additive, non-blocking instrumentation layer. It does not change any
business logic in SMMA, MEM, or PAID.

---

## Core classes

| Class | Location | Role |
|---|---|---|
| `TraceContext` | `src/Telemetry/TraceContext.php` | Static holder for the current `trace_id` |
| `EventEmitter` | `src/Telemetry/EventEmitter.php` | Standardises, persists, and publishes events |
| `AnalyticsFeedbackService` | `src/Telemetry/AnalyticsFeedbackService.php` | Aggregates event metrics into rolling snapshots (OBS-02) |
| `MetricsSnapshotRepository` | `src/Telemetry/MetricsSnapshotRepository.php` | Persists / retrieves snapshot rows from DB (OBS-02) |

---

## Trace / Correlation ID

Every workflow entry point calls `TraceContext::init()` with a UUID v4 (auto-generated
or supplied by the caller via `X-Trace-Id` request header). All events emitted during
that request automatically receive the same `trace_id`.

```
generate.request
→ generate.response
→ compliance.check
→ variant.edit
→ schedule.create
→ schedule.dispatch
```

All share the same `trace_id`.

**UUID v4 format:** `xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx`

---

## Event envelope

Every emitted event has this base shape:

```json
{
  "event_name": "<canonical name>",
  "trace_id":   "<uuid-v4>",
  "timestamp":  1741228800,
  "service":    "smma | mem",
  "<event-specific fields>": "..."
}
```

---

## Audit persistence model (OBS-02)

Every event emitted by `EventEmitter` is persisted **synchronously** to
`wp_kh_smma_audit_log` via `AuditLogger::record_event()` before the async
publish path runs. This is the audit-first fallback.

Stored fields per event:

| Audit column | Value |
|---|---|
| `action` | `telemetry_event` |
| `object_type` | `telemetry` |
| `user_id` | Current WordPress user ID (actor) |
| `details.trace_id` | Correlation ID |
| `details.event_name` | Canonical event name |
| `details.timestamp` | Unix timestamp |
| `details.payload` | Full event envelope (PII-safe) |

For `compliance.check` events the payload additionally carries:
`variant_id`, `outcome`, `rules_matched`, `ai_review_summary`.

---

## Analytics aggregation flow (OBS-02)

```
EventEmitter
     │  do_action('kh_telemetry_event', $event)
     ▼
Telemetry\AnalyticsFeedbackService::handle_event()
     │  accumulates counts / latency in WP option
     ▼
  [every 5 min — cron: kh_smma_analytics_flush]
flush_snapshot() → MetricsSnapshotRepository::write_snapshot()
     ▼
wp_kh_smma_analytics_snapshots
```

See [analytics_snapshots.md](analytics_snapshots.md) for the full schema,
metrics object shape, and privacy rules.

---

## Event registry

### `generate.request`

Emitted at the start of every `/generate` API call.

| Field | Type | Notes |
|---|---|---|
| `session_id` | string | Request ID (`req_*`) |
| `prompt_hash` | string | SHA-256 of the generation inputs |
| `variant_count_requested` | int | How many variants were requested |
| `service` | `"smma"` | |

---

### `generate.response`

Emitted when the generator returns variants.

| Field | Type | Notes |
|---|---|---|
| `session_id` | string | Matches `generate.request.session_id` |
| `variant_count_generated` | int | Actual variants returned |
| `latency_ms` | int | End-to-end generation time |
| `service` | `"smma"` | |

---

### `compliance.check`

Emitted on every compliance evaluation (generate-time and edit-time).

| Field | Type | Notes |
|---|---|---|
| `variant_id` | string | |
| `outcome` | `"OK" \| "WARN" \| "FAIL"` | |
| `rules_matched` | array | Matched rule IDs / phrases |
| `service` | `"smma"` | |

---

### `variant.edit`

Emitted when a variant is saved via `/variant/{id}/edit`.

| Field | Type | Notes |
|---|---|---|
| `variant_id` | string | |
| `editor_id` | string | User ID of editor |
| `revision_id` | string | |
| `deltas` | object | Change summary — not full text |
| `service` | `"smma"` | |

---

### `schedule.create`

Emitted when a schedule row is created via `/schedule`.

| Field | Type | Notes |
|---|---|---|
| `schedule_id` | string | |
| `sponsor_id` | string | |
| `approval_required` | bool | |
| `service` | `"smma"` | |

---

### `schedule.dispatch`

Emitted when `ScheduleQueueProcessor` completes dispatch (success or failure).
Triggered via the `kh_smma_schedule_status_changed` WordPress action.

| Field | Type | Notes |
|---|---|---|
| `schedule_id` | string | |
| `adapter` | string | Delivery mode (e.g. `"manual"`, `"linkedin"`) |
| `result` | `"dispatched" \| "exported" \| "failed"` | |
| `service` | `"smma"` | |

---

### `membership.signup`

Emitted when a successful membership signup occurs.
Fire `do_action('kh_mem_signup', $data)` from the MEM signup handler.

| Field | Type | Notes |
|---|---|---|
| `user_id` | int | Opaque internal ID — no PII |
| `tier` | string | `"standard"`, `"premium"`, etc. |
| `payment_status` | string | `"paid"`, `"trial"`, `"free"` |
| `attribution_id` | string | Promo / campaign ID |
| `service` | `"mem"` | |

---

### `promotion_attribution`

Emitted when a conversion attribution is recorded.
Fire `do_action('kh_mem_attribution', $data)` from the MEM attribution handler.

| Field | Type | Notes |
|---|---|---|
| `schedule_id` | string | |
| `sponsor_id` | string | |
| `utm_source` | string | |
| `utm_campaign` | string | |
| `confidence_score` | float | `0.0 – 1.0` |
| `service` | `"mem"` | |

---

## Audit persistence

Every event is persisted to `wp_kh_smma_audit_log` via `AuditLogger::record_event()`
before the async publish path runs. This ensures events remain queryable even
when the telemetry backend is unavailable.

---

## WordPress action hooks

| Hook | Payload | Purpose |
|---|---|---|
| `kh_telemetry_event` | `$event` (full envelope array) | Primary async publish hook |
| `kh_smma_telemetry_event` | `$event_name, $payload` | Back-compat shim |
| `kh_mem_signup` | `$data` | MEM fires to trigger `membership.signup` |
| `kh_mem_attribution` | `$data` | MEM fires to trigger `promotion_attribution` |

---

## Privacy guidelines

- **Never** include raw email addresses, names, passwords, or access tokens in event payloads.
- `user_id` is an opaque integer. Never serialize the full user object.
- `deltas` in `variant.edit` must be a summary (e.g. edit reason) — not the full text diff.
- UTM parameters (campaign-level) are safe to record.
- Compliance `rules_matched` lists rule IDs / matched phrases — not raw variant text.

---

## Retention

Telemetry audit records in `wp_kh_smma_audit_log` follow the standard audit
retention policy (OBS-CARD-08 will define automated expiry).

---

## Fixture locations

| Fixture | Purpose |
|---|---|
| `tests/fixtures/telemetry/generate_request_event.json` | Canonical `generate.request` shape |
| `tests/fixtures/telemetry/compliance_check_event.json` | Canonical `compliance.check` shape |
| `tests/fixtures/telemetry/trace_propagation_fixture.json` | Full 6-event workflow trace fixture |
| `tests/fixtures/telemetry/retry_cases.json` | Retry/backoff/fallback-buffer golden cases (OBS-06) |

---

## OBS-06: Non-Blocking Emission & Reliability

### Emission pipeline

```
emit(event_name, payload)
  │
  ├─ Step 1: AuditLogger::record_event()   ← synchronous, always runs, never sampled
  │
  ├─ Step 2: EventQueue::enqueue()         ← non-blocking in-memory append (<1 ms)
  │              │
  │              └─ PHP shutdown / kh_smma_telemetry_flush cron
  │                     │
  │                     └─ TelemetryRetryService::publish_with_retry()
  │                                │
  │                                ├─ attempt 1  (0 ms delay)
  │                                ├─ attempt 2  (250 ms on failure)
  │                                ├─ attempt 3  (1 000 ms on failure)
  │                                └─ all fail → wp_kh_smma_telemetry_buffer
  │
  └─ Step 3: do_action('kh_smma_telemetry_event', ...)  ← back-compat shim
```

Without a queue (backward-compat), `kh_telemetry_event` fires synchronously.

### Audit completeness guarantee

`AuditLogger::record_event()` fires **before** any queue or dispatch logic. It runs
even when the queue throws, the hook throws, or all retries are exhausted. Every emitted
event is always persisted to the audit log.

### Bounded FIFO queue

`EventQueue::MAX_SIZE = 1000`. When full, the **oldest** event is evicted so the newest
events are always retained.

### Debug field sampling

Fields `prompt_hash`, `asset_hint_details`, and `debug_metadata` are stripped from the
hook-dispatch payload at `DEFAULT_SAMPLE_RATE = 0.10` (10% retained).

Events with prefixes `schedule.`, `variant.edit`, `sponsor.approval`, `membership.`,
`alert.` are **never sampled** — always dispatched intact.

Audit records are never affected by sampling.

### Fallback buffer (`wp_kh_smma_telemetry_buffer`)

Events that exhaust all retry attempts are written to this table and replayed by the
`kh_smma_telemetry_replay` cron every 5 minutes. The table is created on plugin
activation by `TelemetryRetryService::install()`.

### Performance targets

| Operation | Target |
|-----------|--------|
| `emit()` total overhead | < 5 ms |
| `enqueue()` only | < 1 ms |
| Flush 500 events | < 200 ms |

### OBS-06 cron hooks

| Hook | Interval | Purpose |
|------|----------|---------|
| `kh_smma_telemetry_flush`  | 5 min | Drain EventQueue → retry service → hook |
| `kh_smma_telemetry_replay` | 5 min | Replay events from fallback buffer |

### OBS-06 test files

| File | Coverage |
|------|----------|
| `tests/Telemetry/EventReliabilityTest.php` | Retry/backoff, buffer, sampling, audit guarantee, fixture parity |
| `tests/Telemetry/EventQueueIntegrationTest.php` | Pipeline, 500-event burst, FIFO, overflow, retry routing |
