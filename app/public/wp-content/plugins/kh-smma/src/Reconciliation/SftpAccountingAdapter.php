<?php
/**
 * SFTP Accounting Adapter (Sandbox)
 *
 * Sandbox implementation of AccountingAdapterContract that simulates
 * delivering a settlement ledger via SFTP. No real network connection
 * is made — results are deterministic and safe for CI and staging.
 *
 * In production, a real SFTP adapter would use phpseclib or similar
 * with credentials loaded from CredentialVault at runtime.
 *
 * Sandbox behaviour:
 *  - dry_run()  validates settlement shape and computes CSV checksum.
 *  - execute()  stores the CSV in $GLOBALS['kh_sftp_sandbox'] and returns
 *               a delivery row with status='delivered'.
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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SftpAccountingAdapter extends AccountingAdapterContract {

    const ADAPTER_NAME = 'sftp';
    const VERSION      = '1.0.0';

    public function adapter_name(): string {
        return self::ADAPTER_NAME;
    }

    /**
     * Validate the settlement and compute the CSV checksum without sending.
     *
     * @param array $settlement
     * @param array $opts  Accepts 'simulate_failures' (string) to force error.
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
                    'message' => 'Simulated SFTP failure (test-only).',
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

        $csv      = $this->build_ledger_csv( $settlement );
        $checksum = hash( 'sha256', $csv );

        return [
            'settlement_id'      => $settlement['settlement_id'],
            'adapter'            => self::ADAPTER_NAME,
            'valid'              => true,
            'checksum'           => $checksum,
            'payload_size_bytes' => strlen( $csv ),
            'estimated_ops'      => [ 'generate_csv', 'compute_checksum', 'sftp_connect', 'sftp_upload' ],
            'timestamp'          => $timestamp,
        ];
    }

    /**
     * Deliver the settlement ledger via SFTP (sandbox: stores in $GLOBALS).
     *
     * Idempotent: returns the cached delivery row if one already exists
     * in the idempotency store.
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
                    'code'      => $retryable ? 'sftp_connect_timeout' : 'sftp_auth_error',
                    'message'   => $retryable ? 'SFTP connection timed out.' : 'SFTP authentication failed (permanent).',
                    'retryable' => $retryable,
                ],
                'timestamp'    => $timestamp,
                'adapter_meta' => [ 'adapter' => self::ADAPTER_NAME, 'version' => self::VERSION ],
            ];

            if ( $this->audit_logger ) {
                $this->audit_logger->log( 'paid_adapter.sftp_deliver', [
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

        // ── Sandbox delivery ────────────────────────────────────────────────
        $csv      = $this->build_ledger_csv( $settlement );
        $checksum = hash( 'sha256', $csv );

        // Store in sandbox global (test-accessible).
        if ( ! isset( $GLOBALS['kh_sftp_sandbox'] ) ) {
            $GLOBALS['kh_sftp_sandbox'] = [];
        }
        $GLOBALS['kh_sftp_sandbox'][ $settlement_id ] = $csv;

        $result = [
            'delivery_id'   => $delivery_id,
            'settlement_id' => $settlement_id,
            'adapter'       => self::ADAPTER_NAME,
            'status'        => 'delivered',
            'checksum'      => $checksum,
            'delivered_at'  => $timestamp,
            'error'         => null,
            'timestamp'     => $timestamp,
            'adapter_meta'  => [ 'adapter' => self::ADAPTER_NAME, 'version' => self::VERSION ],
        ];

        // ── Idempotency store ────────────────────────────────────────────────
        if ( $this->idempotency ) {
            $this->idempotency->store( $settlement_id, self::ADAPTER_NAME, $result );
        }

        // ── Audit ────────────────────────────────────────────────────────────
        if ( $this->audit_logger ) {
            $this->audit_logger->log( 'paid_adapter.sftp_deliver', [
                'object_type' => 'settlement',
                'details'     => [
                    'adapter'       => self::ADAPTER_NAME,
                    'settlement_id' => $settlement_id,
                    'delivery_id'   => $delivery_id,
                    'checksum'      => $checksum,
                    'status'        => 'delivered',
                ],
            ] );
        }

        return $result;
    }
}
