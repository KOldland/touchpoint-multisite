# Membership Troubleshooting

## Common failure modes

### 1) `webhook.invalid_signature`

Symptoms:

- Webhook returns `400 Invalid signature`.
- Audit/telemetry includes `webhook.invalid_signature`.

Triage:

1. Confirm webhook secret in environment.
2. Confirm exact raw body passed to signature verifier.
3. Re-generate signature with `tests/helpers/stripe_signature.php` and replay.

### 2) Duplicate attribution concerns

Symptoms:

- Multiple rows for same user/schedule in short window.

Triage:

1. Verify idempotency keys in signup-init payload.
2. Verify webhook event id dedupe in processed store.
3. Check if retries used same event/session IDs.

### 3) DLQ growth (`khm_webhook_dead_letter`)

Symptoms:

- Open dead-letter rows increase.

Triage:

1. `wp khm membership-webhook-dead-letters --last=50`
2. Classify `reason` (`enqueue_failed`, `processing_failed`).
3. Fix root cause, replay with `membership-webhook-dead-letters-replay`.

### 4) Email send failures (`membership.email.failed`)

Symptoms:

- Queue backlog grows, notifications not sent.

Triage:

1. Validate provider credentials and connectivity.
2. Toggle transactional emails off for impact containment.
3. Recover queue processing once provider is healthy.

### 5) DSAR export issues

Symptoms:

- `ZipArchive not available` / export missing.

Triage:

1. Verify zip extension availability.
2. Verify writable uploads dir and private DSAR folder.
3. Re-run approve with legal ticket reference.

## Rollback checklist (membership/email/webhook incident)

- [ ] Freeze risky operations (bulk retention/anonymize jobs).
- [ ] Disable transactional emails if failure storm ongoing.
- [ ] Pause replay loops until root cause is fixed.
- [ ] Record exact timeframe + impacted ids.
- [ ] Capture before/after SQL snapshots.
- [ ] Revert deployment/config if regression confirmed.
- [ ] Replay DLQ in controlled batches.
- [ ] Confirm idempotency and no duplicate side effects.

## Incident post-mortem template

### Incident summary

- Incident ID:
- Start time (UTC):
- End time (UTC):
- Sev level:
- Owner:

### Impact

- Affected flow stage(s):
- Customer impact:
- Data/privacy impact:

### Detection

- Alert fired (`membership.email.failed` / `webhook.invalid_signature` / `membership.attribution.missing` / `paid_reconciliation.discrepancy_alert`):
- Detection time:

### Root cause

- Technical root cause:
- Why not caught earlier:

### Timeline

- T0:
- T+X:
- Mitigation complete:

### Remediation and rollback

- Immediate mitigations:
- Rollback actions performed:
- DLQ replay evidence:

### Preventive actions

- Code/test/docs follow-ups:
- Owners and due dates:
