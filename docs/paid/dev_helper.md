# PAID Sandbox Developer Helper

## Overview

`scripts/paid_sandbox_helper.php` lets you run any sandbox adapter locally, compare output against golden fixtures, and inspect the normalised JSON without a WordPress installation.

**Guard:** The script requires `PAID_DEV_HELPER=true` to prevent accidental execution. It exits with code 2 and a usage message if the env var is missing.

---

## Prerequisites

```bash
cd app/public/wp-content/plugins/kh-smma
composer install --prefer-dist --no-interaction
```

No WordPress installation, database, or network connectivity required.

---

## Usage

```bash
PAID_DEV_HELPER=true php scripts/paid_sandbox_helper.php \
  --adapter=<google|linkedin|manual> \
  --mode=<dry_run|execute> \
  [--manifest=<path-to-manifest.json>]
```

### Options

| Option | Values | Description |
|--------|--------|-------------|
| `--adapter` | `google`, `linkedin`, `manual` | Sandbox adapter to invoke (required) |
| `--mode` | `dry_run`, `execute` | Method to call (required) |
| `--manifest` | path to JSON file | Manifest input (optional; defaults to `google_sandbox_dry_run_manifest.json`) |

---

## Examples

### Google dry_run

```bash
PAID_DEV_HELPER=true php scripts/paid_sandbox_helper.php \
  --adapter=google --mode=dry_run
```

Expected output — normalised dry_run response with `total_estimated_spend`, `operations`, `currency`. Compare against `tests/fixtures/golden/google_sandbox_dry_run_manifest.json`.

### LinkedIn execute

```bash
PAID_DEV_HELPER=true php scripts/paid_sandbox_helper.php \
  --adapter=linkedin --mode=execute
```

Expected output — execute response with `status: success`, deterministic `operation_id_on_channel`, and `total_actual_spend`.

### ManualExport execute (custom manifest)

```bash
PAID_DEV_HELPER=true php scripts/paid_sandbox_helper.php \
  --adapter=manual --mode=execute \
  --manifest=tests/fixtures/golden/google_sandbox_dry_run_manifest.json
```

Expected output — `{ "status": "awaiting_manual_export", "package_url": "option:kh_paid_bundle_..." }`.

### Compare against golden fixture

```bash
# Generate fresh output
PAID_DEV_HELPER=true php scripts/paid_sandbox_helper.php \
  --adapter=google --mode=dry_run \
  > /tmp/google_dry_run_fresh.json

# Diff against stored fixture (volatile fields already stripped)
diff <(cat /tmp/google_dry_run_fresh.json | jq -S .) \
     <(cat tests/fixtures/golden/google_sandbox_dry_run_manifest.json | jq -S .)
```

---

## Volatile Field Normalisation

The helper strips these fields before printing so output can be diff'd deterministically:

| Field | Reason |
|-------|--------|
| `timestamp` | Current time at adapter execution |
| `delivered_at` | Delivery timestamp |
| `acked_at` | ACK timestamp |

This mirrors the normalisation in `scripts/paid_adapter_golden_check.php`.

---

## curl Equivalents (REST API)

If you have a WordPress site running, you can use the REST API instead:

```bash
# Start a reconciliation run (POST)
curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <wp-jwt-token>" \
  -d '{"sponsor_id":"sp_456","adapters":["linkedin_sandbox"],"date_start":"2026-03-01","date_end":"2026-03-07"}' \
  https://your-site.local/wp-json/kh-smma/v1/recon/runs | jq .

# Download a manual export bundle (GET)
curl -s \
  -H "Authorization: Bearer <wp-jwt-token>" \
  https://your-site.local/wp-json/kh-smma/v1/manual-export/man_001 | jq .
```

---

## Not Registered in CI

This script is **not** part of the standard CI pipeline. It is a local developer tool only. The `PAID_DEV_HELPER=true` guard prevents accidental execution in automated environments.
