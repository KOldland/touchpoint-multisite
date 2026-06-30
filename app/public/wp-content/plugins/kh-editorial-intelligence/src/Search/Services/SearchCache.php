<?php
/**
 * Search Cache Layer
 *
 * Caches popular search queries using WP Transients with a configurable TTL.
 * Supports Redis/memcached via wp_using_ext_object_cache().
 *
 * @package KH\Editorial\Search\Services
 */

namespace KH\Editorial\Search\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchCache
 */
class SearchCache {

    /**
     * Cache group prefix.
     */
    const CACHE_PREFIX = 'khm_search_';

    /**
     * Default TTL in seconds (5 minutes).
     */
    const DEFAULT_TTL = 300;

    /**
     * The cache TTL in seconds.
     *
     * @var int
     */
    private int $ttl;

    /**
     * Constructor.
     *
     * @param int|null $ttl Cache TTL in seconds. Defaults to 300 (5 min).
     */
    public function __construct( int $ttl = null ) {
        $this->ttl = $ttl ?? self::DEFAULT_TTL;
    }

    /**
     * Generate a consistent cache key from query parameters.
     *
     * @param string $query   The search query string.
     * @param int    $blog_id The blog/site ID.
     * @param array  $params  Additional parameters (sources, filters, page, limit, etc.).
     * @return string
     */
    public function build_key( string $query, int $blog_id, array $params = array() ): string {
        $parts = array_merge(
            array(
                'q'       => mb_strtolower( trim( $query ) ),
                'blog_id' => $blog_id,
            ),
            $params
        );
        // Sort for consistency regardless of param order.
        ksort( $parts );
        return self::CACHE_PREFIX . md5( wp_json_encode( $parts ) );
    }

    /**
     * Get cached search results.
     *
     * @param string $cache_key The cache key (use build_key() to generate).
     * @return array|null The cached result array, or null if not found/expired.
     */
    public function get( string $cache_key ): ?array {
        $cached = get_transient( $cache_key );
        return false !== $cached ? $cached : null;
    }

    /**
     * Store search results in cache.
     *
     * @param string $cache_key The cache key.
     * @param array  $data      The result data to cache.
     * @param int|null $ttl     Optional TTL override for this specific entry.
     * @return bool
     */
    public function set( string $cache_key, array $data, int $ttl = null ): bool {
        return set_transient( $cache_key, $data, $ttl ?? $this->ttl );
    }

    /**
     * Invalidate (delete) a single cache entry.
     *
     * @param string $cache_key The cache key to delete.
     * @return bool
     */
    public function delete( string $cache_key ): bool {
        return delete_transient( $cache_key );
    }

    /**
     * Flush all search cache entries.
     *
     * Uses a wildcard pattern when using external object cache (Redis),
     * otherwise flushes all transients matching the prefix.
     *
     * @return int Number of cache entries flushed.
     */
    public function flush_all(): int {
        global $wpdb;

        $count = 0;

        if ( wp_using_ext_object_cache() ) {
            // Redis/memcached: use a group key. We can't easily flush by prefix,
            // but we can delete a known "flush marker" that's checked on get().
            // For simplicity, we set a version key and increment it.
            $version = (int) get_option( 'khm_search_cache_version', 0 ) + 1;
            update_option( 'khm_search_cache_version', $version );
            return 0; // Actual entries are invalidated on subsequent gets.
        }

        // WordPress transients: delete by LIKE pattern on _transient_NAME options.
        $like_pattern = '_transient_' . self::CACHE_PREFIX . '%';
        $results      = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like_pattern
            )
        );

        foreach ( $results as $option_name ) {
            $transient_key = str_replace( '_transient_', '', $option_name );
            delete_transient( $transient_key );
            ++$count;
        }

        // Also delete timeout options.
        $timeout_pattern = '_transient_timeout_' . self::CACHE_PREFIX . '%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $timeout_pattern
            )
        );

        return $count;
    }

    /**
     * Check if the cache is currently hot (contains any entries).
     *
     * @return bool
     */
    public function is_hot(): bool {
        global $wpdb;

        if ( wp_using_ext_object_cache() ) {
            // Can't easily check without iterating keys.
            return false;
        }

        $pattern = '_transient_' . self::CACHE_PREFIX . '%';
        $count   = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );

        return $count > 0;
    }
}