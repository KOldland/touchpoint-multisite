# Membership Staging E2E Runbook (QA)

This runbook reproduces MEM-01..MEM-06 acceptance flow with deterministic evidence capture.

## 1) Preconditions checklist

- [ ] Staging deploy includes MEM-01..MEM-06 commits.
- [ ] Feature flags/toggles reviewed (transactional emails as intended for run).
- [ ] Stripe test keys and webhook secret configured.
- [ ] `stripe` CLI installed and authenticated.
- [ ] `KHM_ANON_SALT` present in runtime env.
- [ ] Test user account and admin account prepared.
- [ ] Golden fixtures available in repo.

Quick checks:

```bash
wp eval 'echo getenv("KHM_ANON_SALT") ? "KHM_ANON_SALT present\n" : "KHM_ANON_SALT missing\n";'
wp option get khm_membership_transactional_emails_enabled
```

## 2) Landing -> signup-init

```bash
curl -sS -X POST "https://staging.example.com/wp-json/kh-membership/v1/signup-init" \
  -H "Content-Type: application/json" \
  -d '{
    "schedule_id":"sch_123",
    "sponsor_id":"sp_456",
    "utm_source":"newsletter",
    "utm_medium":"email",
    "utm_campaign":"spring_launch",
    "phase_at_click":"attention",
    "idempotency_key":"a30ef20f-e6a5-4380-a8e9-190523f0de54",
    "consent":true,
    "client_reference":"staging-e2e",
    "plan_id":"pro_monthly"
  }' | tee artifacts/landing_submit.log
```

Expected output contains:

- `checkout_url`
- `session_id`
- `message=checkout_created`
- `temp_store_ttl_seconds=86400`

Promo validation check (must fail with `MBR_ERR_INVALID_PROMO`):

```bash
curl -sS -X POST "https://staging.example.com/wp-json/kh-membership/v1/signup-init" \
  -H "Content-Type: application/json" \
  -d '{
    "schedule_id":"sch_123",
    "idempotency_key":"11111111-1111-4111-8111-111111111111",
    "consent":true,
    "stripe_promotion_code":"promo_raw_unvalidated"
  }'
```

## 3) Trigger signed Stripe webhook (`checkout.session.completed`)

```bash
cd app/public/wp-content/plugins/khm-plugin
SIG=$(php tests/helpers/stripe_signature.php --secret="$KH_STRIPE_WEBHOOK_SECRET" --payload=tests/fixtures/golden/checkout_session_completed.json)

curl -i -X POST "https://staging.example.com/wp-json/khm/v1/webhooks/stripe" \
  -H "Content-Type: application/json" \
  -H "Stripe-Signature: ${SIG}" \
  --data-binary @tests/fixtures/golden/checkout_session_completed.json \
  | tee ../../../../../../artifacts/webhook_post.log
```

Expected webhook response:

- HTTP `200`
- JSON `status=queued`

## 4) Verify DB attribution and membership state

Capture before/after SQL as artifacts:

```bash
wp db query "SELECT id,user_id,user_email,schedule_id,sponsor_id,consent,utm_source,utm_campaign,reference,reference_hash,anonymized_at,created_at FROM wp_promotion_attribution ORDER BY id DESC LIMIT 20;" | tee artifacts/db_before.sql
wp db query "SELECT id,user_id,tier_id,status,started_at,updated_at FROM wp_user_membership ORDER BY id DESC LIMIT 20;" | tee -a artifacts/db_before.sql
```

After webhook worker runs:

```bash
wp db query "SELECT id,user_id,user_email,schedule_id,sponsor_id,consent,utm_source,utm_campaign,reference,reference_hash,anonymized_at,created_at FROM wp_promotion_attribution ORDER BY id DESC LIMIT 20;" | tee artifacts/db_after.sql
wp db query "SELECT id,user_id,tier_id,status,started_at,updated_at FROM wp_user_membership ORDER BY id DESC LIMIT 20;" | tee -a artifacts/db_after.sql
```

## 5) Landing-success verification

```bash
curl -sS "https://staging.example.com/wp-json/kh-membership/v1/landing-success?session_id=<SESSION_ID>" | tee artifacts/landing_success.json
```

