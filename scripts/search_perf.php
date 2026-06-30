<?php
/**
 * Search Performance Benchmark Script
 *
 * Tests RAG + FULLTEXT search latency against configurable data volumes.
 *
 * Usage:
 *   wp eval-file search_perf.php [--iterations=10] [--embeddings=1000]
 *
 * @package KH\Editorial\Benchmarks
 */

if ( ! defined( 'ABSPATH' ) ) {
    // Allow CLI usage outside WordPress context.
    if ( 'cli' !== php_sapi_name() ) {
        exit;
    }
}

// Default parameters.
$iterations    = isset( $argv[1] ) ? (int) $argv[1] : 10;
$embed_count   = isset( $argv[3] ) ? (int) $argv[3] : 1000;

$test_queries = array(
    'trade finance trends',
    'supply chain disruption',
    'ESG reporting standards',
    'interest rate outlook',
    'commodity price forecast',
);

echo "=== KH Search Performance Benchmark ===\n";
echo "Iterations per query: {$iterations}\n";
echo "Test queries: " . count( $test_queries ) . "\n\n";

// ------- RAG Query Latency -------
echo "--- RAG (Semantic) Search ---\n";
$rag_latencies = array();

for ( $q = 0; $q < count( $test_queries ); $q++ ) {
    $query_text = $test_queries[ $q ];
    $times      = array();

    for ( $i = 0; $i < $iterations; $i++ ) {
        $start = microtime( true );

        try {
            $rag_source = new \KH\Editorial\Search\Sources\RAGSource();
            $search_query = new \KH\Editorial\Search\Models\SearchQuery(
                query:   $query_text,
                sources: [ 'rag' ],
                limit:   10,
                page:    1
            );
            $result = $rag_source->search( $search_query );
        } catch ( \Exception $e ) {
            echo "  [ERROR] Query '{$query_text}' iteration {$i}: {$e->getMessage()}\n";
            continue;
        }

        $elapsed = ( microtime( true ) - $start ) * 1000;
        $times[] = $elapsed;
    }

    if ( count( $times ) > 0 ) {
        $avg  = array_sum( $times ) / count( $times );
        sort( $times );
        $p95  = $times[ (int) floor( 0.95 * count( $times ) ) ];
        $rag_latencies[ $query_text ] = array(
            'avg_ms' => round( $avg, 1 ),
            'p95_ms' => round( $p95, 1 ),
        );
        printf( "  %-35s  avg: %6.1f ms   p95: %6.1f ms\n", substr( $query_text, 0, 35 ), $avg, $p95 );
    }
}

// ------- FULLTEXT Query Latency -------
echo "\n--- FULLTEXT (Content Registry) Search ---\n";
$ft_latencies = array();

for ( $q = 0; $q < count( $test_queries ); $q++ ) {
    $query_text = $test_queries[ $q ];
    $times      = array();

    for ( $i = 0; $i < $iterations; $i++ ) {
        $start = microtime( true );

        try {
            $registry = new \KH\Editorial\Search\Sources\RegistrySource();
            $search_query = new \KH\Editorial\Search\Models\SearchQuery(
                query:   $query_text,
                sources: [ 'registry' ],
                limit:   10,
                page:    1
            );
            $result = $registry->search( $search_query );
        } catch ( \Exception $e ) {
            echo "  [ERROR] Query '{$query_text}' iteration {$i}: {$e->getMessage()}\n";
            continue;
        }

        $elapsed = ( microtime( true ) - $start ) * 1000;
        $times[] = $elapsed;
    }

    if ( count( $times ) > 0 ) {
        $avg = array_sum( $times ) / count( $times );
        sort( $times );
        $p95 = $times[ (int) floor( 0.95 * count( $times ) ) ];
        $ft_latencies[ $query_text ] = array(
            'avg_ms' => round( $avg, 1 ),
            'p95_ms' => round( $p95, 1 ),
        );
        printf( "  %-35s  avg: %6.1f ms   p95: %6.1f ms\n", substr( $query_text, 0, 35 ), $avg, $p95 );
    }
}

// ------- Summary -------
echo "\n--- Summary ---\n";

$rag_avg   = ! empty( $rag_latencies ) ? array_sum( array_column( $rag_latencies, 'avg_ms' ) ) / count( $rag_latencies ) : 0;
$ft_avg    = ! empty( $ft_latencies ) ? array_sum( array_column( $ft_latencies, 'avg_ms' ) ) / count( $ft_latencies ) : 0;
$rag_p95   = ! empty( $rag_latencies ) ? max( array_column( $rag_latencies, 'p95_ms' ) ) : 0;
$ft_p95    = ! empty( $ft_latencies ) ? max( array_column( $ft_latencies, 'p95_ms' ) ) : 0;

echo "  Source               Avg (ms)    P95 (ms)\n";
echo "  RAG (semantic)       " . str_pad( round( $rag_avg, 1 ), 10, ' ', STR_PAD_LEFT ) . "    " . str_pad( round( $rag_p95, 1 ), 8, ' ', STR_PAD_LEFT ) . "\n";
echo "  FULLTEXT (registry)  " . str_pad( round( $ft_avg, 1 ), 10, ' ', STR_PAD_LEFT ) . "    " . str_pad( round( $ft_p95, 1 ), 8, ' ', STR_PAD_LEFT ) . "\n";

echo "\nBenchmark complete. {$iterations} iterations per query.\n";