<?php
/**
 * Manual Export Adapter (KHM)
 *
 * Paid adapter that queues operations for manual ops processing within the
 * khm-plugin ecosystem. Implements PaidAdapterContract and uses the existing
 * DatabaseIdempotencyStore for DB-backed idempotency.
 *
 * Returns status "awaiting_manual_export" from execute(), signalling that
 * the manifest bundle has been stored and is pending manual execution.
 *
 * @package KHM\Adapters
 * @see docs/contracts/paid_adapter_manifest.json
 * @see docs/contracts/paid_adapter_execute.json
 * @see docs/contracts/paid_adapter_notes.md
 */

namespace KHM\Adapters;

use KHM\Contracts\IdempotencyStoreInterface;

class ManualExportAdapter implements PaidAdapterContract {

    /** @var IdempotencyStoreInterface */
    private $idempotency;

    /**
     * @param IdempotencyStoreInterface $idempotency DB-backed idempotency store.
     *                                               Use DatabaseIdempotencyStore in production.
     */
    public function __construct( IdempotencyStoreInterface $idempotency ) {
        $this->idempotency = $idempotency;
    }

    /**
     * {@inheritdoc}
     *
     * Produces a deterministic dry-run response from the manifest.
     * Estimated spend per operation = bid.amount x campaign duration in days.
     * Confidence is 1.0 (manual export is fully predictable).
     */
    public function dry_run( array $manifest, array $opts = [] ): array {
        $ops      = [];
        $total    = 0.0;
        $currency = 'AUD';

        foreach ( $manifest['operations'] ?? [] as $op ) {
            $bid      = $op['bid'] ?? [];
            $currency = $bid['currency'] ?? $currency;
            $est      = $this->estimate_from_bid( $bid, $op['start_time'] ?? null, $op['end_time'] ?? null );
            $total   += $est;

            $ops[] = [
                'operation_id'    => $op['operation_id'],
                'estimated_ops'   => [ 'manual_export' ],
                'estimated_spend' => $est,
                'currency'        => $currency,
                'confidence'      => 1.0,
            ];
        }

        return [
            'manifest_id'           => $manifest['manifest_id'] ?? '',
            'operations'            => $ops,
            'total_estimated_spend' => $total,
            'currency'              => $currency,
            'timestamp'             => gmdate( 'Y-m-d\TH:i:s\Z' ),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Stores manifest bundle as idempotency metadata and returns
     * status "awaiting_manual_export". Idempotent: repeated calls with the
     * same meta.idempotency_key return the same cached response.
     */
    public function execute( array $manifest, array $opts = [] ): array {
        $key = $manifest['meta']['idempotency_key'] ?? '';

        // Idempotency check via DB store — return cached response if already processed.
        if ( '' !== $key && $this->idempotency->hasProcessed( $key ) ) {
            $event = $this->idempotency->getProcessedEvent( $key );
            if ( is_array( $event ) && isset( $event['metadata'] ) && is_array( $event['metadata'] ) ) {
                return $event['metadata'];
            }
        }

        $dry       = $this->dry_run( $manifest );
        $estimated = $dry['total_estimated_spend'];
        $currency  = $dry['currency'];

        $response = [
            'manifest_id'           => $manifest['manifest_id'] ?? '',
            'status'                => 'awaiting_manual_export',
            'package_url'           => 'khm_bundle:' . ( $manifest['manifest_id'] ?? '' ),
            'total_estimated_spend' => $estimated,
            'currency'              => $currency,
        ];

        // Persist idempotency record (stores response as metadata).
        if ( '' !== $key ) {
            $this->idempotency->markProcessed( $key, 'ManualExportAdapter', $response );
        }

        return $response;
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
