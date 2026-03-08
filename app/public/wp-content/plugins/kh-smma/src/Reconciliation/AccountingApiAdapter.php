<?php
/**
 * Accounting API Adapter (Sandbox)
 *
 * Sandbox implementation of AccountingAdapterContract that simulates
 * delivering a settlement ledger via a REST accounting API. No real
 * HTTP request is made — results are deterministic and safe for CI.
 *
 * In production, a real API adapter would call the accounting endpoint
 * with an API key loaded from CredentialVault at runtime.
 *
 * Sandbox behaviour:
 *  - dry_run()  validates settlement shape and computes JSON payload checksum.
 *  - execute()  stores the payload in $GLOBALS['kh_api_sandbox'] and returns
 *               a delivery row with status='delivered' and a receipt_id.
 *  - Pass opts['simulate_failures'] = 'transient' or 'permanent' to force
 *    error paths in tests.
 *
 * @package KH_SMMA\Reconciliation
 * @see     docs/paid/accounting_integration_runbook.md
 * @see     docs/contracts/paid_delivery.json
 */

namespace KH_SMMA\Reconciliation;

use KH_SMMA\Services\AuditLogger;

use function current_time;
use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AccountingApiAdapter extends AccountingAdapterContract {

    const ADAPTER_NAME = 'accounting_api';
    const VERSION      = '1.0.0';

    public function adapter_name(): string {
        return self::ADAPTER_NAME;
    }

    /**
     * Validate the settlement and compute the JSON payload checksum.
     *
     * @param array $settlement
     * @param array $opts  Accepts 'simulate_failures' to force error path.
     * @return array
     */
    public function dry_run( array $settlement, array $opts = [] ): array {
        $timestamp = date( 'Y-m-d\TH:i:s\Z' );

        if ( ! empty( $opts['simulate_failures'] ) ) {
            return [
                'settlement_id'      => $settlement['settlement_id'] ?? '',
                'adapter'            => self::ADAPTER_NAME,
                'valid'              => false,
                'checksum'           => null,
                'payload_size_bytes' => 0,
                'estimated_ops'      => [],
                'timestamp'          => $timestamp,
                'error'              => [
                    'code'    => 'simulated_failure',
                    'message' => 'Simulated API failure (test-only).',
                ],
            ];
        }

        if ( ! $this->is_valid_settlement( $settlement ) ) {
            return [
                'settlement_id'      => $settlement['settlement_id'] ?? '',
                'adapter'            => self::ADAPTER_NAME,
                'valid'              => false,
                'checksum'           => null,
                'payload_size_bytes' => 0,
                'estimated_ops'      => [],
                'timestamp'          => $timestamp,
                'error'              => [
                    'code'    => 'invalid_settlement',
                    'message' => 'Settlement missing required fields.',
                ],
            ];
        }

        $payload  = $this->build_json_payload( $settlement );
        $checksum = hash( 'sha256', $payload );

        return [
            'settlement_id'      => $settlement['settlement_id'],
            'adapter'            => self::ADAPTER_NAME,
            'valid'              => true,
            'checksum'           => $checksum,
            'payload_size_bytes' => strlen( $payload ),
            'estimated_ops'      => [ 'validate_payload', 'compute_checksum', 'api_post' ],
            'timestamp'          => $timestamp,
        ];
    }

    /**
     * Deliver the settlement ledger via REST API (sandbox: stores in $GLOBALS).
     *
     * Idempotent: returns the cached delivery row if one already exists.
     *
     * @param array $settlement
     * @param array $opts  Accepts 'simulate_failures' = 'transient'|'permanent'.
     * @return array  Delivery row.
     */
    public function execute( array $settlement, array $opts = [] ): array {
        $settlement_id = $settlement['settlement_id'] ?? '';
        $now           = current_time( 'mysql' );
        $timestamp     = date( 'Y-m-d\TH:i:s\Z' );

        // ── Idempotency check ───────────────────────────────────────────────
        if ( $this->idempotency ) {
            $cached = $this->idempotency->get( $settlement_id, self::ADAPTER_NAME );
            if ( null !== $cached ) {
                return $cached;
            }
        }

        $delivery_id = 'del_' . substr(
            hash( 'sha256', $settlement_id . '|' . self::ADAPTER_NAME . '|' . $now ),
            0, 12
        );

        // ── Simulate failure paths (test-only) ──────────────────────────────
        $simulate = $opts['simulate_failures'] ?? null;
        if ( null !== $simulate ) {
            $retryable = ( 'transient' === $simulate );
            $result    = [
                'delivery_id'   => $delivery_id,
                'settlement_id' => $settlement_id,
                'adapter'       => self::ADAPTER_NAME,
                'status'        => 'failed',
                'checksum'      => null,
                'delivered_at'  => null,
                'error'         => [
                    'code'      => $retryable ? 'api_timeout' : 'api_auth_error',
                    'message'   => $retryable ? 'Accounting API timed out.' : 'API authentication rejected (permanent).',
                    'retryable' => $retryable,
                ],
                'timestamp'    => $timestamp,
                'adapter_meta' => [ 'adapter' => self::ADAPTER_NAME, 'version' => self::VERSION ],
            ];

            if ( $this->audit_logger ) {
                $this->audit_logger->log( 'paid_adapter.api_deliver', [
                    'object_type' => 'settlement',
                    'details'     => [
                        'adapter'       => self::ADAPTER_NAME,
                        'settlement_id' => $settlement_id,
                        'status'        => 'failed',
                        'retryable'     => $retryable,
                    ],
                ] );
            }

            return $result;
        }

        // ── Sandbox delivery ─────────────────────────────────────────────────
        $payload    = $this->build_json_payload( $settlement );
        $checksum   = hash( 'sha256', $payload );
        $receipt_id = 'rcpt_' . substr(
            hash( 'sha256', $settlement_id . '|' . self::ADAPTER_NAME . '|receipt|' . $now ),
            0, 12
        );

        // Store in sandbox global (test-accessible).
        if ( ! isset( $GLOBALS['kh_api_sandbox'] ) ) {
            $GLOBALS['kh_api_sandbox'] = [];
        }
        $GLOBALS['kh_api_sandbox'][ $settlement_id ] = $payload;

        $result = [
            'delivery_id'   => $delivery_id,
            'settlement_id' => $settlement_id,
            'adapter'       => self::ADAPTER_NAME,
            'status'        => 'delivered',
            'checksum'      => $checksum,
            'delivered_at'  => $timestamp,
            'error'         => null,
            'timestamp'     => $timestamp,
            'adapter_meta'  => [
                'adapter'    => self::ADAPTER_NAME,
                'version'    => self::VERSION,
                'receipt_id' => $receipt_id,
            ],
        ];

        // ── Idempotency store ────────────────────────────────────────────────
        if ( $this->idempotency ) {
            $this->idempotency->store( $settlement_id, self::ADAPTER_NAME, $result );
        }

        // ── Audit ─────────────────────────────────────────────────────────────
        if ( $this->audit_logger ) {
            $this->audit_logger->log( 'paid_adapter.api_deliver', [
                'object_type' => 'settlement',
                'details'     => [
                    'adapter'       => self::ADAPTER_NAME,
                    'settlement_id' => $settlement_id,
                    'delivery_id'   => $delivery_id,
                    'checksum'      => $checksum,
                    'receipt_id'    => $receipt_id,
                    'status'        => 'delivered',
                ],
            ] );
        }

        return $result;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Build the JSON payload sent to the accounting API.
     *
     * @param array $settlement
     * @return string  JSON string.
     */
    private function build_json_payload( array $settlement ): string {
        $keys = [
            'settlement_id', 'sponsor_id', 'currency', 'total_settled',
            'fx_rate', 'settled_at', 'reconciliation_ids', 'batch_size',
        ];

        $payload = [];
        foreach ( $keys as $k ) {
            $payload[ $k ] = $settlement[ $k ] ?? null;
        }

        return wp_json_encode( $payload );
    }
}
