# MEM-01 Landing UI + Signup Init (Backend phase)

## Contracts

- Canonical attribution schema: `docs/contracts/membership_attribution.json`
- Signup-init contract: `docs/contracts/signup-init.json`
- Endpoint: `POST /wp-json/kh-membership/v1/signup-init`
- Success payload endpoint: `GET /wp-json/kh-membership/v1/landing-success?session_id=...`

## Consent and privacy behavior

- `consent=true`: server stores canonical attribution payload in temporary store for 24h.
- `consent=false`: server stores minimal marker only (`consent=false`, no persisted UTM fields).
- Temporary store key format: `khm_temp_attribution_{session_id}`.
- Idempotency store key format: `khm_signup_init_idem_{md5(idempotency_key)}`.

## Security behavior

- `schedule_id` and `sponsor_id` are sanitized and validated server-side.
- Sponsor mismatch/invalid sponsor returns `MBR_ERR_INVALID_SPONSOR` (422).
- Public signup-init endpoint has transient-backed rate limiting.
- Sponsor blurbs are sanitized with allowlisted tags before rendering.

## Telemetry

- Landing render emits `landing.view` (`source=landing`, schedule/sponsor context).
- Successful signup-init emits `landing.submit` (session/schedule/sponsor/consent/source only).
- No PII is emitted by these events.

## Local QA checklist

1. Create a page with shortcode:
   - `[khm_landing_page schedule_id="sch_123" sponsor_id="sp_456"]`
2. Open page with referral query params:
   - `?utm_source=newsletter&utm_medium=email&utm_campaign=spring`
3. Click a CTA (Join/Subscribe/Claim Offer).
4. Verify request payload in browser devtools includes canonical attribution fields and UUID idempotency key.
5. Confirm response payload contains `checkout_url`, `session_id`, `temp_store_ttl_seconds=86400`.
6. Validate temporary store:
   - `wp option get khm_temp_attribution_{session_id} --path=/path/to/site`
7. Toggle consent off and repeat:
   - verify persisted payload contains `consent=false` and null UTM fields.
8. Run accessibility quick pass with axe on landing page controls and status/error announcements.

## Staging notes

- Use Stripe test mode or mock mode filter for deterministic runs.
- Keep feature flag defaults aligned with rollout policy (`khm_membership_transactional_emails_enabled=false` on staging).
- Attach artifacts (network payloads + option snapshots + axe output) to MEM-01 PR.
