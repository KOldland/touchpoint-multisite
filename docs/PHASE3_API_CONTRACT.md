# Phase 3 API Contract

This document is the frontend contract for Phase 3. It reflects the current repo state on `integration/hardening` and is the reference point for all Phase 3 UI PRs.

## Conventions

- Base site URL examples assume `http://localhost:8000`.
- WordPress-authenticated SMMA routes require:
  - `Content-Type: application/json`
  - `X-WP-Nonce: <wp_rest nonce>`
- Idempotent mutation routes also require:
  - `Idempotency-Key: <uuid>`
- Optional trace header supported where noted:
  - `X-Trace-Id: <uuid>`

## 1. POST `/wp-json/kh-smma/v1/generate`

- Purpose: generate LinkedIn variants plus optional Google draft payload from a post context.
- Auth: authenticated WP user with `edit_posts` and valid `X-WP-Nonce`.

### Request

```json
{
  "post_id": 123,
  "blocks_summary": "Short summary of the published post.",
  "num_variants": 3,
  "tone": "Authority",
  "geo_targets": ["AU", "US"],
  "sponsor_context": {
    "sponsor_id": "42",
    "allowed_claims": ["ISO certified"]
  },
  "phase_tag": "Attention"
}
```

### Success `200`

```json
{
  "request_id": "req_123",
  "variants": [
    {
      "variant_id": "var_123",
      "linkedIn": {
        "variant_id": "var_123",
        "text": "Draft text",
        "rationale": "Why this copy was generated",
        "asset_hints": [
          {
            "type": "image",
            "description": "Suggested creative asset"
          }
        ],
        "platform": "linkedin",
        "compliance_status": "OK",
        "compliance_reason": "",
        "matched_rules": [],
        "ai_review_summary": "",
        "checked_at": "2026-03-08T12:00:00Z",
        "compliance": {
          "status": "OK",
          "reasons": []
        }
      },
      "google": {
        "ad_groups": []
      }
    }
  ],
  "provenance": {
    "prompt_hash": "sha256...",
    "fixture": "generate_awareness_ok.json",
    "model": "mock-llm"
  },
  "google_ad_compliance": []
}
```

### Errors

- `400 SMMA_ERR_SCHEMA_INVALID` when `post_id` or `blocks_summary` is missing.
- `400 SMMA_ERR_INVALID_LLM` when the LLM response is non-JSON or invalid.
- `401 kh_smma_bad_nonce` when nonce is missing or invalid.
- `403 kh_smma_forbidden` / `kh_smma_disabled` for permission or feature-flag failures.

## 2. POST `/wp-json/kh-smma/v1/variant/{variant_id}/edit`

- Purpose: save an editor revision and re-run compliance.
- Auth: authenticated WP user with `edit_posts` and valid `X-WP-Nonce`.
- Required headers:
  - `Idempotency-Key`
  - optional `X-Trace-Id`

### Request

```json
{
  "editor_user_id": "17",
  "text": "Updated copy",
  "asset_hints": [
    {
      "type": "image",
      "asset_id": "asset_1"
    }
  ],
  "metadata": {},
  "edit_reason": "Tightened claim wording"
}
```

### Success `200`

```json
{
  "variant_id": "var_123",
  "revision_id": "rev_123",
  "revision": {
    "variant_id": "var_123",
    "revision_id": "rev_123",
    "editor_user_id": "17",
    "edited_at": "2026-03-08T12:15:00Z",
    "previous_text": "Old copy",
    "updated_text": "Updated copy",
    "edit_reason": "Tightened claim wording"
  },
  "approval_status": "approved",
  "compliance": {
    "status": "OK",
    "reasons": []
  },
  "idempotent": false
}
```

### Errors

- `400 SMMA_ERR_SCHEMA_INVALID` when `variant_id` is missing or unknown.
- `409 SMMA_ERR_IDEMPOTENCY_CONFLICT` when `Idempotency-Key` is missing.
- `409 SMMA_ERR_COMPLIANCE_FAIL` when the revised text hard-fails compliance.

## 3. POST `/wp-json/kh-smma/v1/schedule`

- Purpose: create a schedule from a compliant variant.
- Auth: authenticated WP user with `edit_posts` or `kh_smma_schedule_posts` and valid nonce.
- Required headers:
  - `Idempotency-Key`
  - optional `X-Trace-Id`

### Request

```json
{
  "variant_id": "var_123",
  "sponsor_id": "42",
  "schedule_time": "2026-04-01T10:00:00Z",
  "boost_options": {
    "budget_cents": 10000,
    "currency": "AUD",
    "channels": ["linkedin"],
    "prioritize": "reach"
  },
  "mode": "sandbox"
}
```

### Success `200`

