# Stripe Webhook Runbook (KHM Membership)

## Scope

Endpoint: `POST /wp-json/kh-membership/v1/webhook/stripe`

This runbook covers:
- signature verification failures
- duplicate/replay events
- failed event reprocessing
- secret rotation
- monitoring and alert thresholds

## Ownership

- Primary owner: `TBD`
- Secondary owner: `TBD`
- Escalation channel: `TBD`

## Security and secrets

Required options/secrets:
- `khm_stripe_webhook_secret`
- `khm_stripe_secret_key`

Never store webhook secrets in code or docs.

## Secret rotation procedure

1. Create/roll webhook secret in Stripe destination settings.
2. Update `khm_stripe_webhook_secret` in WordPress admin settings.
3. Send Stripe test event (`product.updated` or `invoice.paid`) and confirm `200`.
4. Verify new events are written to `wp_khm_processed_webhooks`.
5. Keep old secret only during brief overlap window if required; remove old value after validation.

## Event processing model

1. Webhook request verifies Stripe signature.
2. Event is claimed in `wp_khm_processed_webhooks`.
3. Event is queued for async processing.
4. Endpoint responds quickly (`200`) for queued/duplicate events.
5. Worker updates status to `processed` or `failed`.

## Duplicate and replay behavior

- Duplicate Stripe deliveries are safe.
- Existing `processed` or `processing` events return `200` without re-applying side effects.

## Operator actions

Admin page: `Memberships -> Webhook Events` (`page=khm-membership-webhooks`)

Available actions:
- `Requeue`
- `Mark Processed`
- `Mark Failed`

Use `Requeue` only after confirming root cause.

## Payload storage and PII policy

Payload mode filter: `khm_membership_webhook_payload_mode`
- `excerpt` (default): redacted, truncated payload excerpt
- `hash`: hash-only storage (payload omitted)
- `full`: redacted full payload (truncated to safeguard size)

Retention filter: `khm_membership_webhook_retention_days` (default `30` days)

## Monitoring and thresholds

Telemetry hook: `khm_membership_webhook_telemetry`

Track:
- `webhook.received`
- `webhook.invalid_signature`
- `webhook.rate_limited`
- `webhook.processed`
- `webhook.failed`
- `webhook.queue_failed`

Suggested alerts:
- invalid signature rate > 5% over 15m
- failed processing rate > 2% over 15m
- queue failures > 0 over 5m
- backlog of `processing` events older than 10m

## Manual triage SQL

Recent failures:

```sql
SELECT event_id, event_type, status, attempts, notes, updated_at
FROM wp_khm_processed_webhooks
WHERE status = 'failed'
ORDER BY updated_at DESC
LIMIT 50;
```

Stuck processing:

```sql
SELECT event_id, event_type, status, attempts, updated_at
FROM wp_khm_processed_webhooks
WHERE status = 'processing'
  AND updated_at < (UTC_TIMESTAMP() - INTERVAL 10 MINUTE)
ORDER BY updated_at ASC;
```

## Local verification

```bash
stripe listen --forward-to http://<site>/wp-json/kh-membership/v1/webhook/stripe
stripe trigger checkout.session.completed
```

Then inspect:
- Stripe delivery status `200`
- `wp_khm_processed_webhooks` row status transition `processing -> processed`

