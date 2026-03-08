# Membership & Attribution API Contract

This document defines the formal API contract for the core Membership & Attribution endpoints. It serves as a locked agreement for UI/UX development and testing.

---

## 1. POST /wp-json/kh-membership/v1/attribution

This endpoint records a conversion event (e.g., signup, trial start, demo request) and attributes it to a specific promotion schedule and/or sponsor. It is designed to be called from the frontend when a user performs a key conversion action.

### Inputs

```json
{
  "conversion_type": "signup",
  "schedule_id": 99,
  "sponsor_id": 12,
  "user_id": 123,
  "user_email": "test@example.com",
  "plan_id": 1,
  "phase_at_click": "Attention",
  "utm_source": "newsletter",
  "utm_medium": "email",
  "utm_campaign": "q1-promo",
  "utm_term": "saas-tools",
  "utm_content": "button-cta",
  "reference_metadata": {
    "clicked_element_id": "hero-cta-button",
    "page_url": "https://example.com/landing"
  }
}
```

-   **Required:**
    -   `conversion_type` (string): The type of conversion. Must be one of: `signup`, `trial`, `paid`, `demo_request`.
-   **Optional:**
    -   `schedule_id` (integer): The ID of the `kh_smma_schedule`.
    -   `sponsor_id` (integer): The ID of the sponsor.
    -   `user_id` (integer): The WordPress user ID, if the user is logged in.
    -   `user_email` (string): The user's email address. Required if `user_id` is not present.
    -   `plan_id` (integer): The `membership_tier` ID the user is converting on.
    -   `phase_at_click` (string): The user's engagement phase (e.g., 'Attention', 'Acceptance') at the time of conversion.
    -   `utm_*` (string): Standard UTM parameters.
    -   `reference_metadata` (object): Any additional JSON-serializable data for auditing purposes.

### Guarantees & Idempotency

-   **Idempotency:** The endpoint prevents duplicate records. A unique key is derived from (`user_id` or `user_email`) + `schedule_id` + `conversion_type`. A subsequent identical request made within **10 minutes** of the first will not create a new record and will instead return a `200 OK` response with the `id` of the original attribution record.
-   **Permissions:** This is a public-facing endpoint and will be rate-limited to prevent abuse.

### Responses

**Success (200 OK):**
```json
{
    "success": true,
    "id": 456
}
```

**Error (400 Bad Request):**
```json
{
    "error": "invalid conversion_type",
    "details": "Allowed values are: signup, trial, paid, demo_request"
}
```

---

## 2. POST /wp-json/kh-membership/v1/signup

This server-side endpoint handles the creation of a user, a Stripe customer, and a subscription (or trial). It is the canonical entry point for initiating a membership.

### Inputs

```json
{
  "email": "test@example.com",
  "plan_id": 1,
  "user_id": 123,
  "schedule_id": 99,
  "sponsor_id": 12,
  "utm_source": "newsletter",
  "utm_medium": "email",
  "utm_campaign": "q1-promo",
  "phase_at_click": "Attention",
  "idempotency_key": "a30ef20f-e6a5-4380-a8e9-190523f0de54",
  "consent": true
}
```

-   **Required:**
    -   `email` (string): A valid email address for the new user.
    -   `plan_id` (integer): The ID of the `membership_tier` to subscribe to.
-   **Optional:**
    -   `user_id` (integer): The WordPress user ID. If provided, the system will link the membership to this existing user. If omitted, a new WordPress user will be created.
    -   Canonical attribution payload:
        - `schedule_id` (integer)
        - `sponsor_id` (integer)
        - `utm_source` / `utm_medium` / `utm_campaign` (string)
        - `phase_at_click` (string)
        - `idempotency_key` (string UUID)
        - `consent` (boolean; when `false`, UTM/sponsor attribution is not persisted)

### Behavior

-   **Attribution:** If a `schedule_id` is provided, the endpoint will first check if a `promotion_attribution` record already exists for the user/email and schedule. If not, it will create one.
-   **User Creation:** If no `user_id` is provided, a new WordPress user is created with the provided email.
-   **Payment:** The endpoint orchestrates the creation of a Stripe Customer and a Subscription (or trial). This may involve returning a redirect URL to a Stripe Checkout session.

