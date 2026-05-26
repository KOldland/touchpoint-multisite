# Membership Notifications & Landing UX Runbook

## Purpose
Manual QA steps for landing signup, checkout/webhook processing, attribution visibility, and notification email behavior.

## Preconditions
- Stripe test mode keys and webhook secret configured.
- Webhook endpoint enabled: `/wp-json/kh-membership/v1/webhook/stripe`.
- At least one active `membership_tier` with valid Stripe price mapping.
- Landing page rendered from `LandingPageShortcode`.
- Admin access to:
  - Membership members list/detail
  - Membership reports
  - Email Preview screen

## Scenario A: Landing -> Checkout redirect
1. Open landing page URL with query params, for example:
   - `?utm_source=newsletter&utm_medium=email&utm_campaign=q1-promo`
2. Enter valid email.
3. Leave consent unchecked and submit:
   - Expect inline consent validation error.
4. Check consent and submit:
   - Expect success message and redirect to Stripe Checkout URL.
   - Expect no raw stack trace; only friendly UX copy.

## Scenario B: Checkout completion -> membership + attribution
1. Complete Stripe Checkout in test mode.
2. Trigger or wait for `checkout.session.completed`.
3. Verify `user_membership` row is active/trial as expected.
4. Verify `promotion_attribution` row exists once (no duplicates on replay):
   - Contains `schedule_id`, `sponsor_id`, `utm_*`, `phase_at_click`, `conversion_type`, `reference_metadata`.
5. Replay same webhook event:
   - Confirm idempotent no-op.

## Scenario C: Payment renewal email flow
1. Trigger `invoice.paid` for same member.
2. Verify membership payment fields update.
3. Verify payment confirmation email sends once for that invoice reference.

## Scenario D: Admin visibility
1. Open Members list:
   - Confirm attribution columns visible and populated where available.
   - Confirm schedule and sponsor links work.
2. Open member detail:
   - Confirm attribution block shows schedule/sponsor names and IDs.
3. Open reports:
   - Confirm attribution fields are present and filterable.

## Scenario E: Email preview
1. Open Email Preview admin page.
2. Select `welcome` and `payment_confirmation`.
3. Load sample data and preview each template.
4. Send test email to a mailbox and verify delivery/rendering.

## Expected telemetry/audit signals
- `landing.success`
- `membership.attribution.created`
- `membership.email.welcome.sent`
- `membership.email.payment.sent`
- `membership.email.failed` (if send fails)

## Notes
- Attribution should only be persisted when consent is true.
- Client-facing errors should include support code when provided by API response.

## Evidence Capture (2026-03-02)
- See `docs/e2e-evidence-2026-03-02.md` for executed run and artifacts.
- See `docs/a11y-evidence-2026-03-02.md` for Lighthouse + axe accessibility evidence and remediation.
- See `docs/queue-hardening-evidence-2026-03-02.md` for queue retry/backoff and idempotency evidence.
- See `docs/transactional-email-safety-controls-2026-03-02.md` for the admin safety toggle, rollback procedure, and monitoring hooks.
- Primary artifact folder:
  - `logs/e2e-2026-03-02/`

## Staging Smoke Execution (QA)
1. Set Stripe test keys + webhook secrets in staging settings.
2. Confirm `khm_membership_transactional_emails_enabled=1` for smoke test run.
3. Execute Scenario A-E above on staging.
4. Attach artifacts:
   - webhook event payloads
   - screenshot of member attribution in admin
   - screenshot of Email Preview send/receipt
   - logs for any `membership.email.failed` / `webhook.invalid_signature`

Record:
- Date/time:
- Tester:
- Result (pass/fail):
- Defects/notes:

## Monitoring Window (Ops)
- Observe for first 1-2 hours after staging deployment:
  - `membership.email.failed`
  - `membership.email.sent.failed`
  - `membership.attribution.missing`
  - `webhook.invalid_signature`
  - `membership.email.skipped`
- Alert threshold guidance:
  - failures > 5/hour OR
  - failure rate > 1% of conversions
