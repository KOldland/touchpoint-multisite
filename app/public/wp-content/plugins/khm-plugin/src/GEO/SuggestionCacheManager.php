<?php
/**
 * Suggestion Cache Manager
 *
 * Handles caching of LLM suggestions using content-hash keys.
 * Supports both WordPress transients and Redis object cache.
 *
 * @package KHM\GEO
 */

namespace KHM\GEO;

defined( 'ABSPATH' ) || exit;

/**
 * Suggestion Cache Manager Class
 */
class SuggestionCacheManager {

    /**
     * Cache key prefix
     *
     * @var string
     */
    const CACHE_PREFIX = 'khm_geo_suggest_';

    /**
     * Default TTL (24 hours)
     *
     * @var int
     */
    private $default_ttl;

    /**
     * Constructor
     */
    public function __construct() {
        $this->default_ttl = $this->get_ttl();
    }

    /**
     * Get configured TTL
     *
     * @return int TTL in seconds.
     */
    private function get_ttl() {
        // Check environment/constant
        $env_ttl = getenv( 'KHM_GEO_CACHE_TTL' );
        if ( $env_ttl ) {
            return (int) $env_ttl;
        }

        if ( defined( 'KHM_GEO_CACHE_TTL' ) ) {
            return (int) KHM_GEO_CACHE_TTL;
        }

        // Check option
        $option_ttl = get_option( 'khm_geo_cache_ttl' );
        if ( $option_ttl ) {
            return (int) $option_ttl;
        }

        // Default: 24 hours
        return 86400;
    }

    /**
     * Generate cache key from content
     *
     * @param string $content   Content to hash.
     * @param int    $max_cards Maximum cards requested.
     * @param string $model     Model version.
     * @return string Cache key.
     */
    public function generate_cache_key( $content, $max_cards = 4, $model = 'gpt-4o-mini' ) {
        // Normalize content for consistent hashing
        $normalized = $this->normalize_content( $content );
        
        // Create hash
        $hash = hash( 'sha256', $normalized );
        
        // Include max_cards and model in key for variation
        return self::CACHE_PREFIX . substr( $hash, 0, 16 ) . "_{$max_cards}_{$model}";
    }

    /**
     * Normalize content for hashing
     *
     * @param string $content Raw content.
     * @return string Normalized content.
     */
    private function normalize_content( $content ) {
        // Remove extra whitespace
        $content = preg_replace( '/\s+/', ' ', $content );
        
        // Trim and lowercase for consistency
        $content = strtolower( trim( $content ) );
        
        return $content;
    }

    /**
     * Get cached response
     *
     * @param string $cache_key Cache key.
     * @return array|false Cached data or false if not found.
     */
    public function get( $cache_key ) {
        // Try object cache first (Redis if available)
        $cached = wp_cache_get( $cache_key, 'khm_geo' );
        
        if ( false !== $cached ) {
            return $cached;
        }

        // Fall back to transients
        $cached = get_transient( $cache_key );
        
        if ( false !== $cached ) {
            return $cached;
        }

        return false;
    }

    /**
     * Set cached response
     *
     * @param string $cache_key Cache key.
     * @param array  $data      Data to cache.
     * @param int    $ttl       Optional TTL override.
     * @return bool
     */
    public function set( $cache_key, $data, $ttl = null ) {
        $ttl = $ttl ?? $this->default_ttl;

        // Store in object cache (Redis if available)
        wp_cache_set( $cache_key, $data, 'khm_geo', $ttl );

        // Also store in transients as fallback
        set_transient( $cache_key, $data, $ttl );

        return true;
    }

    /**
     * Delete cached response
     *
     * @param string $cache_key Cache key.
     * @return bool
     */
    public function delete( $cache_key ) {
        wp_cache_delete( $cache_key, 'khm_geo' );
        delete_transient( $cache_key );

        return true;
    }

    /**
     * Clear all suggestion caches
     *
     * @return int Number of cleared entries.
     */
    public function clear_all() {
        global $wpdb;

        // Clear transients with our prefix
        $count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%',
                '_transient_timeout_' . self::CACHE_PREFIX . '%'
            )
        );

        // Try to flush object cache group if supported
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( 'khm_geo' );
        }

        return $count / 2; // Divide by 2 because we delete both transient and timeout
    }

    /**
     * Check if Redis/object cache is available
     *
     * @return bool
     */
    public function has_object_cache() {
        return wp_using_ext_object_cache();
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function get_stats() {
        global $wpdb;

        $transient_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%'
            )
        );

        return array(
            'cached_entries'   => (int) $transient_count,
            'ttl_seconds'      => $this->default_ttl,
            'ttl_human'        => human_time_diff( 0, $this->default_ttl ),
            'has_object_cache' => $this->has_object_cache(),
        );
    }
}
