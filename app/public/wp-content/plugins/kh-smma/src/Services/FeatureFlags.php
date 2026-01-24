<?php
namespace KH_SMMA\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FeatureFlags {
    const OPTION_KEY = 'kh_smma_feature_flags';

    /**
     * Default flags for SMMA features.
     *
     * @return array
     */
    public function get_defaults(): array {
        return array(
            'smma' => false,
            'smma_paid_adapters' => false,
        );
    }

    /**
     * Get normalized flags.
     *
     * @return array
     */
    public function get_flags(): array {
        $defaults = $this->get_defaults();
        $stored   = get_option( self::OPTION_KEY, array() );

        if ( ! is_array( $stored ) || empty( $stored ) ) {
            return $defaults;
        }

        $normalized = array();
        foreach ( $defaults as $key => $default ) {
            if ( array_key_exists( $key, $stored ) ) {
                $normalized[ $key ] = (bool) $stored[ $key ];
                continue;
            }

            $normalized[ $key ] = in_array( $key, $stored, true );
        }

        return $normalized;
    }

    /**
     * Check if a feature is enabled.
     *
     * @param string $flag
     * @return bool
     */
    public function is_enabled( string $flag ): bool {
        $flags = $this->get_flags();
        return ! empty( $flags[ $flag ] );
    }

    /**
     * Ensure defaults are persisted on activation.
     */
    public function ensure_defaults(): void {
        if ( false === get_option( self::OPTION_KEY, false ) ) {
            update_option( self::OPTION_KEY, $this->get_defaults() );
        }
    }
}
