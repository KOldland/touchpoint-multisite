# PAID-06 — Accounting Integration & Settlement Automation Runbook

## Overview

PAID-06 automates delivery of settled ledgers to accounting systems (SFTP or REST API). It adds:
- Sandbox accounting adapters (`SftpAccountingAdapter`, `AccountingApiAdapter`)
- Delivery orchestration with idempotency, retry/backoff, and DLQ (`SettlementDeliveryService`)
- External ACK REST endpoint (`POST /wp-json/kh-smma/v1/settlement-ack`)
- WP-CLI command (`wp kh-smma paid:deliver`)
- Delivery tracking table (`wp_kh_paid_settlement_deliveries`)

**Required capability:** `kh_paid_finance` (or `manage_options`).

---

## Adapter Selection

| Adapter | Slug | Description |
|---------|------|-------------|
| SFTP | `sftp` | Uploads CSV ledger to an SFTP server. Credentials via env vars. |
| Accounting API | `accounting_api` | POSTs JSON payload to a REST accounting endpoint. Credentials via env vars. |

Configure the default adapter in `config/paid_adapters.php`:

```php
'delivery' => [
    'default_adapter' => getenv('KH_AD_DELIVERY_ADAPTER') ?: 'sftp',
    ...
],
```

Or pass `--adapter=accounting_api` to the CLI.

---

## Credentials

**SFTP:**
- `KH_SFTP_HOST` — Hostname or IP of the SFTP server
- `KH_SFTP_PORT` — Port (default 22)
- `KH_SFTP_USER` — SFTP username
- `KH_SFTP_REMOTE_PATH` — Remote directory (default `/settlements/`)
- SFTP password / private key: loaded from `CredentialVault::get('kh_sftp_password')` at runtime (NOT in config or code)

**Accounting API:**
- `KH_ACCOUNTING_API_URL` — REST endpoint URL
- API key: loaded from `CredentialVault::get('kh_accounting_api_key')` at runtime

> **Security:** Credentials MUST NOT be hardcoded in source or committed to version control. Use env vars for host/user config; use `CredentialVault` for secrets. Secret scan runs in CI (`php scripts/ci-safety-check.php`).

---

## Delivery Workflow

```
1. SettlementWorker creates wp_kh_paid_settlements rows (PAID-05)
2. SettlementDeliveryService::deliver($settlement_id, $adapter) is called
   a. DeliveryIdempotencyStore checks for existing delivery → return cached if found
   b. adapter->dry_run($settlement) → validates settlement shape + computes checksum
   c. adapter->execute($settlement) → transmits ledger
   d. INSERT row into wp_kh_paid_settlement_deliveries (status='delivered')
   e. AuditLogger records paid_delivery.delivered
   f. do_action('kh_paid_delivery_complete', $delivery_row)
3. External accounting system POSTs ACK to /wp-json/kh-smma/v1/settlement-ack
   a. SettlementAckController::handle_ack() validates delivery_id
   b. Optional: checksum verification (warning on mismatch, not blocking)
   c. SettlementDeliveryService::record_ack() → status='acked', acked_at=now()
   d. AuditLogger records paid_delivery.acked
   e. do_action('kh_paid_delivery_acked', $delivery_row)
```

---

## Retry & Backoff

If a delivery fails:
- The delivery row is created with `status='failed'` and `attempts=1`
- `SettlementDeliveryService::retry($delivery_id, $adapter)` re-executes the delivery
- `attempts` is incremented on each retry
- When `attempts >= max_retries` (default 3), status changes to `failed_permanent` (DLQ)
- `do_action('kh_paid_delivery_dlq', $delivery_row)` fires on DLQ escalation

**Configuring backoff** (application layer, not enforced by the service):
```php
'delivery' => [
    'max_retries'   => 3,
    'retry_backoff' => [60, 300, 900],  // seconds: 1 min, 5 min, 15 min
    ...
],
```

A cron job or CLI script should check `config['retry_backoff']` and schedule retry calls accordingly.

---

## DLQ Recovery

Deliveries in `failed_permanent` status require manual intervention:

1. Investigate the `last_error` field in `wp_kh_paid_settlement_deliveries`
2. Fix the root cause (credentials, endpoint, firewall)
3. Invalidate idempotency and re-deliver via CLI:

```bash
wp kh-smma paid:deliver --settlement_id=sett_xxx --adapter=sftp --force
```

The `--force` flag calls `DeliveryIdempotencyStore::invalidate()` before re-delivery.

---

## WP-CLI Commands

### Deliver a specific settlement

```bash
wp kh-smma paid:deliver --settlement_id=sett_abc123 --adapter=sftp
```

### Dry run (validate without delivering)

```bash
wp kh-smma paid:deliver --settlement_id=sett_abc123 --adapter=sftp --dry_run
```

### Deliver all undelivered settlements (batch)

```bash
wp kh-smma paid:deliver --adapter=sftp
wp kh-smma paid:deliver --adapter=sftp --sponsor_id=sp_456
wp kh-smma paid:deliver --adapter=sftp --date_start=2026-03-01 --date_end=2026-03-31
```

### Force re-delivery (bypass idempotency)

```bash
wp kh-smma paid:deliver --settlement_id=sett_abc123 --adapter=sftp --force
```

---

## REST Endpoints

### POST /wp-json/kh-smma/v1/settlement-ack

Records an external acknowledgement from the accounting system.

**Request body:**
```json
{
  "delivery_id": "del_abc123def456",
  "checksum": "a3f2c1...",    // optional: SHA256 of received payload
  "notes": "Received OK"      // optional
}
```

