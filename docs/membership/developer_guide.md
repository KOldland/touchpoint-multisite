# Membership Developer Guide (Builders)

## Canonical references

- Signup-init schema: [../contracts/signup-init.json](../contracts/signup-init.json)
- Landing-success schema: [../contracts/landing_success.json](../contracts/landing_success.json)
- Attribution schema: [../contracts/membership_attribution.json](../contracts/membership_attribution.json)
- Paid adapter manifest: [../contracts/paid_adapter_manifest.json](../contracts/paid_adapter_manifest.json)
- Paid reconciliation schema: [../contracts/paid_reconciliation.json](../contracts/paid_reconciliation.json)
- CIC golden fixtures contract: [../contracts/cic-01-golden-contract.json](../contracts/cic-01-golden-contract.json)

## Endpoints used by frontend and middleware

- `POST /wp-json/kh-membership/v1/signup-init`
- `GET /wp-json/kh-membership/v1/landing-success?session_id=<id>`
- `POST /wp-json/kh-membership/v1/webhook/stripe` (also exposed as `/wp-json/khm/v1/webhooks/stripe`)
- `POST /wp-json/kh-membership/v1/dsar/request`
- `POST /wp-json/kh-membership/v1/dsar/approve`
- Reconciliation APIs (SMMA):
  - `GET /wp-json/kh-smma/v1/reconciliations`
  - `GET /wp-json/kh-smma/v1/reconciliations/{id}`
  - `POST /wp-json/kh-smma/v1/reconciliations/{id}/rerun`

## Full flow example (landing -> checkout -> webhook -> membership)

1. Frontend submits signup-init with consent and idempotency key.
2. API returns `checkout_url` + `session_id`.
3. Client redirects user to Stripe Checkout.
4. Stripe sends signed `checkout.session.completed` webhook.
5. Webhook creates/updates membership and attribution.
6. Frontend polls landing-success by `session_id` and renders success CTA set.

### Example: signup-init request

```http
POST /wp-json/kh-membership/v1/signup-init
Content-Type: application/json

{
  "schedule_id": "sch_123",
  "sponsor_id": "sp_456",
  "utm_source": "newsletter",
  "utm_medium": "email",
  "utm_campaign": "spring_launch",
  "phase_at_click": "attention",
  "idempotency_key": "a30ef20f-e6a5-4380-a8e9-190523f0de54",
  "consent": true,
  "client_reference": "landing-hero-cta",
  "plan_id": "pro_monthly"
}
```

Success response (201):

```json
{
  "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_...",
  "session_id": "cs_test_89467c24312293ea",
  "message": "checkout_created",
  "temp_store_ttl_seconds": 86400
}
```

### Example: webhook call (`checkout.session.completed`)

Endpoint: `POST /wp-json/khm/v1/webhooks/stripe`

Build signature header from raw body:

```bash
cd app/public/wp-content/plugins/khm-plugin
SIG=$(php tests/helpers/stripe_signature.php --secret="$KH_STRIPE_WEBHOOK_SECRET" --payload=tests/fixtures/golden/checkout_session_completed.json)
curl -i -X POST "https://staging.example.com/wp-json/khm/v1/webhooks/stripe" \
  -H "Content-Type: application/json" \
  -H "Stripe-Signature: ${SIG}" \
  --data-binary @tests/fixtures/golden/checkout_session_completed.json
```

Expected response:

```json
{ "status": "queued", "id": "evt_checkout_signed_001", "type": "checkout.session.completed" }
```

### Example: landing-success query

```http
GET /wp-json/kh-membership/v1/landing-success?session_id=cs_test_89467c24312293ea
```

Example response (contract-conformant):

```json
{
  "session_id": "cs_test_89467c24312293ea",
  "status": "complete",
  "membership_status": "active",
  "schedule": { "id": "sch_123", "title": "Spring Launch" },
  "sponsor": { "id": "sp_456", "name": "Acme" },
  "consent": true,
  "attribution": {
    "schedule_id": "sch_123",
    "sponsor_id": "sp_456",
    "utm_source": "newsletter",
    "utm_medium": "email",
    "utm_campaign": "spring_launch",
    "phase_at_click": "attention"
  },
  "ctas": [{ "name": "Manage membership", "action": "manage", "url": "https://staging.example.com/account" }],
  "message": "Your membership is active. Welcome aboard!",
  "reference": "LS-a1b2c3d4"
}
```

