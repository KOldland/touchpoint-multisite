<?php
/**
 * Delivery Idempotency Store
 *
 * WP-option-backed idempotency cache for settlement deliveries.
 * Keyed by MD5(settlement_id|adapter) so duplicate delivery calls
 * for the same settlement + adapter pair are skipped transparently.
 *
 * Pattern mirrors AdapterIdempotencyStore (PAID-03).
 *
 * @package KH_SMMA\Reconciliation
 * @see     docs/paid/accounting_integration_runbook.md
 */

namespace KH_SMMA\Reconciliation;

use function get_option;
use function update_option;
use function delete_option;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DeliveryIdempotencyStore {

    const OPTION_PREFIX = 'kh_paid_del_idem_';

    /**
     * Retrieve a cached delivery response.
     *
     * @param string $settlement_id
     * @param string $adapter_name  Adapter slug, e.g. 'sftp'.
     * @return array|null  Cached execute() response, or null if not found.
     */
    public function get( string $settlement_id, string $adapter_name ): ?array {
        if ( '' === $settlement_id || '' === $adapter_name ) {
            return null;
        }

        $stored = get_option( $this->option_name( $settlement_id, $adapter_name ), null );
        return is_array( $stored ) ? $stored : null;
    }

    /**
     * Cache a delivery response.
     *
     * @param string $settlement_id
     * @param string $adapter_name
     * @param array  $response  execute() return value to cache.
     */
    public function store( string $settlement_id, string $adapter_name, array $response ): void {
        if ( '' === $settlement_id || '' === $adapter_name ) {
            return;
        }
        update_option( $this->option_name( $settlement_id, $adapter_name ), $response, false );
    }

    /**
     * Remove a cached delivery response (use before forcing a re-delivery).
     *
     * @param string $settlement_id
     * @param string $adapter_name
     */
    public function invalidate( string $settlement_id, string $adapter_name ): void {
        delete_option( $this->option_name( $settlement_id, $adapter_name ) );
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function option_name( string $settlement_id, string $adapter_name ): string {
        return self::OPTION_PREFIX . md5( $settlement_id . '|' . $adapter_name );
    }
}
