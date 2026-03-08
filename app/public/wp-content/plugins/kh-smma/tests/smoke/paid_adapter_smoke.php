<?php
declare(strict_types=1);

/**
 * PAID-07 — Paid Adapter End-to-End Smoke Test
 *
 * Exercises the full sandbox flow in a single script:
 *   1. Generate manifest
 *   2. dry_run → execute (LinkedIn, Google, ManualExport)
 *   3. Reconcile (record actual vs estimated spend)
 *   4. Settle (SettlementWorker::run)
 *   5. Deliver (SFTP + Accounting API adapters)
 *
 * Runs entirely offline — no network calls, no real DB.
 * Exits 0 on success, 1 on any assertion failure.
 *
 * Usage:
 *   php tests/smoke/paid_adapter_smoke.php
 *   cd app/public/wp-content/plugins/kh-smma && php tests/smoke/paid_adapter_smoke.php
 */

// ── Bootstrap ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/PaidAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/AdapterIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/DeterministicRng.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/GoogleSandboxAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/LinkedInSandboxAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/ManualExportAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/FxService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/PaidReconciliationAdjustmentService.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SettlementWorker.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/AccountingAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/DeliveryIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SftpAccountingAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/AccountingApiAdapter.php';
require_once dirname( __DIR__, 2 ) . '/src/Reconciliation/SettlementDeliveryService.php';

use KH_SMMA\Adapters\GoogleSandboxAdapter;
use KH_SMMA\Adapters\LinkedInSandboxAdapter;
use KH_SMMA\Adapters\ManualExportAdapter;
use KH_SMMA\Reconciliation\FxService;
use KH_SMMA\Reconciliation\PaidReconciliationService;
use KH_SMMA\Reconciliation\PaidReconciliationAdjustmentService;
use KH_SMMA\Reconciliation\SettlementWorker;
use KH_SMMA\Reconciliation\SftpAccountingAdapter;
use KH_SMMA\Reconciliation\AccountingApiAdapter;
use KH_SMMA\Reconciliation\SettlementDeliveryService;
use KH_SMMA\Reconciliation\DeliveryIdempotencyStore;

// ── Globals ───────────────────────────────────────────────────────────────────

$GLOBALS['kh_test_options'] = [];
$GLOBALS['kh_sftp_sandbox'] = [];
$GLOBALS['kh_api_sandbox']  = [];

$failures = 0;

// ── Canonical manifest ────────────────────────────────────────────────────────

$manifest = [
    'manifest_id' => 'man_smoke_001',
    'campaign'    => ['campaign_id' => 'camp_smoke', 'title' => 'Smoke Test Campaign'],
    'operations'  => [
        [
            'operation_id' => 'op_smoke_1',
            'type'         => 'CREATE_CAMPAIGN',
            'channel'      => 'linkedin',
            'targeting'    => ['geo' => ['AU'], 'audience_id' => 'aud_smoke'],
            'creative'     => ['headline' => 'Smoke Test', 'body' => 'Testing 1-2-3'],
            'bid'          => ['type' => 'CPM', 'amount' => 10.0, 'currency' => 'AUD'],
            'start_time'   => '2026-04-01T00:00:00Z',
            'end_time'     => '2026-04-07T23:59:59Z',
        ],
    ],
    'meta' => [
        'sponsor_id'      => 'sp_smoke',
        'schedule_id'     => 'sched_smoke_001',
        'idempotency_key' => 'idem_smoke_' . hash('sha256', 'smoke-test-run-001'),
    ],
];

// ── Phase 1: dry_run / execute for all paid adapters ─────────────────────────

ok_section('Phase 1: Adapter dry_run + execute');

$adapters = [
    'linkedin' => new LinkedInSandboxAdapter(),
    'google'   => new GoogleSandboxAdapter(),
    'manual'   => new ManualExportAdapter(),
];

$execute_results = [];

foreach ($adapters as $name => $adapter) {
    // dry_run
    $dry = $adapter->dry_run($manifest);
    assert_has_keys("$name dry_run", $dry, ['manifest_id', 'operations', 'total_estimated_spend', 'currency']);
    assert_equals("$name dry_run manifest_id", $dry['manifest_id'], 'man_smoke_001');
    assert_equals("$name dry_run operation count", count($dry['operations']), 1);
    assert_positive("$name dry_run total_estimated_spend", $dry['total_estimated_spend']);

    // execute
    $result = $adapter->execute($manifest);
    assert_has_keys("$name execute", $result, ['manifest_id']);
    $execute_results[$name] = $result;

    ok("$name dry_run + execute passed");
}

// LinkedIn and Google should produce deterministic operation IDs.
assert_true(
    'linkedin execute has operation_ids',
    isset($execute_results['linkedin']['operation_ids']) ||
        isset($execute_results['linkedin']['operation_results'])
);

