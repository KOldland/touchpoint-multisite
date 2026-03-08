<?php
declare(strict_types=1);

/**
 * PAID-07 — Paid Adapter Golden Check
 *
 * Loads golden fixture meta, runs adapter dry_run/execute in sandbox mode,
 * normalises volatile fields (timestamp, delivered_at, acked_at), diffs the
 * normalised output against the stored fixture, and writes a
 * golden-summary.json artefact.
 *
 * Usage:
 *   php scripts/paid_adapter_golden_check.php [--write] [--fixture=<name>]
 *
 * Options:
 *   --write            Overwrite fixtures that diverge (use with care; prefer
 *                      regenerate_paid_fixture.php for governed updates).
 *   --fixture=<name>   Run only the named fixture (without .json extension).
 *
 * Exit codes:
 *   0  All fixtures matched (or --write updated diverging ones).
 *   1  One or more fixtures diverged without --write.
 *   2  Usage / configuration error.
 */

const GOLDEN_DIR   = __DIR__ . '/../app/public/wp-content/plugins/kh-smma/tests/fixtures/golden';
const SUMMARY_FILE = __DIR__ . '/../golden-summary.json';

// ── Bootstrap ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../app/public/wp-content/plugins/kh-smma/tests/TestHelpers.php';
require_once __DIR__ . '/../app/public/wp-content/plugins/kh-smma/src/Adapters/PaidAdapterContract.php';
require_once __DIR__ . '/../app/public/wp-content/plugins/kh-smma/src/Adapters/AdapterIdempotencyStore.php';
require_once __DIR__ . '/../app/public/wp-content/plugins/kh-smma/src/Adapters/DeterministicRng.php';
require_once __DIR__ . '/../app/public/wp-content/plugins/kh-smma/src/Services/AuditLogger.php';
require_once __DIR__ . '/../app/public/wp-content/plugins/kh-smma/src/Adapters/GoogleSandboxAdapter.php';
require_once __DIR__ . '/../app/public/wp-content/plugins/kh-smma/src/Adapters/LinkedInSandboxAdapter.php';
require_once __DIR__ . '/../app/public/wp-content/plugins/kh-smma/src/Adapters/ManualExportAdapter.php';
require_once __DIR__ . '/../app/public/wp-content/plugins/kh-smma/src/Reconciliation/AccountingAdapterContract.php';
require_once __DIR__ . '/../app/public/wp-content/plugins/kh-smma/src/Reconciliation/DeliveryIdempotencyStore.php';
require_once __DIR__ . '/../app/public/wp-content/plugins/kh-smma/src/Reconciliation/SftpAccountingAdapter.php';
require_once __DIR__ . '/../app/public/wp-content/plugins/kh-smma/src/Reconciliation/AccountingApiAdapter.php';

use KH_SMMA\Adapters\GoogleSandboxAdapter;
use KH_SMMA\Adapters\LinkedInSandboxAdapter;
use KH_SMMA\Adapters\ManualExportAdapter;
use KH_SMMA\Reconciliation\SftpAccountingAdapter;
use KH_SMMA\Reconciliation\AccountingApiAdapter;
use KH_SMMA\Reconciliation\DeliveryIdempotencyStore;

// ── CLI argument parsing ──────────────────────────────────────────────────────

$write_mode     = in_array('--write', $argv, true);
$fixture_filter = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--fixture=')) {
        $fixture_filter = trim(substr($arg, strlen('--fixture=')));
    }
}

// ── Fixtures map: fixture_name => [adapter_factory, method, input_fixture] ───

$GLOBALS['kh_test_options'] = [];
$GLOBALS['kh_sftp_sandbox'] = [];
$GLOBALS['kh_api_sandbox']  = [];

$canonical_manifest = load_json(GOLDEN_DIR . '/google_sandbox_dry_run_manifest.json');
$canonical_settlement = [
    'settlement_id'      => 'sett_xyz789abc012',
    'sponsor_id'         => 'sp_456',
    'currency'           => 'AUD',
    'total_settled'      => '118.4000',
    'fx_rate'            => '1.000000',
    'settled_at'         => '2026-03-04 00:00:00',
    'reconciliation_ids' => '["rec_001"]',
    'batch_size'         => '1',
];