```json
{
  "schedule_id": "sched_123",
  "status": "queued",
  "approval_required": false,
  "approval_status": "approved",
  "compliance_status": "OK",
  "compliance_reason": "",
  "enqueued": true,
  "manifest": {
    "manifest_id": "man_123",
    "campaign": {
      "campaign_id": "camp_var_123",
      "title": "SMMA Schedule var_123"
    },
    "operations": [],
    "meta": {
      "sponsor_id": "42",
      "schedule_id": "sched_123",
      "idempotency_key": "uuid"
    }
  },
  "idempotent": false
}
```

### Errors

- `400 SMMA_ERR_SCHEMA_INVALID` for missing `variant_id` or `sponsor_id`.
- `403 SMMA_ERR_FORBIDDEN` when user lacks schedule capability.
- `409 SMMA_ERR_IDEMPOTENCY_CONFLICT` when `Idempotency-Key` is missing.
- `409 SMMA_ERR_COMPLIANCE_FAIL` when scheduling is blocked by compliance.
- blocked approval gate on downstream prepare/export paths returns:

```json
{
  "status": "blocked",
  "reason": "approval_required",
  "approval_status": "pending"
}
```

## 4. Sponsor approval endpoints

- Base: `/wp-json/kh-smma/v1/sponsor-approvals`
- Auth: authenticated WP user, nonce required.

### GET `/sponsor-approvals`

- Query params: `sponsor_id`, `status`, `date_from`, `date_to`, `search_term`, `page`, `per_page`

### Success `200`

```json
{
  "rows": [
    {
      "schedule_id": "sched_123",
      "variant_id": "var_123",
      "post_title": "March sponsor launch",
      "variant_preview": "Short preview used in the pending approvals admin list.",
      "sponsor_name": "Acme Sponsor",
      "submitter": "editor@example.com",
      "requested_schedule_date": "2026-03-11T09:00:00Z",
      "approval_status": "pending",
      "compliance_status": "WARN",
      "compliance_reason": "Performance claim requires sponsor sign-off.",
      "can_approve": true,
      "permission_message": ""
    }
  ],
  "total": 1,
  "page": 1,
  "per_page": 25,
  "total_pages": 1,
  "sponsors": [],
  "permissions": {
    "can_manage_approvals": true,
    "denied_message": ""
  }
}
```

### POST `/sponsor-approvals/review-started`

```json
{
  "schedule_ids": ["sched_123"],
  "reviewer_user_id": 17,
  "timestamp": 1770000000
}
```

### POST `/sponsor-approvals/approve`

```json
{
  "schedule_ids": ["sched_123"],
  "reviewer_user_id": 17,
  "review_notes": "Approved for sponsor policy"
}
```

### POST `/sponsor-approvals/reject`

```json
{
  "schedule_ids": ["sched_123"],
  "reviewer_user_id": 17,
  "review_notes": "Claim needs substantiation"
}
```

### GET `/sponsor-approvals/history?schedule_id=sched_123`

```json
{
  "schedule_id": "sched_123",
  "history": []
}
```

## 5. POST `/wp-json/kh-membership/v1/signup-init`

- Purpose: create hosted Stripe Checkout session for landing/signup flow.
- Auth: public.
- Required body fields:
  - `schedule_id`
  - `idempotency_key` as UUID
- Invalid promo handling is server-enforced.

### Request

```json
{
  "schedule_id": "sched_123",
  "sponsor_id": "42",
  "utm_source": "linkedin",
  "utm_medium": "paid_social",
  "utm_campaign": "awareness",
  "phase_at_click": "Attention",
  "consent": true,
  "profile_marketing_optin": false,
  "promo_code": "WELCOME10",
  "idempotency_key": "11111111-1111-4111-8111-111111111111"
}
```

### Success `201`

```json
{
  "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_123",
  "session_id": "cs_test_123",
  "message": "checkout_created",
  "temp_store_ttl_seconds": 86400
}
```

### Error: invalid promo `400`

```json
{
  "error": "Invalid promotion code.",
  "code": "MBR_ERR_INVALID_PROMO",
  "message": "Invalid promotion code.",
  "retryable": false
}
```

### Stored Checkout metadata shape

Frontend should assume the checkout session is created with metadata keys used later by webhooks and landing success:

```json
{
  "wp_user_id": "17",
  "schedule_id": "sched_123",
  "sponsor_id": "42",
  "utm_source": "linkedin",
  "utm_medium": "paid_social",
  "utm_campaign": "awareness",
  "utm_term": "",
  "utm_content": "",
  "idempotency_key": "11111111-1111-4111-8111-111111111111",
  "consent": "true",
  "profile_marketing_optin": "false"
}
```

Validated promo is attached to Stripe `discounts[].promotion_code` only when server validation succeeds.

## 6. POST `/wp-json/kh-membership/v1/signup`

- Purpose: direct membership signup and checkout/trial orchestration.
- Auth: public.

### Request

```json
{
  "email": "user@example.com",
  "tier": "premium",
  "schedule_id": "sched_123",
  "sponsor_id": "42",
  "utm_source": "linkedin",
  "utm_medium": "paid_social",
  "utm_campaign": "awareness",
  "consent": true,
  "profile_marketing_optin": false,
  "promo_code": "WELCOME10"
}
```