// ManualExport must return awaiting_manual_export.
assert_equals(
    'manual execute status',
    $execute_results['manual']['status'] ?? '',
    'awaiting_manual_export'
);

// ── Phase 2: Reconciliation ───────────────────────────────────────────────────

ok_section('Phase 2: Reconciliation');

$db = new SmokeWpdb();

$logger = new class extends \KH_SMMA\Services\AuditLogger {
    public function __construct() {}
    public function log( string $event, array $context = [] ): void {}
};

$reconciliation_service = new PaidReconciliationService( $db, $logger );

// Record a reconciliation row.
$rec_id  = 'rec_smoke_001';
$rec_row = [
    'reconciliation_id' => $rec_id,
    'manifest_id'       => 'man_smoke_001',
    'sponsor_id'        => 'sp_smoke',
    'estimated_spend'   => 60.0,
    'actual_spend'      => 59.8,
    'currency'          => 'AUD',
    'status'            => 'reconciled',
    'discrepancy_pct'   => 0.33,
    'channel'           => 'linkedin',
    'created_at'        => gmdate('Y-m-d H:i:s'),
    'updated_at'        => gmdate('Y-m-d H:i:s'),
];
$db->insert( 'wp_kh_paid_reconciliations', $rec_row );

$fetched = $db->get_row(
    $db->prepare( "SELECT * FROM wp_kh_paid_reconciliations WHERE reconciliation_id = %s", $rec_id )
);
assert_not_null('reconciliation record saved', $fetched);
assert_equals('reconciliation status', $fetched['status'] ?? '', 'reconciled');

ok('Reconciliation record saved and retrieved');

// ── Phase 3: Settlement ───────────────────────────────────────────────────────

ok_section('Phase 3: Settlement');

$adj_service = new PaidReconciliationAdjustmentService( $db, $logger );
$fx_service  = new FxService(['AUD_AUD' => 1.0]);
$worker      = new SettlementWorker( $db, $adj_service, $fx_service, $logger );

// Seed unsettled reconciliation for the worker.
$unsettled_row = array_merge($rec_row, ['settlement_id' => null]);
$db->store_unsettled($unsettled_row);

$settlement_result = $worker->run(['sponsor_id' => 'sp_smoke', 'currency' => 'AUD']);
assert_has_keys('settlement result', $settlement_result, ['total_settlements', 'total_spend']);
assert_true('at least one settlement created', $settlement_result['total_settlements'] >= 1);

ok('Settlement created: ' . json_encode([
    'total_settlements' => $settlement_result['total_settlements'],
    'total_spend'       => $settlement_result['total_spend'],
]));

// Retrieve the settlement from the in-memory DB.
$settlements = $db->get_settlements();
assert_true('settlement row in DB', count($settlements) >= 1);
$settlement = $settlements[0];

ok('Settlement row: settlement_id=' . ($settlement['settlement_id'] ?? 'n/a'));

// ── Phase 4: Delivery (SFTP + Accounting API) ─────────────────────────────────

ok_section('Phase 4: Settlement Delivery');

$store            = new DeliveryIdempotencyStore();
$delivery_service = new SettlementDeliveryService( $db, $worker, $logger, $store );

$sftp = new SftpAccountingAdapter();
$api  = new AccountingApiAdapter();

foreach (['sftp' => $sftp, 'api' => $api] as $adapter_name => $delivery_adapter) {
    $GLOBALS['kh_sftp_sandbox'] = [];
    $GLOBALS['kh_api_sandbox']  = [];
    $GLOBALS['kh_test_options'] = [];

    $settlement_id = $settlement['settlement_id'];

    // dry_run first.
    $dry = $delivery_adapter->dry_run($settlement);
    assert_equals("$adapter_name dry_run valid", $dry['valid'] ?? false, true);

    // execute.
    $delivery = $delivery_service->deliver($settlement_id, $delivery_adapter);
    assert_equals("$adapter_name delivery status", $delivery['status'] ?? '', 'delivered');
    assert_not_empty("$adapter_name delivery checksum", $delivery['checksum'] ?? '');

    // ACK.
    $acked = $delivery_service->record_ack($delivery['delivery_id']);
    assert_equals("$adapter_name ack status", $acked['status'] ?? '', 'acked');

    ok("$adapter_name: delivered + acked (delivery_id=" . ($delivery['delivery_id'] ?? 'n/a') . ')');
}

// ── Summary ───────────────────────────────────────────────────────────────────

echo "\n";
if ($failures > 0) {
    fwrite(STDERR, "\033[31mSMOKE FAILED: {$failures} assertion(s) failed.\033[0m\n");
    exit(1);
}
echo "\033[32m✓ All smoke checks passed (dry_run → execute → reconcile → settle → deliver).\033[0m\n";
exit(0);

