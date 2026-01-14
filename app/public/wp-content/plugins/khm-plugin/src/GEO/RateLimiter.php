<?php
/**
 * Rate Limiter
 *
 * Per-user rate limiting for GEO suggestion requests.
 *
 * @package KHM\GEO
 */

namespace KHM\GEO;

defined( 'ABSPATH' ) || exit;

/**
 * Rate Limiter Class
 */
class RateLimiter {

    /**
     * Transient prefix
     *
     * @var string
     */
    const PREFIX = 'khm_geo_rate_';

    /**
     * Default limits
     */
    const DEFAULT_LIMIT_MINUTE = 3;
    const DEFAULT_LIMIT_DAY    = 50;

    /**
     * Check if user is within rate limits
     *
     * @param int $user_id User ID.
     * @return true|\WP_Error True if allowed, WP_Error if rate limited.
     */
    public function check_limit( $user_id ) {
        // Check if rate limiting is disabled
        if ( $this->is_disabled() ) {
            return true;
        }

        // Check if user is exempt (e.g., admins)
        if ( $this->is_exempt( $user_id ) ) {
            return true;
        }

        // Check per-minute limit
        $minute_result = $this->check_window( $user_id, 'minute', 60, $this->get_limit_minute() );
        if ( is_wp_error( $minute_result ) ) {
            return $minute_result;
        }

        // Check per-day limit
        $day_result = $this->check_window( $user_id, 'day', 86400, $this->get_limit_day() );
        if ( is_wp_error( $day_result ) ) {
            return $day_result;
        }

        return true;
    }

    /**
     * Check a specific time window
     *
     * @param int    $user_id User ID.
     * @param string $window  Window name (minute, day).
     * @param int    $ttl     Window duration in seconds.
     * @param int    $limit   Maximum requests allowed.
     * @return true|\WP_Error
     */
    private function check_window( $user_id, $window, $ttl, $limit ) {
        $key     = self::PREFIX . $window . '_' . $user_id;
        $current = (int) get_transient( $key );

        if ( $current >= $limit ) {
            return new \WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    /* translators: 1: limit number, 2: time window */
                    __( 'Rate limit exceeded. Maximum %1$d requests per %2$s. Please wait before trying again.', 'khm-membership' ),
                    $limit,
                    $window
                ),
                array(
                    'status'      => 429,
                    'retry_after' => $this->get_retry_after( $key, $ttl ),
                    'limit_type'  => $window,
                    'current'     => $current,
                    'limit'       => $limit,
                )
            );
        }

        return true;
    }

    /**
     * Increment usage counters
     *
     * @param int $user_id User ID.
     * @return void
     */
    public function increment( $user_id ) {
        if ( $this->is_disabled() || $this->is_exempt( $user_id ) ) {
            return;
        }

        // Increment minute counter
        $minute_key = self::PREFIX . 'minute_' . $user_id;
        $minute_val = (int) get_transient( $minute_key );
        set_transient( $minute_key, $minute_val + 1, 60 );

        // Increment day counter
        $day_key = self::PREFIX . 'day_' . $user_id;
        $day_val = (int) get_transient( $day_key );
        set_transient( $day_key, $day_val + 1, 86400 );
    }

    /**
     * Get usage for a user
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function get_usage( $user_id ) {
        $minute_key = self::PREFIX . 'minute_' . $user_id;
        $day_key    = self::PREFIX . 'day_' . $user_id;

        return array(
            'minute' => array(
                'current' => (int) get_transient( $minute_key ),
                'limit'   => $this->get_limit_minute(),
            ),
            'day'    => array(
                'current' => (int) get_transient( $day_key ),
                'limit'   => $this->get_limit_day(),
            ),
        );
    }

    /**
     * Reset rate limits for a user
     *
     * @param int    $user_id User ID.
     * @param string $window  Window to reset (minute, day, or all).
     * @return void
     */
    public function reset( $user_id, $window = 'all' ) {
        if ( $window === 'all' || $window === 'minute' ) {
            delete_transient( self::PREFIX . 'minute_' . $user_id );
        }
        if ( $window === 'all' || $window === 'day' ) {
            delete_transient( self::PREFIX . 'day_' . $user_id );
        }
    }

    /**
     * Get retry-after value in seconds
     *
     * @param string $key Transient key.
     * @param int    $ttl Original TTL.
     * @return int Seconds until retry allowed.
     */
    private function get_retry_after( $key, $ttl ) {
        $timeout_key = '_transient_timeout_' . $key;
        $timeout     = get_option( $timeout_key );

        if ( $timeout ) {
            $remaining = $timeout - time();
            return max( 1, $remaining );
        }

        return $ttl;
    }

    /**
     * Check if rate limiting is disabled
     *
     * @return bool
     */
    private function is_disabled() {
        if ( defined( 'KHM_GEO_DISABLE_RATE_LIMIT' ) && KHM_GEO_DISABLE_RATE_LIMIT ) {
            return true;
        }

        return (bool) get_option( 'khm_geo_disable_rate_limit', false );
    }

    /**
     * Check if user is exempt from rate limiting
     *
     * @param int $user_id User ID.
     * @return bool
     */
    private function is_exempt( $user_id ) {
        // Check if admins are exempt
        $exempt_admins = get_option( 'khm_geo_rate_limit_exempt_admins', false );
        
        if ( $exempt_admins && user_can( $user_id, 'manage_options' ) ) {
            return true;
        }

        /**
         * Filter to exempt specific users from rate limiting
         *
         * @param bool $exempt  Whether user is exempt.
         * @param int  $user_id User ID.
         */
        return apply_filters( 'khm_geo_rate_limit_exempt', false, $user_id );
    }

    /**
     * Get per-minute limit
     *
     * @return int
     */
    private function get_limit_minute() {
        $env = getenv( 'KHM_GEO_RATE_LIMIT_MINUTE' );
        if ( $env ) {
            return (int) $env;
        }

        if ( defined( 'KHM_GEO_RATE_LIMIT_MINUTE' ) ) {
            return (int) KHM_GEO_RATE_LIMIT_MINUTE;
        }

        $option = get_option( 'khm_geo_rate_limit_minute' );
        if ( $option ) {
            return (int) $option;
        }

        return self::DEFAULT_LIMIT_MINUTE;
    }

    /**
     * Get per-day limit
     *
     * @return int
     */
    private function get_limit_day() {
        $env = getenv( 'KHM_GEO_RATE_LIMIT_DAY' );
        if ( $env ) {
            return (int) $env;
        }

        if ( defined( 'KHM_GEO_RATE_LIMIT_DAY' ) ) {
            return (int) KHM_GEO_RATE_LIMIT_DAY;
        }

        $option = get_option( 'khm_geo_rate_limit_day' );
        if ( $option ) {
            return (int) $option;
        }

        return self::DEFAULT_LIMIT_DAY;
    }
}
