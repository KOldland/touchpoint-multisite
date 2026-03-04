<?php

namespace KH_SMMA\Adapters;

use function get_option;
use function update_option;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AdapterIdempotencyStore: WP-option-backed idempotency store for paid adapter execute() calls.
 *
 * Prevents duplicate execute() processing for the same manifest idempotency_key.
 * Uses WordPress options with autoload=false so entries don't load on every request.
 *
 * In tests, get_option/update_option are mocked in-memory by TestHelpers, so
 * idempotency is fully exercisable without a database.
 *
 * Option key format: kh_paid_idem_{md5(idempotency_key)}
 *
 * @see docs/contracts/paid_adapter_notes.md
 */
class AdapterIdempotencyStore {

    const OPTION_PREFIX = 'kh_paid_idem_';

    /**
     * Retrieve a cached execute response for the given idempotency key.
     *
     * @param string $key Raw idempotency_key UUID from manifest meta.
     * @return array|null Cached execute response array, or null if not found.
     */
    public function get( string $key ): ?array {
        if ( '' === $key ) {
            return null;
        }

        $stored = get_option( $this->option_name( $key ), null );

        return is_array( $stored ) ? $stored : null;
    }

    /**
     * Store an execute response keyed by idempotency_key.
     *
     * @param string $key      Raw idempotency_key UUID from manifest meta.
     * @param array  $response Execute response array to cache.
     * @return void
     */
    public function store( string $key, array $response ): void {
        if ( '' === $key ) {
            return;
        }

        update_option( $this->option_name( $key ), $response, false );
    }

    /**
     * Build the WP option name for a given idempotency key.
     *
     * @param string $key Raw idempotency_key.
     * @return string
     */
    private function option_name( string $key ): string {
        return self::OPTION_PREFIX . md5( $key );
    }
}
