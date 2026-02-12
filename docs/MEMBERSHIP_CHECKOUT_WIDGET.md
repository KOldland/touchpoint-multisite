# Membership Checkout Button Widget - Implementation Summary

## Overview

A custom Elementor widget that renders a button to trigger a membership checkout modal. When clicked, the button opens a modal displaying membership tier information and redirects users to Stripe Checkout for payment processing.

**Key Features:**
- ✅ Elementor widget with full editor controls
- ✅ Modal system showing tier details (name, price, features)
- ✅ Stripe Checkout integration (hosted payment page)
- ✅ No payment logic in widget (follows separation of concerns)
- ✅ Reusable across multiple membership tiers

---

## Files Created

### 1. Elementor Widget
**Location:** `/app/public/wp-content/plugins/khm-plugin/src/Elementor/Widgets/MembershipCheckoutButton_Widget.php`

**Responsibilities:**
- Provides Elementor editor controls (tier selection, button text, styling)
- Renders button with membership data attributes
- Enqueues modal assets (JS/CSS)
- Validates membership tier exists before rendering

**Editor Controls:**
- **Membership Tier:** Dropdown populated from `LevelRepository::getNameMap()`
- **Button Text:** Customizable text (e.g., "Join Pro", "Upgrade Now")
- **Button Styling:** Typography, colors (normal/hover), border, padding
- **CSS Classes:** Optional additional classes for advanced styling

### 2. JavaScript Modal Handler
**Location:** `/app/public/wp-content/plugins/khm-plugin/assets/js/membership-modal.js`

**Responsibilities:**
- Creates and injects modal HTML into page
- Binds click handlers to `.khm-checkout-trigger` buttons
- Reads membership data from button data attributes
- Populates modal with tier info (name, price, interval, features)
- Triggers AJAX call to create Stripe Checkout Session
- Redirects user to Stripe-hosted checkout page

**Data Attributes Used:**
```html
data-membership-level-id="3"
data-membership-level-name="Pro"
data-membership-price="29.00"
data-membership-price-display="$29.00"
data-membership-interval="month"
data-purchase-type="subscription"
data-membership-description="Full access to all features"
data-membership-monthly-credits="10"
```

### 3. CSS Styling
**Location:** `/app/public/wp-content/plugins/khm-plugin/assets/css/membership-modal.css`

**Styling Includes:**
- Button styles (`.khm-checkout-trigger`)
- Modal overlay and container
- Tier information display (name, price, features)
- Responsive design for mobile devices
- Action buttons (primary/secondary)
- Error/success message styling

### 4. AJAX Handler
**Location:** `/app/public/wp-content/plugins/khm-plugin/src/Frontend/MembershipCheckoutHandler.php`

**Responsibilities:**
- Registers AJAX action: `khm_create_membership_checkout`
- Validates membership level and user eligibility
- Checks for existing active memberships (prevents duplicates)
- Resolves Stripe Price ID for the tier
- Creates Stripe Checkout Session
- Returns checkout URL for redirect

**AJAX Endpoint:**
- **Action:** `khm_create_membership_checkout`
- **Method:** POST
- **Parameters:**
  - `membership_level_id` (required): The tier ID
  - `nonce` (required): Security nonce
- **Returns:**
  ```json
  {
    "success": true,
    "data": {
      "checkout_url": "https://checkout.stripe.com/c/pay/...",
      "session_id": "cs_test_..."
    }
  }
  ```

### 5. Plugin Registration
**Location:** `/app/public/wp-content/plugins/khm-plugin/khm-plugin.php`

**Changes Made:**
- Added `MembershipCheckoutButton_Widget.php` to widget files list (line ~323)
- Registered widget with Elementor (line ~446-453)
- Initialized `MembershipCheckoutHandler` frontend class (line ~1362-1365)

---

## How It Works

### User Flow

1. **Editor Setup:**
   - User adds "Membership Checkout Button" widget to page
   - Selects membership tier from dropdown
   - Customizes button text and styling
   - Saves page

2. **Frontend Interaction:**
   - User clicks the checkout button
   - Modal opens showing tier details:
     - Tier name (e.g., "Pro")
     - Price and billing interval (e.g., "$29.00/month")
     - Description
     - Features list (credits, access, downloads)
   - User clicks "Proceed to Checkout"

3. **Backend Processing:**
   - AJAX request to `khm_create_membership_checkout`
   - Server validates tier and user
   - Creates Stripe Checkout Session with:
     - Line items (Stripe Price ID)
     - Success/cancel URLs
     - Metadata (tier ID, user ID, purchase type)
     - Customer email (if logged in)
   - Returns checkout URL

