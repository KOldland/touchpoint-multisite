# Transactional Email Safety Controls & Rollback

Date: 2026-03-02

## Control implemented
- Admin safety toggle added in Membership Settings:
  - Option key: `khm_membership_transactional_emails_enabled`
  - Scope: webhook-triggered membership transactional emails (`welcome`, `payment_confirmation`)
- Default behavior:
  - disabled unless explicitly enabled
- Webhook behavior when disabled:
  - no transactional email send/queue
  - telemetry emitted: `membership.email.skipped`
  - audit entry recorded as `skipped`

## Admin toggle location
- WP Admin -> `Memberships` -> `Settings` -> `Email Settings`
- Field label: `Transactional Membership Emails`

## Emergency rollback plan
1. Immediate stop (UI):
- Uncheck `Transactional Membership Emails` and save settings.

2. Immediate stop (WP-CLI):
```bash
wp option update khm_membership_transactional_emails_enabled 0
```

3. Verify rollback:
- Check option:
```bash
wp option get khm_membership_transactional_emails_enabled
```
- Confirm webhook logs show skip events:
  - `membership.email.skipped`
- Confirm queue not growing for membership transactional templates.

4. Re-enable (controlled rollout):
```bash
wp option update khm_membership_transactional_emails_enabled 1
```

## Monitoring/alert hooks to wire
- `membership.email.failed`
- `membership.email.sent.failed`
- `membership.email.skipped`
- `membership.attribution.missing`
- `webhook.invalid_signature`

## Evidence artifact
- `logs/e2e-2026-03-02/transactional-email-toggle-evidence.json`
  - observed:
    - disabled: `transactional_off_queue_count = 0`
    - enabled: `transactional_on_queue_count = 2`
