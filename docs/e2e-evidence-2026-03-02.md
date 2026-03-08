# Membership Notifications & UX E2E Evidence

Date: 2026-03-02

## Scope executed
- Signup init (canonical attribution payload) -> Checkout URL generation
- Signed Stripe `checkout.session.completed` webhook dispatch + replay
- Membership/attribution persistence checks
- Email idempotency marker checks

## Environment notes
- Local DB initially lacked `membership_tier`, `user_membership`, `promotion_attribution`, and processed webhook tables required by the new membership flow.
- For evidence execution, local-only schema fixtures were bootstrapped.
- HTTP endpoint calls to `http://touchpoint-template-remote.local` did not map to this workspace code path; execution used internal `rest_do_request` in this workspace to validate handlers.

## Artifacts
- Signup request payload:
  - `logs/e2e-2026-03-02/signup-request.json`
- Signup response (includes real Stripe Checkout URL):
  - `logs/e2e-2026-03-02/signup-response.json`
- Stripe fixture creation (product + price):
  - `logs/e2e-2026-03-02/stripe-create-price.txt`
- Webhook signed payload fixture:
  - `logs/e2e-2026-03-02/webhook-checkout-session-completed.json`
- Webhook initial response:
  - `logs/e2e-2026-03-02/webhook-response-initial.json`
- Replay response (idempotency behavior):
  - `logs/e2e-2026-03-02/webhook-response-replay-after-processed.json`
- Cron execution output:
  - `logs/e2e-2026-03-02/wp-cron-due-now.txt`
- Manual webhook worker processing output:
  - `logs/e2e-2026-03-02/manual-process-output.txt`
- Post-webhook DB state:
  - `logs/e2e-2026-03-02/post-webhook-db-state.json`
- Email idempotency usermeta evidence:
  - `logs/e2e-2026-03-02/email-idempotency-meta.json`

## Observed outcomes
1. Signup flow succeeded and returned Stripe Checkout redirect URL.
2. Signed webhook accepted (`status=queued`), replay returned `already processed` after processing.
3. Membership row created and set to `active`.
4. Attribution row created with expected schedule/sponsor/UTM context.
5. User meta captured attribution fields.
6. Welcome/payment email idempotency keys recorded in user meta.

## Gaps found during E2E (hardening follow-up)
1. Route registration wiring bug:
   - Membership routes were not registered in runtime until bootstrap was adjusted to call `register_routes()` immediately.
2. Async worker callback availability:
   - Cron run executed but queued webhook did not process in this runtime until manual processing.
   - Indicates callback wiring is coupled to REST bootstrap path.
3. Processed webhook replay generated duplicate-key DB warning before returning idempotent response.
4. Local fixture schema mismatch exposed formatting/column anomalies in attribution row values (needs schema alignment migration and insert-format verification).

## Recommendation before wider release
- Keep this artifact set as proof of baseline behavior, but treat the four hardening gaps above as blockers for final sign-off.
