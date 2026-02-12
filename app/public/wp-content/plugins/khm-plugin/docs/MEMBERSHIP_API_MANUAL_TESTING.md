# Membership API Manual Testing Guide

## Overview

This guide provides step-by-step instructions for manually testing all Membership API endpoints. Use these procedures to verify production readiness before deployment.

---

## Prerequisites

- WordPress site with KHM Plugin installed
- Stripe Test Mode configured
- REST API client (Postman, Insomnia, or curl)
- Admin WordPress user account
- Stripe CLI (for webhook testing)

---

## Environment Setup

### 1. Configure Stripe Test Mode

**WordPress Admin > Settings > KHM Membership**

```
Stripe Secret Key: sk_test_xxxxxxxxxxxxx
Stripe Publishable Key: pk_test_xxxxxxxxxxxxx
Stripe Webhook Secret: whsec_xxxxxxxxxxxxx
```

### 2. Create Test Membership Tiers

**WordPress Admin > Membership > Tiers**

Create 3 test plans:

**Plan 1: Free Trial**
- Name: "Free Trial Plan"
- Price: £0.00
- Trial Days: 14
- ✅ Active

**Plan 2: Paid Monthly**
- Name: "Premium Monthly"
- Price: £29.99/month
- Trial Days: 0
- Stripe Price ID: `price_xxxxxxxxxxxxx`
- ✅ Active

**Plan 3: Trial + Paid**
- Name: "Premium with Trial"
- Price: £49.99/month
- Trial Days: 7
- Stripe Price ID: `price_xxxxxxxxxxxxx`
- ✅ Active

### 3. Stripe CLI Setup

```bash
# Install Stripe CLI
brew install stripe/stripe-cli/stripe

# Login
stripe login

# Forward webhooks to local WordPress
stripe listen --forward-to http://touchpoint-template.local/wp-json/kh-membership/v1/webhook/stripe
```

Copy the webhook signing secret (`whsec_...`) to WordPress settings.

---

## Test 1: Attribution Endpoint

**Endpoint**: `POST /wp-json/kh-membership/v1/attribution`

### Test 1.1: Valid Signup Conversion

**Request**:
```bash
curl -X POST "http://touchpoint-template.local/wp-json/kh-membership/v1/attribution" \
  -H "Content-Type: application/json" \
  -d '{
    "conversion_type": "signup",
    "user_id": 123,
    "user_email": "test@example.com",
    "schedule_id": 99,
    "sponsor_id": 12,
    "utm_source": "newsletter",
    "utm_medium": "email",
    "plan_id": 1
  }'
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "id": 1
}
```

**Verify**:
- Database record created in `wp_promotion_attribution`
- Record ID returned matches database

### Test 1.2: Invalid Conversion Type

**Request**:
```bash
curl -X POST "http://touchpoint-template.local/wp-json/kh-membership/v1/attribution" \
  -H "Content-Type: application/json" \
  -d '{
    "conversion_type": "invalid_type"
  }'
```

**Expected Response** (400 Bad Request):
```json
{
  "error": "invalid conversion_type",
  "details": "Allowed values are: signup, trial, paid, demo_request"
}
```

### Test 1.3: Idempotency Check

**Request** (send twice within 10 minutes):
```bash
curl -X POST "http://touchpoint-template.local/wp-json/kh-membership/v1/attribution" \
  -H "Content-Type: application/json" \
  -d '{
    "conversion_type": "signup",
    "user_id": 456,
    "schedule_id": 99
  }'
```

**Expected Result**:
- First request: Returns new ID (e.g., `"id": 2`)
- Second request: Returns same ID (`"id": 2`)
- Database: Only 1 record exists for user_id 456 + schedule_id 99

---

## Test 2: Signup Endpoint

**Endpoint**: `POST /wp-json/kh-membership/v1/signup`

### Test 2.1: Free Trial Signup

**Request**:
```bash
curl -X POST "http://touchpoint-template.local/wp-json/kh-membership/v1/signup" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "freetrial@example.com",
    "plan_id": 1
  }'
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "status": "trialing",
  "user_id": 124,
  "membership": {
    "tier_id": 1,
    "status": "trialing",
    "trial_ends_at": "2026-02-22T12:00:00+00:00"
  }
}
```

**Verify**:
- New WordPress user created with email
- Membership record in `wp_user_membership` with status='trialing'
- trial_ends_at is 14 days from now

### Test 2.2: Paid Plan Signup (Stripe Checkout)

