# MEM-01 Staging E2E Evidence Template

Use this file as the PR attachment/report for staging validation of MEM-01.

## Environment

- Date:
- Environment URL:
- WP version:
- Plugin branch/commit:
- Tester:

## Preconditions

- [ ] `khm_membership_transactional_emails_enabled = 0` confirmed on staging.
- [ ] Test schedule exists (`schedule_id=...`) with `recommended_post_time` and `boost_copy`.
- [ ] Test sponsor exists (`sponsor_id=...`) with logo/accent/blurb.
- [ ] Stripe test mode configured.

## Flow 1 — Landing render and referral context

- [ ] Open landing page with shortcode:
  - `[khm_landing_page schedule_id="..." sponsor_id="..."]`
- [ ] Confirm rendered fields:
  - [ ] Schedule title
  - [ ] `recommended_post_time`
  - [ ] `boost_copy`
  - [ ] Sponsor logo
  - [ ] Sponsor accent color
  - [ ] Sponsor blurb (sanitized)
- [ ] Open with referral params and confirm referral UI:
  - `?utm_source=newsletter&utm_medium=email&utm_campaign=spring`
  - [ ] “Referred by ...” is shown

Evidence links:
- Screenshot (landing):
- Screenshot (referral):

## Flow 2 — signup-init contract and temp attribution persistence

- [ ] Click CTA (Join/Subscribe/Claim Offer).
- [ ] Capture request payload to `POST /wp-json/kh-membership/v1/signup-init`.
- [ ] Confirm response contains:
  - [ ] `checkout_url`
  - [ ] `session_id`
  - [ ] `message=checkout_created`
  - [ ] `temp_store_ttl_seconds=86400`
- [ ] Confirm temp attribution store exists for returned session:
  - `wp option get khm_temp_attribution_{session_id} --path=/path/to/site`

Evidence links:
- Network request JSON:
- Network response JSON:
- WP CLI output (temp attribution):

## Flow 3 — Consent=false privacy behavior

- [ ] Repeat signup-init with consent unchecked (`consent=false`).
- [ ] Confirm stored temp attribution has no UTM fields persisted:
  - [ ] `utm_source=null`
  - [ ] `utm_medium=null`
  - [ ] `utm_campaign=null`

Evidence links:
- Network request JSON (consent=false):
- WP CLI output (consent=false record):

## Flow 4 — Sponsor validation and idempotency

- [ ] Invalid sponsor or schedule/sponsor mismatch returns 422.
- [ ] Error payload contains `error.code=MBR_ERR_INVALID_SPONSOR`.
- [ ] Repeat same request with same `idempotency_key` returns same `session_id`.

Evidence links:
- 422 response payload:
- Idempotency replay payloads (A/B):

## Flow 5 — Webhook to membership persistence and admin visibility

- [ ] Complete checkout in Stripe test flow.
- [ ] Trigger/confirm webhook processing (`checkout.session.completed` and/or `invoice.paid`).
- [ ] Confirm membership row created/updated in staging DB.
- [ ] Confirm admin membership view reflects expected member + attribution context.

Evidence links:
- stripe-cli trace/log:
- Webhook server logs:
- DB query output/screenshot:
- Admin page screenshot:

## Accessibility quick pass (landing)

- [ ] Consent checkbox announced (label/aria).
- [ ] CTA buttons are keyboard-focusable and labelled.
- [ ] Status/error announcements exposed via ARIA live regions.
- [ ] Axe quick scan run with no critical issues.

Evidence links:
- Axe report/screenshot:

## CI / Quality gates attached to PR

- [ ] Targeted tests output attached (`phpunit-mem01-targeted.txt`).
- [ ] Golden verifier output attached (`golden-fixture-verify.txt`).
- [ ] CI job links pasted in PR (full run + golden check + secret scan).

CI links:
- Full CI:
- Golden check:
- Secret scan:

## Sign-off

- Result: [ ] PASS  [ ] PASS WITH NOTES  [ ] FAIL
- Notes / incidents:
- Recommended action:
