<?php
/**
 * Search Orchestrator
 *
 * Routes queries to requested search sources, merges results,
 * deduplicates, sorts, and returns a unified ResultSet.
 *
 * @package KH\Editorial\Search
 */

namespace KH\Editorial\Search;

use KH\Editorial\Search\Interfaces\SearchSourceInterface;
use KH\Editorial\Search\Models\SearchQuery;
use KH\Editorial\Search\Models\ResultSet;
use KH\Editorial\Search\Services\SearchCache;
use KH\Editorial\Search\Services\SearchLogger;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchOrchestrator
 */
class SearchOrchestrator {

    /**
     * Registered search sources.
     *
     * @var SearchSourceInterface[]
     */
    private array $sources = [];

    /**
     * Cache layer instance.
     *
     * @var SearchCache|null
     */
    private ?SearchCache $cache = null;

    /**
     * Logger instance.
     *
     * @var SearchLogger|null
     */
    private ?SearchLogger $logger = null;

    /**
     * Whether to enable caching.
     *
     * @var bool
     */
    private bool $use_cache = true;

    /**
     * Whether to enable logging.
     *
     * @var bool
     */
    private bool $use_logging = true;

    /**
     * Constructor.
     *
     * @param SearchCache|null  $cache  Cache instance. Defaults to new SearchCache().
     * @param SearchLogger|null $logger Logger instance. Defaults to new SearchLogger().
     * @param array             $opts   Options: 'use_cache' (bool), 'use_logging' (bool).
     */
    public function __construct( ?SearchCache $cache = null, ?SearchLogger $logger = null, array $opts = [] ) {
        $this->cache       = $cache ?? new SearchCache();
        $this->logger      = $logger ?? new SearchLogger();
        $this->use_cache   = $opts['use_cache'] ?? true;
        $this->use_logging = $opts['use_logging'] ?? true;
    }

    /**
     * Register a search source.
     *
     * @param SearchSourceInterface $source The source to register.
     * @return void
     */
    public function register_source( SearchSourceInterface $source ): void {
        $this->sources[ $source->name() ] = $source;
    }

    /**
     * Execute a search across all requested sources.
     *
     * Checks cache first. Logs execution time and result counts.
     *
     * @param SearchQuery $query The query to execute.
     * @return ResultSet
     */
    public function search( SearchQuery $query ): ResultSet {
        $start_time = microtime( true );
        $blog_id    = $query->get_blog_id() ?: (int) ( defined( 'BLOG_ID_CURRENT_SITE' ) ? BLOG_ID_CURRENT_SITE : 0 );
        $query_text = $query->get_query();

        // Check cache first.
        if ( $this->use_cache ) {
            $cache_key = $this->cache->build_key(
                $query_text,
                $blog_id,
                [
                    'sources' => $query->get_sources(),
                    'page'    => $query->get_page(),
                    'limit'   => $query->get_limit(),
                    'filters' => $query->get_filters(),
                ]
            );

            $cached = $this->cache->get( $cache_key );
            if ( null !== $cached ) {
                $elapsed_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
                $this->log_query( $query_text, $blog_id, count( $cached['results'] ?? [] ), $elapsed_ms, false, true );
                return ResultSet::from_array( $cached );
            }
        }

        $requested_sources = $query->get_sources();
        $merged = new ResultSet( [], 0, $query->get_page(), $query->get_limit(), 'unified' );

        $source_summary = [];

        foreach ( $this->sources as $name => $source ) {
            // Skip if not requested.
            if ( ! in_array( $name, $requested_sources, true ) ) {
                continue;
            }

            // Skip if source doesn't support this query.
            if ( ! $source->supports( $query ) ) {
                $source_summary[ $name ] = [
                    'status' => 'skipped',
                    'reason' => 'unsupported',
                    'total'  => 0,
                ];
                continue;
            }

            try {
                $result_set = $source->search( $query );
                $merged->merge( $result_set );

                $source_summary[ $name ] = [
                    'status' => 'success',
                    'total'  => $result_set->get_total(),
                ];
            } catch ( \Exception $e ) {
                error_log( '[KH Search] Source "' . $name . '" failed: ' . $e->getMessage() );
                $source_summary[ $name ] = [
                    'status' => 'error',
                    'reason' => $e->getMessage(),
                    'total'  => 0,
                ];
            }
        }

        // Deduplicate across sources (same URL from different sources).
        $merged->deduplicate();

        // Sort by score descending.
        $merged->sort_by_score();

        // Apply pagination on the merged set.
        $offset = $query->get_offset();
        $limit  = $query->get_limit();
        $merged->slice( $offset, $limit );

        // Build the final response with source summary.
        $final = new ResultSet(
            results:  $merged->get_results(),
            total:    $merged->get_total(),
            page:     $query->get_page(),
            per_page: $query->get_limit(),
            source:   'unified',
            aggregations: [
                'source_summary' => $source_summary,
            ]
        );

        // Store in cache if caching is enabled.
        if ( $this->use_cache && isset( $cache_key ) ) {
            $this->cache->set( $cache_key, $final->to_array() );
        }

        // Log the query execution.
        $elapsed_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
        $this->log_query( $query_text, $blog_id, $final->get_total(), $elapsed_ms, false, false );

        return $final;
    }

    /**
     * Log a search query.
     *
     * @param string $query_text     The query text.
     * @param int    $blog_id        The blog ID.
     * @param int    $result_count   Number of results.
     * @param int    $elapsed_ms     Execution time in ms.
     * @param bool   $from_cache     Whether result was served from cache.
     * @param bool   $cached_hit     Whether it was a cache hit (for cached responses).
     * @return void
     */
    private function log_query( string $query_text, int $blog_id, int $result_count, int $elapsed_ms, bool $from_cache = false, bool $cached_hit = false ): void {
        if ( ! $this->use_logging || ! $this->logger ) {
            return;
        }

        $this->logger->log_query(
            query:        $query_text,
            source:       'internal',
            blog_id:      $blog_id,
            result_count: $result_count,
            latency_ms:   $elapsed_ms,
            synthesized:  false,
            success:      true,
            rate_limited: false
        );
    }

    /**
     * Get all registered source names.
     *
     * @return string[]
     */
    public function get_available_sources(): array {
        return array_keys( $this->sources );
    }

    /**
     * Check if a specific source is registered.
     *
     * @param string $name Source name.
     * @return bool
     */
    public function has_source( string $name ): bool {
        return isset( $this->sources[ $name ] );
    }
}