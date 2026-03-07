# Sandbox Adapter Runbook

Owner: @paid-adapter-team
Last updated: 2026-03-04 (PAID-03)

## Overview

`LinkedInSandboxAdapter` and `GoogleSandboxAdapter` are deterministic, offline
implementations of `PaidAdapterContract`. They simulate LinkedIn Ads and Google Ads
campaign creation without calling any external API.

**When to use sandbox adapters**
- CI / CIC golden fixture validation
- Staging environments where real credentials are not available
- Finance / reconciliation testing without real spend
- Local development

---

## Deterministic Algorithm

Sandbox adapters use a seeded pseudo-random algorithm so outputs are **identical for
identical inputs**. The only source of variance is a SHA-256 derived delta.

### Seed derivation

```php
$seed = hash('sha256', "{$manifest_id}|{$op_id}|{$idempotency_key}|{$adapter_name}");
```

Example (LinkedIn, canonical fixture):
```
manifest_id   = man_20260303_001
op_id         = op_1
idem_key      = a1b2c3d4-e5f6-7890-abcd-ef1234567890
adapter       = linkedin_sandbox
seed          = 51a139aa874bfa20b127d60cee51a0d172730725f7926b6f708d908032a26403
```

### operation_id_on_channel

```php
// LinkedIn
$op_id_on_channel = 'li_op_' . substr($seed, 0, 12);
// Google
$op_id_on_channel = 'g_op_'  . substr($seed, 0, 12);
```

### Spend delta

```php
$delta   = -0.03 + (0.03 - (-0.03)) * hexdec(substr($seed, 0, 8)) / 0xFFFFFFFF;
$actual  = round($estimated * (1 + $delta), 2);
```

The delta is always in the range `[-0.03, +0.03]`, giving ±3% variance from estimated spend.

### Estimated spend (dry_run and execute)

```php
$estimated = $bid['amount'] * $duration_days;
// duration_days = ceil((strtotime($end_time) - strtotime($start_time)) / 86400)
```

---

## simulate_failures (test only)

To force operation failures in tests, add `simulate_failures` to `manifest.meta`:

```json
{
  "meta": {
    "idempotency_key": "...",
    "simulate_failures": {
      "op_1": true
    }
  }
}
```

- Key: `operation_id` to fail
- Value: `true` = retryable, `false` = non-retryable

The adapter returns `partial_success` status with a structured `error` object for each
failed operation. In production flows, `simulate_failures` is **not expected** and should
not be present in real manifests.

---

## Sandbox vs Production Mode

Toggle via `kh_ad_adapter_mode` WP option or `KH_AD_ADAPTER_MODE` environment variable:

| Mode         | Adapters used               |
|--------------|-----------------------------|
| `sandbox`    | LinkedInSandboxAdapter, GoogleSandboxAdapter |
| `production` | Real provider adapters (when available)       |

Default is `sandbox`. **Sandbox mode is always active in CI** (env `KH_AD_ADAPTER_MODE` not set → option defaults to `sandbox`).

To switch in staging (WP admin or WP-CLI):
```bash
wp option update kh_ad_adapter_mode production
```

To switch via environment:
```bash
export KH_AD_ADAPTER_MODE=production
```

---

## Idempotency

Both sandbox adapters store execute responses via `AdapterIdempotencyStore` (WP option-backed).

Option key format: `kh_paid_idem_{md5(idempotency_key)}`

To inspect idempotency records:
```bash
wp option get kh_paid_idem_$(php -r "echo md5('your-idempotency-key-here');")
```

To clear all idempotency records (useful in dev/staging):
```bash
wp option list --search="kh_paid_idem_*" --field=option_name | xargs -I{} wp option delete {}
```

A cached response will be returned for any repeat call with the same `idempotency_key`.
To re-execute, use a **new** `idempotency_key`.

---

## Running Sandbox Tests

```bash
cd app/public/wp-content/plugins/kh-smma

# Unit tests
vendor/bin/phpunit tests/Paid/LinkedInSandboxAdapterTest.php   # 7 tests
vendor/bin/phpunit tests/Paid/GoogleSandboxAdapterTest.php     # 7 tests

# Integration smoke
vendor/bin/phpunit tests/integration/PaidSandboxIntegrationTest.php  # 2 tests

# Full PAID suite
vendor/bin/phpunit tests/Paid/ tests/PaidAdaptersTest.php tests/ManualExportAdapterTest.php

# Golden fixture verification
php ../../../../../../scripts/verify_golden_fixtures.php
```

All tests run **100% offline** with zero network calls.

---

## Golden Fixtures

| Fixture | Description |
|---|---|
| `paid_adapter_dry_run_manifest.json` | Canonical input (LinkedIn channel) |
| `google_sandbox_dry_run_manifest.json` | Input manifest with channel=google |
| `linkedin_sandbox_execute_response.json` | Expected LinkedIn execute output |
| `google_sandbox_execute_response.json` | Expected Google execute output |

Computed constants for canonical fixture:
- LinkedIn: `operation_id_on_channel = li_op_51a139aa874b`, `actual_spend = 59.35`
- Google: `operation_id_on_channel = g_op_84a2dfb9e9a6`, `actual_spend = 60.07`