4. **Payment Flow:**
   - User redirects to Stripe-hosted checkout page
   - User completes payment on Stripe
   - Stripe redirects back to success URL

5. **Post-Purchase:**
   - Stripe webhook fires
   - `StripeWebhookHandler` activates membership
   - User gains access to member content

---

## Configuration Requirements

### 1. Stripe Configuration

**Required Settings:**
- Stripe Secret Key: `khm_stripe_secret_key` (option)
- Stripe Publishable Key: `khm_stripe_publishable_key` (option)

**Check Configuration:**
```php
// In WordPress admin or code
$secret = get_option('khm_stripe_secret_key');
$publishable = get_option('khm_stripe_publishable_key');
```

### 2. Stripe Price Mapping

Each membership tier needs a corresponding Stripe Price ID. The system checks multiple sources:

**Option 1: Filter Hook (Recommended)**
```php
add_filter('khm_stripe_membership_price_map', function($map, $level_id) {
    $prices = [
        1 => 'price_1ABC123...', // Free tier (optional)
        2 => 'price_2XYZ456...', // Pro tier
        3 => 'price_3LMN789...', // Premium tier
    ];
    return $prices;
}, 10, 2);
```

**Option 2: Level Meta**
```php
// Store in levelmeta table
$wpdb->insert($wpdb->prefix . 'khm_membership_levelmeta', [
    'level_id' => 2,
    'meta_key' => 'stripe_price_id',
    'meta_value' => 'price_2XYZ456...'
]);
```

**Option 3: Options Table**
```php
update_option('khm_stripe_price_map', [
    2 => 'price_2XYZ456...',
    3 => 'price_3LMN789...',
]);
```

### 3. Success/Cancel URLs

Default URLs can be customized via filters:

```php
// Success URL (after successful payment)
add_filter('khm_membership_checkout_success_url', function($url, $level_id, $user_id) {
    return home_url('/welcome/');
}, 10, 3);

// Cancel URL (if user cancels)
add_filter('khm_membership_checkout_cancel_url', function($url, $level_id, $user_id) {
    return home_url('/pricing/');
}, 10, 3);
```

**Default URLs:**
- Success: `/account/?membership=success`
- Cancel: `/?membership=cancelled`

---

## Testing Checklist

### Pre-Flight Checks

- [ ] Stripe keys configured (secret + publishable)
- [ ] Membership tiers exist in `khm_membership_levels` table
- [ ] Stripe Price IDs mapped to membership tiers
- [ ] Webhook endpoint configured in Stripe dashboard
- [ ] Test mode enabled in Stripe (use test keys)

### Widget Testing

1. **Elementor Editor:**
   - [ ] Widget appears in "Touchpoint" or "Theme Elements" category
   - [ ] Tier dropdown shows all membership levels
   - [ ] Button text field accepts custom text
   - [ ] Style controls work (colors, typography, padding, border)
   - [ ] Preview shows button correctly

2. **Frontend Display:**
   - [ ] Button renders with correct text
   - [ ] Button has correct styling
   - [ ] Data attributes are present on button element

3. **Modal Functionality:**
   - [ ] Click button opens modal
   - [ ] Modal shows correct tier name
   - [ ] Price displays correctly (e.g., "$29.00/month")
   - [ ] Description appears if set
   - [ ] Features list renders
   - [ ] Close button works
   - [ ] Overlay click closes modal

4. **Checkout Flow:**
   - [ ] "Proceed to Checkout" button triggers AJAX
   - [ ] AJAX returns checkout URL
   - [ ] Redirect to Stripe Checkout works
   - [ ] Stripe page shows correct product/price
   - [ ] Test payment completes successfully
   - [ ] Redirect back to success URL works

5. **Error Handling:**
   - [ ] Error shown if tier not selected in editor
   - [ ] Error shown if tier doesn't exist
   - [ ] Error shown if Stripe not configured
   - [ ] Error shown if Price ID missing
   - [ ] Error shown if user has active membership

---

## Debugging

### Enable Debug Logging

Check WordPress error logs for:
```
KHM Membership Checkout: Session created for level X
KHM Membership Checkout: No Stripe price ID found for level X
KHM Membership Checkout Stripe Error: [error message]
```

### Common Issues

**Modal doesn't open:**
- Check browser console for JS errors
- Verify `membership-modal.js` is loaded
- Check if jQuery is loaded

**AJAX fails:**
- Verify nonce is valid (check `khmMembershipModal.nonce` in console)
- Check AJAX URL is correct (`admin-ajax.php`)
- Review server error logs

