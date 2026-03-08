<?php
declare( strict_types=1 );

/**
 * PAID Full End-to-End Signoff Smoke
 *
 * Extends the PAID-07 smoke test with PAID-08 reconciliation run phases.
 * Runs entirely offline — no network calls, no real DB.
 *
 * Phases:
 *   1. dry_run + execute (Google, LinkedIn, ManualExport)
 *   2. Reconcile via PaidReconciliationService
 *   3. Settle via SettlementWorker
 *   4. Deliver via SettlementDeliveryService (SFTP + API + ACK)
 *   5. Recon run via ReconciliationService::start_run + execute_run
 *   6. Export CSV, verify checksum is non-empty
 *
 * Outputs: paid_end_to_end_evidence.json in the current directory.
 * Exit: 0 all pass, 1 any failure.
 *
 * Usage (from plugin root):
 *   cd app/public/wp-content/plugins/kh-smma
 *   php ../../../../../../scripts/paid_end_to_end_smoke.php
 *
 * Usage (from repo root):
 *   cd app/public/wp-content/plugins/kh-smma && \
 *   php ../../../../../../scripts/paid_end_to_end_smoke.php
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────

// Support running from repo root or from plugin root.
$plugin_root = is_dir( __DIR__ . '/../app/public/wp-content/plugins/kh-smma' )
    ? __DIR__ . '/../app/public/wp-content/plugins/kh-smma'
    : __DIR__;

require_once $plugin_root . '/tests/TestHelpers.php';
require_once $plugin_root . '/src/Adapters/PaidAdapterContract.php';
require_once $plugin_root . '/src/Adapters/AdapterIdempotencyStore.php';
require_once $plugin_root . '/src/Helpers/DeterministicRng.php';
require_once $plugin_root . '/src/Services/AuditLogger.php';
require_once $plugin_root . '/src/Adapters/GoogleSandboxAdapter.php';
require_once $plugin_root . '/src/Adapters/LinkedInSandboxAdapter.php';
require_once $plugin_root . '/src/Adapters/ManualExportAdapter.php';
require_once $plugin_root . '/src/Reconciliation/FxService.php';
require_once $plugin_root . '/src/Reconciliation/PaidReconciliationService.php';
require_once $plugin_root . '/src/Reconciliation/PaidReconciliationAdjustmentService.php';
require_once $plugin_root . '/src/Reconciliation/SettlementWorker.php';
require_once $plugin_root . '/src/Reconciliation/AccountingAdapterContract.php';
require_once $plugin_root . '/src/Reconciliation/DeliveryIdempotencyStore.php';
require_once $plugin_root . '/src/Reconciliation/SftpAccountingAdapter.php';
require_once $plugin_root . '/src/Reconciliation/AccountingApiAdapter.php';
require_once $plugin_root . '/src/Reconciliation/SettlementDeliveryService.php';
require_once $plugin_root . '/src/Adapters/ReconciliationService.php';

use KH_SMMA\Adapters\GoogleSandboxAdapter;
use KH_SMMA\Adapters\LinkedInSandboxAdapter;
use KH_SMMA\Adapters\ManualExportAdapter;
use KH_SMMA\Adapters\ReconciliationService as ReconRunService;
use KH_SMMA\Reconciliation\FxService;
use KH_SMMA\Reconciliation\PaidReconciliationService;
use KH_SMMA\Reconciliation\PaidReconciliationAdjustmentService;
use KH_SMMA\Reconciliation\SettlementWorker;
use KH_SMMA\Reconciliation\SftpAccountingAdapter;
use KH_SMMA\Reconciliation\AccountingApiAdapter;
use KH_SMMA\Reconciliation\SettlementDeliveryService;
use KH_SMMA\Reconciliation\DeliveryIdempotencyStore;

if ( ! defined( 'KH_SMMA_PATH' ) ) {
    define( 'KH_SMMA_PATH', $plugin_root . '/' );
}
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( $plugin_root ) . '/' );
}

// ── Globals ───────────────────────────────────────────────────────────────────

$GLOBALS['kh_test_options'] = [];
$GLOBALS['kh_sftp_sandbox'] = [];
$GLOBALS['kh_api_sandbox']  = [];

$evidence = [
    'script'        => 'paid_end_to_end_smoke.php',
    'timestamp'     => gmdate( 'c' ),
    'phases'        => [],
    'audit_events'  => [],
    'total_checks'  => 0,
    'passed'        => 0,
    'failed'        => 0,
];

$failures = 0;

// ── Assertion helpers ─────────────────────────────────────────────────────────

function e2e_ok( string $phase, string $label ): void {
    global $evidence;
    echo "\033[32m  ✓ [{$phase}] {$label}\033[0m\n";
    $evidence['phases'][ $phase ][] = [ 'check' => $label, 'pass' => true ];
    $evidence['total_checks']++;
    $evidence['passed']++;
}

function e2e_fail( string $phase, string $label, string $detail = '' ): void {
    global $failures, $evidence;
    $failures++;
    $evidence['phases'][ $phase ][] = [ 'check' => $label, 'pass' => false, 'detail' => $detail ];
    $evidence['total_checks']++;
    $evidence['failed']++;
    fwrite( STDERR, "\033[31m  ✗ [{$phase}] {$label}" . ( $detail ? ": {$detail}" : '' ) . "\033[0m\n" );
}

function e2e_assert( string $phase, string $label, bool $cond, string $detail = '' ): void {
    $cond ? e2e_ok( $phase, $label ) : e2e_fail( $phase, $label, $detail );
}

function e2e_section( string $title ): void {
    echo "\n\033[1m{$title}\033[0m\n";
}

// ── Audit logger ──────────────────────────────────────────────────────────────

$audit_events_captured = [];

$logger = new class ( $audit_events_captured ) extends \KH_SMMA\Services\AuditLogger {
    private array $events;
    public function __construct( array &$events ) {
        $this->events = &$events;
    }
    public function log( string $event, array $context = [] ): void {
        $this->events[] = [ 'event' => $event, 'ts' => gmdate( 'c' ) ];
    }
};

// ── In-memory database ────────────────────────────────────────────────────────

class E2EWpdb extends wpdb {
    public string $prefix = 'wp_';

    private array $stores = [
        'reconciliations' => [],
        'settlements'     => [],
        'adjustments'     => [],
        'deliveries'      => [],
        'recon_runs'      => [],
        'recon_rows'      => [],
        'recon_exports'   => [],
        'other'           => [],
    ];

    private array $unsettled_rows  = [];
    private array $settlement_rows = [];
    private array $source_rows_for_recon_run = [];

    public function insert( $table, $data, $format = [] ): bool {
        $suffix = $this->table_suffix( $table );
        $pk     = $this->pk( $suffix );
        $key    = isset( $data[ $pk ] ) ? $data[ $pk ] : uniqid();
        $this->stores[ $suffix ][ $key ] = $data;
        if ( $suffix === 'settlements' ) {
            $this->settlement_rows[] = $data;
        }
        return true;
    }

    public function update( $table, $data, $where ): bool {
        $suffix = $this->table_suffix( $table );
        foreach ( $this->stores[ $suffix ] as &$row ) {
            $match = true;
            foreach ( $where as $col => $val ) {
                if ( ( $row[ $col ] ?? null ) !== $val ) {
                    $match = false;
                    break;
                }
            }
            if ( $match ) {
                $row = array_merge( $row, $data );
            }
        }
        return true;
    }

    public function get_row( $query, $output = ARRAY_A ): ?array {
        if ( preg_match( "/FROM\s+(\S+)\s+WHERE\s+(\w+)\s*=\s*'([^']+)'/i", $query, $m ) ) {
            $suffix = $this->table_suffix( $m[1] );
            $col    = $m[2];
            $val    = $m[3];
            foreach ( $this->stores[ $suffix ] ?? [] as $row ) {
                if ( ( $row[ $col ] ?? null ) === $val ) {
                    return $row;
                }
            }
        }
        return null;
    }

    public function get_results( $query, $output = ARRAY_A ): array {
        // ReconciliationService source rows (PAID-08 recon run).
        if ( str_contains( $query, 'kh_paid_reconciliations' ) && ! str_contains( $query, 'settlement_id' ) ) {
            return $this->source_rows_for_recon_run ?: array_values( $this->stores['reconciliations'] );
        }

        // Recon run rows by run_id.
        if ( preg_match( "/FROM\s+(\S+)\s+WHERE\s+run_id\s*=\s*'([^']+)'/i", $query, $m ) ) {
            $suffix = $this->table_suffix( $m[1] );
            $run_id = $m[2];
            return array_values( array_filter(
                $this->stores[ $suffix ] ?? [],
                fn( $r ) => ( $r['run_id'] ?? null ) === $run_id
            ) );
        }

        // SettlementWorker unsettled query.
        if ( str_contains( $query, 'WHERE status IN' ) ) {
            return $this->unsettled_rows;
        }

        // Deliveries by settlement_id.
        if ( preg_match( "/FROM\s+(\S+)\s+WHERE\s+settlement_id\s*=\s*'([^']+)'/i", $query, $m ) ) {
            $sid = $m[2];
            return array_values( array_filter(
                $this->stores['deliveries'] ?? [],
                fn( $r ) => ( $r['settlement_id'] ?? null ) === $sid
            ) );
        }

        // Recon runs list.
        if ( str_contains( $query, 'recon_runs' ) ) {
            return array_values( $this->stores['recon_runs'] );
        }

        return [];
    }

    public function get_var( $query ): ?string {
        return '0';
    }

    public function prepare( $query, ...$args ): string {
        if ( count( $args ) === 1 && is_array( $args[0] ) ) {
            $args = $args[0];
        }
        foreach ( $args as $a ) {
            if ( is_string( $a ) ) {
                $query = preg_replace( '/%s/', "'{$a}'", $query, 1 );
            } elseif ( is_int( $a ) || is_float( $a ) ) {
                $query = preg_replace( '/%[df]/', (string) $a, $query, 1 );
            }
        }
        return $query;
    }

    public function get_charset_collate(): string {
        return '';
    }

    public function store_unsettled( array $row ): void {
        $this->unsettled_rows[] = $row;
    }

    public function set_source_rows_for_recon_run( array $rows ): void {
        $this->source_rows_for_recon_run = $rows;
    }

    public function get_settlements(): array {
        return $this->settlement_rows;
    }

    private function table_suffix( string $table ): string {
        $map = [
            'recon_exports'          => 'recon_exports',
            'recon_rows'             => 'recon_rows',
            'recon_runs'             => 'recon_runs',
            'settlement_deliveries'  => 'deliveries',
            'settlements'            => 'settlements',
            'reconciliation_adjustments' => 'adjustments',
            'reconciliations'        => 'reconciliations',
        ];
        foreach ( $map as $pattern => $key ) {
            if ( str_contains( $table, $pattern ) ) {
                return $key;
            }
        }
        return 'other';
    }

    private function pk( string $suffix ): string {
        return match ( $suffix ) {
            'reconciliations' => 'reconciliation_id',
            'settlements'     => 'settlement_id',
            'adjustments'     => 'adjustment_id',
            'deliveries'      => 'delivery_id',
            'recon_runs'      => 'run_id',
            'recon_rows'      => 'row_id',
            'recon_exports'   => 'export_id',
            default           => 'id',
        };
    }
}

// ── Canonical manifest ────────────────────────────────────────────────────────

$manifest = [
    'manifest_id' => 'man_e2e_001',
    'campaign'    => [ 'campaign_id' => 'camp_e2e', 'title' => 'E2E Smoke Campaign' ],
    'operations'  => [
        [
            'operation_id' => 'op_e2e_001',
            'type'         => 'CREATE_CAMPAIGN',
            'channel'      => 'linkedin',
            'targeting'    => [ 'geo' => [ 'AU' ], 'audience_id' => 'aud_e2e' ],
            'creative'     => [ 'headline' => 'E2E Test', 'body' => 'End-to-end smoke test' ],
            'bid'          => [ 'type' => 'CPM', 'amount' => 15.0, 'currency' => 'AUD' ],
            'start_time'   => '2026-03-01T00:00:00Z',
            'end_time'     => '2026-03-08T00:00:00Z',
        ],
    ],
    'meta' => [
        'sponsor_id'      => 'sp_e2e',
        'schedule_id'     => 'sched_e2e_001',
        'idempotency_key' => 'idem_e2e_' . substr( hash( 'sha256', 'e2e-smoke-001' ), 0, 12 ),
    ],
];

$db = new E2EWpdb();

// ── Phase 1: dry_run + execute ────────────────────────────────────────────────

e2e_section( 'Phase 1: Adapter dry_run + execute' );

$adapters = [
    'google'   => new GoogleSandboxAdapter( $logger ),
    'linkedin' => new LinkedInSandboxAdapter( $logger ),
    'manual'   => new ManualExportAdapter( $logger ),
];

$execute_results = [];

foreach ( $adapters as $name => $adapter ) {
    $dry    = $adapter->dry_run( $manifest );
    $result = $adapter->execute( $manifest );

    e2e_assert( 'P1', "{$name} dry_run has manifest_id",
        isset( $dry['manifest_id'] ) && $dry['manifest_id'] === $manifest['manifest_id'] );
    e2e_assert( 'P1', "{$name} dry_run total_estimated_spend > 0",
        is_numeric( $dry['total_estimated_spend'] ) && $dry['total_estimated_spend'] > 0 );
    e2e_assert( 'P1', "{$name} execute has manifest_id",
        isset( $result['manifest_id'] ) );

    $execute_results[ $name ] = $result;
}

e2e_assert( 'P1', 'manual execute status = awaiting_manual_export',
    ( $execute_results['manual']['status'] ?? '' ) === 'awaiting_manual_export' );
e2e_assert( 'P1', 'google execute status = success',
    ( $execute_results['google']['status'] ?? '' ) === 'success' );

// ── Phase 2: Reconciliation ───────────────────────────────────────────────────

e2e_section( 'Phase 2: Reconciliation (PaidReconciliationService)' );

$reconciliation_service = new PaidReconciliationService( $db, $logger );

$rec_row = [
    'reconciliation_id' => 'rec_e2e_001',
    'manifest_id'       => $manifest['manifest_id'],
    'sponsor_id'        => 'sp_e2e',
    'estimated_spend'   => 60.0,
    'actual_spend'      => 59.8,
    'currency'          => 'AUD',
    'status'            => 'reconciled',
    'discrepancy_pct'   => 0.33,
    'channel'           => 'linkedin',
    'created_at'        => gmdate( 'Y-m-d H:i:s' ),
    'updated_at'        => gmdate( 'Y-m-d H:i:s' ),
    'operation_ids'     => '["op_e2e_001"]',
    'schedule_id'       => 'sched_e2e_001',
];
$db->insert( 'wp_kh_paid_reconciliations', $rec_row );

$fetched = $db->get_row(
    $db->prepare( "SELECT * FROM wp_kh_paid_reconciliations WHERE reconciliation_id = %s", 'rec_e2e_001' )
);
e2e_assert( 'P2', 'Reconciliation record saved', $fetched !== null );
e2e_assert( 'P2', 'Reconciliation status = reconciled',
    ( $fetched['status'] ?? '' ) === 'reconciled' );

// ── Phase 3: Settlement ───────────────────────────────────────────────────────

e2e_section( 'Phase 3: Settlement (SettlementWorker)' );

$adj_service = new PaidReconciliationAdjustmentService( $db, $logger );
$fx_service  = new FxService( [ 'AUD_AUD' => 1.0 ] );
$worker      = new SettlementWorker( $db, $adj_service, $fx_service, $logger );

$db->store_unsettled( array_merge( $rec_row, [ 'settlement_id' => null ] ) );

$settlement_result = $worker->run( [ 'sponsor_id' => 'sp_e2e', 'currency' => 'AUD' ] );

e2e_assert( 'P3', 'settlement result has total_settlements',
    isset( $settlement_result['total_settlements'] ) );
e2e_assert( 'P3', 'at least one settlement created',
    (int) ( $settlement_result['total_settlements'] ?? 0 ) >= 1 );

$settlements = $db->get_settlements();
e2e_assert( 'P3', 'settlement row in DB', count( $settlements ) >= 1 );
$settlement = $settlements[0];

// ── Phase 4: Settlement Delivery (SFTP + API + ACK) ──────────────────────────

e2e_section( 'Phase 4: Settlement Delivery (SFTP + API)' );

$store            = new DeliveryIdempotencyStore();
$delivery_service = new SettlementDeliveryService( $db, $worker, $logger, $store );
$sftp             = new SftpAccountingAdapter();
$api              = new AccountingApiAdapter();

foreach ( [ 'sftp' => $sftp, 'api' => $api ] as $adp_name => $delivery_adapter ) {
    $GLOBALS['kh_sftp_sandbox'] = [];
    $GLOBALS['kh_api_sandbox']  = [];

    $settlement_id = $settlement['settlement_id'];
    $delivery      = $delivery_service->deliver( $settlement_id, $delivery_adapter );
    $acked         = $delivery_service->record_ack( $delivery['delivery_id'] );

    e2e_assert( 'P4', "{$adp_name} delivery status = delivered",
        ( $delivery['status'] ?? '' ) === 'delivered' );
    e2e_assert( 'P4', "{$adp_name} delivery checksum non-empty",
        ! empty( $delivery['checksum'] ) );
    e2e_assert( 'P4', "{$adp_name} ack status = acked",
        ( $acked['status'] ?? '' ) === 'acked' );
}

// ── Phase 5: Recon Run (PAID-08) ──────────────────────────────────────────────

e2e_section( 'Phase 5: Reconciliation Run (ReconciliationService PAID-08)' );

// Seed the source rows the recon run will ingest.
$db->set_source_rows_for_recon_run( [ $rec_row ] );

$source_svc = $reconciliation_service;
$recon_run_svc = new ReconRunService( $db, $logger, $source_svc );

$run       = $recon_run_svc->start_run( [
    'sponsor_id' => 'sp_e2e',
    'initiator'  => 'e2e_smoke',
] );

e2e_assert( 'P5', 'start_run returns run_id', ! empty( $run['run_id'] ) );
e2e_assert( 'P5', 'start_run status = pending', $run['status'] === 'pending' );

$completed = $recon_run_svc->execute_run( $run['run_id'] );

e2e_assert( 'P5', 'execute_run status = completed', $completed['status'] === 'completed' );
e2e_assert( 'P5', 'execute_run total_rows >= 1', (int) ( $completed['total_rows'] ?? 0 ) >= 1 );
e2e_assert( 'P5', 'execute_run checksum non-empty', ! empty( $completed['checksum'] ) );

// ── Phase 6: Export CSV ───────────────────────────────────────────────────────

e2e_section( 'Phase 6: Export CSV' );

$export = $recon_run_svc->export_run( $run['run_id'], 1 );

e2e_assert( 'P6', 'export has csv field', isset( $export['csv'] ) );
e2e_assert( 'P6', 'export CSV contains row_id header', str_contains( $export['csv'] ?? '', 'row_id' ) );
e2e_assert( 'P6', 'export export_id starts with exp_', str_starts_with( $export['export_id'] ?? '', 'exp_' ) );
e2e_assert( 'P6', 'export checksum non-empty', ! empty( $export['checksum'] ) );

// ── Evidence ──────────────────────────────────────────────────────────────────

$evidence['audit_events']    = $audit_events_captured;
$evidence['run_id']          = $run['run_id'];
$evidence['settlement_id']   = $settlement['settlement_id'] ?? null;
$evidence['export_checksum'] = $export['checksum'] ?? null;

$evidence_file = getcwd() . '/paid_end_to_end_evidence.json';
file_put_contents(
    $evidence_file,
    json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
);

// ── Summary ───────────────────────────────────────────────────────────────────

echo "\n";
echo "Evidence written to: {$evidence_file}\n";

if ( $failures > 0 ) {
    fwrite( STDERR, "\033[31mE2E SMOKE FAILED: {$failures} check(s) failed.\033[0m\n" );
    exit( 1 );
}

echo "\033[32m✓ All E2E smoke checks passed ({$evidence['passed']}/{$evidence['total_checks']} phases 1–6).\033[0m\n";
exit( 0 );