## Error mapping (`MBR_ERR_*`)

| Code | Friendly message | Retryable | UI mapping |
|---|---|---:|---|
| `MBR_ERR_INVALID_ATTR` | We could not start checkout. Check referral data and try again. | sometimes | show inline form error + retry button |
| `MBR_ERR_INVALID_SPONSOR` | This sponsor link is invalid for the selected schedule. | no | show blocking validation state |
| `MBR_ERR_100` | Enter a valid email address. | no | highlight email field |
| `MBR_ERR_101` | Select a membership plan. | no | highlight plan selector |
| `MBR_ERR_103` | Requested tier is unavailable. | no | reload plan options |
| `MBR_ERR_104` | Requested plan is unavailable. | no | reload plan options |
| `MBR_ERR_105` | Email already belongs to another account context. | no | show account recovery path |
| `MBR_ERR_106` | We could not create your account. Try again. | yes | retry + support fallback |
| `MBR_ERR_107` | Membership already active for this email. | no | route to account/login |
| `MBR_ERR_200` | Billing service is not configured. | no | show maintenance state |
| `MBR_ERR_201` | Billing plan is missing configuration. | no | show maintenance state |
| `MBR_ERR_202` | Checkout URL was not returned. | yes | retry flow |
| `MBR_ERR_203` | Checkout creation failed. Please retry. | yes | retry flow |

## Client snippet (idempotency + signup-init + landing-success polling)

```js
function uuidV4() {
  if (crypto?.randomUUID) return crypto.randomUUID();
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

async function startCheckout(payload) {
  const body = {
    ...payload,
    idempotency_key: payload.idempotency_key || uuidV4()
  };

  const res = await fetch('/wp-json/kh-membership/v1/signup-init', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  const json = await res.json();
  if (!res.ok) throw json;
  window.location.assign(json.checkout_url);
}

async function pollLandingSuccess(sessionId) {
  const delays = [1000, 2000, 4000, 8000, 8000];
  for (const delay of delays) {
    const res = await fetch(`/wp-json/kh-membership/v1/landing-success?session_id=${encodeURIComponent(sessionId)}`);
    const json = await res.json();
    if (json.status === 'complete' || json.status === 'failed') return json;
    await new Promise(r => setTimeout(r, delay));
  }
  return { status: 'pending', membership_status: 'pending' };
}
```

## Frontend integration guidance

- Landing page shortcode pattern: `[khm_landing_page schedule_id="sch_123" sponsor_id="sp_456"]`
- Consent semantics:
  - `consent=true`: attribution may include UTM/sponsor values.
  - `consent=false`: server stores minimal marker and redacts attribution payload fields.
- Success rendering:
  - Render `message`, `membership_status`, and `ctas[]` from landing-success payload.
  - Hide attribution UI when `consent=false` or `attribution=null`.

## Accessibility checklist for success UI

- Consent control present when required: checkbox with associated label.
- Success container exists: `.khm-success-page` or `.khm-success-modal` (or `[data-khm-success]`).
- Status announcement region exists with `aria-live`.
- At least one focusable control in success surface (CTA link/button).
- Playwright + axe should report zero serious/critical violations for landing and success surfaces.

## MockLLM + golden fixture mapping (SMMA/integration)

Golden root: `app/public/wp-content/plugins/kh-smma/tests/fixtures/golden/`

- Membership fixtures:
  - `signup_init_success.json`
  - `landing_success_complete.json`
  - `checkout_session_completed.json`
  - `invoice_paid.json`
- Paid adapter fixtures:
  - `paid_adapter_dry_run_manifest.json`
  - `paid_adapter_dry_run_response.json`
  - `paid_adapter_execute_response.json`
  - `linkedin_sandbox_execute_response.json`
  - `google_sandbox_execute_response.json`

Deterministic env knobs:

```bash
export KH_SMMA_TEST_MODE=ci
export KH_SMMA_GOLDEN_FIXTURE=signup_init_success.json
```