**Stripe Checkout error:**
- Verify Stripe keys are correct
- Confirm Price ID exists in Stripe dashboard
- Check Price ID is correctly mapped to tier
- Ensure Price is set to "recurring" mode in Stripe

**User already has membership:**
- System prevents duplicate subscriptions
- User must cancel existing membership first
- Check `khm_memberships` table for active/trialing status

---

## Architecture Notes

### Data Flow Diagram

```
Elementor Widget (Button)
    ↓ (click)
JavaScript (membership-modal.js)
    ↓ (open modal)
User reviews tier info
    ↓ (click "Proceed to Checkout")
AJAX (khm_create_membership_checkout)
    ↓
MembershipCheckoutHandler.php
    ↓ (validate, create session)
Stripe Checkout Session
    ↓ (redirect)
Stripe Hosted Checkout Page
    ↓ (payment complete)
Redirect to Success URL
    ↓ (webhook fires)
StripeWebhookHandler.php
    ↓
Membership Activated ✓
```

### Integration Points

**With Existing Systems:**
- Uses `LevelRepository` to fetch membership tiers
- Integrates with existing Stripe webhook handler
- Follows same modal pattern as `CommerceFrontend`
- Uses same nonce/AJAX pattern as other KHM features

**No Conflicts:**
- Does NOT duplicate modal markup (creates own modal)
- Does NOT interfere with Social Strip modal
- Does NOT interfere with Commerce modal
- Separate AJAX action (`khm_create_membership_checkout`)

---

## Next Steps

### Required Before Production

1. **Configure Stripe Price Mapping:**
   - Create Stripe Products for each tier
   - Create Stripe Prices (recurring)
   - Map Price IDs to tier IDs (via filter or meta)

2. **Set Success/Cancel Pages:**
   - Create thank-you page for successful signups
   - Create pricing page or message for cancellations
   - Apply filters to customize URLs

3. **Test End-to-End:**
   - Test with Stripe test mode
   - Verify webhook activates membership
   - Confirm user sees member content after signup

### Optional Enhancements

1. **Trial Support:**
   - Stripe Checkout supports trials via Price settings
   - No code changes needed if Price has trial configured

2. **Coupon Support:**
   - Already enabled: `allow_promotion_codes: true`
   - Users can enter coupons on Stripe Checkout page

3. **Analytics:**
   - Add tracking to modal open/close events
   - Track conversion rate (modal → checkout → payment)

4. **Multiple Tiers in One Modal:**
   - Current: one widget = one tier
   - Future: widget could show tier comparison table
   - Multiple buttons in same modal

5. **Guest Checkout:**
   - Already supported (AJAX allows nopriv access)
   - User ID stored in metadata if logged in
   - Webhook can create user if needed

---

## Code References

### Key Classes

- **Widget:** [MembershipCheckoutButton_Widget.php:15](../../app/public/wp-content/plugins/khm-plugin/src/Elementor/Widgets/MembershipCheckoutButton_Widget.php#L15)
- **AJAX Handler:** [MembershipCheckoutHandler.php:32](../../app/public/wp-content/plugins/khm-plugin/src/Frontend/MembershipCheckoutHandler.php#L32)
- **Level Repository:** [LevelRepository.php:118](../../app/public/wp-content/plugins/khm-plugin/src/Services/LevelRepository.php#L118)

### Key Functions

- **Widget Render:** [MembershipCheckoutButton_Widget.php:169](../../app/public/wp-content/plugins/khm-plugin/src/Elementor/Widgets/MembershipCheckoutButton_Widget.php#L169)
- **Checkout Session Creation:** [MembershipCheckoutHandler.php:109](../../app/public/wp-content/plugins/khm-plugin/src/Frontend/MembershipCheckoutHandler.php#L109)
- **Price ID Resolution:** [MembershipCheckoutHandler.php:194](../../app/public/wp-content/plugins/khm-plugin/src/Frontend/MembershipCheckoutHandler.php#L194)

---

## Support

For issues or questions:
- Check error logs: `wp-content/debug.log`
- Review Stripe dashboard for session/payment logs
- Verify database tables: `khm_membership_levels`, `khm_memberships`
- Test in Stripe test mode before production

**Common Filters for Customization:**
- `khm_stripe_membership_price_map` - Map tier IDs to Stripe Price IDs
- `khm_membership_checkout_success_url` - Customize success redirect
- `khm_membership_checkout_cancel_url` - Customize cancel redirect

---

*Built following the KHM plugin architecture and Elementor best practices.*
