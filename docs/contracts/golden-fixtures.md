# CIC-01 Golden Fixtures

Canonical fixture directory: `app/public/wp-content/plugins/kh-smma/tests/fixtures/golden/`

## Core fixtures

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

## Sidecar metadata contract

Each core fixture has `<fixture>.meta.json` with:

- `version`
- `prompt_hash`
- `prompt_version`
- `created_at`
- `author`
- `checksum`
- `notes`

## Local verification

```bash
php scripts/verify_golden_fixtures.php
```

## Local deterministic run

```bash
export KH_SMMA_TEST_MODE=ci
export KH_SMMA_GOLDEN_FIXTURE=generate_awareness_ok.json
vendor/bin/phpunit tests/Lib/MockLLMClientTest.php
```
