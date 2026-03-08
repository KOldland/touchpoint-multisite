<?php

declare(strict_types=1);

$baseRef = getenv('GITHUB_BASE_REF') ?: 'main';
$baseRef = preg_replace('/^origin\//', '', $baseRef);
$base = 'origin/' . $baseRef;

exec('git fetch origin ' . escapeshellarg($baseRef), $fetchOut, $fetchCode);
if ($fetchCode !== 0) {
    fwrite(STDERR, "Migration Guard Failure\nUnable to fetch base branch: {$baseRef}\n");
    exit(1);
}

$diffOutput = shell_exec('git diff --name-status ' . escapeshellarg($base . '...HEAD'));
$changed = array_filter(array_map('trim', explode("\n", (string) $diffOutput)));

$migrationDirs = array(
    'database/migrations/',
    'migrations/',
);

$violations = array();
$addedMigrations = array();

foreach ($changed as $line) {
    if ($line === '') {
        continue;
    }

    $parts = preg_split('/\s+/', $line, 3);
    if (!is_array($parts) || count($parts) < 2) {
        continue;
    }

    $status = trim((string) $parts[0]);
    $file = trim((string) $parts[1]);

    $isMigration = false;
    foreach ($migrationDirs as $dir) {
        if (strpos($file, $dir) === 0) {
            $isMigration = true;
            break;
        }
    }

    if (!$isMigration) {
        continue;
    }

    if ($status === 'M') {
        $violations[] = $file;
        continue;
    }

    if ($status === 'A') {
        $addedMigrations[] = $file;
    }
}

if (!empty($violations)) {
    echo "Migration Guard Failure\n";
    echo "Existing migrations cannot be modified:\n";

    foreach ($violations as $file) {
        echo " - {$file}\n";
    }

    echo "\nCreate a new migration instead.\n";
    exit(1);
}

/**
 * @return array{primary:int,secondary:?int}|null
 */
function migration_sequence_parts(string $file): ?array {
    $base = basename($file);
    if (!preg_match('/^(\d+)(?:_(\d+))?/', $base, $matches)) {
        return null;
    }

    return array(
        'primary' => (int) $matches[1],
        'secondary' => isset($matches[2]) ? (int) $matches[2] : null,
    );
}

/**
 * @return string|null
 */
function migration_directory_for_file(string $file, array $dirs): ?string {
    foreach ($dirs as $dir) {
        if (strpos($file, $dir) === 0) {
            return $dir;
        }
    }
    return null;
}

if (!empty($addedMigrations)) {
    $maxExistingByDir = array();

    foreach ($migrationDirs as $dir) {
        $treeOutput = shell_exec('git ls-tree -r --name-only ' . escapeshellarg($base) . ' ' . escapeshellarg($dir));
        $files = array_filter(array_map('trim', explode("\n", (string) $treeOutput)));

        $max = 0;
        foreach ($files as $existingFile) {
            $parts = migration_sequence_parts($existingFile);
            if ($parts !== null && $parts['primary'] > $max) {
                $max = $parts['primary'];
            }
        }
        $maxExistingByDir[$dir] = $max;
    }

    $addedByDir = array();
    foreach ($addedMigrations as $file) {
        $dir = migration_directory_for_file($file, $migrationDirs);
        if ($dir === null) {
            continue;
        }

        $addedByDir[$dir] = $addedByDir[$dir] ?? array();
        $addedByDir[$dir][] = $file;
    }

    $sequenceViolations = array();

    foreach ($addedByDir as $dir => $files) {
        $existingMax = (int) ($maxExistingByDir[$dir] ?? 0);
        $explicitSequenceKeys = array();

        foreach ($files as $file) {
            $parts = migration_sequence_parts($file);
            if ($parts === null) {
                continue;
            }

            if ($parts['primary'] < $existingMax) {
                $sequenceViolations[] = $file . ' (sequence ' . $parts['primary'] . ' must be greater than or equal to existing max ' . $existingMax . ')';
            }

            if ($parts['secondary'] !== null) {
                $explicitSequenceKeys[$file] = ((int) ($parts['primary'] * 1000000)) + $parts['secondary'];
            }
        }

        if (!empty($explicitSequenceKeys)) {
            $sorted = $explicitSequenceKeys;
            asort($sorted, SORT_NUMERIC);

            $seen = array();
            foreach ($sorted as $file => $key) {
                if (in_array($key, $seen, true)) {
                    $sequenceViolations[] = $file . ' (duplicate sequence ' . $key . ')';
                }
                $seen[] = $key;
            }

            $values = array_values($sorted);
            for ($i = 1; $i < count($values); $i++) {
                if ($values[$i] <= $values[$i - 1]) {
                    $sequenceViolations[] = 'new migrations with explicit sequence numbers must be strictly increasing in ' . $dir;
                    break;
                }
            }
        }
    }

    if (!empty($sequenceViolations)) {
        echo "Migration Guard Failure\n";
        echo "Sequential migration check failed:\n";
        foreach ($sequenceViolations as $issue) {
            echo " - {$issue}\n";
        }
        echo "\nUse the next available migration sequence.\n";
        exit(1);
    }
}

echo "Migration Guard Passed\n";
exit(0);