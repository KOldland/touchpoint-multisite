# Stripe Webhook Runbook (KHM Membership)

## Scope

Canonical endpoint: `POST /wp-json/khm/v1/webhooks/stripe`

Legacy endpoint (only if legacy membership webhook handler is enabled): `POST /wp-json/kh-membership/v1/webhook/stripe`

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

Required deployment secrets:
- `KH_STRIPE_WEBHOOK_SECRET`
- `KH_STRIPE_SECRET_KEY`
- optional split routing: `KH_STRIPE_WEBHOOK_SECRET_MARKETING`, `KH_STRIPE_WEBHOOK_SECRET_BILLING`

Never store webhook secrets in code, docs, or WordPress options.

## Secret rotation procedure

1. Rotate webhook signing secret in Stripe endpoint settings.
2. Update deployment env var `KH_STRIPE_WEBHOOK_SECRET` (and split env vars if used).
3. Deploy/restart runtime so env values are active.
4. Send Stripe test event (`product.updated` or `invoice.paid`) and confirm `200`.
5. Verify events are written to `wp_khm_webhook_events` and `webhook.invalid_signature` does not spike.
6. Remove old secret after overlap window (if dual-secret overlap was used).

## Rate limit operations

Rate-limit keys (transients):
- `khm_webhook_rate:{ip_hash}:{minute_bucket}`
- `khm_webhook_badsig:{ip_hash}`
- `khm_webhook_badsig_total`
- `khm_webhook_block:{ip_hash}`
- `khm_webhook_block_level:{ip_hash}`

To unblock an IP immediately (example hash):

```bash
wp transient delete "khm_webhook_block:<md5_of_ip>"
wp transient delete "khm_webhook_block_level:<md5_of_ip>"
```

Progressive blocking defaults:
- bad signatures: `>10` in `60s` => block
- request rate: `>60/min` => throttle with `429` + `Retry-After`
- block TTL progression: `1m -> 5m -> 25m` (capped by max TTL)

Test mode:
- set `KHM_WEBHOOK_RATE_LIMIT_TEST_MODE=true` to use lower thresholds for repeatable staging/unit verification.

## Event processing model

1. Webhook request verifies Stripe signature.
2. Event ID is checked against `wp_khm_webhook_events` (idempotency).
3. Allowed events are processed inline by `KHM\Rest\WebhooksController`.
4. Endpoint returns `200` with `processed`, `duplicate`, or `ignored`.
5. Event is written once to `wp_khm_webhook_events` with metadata.

## Duplicate and replay behavior

- Duplicate Stripe deliveries are safe.
- Existing processed events return `200` (`status=duplicate`) without re-applying side effects.

## Operator actions

Admin page: `Memberships -> Webhook Events` (`page=khm-membership-webhooks`) is tied to the legacy membership webhook store (`wp_khm_processed_webhooks`), not the canonical `khm/v1` idempotency table.

Available actions (legacy handler path):
- `Requeue`
- `Mark Processed`
- `Mark Failed`

## Payload storage and PII policy (legacy handler only)

Payload mode filter: `khm_membership_webhook_payload_mode`
- `excerpt` (default): redacted, truncated payload excerpt
- `hash`: hash-only storage (payload omitted)
- `full`: redacted full payload (truncated to safeguard size)

Retention filter: `khm_membership_webhook_retention_days` (default `30` days)

Policy constants (optional, override defaults):
- `KHM_MEMBERSHIP_WEBHOOK_RATE_LIMIT_MAX_REQUESTS` (default `100`)
- `KHM_MEMBERSHIP_WEBHOOK_RATE_LIMIT_WINDOW` (default `60` seconds)
- `KHM_MEMBERSHIP_WEBHOOK_PAYLOAD_MODE` (default `excerpt`)
- `KHM_MEMBERSHIP_WEBHOOK_RETENTION_DAYS` (default `30`)

## Monitoring and thresholds

Telemetry hook: `khm_membership_webhook_telemetry`

Safety threshold policy doc: `docs/MEMBERSHIP_OPS_SAFETY_THRESHOLDS.md`

Track:
- `webhook.received`
- `webhook.invalid_signature`
- `webhook.rate_limit.exceeded`
- `webhook.rate_limit.blocked`
- `webhook.processed`
- `webhook.failed`
- `webhook.queue_failed`

Suggested alerts:
- invalid signature rate > 5% over 15m
- rate-limit exceeded > 20 events in 5m
- blocked IP count > 5 in 15m
- failed processing rate > 2% over 15m
- queue failures > 0 over 5m
- backlog of `processing` events older than 10m

## Admin permissions validation (staging)

1. Log in as non-admin user (no `manage_options`).
2. Visit `wp-admin/admin.php?page=khm-settings` and `wp-admin/admin.php?page=khm-email-preview`.
3. Expected: denied (`403`/permission error) and access attempt logged as `unauthorized_admin_access`.
4. For ingestion routes, call `/wp-json/khm/v1/ingest/ga4` without `x-khm-ingest-key`; expected `401`.

## Manual triage SQL

Recent failures (legacy table):

```sql
SELECT event_id, event_type, status, attempts, notes, updated_at
FROM wp_khm_processed_webhooks
WHERE status = 'failed'
ORDER BY updated_at DESC
LIMIT 50;
```

Stuck processing (legacy table):

```sql
SELECT event_id, event_type, status, attempts, updated_at
FROM wp_khm_processed_webhooks
WHERE status = 'processing'
  AND updated_at < (UTC_TIMESTAMP() - INTERVAL 10 MINUTE)
ORDER BY updated_at ASC;
```

## Local verification

```bash
stripe listen --forward-to http://<site>/wp-json/khm/v1/webhooks/stripe
stripe trigger checkout.session.completed
```

Then inspect:
- Stripe delivery status `200`
- `wp_khm_webhook_events` contains a new row for the Stripe `event_id`

## Staging UAT script (copy/paste)

### 1) Confirm webhook endpoint is reachable

```bash
curl -i -X POST "https://<staging-domain>/wp-json/khm/v1/webhooks/stripe" \
  -H "Content-Type: application/json" \
  --data '{"id":"evt_probe","type":"invoice.paid","data":{"object":{"customer":"cus_probe"}}}'
```

Expected:
- If unsigned request: `400 Invalid signature`
- Route exists and does not return `404 rest_no_route`

### 2) Forward Stripe events to staging and trigger an event

```bash
stripe listen --forward-to "https://<staging-domain>/wp-json/khm/v1/webhooks/stripe"
stripe trigger checkout.session.completed
```

Expected:
- Stripe dashboard delivery = `200`
- New row appears in `wp_khm_webhook_events`

### 3) Validate idempotency (duplicate replay)

In Stripe dashboard, re-send the same event ID once.

Expected:
- Endpoint still returns `200`
- No duplicate side effects in membership/credits records
- `wp_khm_webhook_events` still contains one record for that `event_id`

### 4) Validate failure retry behavior (canonical route)

1. Trigger an event expected to fail handler logic (staging-only controlled fault).
2. Confirm endpoint returns `500` so Stripe retries delivery.
3. Fix root cause.
4. Re-send event from Stripe dashboard.

Expected:
- Re-delivery succeeds with `200`.
- Event appears once in `wp_khm_webhook_events`.

### 5) Validate cleanup retention

Canonical `wp_khm_webhook_events` cleanup is managed by `DatabaseIdempotencyStore::cleanup()` when invoked by maintenance tooling; legacy cron hook below applies only to `wp_khm_processed_webhooks`:

```bash
wp cron event run khm_membership_webhook_cleanup
```
