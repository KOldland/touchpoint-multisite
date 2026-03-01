# PR20 Staging QA Evidence Template

Environment:
- Branch deployed: `chore/remote-recovery-checkpoint-20260217`
- URL: `<staging-url>`
- Date: `<YYYY-MM-DD>`
- Tester: `<name>`

## 1) Webhook reliability
- [ ] Stripe destination configured to staging webhook URL
- [ ] Fresh `product.updated` sent (not resend)
- [ ] Stripe delivery status = `200 OK`
- [ ] WP audit/dead-letter check shows no new failures
- Evidence:
  - Event ID: `<evt_xxx>`
  - Screenshot/log link: `<link-or-note>`

## 2) Membership checkout (Stripe promo codes)
- [ ] Modal membership checkout opens and creates Stripe Checkout session
- [ ] Promo code field appears when level allows promotion codes
- [ ] Valid Stripe promo code applies in hosted checkout
- [ ] Checkout succeeds; membership/order created in WP
- Evidence:
  - Level ID: `<id>`
  - Stripe session ID: `<cs_xxx>`
  - Order ID / membership ID: `<id>`

## 3) Legacy hardening
- [ ] Legacy discount widget not visible on `[khm_checkout]` pages (default)
- [ ] Admin warning appears for published legacy `[khm_checkout]` pages
- [ ] "Create Draft Replacements" creates draft pages successfully
- [ ] Draft page with `[khm_membership_checkout_button level_id="X"]` works
- Evidence:
  - Draft IDs: `<id, id>`
  - Notes/screenshots: `<link-or-note>`

## 4) Transactional promo/voucher flow (WP codes, commerce/social-strip)
- [ ] Apply WP promo code succeeds in transactional flow
- [ ] Cart totals reflect discount (`discount`, `total`)
- [ ] Payment intent/charge uses discounted total
- [ ] Finalized order stores `discount_code`, `discount_amount`, `subtotal`, `total`
- [ ] Promo usage tracked and cleared after success
- Evidence:
  - Promo code: `<code>`
  - Payment intent: `<pi_xxx>`
  - Order ID: `<id>`

## 5) Regression checks
- [ ] Member portal/account pages load
- [ ] Gift voucher redeem endpoint still works
- [ ] No critical PHP errors/fatals in logs during test window

## Result
- QA status: `<PASS / FAIL / PASS with notes>`
- Blocking issues: `<none or list>`
- Follow-ups (non-blocking): `<list>`