/** @var array<string, callable> */
$checks = [

    // ── Sandbox paid adapters ──────────────────────────────────────────────

    'google_sandbox_dry_run_response' => static function () use ( $canonical_manifest ): array {
        $adapter = new GoogleSandboxAdapter();
        $result  = $adapter->dry_run( $canonical_manifest );
        return normalise_volatile( $result );
    },

    'google_sandbox_execute_response' => static function () use ( $canonical_manifest ): array {
        $GLOBALS['kh_test_options'] = []; // reset idempotency
        $adapter = new GoogleSandboxAdapter();
        $result  = $adapter->execute( $canonical_manifest );
        return normalise_volatile( $result );
    },

    'linkedin_sandbox_dry_run_response' => static function () use ( $canonical_manifest ): array {
        $adapter = new LinkedInSandboxAdapter();
        $result  = $adapter->dry_run( $canonical_manifest );
        return normalise_volatile( $result );
    },

    'linkedin_sandbox_execute_response' => static function () use ( $canonical_manifest ): array {
        $GLOBALS['kh_test_options'] = [];
        $adapter = new LinkedInSandboxAdapter();
        $result  = $adapter->execute( $canonical_manifest );
        return normalise_volatile( $result );
    },

    'manual_export_dry_run_response' => static function () use ( $canonical_manifest ): array {
        $adapter = new ManualExportAdapter();
        $result  = $adapter->dry_run( $canonical_manifest );
        return normalise_volatile( $result );
    },

    'manual_export_execute_response' => static function () use ( $canonical_manifest ): array {
        $GLOBALS['kh_test_options'] = [];
        $adapter = new ManualExportAdapter();
        $result  = $adapter->execute( $canonical_manifest );
        return normalise_volatile( $result );
    },

    // ── Accounting / delivery adapters ─────────────────────────────────────

    'sftp_delivery_dry_run_response' => static function () use ( $canonical_settlement ): array {
        $adapter = new SftpAccountingAdapter();
        $result  = $adapter->dry_run( $canonical_settlement );
        return normalise_volatile( $result );
    },

    'sftp_delivery_execute_response' => static function () use ( $canonical_settlement ): array {
        $GLOBALS['kh_sftp_sandbox'] = [];
        $GLOBALS['kh_test_options'] = [];
        $adapter = new SftpAccountingAdapter();
        $result  = $adapter->execute( $canonical_settlement );
        return normalise_volatile( $result );
    },

    'api_delivery_execute_response' => static function () use ( $canonical_settlement ): array {
        $GLOBALS['kh_api_sandbox']  = [];
        $GLOBALS['kh_test_options'] = [];
        $adapter = new AccountingApiAdapter();
        $result  = $adapter->execute( $canonical_settlement );
        return normalise_volatile( $result );
    },
];

// ── Run checks ───────────────────────────────────────────────────────────────

$summary  = [];
$failures = 0;
$skipped  = 0;
$passed   = 0;

foreach ($checks as $name => $factory) {
    if ($fixture_filter !== null && $fixture_filter !== $name) {
        $skipped++;
        continue;
    }

    $fixture_path = GOLDEN_DIR . "/{$name}.json";
    $meta_path    = GOLDEN_DIR . "/{$name}.meta.json";

    if (!is_file($fixture_path)) {
        out_warn("SKIP  {$name}: fixture file not found");
        $skipped++;
        continue;
    }

    $stored = load_json($fixture_path);

    // Run the adapter factory.
    try {
        $actual = $factory();
    } catch (Throwable $e) {
        out_fail("FAIL  {$name}: adapter threw " . get_class($e) . ': ' . $e->getMessage());
        $summary[$name] = ['status' => 'error', 'error' => $e->getMessage()];
        $failures++;
        continue;
    }

    // Normalise stored fixture for comparison (strip volatile fields).
    $stored_normalised = normalise_volatile($stored);

    $diff = json_diff($stored_normalised, $actual);

    if (empty($diff)) {
        out_ok("PASS  {$name}");
        $summary[$name] = ['status' => 'pass'];
        $passed++;
    } else {
        if ($write_mode) {
            // Merge volatile fields back from stored fixture, then save.
            $updated  = array_merge($stored, $actual);
            $json_out = json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            file_put_contents($fixture_path, $json_out);
            $new_checksum = hash_file('sha256', $fixture_path);
            update_meta_checksum($meta_path, $new_checksum);
            out_ok("WRITE {$name}: fixture updated (checksum={$new_checksum})");
            $summary[$name] = ['status' => 'updated', 'checksum' => $new_checksum];
            $passed++;
        } else {
            out_fail("FAIL  {$name}: output diverged from fixture");
            foreach ($diff as $key => $change) {
                out_fail("      [{$key}] stored=" . json_encode($change['stored'])
                    . ' actual=' . json_encode($change['actual']));
            }
            $summary[$name] = ['status' => 'diverged', 'diff' => $diff];
            $failures++;
        }
    }
}

