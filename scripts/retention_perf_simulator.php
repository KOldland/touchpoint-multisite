<?php
declare(strict_types=1);

/**
 * MEM-09 retention performance simulator.
 *
 * Usage (inside WordPress context):
 *   wp eval-file scripts/retention_perf_simulator.php -- --rows=100000 --chunk-size=1000 --mode=anonymize
 */

if ( ! isset( $GLOBALS['wpdb'] ) || ! $GLOBALS['wpdb'] instanceof wpdb ) {
    fwrite( STDERR, "Run via wp eval-file so WordPress context is loaded.\n" );
    exit( 1 );
}

$options = getopt('', [
    'rows::',
    'chunk-size::',
    'mode::',
    'seed-only',
    'run-only',
    'retention-days::',
]);

$rows = max(1, (int) ($options['rows'] ?? 100000));
$chunkSize = max(1, (int) ($options['chunk-size'] ?? 1000));
$mode = (string) ($options['mode'] ?? 'anonymize');
$retentionDays = max(1, (int) ($options['retention-days'] ?? 365));

if ( ! in_array( $mode, [ 'anonymize', 'delete' ], true ) ) {
    fwrite( STDERR, "Invalid --mode; use anonymize|delete\n" );
    exit( 1 );
}

$seedOnly = array_key_exists('seed-only', $options);
$runOnly = array_key_exists('run-only', $options);

global $wpdb;
$table = $wpdb->prefix . 'promotion_attribution';
$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
if ( $exists !== $table ) {
    fwrite( STDERR, "Table not found: {$table}\n" );
    exit( 1 );
}

if ( ! $runOnly ) {
    $inserted = seed_rows( $wpdb, $table, $rows );
    fwrite( STDOUT, "Seeded rows: {$inserted}\n" );
}

if ( $seedOnly ) {
    exit( 0 );
}

$worker = new \KHM\Membership\RetentionWorker();
$start = microtime( true );
$memoryStart = memory_get_usage( true );

$result = $worker->run( false, $retentionDays, $mode, $chunkSize );

$duration = microtime( true ) - $start;
$memoryEnd = memory_get_usage( true );

$report = [
    'rows_seed_target' => $rows,
    'chunk_size' => $chunkSize,
    'mode' => $mode,
    'retention_days' => $retentionDays,
    'result' => $result,
    'duration_seconds' => round( $duration, 3 ),
    'memory_mb' => [
        'start' => round( $memoryStart / 1048576, 2 ),
        'end' => round( $memoryEnd / 1048576, 2 ),
        'delta' => round( ( $memoryEnd - $memoryStart ) / 1048576, 2 ),
    ],
    'timestamp_utc' => gmdate( 'c' ),
];

echo wp_json_encode( $report, JSON_PRETTY_PRINT ) . PHP_EOL;
exit( 0 );

function seed_rows( wpdb $wpdb, string $table, int $rows ): int {
    $inserted = 0;
    $now = time();

    for ( $i = 0; $i < $rows; $i++ ) {
        $created = gmdate( 'Y-m-d H:i:s', $now - ( 400 + ( $i % 800 ) ) * 86400 );
        $result = $wpdb->insert(
            $table,
            [
                'schedule_id' => (string) ( 1000 + ( $i % 100 ) ),
                'sponsor_id' => (string) ( 200 + ( $i % 25 ) ),
                'user_id' => 900000 + $i,
                'user_email' => 'mem09+' . $i . '@example.test',
                'utm_source' => 'perf',
                'utm_medium' => 'sim',
                'utm_campaign' => 'mem09',
                'phase_at_click' => 'attention',
                'conversion_type' => 'signup',
                'reference' => 'cs_perf_' . $i,
                'consent' => 1,
                'consent_source' => 'landing',
                'created_at' => $created,
            ],
            [ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
        );

        if ( false !== $result ) {
            $inserted++;
        }
    }

    return $inserted;
}
