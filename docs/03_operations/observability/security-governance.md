# Telemetry Security & Governance — OBS-07

## Overview

This document defines security, privacy, and operational governance standards for the
SMMA telemetry and observability stack. These controls ensure the system is safe for
production environments.

---

## PII Policy

Telemetry payloads **must never** contain personally identifiable information (PII).

### What counts as PII

| Category | Examples |
|----------|---------|
| Email addresses | `user@example.com`, `admin@site.com` |
| Personal names | first name, last name, display name, username |
| Phone numbers | `+1-555-0100`, `555-0101` |
| Physical addresses | billing address, shipping address |
| Payment identifiers | card numbers, payment methods |
| Credentials | passwords, API keys, access tokens, secrets |
| Network identifiers | IP addresses |

### Enforcement mechanism

`TelemetryPayloadSanitizer` runs inside `EventEmitter::emit()` before the payload
reaches the audit logger or dispatch queue:

```
emit(event_name, payload)
  ↓
TelemetryPayloadSanitizer::sanitize(payload)
  ├─ PII fields → value replaced with "[REDACTED]" (key preserved for audit visibility)
  └─ Unknown fields → stripped silently
  ↓
Canonical envelope built → AuditLogger → EventQueue
```

If sanitization itself throws an unexpected error, `sanitize()` returns a minimal
safe array (`sanitization_error: true`) rather than allowing unsanitized data through.

### Allowed telemetry fields

Only fields in `TelemetryPayloadSanitizer::ALLOWED_FIELDS` are permitted in telemetry
payloads. All other fields are stripped automatically.

| Field | Event(s) | Notes |
|-------|----------|-------|
| `event_name` | all | Canonical event identifier |
| `trace_id` | all | Correlation UUID — opaque, not PII |
| `timestamp` | all | Unix timestamp |
| `service` | all | `"smma"` or `"mem"` |
| `user_id` | generate, membership | Opaque integer ID — **not** email/name |
| `session_id` | generate | Request-scoped ID |
| `prompt_hash` | generate | SHA-256 — never raw prompt text |
| `variant_id` | compliance, variant | |
| `schedule_id` | schedule | |
| `sponsor_id` | schedule, attribution | |
| `editor_id` | variant.edit | User ID integer |
| `latency_ms` | generate.response | |
| `model_version` | generate.response | |
| `outcome` | compliance | `"OK"/"WARN"/"FAIL"` |
| `rules_matched` | compliance | Rule IDs — not raw text |
| `channel` | schedule | |
| `adapter` | schedule.dispatch | |
| `result` | schedule.dispatch | |
| `tier` | membership | |
| `payment_status` | membership | `"paid"/"trial"/"free"` — not card details |
| `attribution_id` | membership | Promo/campaign ID |
| `utm_source/campaign` | attribution | Campaign-level tracking |
| `alert_name` | alert | System alert identifier |
| `change_type` | telemetry.config | `"api_key_rotated"` etc. |

**Prohibited fields include** (but are not limited to): `email`, `user_email`,
`first_name`, `last_name`, `display_name`, `username`, `phone`, `address`,
`api_key`, `access_token`, `secret`, `password`, `ip_address`.

---

## Retention Policy

| Data type | Table | Retention |
|-----------|-------|-----------|
| Telemetry buffer events | `wp_kh_smma_telemetry_buffer` | **30 days** |
| Analytics snapshots | `wp_kh_smma_analytics_snapshots` | **90 days** |
| Audit log events | `wp_kh_smma_audit_log` | **365 days** |

### Automated cleanup

The `kh_smma_telemetry_cleanup` cron job runs **daily** and deletes stale records
according to the table above. Implemented in `TelemetryConfigService::run_cleanup()`.

- Deletes in batches of 500 rows per table to avoid long-running locks.
- Returns a count of deleted rows per table for monitoring.
- Never throws — failures are swallowed so telemetry cleanup never blocks.

Cron registration in `Plugin::register_cron()`:
```php
wp_schedule_event( time(), 'daily', TelemetryConfigService::CRON_HOOK );
```

---

## Telemetry Credential Management

### Environment variables

External telemetry sink credentials are loaded exclusively from the server environment.
They are **never** stored in the database, source code, or WordPress options.

| Variable | Purpose |
|----------|---------|
| `SMMA_TELEMETRY_API_KEY` | API key for external telemetry endpoint |
| `SMMA_TELEMETRY_ENDPOINT` | URL of the external telemetry sink |

Accessed via `TelemetryConfigService`:
```php
$config = new TelemetryConfigService( $wpdb );
if ( $config->is_configured() ) {
    $key      = $config->get_api_key();
    $endpoint = $config->get_endpoint();
}
```

`TelemetryConfigService` reads from `$_SERVER` first, then `getenv()` as fallback.

### Access control

- Credentials are read server-side only.
- Never exposed to the frontend or logged in telemetry events.
- No credential values are emitted into the `telemetry.config.updated` event
  (only `change_type` and `user_id` are included).

### Secret rotation procedure

1. Generate a new API key in the telemetry provider dashboard.
2. Update the secret manager / hosting environment variable (`SMMA_TELEMETRY_API_KEY`).
3. Deploy the updated configuration (restart PHP-FPM / web workers).
4. Verify telemetry events flow correctly (check `wp_kh_smma_telemetry_buffer` depth).
5. Invalidate the old key in the provider dashboard.
6. Emit a `telemetry.config.updated` audit event:
   ```php
   $config->emit_config_update( get_current_user_id(), 'api_key_rotated' );
   ```

**Rotation frequency:** Every 90 days, or immediately after any suspected compromise.

---

## Security Event Audit Trail

When telemetry configuration changes, emit a `telemetry.config.updated` event:

| Field | Value |
|-------|-------|
| `event_name` | `telemetry.config.updated` |
| `trace_id` | Current trace context |
| `user_id` | ID of the operator who made the change |
| `change_type` | e.g. `"api_key_rotated"`, `"endpoint_updated"` |
| `timestamp` | Unix timestamp |

This creates a traceable audit record in `wp_kh_smma_audit_log`.

Example:
```php
$config->emit_config_update( get_current_user_id(), 'api_key_rotated' );
```

---

## Privacy Guidelines for Developers

When adding new telemetry events:

1. **Never** include raw user-supplied content (ad copy, bio text, comments).
2. **Never** include email addresses, names, or phone numbers.
3. Use opaque identifiers (`user_id`, `variant_id`) instead of PII.
4. Use hashes (`prompt_hash`, `response_hash`) instead of raw content.
5. Add new allowed fields to `TelemetryPayloadSanitizer::ALLOWED_FIELDS` if needed.
6. Update `docs/contracts/telemetry_events.md` and `event_emission.md`.
7. Add a fixture case to `tests/fixtures/telemetry/pii_payload_cases.json` if the event
   has fields that could be mistaken for PII.

---

## Files

| File | Purpose |
|------|---------|
| `src/Telemetry/TelemetryPayloadSanitizer.php` | PII masking + field allow-list enforcement |
| `src/Telemetry/TelemetryConfigService.php` | Credential loading + retention cleanup cron |
| `tests/Telemetry/PayloadSanitizerTest.php` | 28 unit tests for sanitizer |
| `tests/Telemetry/TelemetryCleanupTest.php` | 18 tests for cleanup and credential service |
| `tests/fixtures/telemetry/pii_payload_cases.json` | Golden fixture: 6 PII/sanitization cases |
