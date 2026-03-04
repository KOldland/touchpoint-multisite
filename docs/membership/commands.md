# Membership Commands Quick Reference

## Webhook and DLQ

```bash
# List dead letters
wp khm membership-webhook-dead-letters --last=50

# Replay one
wp khm membership-webhook-dead-letters-replay --id=123

# Replay batch
wp khm membership-webhook-dead-letters-replay --all-open --limit=20
```

Compatibility wrapper in some environments (not shipped in this repo):

```bash
php bin/khm requeue:webhook <event_id>
```

## Anonymize and retention

```bash
# Anonymize single row
wp khm anonymize_attribution --id=123 --dry-run
wp khm anonymize_attribution --id=123 --reason="ops-ticket"

# Batch anonymize by filter
wp khm anonymize_attribution --batch --filter="consent=false AND created_at < '2025-01-01'" --limit=1000 --dry-run
wp khm anonymize_attribution --batch --filter="consent=false AND created_at < '2025-01-01'" --limit=1000

# Retention
wp khm retention:run --dry-run --chunk-size=1000
wp khm retention:run --mode=anonymize --chunk-size=1000
```

## DSAR API

```bash
# User request (export/delete/anonymize)
curl -X POST "https://staging.example.com/wp-json/kh-membership/v1/dsar/request" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: <USER_NONCE>" \
  -d '{"type":"export","ticket_id":"LEGAL-123"}'

# Admin approval
curl -X POST "https://staging.example.com/wp-json/kh-membership/v1/dsar/approve" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: <ADMIN_NONCE>" \
  -d '{"request_id":"<REQUEST_ID>","ticket_id":"LEGAL-123"}'
```

## Signup-init and landing-success

```bash
curl -X POST "https://staging.example.com/wp-json/kh-membership/v1/signup-init" \
  -H "Content-Type: application/json" \
  -d '{"schedule_id":"sch_123","idempotency_key":"a30ef20f-e6a5-4380-a8e9-190523f0de54","consent":true}'

curl "https://staging.example.com/wp-json/kh-membership/v1/landing-success?session_id=<SESSION_ID>"
```

## Signed webhook fixture post

```bash
cd app/public/wp-content/plugins/khm-plugin
SIG=$(php tests/helpers/stripe_signature.php --secret="$KH_STRIPE_WEBHOOK_SECRET" --payload=tests/fixtures/golden/checkout_session_completed.json)
curl -X POST "https://staging.example.com/wp-json/khm/v1/webhooks/stripe" \
  -H "Content-Type: application/json" \
  -H "Stripe-Signature: ${SIG}" \
  --data-binary @tests/fixtures/golden/checkout_session_completed.json
```

## Reconciliation

```bash
php app/public/wp-content/plugins/kh-smma/scripts/paid04_signoff_smoke.php

curl -X POST "https://staging.example.com/wp-json/kh-smma/v1/reconciliations/<RECONCILIATION_ID>/rerun" \
  -H "X-WP-Nonce: <ADMIN_NONCE>"

wp db query "SELECT reconciliation_id,manifest_id,status,discrepancy_percent,created_at FROM wp_kh_paid_reconciliations ORDER BY created_at DESC LIMIT 20;"
```

## Useful verification snippets

```bash
# Confirm salt present
wp eval 'echo getenv("KHM_ANON_SALT") ? "KHM_ANON_SALT present\n" : "KHM_ANON_SALT missing\n";'

# Email toggle
wp option get khm_membership_transactional_emails_enabled
wp option update khm_membership_transactional_emails_enabled 1
wp option update khm_membership_transactional_emails_enabled 0
```