### Responses

**Success - Redirect to Checkout (200 OK):**
*Returned when payment confirmation is required.*
```json
{
    "success": true,
    "status": "requires_payment_method",
    "redirect_url": "https://checkout.stripe.com/pay/cs_test_..."
}
```

**Success - Trial Started (200 OK):**
*Returned for free trial plans that do not require immediate payment.*
```json
{
    "success": true,
    "status": "trialing",
    "user_id": 123,
    "membership": {
        "tier_id": 1,
        "status": "trialing",
        "trial_ends_at": "2026-02-18T12:00:00Z"
    }
}
```

**Error Conditions (for UI handling):**
-   `400 Bad Request`: Invalid `email` format or plan metadata.
-   `409 Conflict`: A user with this email already has an active subscription.
-   `500 Internal Server Error`: A failure occurred during interaction with the Stripe API.

Structured error response schema:
```json
{
  "error": "invalid email",
  "code": "MBR_ERR_100",
  "message": "invalid_email",
  "details": { "field": "email" },
  "help_url": "https://example.com/support/",
  "retryable": false,
  "support_code": "MBR_ERR_100-ABC123"
}
```

---

## 3. GET /wp-json/kh-membership/v1/status

Retrieves the current membership status and entitlements for an authenticated user.

### Inputs

-   **Query Parameter:** `user_id` (integer, required): The ID of the user to query.
-   **Permissions:** This endpoint is private and requires an authenticated session. The request will only succeed if the authenticated user's ID matches the `user_id` parameter.

### Guarantees & Response Payload

The response is guaranteed to contain the `status` field. All other fields are provided if applicable.

**Success (200 OK):**
```json
{
    "user_id": 123,
    "tier": {
        "id": 1,
        "slug": "premium",
        "name": "Premium Plan"
    },
    "status": "active",
    "trial_ends_at": null,
    "started_at": "2026-01-15T10:00:00Z",
    "cancelled_at": null,
    "renews_at": "2026-03-15T10:00:00Z"
}
```

-   **`status` (string):** One of the following values:
    -   `trialing`: User is in a free trial period.
    -   `active`: User has an active, paid subscription.
    -   `past_due`: Payment has failed, but the subscription is still in a grace period.
    -   `unpaid`: Payment has failed, and the grace period is over. Access is revoked.
    -   `cancelled`: User has cancelled their subscription. Access may persist until the end of the billing period.
    -   `none`: User has no membership record.

---

## 4. POST /wp-json/kh-membership/v1/webhook/stripe

This endpoint receives and processes events from Stripe to keep membership status synchronized.

### Inputs

-   The request body is a Stripe `Event` object.
-   The `Stripe-Signature` header must be present for verification.

### Behavior & Side Effects

The handler is responsible for updating the `user_membership` table based on Stripe events.

-   **Expected Event Types:**
    -   `checkout.session.completed`: A checkout was successful. Sets `status` to `active` (or `trialing`) and populates Stripe customer/subscription IDs.
    -   `invoice.paid`: A recurring payment succeeded. Ensures `status` is `active`.
    -   `invoice.payment_failed`: A payment failed. Sets `status` to `past_due`.
    -   `customer.subscription.updated`: A subscription was changed (e.g., upgraded, cancelled). Updates `tier_id`, `status`, `cancelled_at`.
    -   `customer.subscription.deleted`: A subscription has ended. Sets `status` to `cancelled`.

-   **Email side effects:**
    -   `checkout.session.completed` sends idempotent welcome email (`welcome` template).
    -   `invoice.paid` and immediate paid checkout sends idempotent payment email (`payment_confirmation` template).
    -   Telemetry events: `membership.email.welcome.sent`, `membership.email.payment.sent`, `membership.email.failed`.
    -   Safety toggle: emails are gated by option `khm_membership_transactional_emails_enabled` (admin configurable).

### Guarantees & Idempotency

-   **Idempotency:** The handler is idempotent. It logs the `id` of each processed Stripe event and will not re-process an event it has already handled successfully.
-   **Response:** The endpoint will return a `200 OK` response to Stripe to acknowledge successful receipt of an event. Any other status code indicates a failure and may cause Stripe to retry the webhook.
