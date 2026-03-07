<?php
/**
 * PAID-04 Discrepancy Alert Signoff Smoke
 *
 * Standalone script (no WP bootstrap required). Bootstraps PaidReconciliationService
 * with in-memory stubs and verifies the discrepancy alert flow:
 *
 *   1. Calls reconcile() with estimated=60.0, actual=75.0 (25% > 10% threshold)
 *   2. Asserts result status = 'discrepancy'
 *   3. Asserts hook kh_paid_reconciliation_discrepancy was fired with the row
 *   4. Asserts audit event paid_reconciliation.discrepancy_alert was logged
 *   5. Writes evidence JSON to stdout
 *
 * Exit: 0 on success, 1 on failure.
 *
 * Usage: php scripts/paid04_signoff_smoke.php
 */

$plugin_root = __DIR__ . '/../app/public/wp-content/plugins/kh-smma';

require_once $plugin_root . '/tests/TestHelpers.php';
require_once $plugin_root . '/src/Services/AuditLogger.php';
require_once $plugin_root . '/src/Reconciliation/PaidReconciliationService.php';

if ( ! defined( 'KH_SMMA_PATH' ) ) {
    define( 'KH_SMMA_PATH', $plugin_root . '/' );
}
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( $plugin_root ) . '/' );
}

$GLOBALS['kh_test_options'] = [];
$GLOBALS['kh_test_filters'] = [];

// ── In-memory audit logger ────────────────────────────────────────────────────

$audit_events = [];

class SmokeAuditLogger extends \KH_SMMA\Services\AuditLogger {
    public array $events = [];

    public function __construct() {
        // No wpdb needed — override log() directly.
    }

    public function log( string $event, array $context = [] ): void {
        $this->events[] = [ 'event' => $event, 'context' => $context ];
    }
}

// ── In-memory wpdb ────────────────────────────────────────────────────────────

class SmokeWpdb extends wpdb {
    public string $prefix  = 'wp_';
    public array  $inserts = [];

    public function insert( $table, $data, $format = [] ): bool {
        $this->inserts[] = [ 'table' => $table, 'data' => $data ];
        return true;
    }

    public function get_row( $query, $output = ARRAY_A ): ?array {
        return null; // No existing row — force new reconciliation.
    }

    public function prepare( $query, ...$args ): string {
        return $query;
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

$manifest_id   = 'man_smoke_disc_001';
$estimated     = 60.0;
$actual        = 75.0; // 25% discrepancy > 10% threshold

$execute_response = [
    'manifest_id'        => $manifest_id,
    'status'             => 'success',
    'operation_results'  => [
        [
            'operation_id'            => 'op_smoke_disc_001',
            'operation_id_on_channel' => 'g_op_smoke01',
            'result'                  => 'created',
            'actual_spend'            => $actual,
            'currency'                => 'AUD',
            'error'                   => null,
        ],
    ],
    'total_actual_spend' => $actual,
    'currency'           => 'AUD',
    'errors'             => null,
    'timestamp'          => date( 'Y-m-d\TH:i:s\Z' ),
    'adapter_meta'       => [
        'adapter'         => 'google_sandbox',
        'version'         => '1.0.0',
        'idempotency_key' => 'idem_smoke_disc_001',
    ],
];

$dry_run_response = [
    'total_estimated_spend' => $estimated,
    'currency'              => 'AUD',
];

// ── Run smoke ─────────────────────────────────────────────────────────────────

$evidence = [
    'script'      => 'paid04_signoff_smoke.php',
    'timestamp'   => date( 'c' ),
    'checks'      => [],
    'passed'      => 0,
    'failed'      => 0,
];

function smoke_check( string $label, bool $pass, array &$evidence ): void {
    $evidence['checks'][] = [ 'label' => $label, 'pass' => $pass ];
    if ( $pass ) {
        $evidence['passed']++;
    } else {
        $evidence['failed']++;
        fwrite( STDERR, "FAIL: {$label}\n" );
    }
}

$db     = new SmokeWpdb();
$logger = new SmokeAuditLogger();
$svc    = new \KH_SMMA\Reconciliation\PaidReconciliationService( $db, $logger, 10.0 );

// Track hook firing.
$hook_fired = false;
$hook_row   = null;
add_action( 'kh_paid_reconciliation_discrepancy', function ( $row ) use ( &$hook_fired, &$hook_row ) {
    $hook_fired = true;
    $hook_row   = $row;
} );

$result = $svc->reconcile( $manifest_id, $execute_response, $dry_run_response, [
    'sponsor_id' => 'sp_smoke_001',
] );

// Check 1: status = discrepancy.
smoke_check( 'reconcile() returns status=discrepancy', $result['status'] === 'discrepancy', $evidence );

// Check 2: discrepancy_percent > 10.
smoke_check( 'discrepancy_percent exceeds threshold', (float) $result['discrepancy_percent'] > 10.0, $evidence );

// Check 3: hook fired.
smoke_check( 'kh_paid_reconciliation_discrepancy hook fired', $hook_fired === true, $evidence );

// Check 4: hook row matches result.
smoke_check(
    'hook row has correct reconciliation_id',
    $hook_row !== null && $hook_row['reconciliation_id'] === $result['reconciliation_id'],
    $evidence
);

// Check 5: audit event paid_adapter.reconciled was logged.
$reconciled_logged = false;
foreach ( $logger->events as $e ) {
    if ( $e['event'] === 'paid_adapter.reconciled' ) {
        $reconciled_logged = true;
        break;
    }
}
smoke_check( 'paid_adapter.reconciled audit event logged', $reconciled_logged, $evidence );

// Check 6: audit event paid_reconciliation.discrepancy_alert was logged with manifest_id and discrepancy_percent.
$alert_logged = false;
foreach ( $logger->events as $e ) {
    if ( $e['event'] === 'paid_reconciliation.discrepancy_alert' ) {
        $d = $e['context']['details'] ?? [];
        if ( isset( $d['manifest_id'] ) && isset( $d['discrepancy_percent'] ) ) {
            $alert_logged = true;
            break;
        }
    }
}
smoke_check( 'paid_reconciliation.discrepancy_alert logged with required fields', $alert_logged, $evidence );

// Check 7: DB insert occurred.
smoke_check( 'reconciliation row inserted into DB', count( $db->inserts ) >= 1, $evidence );

$evidence['audit_events'] = array_column( $logger->events, 'event' );
$evidence['result_status'] = $result['status'];
$evidence['discrepancy_percent'] = (float) $result['discrepancy_percent'];

// ── Output ────────────────────────────────────────────────────────────────────

echo json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;

if ( $evidence['failed'] > 0 ) {
    fwrite( STDERR, "\npaid04_signoff_smoke: FAILED ({$evidence['failed']} checks failed)\n" );
    exit( 1 );
}

echo "paid04_signoff_smoke: PASSED ({$evidence['passed']} checks)\n";
exit( 0 );
