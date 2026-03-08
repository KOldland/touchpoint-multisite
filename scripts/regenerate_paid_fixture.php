<?php
declare(strict_types=1);

/**
 * PAID-07 — Paid Fixture Regenerator
 *
 * Safe, governed helper for regenerating a single golden fixture.
 * Runs the adapter in sandbox mode, normalises the output, writes the
 * fixture JSON, computes the SHA256, and updates (or creates) the
 * .meta.json sidecar.
 *
 * CIC governance: every run requires --confirmed flag to prevent accidental
 * overwrites.  The resulting fixture must be reviewed and the
 * 'golden-owner-approved' label retained in the .meta.json before merging.
 *
 * Usage:
 *   php scripts/regenerate_paid_fixture.php --fixture=<name> --confirmed
 *
 * Example:
 *   php scripts/regenerate_paid_fixture.php --fixture=google_sandbox_dry_run_response --confirmed
 *
 * Exit codes:
 *   0  Fixture regenerated successfully.
 *   1  Fixture name unknown.
 *   2  Usage error (missing --fixture or --confirmed).
 */

const GOLDEN_DIR = __DIR__ . '/../app/public/wp-content/plugins/kh-smma/tests/fixtures/golden';

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

// ── CLI argument parsing ──────────────────────────────────────────────────────

$fixture_name = null;
$confirmed    = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--fixture=')) {
        $fixture_name = trim(substr($arg, strlen('--fixture=')));
    }
    if ($arg === '--confirmed') {
        $confirmed = true;
    }
}

if ($fixture_name === null) {
    fwrite(STDERR, "ERROR: --fixture=<name> is required.\n");
    fwrite(STDERR, "Available fixtures: see scripts/verify_golden_fixtures.php \$required array.\n");
    exit(2);
}

if (!$confirmed) {
    fwrite(STDERR, "ERROR: --confirmed flag is required to prevent accidental overwrites.\n");
    fwrite(STDERR, "Review the output of paid_adapter_golden_check.php first.\n");
    exit(2);
}

// ── Canonical inputs ──────────────────────────────────────────────────────────

$GLOBALS['kh_test_options'] = [];
$GLOBALS['kh_sftp_sandbox'] = [];
$GLOBALS['kh_api_sandbox']  = [];

$canonical_manifest_path = GOLDEN_DIR . '/google_sandbox_dry_run_manifest.json';
$canonical_manifest      = load_json_or_exit($canonical_manifest_path);

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

// ── Fixture registry ──────────────────────────────────────────────────────────

/** @var array<string, array{factory: callable, prompt_version: string, notes: string}> */
$registry = [

    'google_sandbox_dry_run_response' => [
        'factory'        => static function () use ( $canonical_manifest ): array {
            return (new GoogleSandboxAdapter())->dry_run($canonical_manifest);
        },
        'prompt_version' => 'paid-07',
        'notes'          => 'PAID-07: GoogleSandboxAdapter deterministic dry_run response. manifest_id=man_20260303_001, op_1, estimated=60.0 AUD (10×6d), confidence=0.91.',
    ],

    'google_sandbox_execute_response' => [
        'factory'        => static function () use ( $canonical_manifest ): array {
            $GLOBALS['kh_test_options'] = [];
            return (new GoogleSandboxAdapter())->execute($canonical_manifest);
        },
        'prompt_version' => 'paid-03',
        'notes'          => 'PAID-03: GoogleSandboxAdapter deterministic execute response. manifest_id=man_20260303_001, op_1, idem=a1b2c3d4..., estimated=60.0 AUD (10×6d), actual=60.07 AUD (delta via sha256 seed of google_sandbox).',
    ],

    'linkedin_sandbox_dry_run_response' => [
        'factory'        => static function () use ( $canonical_manifest ): array {
            return (new LinkedInSandboxAdapter())->dry_run($canonical_manifest);
        },
        'prompt_version' => 'paid-07',
        'notes'          => 'PAID-07: LinkedInSandboxAdapter deterministic dry_run response. manifest_id=man_20260303_001, op_1, estimated=60.0 AUD (10×6d), confidence=0.86.',
    ],

    'linkedin_sandbox_execute_response' => [
        'factory'        => static function () use ( $canonical_manifest ): array {
            $GLOBALS['kh_test_options'] = [];
            return (new LinkedInSandboxAdapter())->execute($canonical_manifest);
        },
        'prompt_version' => 'paid-03',
        'notes'          => 'PAID-03: LinkedInSandboxAdapter deterministic execute response. manifest_id=man_20260303_001, op_1, idem=a1b2c3d4..., estimated=60.0 AUD (10×6d), actual=59.35 AUD (delta via sha256 seed of linkedin_sandbox).',
    ],

    'manual_export_dry_run_response' => [
        'factory'        => static function () use ( $canonical_manifest ): array {
            return (new ManualExportAdapter())->dry_run($canonical_manifest);
        },
        'prompt_version' => 'paid-07',
        'notes'          => 'PAID-07: ManualExportAdapter deterministic dry_run response. manifest_id=man_20260303_001, op_1, estimated=60.0 AUD, confidence=1.0 (manual export is fully predictable).',
    ],

    'manual_export_execute_response' => [
        'factory'        => static function () use ( $canonical_manifest ): array {
            $GLOBALS['kh_test_options'] = [];
            return (new ManualExportAdapter())->execute($canonical_manifest);
        },
        'prompt_version' => 'paid-07',
        'notes'          => 'PAID-07: ManualExportAdapter execute response. manifest_id=man_20260303_001, status=awaiting_manual_export. Bundle stored to WP option for manual ops pickup.',
    ],

    'sftp_delivery_dry_run_response' => [
        'factory'        => static function () use ( $canonical_settlement ): array {
            return (new SftpAccountingAdapter())->dry_run($canonical_settlement);
        },
        'prompt_version' => 'paid-07',
        'notes'          => 'PAID-07: SftpAccountingAdapter deterministic dry_run response. settlement_id=sett_xyz789abc012, adapter=sftp, valid=true. Sandbox simulation — no real SFTP connection.',
    ],

    'sftp_delivery_execute_response' => [
        'factory'        => static function () use ( $canonical_settlement ): array {
            $GLOBALS['kh_sftp_sandbox'] = [];
            $GLOBALS['kh_test_options'] = [];
            return (new SftpAccountingAdapter())->execute($canonical_settlement);
        },
        'prompt_version' => 'paid-06',
        'notes'          => 'PAID-06: SftpAccountingAdapter deterministic execute response. settlement_id=sett_xyz789abc012, adapter=sftp, status=delivered. Sandbox simulation — no real SFTP connection.',
    ],

    'api_delivery_execute_response' => [
        'factory'        => static function () use ( $canonical_settlement ): array {
            $GLOBALS['kh_api_sandbox']  = [];
            $GLOBALS['kh_test_options'] = [];
            return (new AccountingApiAdapter())->execute($canonical_settlement);
        },
        'prompt_version' => 'paid-06',
        'notes'          => 'PAID-06: AccountingApiAdapter deterministic execute response. settlement_id=sett_xyz789abc012, adapter=accounting_api, status=delivered. Sandbox simulation — no real HTTP call.',
    ],
];

