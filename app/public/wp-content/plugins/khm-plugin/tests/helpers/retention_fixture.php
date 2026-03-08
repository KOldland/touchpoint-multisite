<?php
declare(strict_types=1);

/**
 * Seed synthetic attribution rows for retention/perf-like test runs.
 */
function khm_test_seed_retention_rows(int $count, int $ageDays = 800): int {
    global $wpdb;
    $table = $wpdb->prefix . 'promotion_attribution';
    $createdAt = gmdate('Y-m-d H:i:s', time() - ($ageDays * 86400));

    $inserted = 0;
    for ($index = 1; $index <= $count; $index++) {
        $ok = $wpdb->insert($table, [
            'user_id' => 10000 + $index,
            'user_email' => 'retention' . $index . '@example.com',
            'schedule_id' => 1,
            'sponsor_id' => 1,
            'utm_source' => 'seed',
            'conversion_type' => 'paid',
            'consent' => 1,
            'created_at' => $createdAt,
        ]);

        if ($ok) {
            $inserted++;
        }
    }

    return $inserted;
}
