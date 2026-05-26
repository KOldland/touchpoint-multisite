# Stripe Price ID Feature - Quick Reference

## TL;DR

Store Stripe Price IDs directly on membership levels. No more hardcoded mappings.

---

## Quick Start

### 1. Add Price to Level
```
WP Admin → Memberships → Levels → Edit Level
→ Stripe Price ID: price_xxxxxxxxxxxxx
→ (Optional) KHM Level Meta JSON for features/commerce/presentation
→ Save
```

### 2. Checkout Automatically Uses It
```bash
POST /wp-json/khm/v1/checkout/subscription
{
  "membership_level_id": 1,
  "email": "user@example.com"
}
# → Creates Stripe session with that price
```

### 3. Webhook Assigns Membership
```
Stripe sends checkout.session.completed
→ Membership assigned automatically
→ Stripe IDs saved to user meta
```

---

## Priority Order

When resolving which price to use:

1. **Level Metadata** (stripe_price_id) ⭐ Recommended
2. **Level Meta JSON** (khm_level_meta.stripe_price_ids[currency][interval])
3. **Filter** (khm_stripe_membership_price_map)
4. **Option** (khm_stripe_membership_price_map or khm_stripe_price_map) - Legacy

---

## Valid Price ID Format

✅ `price_1234567890abcdef`
✅ `price_ABCDEFGHIJ`
✅ `price_MixedCase123`

❌ `prod_123` (product ID, not price)
❌ `price_with-dash`
❌ `price_with_underscore`
❌ `price_` (incomplete)

**Regex:** `/^price_[A-Za-z0-9]+$/`

---

## Migration

Move existing option-based config to metadata:

```bash
# Preview
wp khm migrate-prices --dry-run

# Execute
wp khm migrate-prices

# Force overwrite existing values
wp khm migrate-prices --overwrite

# Store a rollback snapshot option
wp khm migrate-prices --backup

# Custom rollback option name
wp khm migrate-prices --backup --backup-key=khm_migrate_prices_backup_20260211_120000
```

If your legacy option stores per-currency prices, the migration will place them under:
`khm_level_meta.stripe_price_ids`.

Rollback snapshot notes:
- The snapshot stores the previous `stripe_price_id` and `khm_level_meta` for each updated level.
- Use the option to manually restore values if needed.

---

## Code Examples

### Set Price ID Programmatically
```php
$level_id = 1;
$price_id = 'price_1234567890';

$level_repo = new KHM\Services\LevelRepository();
$level_repo->updateMeta($level_id, 'stripe_price_id', $price_id);
```

### Check Current Price ID
```php
$level_repo = new KHM\Services\LevelRepository();
$price_id = $level_repo->getMeta($level_id, 'stripe_price_id');

if ($price_id) {
    echo "Price ID: " . $price_id;
} else {
    echo "No price configured";
}
```

### Runtime Override (Filter)
```php
add_filter('khm_stripe_membership_price_map', function($map, $level_id) {
    // Different prices for staging
    if (wp_get_environment_type() === 'staging') {
        return [1 => 'price_test123'];
    }
    return $map;
}, 10, 2);
```

### Level Meta JSON Example
```json
{
  "features": { "gifting": true, "portal": true },
  "presentation": { "cta_text": "Join now", "price_inclusive": true },
  "commerce": { "allow_promotion_codes": false, "trial_days": 14 },
  "availability": { "start_at": "2026-02-11", "end_at": "2026-12-31" },
  "stripe_price_ids": {
    "USD": { "monthly": "price_123", "yearly": "price_456" }
  }
}
```

---

## Admin UX Notes

- The Stripe Price ID field displays the Stripe key mode (Test/Live) based on the configured secret key.
- The "Open Price in Stripe" link appears once a valid `price_...` ID is entered or validated.
- "Validate Price" checks the ID against Stripe and returns a Test/Live badge for that price.

---

## Edge Case Policy

If a Stripe Price is deleted after subscriptions exist:
- Existing subscriptions continue billing and remain active.
- New checkouts for that level will fail until a valid Price ID is configured.

---

## Testing Checklist

### Admin
- [ ] Create level with price ID
- [ ] Edit existing level
- [ ] Invalid format shows error
- [ ] Info notice for paid levels without price

### Checkout
- [ ] Session created with correct price
- [ ] Error if no price configured
- [ ] Metadata includes level_id and user_id

### Webhook
- [ ] Membership assigned on checkout.session.completed
- [ ] Stripe IDs saved to user meta
- [ ] Replay doesn't duplicate

### Migration
- [ ] Dry-run shows preview
- [ ] Actual run migrates data
- [ ] Existing metadata preserved (unless --overwrite)

---

## Debugging

### Enable Debug Log
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Check Logs
```bash
tail -f wp-content/debug.log | grep -i stripe
```

### Test Webhook Locally
```bash
stripe listen --forward-to http://localhost/wp-json/khm/v1/webhooks/stripe
stripe trigger checkout.session.completed
```

### Database Query
```sql
-- Check all levels with price IDs
SELECT l.id, l.name, l.billing_amount, m.meta_value as stripe_price_id
FROM wp_khm_membership_levels l
LEFT JOIN wp_khm_membership_levelmeta m 
  ON l.id = m.level_id AND m.meta_key = 'stripe_price_id'
ORDER BY l.id;
```

---

## Error Messages

| Error | Cause | Fix |
|-------|-------|-----|
| "No Stripe price configured for membership level X" | No price set | Add stripe_price_id to level |
| "Invalid Stripe Price ID format" | Wrong format | Use `price_[A-Za-z0-9]+` |
| "Unable to create checkout session" | Stripe API error | Check Stripe Dashboard & logs |
| "Membership level not found" | Invalid level_id | Verify level exists |

---

## Files Modified

- `src/Admin/LevelsPage.php` - Admin UI
- `src/Rest/CheckoutController.php` - Price resolution
- `src/Rest/WebhooksController.php` - Webhook handling
- `khm-plugin.php` - CLI registration

---

## Files Created

- `src/CLI/MigratePricesCommand.php` - Migration tool
- `tests/Admin/StripePriceIdTest.php` - Admin tests
- `tests/Rest/CheckoutStripePriceTest.php` - Checkout tests
- `docs/STRIPE_PRICE_METADATA_TESTING.md` - Full test guide
- `docs/STRIPE_PRICE_METADATA_IMPLEMENTATION.md` - Implementation details

---

## Contact & Support

- **Full Testing Guide:** `docs/STRIPE_PRICE_METADATA_TESTING.md`
- **Implementation Details:** `docs/STRIPE_PRICE_METADATA_IMPLEMENTATION.md`
- **Run Tests:** `./vendor/bin/phpunit`

---

**Last Updated:** February 10, 2026
