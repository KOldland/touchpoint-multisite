<?php

namespace KHM\Membership;

class TierRegistry {
    /**
     * Resolve registry entry for a tier slug.
     *
     * @return array<string,mixed>|null
     */
    public static function get_tier( string $tier_slug ): ?array {
        $tier_slug = sanitize_key( $tier_slug );
        if ( '' === $tier_slug ) {
            return null;
        }

        $registry = self::get_registry();
        if ( ! isset( $registry[ $tier_slug ] ) || ! is_array( $registry[ $tier_slug ] ) ) {
            return null;
        }

        $entry = $registry[ $tier_slug ];
        $price_id = self::resolve_price_id_from_entry( $entry );
        if ( '' === $price_id ) {
            return null;
        }

        return [
            'slug' => $tier_slug,
            'price_id' => $price_id,
            'billing_interval' => isset( $entry['billing_interval'] ) ? sanitize_key( (string) $entry['billing_interval'] ) : 'month',
            'trial_days' => isset( $entry['trial_days'] ) ? max( 0, (int) $entry['trial_days'] ) : 0,
            'trial_eligible' => ! empty( $entry['trial_eligible'] ),
            'credit_allowance' => isset( $entry['credit_allowance'] ) ? (int) $entry['credit_allowance'] : 0,
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function get_registry(): array {
        $default = [];
        $option = get_option( 'khm_membership_tier_registry', [] );
        if ( is_array( $option ) ) {
            $default = $option;
        }

        $registry = apply_filters( 'khm_membership_tier_registry', $default );
        return is_array( $registry ) ? $registry : $default;
    }

    public static function validate_price_match( string $tier_slug, string $price_id ): bool {
        $tier = self::get_tier( $tier_slug );
        if ( ! $tier ) {
            return false;
        }

        return (string) $tier['price_id'] === trim( $price_id );
    }

    public static function find_tier_by_price( string $price_id ): ?array {
        $needle = trim( $price_id );
        if ( '' === $needle ) {
            return null;
        }

        foreach ( self::get_registry() as $slug => $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }
            $resolved = self::resolve_price_id_from_entry( $entry );
            if ( $resolved !== '' && $resolved === $needle ) {
                $tier = self::get_tier( (string) $slug );
                if ( $tier ) {
                    return $tier;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function resolve_price_id_from_entry( array $entry ): string {
        $mode = self::is_live_mode() ? 'live' : 'test';
        if ( isset( $entry['prices'] ) && is_array( $entry['prices'] ) && isset( $entry['prices'][ $mode ] ) ) {
            return sanitize_text_field( (string) $entry['prices'][ $mode ] );
        }

        if ( isset( $entry[ $mode . '_price_id' ] ) ) {
            return sanitize_text_field( (string) $entry[ $mode . '_price_id' ] );
        }

        if ( isset( $entry['price_id'] ) ) {
            return sanitize_text_field( (string) $entry['price_id'] );
        }

        return '';
    }

    private static function is_live_mode(): bool {
        $secret = (string) get_option( 'khm_stripe_secret_key', '' );
        return strpos( $secret, 'sk_live_' ) === 0;
    }
}
