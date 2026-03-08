# Membership Admin Reporting (MEM-04)

## Overview
The membership reporting admin surface now supports:
- Server-side filtering on schedule, sponsor, user, conversion type, date range, and search query.
- KPI summary cards for total, paid, signup, no-consent, and unique users.
- Paginated report table with consent-aware redaction.
- CSV export with SHA-256 checksum header and export file TTL cleanup.

## Access Control
- Reports page requires `manage_options`.
- Members page actions require `manage_khm`.
- Unauthorized access attempts are logged using the standard `unauthorized_admin_access` pattern.

## Consent Redaction
Rows that indicate no consent (`conversion_type` contains `no_consent` or metadata consent is false) are redacted:
- `user_id`
- `user_email`
- `utm_source`
- `utm_medium`
- `utm_campaign`

## Member Detail Enhancements
Member detail now includes:
- Attribution history table (latest 100 records)
- Expandable raw metadata JSON view
- One-click irreversible anonymize action for attribution PII

## Telemetry / Audit Events
The flow emits reporting telemetry via `khm_membership_reporting_telemetry` and logs to PHP error log for:
- `membership.report.view`
- `membership.export.started`
- `membership.export.completed`
- `membership.anonymize.executed`
