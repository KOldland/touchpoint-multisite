Title: MEM-03: Success UX — modal + redirect success page

What
- Add `GET /wp-json/kh-membership/v1/landing-success?session_id=<id>` with canonical payload contract.
- Deliver accessible Success UX for both flows:
  - Modal for same-page flow.
  - Dedicated success page for redirect flow.
- Render server-resolved schedule/sponsor fields, consent-aware attribution, CTA list, and telemetry.

Why
- Provide clear post-checkout state and next actions.
- Keep attribution privacy-safe by honoring `consent=true` gate.
- Make builders/other buckets consume a stable contract-first payload.

Contract-first changes
- Added: `docs/contracts/landing_success.json`
- Added fixture + sidecar:
  - `app/public/wp-content/plugins/khm-plugin/tests/fixtures/golden/landing_success_complete.json`
  - `app/public/wp-content/plugins/khm-plugin/tests/fixtures/golden/landing_success_complete.json.meta.json`
- Updated fixture manifests:
  - `docs/contracts/cic-01-golden-contract.json`
  - `docs/contracts/golden-fixtures.md`

Backend changes
- Added endpoint class:
  - `app/public/wp-content/plugins/khm-plugin/src/Membership/LandingSuccessEndpoint.php`
- Endpoint behavior:
  - Returns canonical payload with `status`, `membership_status`, resolved `schedule`, optional `sponsor`, consent-aware `attribution`, `ctas[]`, `reference`.
  - Session privacy guard: hides sensitive attribution/sponsor details on user mismatch.
  - Emits `landing.success` telemetry.
- Added telemetry ingestion route:
  - `POST /wp-json/kh-membership/v1/landing-telemetry`
- Added repository helpers:
  - `resolveLandingSchedule()`
  - `resolveLandingSponsor()`
  - `buildLandingSuccessCtas()`
- Wired endpoint bootstrap and legacy compatibility:
  - `app/public/wp-content/plugins/khm-plugin/khm-plugin.php`
  - `app/public/wp-content/plugins/khm-plugin/src/Membership/SignupEndpoint.php` delegates landing-success handler.

Frontend/UI changes
- Added success page template:
  - `app/public/wp-content/plugins/khm-plugin/templates/success.php`
- Added styles:
  - `app/public/wp-content/plugins/khm-plugin/assets/css/landing.css`
- Extended client behavior:
  - `app/public/wp-content/plugins/khm-plugin/assets/js/landing.js`
  - Modal accessibility: `role="dialog"`, `aria-modal`, `aria-labelledby`, `aria-describedby`, keyboard close, focus trap, focus return.
  - Pending polling: exponential backoff, max 5 attempts.
  - CTA telemetry emission: `landing.cta.clicked` with `cta_name` and `session_id`.
  - Graceful fallback copy/support code when payload unavailable.
- Landing shortcode now supports dedicated success rendering when `session_id` is present:
  - `app/public/wp-content/plugins/khm-plugin/src/Membership/LandingPageShortcode.php`

Tests added/updated
- PHPUnit:
  - `app/public/wp-content/plugins/khm-plugin/tests/Membership/LandingSuccessEndpointTest.php`
    - `testCompletePayload()`
    - `testConsentFalseHidesAttribution()`
- UI specs (requested files):
  - `app/public/wp-content/plugins/khm-plugin/tests/UI/landing_success_a11y.spec.js`
  - `app/public/wp-content/plugins/khm-plugin/tests/UI/landing_success_snapshot.spec.js`

Executed validation
- `LandingSuccessEndpointTest`: PASS
- `SignupInitTest`: PASS
- `LandingShortcodeTest`: PASS

Accessibility notes
- Modal includes ARIA dialog semantics and keyboard operation.
- Live updates announced via polite live region.
- Focus visible styles included in `landing.css`.
- Axe spec file added for automated audit coverage.

Privacy/Security notes
- Attribution shown only when `consent=true`.
- Sponsor/attribution suppressed when session user context does not match current user.
- Sponsor blurb sanitized server-side.
- Telemetry avoids email/token PII.

Risk / follow-up
- UI specs are added as requested but require project Playwright/axe runtime in CI runner to execute.
- If desired, add these specs to CI job matrix in a follow-up infra card.

Request
- Please run staging E2E + a11y checklist in `docs/evidence/mem-03/STAGING_E2E_EVIDENCE_TEMPLATE.md` and attach artifacts.
- Owner review requested: @mem-frontend (UI/a11y), @mem-backend (contract/privacy), @qa (staging evidence).
