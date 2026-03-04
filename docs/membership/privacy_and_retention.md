# Privacy and Retention (MEM)

## Policy Summary

- Attribution persistence is consent-gated.
- Records with `consent=false` must not retain UTMs or direct identifiers.
- Default retention is 24 months (`730` days) via site option `khm_attribution_retention_days`.
- Expired records are anonymized by default; optional delete mode is available for strict policies.

Legal baseline statement (required in sign-off evidence):

> Legal has reviewed DSAR flows and approves anonymize-by-default; deletion requires sign-off.

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

If recovery is required for legal reasons, restoration must come from an encrypted database backup controlled by Ops with explicit legal/compliance approval.

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
- Expected source of truth: Ops secrets vault / deployment environment variables / GitHub Actions encrypted secrets.
- Follow CIC secret-handling controls (CIC-07 policy/ticket process) for rotation, access, and audit trail.
- CI secret and fixture hygiene reference: [../contracts/CONTRIBUTING-GOLDEN.md](../contracts/CONTRIBUTING-GOLDEN.md)
- Do not commit `.env` values or sample salts to the repo.
- Staging verification command:
  - `wp eval 'echo getenv("KHM_ANON_SALT") ? "KHM_ANON_SALT present\n" : "KHM_ANON_SALT missing\n";'`
- Hash verification example (matches `reference_hash = sha256(KHM_ANON_SALT + reference)`):
  - `wp eval '$salt=getenv("KHM_ANON_SALT"); $ref="cs_test_123"; echo hash("sha256", $salt . $ref) . "\n";'`
- Rotation impact:
  - Old `reference_hash` values are no longer comparable to newly generated hashes.
  - Rotation is best-effort only; rehashing original references is not reversible.
- Rotation plan:
  - Rotate in maintenance window, record ticket/change ID.
  - Keep prior salt only in secure vault for audit reconciliation window (no app access).
  - Confirm new anonymizations produce expected hashes and close change ticket.

## Retention Worker

- Cron hook: `khm_cleanup_attribution`
- Worker class: `src/Membership/RetentionWorker.php`
- Default chunk size per run: `1000` rows (configurable in CLI via `--chunk-size`).
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
- Retention preview:
  - `wp khm retention:run --dry-run`
- Retention execute:
  - `wp khm retention:run --chunk-size=1000`

## DSAR Workflow

- User endpoint: `POST /wp-json/kh-membership/v1/dsar/request` (authenticated)
- Admin approval endpoint: `POST /wp-json/kh-membership/v1/dsar/approve` (`manage_options`)
- Audited events:
  - `dsar.requested`
  - `dsar.completed`
  - `dsar.deleted`

Deletion policy: anonymization is the default DSAR fulfillment mode; hard deletion requires legal/compliance sign-off and ticket reference.

### DSAR export format and retention policy

- Export bundle is zip containing `attribution.json` generated at approval time.
- Export files are written to a private uploads subdirectory (`khm-dsar-private`) with access denied via `.htaccess`.
- Export retention recommendation:
  - staging: purge within 7 days of verification
  - production: purge within 30 days unless legal hold requires longer
- Every export must be traceable to a compliance ticket ID and approver.

### Emergency access policy

- Two-person approval required for direct data access.
- Ticket ID and time window required.
- Access logs and post-access review mandatory.

## Rollout and Rollback

- Staged rollout:
  - Deploy to staging.
  - Run `wp khm retention:run --dry-run` and review candidate counts.
  - Execute a small anonymize batch, validate audit/telemetry, then promote to production window.
- Rollback:
  - Stop further anonymization jobs/actions immediately.
  - Restore from approved database backup if recovery is legally required.
  - Document incident + ticket references in the runbook log.

## Export file handling

- Never move DSAR export files to public buckets or shared drives without legal approval.
- Do not attach raw export contents to PRs; attach only controlled evidence metadata and redacted snippets.
- On completion, record purge date and operator in ticket.
