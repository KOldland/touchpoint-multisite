# Membership Ops Runbook (Production)

## Operational areas

- Webhook ingestion and replay
- Email queue safety and retries
- Retention and anonymization operations
- DSAR approvals and legal gates
- Reconciliation/finance reruns and discrepancy response

## Webhook operations

### Signature verification and invalid signature triage

When `webhook.invalid_signature` appears:

1. Confirm endpoint and secret match current environment.
2. Confirm request body was not altered in transit.
3. Replay from source or DLQ after secret fix.

Check endpoint config in admin settings:

- Membership endpoint: `/wp-json/khm/v1/webhooks/stripe`

### Rate limit handling

Handler enforces transient-backed limits and emits `webhook.rate_limited`.

Immediate actions:

1. Verify source IP / burst pattern.
2. Confirm Stripe retry behavior and webhook delivery history.
3. Process backlog via dead-letter replay after burst subsides.

### Idempotency store and DLQ replay

Primary replay command set:

```bash
wp khm membership:dlq --last=50
wp khm membership:dlq:replay --id=123
wp khm membership:dlq:replay --all-open --limit=20
```

Compatibility note: if your environment provides a wrapper command `php bin/khm requeue:webhook {event_id}`, it should map to the same replay behavior. This repository ships WP-CLI replay commands above.

Confirm idempotency after replay:

- No duplicate membership status flips.
- No duplicate attribution inserts for same operation key.
- Audit rows show one successful processing state per event id.

## Email operations

### Toggle transactional membership emails

Option key: `khm_membership_transactional_emails_enabled`

```bash
wp option get khm_membership_transactional_emails_enabled
wp option update khm_membership_transactional_emails_enabled 1
wp option update khm_membership_transactional_emails_enabled 0
```

Verify effect:

- Enabled: welcome/payment events enqueue/sent according to flow.
- Disabled: audit/telemetry should emit `membership.email.skipped`.

### Queue/backoff checks

```bash
wp db query "SELECT id,template,status,attempt_count,last_error,created_at,updated_at FROM wp_khm_email_queue ORDER BY id DESC LIMIT 50;"
```

On repeated `membership.email.failed`:

1. Validate SMTP/provider status.
2. Confirm queue growth and retry attempts.
3. Keep toggle off during incident if failure storm risks customer impact.

## Retention and anonymization

### Standard retention runs

```bash
wp khm retention:run --dry-run --chunk-size=1000
wp khm retention:run --mode=anonymize --chunk-size=1000
```

Performance knobs:

- `--chunk-size` (default 1000)
- `--retention-days=<days>`
- `--mode=anonymize|delete`

### Emergency anonymization

```bash
wp khm anonymize_attribution --id=123 --dry-run
wp khm anonymize_attribution --id=123 --reason="incident-response"
wp khm anonymize_attribution --id=123 --execute
```

Batch mode:

```bash
wp khm anonymize_attribution --batch --filter="consent=false AND created_at < '2025-01-01'" --limit=1000 --dry-run
wp khm anonymize_attribution --batch --filter="consent=false AND created_at < '2025-01-01'" --limit=1000
```

## DSAR operations + legal checklist

### Request and approve

- `POST /wp-json/kh-membership/v1/dsar/request` (authenticated user)
- `POST /wp-json/kh-membership/v1/dsar/approve` (`manage_options`)

For every approve action, provide:

- Compliance ticket ID
- Approval actor
- Mode (`export|delete|anonymize`)

Required legal language in PR/runbook evidence:

> Legal has reviewed DSAR flows and approves anonymize-by-default; deletion requires sign-off.

Include link to compliance ticket in evidence payload.

## Salt rotation (`KHM_ANON_SALT`)

1. Open change request and legal/compliance communication thread.
2. Rotate env secret in vault/deployment.
3. Validate salt presence:

```bash
wp eval 'echo getenv("KHM_ANON_SALT") ? "KHM_ANON_SALT present\n" : "KHM_ANON_SALT missing\n";'
```

4. Validate new hash generation behavior:

```bash
wp eval '$salt=getenv("KHM_ANON_SALT"); $ref="cs_test_123"; echo hash("sha256", $salt . $ref) . "\n";'
```

Expected impact:

- Historical correlation using prior salt is lost for new hashes.
- Reversibility is not supported without approved encrypted backup recovery path.

## Reconciliation/finance operations

Smoke validation:

```bash
php app/public/wp-content/plugins/kh-smma/scripts/paid04_signoff_smoke.php
```

Operational verification:

```bash
wp db query "SELECT reconciliation_id,manifest_id,status,estimated_spend,actual_spend,discrepancy_percent,created_at FROM wp_kh_paid_reconciliations ORDER BY created_at DESC LIMIT 20;"
```

Rerun endpoint:

- `POST /wp-json/kh-smma/v1/reconciliations/{id}/rerun`

## Alert definitions and response

Must be monitored:

- `membership.email.failed`
- `webhook.invalid_signature`
- `membership.attribution.missing`
- `paid_reconciliation.discrepancy_alert`

Response policy:

1. Classify severity and customer impact.
2. Mitigate (toggle, replay, rollback, pause jobs).
3. Attach logs and SQL evidence.
4. Publish incident update and ETA.
