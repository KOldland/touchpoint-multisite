<?php

declare(strict_types=1);

function parse_scope_file(string $path): array {
    if (function_exists('yaml_parse_file')) {
        $parsed = yaml_parse_file($path);
        return is_array($parsed) ? $parsed : [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $result = [];
    $currentList = null;

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (preg_match('/^([a-zA-Z0-9_]+):\s*(.*)$/', $trimmed, $matches)) {
            $key = $matches[1];
            $value = trim($matches[2]);

            if ($value === '') {
                $result[$key] = [];
                $currentList = $key;
            } else {
                $result[$key] = $value;
                $currentList = null;
            }

            continue;
        }

        if ($currentList !== null && preg_match('/^-\s+(.*)$/', $trimmed, $matches)) {
            $result[$currentList][] = trim($matches[1], " \t\n\r\0\x0B\"'");
        }
    }

    return $result;
}

$scopePath = '.dev-scope.yml';
if (!is_file($scopePath)) {
    fwrite(STDERR, "Scope Guard Failure\nMissing scope file: .dev-scope.yml\n");
    exit(1);
}

$baseRef = getenv('GITHUB_BASE_REF') ?: 'main';
$baseRef = preg_replace('/^origin\//', '', $baseRef);
$base = 'origin/' . $baseRef;

exec('git fetch origin ' . escapeshellarg($baseRef), $fetchOut, $fetchCode);
if ($fetchCode !== 0) {
    fwrite(STDERR, "Scope Guard Failure\nUnable to fetch base branch: {$baseRef}\n");
    exit(1);
}

$diff = shell_exec('git diff --name-only ' . escapeshellarg($base . '...HEAD'));
$files = array_values(array_filter(explode("\n", (string) $diff)));

$scope = parse_scope_file($scopePath);

$allowed = is_array($scope['allowed_paths'] ?? null) ? $scope['allowed_paths'] : [];
$protected = is_array($scope['protected_paths'] ?? null) ? $scope['protected_paths'] : [];

$violations = [];
$protectedHits = [];

foreach ($files as $file) {
    $allowedMatch = false;

    foreach ($allowed as $path) {
        if (strpos($file, (string) $path) === 0) {
            $allowedMatch = true;
            break;
        }
    }

    if (!$allowedMatch) {
        $violations[] = $file;
    }

    foreach ($protected as $path) {
        if (strpos($file, (string) $path) === 0) {
            $protectedHits[] = $file;
        }
    }
}

if (!empty($violations)) {
    echo "Scope Guard Failure\n";
    echo "The following files are outside allowed paths:\n";

    foreach ($violations as $file) {
        echo " - {$file}\n";
    }

    exit(1);
}

if (!empty($protectedHits) && !getenv('CONTRACT_CHANGE_APPROVED')) {
    echo "Protected Contract Files Modified:\n";

    foreach ($protectedHits as $file) {
        echo " - {$file}\n";
    }

    echo "\nAdd CI label 'contract-change-approved' to proceed.\n";
    exit(1);
}

echo "Scope Guard Passed\n";
exit(0);