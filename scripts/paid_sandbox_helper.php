<?php
/**
 * PAID Sandbox Developer Helper
 *
 * Runs a sandbox adapter (Google, LinkedIn, ManualExport) in dry_run or execute
 * mode and pretty-prints the normalised JSON output to stdout.
 *
 * Guards:
 *   - Must set PAID_DEV_HELPER=true env var (prevents accidental execution).
 *   - Only runs in non-production environments.
 *
 * Usage:
 *   PAID_DEV_HELPER=true php scripts/paid_sandbox_helper.php \
 *     --adapter=google --mode=dry_run
 *
 *   PAID_DEV_HELPER=true php scripts/paid_sandbox_helper.php \
 *     --adapter=linkedin --mode=execute \
 *     --manifest=tests/fixtures/golden/google_sandbox_dry_run_manifest.json
 *
 * Options:
 *   --adapter=google|linkedin|manual   Sandbox adapter to invoke (required)
 *   --mode=dry_run|execute             Method to call (required)
 *   --manifest=<path>                  Path to manifest JSON (optional; defaults to canonical golden fixture)
 *
 * Output: Normalised JSON with volatile fields (timestamp, delivered_at, acked_at) removed.
 */

// ── Guard: require PAID_DEV_HELPER=true ──────────────────────────────────────

if ( getenv( 'PAID_DEV_HELPER' ) !== 'true' ) {
    fwrite( STDERR, implode( PHP_EOL, [
        '',
        '  PAID Sandbox Helper requires an explicit opt-in.',
        '  Set PAID_DEV_HELPER=true before running:',
        '',
        '    PAID_DEV_HELPER=true php scripts/paid_sandbox_helper.php --adapter=google --mode=dry_run',
        '',
    ] ) . PHP_EOL );
    exit( 2 );
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────

$plugin_root = __DIR__ . '/../app/public/wp-content/plugins/kh-smma';

require_once $plugin_root . '/tests/TestHelpers.php';
require_once $plugin_root . '/src/Adapters/PaidAdapterContract.php';
require_once $plugin_root . '/src/Adapters/AdapterIdempotencyStore.php';
require_once $plugin_root . '/src/Helpers/DeterministicRng.php';
require_once $plugin_root . '/src/Adapters/GoogleSandboxAdapter.php';
require_once $plugin_root . '/src/Adapters/LinkedInSandboxAdapter.php';
require_once $plugin_root . '/src/Adapters/ManualExportAdapter.php';

if ( ! defined( 'KH_SMMA_PATH' ) ) {
    define( 'KH_SMMA_PATH', $plugin_root . '/' );
}
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( $plugin_root ) . '/' );
}

$GLOBALS['kh_test_options'] = [];
$GLOBALS['kh_test_filters'] = [];

// ── Parse arguments ───────────────────────────────────────────────────────────

$opts = getopt( '', [ 'adapter:', 'mode:', 'manifest:' ] );

$adapter_slug = $opts['adapter'] ?? '';
$mode         = $opts['mode'] ?? '';
$manifest_path = $opts['manifest'] ?? null;

$valid_adapters = [ 'google', 'linkedin', 'manual' ];
$valid_modes    = [ 'dry_run', 'execute' ];

if ( ! in_array( $adapter_slug, $valid_adapters, true ) ) {
    fwrite( STDERR, "ERROR: --adapter must be one of: " . implode( ', ', $valid_adapters ) . PHP_EOL );
    exit( 2 );
}

if ( ! in_array( $mode, $valid_modes, true ) ) {
    fwrite( STDERR, "ERROR: --mode must be one of: " . implode( ', ', $valid_modes ) . PHP_EOL );
    exit( 2 );
}

// ── Load manifest ─────────────────────────────────────────────────────────────

$default_fixtures = [
    'google'  => $plugin_root . '/tests/fixtures/golden/google_sandbox_dry_run_manifest.json',
    'linkedin'=> $plugin_root . '/tests/fixtures/golden/google_sandbox_dry_run_manifest.json',
    'manual'  => $plugin_root . '/tests/fixtures/golden/google_sandbox_dry_run_manifest.json',
];

$fixture_file = $manifest_path ?? $default_fixtures[ $adapter_slug ];

if ( ! file_exists( $fixture_file ) ) {
    fwrite( STDERR, "ERROR: Manifest file not found: {$fixture_file}" . PHP_EOL );
    exit( 2 );
}

$manifest = json_decode( (string) file_get_contents( $fixture_file ), true );

if ( ! is_array( $manifest ) || json_last_error() !== JSON_ERROR_NONE ) {
    fwrite( STDERR, "ERROR: Invalid JSON in manifest file: {$fixture_file}" . PHP_EOL );
    exit( 2 );
}

// ── Instantiate adapter ───────────────────────────────────────────────────────

$store = new \KH_SMMA\Adapters\AdapterIdempotencyStore();

$adapter = match ( $adapter_slug ) {
    'google'  => new \KH_SMMA\Adapters\GoogleSandboxAdapter( null, $store ),
    'linkedin'=> new \KH_SMMA\Adapters\LinkedInSandboxAdapter( null, $store ),
    'manual'  => new \KH_SMMA\Adapters\ManualExportAdapter( null, $store ),
};

// ── Run method ────────────────────────────────────────────────────────────────

$result = match ( $mode ) {
    'dry_run' => $adapter->dry_run( $manifest ),
    'execute' => $adapter->execute( $manifest ),
};

// ── Normalise volatile fields ─────────────────────────────────────────────────

$result = normalise_volatile( $result );

// ── Output ────────────────────────────────────────────────────────────────────

echo json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;

exit( 0 );

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Strip volatile fields so output can be compared against golden fixtures.
 * Mirrors the normalisation logic in paid_adapter_golden_check.php.
 *
 * @param array $data
 * @return array
 */
function normalise_volatile( array $data ): array {
    $volatile = [ 'timestamp', 'delivered_at', 'acked_at' ];

    foreach ( $volatile as $field ) {
        unset( $data[ $field ] );
    }

    if ( isset( $data['operations'] ) && is_array( $data['operations'] ) ) {
        foreach ( $data['operations'] as &$op ) {
            if ( is_array( $op ) ) {
                foreach ( $volatile as $field ) {
                    unset( $op[ $field ] );
                }
            }
        }
        unset( $op );
    }

    if ( isset( $data['operation_results'] ) && is_array( $data['operation_results'] ) ) {
        foreach ( $data['operation_results'] as &$op ) {
            if ( is_array( $op ) ) {
                foreach ( $volatile as $field ) {
                    unset( $op[ $field ] );
                }
            }
        }
        unset( $op );
    }

    return $data;
}
