# OBS — Operational Runbook

## Overview

This runbook covers investigation and remediation for the three automated alert
conditions produced by `AlertEvaluator` (OBS-04).

Alert evaluation runs every 5 minutes via `kh_smma_alert_evaluate` WP-Cron.
Active alerts are stored in the `kh_smma_active_alerts` WP option and surfaced
in the **Observability Dashboard** (`wp-admin/admin.php?page=kh-observability`).

All alerts are **internal monitoring signals only** — no external delivery
(PagerDuty, Slack, email) is configured in this implementation.

---

## Alert: `compliance_fail_rate`

### What it means

The compliance engine is rejecting a disproportionate share of generated
content variants.

| Severity | Trigger condition |
|---|---|
| **WARNING** | `compliance_fail / total > 10%` across 2 consecutive 5-min snapshots |
| **CRITICAL** | `compliance_fail / total > 25%` in the latest snapshot |

### Possible causes

1. **Bad LLM prompt** — a recent prompt change causes the model to produce
   non-compliant content at higher rates.
2. **Policy rule change** — a compliance rule was added or tightened that
   now blocks content that previously passed.
3. **Rule engine bug** — an error in `ComplianceValidator` is incorrectly
   classifying safe content as failures.
4. **Content campaign type** — a new campaign category with naturally harder
   compliance requirements was activated.

### Investigation steps

1. **Check the dashboard** — open
   `wp-admin/admin.php?page=kh-observability` and note the `compliance_fail`
   count and `ok_pct` in the Content Safety section.

2. **Inspect recent traces** — click any trace link in the Diagnostics section
   and look for `compliance.check` events with `outcome=FAIL`.

3. **Review variant samples** — query the audit log for recent compliance
   failures:
   ```sql
   SELECT details FROM wp_kh_smma_audit_log
   WHERE action = 'telemetry_event'
     AND details LIKE '%compliance.check%'
     AND details LIKE '%FAIL%'
   ORDER BY id DESC
   LIMIT 20;
   ```

4. **Check for recent prompt changes** — review the
   `smma_generate_request` audit entries for `prompt_hash` changes.

5. **Review compliance rules** — inspect `ComplianceValidator` for recent
   changes to the blacklist or channel-specific rule sets.

### Remediation

| Cause | Action |
|---|---|
| Bad prompt | Roll back prompt version; coordinate with LLM team |
| Policy rule change | Review new rules; update guidance for content creators |
| Rule engine bug | Roll back rule change; file bug against `ComplianceValidator` |
| Campaign type | Adjust variant generation parameters for the campaign type |

### Escalation

If `compliance_fail_rate > 50%` persists for more than 15 minutes, pause
automated content generation for the affected campaign type until the root
cause is identified.

---

## Alert: `queue_backlog`

### What it means

The schedule dispatch queue has more pending items than can be processed in
the expected window. Backlog is calculated as:

```
backlog = schedule_created − schedule_dispatched
```

| Severity | Trigger condition |
|---|---|
| **WARNING** | `backlog > 20` in the latest snapshot |

### Possible causes

1. **Queue processor stalled** — `ScheduleQueueProcessor` cron is not
   running or is failing silently.
2. **Adapter failures** — a channel adapter (Meta, LinkedIn, Twitter) is
   returning errors, leaving dispatches incomplete.
3. **Large campaign batch** — a bulk campaign creation event produced more
   schedules than the queue can process in one 5-minute window.
4. **Cron backlog** — WordPress cron is delayed due to low site traffic.

### Investigation steps

1. **Check the dashboard** — review the Scheduling section for
   `schedule_created` vs `schedule_dispatched` gap.

2. **Inspect dispatch events** — look for `schedule.dispatch` events with
   `result=failed` in recent audit traces (Diagnostics section).

3. **Check WP-Cron status**:
   ```bash
   wp cron event list --format=table
   wp cron event run kh_smma_process_queue
   ```

4. **Check adapter logs** — inspect recent `schedule.dispatch` telemetry
   events for `adapter` field to identify which channel is failing.

5. **Database query for stuck schedules**:
   ```sql
   SELECT ID, post_status, post_date FROM wp_posts
   WHERE post_type = 'social_schedule'
     AND post_status = 'pending'
   ORDER BY post_date ASC
   LIMIT 30;
   ```

### Remediation

| Cause | Action |
|---|---|
| Stalled processor | Trigger manually: `wp cron event run kh_smma_process_queue` |
| Adapter failures | See Dispatch Errors runbook below |
| Large batch | Verify batch size limits; increase cron frequency if needed |
| WP-Cron delay | Consider switching to system cron (`DISABLE_WP_CRON = true`) |

### Escalation

