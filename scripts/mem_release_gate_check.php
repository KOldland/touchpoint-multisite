<?php
declare(strict_types=1);

const ROOT_DIR = __DIR__ . '/..';
const KHM_PLUGIN_DIR = ROOT_DIR . '/app/public/wp-content/plugins/khm-plugin';

$options = getopt('', [
    'environment::',
    'php-bin::',
    'skip-golden',
    'skip-smoke',
    'skip-retention',
    'dry-run',
]);

$environment = isset($options['environment']) ? (string) $options['environment'] : 'production';
$phpBin = isset($options['php-bin']) ? (string) $options['php-bin'] : 'php';
$dryRun = array_key_exists('dry-run', $options);

$steps = [];

if (!array_key_exists('skip-golden', $options)) {
    $steps[] = [
        'name' => 'golden-check',
        'command' => sprintf('%s %s/scripts/verify_golden_fixtures.php', escapeshellarg($phpBin), escapeshellarg(ROOT_DIR)),
    ];
}

if (!array_key_exists('skip-smoke', $options)) {
    $steps[] = [
        'name' => 'membership-smoke-signup-init',
        'command' => sprintf(
            'cd %s && %s vendor/bin/phpunit --colors=never tests/Membership/SignupInitMatrixTest.php',
            escapeshellarg(KHM_PLUGIN_DIR),
            escapeshellarg($phpBin)
        ),
    ];
    $steps[] = [
        'name' => 'membership-smoke-webhook-fixtures',
        'command' => sprintf(
            'cd %s && KH_STRIPE_WEBHOOK_SECRET=whsec_test_secret %s vendor/bin/phpunit --colors=never tests/Membership/StripeWebhookFixtureIntegrationTest.php',
            escapeshellarg(KHM_PLUGIN_DIR),
            escapeshellarg($phpBin)
        ),
    ];
}

if (!array_key_exists('skip-retention', $options)) {
    $steps[] = [
        'name' => 'retention-sanity',
        'command' => sprintf(
            'cd %s && %s vendor/bin/phpunit --colors=never tests/Membership/RetentionTest.php tests/Membership/RetentionWorkerChunkTest.php',
            escapeshellarg(KHM_PLUGIN_DIR),
            escapeshellarg($phpBin)
        ),
    ];
}

if (empty($steps)) {
    fwrite(STDERR, "No gate checks selected. Remove skip flags.\n");
    exit(2);
}

fwrite(STDOUT, "MEM release gate check starting\n");
fwrite(STDOUT, "Environment: {$environment}\n");
fwrite(STDOUT, "Checks: " . implode(', ', array_map(static fn(array $step): string => $step['name'], $steps)) . "\n\n");

$results = [];
$failed = false;

foreach ($steps as $step) {
    $startedAt = microtime(true);
    fwrite(STDOUT, "==> {$step['name']}\n");
    fwrite(STDOUT, "    {$step['command']}\n");

    if ($dryRun) {
        $results[] = [
            'name' => $step['name'],
            'status' => 'skipped(dry-run)',
            'duration' => 0.0,
            'exit_code' => 0,
        ];
        fwrite(STDOUT, "    dry-run: skipped\n\n");
        continue;
    }

    $exitCode = 0;
    passthru($step['command'], $exitCode);
    $duration = microtime(true) - $startedAt;
    $status = $exitCode === 0 ? 'passed' : 'failed';
    if ($exitCode !== 0) {
        $failed = true;
    }

    $results[] = [
        'name' => $step['name'],
        'status' => $status,
        'duration' => $duration,
        'exit_code' => $exitCode,
    ];

    fwrite(STDOUT, sprintf("    %s (exit=%d, %.2fs)\n\n", $status, $exitCode, $duration));
}

fwrite(STDOUT, "MEM release gate summary\n");
foreach ($results as $result) {
    fwrite(
        STDOUT,
        sprintf(
            "- %s: %s (exit=%d, %.2fs)\n",
            $result['name'],
            $result['status'],
            $result['exit_code'],
            $result['duration']
        )
    );
}

if ($failed) {
    fwrite(STDERR, "\nMEM release gate failed. Resolve failing checks before canary/full enable.\n");
    exit(1);
}

fwrite(STDOUT, "\nMEM release gate passed. Proceed to canary rollout.\n");
exit(0);