**Request**:
```bash
curl -X POST "http://touchpoint-template.local/wp-json/kh-membership/v1/signup" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "premium@example.com",
    "plan_id": 2,
    "schedule_id": 99
  }'
```

**Expected Response** (200 OK):
```json
{
  "success": true,
  "status": "requires_payment_method",
  "redirect_url": "https://checkout.stripe.com/pay/cs_test_xxxxxxxxxxxxx"
}
```

**Manual Steps**:
1. Open `redirect_url` in browser
2. Complete Stripe checkout (use test card `4242 4242 4242 4242`)
3. Verify redirect to success URL after payment

**Verify**:
- User created but membership not yet active
- After Stripe checkout: Membership status becomes 'active'
- Attribution record created for schedule_id 99

### Test 2.3: Invalid Email

**Request**:
```bash
curl -X POST "http://touchpoint-template.local/wp-json/kh-membership/v1/signup" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "not-an-email",
    "plan_id": 1
  }'
```

**Expected Response** (400 Bad Request):
```json
{
  "error": "invalid email"
}
```

### Test 2.4: Duplicate Active Subscription

**Request** (for existing active user):
```bash
curl -X POST "http://touchpoint-template.local/wp-json/kh-membership/v1/signup" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "freetrial@example.com",
    "plan_id": 2
  }'
```

**Expected Response** (409 Conflict):
```json
{
  "error": "user already has an active subscription"
}
```

---

## Test 3: Status Endpoint

**Endpoint**: `GET /wp-json/kh-membership/v1/status?user_id={id}`

### Test 3.1: Unauthenticated Request

**Request**:
```bash
curl "http://touchpoint-template.local/wp-json/kh-membership/v1/status?user_id=123"
```

**Expected Response** (401 Unauthorized):
```json
{
  "code": "rest_forbidden",
  "message": "Authentication required.",
  "data": {
    "status": 401
  }
}
```

### Test 3.2: Authenticated User (Own Status)

**Request** (with WordPress auth cookie):
```bash
curl "http://touchpoint-template.local/wp-json/kh-membership/v1/status?user_id=123" \
  -H "Cookie: wordpress_logged_in_xxxxx=..."
```

**Expected Response** (200 OK):
```json
{
  "user_id": 123,
  "tier": {
    "id": 1,
    "slug": "free-trial-plan",
    "name": "Free Trial Plan"
  },
  "status": "trialing",
  "trial_ends_at": "2026-02-22T12:00:00+00:00",
  "started_at": "2026-02-08T12:00:00+00:00",
  "cancelled_at": null,
  "renews_at": "2026-02-22T12:00:00+00:00"
}
```

### Test 3.3: Access Another User's Status (Non-Admin)

**Request** (user 123 tries to access user 456):
```bash
curl "http://touchpoint-template.local/wp-json/kh-membership/v1/status?user_id=456" \
  -H "Cookie: wordpress_logged_in_xxxxx=..."
```

**Expected Response** (403 Forbidden):
```json
{
  "code": "rest_forbidden",
  "message": "You can only access your own membership status.",
  "data": {
    "status": 403
  }
}
```

### Test 3.4: No Membership Record

**Request** (for user with no membership):
```bash
curl "http://touchpoint-template.local/wp-json/kh-membership/v1/status?user_id=999" \
  -H "Cookie: wordpress_logged_in_admin_xxxxx=..."
```

**Expected Response** (200 OK):
```json
{
  "user_id": 999,
  "tier": null,
  "status": "none",
  "trial_ends_at": null,
  "started_at": null,
  "cancelled_at": null,
  "renews_at": null
}
```

---

## Test 4: Stripe Webhook Handler

**Endpoint**: `POST /wp-json/kh-membership/v1/webhook/stripe`

### Test 4.1: checkout.session.completed

**Setup**:
1. Start Stripe CLI webhook forwarding
2. Complete a Stripe Checkout session for user_id=125

**Stripe CLI**:
```bash
stripe listen --forward-to http://touchpoint-template.local/wp-json/kh-membership/v1/webhook/stripe
```

**Trigger Event**:
```bash
stripe trigger checkout.session.completed
```

**Verify**:
- Membership created in `wp_user_membership`
- Status = 'active'
- stripe_customer_id and stripe_subscription_id populated
- Event logged in `wp_stripe_webhook_events`

### Test 4.2: invoice.paid

**Trigger Event**:
```bash
stripe trigger invoice.paid
```

**Verify**:
- Membership status updated to 'active'
- Event logged (not processed twice if sent again)

### Test 4.3: invoice.payment_failed

**Trigger Event**:
```bash
stripe trigger invoice.payment_failed
```

**Verify**:
- Membership status updated to 'past_due'

### Test 4.4: customer.subscription.deleted

**Trigger Event**:
```bash
stripe trigger customer.subscription.deleted
```

**Verify**:
- Membership status updated to 'cancelled'
- cancelled_at timestamp set

### Test 4.5: Idempotency Check

**Steps**:
1. Send same webhook event twice
2. Verify only processed once

**Check Database**:
```sql
SELECT * FROM wp_stripe_webhook_events;
```

**Expected**:
- Event ID appears only once
- Second request returns 200 OK with `"note": "already processed"`

### Test 4.6: Invalid Signature

**Request** (missing or invalid signature):
```bash
curl -X POST "http://touchpoint-template.local/wp-json/kh-membership/v1/webhook/stripe" \
  -H "Content-Type: application/json" \
  -H "Stripe-Signature: invalid" \
  -d '{
    "id": "evt_test",
    "type": "invoice.paid"
  }'
```

**Expected Response** (400 Bad Request):
```json
{
  "error": "Invalid signature"
}
```

---

## Test Checklist

### Attribution Endpoint
- [ ] Valid signup conversion creates record
- [ ] Invalid conversion type returns 400
- [ ] Idempotency prevents duplicates within 10 minutes
- [ ] user_email alternative works without user_id
- [ ] NULL schedule_id handled correctly

### Signup Endpoint
- [ ] Free trial plan starts immediately without payment
- [ ] Paid plan returns Stripe Checkout redirect URL
- [ ] Invalid email returns 400
- [ ] Missing plan_id returns 400
- [ ] Non-existent plan_id returns 400
- [ ] Duplicate subscription returns 409
- [ ] Attribution created when schedule_id provided

### Status Endpoint
- [ ] Unauthenticated request returns 401
- [ ] User can access own status
- [ ] User cannot access other users (403)
- [ ] Admin can access any user status
- [ ] Non-existent user returns status='none'
- [ ] Response schema matches contract
- [ ] Timestamps in ISO 8601 format

### Webhook Handler
- [ ] checkout.session.completed creates membership
- [ ] invoice.paid updates to 'active'
- [ ] invoice.payment_failed updates to 'past_due'
- [ ] customer.subscription.deleted updates to 'cancelled'
- [ ] Idempotency prevents duplicate processing
- [ ] Invalid signature returns 400
- [ ] Unknown event types logged but don't error

---

## Database Verification

### Check Attribution Records
```sql
SELECT * FROM wp_promotion_attribution ORDER BY created_at DESC LIMIT 10;
```

### Check User Memberships
```sql
SELECT
    um.user_id,
    um.status,
    um.stripe_customer_id,
    mt.name as tier_name
FROM wp_user_membership um
LEFT JOIN wp_membership_tier mt ON um.tier_id = mt.id
ORDER BY um.started_at DESC;
```

### Check Webhook Events
```sql
SELECT * FROM wp_stripe_webhook_events ORDER BY processed_at DESC LIMIT 20;
```

---

## Production Readiness Checklist

Before deploying to production:

- [ ] All manual tests pass
- [ ] Stripe production keys configured (not test keys)
- [ ] Webhook endpoint registered in Stripe Dashboard
- [ ] Database tables created (`promotion_attribution`, `user_membership`, `membership_tier`, `stripe_webhook_events`)
- [ ] SSL certificate valid (webhooks require HTTPS)
- [ ] Rate limiting configured for public endpoints
- [ ] Error logging configured
- [ ] Monitoring alerts set up for webhook failures
- [ ] Backup procedures tested

---

## Troubleshooting

### Webhook Not Receiving Events

1. Check Stripe webhook endpoint URL in Stripe Dashboard
2. Verify webhook secret matches WordPress settings
3. Check firewall allows Stripe IP ranges
4. Enable WordPress debug logging: `WP_DEBUG_LOG = true`

### Checkout Redirect Fails

1. Verify Stripe price IDs configured for plans
2. Check `khm_stripe_membership_price_map` filter/option
3. Test with Stripe test card: `4242 4242 4242 4242`
4. Check browser console for JavaScript errors

### Database Errors

1. Verify tables exist: `SHOW TABLES LIKE 'wp_%membership%';`
2. Check table schema matches migration
3. Verify database user has CREATE privilege
4. Run migration manually if needed

---

**Status**: Ready for manual testing
**Environment**: Local by Flywheel (touchpoint-template.local)
**Stripe Mode**: Test