**Response (200):**
```json
{
  "delivery_id": "del_abc123def456",
  "settlement_id": "sett_xyz789abc012",
  "adapter": "sftp",
  "status": "acked",
  "acked_at": "2026-03-04T01:05:00Z",
  ...
}
```

**Errors:**
- `400` — `delivery_id` missing
- `403` — insufficient capability (logs `unauthorized_admin_access`)
- `404` — delivery not found

---

## CSV Ledger Format

The SFTP adapter transmits the ledger as a CSV file:

```
settlement_id,sponsor_id,currency,total_settled,fx_rate,settled_at,reconciliation_ids,batch_size
sett_abc123def456,sp_456,AUD,118.4000,1.000000,2026-03-04 00:00:00,"[""rec_001""]",1
```

Checksum (SHA256 of the full CSV string including header) is stored in `wp_kh_paid_settlement_deliveries.checksum` and returned in the delivery response. Auditors can re-generate the CSV and verify:

```bash
sha256sum settlement_sett_abc123def456.csv
```

---

## JSON Payload Format (Accounting API)

The API adapter transmits the payload as JSON:

```json
{
  "settlement_id": "sett_abc123def456",
  "sponsor_id": "sp_456",
  "currency": "AUD",
  "total_settled": "118.4000",
  "fx_rate": "1.000000",
  "settled_at": "2026-03-04 00:00:00",
  "reconciliation_ids": "[\"rec_001\"]",
  "batch_size": "1"
}
```

---

## Database Schema

### wp_kh_paid_settlement_deliveries

| Column | Type | Description |
|--------|------|-------------|
| `delivery_id` | VARCHAR(32) PK | `del_` + SHA256(settlement_id\|adapter\|timestamp)[0:12] |
| `settlement_id` | VARCHAR(32) | References `wp_kh_paid_settlements.settlement_id` |
| `adapter` | VARCHAR(50) | `sftp` or `accounting_api` |
| `status` | VARCHAR(30) | `pending`, `delivering`, `delivered`, `acked`, `failed`, `failed_permanent` |
| `delivery_idempotency_key` | VARCHAR(255) | `{settlement_id}\|{adapter}` |
| `delivered_at` | DATETIME NULL | When ledger was transmitted |
| `acked_at` | DATETIME NULL | When external ACK was received |
| `checksum` | VARCHAR(64) NULL | SHA256 of transmitted payload |
| `attempts` | TINYINT UNSIGNED | Number of delivery attempts |
| `last_error` | TEXT NULL | Last error message |
| `created_at` | DATETIME | Row creation time |
| `updated_at` | DATETIME | Last status change |

---

## Audit Events

| Event | Trigger |
|-------|---------|
| `paid_delivery.delivered` | Successful delivery via adapter |
| `paid_delivery.failed` | Delivery attempt failed |
| `paid_delivery.retry_failed` | Retry attempt failed |
| `paid_delivery.dlq` | Max retries exhausted → DLQ |
| `paid_delivery.acked` | External ACK received |
| `paid_delivery.checksum_mismatch` | ACK checksum differs from stored (warning only) |
| `paid_delivery.batch_error` | Single error in batch run (non-fatal) |
| `unauthorized_admin_access` | Unauthenticated ACK endpoint attempt |

---

## Action Hooks

| Hook | Args | Description |
|------|------|-------------|
| `kh_paid_delivery_complete` | `$delivery_row` | Fires on successful delivery |
| `kh_paid_delivery_dlq` | `$delivery_row` | Fires when delivery enters DLQ |
| `kh_paid_delivery_acked` | `$delivery_row` | Fires when ACK received |

---

## Permission Model

- `kh_paid_finance` or `manage_options` required for all delivery operations
- Assigned to: `administrator` role by default (via `CapabilityManager`)
- REST ACK endpoint requires the same capability (logs `unauthorized_admin_access` on 403)

---

## Rollback Procedure

If a delivery must be reversed:
1. There is no automatic reversal — accounting system must be notified manually
2. Update the delivery row status manually via WP-CLI or direct DB:
   ```sql
   UPDATE wp_kh_paid_settlement_deliveries
   SET status = 'failed', last_error = 'Manually reversed by ops', updated_at = NOW()
   WHERE delivery_id = 'del_xxx';
   ```
3. Invalidate idempotency store:
   ```bash
   wp option delete kh_paid_del_idem_<md5(settlement_id|adapter)>
   ```
4. Re-run settlement and delivery as needed

---

## Testing

```bash
cd app/public/wp-content/plugins/kh-smma

# Unit tests (12)
vendor/bin/phpunit tests/Paid/SettlementDeliveryTest.php

# Integration tests (4)
vendor/bin/phpunit tests/integration/SettlementAccountingIntegrationTest.php

# Full PAID suite (55 existing + 16 new = 71 pass)
vendor/bin/phpunit tests/Paid/ tests/integration/ tests/PaidAdaptersTest.php tests/ManualExportAdapterTest.php

# Golden fixture verification
php scripts/verify_golden_fixtures.php

# WP-CLI smoke (staging)
wp kh-smma paid:settle --sponsor_id=sp_456 --currency=AUD
wp kh-smma paid:deliver --settlement_id=<id_from_above> --adapter=sftp --dry_run
wp kh-smma paid:deliver --settlement_id=<id_from_above> --adapter=sftp
```

---

## Out of Scope (PAID-06)

- Real SFTP transport (phpseclib or similar) — sandbox only
- Real HTTP delivery — sandbox only
- Admin UI for delivery status tracking — deferred to PAID-07
- Multi-adapter fan-out per settlement (single adapter per delivery)
- Automated retry scheduling via WP-cron (manual retry via CLI for now)
