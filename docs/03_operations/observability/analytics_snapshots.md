# OBS — Analytics Aggregation & Metrics Snapshots

## Overview

Introduced in **OBS-CARD-02**. The telemetry analytics layer listens to emitted
events, accumulates counts and distributions in a rolling WP option, and writes
a durable snapshot to the database every 5 minutes.

---

## Pipeline

```
EventEmitter
     │  do_action('kh_telemetry_event', $event)
     ▼
Telemetry\AnalyticsFeedbackService::handle_event()
     │  increments WP option accumulator (kh_smma_telemetry_accumulator)
     ▼
  [every 5 min — cron: kh_smma_analytics_flush]
Telemetry\AnalyticsFeedbackService::flush_snapshot()
     │  compute_metrics() → MetricsSnapshotRepository::write_snapshot()
     ▼
wp_kh_smma_analytics_snapshots  (DB table)
```

---

## Classes

| Class | Location | Role |
|---|---|---|
| `AnalyticsFeedbackService` | `src/Telemetry/AnalyticsFeedbackService.php` | Accumulates metrics from events, flushes snapshots |
| `MetricsSnapshotRepository` | `src/Telemetry/MetricsSnapshotRepository.php` | Persists / retrieves snapshot rows |

> **Note:** The existing `Services\AnalyticsFeedbackService` tracks schedule status
> changes (dispatch outcomes) and is unmodified. The new `Telemetry\AnalyticsFeedbackService`
> tracks events across all workflows via the `kh_telemetry_event` action.

---

## Accumulator

Key: `kh_smma_telemetry_accumulator` (WP option, `autoload=false`)

| Field | Incremented by |
|---|---|
| `generate_requests` | `generate.request` |
| `variants_created` | `generate.response` (`variant_count_generated`) |
| `variant_edits` | `variant.edit` |
| `compliance_ok` | `compliance.check` with `outcome=OK` |
| `compliance_warn` | `compliance.check` with `outcome=WARN` |
| `compliance_fail` | `compliance.check` with `outcome=FAIL` |
| `schedule_created` | `schedule.create` |
| `schedule_dispatched` | `schedule.dispatch` |
| `membership_signups` | `membership.signup` |
| `promotion_attributions` | `promotion_attribution` |
| `total_latency_ms` | `generate.response` (`latency_ms`) |
| `latency_count` | `generate.response` (non-zero `latency_ms`) |
| `window_start` | Set on first event after a flush |

---

## Snapshot schema

Table: `wp_kh_smma_analytics_snapshots`

| Column | Type | Notes |
|---|---|---|
| `snapshot_id` | BIGINT UNSIGNED AUTO_INCREMENT | Primary key |
| `window_start` | DATETIME | Start of the 5-minute accumulation window |
| `created_at` | DATETIME | When `flush_snapshot()` ran |
| `metrics_json` | LONGTEXT | JSON-encoded metrics object |

---

## Metrics snapshot object

```json
{
  "window_start":             "2026-03-07T12:00:00+00:00",
  "generate_requests":        42,
  "variants_created":         38,
  "variant_edits":            12,
  "compliance_ok":            30,
  "compliance_warn":           6,
  "compliance_fail":           2,
  "schedule_created":         18,
  "schedule_dispatched":      17,
  "membership_signups":        5,
  "promotion_attributions":    8,
  "avg_generate_latency_ms": 312.4
}
```

---

## Compliance audit persistence

Every `compliance.check` event now carries:

| Field | Source |
|---|---|
| `variant_id` | `RestController` |
| `outcome` | `OK \| WARN \| FAIL` |
| `rules_matched` | Array of matched rule IDs |
| `ai_review_summary` | AI review text (empty when rule-only check) |

All fields are stored by `AuditLogger::record_event()` (via EventEmitter) in
`wp_kh_smma_audit_log` with `action = 'telemetry_event'`.

---

## Cron

| Hook | Interval | Purpose |
|---|---|---|
| `kh_smma_analytics_flush` | 5 minutes (`kh_smma_five_minutes`) | Flush accumulator → snapshot |

---

## WordPress action hooks

| Hook | Payload | Purpose |
|---|---|---|
| `kh_smma_analytics_snapshot_flushed` | `$metrics` (array) | Fires after each snapshot write |

---

## Privacy rules

Snapshots must contain **only**:
- integer counts
- float averages / latencies
- status distribution strings (`compliance_ok`, etc.)

Snapshots must **never** contain:
- user emails or names
- raw prompt text
- payment identifiers
- membership personal data

---

## Querying recent snapshots

```php
$repo = new \KH_SMMA\Telemetry\MetricsSnapshotRepository( $wpdb );

$latest = $repo->get_latest();
// $latest['metrics'] is the decoded metrics array

$recent = $repo->get_recent( 12 ); // last 12 snapshots (~1 hour)
```

---

## Fixture

`tests/fixtures/telemetry/analytics_events.json` — 10-event stream with
`expected_metrics` block. Used by `AnalyticsIntegrationTest` to validate the
full pipeline deterministically.
