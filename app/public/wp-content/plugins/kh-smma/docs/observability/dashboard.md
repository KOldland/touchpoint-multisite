# OBS — Observability Dashboard & Telemetry Debug View

## Overview

Introduced in **OBS-03**, extended in **OBS-04**. The Observability Dashboard
is a read-only WordPress admin page that surfaces the metrics aggregated by
the analytics pipeline (OBS-02), provides a drill-down trace inspector for
debugging individual workflows, and displays automated alert indicators from
the `AlertEvaluator` (OBS-04).

---

## Admin Page

| Property | Value |
|---|---|
| Page slug | `kh-observability` |
| Parent menu | `kh-smma-dashboard` |
| Capability | `manage_options` |
| Class | `KH_SMMA\Admin\ObservabilityDashboardPage` |
| Registered in | `Plugin::register_admin()` |

URL: `wp-admin/admin.php?page=kh-observability`

---

## Dashboard Sections

### System Alerts (OBS-04)

Displayed at the top of the dashboard when `AlertEvaluator` is wired in.

When no alerts are active, a green "All systems operating normally" notice
is shown.

When alerts are active, each is displayed as a coloured notice:
- **WARNING** → yellow `notice-warning`
- **CRITICAL** → red `notice-error`

Each active alert shows: severity, alert type label, and last triggered time.

Below the active alerts, an **Alert History** table shows the last 10
`alert.triggered` audit events with: timestamp, alert type, severity, and
metrics context.

#### Alert types surfaced

| Alert type | Trigger | Severity |
|---|---|---|
| `compliance_fail_rate` | fail rate > 10% for 2 consecutive snapshots | WARNING |
| `compliance_fail_rate` | fail rate > 25% in latest snapshot | CRITICAL |
| `queue_backlog` | backlog (created − dispatched) > 20 | WARNING |
| `dispatch_errors` | > 5 `schedule.dispatch failed` events in recent telemetry | WARNING |

See [runbook.md](runbook.md) for investigation and remediation guidance.

### System Activity
Throughput metrics from the most recent analytics snapshot:

| Metric | Source field |
|---|---|
| Generate requests | `generate_requests` |
| Variants created | `variants_created` |
| Variant edits | `variant_edits` |
| Avg generate latency | `avg_generate_latency_ms` |
| P95 latency (estimate) | `avg_generate_latency_ms × 1.65` |

> **P95 note:** The heuristic `p95 ≈ 1.65 × avg` assumes a roughly normal
> distribution. Individual latency samples are not stored; only the aggregate
> average is available in the snapshot.

### Content Safety — Compliance Outcomes

| Metric | Source field |
|---|---|
| OK count + % | `compliance_ok` |
| WARN count + % | `compliance_warn` |
| FAIL count + % | `compliance_fail` |

Percentages are computed as `round(count / total * 100, 1)`. When all counts
are zero the denominator is floored at 1 (no division by zero).

### Scheduling

| Metric | Derived from |
|---|---|
| Schedules created | `schedule_created` |
| Schedules dispatched | `schedule_dispatched` |
| Queue backlog estimate | `max(0, created − dispatched)` |

### Business Metrics

| Metric | Source field |
|---|---|
| Membership signups | `membership_signups` |
| Promotion attributions | `promotion_attributions` |

### Diagnostics — Recent Audit Traces

Lists the 10 most recent `telemetry_event` rows from `wp_kh_smma_audit_log`.
Each row shows:
- Timestamp
- Truncated trace ID (13 chars + `…`) — clickable link to trace drill-down
- Event name
- Variant / Schedule ID (when present)

---

## Trace Drill-Down View

Activated when `?page=kh-observability&trace_id={uuid}` is present.

Rendered by `KH_SMMA\Admin\TelemetryTracePage`. Displays all audit events
sharing the given `trace_id` in ascending timestamp order (workflow sequence).

### Key fields column

`TelemetryTracePage::extract_key_fields()` returns a compact, PII-free summary
per event type:

| Event | Fields shown |
|---|---|
| `generate.request` | `session_id`, `variant_count_requested` |
| `generate.response` | `variant_count_generated`, `latency_ms` |
| `compliance.check` | `variant_id`, `outcome` |
| `variant.edit` | `variant_id`, `revision_id` |
| `schedule.create` | `schedule_id`, `result` |
| `schedule.dispatch` | `schedule_id`, `result` |
| `membership.signup` | `tier`, `payment_status` |
| `promotion_attribution` | `utm_source`, `confidence_score` |
| (other) | `—` |

---

## Classes

| Class | Location | Role |
|---|---|---|
| `ObservabilityDashboardPage` | `src/Admin/ObservabilityDashboardPage.php` | Dashboard rendering + data assembly |
| `TelemetryTracePage` | `src/Admin/TelemetryTracePage.php` | Trace drill-down view |
| `AlertEvaluator` | `src/Telemetry/AlertEvaluator.php` | Alert condition evaluation + active alert state |

---

## Data Flow

```
Admin request: ?page=kh-observability
     │
     ▼
ObservabilityDashboardPage::render_page()
     │  capability check: manage_options
     │
     ├── ?trace_id present?
     │        └── TelemetryTracePage::render($trace_id)
     │                └── AuditLogger::get_events_by_trace()
     │
     └── MetricsSnapshotRepository::get_latest()
         MetricsSnapshotRepository::get_recent(12)
         AuditLogger::get_recent_telemetry_events(10)
              └── render_dashboard()
```

---

## Testing

| File | Tests |
|---|---|
| `tests/Telemetry/ObservabilityDashboardTest.php` | 23 unit tests (OBS-03) |
| `tests/Telemetry/AlertEvaluatorTest.php` | 26 unit tests (OBS-04) |
| `tests/fixtures/telemetry/dashboard_metrics.json` | Fixture snapshot + expected values |
| `tests/fixtures/telemetry/alert_snapshots.json` | Alert threshold fixture scenarios |

Test categories:
- Empty / no snapshot → zeroed metrics
- Throughput, latency, compliance, scheduling, membership sections
- P95 estimate computation
- Backlog never negative
- Division-by-zero guard for compliance totals
- All required dashboard keys present
- `extract_key_fields()` for all 8 event types
- Fixture-driven assertion of computed vs expected values

Run:
```bash
cd app/public/wp-content/plugins/kh-smma
export KH_SMMA_TEST_MODE=ci
vendor/bin/phpunit tests/Telemetry/ObservabilityDashboardTest.php --testdox
```

---

## Privacy

The dashboard surfaces only:
- Integer counts and float averages from analytics snapshots
- Opaque IDs (variant_id, schedule_id, trace_id) from the audit log
- Outcome labels (`OK`, `WARN`, `FAIL`)

It never displays:
- User emails, names, or any PII
- Raw prompt or response text
- Payment identifiers
