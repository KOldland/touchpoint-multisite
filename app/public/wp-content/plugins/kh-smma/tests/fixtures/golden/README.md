# Golden Fixtures (CIC)

This directory is the deterministic contract source for MockLLM + cross-bucket test fixtures.

## Owners

- Primary owner: `@ci-qa-team`
- Secondary reviewers: bucket owners for SMMA, Compliance, Membership, Paid Adapters

## Core CIC-01 fixtures

- `generate_awareness_ok.json`
- `generate_sponsor_warn.json`
- `generate_sponsor_fail.json`
- `google_ad_draft.json`
- `compliance_ok.json`
- `compliance_warn.json`
- `compliance_fail.json`
- `checkout_session_completed.json`
- `invoice_paid.json`
- `checkout_session_no_consent.json`
- `paid_adapter_dry_run_manifest.json`
- `paid_adapter_execute_response.json`

Each fixture must have a sidecar metadata file: `<fixture>.meta.json` with:

- `version`
- `prompt_hash`
- `prompt_version`
- `created_at`
- `author`
- `checksum`
- `notes` (optional)

## Regeneration policy

1. Run `php scripts/regenerate_fixture.php --input <recorded.json> --fixture-name <name>.json --author @<handle>`.
2. Review generated fixture + metadata manually.
3. Open PR and include why fixture changed and impact.
4. Add label `golden-owner-approved` before merge.

CI will fail fixture changes when the owner label is missing or metadata/checksum is invalid.

## Security

- Do not commit secrets or PII in fixtures.
- Regeneration script blocks obvious secret patterns.
- Keep canonical placeholders for volatile fields (timestamps and ids), for example `{{UNIX_TS}}` and `{{EVENT_ID}}`.