### Success: trial or membership activation `200`

```json
{
  "success": true,
  "status": "trial",
  "user_id": 17,
  "membership": {
    "tier_id": 3,
    "tier_slug": "premium",
    "status": "trial",
    "trial_ends_at": "2026-03-15T12:00:00Z"
  }
}
```

### Success: Stripe checkout required `200`

```json
{
  "success": true,
  "status": "requires_payment_method",
  "redirect_url": "https://checkout.stripe.com/pay/cs_test_123"
}
```

### Invalid promo `400`

```json
{
  "error": "Invalid promotion code.",
  "code": "MBR_ERR_INVALID_PROMO",
  "message": "Invalid promotion code.",
  "retryable": false
}
```

## 7. POST `/wp-json/khm/v1/checkout/subscription`

- Purpose: generic membership checkout from modal/button flows.
- Auth: public, or current WP user session.

### Request

```json
{
  "membership_level_id": 3,
  "email": "user@example.com",
  "schedule_id": "sched_123",
  "sponsor_id": "42",
  "utm_source": "linkedin",
  "utm_medium": "paid_social",
  "utm_campaign": "awareness",
  "profile_marketing_optin": false,
  "consent": true,
  "promo_code": "WELCOME10",
  "idempotency_key": "11111111-1111-4111-8111-111111111111"
}
```

### Success `200`

```json
{
  "url": "https://checkout.stripe.com/c/pay/cs_test_123"
}
```

### Invalid promo `400`

```json
{
  "code": "MBR_ERR_INVALID_PROMO",
  "message": "Invalid promotion code.",
  "retryable": false
}
```

## 8. GET `/wp-json/kh-membership/v1/landing-success?session_id=...`

- Purpose: success-state payload for landing/checkout completion.
- Auth: public.
- Consent gates the presence of sponsor and attribution details.

### Success `200`

```json
{
  "session_id": "cs_test_123",
  "status": "complete",
  "membership_status": "active",
  "schedule": {
    "id": "sched_123",
    "title": "Membership",
    "recommended_post_time": "2026-03-09 10:00 UTC",
    "boost_copy": "Suggested post copy"
  },
  "sponsor": {
    "id": "42",
    "name": "Example Sponsor",
    "logo_url": "https://example.com/logo.png",
    "accent_color": "#2271b1",
    "blurb": "Sponsor copy"
  },
  "consent": true,
  "attribution": {
    "schedule_id": "sched_123",
    "sponsor_id": "42",
    "utm_source": "linkedin",
    "utm_medium": "paid_social",
    "utm_campaign": "awareness",
    "phase_at_click": "Attention"
  },
  "ctas": [],
  "message": "Your membership is active. Welcome aboard!",
  "reference": "LS-deadbeef"
}
```

### Consent-gated behavior

- If `consent=false`, frontend must assume:
  - `sponsor` is `null`
  - `attribution` is `null`
  - only generic success copy should be shown

## 9. POST `/wp-json/kh-membership/v1/landing-telemetry`

- Purpose: record frontend success/CTA telemetry.
- Auth: public.

### Request

```json
{
  "metric": "landing.cta.clicked",
  "session_id": "cs_test_123",
  "cta_name": "Open member dashboard",
  "cta_action": "navigate"
}
```

### Success `200`

```json
{
  "ok": true
}
```

## Frontend telemetry contract

Frontend should emit or expect the following event names already present in the repo:

### SMMA

- `generate.request`
- `generate.response`
- `variant.edit`
- `compliance.check`
- `schedule.create`
- `schedule.dispatch`
- `schedule.blocked`
- `sponsor.approval.review_started`
- `sponsor.approval.history_viewed`

### Membership / checkout

- `landing.submit`
- `landing.success`
- `landing.cta.clicked`
- `webhook.invalid_signature`
- `webhook.rate_limit.exceeded`
- `membership.attribution.created`

## Frontend implementation notes

- Current repo stack is WordPress + jQuery, not React/Vue.
- Existing UI entry points worth extending rather than replacing:
  - `app/public/wp-content/plugins/kh-smma/assets/js/smma-admin.js`
  - `app/public/wp-content/plugins/kh-smma/assets/js/pending-approvals.js`
  - `app/public/wp-content/plugins/kh-smma/assets/js/calendar-modal.js`
  - `app/public/wp-content/plugins/khm-plugin/assets/js/membership-modal.js`
  - `app/public/wp-content/plugins/khm-plugin/assets/js/landing.js`
- Existing landing/signup UI already uses:
  - `/kh-membership/v1/signup-init`
  - `/kh-membership/v1/landing-success`
  - `/kh-membership/v1/landing-telemetry`

## Open constraints for later Phase 3 PRs

- Image upload/layout preview endpoints are not formalized in the current backend contract yet. Frontend should not invent them; that needs a follow-up backend contract or explicit reuse of an existing media endpoint.
- Price Review spreadsheet write-back API is not yet formalized in current repo contracts. Treat that as a separate contract step before UI implementation.