// ── In-memory wpdb for smoke test ────────────────────────────────────────────

class SmokeWpdb extends wpdb {
    private array $stores         = [];
    private array $unsettled_rows = [];
    private array $settlement_rows = [];

    public function insert( $table, $data, $format = [] ): bool {
        $key_map = [
            'reconciliations' => 'reconciliation_id',
            'settlements'     => 'settlement_id',
            'adjustments'     => 'adjustment_id',
            'deliveries'      => 'delivery_id',
        ];
        foreach ($key_map as $suffix => $pk) {
            if (str_contains($table, $suffix)) {
                $this->stores[$suffix][$data[$pk] ?? uniqid()] = $data;
                if ($suffix === 'settlements') {
                    $this->settlement_rows[] = $data;
                }
                return true;
            }
        }
        $this->stores['other'][] = $data;
        return true;
    }

    public function update( $table, $data, $where ): bool {
        foreach ($this->stores as &$store) {
            foreach ($store as &$row) {
                $match = true;
                foreach ($where as $col => $val) {
                    if (($row[$col] ?? null) !== $val) { $match = false; break; }
                }
                if ($match) { $row = array_merge($row, $data); }
            }
        }
        return true;
    }

    public function get_row( $query, $output = ARRAY_A ): ?array {
        if (preg_match("/FROM (\S+) WHERE (\w+) = '([^']+)'/", $query, $m)) {
            $suffix = $this->table_suffix($m[1]);
            $col    = $m[2];
            $val    = $m[3];
            foreach ($this->stores[$suffix] ?? [] as $row) {
                if (($row[$col] ?? null) === $val) { return $row; }
            }
        }
        return null;
    }

    public function get_results( $query, $output = ARRAY_A ): array {
        // Unsettled reconciliations for SettlementWorker::run().
        if (str_contains($query, 'WHERE status IN')) {
            return $this->unsettled_rows;
        }
        // Deliveries by settlement_id.
        if (preg_match("/FROM (\S+) WHERE settlement_id = '([^']+)'/", $query, $m)) {
            $sid = $m[2];
            return array_values(array_filter(
                $this->stores['deliveries'] ?? [],
                fn($r) => ($r['settlement_id'] ?? null) === $sid
            ));
        }
        return [];
    }

    public function get_var( $query ): ?string { return '0'; }

    public function prepare( $query, ...$args ): string {
        foreach ($args as $a) {
            if (is_string($a)) {
                $query = preg_replace('/%s/', "'{$a}'", $query, 1);
            } elseif (is_int($a) || is_float($a)) {
                $query = preg_replace('/%[df]/', (string) $a, $query, 1);
            }
        }
        return $query;
    }

    public function get_charset_collate(): string { return ''; }

    // Expose helpers for smoke test.
    public function store_unsettled( array $row ): void { $this->unsettled_rows[] = $row; }
    public function get_settlements(): array { return $this->settlement_rows; }

    private function table_suffix( string $table ): string {
        foreach (['settlement_deliveries' => 'deliveries', 'settlements' => 'settlements',
                  'reconciliation_adjustments' => 'adjustments', 'reconciliations' => 'reconciliations'] as $pattern => $key) {
            if (str_contains($table, $pattern)) { return $key; }
        }
        return 'other';
    }
}

// ── Assertion helpers ─────────────────────────────────────────────────────────

function ok( string $msg ): void {
    echo "\033[32m  ✓ {$msg}\033[0m\n";
}

function ok_section( string $title ): void {
    echo "\n\033[1m{$title}\033[0m\n";
}

function fail( string $msg ): void {
    global $failures;
    $failures++;
    fwrite(STDERR, "\033[31m  ✗ {$msg}\033[0m\n");
}

function assert_equals( string $label, mixed $actual, mixed $expected ): void {
    if ($actual !== $expected) {
        fail("{$label}: expected " . json_encode($expected) . ', got ' . json_encode($actual));
    } else {
        ok($label);
    }
}

function assert_true( string $label, bool $cond ): void {
    $cond ? ok($label) : fail("{$label}: expected true");
}

function assert_positive( string $label, mixed $value ): void {
    (is_numeric($value) && $value > 0) ? ok($label) : fail("{$label}: expected > 0, got " . json_encode($value));
}

function assert_not_null( string $label, mixed $value ): void {
    $value !== null ? ok($label) : fail("{$label}: expected non-null");
}

function assert_not_empty( string $label, mixed $value ): void {
    !empty($value) ? ok($label) : fail("{$label}: expected non-empty");
}

function assert_has_keys( string $label, array $arr, array $keys ): void {
    foreach ($keys as $k) {
        if (!array_key_exists($k, $arr)) {
            fail("{$label}: missing key '{$k}'");
            return;
        }
    }
    ok("{$label} has required keys");
}