If backlog exceeds 100 or has been growing for more than 30 minutes, consider
temporarily pausing new schedule creation while the queue is drained.

---

## Alert: `dispatch_errors`

### What it means

A significant number of schedule dispatch attempts have failed within the
recent telemetry window.

| Severity | Trigger condition |
|---|---|
| **WARNING** | `> 5` `schedule.dispatch` events with `result=failed` in the recent audit telemetry |

### Possible causes

1. **Adapter API failure** — a channel API (Meta Ads, LinkedIn, Twitter) is
   returning errors or rate-limiting requests.
2. **Credential issues** — OAuth tokens or API keys have expired or been
   revoked.
3. **Invalid campaign payload** — a schedule post contains malformed data
   that the adapter rejects.
4. **Network connectivity** — the server cannot reach the external API
   endpoint.

### Investigation steps

1. **Identify the failing adapter** — the alert payload includes the
   `adapter` field (e.g. `meta`, `linkedin`, `twitter`). Start there.

2. **Check adapter logs** — look for `schedule.dispatch` events with
   `result=failed` in audit traces:
   ```sql
   SELECT details FROM wp_kh_smma_audit_log
   WHERE action = 'telemetry_event'
     AND details LIKE '%schedule.dispatch%'
     AND details LIKE '%failed%'
   ORDER BY id DESC
   LIMIT 10;
   ```

3. **Verify OAuth tokens**:
   ```bash
   wp eval "
   \$repo = new KH_SMMA\Services\TokenRepository(\$wpdb, new KH_SMMA\Security\CredentialVault());
   var_dump(\$repo->get_tokens_for_account(ACCOUNT_ID));
   "
   ```

4. **Test adapter connectivity manually**:
   ```bash
   # Verify API endpoint reachability
   curl -I https://graph.facebook.com/v18.0/
   curl -I https://api.linkedin.com/v2/
   ```

5. **Check for payload issues** — review `schedule_id` values in the failed
   dispatch events and verify the associated posts have valid metadata:
   ```sql
   SELECT post_id, meta_key, meta_value
   FROM wp_postmeta
   WHERE post_id IN (SCHEDULE_IDS)
     AND meta_key LIKE '_kh_smma_%';
   ```

### Remediation

| Cause | Action |
|---|---|
| Adapter API failure | Wait for API recovery; retry failed schedules |
| Expired credentials | Refresh OAuth tokens via Settings > Social Accounts |
| Invalid payload | Inspect and fix the schedule post; re-queue dispatch |
| Network connectivity | Verify server outbound network access |

#### Retrying failed dispatches

```bash
# Trigger the queue processor which will retry pending schedules
wp cron event run kh_smma_process_queue
```

#### Refreshing OAuth tokens

Navigate to: `wp-admin/admin.php?page=kh-smma-settings` and re-authenticate
the affected social account.

### Escalation

If failures persist for a specific adapter after token refresh and API status
confirms the service is healthy, escalate to the adapter implementation team
for code-level investigation.

---

## General Ops Procedures

### Viewing the Observability Dashboard

URL: `wp-admin/admin.php?page=kh-observability`

Required capability: `manage_options`

### Manually triggering alert evaluation

```bash
wp cron event run kh_smma_alert_evaluate
```

### Viewing active alerts (WP option)

```bash
wp option get kh_smma_active_alerts
```

### Clearing a stale active alert

```bash
# Clear all alerts (use with caution — evaluate() will re-populate on next run)
wp option delete kh_smma_active_alerts
```

### Checking cron schedule health

```bash
wp cron event list --format=table | grep kh_smma
```

Expected entries:

| Hook | Interval |
|---|---|
| `kh_smma_process_queue` | 1 minute |
| `kh_smma_phase_aggregate` | hourly |
| `kh_smma_run_settlement` | daily |
| `kh_smma_analytics_flush` | 5 minutes |
| `kh_smma_alert_evaluate` | 5 minutes |

---

## Alert Evaluation Architecture

```
[WP-Cron: kh_smma_alert_evaluate every 5 min]
     │
     ▼
AlertEvaluator::evaluate()
     │
     ├── MetricsSnapshotRepository::get_recent(2)
     │        └── check_compliance_fail_rate()
     │        └── check_queue_backlog()
     │
     ├── AuditLogger::get_recent_telemetry_events(100)
     │        └── check_dispatch_errors()
     │
     └── For each triggered alert:
              ├── EventEmitter::emit('alert.triggered', $payload)
              │        └── AuditLogger::record_event()   (audit-first)
              │        └── do_action('kh_telemetry_event', $event)
              └── update_option('kh_smma_active_alerts', ...)
```

---

*Last updated: OBS-04 implementation*
