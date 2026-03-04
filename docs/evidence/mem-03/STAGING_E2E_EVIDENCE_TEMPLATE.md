# MEM-03 Staging E2E Evidence Template

Use this file as the PR attachment/report for staging validation of MEM-03.

## Environment

- Date:
- Environment URL:
- Branch/commit:
- WP version:
- Tester:

## Preconditions

- [ ] Contract file present: `docs/contracts/landing_success.json`
- [ ] Success endpoint reachable: `GET /wp-json/kh-membership/v1/landing-success?session_id=...`
- [ ] Test sponsor configured (logo/accent/blurb).
- [ ] Test schedule configured (title/recommended_post_time/boost_copy).
- [ ] Stripe test mode configured.

## Flow 1 — Redirect success page render

- [ ] Complete checkout in test mode and capture `session_id`.
- [ ] Visit success URL with `session_id`.
- [ ] Verify page shows:
  - [ ] membership status copy
  - [ ] schedule title
  - [ ] recommended post time (if available)
  - [ ] boost copy (if available)
  - [ ] CTA buttons from `ctas[]`
  - [ ] Print/PDF action

Evidence links:
- Checkout trace:
- Success page screenshot:
- Network response payload (landing-success):

## Flow 2 — Modal flow (single-page)

- [ ] Trigger modal success flow on landing page.
- [ ] Verify modal appears and keyboard focus lands on primary action.
- [ ] Verify focus trap works (`Tab` / `Shift+Tab`).
- [ ] Verify `Esc` closes modal and focus returns to trigger.

Evidence links:
- Modal screenshot:
- Keyboard test notes/video:

## Flow 3 — Consent-aware attribution

### Case A: consent=true
- [ ] `consent` in payload is `true`.
- [ ] Attribution block is visible.
- [ ] Sponsor co-brand block is visible (name/logo/accent/blurb).

### Case B: consent=false
- [ ] `consent` in payload is `false`.
- [ ] `attribution` is `null`.
- [ ] Sponsor/attribution section is hidden.

Evidence links:
- consent=true payload + screenshot:
- consent=false payload + screenshot:

## Flow 4 — Pending and failed states

### Pending
- [ ] Force/observe `status=pending`.
- [ ] Friendly pending copy is shown.
- [ ] Polling retries occur and eventually stop at policy limit.

### Failed
- [ ] Force/observe `status=failed`.
- [ ] Error/support message displays with support code.

Evidence links:
- Pending logs/timeline:
- Failed state screenshot:

## Flow 5 — Telemetry

- [ ] `landing.success` emitted once on render.
- [ ] `landing.cta.clicked` emitted with `cta_name` and `session_id`.
- [ ] Telemetry payload contains no email/token PII.

Evidence links:
- Telemetry logs (landing.success):
- Telemetry logs (landing.cta.clicked):

## Accessibility checks

- [ ] Dialog has `role="dialog"` and `aria-modal="true"`.
- [ ] Dialog has `aria-labelledby` and `aria-describedby`.
- [ ] Live updates announced with `aria-live="polite"`.
- [ ] Keyboard-only navigation works.
- [ ] Focus indicators visible.
- [ ] Contrast meets WCAG AA for text/background.
- [ ] Axe audit run with no serious/critical violations.

Evidence links:
- Axe report:
- Accessibility notes:

## API contract validation

- [ ] Response conforms to `docs/contracts/landing_success.json` for complete state.
- [ ] Response conforms for consent=false state.
- [ ] `ctas[]` includes expected action values.

Evidence links:
- Contract validation output:

## Sign-off

- Result: [ ] PASS  [ ] PASS WITH NOTES  [ ] FAIL
- Notes:
- Follow-up tickets:
