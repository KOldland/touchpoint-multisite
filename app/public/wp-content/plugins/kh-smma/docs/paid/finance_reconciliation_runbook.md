# Finance Reconciliation Runbook

Owner: @paid-adapter-team
Last updated: 2026-03-04 (PAID-05)

## Overview

PAID-05 adds three finance-grade services on top of the PAID-04 reconciliation table:

- **PaidReconciliationAdjustmentService** — manual adjustments & reversals
- **FxService** — static-rate currency conversion
- **SettlementWorker** — batch settlement grouped by sponsor + currency

Settlement is triggered via WP-cron (daily), WP-CLI, or the Finance admin UI.

---

## Adjustments

### Creating an adjustment

```php
use KH_SMMA\Reconciliation\PaidReconciliationAdjustmentService;

$adj_svc = new PaidReconciliationAdjustmentService( $wpdb, $audit_logger );

$row = $adj_svc->create_adjustment(
    reconciliation_id: 'rec_97a16b47b5602382',
    amount:            -5.00,   // negative = credit to sponsor
    currency:          'AUD',
    reason:            'LinkedIn rate change — agreed with sponsor 2026-03-01',
    adjusted_by:       get_current_user_id()
);
```

- `amount > 0` increases the settled amount (charge sponsor more).
- `amount < 0` reduces the settled amount (credit to sponsor).
- Rows are **immutable** once created.

### Reversing an adjustment

```php
$reversal = $adj_svc->create_reversal(
    adjustment_id: 'adj_abc0000000001',
    adjusted_by:   get_current_user_id()
);
```

- Creates an equal-and-opposite row (`amount = -original_amount`).
- Throws `RuntimeException` if the adjustment was already reversed.

### Listing adjustments for a reconciliation

```php
$adjustments = $adj_svc->get_adjustments( 'rec_97a16b47b5602382' );
```

### Settled amount formula

```
settled_amount = actual_spend + SUM(adjustments.amount for this rec_id)
```

Rounded to 4 decimal places. Used by SettlementWorker instead of raw `actual_spend`.

---

## Settlement batch

### Running manually (WP-CLI)

```bash
# Settle all pending reconciliations
wp kh-smma paid:settle

# Limit to a single sponsor
wp kh-smma paid:settle --sponsor_id=sp_456 --currency=AUD

# Date-bounded batch
wp kh-smma paid:settle --date_start=2026-03-01 --date_end=2026-03-31

# Custom batch size
wp kh-smma paid:settle --batch_size=200

# Convert totals to USD via FxService
wp kh-smma paid:settle --target_currency=USD
```

Output: JSON array of created settlement rows.

### Running via REST

Requires `kh_paid_finance` or `manage_options` capability.

```
POST /wp-json/kh-smma/v1/reconciliations/settle
Content-Type: application/json

{
  "sponsor_id":      "sp_456",
  "currency":        "AUD",
  "target_currency": "USD",
  "date_start":      "2026-03-01",
  "batch_size":      500
}
```

Returns the created settlement rows.

### Scheduled run

Settlement is registered on the `kh_smma_run_settlement` WP-cron event (daily).
To verify it is scheduled:

```bash
wp cron event list --fields=hook,next_run
```

To manually trigger:

```bash
wp cron event run kh_smma_run_settlement
```

### Algorithm

1. Query `wp_kh_paid_reconciliations WHERE status IN ('reconciled','discrepancy') AND settlement_id IS NULL`.
2. Group rows by `sponsor_id + currency`.
3. For each group: `total_settled = SUM(compute_settled_amount(rec_id, actual_spend))`.
4. Apply FX: `total_in_target = FxService::convert(total_settled, currency, target_currency)`.
5. INSERT into `wp_kh_paid_settlements`.
6. UPDATE each reconciliation: `status='settled'`, `settlement_id=<new id>`.
7. Fire `paid_settlement.complete` audit + `kh_paid_settlement_complete` action hook.

---

## FX rates

Static rates are configured in `config/paid_adapters.php`:

```php
'fx_rates' => [
    'AUD_USD' => 0.6453,
    'AUD_GBP' => 0.5142,
    'USD_AUD' => 1.5497,
    'USD_GBP' => 0.7967,
    'GBP_AUD' => 1.9447,
    'GBP_USD' => 1.2551,
],
```

Pairs not configured fall back to `1.0` (same-currency passthrough, sandbox-safe).
**No external API calls are made.**

To update rates for a new period:
1. Update `config/paid_adapters.php['fx_rates']`.
2. Re-run settlement for the affected period with the new rates.

---

## Permission model

| Capability       | Who has it              | What it unlocks                                 |
|------------------|-------------------------|-------------------------------------------------|
| `manage_options` | Administrator           | All PAID finance actions (legacy fallback)      |
| `kh_paid_finance`| Finance team (assigned) | Finance page, adjustments, dispute, settlement  |

Assign `kh_paid_finance` to a user:

```php
$user = get_user_by( 'login', 'finance_user' );
$user->add_cap( 'kh_paid_finance' );
```

Or via `CapabilityManager::ensure_capabilities()` on plugin activation (administrators only by default).

All write actions audit `unauthorized_admin_access` on 403.

---

## Admin UI

KH Ad Manager → **Finance** submenu (`kh-paid-finance`).

- Status filter includes: `reconciled`, `discrepancy`, `partial`, `error`, `settled`, `disputed`.
- Columns: Date, Reconciliation ID, Adapter, Status, Actual Spend, **Settled Amount**, Discrepancy %, Sponsor, **Settlement ID**, **Actions**.
- Row actions (available on `reconciled`/`discrepancy` rows):
  - **Adjust** — opens inline modal to enter amount, direction (debit/credit), and reason.
  - **Dispute** — marks the row as `disputed` via REST POST.
- **Export Ledger CSV** — includes `settled_amount` and `settlement_id` columns.
- **Run Settlement Batch** — triggers `POST /reconciliations/settle`.

---

## Ledger CSV export

```
POST /wp-admin/admin-post.php?action=kh_finance_export_csv
```

Columns: `created_at`, `reconciliation_id`, `manifest_id`, `adapter`, `status`,
`estimated_spend`, `actual_spend`, `settled_amount`, `discrepancy_percent`, `currency`,
`sponsor_id`, `campaign_id`, `partial_failure`, `settlement_id`.

Settlement ledger CSV (per settlement_id):
```
settlement_id, sponsor_id, currency, total_settled, fx_rate, settled_at,
reconciliation_ids, batch_size
```

Retrieve via `SettlementWorker::export_ledger_csv($settlement_id)` or:

```
GET /wp-json/kh-smma/v1/reconciliations/settlement/{settlement_id}
```

---

## DB tables

### wp_kh_paid_reconciliation_adjustments

Created by `PaidReconciliationAdjustmentService::install()`.

| Column             | Type               | Notes                                  |
|--------------------|--------------------|----------------------------------------|
| adjustment_id      | VARCHAR(32) PK     | `adj_` + SHA-256(rec_id\|amount\|ts)   |
| reconciliation_id  | VARCHAR(32)        | FK → wp_kh_paid_reconciliations        |
| amount             | DECIMAL(12,2)      | Signed; negative = credit              |
| currency           | CHAR(3)            | ISO 4217                               |
| reason             | TEXT               |                                        |
| adjusted_by        | BIGINT             | WP user ID                             |
| reversal_of        | VARCHAR(32) NULL   | NULL if original; adj_id if reversal   |
| created_at         | DATETIME           |                                        |

### wp_kh_paid_settlements

Created by `SettlementWorker::install()`.

| Column              | Type                | Notes                                      |
|---------------------|---------------------|--------------------------------------------|
| settlement_id       | VARCHAR(32) PK      | `sett_` + SHA-256(sponsor\|currency\|ts)   |
| sponsor_id          | VARCHAR(100)        |                                            |
| currency            | CHAR(3)             | Source currency of the batch               |
| total_settled       | DECIMAL(14,4)       | Sum of settled amounts, post-FX            |
| fx_rate             | DECIMAL(10,6)       | Rate applied (1.0 if same currency)        |
| settled_at          | DATETIME            |                                            |
| reconciliation_ids  | LONGTEXT            | JSON array of reconciliation_id strings    |
| batch_size          | INT UNSIGNED        |                                            |
| notes               | TEXT NULL           |                                            |

### wp_kh_paid_reconciliations additions

`settlement_id VARCHAR(32) NULL DEFAULT NULL` added via `dbDelta()` on install.

---

## Running tests

```bash
cd app/public/wp-content/plugins/kh-smma

# Adjustment service (8 tests)
vendor/bin/phpunit tests/Paid/ReconciliationAdjustmentServiceTest.php

# Settlement worker (7 tests)
vendor/bin/phpunit tests/Paid/SettlementWorkerTest.php

# Settlement integration (3 tests)
vendor/bin/phpunit tests/integration/ReconciliationSettlementIntegrationTest.php

# Full PAID suite (55 tests)
vendor/bin/phpunit tests/Paid/ tests/integration/ tests/PaidAdaptersTest.php tests/ManualExportAdapterTest.php
```

---

## Re-running settlement / rollback

**To re-run settlement for a period:**
1. Identify settlement_id(s) to reverse.
2. UPDATE reconciliations: `SET status='reconciled', settlement_id=NULL WHERE settlement_id='sett_...'`.
3. DELETE the settlement row from `wp_kh_paid_settlements`.
4. Re-run: `wp kh-smma paid:settle --date_start=... --date_end=...`.

**To roll back an adjustment:**
Use `create_reversal()` — never directly delete adjustment rows.

---

## Action hooks

### kh_paid_settlement_complete

Fires for each settlement batch created.

```php
add_action( 'kh_paid_settlement_complete', function ( array $settlement_row ) {
    // settlement_row: settlement_id, sponsor_id, currency, total_settled, fx_rate, ...
    my_finance_system_record_settlement( $settlement_row );
} );
```

### kh_paid_adjustment_created

Fires when a manual adjustment is created.

```php
add_action( 'kh_paid_adjustment_created', function ( array $adjustment_row ) {
    // adjustment_row: adjustment_id, reconciliation_id, amount, currency, reason, ...
} );
```
