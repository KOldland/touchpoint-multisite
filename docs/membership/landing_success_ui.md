# MEM-03 Landing Success UX

## Endpoint contract

- Contract: [docs/contracts/landing_success.json](docs/contracts/landing_success.json)
- Endpoint: `GET /wp-json/kh-membership/v1/landing-success?session_id=<id>`
- Telemetry endpoint: `POST /wp-json/kh-membership/v1/landing-telemetry`

## UX modes

- **Modal mode**: shown for same-page flows when `session_id` is present and modal path is enabled.
- **Dedicated page mode**: rendered via `templates/success.php` when landing shortcode sees `session_id` query parameter.

Both modes render server-resolved schedule/sponsor metadata, status copy, CTAs, and consent-aware attribution.

## Privacy rules

- `consent=true`: attribution object is rendered.
- `consent=false`: `attribution=null` and sponsor block is omitted.
- If `payload.user_id` does not match `get_current_user_id()`, sensitive attribution/sponsor data is hidden.

## CTA actions

Returned in `ctas[]` and rendered as buttons:

- Go to account (`account_url`)
- Download welcome pack (`download`)
- Invite a friend (`invite`)
- Manage membership (`manage`)

## Telemetry

- `landing.success` emitted when success payload is rendered.
- `landing.cta.clicked` emitted with `cta_name` and `session_id` on CTA click.
- Telemetry excludes email/token PII.

## Pending polling policy

When endpoint returns `status=pending`, client polls `landing-success` with exponential backoff:

- Delay progression: 1s, 2s, 4s, 8s, 8s
- Max attempts: 5 (config in `landing.js`)

## Staging QA runbook

1. Complete checkout and capture `session_id`.
2. Visit success URL with `session_id` and verify status copy + sponsor/schedule data.
3. Validate consent behavior:
   - `consent=true` shows attribution.
   - `consent=false` hides attribution/sponsor block.
4. Test keyboard-only modal/page navigation and focus visibility.
5. Run UI a11y spec and attach axe report.
6. Validate telemetry events in logs (`landing.success`, `landing.cta.clicked`).
