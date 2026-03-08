<?php
/**
 * Google Sandbox Adapter
 *
 * Deterministic, offline implementation of PaidAdapterContract for Google Ads.
 * Produces identical output for identical inputs — safe for CI/CIC golden tests.
 *
 * Behaviour rules:
 * - No network calls. Zero HTTP requests to any external API.
 * - dry_run() and execute() are repeatable: same manifest + idempotency_key → same response.
 * - Randomness comes only from DeterministicRng (seeded SHA-256 + deterministic delta).
 * - Partial failures are supported via manifest.meta.simulate_failures (test-only key).
 * - execute() is idempotent: duplicate calls return cached response from AdapterIdempotencyStore.
 * - Audit events are emitted for dry_run and execute when AuditLogger is injected.
 *
 * @package KH_SMMA\Adapters
 * @see docs/paid/sandbox_adapter.md
 * @see docs/contracts/paid_adapter_manifest.json
 * @see docs/contracts/paid_adapter_execute.json
 */

namespace KH_SMMA\Adapters;

use KH_SMMA\Helpers\DeterministicRng;
use KH_SMMA\Services\AuditLogger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GoogleSandboxAdapter implements PaidAdapterContract {

    const ADAPTER_NAME    = 'google_sandbox';
    const ADAPTER_VERSION = '1.0.0';

    /** @var AuditLogger|null */
    private $audit_logger;

    /** @var AdapterIdempotencyStore|null */
    private $idempotency;

    /**
     * @param AuditLogger|null            $audit_logger Optional audit logger.
     * @param AdapterIdempotencyStore|null $idempotency  Optional idempotency store.
     */
    public function __construct(
        ?AuditLogger $audit_logger = null,
        ?AdapterIdempotencyStore $idempotency = null
    ) {
        $this->audit_logger = $audit_logger;
        $this->idempotency  = $idempotency;
    }

    /**
     * {@inheritdoc}
     *
     * Simulates Google Ads campaign creation deterministically.
     * Estimated spend = bid.amount × duration_days per operation.
     * Confidence is fixed at 0.91 for Google (higher predictability than LinkedIn).
     */
    public function dry_run( array $manifest, array $opts = [] ): array {
        $manifest_id  = $manifest['manifest_id'] ?? '';
        $idem_key     = $manifest['meta']['idempotency_key'] ?? '';
        $currency     = 'AUD';
        $total        = 0.0;
        $ops          = [];

        foreach ( $manifest['operations'] ?? [] as $op ) {
            $op_id     = $op['operation_id'] ?? '';
            $bid       = $op['bid'] ?? [];
            $currency  = $bid['currency'] ?? $currency;
            $estimated = $this->estimate_from_bid( $bid, $op['start_time'] ?? null, $op['end_time'] ?? null );
            $total    += $estimated;

            $ops[] = [
                'operation_id'    => $op_id,
                'estimated_ops'   => [ 'create_campaign', 'create_adgroup', 'create_ads', 'add_keywords' ],
                'estimated_spend' => $estimated,
                'currency'        => $currency,
                'confidence'      => 0.91,
            ];
        }

        $response = [
            'manifest_id'           => $manifest_id,
            'operations'            => $ops,
            'total_estimated_spend' => $total,
            'currency'              => $currency,
            'timestamp'             => gmdate( 'Y-m-d\\TH:i:s\\Z' ),
        ];

        if ( $this->audit_logger ) {
            $this->audit_logger->log( 'paid_adapter.dry_run', [
                'object_type' => 'manifest',
                'details'     => [
                    'adapter'         => self::ADAPTER_NAME,
                    'manifest_id'     => $manifest_id,
                    'estimated_spend' => $total,
                    'currency'        => $currency,
                    'sponsor_id'      => $manifest['meta']['sponsor_id'] ?? null,
                    'operation_count' => count( $ops ),
                ],
            ] );
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     *
     * Executes the manifest deterministically (sandbox mode — no real API calls).
     * operation_id_on_channel is derived from DeterministicRng::seed().
     * actual_spend = round(estimated × (1 + delta), 2) using seeded delta.
     *
     * Supports manifest.meta.simulate_failures map (test-only) to force
     * specific operation_ids to fail with configurable retryable flag.
     */
    public function execute( array $manifest, array $opts = [] ): array {
        $manifest_id = $manifest['manifest_id'] ?? '';
        $idem_key    = $manifest['meta']['idempotency_key'] ?? '';
        $simulate    = $manifest['meta']['simulate_failures'] ?? [];

        // Idempotency: return cached response if already processed.
        if ( '' !== $idem_key && $this->idempotency ) {
            $cached = $this->idempotency->get( $idem_key );
            if ( null !== $cached ) {
                return $cached;
            }
        }

        $currency          = 'AUD';
        $total_actual      = 0.0;
        $operation_results = [];
        $has_failure       = false;

        foreach ( $manifest['operations'] ?? [] as $op ) {
            $op_id     = $op['operation_id'] ?? '';
            $bid       = $op['bid'] ?? [];
            $currency  = $bid['currency'] ?? $currency;
            $estimated = $this->estimate_from_bid( $bid, $op['start_time'] ?? null, $op['end_time'] ?? null );

            $seed             = DeterministicRng::seed( $manifest_id, $op_id, $idem_key, self::ADAPTER_NAME );
            $op_id_on_channel = DeterministicRng::operation_id( $seed, 'g_op' );
            $actual           = round( $estimated * ( 1 + DeterministicRng::delta( $seed ) ), 2 );

            // simulate_failures: test-only; key = operation_id, value = retryable bool.
            if ( isset( $simulate[ $op_id ] ) ) {
                $has_failure         = true;
                $operation_results[] = [
                    'operation_id'            => $op_id,
                    'operation_id_on_channel' => null,
                    'result'                  => 'failed',
                    'actual_spend'            => 0.0,
                    'currency'                => $currency,
                    'error'                   => [
                        'code'      => 'simulated_failure',
                        'message'   => 'Simulated failure for testing.',
                        'retryable' => (bool) $simulate[ $op_id ],
                    ],
                ];
            } else {
                $total_actual       += $actual;
                $operation_results[] = [
                    'operation_id'            => $op_id,
                    'operation_id_on_channel' => $op_id_on_channel,
                    'result'                  => 'created',
                    'actual_spend'            => $actual,
                    'currency'                => $currency,
                    'error'                   => null,
                ];
            }
        }

        $status = $has_failure ? 'partial_success' : 'success';

        $response = [
            'manifest_id'        => $manifest_id,
            'status'             => $status,
            'operation_results'  => $operation_results,
            'total_actual_spend' => $total_actual,
            'currency'           => $currency,
            'errors'             => null,
            'timestamp'          => gmdate( 'Y-m-d\\TH:i:s\\Z' ),
            'adapter_meta'       => [
                'adapter' => self::ADAPTER_NAME,
                'version' => self::ADAPTER_VERSION,
            ],
        ];

        if ( $this->audit_logger ) {
            $this->audit_logger->log( 'paid_adapter.execute', [
                'object_type' => 'manifest',
                'details'     => [
                    'adapter'         => self::ADAPTER_NAME,
                    'manifest_id'     => $manifest_id,
                    'estimated_spend' => null,
                    'actual_spend'    => $total_actual,
                    'currency'        => $currency,
                    'sponsor_id'      => $manifest['meta']['sponsor_id'] ?? null,
                    'idempotency_key' => $idem_key,
                    'status'          => $status,
                    'operation_count' => count( $operation_results ),
                ],
            ] );
        }

        // Store for idempotency.
        if ( '' !== $idem_key && $this->idempotency ) {
            $this->idempotency->store( $idem_key, $response );
        }

        return $response;
    }

    /**
     * Adapter metadata.
     *
     * @return array
     */
    public function get_metadata(): array {
        return [
            'name'         => 'Google Sandbox',
            'version'      => self::ADAPTER_VERSION,
            'adapter_name' => self::ADAPTER_NAME,
            'capabilities' => [
                'dry_run'           => true,
                'execute'           => true,
                'idempotency'       => true,
                'partial_failure'   => true,
                'simulate_failures' => true,
                'network_calls'     => false,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Estimate spend from a bid object and optional campaign window.
     *
     * @param array       $bid        Bid array with amount and currency.
     * @param string|null $start_time ISO 8601 start time.
     * @param string|null $end_time   ISO 8601 end time.
     * @return float
     */
    private function estimate_from_bid( array $bid, ?string $start_time, ?string $end_time ): float {
        $amount = (float) ( $bid['amount'] ?? 0 );

        if ( $start_time && $end_time ) {
            $start = strtotime( $start_time );
            $end   = strtotime( $end_time );
            if ( $start && $end && $end > $start ) {
                $days = (int) ceil( ( $end - $start ) / 86400 );
                return $amount * (float) $days;
            }
        }

        return $amount;
    }
}
