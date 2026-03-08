<?php
declare(strict_types=1);

/**
 * MEM-09 membership load test harness.
 *
 * Simulates signup-init requests and optional signed webhook posts.
 *
 * Usage example:
 * php tests/perf/membership_load_test.php \
 *   --base-url=https://staging.example.com \
 *   --requests=1000 \
 *   --concurrency=100 \
 *   --mode=end_to_end \
 *   --out=artifacts/mem09_load
 */

$options = getopt('', [
    'base-url:',
    'requests::',
    'concurrency::',
    'mode::',
    'out::',
    'webhook-secret::',
    'timeout::',
]);

$baseUrl = isset($options['base-url']) ? rtrim((string) $options['base-url'], '/') : '';
if ($baseUrl === '') {
    fwrite(STDERR, "Missing required --base-url\n");
    exit(1);
}

$requests = max(1, (int) ($options['requests'] ?? 500));
$concurrency = max(1, (int) ($options['concurrency'] ?? 50));
$mode = (string) ($options['mode'] ?? 'landing_only');
$outDir = (string) ($options['out'] ?? 'artifacts/mem09_load');
$webhookSecret = (string) ($options['webhook-secret'] ?? 'whsec_test_secret');
$timeout = max(1, (int) ($options['timeout'] ?? 30));

if (!in_array($mode, ['landing_only', 'end_to_end'], true)) {
    fwrite(STDERR, "Invalid --mode. Use landing_only|end_to_end\n");
    exit(1);
}

if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Failed to create output directory: {$outDir}\n");
    exit(1);
}

$results = [];
$errors = 0;
$signupUrl = $baseUrl . '/wp-json/kh-membership/v1/signup-init';
$webhookUrl = $baseUrl . '/wp-json/khm/v1/webhooks/stripe';

$start = microtime(true);

for ($offset = 0; $offset < $requests; $offset += $concurrency) {
    $batchSize = min($concurrency, $requests - $offset);
    $multi = curl_multi_init();
    $handles = [];

    for ($i = 0; $i < $batchSize; $i++) {
        $index = $offset + $i + 1;
        $payload = build_signup_payload($index);

        $ch = curl_init($signupUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HEADER => true,
        ]);

        $handles[(int) $ch] = [
            'curl' => $ch,
            'index' => $index,
            'start' => microtime(true),
            'type' => 'signup-init',
        ];

        curl_multi_add_handle($multi, $ch);
    }

    execute_multi($multi);

    foreach ($handles as $item) {
        $ch = $item['curl'];
        $elapsedMs = (microtime(true) - $item['start']) * 1000;
        $raw = (string) curl_multi_getcontent($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        [$headers, $body] = split_http_response($raw);
        $sessionId = '';
        $json = json_decode($body, true);
        if (is_array($json) && isset($json['session_id'])) {
            $sessionId = (string) $json['session_id'];
        }

        $results[] = [
            'index' => $item['index'],
            'request_type' => 'signup-init',
            'http_status' => $status,
            'latency_ms' => round($elapsedMs, 2),
            'ok' => $status >= 200 && $status < 300 ? 1 : 0,
            'session_id' => $sessionId,
        ];

        if ($status < 200 || $status >= 300) {
            $errors++;
        }

        if ($mode === 'end_to_end' && $sessionId !== '') {
            $webhookPayload = build_webhook_payload($item['index'], $sessionId);
            $webhookBody = json_encode($webhookPayload);
            $signature = build_signature((string) $webhookBody, $webhookSecret);

            $wStart = microtime(true);
            $wch = curl_init($webhookUrl);
            curl_setopt_array($wch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Stripe-Signature: ' . $signature,
                ],
                CURLOPT_POSTFIELDS => $webhookBody,
                CURLOPT_TIMEOUT => $timeout,
            ]);
            $wBody = (string) curl_exec($wch);
            $wStatus = (int) curl_getinfo($wch, CURLINFO_HTTP_CODE);
            curl_close($wch);

            $results[] = [
                'index' => $item['index'],
                'request_type' => 'webhook',
                'http_status' => $wStatus,
                'latency_ms' => round((microtime(true) - $wStart) * 1000, 2),
                'ok' => $wStatus >= 200 && $wStatus < 300 ? 1 : 0,
                'session_id' => $sessionId,
            ];

            if ($wStatus < 200 || $wStatus >= 300) {
                $errors++;
                file_put_contents($outDir . '/webhook_errors.log', "#{$item['index']} status={$wStatus} body={$wBody}\n", FILE_APPEND);
            }
        }

        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }

    curl_multi_close($multi);
}

