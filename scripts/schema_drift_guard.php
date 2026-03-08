<?php

declare(strict_types=1);

function run_or_fail(string $title, string $command): void {
    echo "{$title}: {$command}\n";
    passthru($command, $code);
    if ($code !== 0) {
        echo "Schema Drift Failure\n";
        echo "Command failed: {$command}\n";
        exit(1);
    }
}

$applyCmd = trim((string) getenv('SCHEMA_APPLY_CMD'));
$validateCmd = trim((string) getenv('SCHEMA_VALIDATE_CMD'));

if ($applyCmd === '' || $validateCmd === '') {
    echo "Schema Drift Failure\n";
    echo "Missing required CI configuration.\n";
    echo "Set both SCHEMA_APPLY_CMD and SCHEMA_VALIDATE_CMD environment variables.\n";
    echo "Example (Laravel):\n";
    echo "  SCHEMA_APPLY_CMD=\"php artisan migrate:fresh\"\n";
    echo "  SCHEMA_VALIDATE_CMD=\"php artisan schema:validate\"\n";
    echo "Example (WordPress style):\n";
    echo "  SCHEMA_APPLY_CMD=\"<run migrations + install script>\"\n";
    echo "  SCHEMA_VALIDATE_CMD=\"<compare expected schema>\"\n";
    exit(1);
}

run_or_fail('Schema Apply', $applyCmd);
run_or_fail('Schema Validate', $validateCmd);

echo "Schema Drift Guard Passed\n";
exit(0);