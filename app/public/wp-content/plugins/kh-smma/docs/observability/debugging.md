# Telemetry Debug — Runbook

**Card:** OBS-08
**Status:** Production-ready
**Capability required:** `manage_observability` (Administrators only)

---

## Overview

The **Telemetry Debug** page (`Admin → SMMA → Telemetry Debug`) lets engineers and on-call admins investigate individual workflows end-to-end using trace correlation IDs.

All displayed payloads are passed through `TelemetryPayloadSanitizer` — PII fields are replaced with `[REDACTED]` before display.

---

## Access Control

| Role          | `view_observability` | `manage_observability` | Telemetry Debug page |
|---------------|---------------------|----------------------|---------------------|
| Administrator | ✅                   | ✅                    | ✅ Full access       |
| Editor        | ✅                   | ❌                    | ❌ Not visible       |
| Other roles   | ❌                   | ❌                    | ❌ Not visible       |

Capabilities are registered by `CapabilityManager` on plugin activation.

---

## Lookup Modes

### 1. Trace ID (default)

Use when you have a correlation ID from an error log, API response header (`X-Trace-Id`), or alert payload.

**Steps:**
1. Open **Admin → SMMA → Telemetry Debug**
2. Select **Trace ID** from the dropdown
3. Paste the full trace ID (e.g. `trace-a1b2c3-...`)
4. Click **Look Up**

Returns all audit-persisted events for that trace in chronological order.

### 2. Schedule ID

Use when investigating a specific scheduled post that failed or behaved unexpectedly.

Returns all telemetry events (across all traces) where `schedule_id` matches, ordered ascending.

### 3. Variant ID

Use when tracing the lifecycle of a specific content variant from generation through compliance check to dispatch.

---

## Timeline Interpretation

Each timeline row shows:

| Column       | Meaning                                                 |
|--------------|---------------------------------------------------------|
| Timestamp    | Wall-clock time the audit row was written               |
| Event        | Canonical event name (see telemetry_events.md)          |
| Trace ID     | Truncated trace correlation ID                          |
| Key Fields   | Extracted diagnostic fields (outcome, result, adapter…) |

### Common Event Sequences

**Healthy generate → dispatch:**
```
generate.request → generate.response → compliance.check → schedule.create → schedule.dispatch(result=delivered)
```

**Compliance failure:**
```
generate.request → generate.response → compliance.check(outcome=FAIL, violations=N)
```

**Dispatch failure:**
```
schedule.create → schedule.dispatch(result=failed)
→ check adapter logs for the schedule_id
```

**Membership attribution:**
```
membership.signup → promotion_attribution
```

---

## Privacy Safeguards

- All payloads are sanitized via `TelemetryPayloadSanitizer` before reaching the browser
- PII fields (email, phone, address, name fields, tokens) are replaced with `[REDACTED]`
- Unknown / unrecognised payload fields are stripped (allow-list only)
- The page requires `manage_observability` — editors cannot access raw telemetry

---

## Architecture

### `TelemetryTraceService` (`src/Telemetry/TelemetryTraceService.php`)

| Method | Description |
|--------|-------------|
| `get_trace_timeline(string $trace_id)` | Returns chronological event list for a trace |
| `find_by_schedule_id(string $schedule_id)` | Scans recent 100 events, filters by schedule_id |
| `find_by_variant_id(string $variant_id)` | Scans recent 100 events, filters by variant_id |
| `extract_key_fields(array $event)` | Returns diagnostic key→value pairs for timeline display |

Constructed with `AuditLogger` (required) and `TelemetryPayloadSanitizer` (optional — passed in from Plugin.php).

### `TelemetryDebugPage` (`src/Admin/TelemetryDebugPage.php`)

- Slug: `kh-telemetry-debug`
- Parent: `kh-smma-dashboard`
- Capability: `CapabilityManager::CAP_MANAGE_OBSERVABILITY`
- Nonce: `kh_telemetry_debug_lookup` (per-form GET request)

---

## Troubleshooting

### "No events found for the given lookup value"

- Verify the trace_id / schedule_id / variant_id is correct (exact match required)
- Events are retained for 30 days (telemetry buffer) / 365 days (audit log) — older events may have been cleaned up
- Confirm telemetry is enabled: check `is_configured()` in `TelemetryConfigService`

### Trace shows only 1–2 events

- Some events may have been sampled out (10% default sample rate in `EventQueue`)
- Audit-persisted events (via `AuditLogger::record_event`) are never sampled — they are always present
- Non-sampled events only exist in the audit log, not in the telemetry backend

### Payload fields showing `[REDACTED]`

- Expected behaviour for PII fields (email, phone, name, token, etc.)
- The sanitizer runs at display time — original payloads are stored as-is in the audit log

---

## Observability Dashboard vs Telemetry Debug

| Feature                     | Observability Dashboard | Telemetry Debug |
|-----------------------------|------------------------|-----------------|
| Capability required         | `view_observability`   | `manage_observability` |
| Shows aggregate metrics     | ✅                      | ❌              |
| Shows per-trace timeline    | ✅ (linked from table)  | ✅ (full search) |
| Supports schedule/variant search | ❌                | ✅              |
| PII-safe                    | ✅                      | ✅              |

---

## Related Documentation

- [Event Registry](../contracts/telemetry_events.md)
- [Event Emission Runbook](event_emission.md)
- [Security & Governance](security-governance.md)
- [Observability Dashboard](dashboard.md)
- [Alerts & Runbook](runbook.md)
