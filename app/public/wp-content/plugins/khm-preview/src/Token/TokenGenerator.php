<?php

namespace KHM\Preview\Token;

/**
 * Generates random preview tokens and their secure hashes.
 */
class TokenGenerator {
    /** @var callable */
    private $secret_provider;

    public function __construct( callable $secret_provider = null ) {
        $this->secret_provider = $secret_provider ?: [ $this, 'default_secret' ];
    }

    public function generate( int $length = 32 ): string {
        return bin2hex( random_bytes( $length ) );
    }

    public function hash_token( string $token ): string {
        return hash_hmac( 'sha256', $token, call_user_func( $this->secret_provider ) );
    }

    public function default_secret(): string {
        $option = 'khm_preview_secret';
        $secret = get_option( $option );
        if ( ! $secret ) {
            $secret = wp_generate_password( 64, true, true );
            update_option( $option, $secret );
        }
        return $secret;
    }
}
