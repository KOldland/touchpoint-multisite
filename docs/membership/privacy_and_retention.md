# MEM-05 Privacy and Retention Runbook

## Policy Summary

- Attribution persistence is consent-gated.
- Records with `consent=false` must not retain UTMs or direct identifiers.
- Default retention is 24 months (`730` days) via site option `khm_attribution_retention_days`.
- Expired records are anonymized by default; optional delete mode is available for strict policies.

## Consent Rules

- Persisted attribution rows now track:
  - `consent` (boolean)
  - `consent_given_at` (UTC timestamp)
  - `consent_source` (`landing|manual|api|webhook|anonymized`)
- Consent revocation should emit:
  - `consent.changed`
  - `membership.consent.revoked`

## Anonymization Rules

Anonymization is irreversible by default.

- Null/redact:
  - `user_id`, `user_email`
  - `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`
  - `phase_at_click`, `reference`
- Preserve:
  - `schedule_id`, `sponsor_id`, `created_at`
- Set:
  - `reference_hash = sha256(KHM_ANON_SALT + reference)`
  - `anonymized_at`, `anonymized_by`, `anonymize_reason`
  - `consent=0`, `consent_source=anonymized`

## Salt Management (`KHM_ANON_SALT`)

- Must be provided via environment, not in repository.
- Rotation impact:
  - Old `reference_hash` values are no longer comparable to newly generated hashes.
  - Rotation is best-effort only; rehashing original references is not reversible.

## Retention Worker

- Cron hook: `khm_cleanup_attribution`
- Worker class: `src/Membership/RetentionWorker.php`
- Mode option:
  - `khm_attribution_retention_mode=anonymize` (default)
  - `khm_attribution_retention_mode=delete`

## Admin Workflows

### Member detail anonymize

- Requires `manage_options`.
- Uses nonce verification.
- Emits anonymization telemetry and audit context.

### Reports bulk anonymize

- Requires `manage_options`.
- Runs against current report filter context.
- Includes privacy-safe CSV export headers:
  - `X-KHM-Export-Checksum`
  - `X-KHM-Export-Redacted`

## CLI Workflows

- Single row:
  - `wp khm anonymize_attribution --id=123 --reason="ticket-123"`
- Batch dry-run:
  - `wp khm anonymize_attribution --batch --filter="consent=false AND created_at < '2025-01-01'" --dry-run`
- Batch execute:
  - `wp khm anonymize_attribution --batch --filter="consent=false AND created_at < '2025-01-01'" --limit=1000`

## DSAR Workflow

- User endpoint: `POST /wp-json/kh-membership/v1/dsar/request` (authenticated)
- Admin approval endpoint: `POST /wp-json/kh-membership/v1/dsar/approve` (`manage_options`)
- Audited events:
  - `dsar.requested`
  - `dsar.completed`
  - `dsar.deleted`

## Emergency Access

- Two-person approval
- Ticket ID required
- Time-limited access
- Access logging and post-incident review mandatory
