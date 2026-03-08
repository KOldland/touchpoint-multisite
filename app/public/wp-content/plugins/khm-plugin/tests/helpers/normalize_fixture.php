<?php
declare(strict_types=1);

/**
 * Normalize fixture payload for stable assertions.
 *
 * - Removes volatile keys (id/created/timestamp) unless preserve list is provided.
 * - Sorts associative keys recursively for deterministic comparisons.
 *
 * @param array<string,mixed> $payload
 * @param array<int,string> $preserveKeys
 * @return array<string,mixed>
 */
function khm_test_normalize_fixture(array $payload, array $preserveKeys = []): array {
    $preserve = array_fill_keys($preserveKeys, true);

    $normalized = khm_test_recursive_normalize($payload, $preserve);
    return is_array($normalized) ? $normalized : [];
}

/**
 * @param mixed $value
 * @param array<string,bool> $preserve
 * @return mixed
 */
function khm_test_recursive_normalize($value, array $preserve) {
    if (!is_array($value)) {
        return $value;
    }

    $volatileKeys = [
        'id',
        'created',
        'timestamp',
        'trace_id',
        'latency_ms',
    ];

    $result = [];
    foreach ($value as $key => $item) {
        $stringKey = is_string($key) ? $key : (string) $key;
        if (is_string($key) && in_array($stringKey, $volatileKeys, true) && empty($preserve[$stringKey])) {
            continue;
        }
        $result[$key] = khm_test_recursive_normalize($item, $preserve);
    }

    if (khm_test_is_assoc($result)) {
        ksort($result);
    }

    return $result;
}

/**
 * @param array<mixed> $array
 */
function khm_test_is_assoc(array $array): bool {
    if ([] === $array) {
        return false;
    }
    return array_keys($array) !== range(0, count($array) - 1);
}
