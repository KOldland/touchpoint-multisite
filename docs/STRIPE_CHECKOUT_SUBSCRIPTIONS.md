# Stripe Checkout Subscriptions (Backend)

## Endpoint
`POST /wp-json/khm/v1/checkout/subscription`

### Input
- `membership_level_id` (int, required)
- `email` (string, optional; required for guests)

### Output
- `{ "url": "https://checkout.stripe.com/..." }`

### Behavior
- Validates membership level exists.
- Resolves Stripe Price ID using this deterministic order:
  1. `khm_stripe_membership_price_map` filter (string or array)
  2. `khm_stripe_membership_price_map` option (array of `level_id => price_id`)
  3. Membership level meta key `stripe_price_id`
- Creates Stripe Checkout Session with:
  - `mode = subscription`
  - `line_items = [{ price: PRICE_ID, quantity: 1 }]`
  - `success_url`, `cancel_url` (filterable)
  - `customer_email`
  - `allow_promotion_codes = true`
  - `metadata`: `purchase_type`, `membership_level_id`, `user_id`

## Webhook Activation
Memberships are activated only on `checkout.session.completed`:
- Confirms `mode === "subscription"`
- Resolves user from `metadata.user_id` or `customer_email`
- Creates user if missing (email-based)
- Assigns membership with status `active` or `trialing`
- Persists `stripe_customer_id` and `stripe_subscription_id` on the user
- Idempotent via existing webhook idempotency store

## Configuration Keys
- Option: `khm_stripe_membership_price_map` (array)
- Level meta: `stripe_price_id` (string)
- Filters: `khm_stripe_membership_price_map`, `khm_stripe_checkout_success_url`, `khm_stripe_checkout_cancel_url`, `khm_stripe_checkout_session_params`
