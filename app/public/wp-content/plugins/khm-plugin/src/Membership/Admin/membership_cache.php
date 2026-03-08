<?php

namespace KHM\Membership\Admin;

class MembershipCache {
    private const VERSION_OPTION = 'khm_membership_report_cache_version';
    private const KEY_PREFIX = 'khm_membership_report_';
    private const DEFAULT_TTL = 120;

    /**
     * @param array<string,mixed> $context
     */
    public static function get( string $bucket, array $context = [] ) {
        $key = self::build_key( $bucket, $context );
        return get_transient( $key );
    }

    /**
     * @param array<string,mixed> $context
     * @param mixed $value
     */
    public static function set( string $bucket, array $context, $value, int $ttl = self::DEFAULT_TTL ): bool {
        $ttl = max( 5, (int) apply_filters( 'khm_membership_report_cache_ttl_seconds', $ttl, $bucket, $context ) );
        $key = self::build_key( $bucket, $context );
        return (bool) set_transient( $key, $value, $ttl );
    }

    public static function invalidate_all(): void {
        update_option( self::VERSION_OPTION, self::version() + 1, false );
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function build_key( string $bucket, array $context ): string {
        ksort( $context );
        $encoded = wp_json_encode( $context );
        if ( ! is_string( $encoded ) ) {
            $encoded = '';
        }

        return self::KEY_PREFIX . md5( self::version() . '|' . $bucket . '|' . $encoded );
    }

    private static function version(): int {
        return (int) get_option( self::VERSION_OPTION, 1 );
    }
}