// ── Regenerate ────────────────────────────────────────────────────────────────

if (!isset($registry[$fixture_name])) {
    fwrite(STDERR, "ERROR: unknown fixture '{$fixture_name}'.\n");
    fwrite(STDERR, "Available: " . implode(', ', array_keys($registry)) . "\n");
    exit(1);
}

$entry        = $registry[$fixture_name];
$fixture_path = GOLDEN_DIR . "/{$fixture_name}.json";
$meta_path    = GOLDEN_DIR . "/{$fixture_name}.meta.json";

// Run adapter.
try {
    $result = ($entry['factory'])();
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: adapter threw " . get_class($e) . ': ' . $e->getMessage() . "\n");
    exit(1);
}

// Strip volatile fields before saving.
$normalised = normalise_volatile($result);

// Write fixture JSON (2-space indent, no escaped slashes, trailing newline).
$json_out = json_encode($normalised, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
file_put_contents($fixture_path, $json_out);

// Compute checksum of saved file.
$checksum = hash_file('sha256', $fixture_path);

// Build or update .meta.json sidecar.
$meta_existing = is_file($meta_path)
    ? (json_decode((string) file_get_contents($meta_path), true) ?? [])
    : [];

$meta = array_merge($meta_existing, [
    'version'        => '1.0.0',
    'prompt_hash'    => $checksum,
    'prompt_version' => $entry['prompt_version'],
    'created_at'     => gmdate('Y-m-d\TH:i:s\Z'),
    'author'         => $meta_existing['author'] ?? '@paid-adapter-team',
    'checksum'       => $checksum,
    'labels'         => $meta_existing['labels'] ?? ['golden-owner-approved'],
    'notes'          => $entry['notes'],
]);

file_put_contents($meta_path, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

fwrite(STDOUT, "OK  {$fixture_name}\n");
fwrite(STDOUT, "    fixture : {$fixture_path}\n");
fwrite(STDOUT, "    meta    : {$meta_path}\n");
fwrite(STDOUT, "    checksum: {$checksum}\n");
fwrite(STDOUT, "\nIMPORTANT: Review diff, retain 'golden-owner-approved' label, then commit.\n");
exit(0);

// ── Helpers ───────────────────────────────────────────────────────────────────

function load_json_or_exit(string $path): array {
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

function normalise_volatile(array $data): array {
    $volatile = ['timestamp', 'delivered_at', 'acked_at'];
    foreach ($volatile as $field) {
        unset($data[$field]);
        if (isset($data['operations']) && is_array($data['operations'])) {
            foreach ($data['operations'] as &$op) {
                unset($op[$field]);
            }
            unset($op);
        }
    }
    return $data;
}
