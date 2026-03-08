# Reconciliation Service Runbook

Owner: @paid-adapter-team
Last updated: 2026-03-04 (PAID-04)

## Overview

`PaidReconciliationService` ingests adapter `execute()` responses, writes
canonical rows to `wp_kh_paid_reconciliations`, and alerts on large spend
discrepancies. It is the bridge between adapter outputs and Finance reporting
(PAID-05).

---

## Calling reconcile() after execute()

```php
use KH_SMMA\Reconciliation\PaidReconciliationService;

global $wpdb;
$svc = new PaidReconciliationService( $wpdb, $audit_logger );

// After adapter->execute():
$exec_response = $adapter->execute( $manifest );

// Optionally pass dry_run response and context:
$row = $svc->reconcile(
    $manifest['manifest_id'],
    $exec_response,
    $dry_run_response,                          // optional
    [
        'idempotency_key' => $manifest['meta']['idempotency_key'],
        'sponsor_id'      => $manifest['meta']['sponsor_id'] ?? '',
        'campaign_id'     => $manifest['campaign']['campaign_id'] ?? '',
    ]
);
```

Reconcile is **idempotent**: calling it again with the same manifest_id +
idempotency_key returns the cached row and does not insert a duplicate.

---

## Status values

| Status        | Meaning                                                              |
|---------------|----------------------------------------------------------------------|
| `reconciled`  | success, discrepancy within threshold (default ±10%)                 |
| `discrepancy` | success, but |discrepancy| > threshold — Finance review required      |
| `partial`     | partial_success from adapter (some operations failed)                |
| `error`       | adapter execute returned 'failed'                                    |

---

## Discrepancy threshold

Default: **10.0%**. Override at construction:

```php
$svc = new PaidReconciliationService( $wpdb, $audit_logger, 5.0 ); // 5% threshold
```

Or set `discrepancy_threshold_percent` in `config/paid_adapters.php`:

```php
'discrepancy_threshold_percent' => 10.0,
```

---

## Action hooks

### kh_paid_reconciliation_complete

Fires on every successful reconcile (all statuses). kh-ad-manager hooks here
to update sponsor spend totals.

```php
add_action( 'kh_paid_reconciliation_complete', function ( array $row ) {
    // $row contains: reconciliation_id, manifest_id, actual_spend, currency,
    //               sponsor_id, campaign_id, status, discrepancy_percent, etc.
    kh_ad_manager_handle_reconciliation( $row );
} );
```

### kh_paid_reconciliation_discrepancy

Fires only when |discrepancy| > threshold. Use for Finance alerts.

```php
add_action( 'kh_paid_reconciliation_discrepancy', function ( array $row ) {
    // Send alert email, create admin notice, etc.
} );
```

---

## REST API

All endpoints require `manage_options` capability.

### List reconciliations

```
GET /wp-json/kh-smma/v1/reconciliations
```

Query params: `sponsor_id`, `status`, `per_page` (default 25), `page`, `date_start`, `date_end`.

### Get single row

```
GET /wp-json/kh-smma/v1/reconciliations/{reconciliation_id}
```

### Re-run (returns existing row — use new idempotency_key for a fresh run)

```
POST /wp-json/kh-smma/v1/reconciliations/{reconciliation_id}/rerun
```

---

## Resolving discrepancy rows

1. Open **KH Ad Manager → Reconciliations** (WordPress admin).
2. Filter by `status = discrepancy`.
3. Review `discrepancy_percent`, `estimated_spend`, `actual_spend`.
4. If the discrepancy is expected (e.g. LinkedIn rate change), mark as resolved
   via a custom `notes` update or re-run with a corrected manifest.

---

## CSV Export for Finance

Admin: **KH Ad Manager → Reconciliations → Export CSV**.

CSV columns: `created_at`, `reconciliation_id`, `manifest_id`, `adapter`,
`status`, `estimated_spend`, `actual_spend`, `discrepancy_percent`, `currency`,
`sponsor_id`, `campaign_id`, `partial_failure`.

---

## DB table

`wp_kh_paid_reconciliations` is created via `PaidReconciliationService::install()`
on plugin activation. To create it manually (staging):

```php
( new PaidReconciliationService( $wpdb, new AuditLogger( $wpdb ) ) )->install();
```

Inspect rows:

```bash
wp db query "SELECT reconciliation_id, manifest_id, status, discrepancy_percent FROM wp_kh_paid_reconciliations ORDER BY created_at DESC LIMIT 20;"
```

---

## Running Tests

```bash
cd app/public/wp-content/plugins/kh-smma

# Unit tests (7)
vendor/bin/phpunit tests/Paid/ReconciliationServiceTest.php

# Integration tests (2)
vendor/bin/phpunit tests/integration/ReconciliationIntegrationTest.php

# Full PAID suite
vendor/bin/phpunit tests/Paid/ tests/integration/ tests/PaidAdaptersTest.php tests/ManualExportAdapterTest.php
```

---

## Hooking kh-ad-manager into spend updates

kh-ad-manager listens on `kh_paid_reconciliation_complete` and updates sponsor
spend totals via its own internal logic. No direct function call from kh-smma
is needed (loose coupling via WordPress action).

See `kh-ad-manager/src/Admin/ReconciliationPage.php` for the admin UI.
