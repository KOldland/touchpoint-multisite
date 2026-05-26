Title: MEM-01: Landing shortcode + signup-init server contract + client UX

Short summary of feature and why: landing shortcode, sponsor co-branding, canonical signup-init contract, consent and idempotency handling.

Files changed:
- app/public/wp-content/plugins/khm-plugin/src/Membership/LandingPageShortcode.php
- app/public/wp-content/plugins/khm-plugin/assets/js/landing.js
- app/public/wp-content/plugins/khm-plugin/src/Membership/SignupEndpoint.php
- app/public/wp-content/plugins/khm-plugin/src/Services/MembershipRepository.php
- app/public/wp-content/plugins/khm-plugin/templates/landing.php
- docs/contracts/cic-01-golden-contract.json
- docs/contracts/golden-fixtures.md
- docs/membership/landing_ui.md

Contract files added:
- docs/contracts/membership_attribution.json
- docs/contracts/signup-init.json

Tests added:
- app/public/wp-content/plugins/khm-plugin/tests/Membership/LandingShortcodeTest.php
- app/public/wp-content/plugins/khm-plugin/tests/Membership/SignupInitTest.php
- app/public/wp-content/plugins/khm-plugin/tests/fixtures/golden/signup_init_success.json.meta.json

Security notes:
- Sponsor validation is enforced server-side for schedule/sponsor consistency.
- Consent=false stores a minimal marker only and omits persisted UTM fields.
- Invalid sponsor/schedule combinations return 422 (`MBR_ERR_INVALID_SPONSOR`).
- Idempotency key dedupes repeated requests and returns the same `session_id`/checkout URL.

Broader suite note:
- Broader membership-suite failures are pre-existing and tracked in tickets #X and #Y — not introduced by this change.