$duration = microtime(true) - $start;
$latencies = array_map(static fn(array $row): float => (float) $row['latency_ms'], $results);
sort($latencies);

$summary = [
    'base_url' => $baseUrl,
    'mode' => $mode,
    'requests' => $requests,
    'concurrency' => $concurrency,
    'total_observations' => count($results),
    'errors' => $errors,
    'error_rate_percent' => count($results) > 0 ? round(($errors / count($results)) * 100, 3) : 0,
    'duration_seconds' => round($duration, 3),
    'throughput_rps' => $duration > 0 ? round(count($results) / $duration, 3) : 0,
    'latency_ms' => [
        'p50' => percentile($latencies, 50),
        'p95' => percentile($latencies, 95),
        'p99' => percentile($latencies, 99),
        'max' => empty($latencies) ? 0 : max($latencies),
    ],
    'timestamp_utc' => gmdate('c'),
];

write_csv($outDir . '/membership_load_results.csv', $results);
file_put_contents($outDir . '/membership_load_summary.json', json_encode($summary, JSON_PRETTY_PRINT));

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
exit($errors > 0 ? 2 : 0);

function execute_multi($multi): void {
    $running = 0;
    do {
        curl_multi_exec($multi, $running);
        curl_multi_select($multi, 0.5);
    } while ($running > 0);
}

function percentile(array $values, int $p): float {
    if (empty($values)) {
        return 0.0;
    }
    $index = (int) ceil(($p / 100) * count($values)) - 1;
    $index = max(0, min(count($values) - 1, $index));
    return round((float) $values[$index], 2);
}

function split_http_response(string $raw): array {
    $parts = preg_split("/\r\n\r\n/", $raw, 2);
    if (!is_array($parts) || count($parts) < 2) {
        return ['', $raw];
    }
    return [$parts[0], $parts[1]];
}

function write_csv(string $path, array $rows): void {
    $handle = fopen($path, 'w');
    if ($handle === false) {
        throw new RuntimeException('Unable to write CSV: ' . $path);
    }

    fputcsv($handle, ['index', 'request_type', 'http_status', 'latency_ms', 'ok', 'session_id']);
    foreach ($rows as $row) {
        fputcsv($handle, [
            $row['index'] ?? '',
            $row['request_type'] ?? '',
            $row['http_status'] ?? '',
            $row['latency_ms'] ?? '',
            $row['ok'] ?? '',
            $row['session_id'] ?? '',
        ]);
    }

    fclose($handle);
}

function build_signup_payload(int $index): array {
    return [
        'schedule_id' => 'sch_perf_' . $index,
        'sponsor_id' => 'sp_perf_' . ($index % 10),
        'utm_source' => 'perf_suite',
        'utm_medium' => 'load',
        'utm_campaign' => 'mem09',
        'phase_at_click' => 'attention',
        'idempotency_key' => generate_uuid($index),
        'consent' => true,
        'client_reference' => 'perf-run-' . $index,
        'plan_id' => 'pro_monthly',
    ];
}

function build_webhook_payload(int $index, string $sessionId): array {
    return [
        'id' => 'evt_perf_' . $index,
        'type' => 'checkout.session.completed',
        'created' => time(),
        'data' => [
            'object' => [
                'id' => $sessionId,
                'mode' => 'subscription',
                'metadata' => [
                    'user_id' => (string) (100000 + $index),
                    'membership_level_id' => '1',
                    'tier_slug' => 'pro',
                    'stripe_price_id' => 'price_perf_001',
                    'schedule_id' => (string) (1000 + ($index % 50)),
                    'sponsor_id' => (string) (200 + ($index % 10)),
                    'utm_source' => 'perf_suite',
                    'utm_medium' => 'load',
                    'utm_campaign' => 'mem09',
                    'phase_at_click' => 'attention',
                    'consent' => '1',
                ],
            ],
        ],
    ];
}

function build_signature(string $payload, string $secret): string {
    $timestamp = time();
    $signedPayload = $timestamp . '.' . $payload;
    $sig = hash_hmac('sha256', $signedPayload, $secret);
    return 't=' . $timestamp . ',v1=' . $sig;
}

function generate_uuid(int $seed): string {
    $hex = md5('mem09-' . $seed);
    return substr($hex, 0, 8)
        . '-' . substr($hex, 8, 4)
        . '-4' . substr($hex, 13, 3)
        . '-a' . substr($hex, 17, 3)
        . '-' . substr($hex, 20, 12);
}
