# Author Policy Enforcement Phase Handover

## Scope delivered
- Server-enforced Author policy lifecycle for planner sessions.
- Dedicated Author policy read/save endpoint (`GET/POST /dual-gpt/v1/planner/author-policy`).
- Author Agent prompt + validation enforcement bound to effective session policy.
- Planner UI parity for Author policy read/edit/save/reset.
- Session-level Author validation summary in planner detail (aggregated from author outputs).

## Behavior and controls
- Policy fields:
  - `reporter_voice_required`
  - `disallow_first_person`
  - `disallow_em_dash`
  - `disallow_rhetorical_binaries`
  - `disallow_listicle_framing`
  - `disallow_tidy_conclusion`
  - `min_words`
  - `max_words`
  - `banned_phrases`
- Save semantics:
  - Save updates enforcement policy in session meta.
  - Save does **not** re-run planner phases.
  - Endpoint returns `changed` to support no-op UX messaging.

## Planner UX notes
- Author policy panel now shows:
  - effective policy snapshot,
  - editable controls,
  - save/reset actions,
  - aggregated Author validation summary (`errors`, `warnings`, drafts with output, failed runs),
  - first five validation issues.

## Verification commands
- Targeted dual-gpt tests:
  - `vendor/bin/phpunit -c app/public/wp-content/plugins/dual-gpt-wordpress-plugin/tests/phpunit.xml`

## Files added for targeted test coverage
- `app/public/wp-content/plugins/dual-gpt-wordpress-plugin/tests/bootstrap.php`
- `app/public/wp-content/plugins/dual-gpt-wordpress-plugin/tests/phpunit.xml`
- `app/public/wp-content/plugins/dual-gpt-wordpress-plugin/tests/AuthorPolicyPluginTest.php`
- `app/public/wp-content/plugins/dual-gpt-wordpress-plugin/tests/AuthorAgentPolicyConstraintsTest.php`