Expected fields:

- `status` in `pending|complete|failed`
- `membership_status` in `active|trialing|pending|none`
- `ctas[]` populated

## 6) Email queue / audit verification

```bash
wp db query "SELECT id,template,status,attempt_count,created_at,updated_at FROM wp_khm_email_queue ORDER BY id DESC LIMIT 20;"
wp db query "SELECT created_at,event,context FROM wp_khm_membership_webhook_audit ORDER BY id DESC LIMIT 50;" | tee artifacts/audit_log.txt
```

Check for expected audit/telemetry lines (`membership.email.*`, `webhook.*`).

## 7) Admin checks (manual)

- Open member detail page and capture `artifacts/admin_screenshot.png`.
- Export reports CSV and save `artifacts/export.csv`.
- Validate redaction behavior for consent=false rows.

## 8) Anonymize and retention checks

```bash
wp khm anonymize_attribution --id=<ATTRIBUTION_ID> --dry-run
wp db query "SELECT id,user_email,utm_source,reference,reference_hash,anonymized_at FROM wp_promotion_attribution WHERE id=<ATTRIBUTION_ID>;" | tee artifacts/anonymize_before_after.sql
wp khm anonymize_attribution --id=<ATTRIBUTION_ID> --reason="staging-e2e" --execute
wp db query "SELECT id,user_email,utm_source,reference,reference_hash,anonymized_at FROM wp_promotion_attribution WHERE id=<ATTRIBUTION_ID>;" | tee -a artifacts/anonymize_before_after.sql

time wp khm retention:run --dry-run --chunk-size=1000 | tee artifacts/retention_dry_run.log
time wp khm retention:run --mode=anonymize --chunk-size=1000 | tee artifacts/retention_execute.log
```

## 9) DSAR request -> approve -> export evidence

User request:

```bash
curl -sS -X POST "https://staging.example.com/wp-json/kh-membership/v1/dsar/request" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: <USER_NONCE>" \
  -d '{"type":"export","ticket_id":"LEGAL-123"}' | tee artifacts/dsar_request.json
```

Admin approve:

```bash
curl -sS -X POST "https://staging.example.com/wp-json/kh-membership/v1/dsar/approve" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: <ADMIN_NONCE>" \
  -d '{"request_id":"<REQUEST_ID>","ticket_id":"LEGAL-123"}' | tee artifacts/dsar_approve.json
```

Expected output includes zip path (`file`) and row count.

## 10) Reconciliation flow

Use smoke harness:

```bash
php app/public/wp-content/plugins/kh-smma/scripts/paid04_signoff_smoke.php | tee artifacts/smoke-telemetry.json
```

Then verify rows/export:

```bash
wp db query "SELECT reconciliation_id,manifest_id,status,estimated_spend,actual_spend,discrepancy_percent,created_at FROM wp_kh_paid_reconciliations ORDER BY created_at DESC LIMIT 20;" | tee artifacts/reconciliation_rows.sql
```

Optional rerun endpoint:

```bash
curl -sS -X POST "https://staging.example.com/wp-json/kh-smma/v1/reconciliations/<RECONCILIATION_ID>/rerun" \
  -H "X-WP-Nonce: <ADMIN_NONCE>"
```

## 11) Playwright + axe acceptance

```bash
cd app/public/wp-content/plugins/khm-plugin/tests/UI
npm install
npx playwright install
PLAYWRIGHT_BASE_URL=https://staging.example.com npx playwright test landing_success_a11y.spec.js admin_membership_a11y.spec.js landing_success_snapshot.spec.js --reporter=list,html
```

Pass criteria:

- No serious/critical axe violations.
- Landing success snapshot passes.
- Report exported to `playwright-report/`.

## 12) Required artifact filenames

- `db_before.sql`
- `db_after.sql`
- `audit_log.txt`
- `smoke-telemetry.json`
- `landing_submit.log`
- `webhook_post.log`
- `admin_screenshot.png`
- `export.csv`
- `anonymize_before_after.sql`

Use [staging_evidence_template.md](staging_evidence_template.md) for PR paste-in.