// ── Write golden-summary.json ─────────────────────────────────────────────────

$summary_payload = [
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'passed'       => $passed,
    'failed'       => $failures,
    'skipped'      => $skipped,
    'results'      => $summary,
];
file_put_contents(SUMMARY_FILE, json_encode($summary_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
out_info("Summary written to " . SUMMARY_FILE);

if ($failures > 0) {
    out_fail("{$failures} fixture(s) diverged. Run with --write to update, or use regenerate_paid_fixture.php.");
    exit(1);
}

out_ok("All checks passed ({$passed} passed, {$skipped} skipped).");
exit(0);

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Load and JSON-decode a file, exiting on error. */
function load_json(string $path): array {
    if (!is_file($path)) {
        fwrite(STDERR, "ERROR: missing file: {$path}\n");
        exit(2);
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        fwrite(STDERR, "ERROR: invalid JSON: {$path}\n");
        exit(2);
    }
    return $data;
}

/**
 * Strip volatile / non-deterministic fields so fixtures can be compared
 * across runs.  Fields stripped: timestamp, delivered_at, acked_at.
 */
function normalise_volatile(array $data): array {
    $volatile = ['timestamp', 'delivered_at', 'acked_at'];
    foreach ($volatile as $field) {
        unset($data[$field]);
        // Also strip from nested operation arrays.
        if (isset($data['operations']) && is_array($data['operations'])) {
            foreach ($data['operations'] as &$op) {
                unset($op[$field]);
            }
            unset($op);
        }
    }
    return $data;
}

/**
 * Shallow diff: returns [key => [stored, actual]] for diverging scalar keys.
 * Recursively diffs nested arrays.
 */
function json_diff(array $stored, array $actual, string $prefix = ''): array {
    $diff = [];
    $all_keys = array_unique(array_merge(array_keys($stored), array_keys($actual)));

    foreach ($all_keys as $key) {
        $path = $prefix ? "{$prefix}.{$key}" : (string) $key;

        if (!array_key_exists($key, $stored)) {
            $diff[$path] = ['stored' => null, 'actual' => $actual[$key]];
        } elseif (!array_key_exists($key, $actual)) {
            $diff[$path] = ['stored' => $stored[$key], 'actual' => null];
        } elseif (is_array($stored[$key]) && is_array($actual[$key])) {
            $nested = json_diff($stored[$key], $actual[$key], $path);
            $diff   = array_merge($diff, $nested);
        } elseif ($stored[$key] !== $actual[$key]) {
            $diff[$path] = ['stored' => $stored[$key], 'actual' => $actual[$key]];
        }
    }

    return $diff;
}

/** Update the checksum field in a .meta.json file in place. */
function update_meta_checksum(string $meta_path, string $checksum): void {
    if (!is_file($meta_path)) {
        return;
    }
    $meta = json_decode((string) file_get_contents($meta_path), true);
    if (!is_array($meta)) {
        return;
    }
    $meta['checksum']    = $checksum;
    $meta['prompt_hash'] = $checksum;
    file_put_contents($meta_path, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function out_ok(string $msg): void   { fwrite(STDOUT, "\033[32m{$msg}\033[0m\n"); }
function out_fail(string $msg): void { fwrite(STDERR, "\033[31m{$msg}\033[0m\n"); }
function out_warn(string $msg): void { fwrite(STDOUT, "\033[33m{$msg}\033[0m\n"); }
function out_info(string $msg): void { fwrite(STDOUT, "{$msg}\n"); }
