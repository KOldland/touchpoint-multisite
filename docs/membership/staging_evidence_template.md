# Staging E2E Evidence Template (MEM)

Paste this into PR description/comment and fill each field.

## Run metadata

- Environment:
- Branch/commit:
- Runner:
- Date/time (UTC):

## Flow evidence (landing -> checkout -> webhook -> membership)

- Signup-init request log: `landing_submit.log`
- Session ID:
- Webhook post log: `webhook_post.log`
- Landing-success response file: `landing_success.json`
- Membership row verification query output attached: yes/no

## Artifact list

- `db_before.sql`
- `db_after.sql`
- `audit_log.txt`
- `smoke-telemetry.json`
- `landing_submit.log`
- `webhook_post.log`
- `admin_screenshot.png`
- `export.csv`
- `anonymize_before_after.sql`

## Privacy and retention evidence

- Anonymize dry-run output attached: yes/no
- Anonymize execute output attached: yes/no
- Retention dry-run output attached: yes/no
- Retention execute output + timing attached: yes/no

## DSAR evidence

- DSAR request response (`request_id`) attached: yes/no
- DSAR approve response (`file`, `rows`) attached: yes/no
- DSAR zip path evidence attached: yes/no
- Compliance ticket ID:

## Reconciliation evidence

- `paid04_signoff_smoke.php` output attached: yes/no
- Reconciliation rows query output attached: yes/no
- Rerun endpoint proof attached (if used): yes/no

## A11y/snapshot evidence

- Playwright command used:
- Result summary:
- HTML report path:

## Alert and logs review

- Checked for `membership.email.failed`:
- Checked for `webhook.invalid_signature`:
- Checked for `membership.attribution.missing`:
- Checked for `paid_reconciliation.discrepancy_alert`:

## Sign-off block

- QA sign-off:
- Ops sign-off:
- Legal ACK comment link:
- PM final sign-off:
