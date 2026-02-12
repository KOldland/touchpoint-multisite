# Stripe Price ID Metadata - Testing Guide

## Overview

This document provides comprehensive testing instructions for the Stripe Price ID metadata feature. The feature allows storing Stripe Price IDs directly on membership levels as editable metadata, eliminating the need for hardcoded price mappings.

## Features Implemented

1. **Admin UI**: Stripe Price ID field on membership level edit screen
2. **Validation**: Format validation for Stripe Price IDs (`price_[A-Za-z0-9]+`)
3. **Priority System**: Metadata → Filter → Option (legacy fallback)
4. **Admin Notices**: Helpful guidance for missing price IDs on paid levels
5. **Webhook Integration**: Enhanced webhook handler with Stripe ID storage
6. **Migration Tool**: WP-CLI command to migrate existing mappings

---

## Test Plan

### 1. Admin Interface Tests

#### 1.1 Add Stripe Price ID to New Level

**Steps:**
1. Go to WP Admin → Memberships → Levels
2. Click "Add New Level"
3. Fill in basic information:
   - Name: "Premium Membership"
   - Billing Amount: $29.99
   - Cycle: 1 Month
4. In "Stripe Price ID" field, enter: `price_1234567890abcdef`
5. Click "Save Membership Level"

**Expected Results:**
- ✅ Level is created successfully
- ✅ Success notice appears
- ✅ No warning about missing Stripe Price ID (since it was provided)

#### 1.2 Edit Existing Level to Add Stripe Price ID

**Steps:**
1. Go to Memberships → Levels
2. Click "Edit" on an existing level
3. Add a Stripe Price ID: `price_test123abc`
4. Click "Save Membership Level"

**Expected Results:**
- ✅ Level is updated successfully
- ✅ Price ID is saved and visible on reload

#### 1.3 Validation - Invalid Price ID Format

**Steps:**
1. Edit a membership level
2. Enter invalid price ID: `prod_invalidformat`
3. Click "Save Membership Level"

**Expected Results:**
- ✅ Error notice: "Invalid Stripe Price ID format. Must start with 'price_' followed by alphanumeric characters."
- ✅ Form is not saved
- ✅ Previously entered data is preserved

#### 1.4 Info Notice for Paid Level Without Price ID

**Steps:**
1. Create/edit a paid level (billing_amount > 0)
2. Leave Stripe Price ID field empty
3. Save the level

**Expected Results:**
- ✅ Level is saved successfully
- ✅ Info notice appears: "Note: This is a paid membership level but no Stripe Price ID is configured..."
- ✅ Notice includes guidance to find Price ID in Stripe Dashboard

#### 1.5 Free Level Without Price ID (No Warning)

**Steps:**
1. Create a free level (billing_amount = 0)
2. Leave Stripe Price ID empty
3. Save the level

**Expected Results:**
- ✅ Level is saved successfully
- ✅ No warning about missing Price ID (free levels don't need Stripe)

---

### 2. Checkout Session Creation Tests

#### 2.1 Create Checkout with Metadata Price ID

**Prerequisites:**
- Level #1 exists with `stripe_price_id` metadata set to a valid Stripe price

**Steps:**
1. Make POST request to `/wp-json/khm/v1/checkout/subscription`
2. Body: `{"membership_level_id": 1, "email": "test@example.com"}`

**Expected Results:**
- ✅ Checkout session is created successfully
- ✅ Response contains `url` field
- ✅ Stripe session uses the price from metadata
- ✅ Session metadata includes `membership_level_id` and `user_id`

**Verification:**
```bash
# Check Stripe CLI
stripe listen --forward-to http://yoursite.local/wp-json/khm/v1/webhooks/stripe
```

#### 2.2 Checkout with No Price Configured

**Prerequisites:**
- Level #99 exists but has no `stripe_price_id` metadata
- No option or filter configured for this level

**Steps:**
1. POST to `/wp-json/khm/v1/checkout/subscription`
2. Body: `{"membership_level_id": 99, "email": "test@example.com"}`

**Expected Results:**
- ✅ Response: 400 Bad Request
- ✅ Error message: "No Stripe price configured for membership level 99. Please configure a Stripe Price ID in the membership level settings."
- ✅ Error is logged to PHP error log

#### 2.3 Invalid Price ID Format Prevention

**Prerequisites:**
- Level has metadata: `stripe_price_id` = `prod_invalid123` (product ID, not price)

**Steps:**
1. POST to checkout endpoint for this level

**Expected Results:**
- ✅ Response: 400 Bad Request
- ✅ Error logged: "KHM Checkout: Invalid Stripe Price ID format for level X: prod_invalid123"
- ✅ Checkout session is NOT created

#### 2.4 Price Resolution Priority Testing

**Setup:**
```php
// Set all three sources with different prices
$level_id = 1;

// 1. Metadata (should win)
update_post_meta($level_id, 'stripe_price_id', 'price_from_meta');

// 2. Filter
add_filter('khm_stripe_membership_price_map', function($map) {
    return [1 => 'price_from_filter'];
});

// 3. Option
update_option('khm_stripe_membership_price_map', [1 => 'price_from_option']);
```

**Test:**
1. Create checkout session for level 1

**Expected Results:**
- ✅ Checkout uses `price_from_meta` (metadata has highest priority)

**Remove metadata and test again:**
```php
delete_post_meta($level_id, 'stripe_price_id');
```

**Expected Results:**
- ✅ Checkout now uses `price_from_filter` (second priority)

---

### 3. Webhook Handler Tests

#### 3.1 Successful Checkout Session Completed

**Prerequisites:**
- Stripe CLI installed and authenticated
- Webhook endpoint: `/wp-json/khm/v1/webhooks/stripe`

**Steps:**
1. Complete a Stripe checkout (use test card: 4242 4242 4242 4242)
2. Monitor webhook with:
   ```bash
   stripe listen --forward-to http://yoursite.local/wp-json/khm/v1/webhooks/stripe
   ```

**Expected Results:**
- ✅ Webhook handler receives `checkout.session.completed` event
- ✅ User is created/found from session email
- ✅ Membership is assigned via `$membershipRepo->assign()`
- ✅ Assignment options include:
  - `status`: 'active' or 'trialing'
  - `stripe_customer_id`: from session
  - `stripe_subscription_id`: from session
- ✅ User meta updated with Stripe customer/subscription IDs
- ✅ Action `khm_stripe_checkout_session_completed_handled` is fired

**Verification:**
```sql
-- Check membership assignment
SELECT * FROM wp_khm_memberships_users 
WHERE user_id = ? AND membership_id = ?;

-- Check user meta
SELECT * FROM wp_usermeta 
WHERE user_id = ? AND meta_key IN ('stripe_customer_id', 'stripe_subscription_id');
```

#### 3.2 Webhook Idempotency Test

**Steps:**
1. Complete a checkout successfully
2. Use Stripe CLI to replay the same event:
   ```bash
   stripe events resend evt_xxxxxxxxxxxxx
   ```

**Expected Results:**
- ✅ Webhook processes event again
- ✅ No duplicate membership is created
- ✅ Existing membership is updated (if needed)
- ✅ No errors occur

#### 3.3 Webhook with Missing Metadata

**Setup:**
- Manually trigger checkout.session.completed event without `membership_level_id` in metadata

**Expected Results:**
- ✅ Webhook handler returns early
- ✅ Action `khm_stripe_unresolved_context` is fired
- ✅ No membership is assigned
- ✅ Event is logged for debugging

---

### 4. Migration Tool Tests

#### 4.1 WP-CLI Migration - Dry Run

**Prerequisites:**
- Option `khm_stripe_membership_price_map` contains:
  ```php
  [
      1 => 'price_standard123',
      2 => 'price_premium456',
      3 => 'price_enterprise789'
  ]
  ```

**Steps:**
```bash
wp khm migrate-prices --dry-run
```

**Expected Results:**
- ✅ Output shows: "Found 3 price mappings in option"
- ✅ Each level is listed with its price ID
- ✅ Message: "[DRY RUN] No changes were saved"
- ✅ No actual database changes occur

**Verify:**
```bash
# Check that metadata is NOT actually set
wp post meta get 1 stripe_price_id
# Should return empty or "Error: No rows returned."
```

#### 4.2 WP-CLI Migration - Actual Migration

**Steps:**
```bash
wp khm migrate-prices
```

**Expected Results:**
- ✅ Output shows successful migration for each level
- ✅ Summary shows: "Migrated: 3, Skipped: 0, Errors: 0"
- ✅ Success message appears

**Verify:**
```bash
# Check metadata was set
wp post meta get 1 stripe_price_id
# Should return: price_standard123

wp post meta get 2 stripe_price_id
# Should return: price_premium456
```

#### 4.3 Migration with Existing Metadata

**Setup:**
- Level 1 already has `stripe_price_id` metadata: `price_existing`
- Option has different value: `price_from_option`

**Test 1 - Without overwrite:**
```bash
wp khm migrate-prices
```

**Expected Results:**
- ✅ Output: "Level #1 already has price ID: price_existing (use --overwrite to replace)"
- ✅ Skipped: 1
- ✅ Metadata remains: `price_existing`

**Test 2 - With overwrite:**
```bash
wp khm migrate-prices --overwrite
```

**Expected Results:**
- ✅ Output: "Level #1: price_from_option → stripe_price_id metadata"
- ✅ Migrated: 1
- ✅ Metadata updated to: `price_from_option`

#### 4.4 Migration with Invalid Price Format

**Setup:**
- Option contains: `[4 => 'invalid_format']`

**Steps:**
```bash
wp khm migrate-prices
```

**Expected Results:**
- ✅ Warning: "Invalid Stripe Price ID format for level #4: invalid_format"
- ✅ Errors: 1
- ✅ Level 4 metadata is NOT updated

---

### 5. Integration Tests

#### 5.1 End-to-End Checkout Flow

**Complete Flow:**
1. Create level in WP Admin with Stripe Price ID
2. Create Stripe Price in Stripe Dashboard (same ID)
3. Use checkout widget/endpoint to create session
4. Complete payment with test card
5. Webhook activates membership

**Expected Results:**
- ✅ User can access member-only content
- ✅ Membership status is "active"
- ✅ Stripe IDs are stored in user meta
- ✅ Credits are allocated (if applicable)

#### 5.2 Subscription Lifecycle

**Steps:**
1. Complete checkout (as above)
2. Wait for subscription to renew (or trigger renewal in Stripe)
3. Cancel subscription in Stripe Dashboard
4. Update subscription in Stripe Dashboard

**Expected Results:**
- ✅ Each webhook event is processed correctly
- ✅ Membership status updates accordingly
- ✅ No duplicate memberships created
- ✅ Billing profile is updated

---

### 6. Edge Cases & Error Handling

#### 6.1 Price ID Changed After Active Subscriptions

**Scenario:**
- Level has active subscriptions using `price_old123`
- Admin changes level's `stripe_price_id` to `price_new456`

**Expected Results:**
- ✅ New checkouts use `price_new456`
- ✅ Existing subscriptions with `price_old123` continue unaffected
- ✅ Webhooks for old subscriptions still process correctly

#### 6.2 Deleted Stripe Price

**Scenario:**
- Level has `stripe_price_id` = `price_deleted`
- Price is archived/deleted in Stripe

**Steps:**
1. Attempt to create checkout session

**Expected Results:**
- ✅ Stripe API returns error
- ✅ Checkout endpoint returns 500 with generic error: "Unable to create checkout session"
- ✅ Detailed error is logged to PHP error log

#### 6.3 Network/API Failures

**Scenario:**
- Stripe API is temporarily unavailable

**Expected Results:**
- ✅ Checkout endpoint returns 500 error
- ✅ User sees friendly error message
- ✅ Admin can view detailed error in logs

---

## Manual Testing Checklist

Use this checklist for comprehensive manual testing:

### Admin UI
- [ ] Can create new level with Stripe Price ID
- [ ] Can edit existing level to add Stripe Price ID
- [ ] Can edit existing Stripe Price ID
- [ ] Can clear Stripe Price ID (set to empty)
- [ ] Invalid format shows error and prevents save
- [ ] Info notice appears for paid levels without price ID
- [ ] No notice for free levels without price ID
- [ ] Field is visible and properly labeled
- [ ] Placeholder text is helpful

### Checkout
- [ ] Checkout works with price from metadata
- [ ] Checkout falls back to filter when metadata empty
- [ ] Checkout falls back to option when filter and metadata empty
- [ ] Checkout returns 400 when no price configured
- [ ] Invalid price format returns 400
- [ ] Error messages are clear and actionable
- [ ] Stripe session is created with correct price
- [ ] Metadata includes `membership_level_id` and `user_id`

### Webhooks
- [ ] `checkout.session.completed` assigns membership
- [ ] Membership status is set correctly (active/trialing)
- [ ] Stripe customer ID is saved to user meta
- [ ] Stripe subscription ID is saved to user meta
- [ ] Idempotency works (replay doesn't duplicate)
- [ ] Missing metadata is handled gracefully
- [ ] Action hooks fire correctly

### Migration
- [ ] Dry run shows preview without saving
- [ ] Actual migration updates metadata
- [ ] Existing metadata is preserved (without --overwrite)
- [ ] --overwrite flag replaces existing metadata
- [ ] Invalid formats are skipped with warning
- [ ] Summary shows accurate counts

### Edge Cases
- [ ] Multiple levels with same price ID work correctly
- [ ] Changing price ID doesn't break existing subscriptions
- [ ] Deleted/archived Stripe prices fail gracefully
- [ ] Network errors are handled properly
- [ ] Concurrent webhooks don't cause issues

---

## Automated Test Execution

```bash
# Run PHPUnit tests
cd /path/to/khm-plugin
./vendor/bin/phpunit tests/Admin/StripePriceIdTest.php
./vendor/bin/phpunit tests/Rest/CheckoutStripePriceTest.php

# Run all tests
./vendor/bin/phpunit
```

---

## Debugging Tips

### Enable Debug Logging
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Check Error Logs
```bash
tail -f wp-content/debug.log
```

### Test Webhook Locally
```bash
# Install Stripe CLI
stripe listen --forward-to http://localhost/wp-json/khm/v1/webhooks/stripe

# Trigger test events
stripe trigger checkout.session.completed
```

### Inspect Database
```sql
-- Check level metadata
SELECT * FROM wp_khm_membership_levelmeta WHERE meta_key = 'stripe_price_id';

-- Check user memberships
SELECT * FROM wp_khm_memberships_users WHERE user_id = ?;

-- Check user meta for Stripe IDs
SELECT * FROM wp_usermeta 
WHERE user_id = ? AND meta_key LIKE 'stripe_%';
```

---

## Success Criteria

The feature is considered fully functional when:

1. ✅ Admin can set/edit Stripe Price ID on membership levels
2. ✅ Invalid formats are rejected with clear error messages
3. ✅ Checkout sessions use the correct price (metadata priority)
4. ✅ Fallback system works (metadata → filter → option)
5. ✅ Webhooks assign memberships with Stripe IDs
6. ✅ Idempotency prevents duplicate assignments
7. ✅ Migration tool successfully moves option data to metadata
8. ✅ All automated tests pass
9. ✅ Edge cases are handled gracefully
10. ✅ Documentation is clear and complete

---

## Troubleshooting

### Issue: Checkout returns "No Stripe price configured"
**Solution:** Ensure level has `stripe_price_id` metadata set, or configure fallback option/filter.

### Issue: Invalid Price ID error
**Solution:** Verify format is `price_[A-Za-z0-9]+`. No dashes, underscores, or spaces.

### Issue: Webhook not assigning membership
**Solution:** Check that session metadata includes `membership_level_id`. Enable debug logging to see webhook payload.

### Issue: Migration skips all levels
**Solution:** Check that levels exist in database and option has valid data. Use `--dry-run` to preview.

---

## Next Steps

After successful testing:

1. Deploy to staging environment
2. Run migration tool on staging data
3. Test with real Stripe test mode data
4. Update production documentation
5. Schedule production deployment
6. Monitor error logs after deployment

---

## Release Checklist (Copy-Paste)

- [ ] All unit tests pass (`phpunit`)
- [ ] Integration tests: Checkout creation + webhook assign (stripe-cli) pass
- [ ] Manual E2E on staging: set price ID → modal → checkout → membership assigned
- [ ] Run `wp khm migrate-prices --dry-run` and review planned changes
- [ ] If migrating: run `wp khm migrate-prices` on staging, confirm results, then production
- [ ] Update docs: QUICKREF + TESTING files, and admin help text in Levels UI
- [ ] Add release notes: v1/v2 policy + how to revert migration
- [ ] Monitor webhooks and logs for 2–4 hours after go-live
