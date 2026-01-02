<?php
namespace KH_SMMA\Security;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simple encryption/decryption helper for storing provider tokens.
 */
class CredentialVault {
    /**
     * @var string
     */
    private $key;

    public function __construct( $key = null ) {
        $salt    = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
        $this->key = substr( hash( 'sha256', ( $key ?: $salt ) ), 0, 32 );
    }

    public function encrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $iv = openssl_random_pseudo_bytes( 16 );
        if ( ! $iv ) {
            throw new RuntimeException( 'Unable to generate IV for encryption.' );
        }

        $cipher = openssl_encrypt( wp_json_encode( $value ), 'aes-256-ctr', $this->key, OPENSSL_RAW_DATA, $iv );
        if ( false === $cipher ) {
            throw new RuntimeException( 'Token encryption failed.' );
        }

        return base64_encode( $iv . $cipher );
    }

    public function decrypt( $encoded ) {
        if ( empty( $encoded ) ) {
            return null;
        }

        $data = base64_decode( $encoded );
        if ( false === $data || strlen( $data ) < 17 ) {
            return null;
        }

        $iv     = substr( $data, 0, 16 );
        $cipher = substr( $data, 16 );
        $plain  = openssl_decrypt( $cipher, 'aes-256-ctr', $this->key, OPENSSL_RAW_DATA, $iv );

        if ( false === $plain ) {
            return null;
        }

        $decoded = json_decode( $plain, true );

        return $decoded ? $decoded : null;
    }
}
