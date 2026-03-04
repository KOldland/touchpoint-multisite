<?php

namespace KH_SMMA\Adapters;

use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Services\ScheduleQueueProcessor;

use function add_filter;
use function get_option;
use function get_post_meta;
use function gmdate;
use function time;
use function update_option;
use function update_post_meta;
use function sanitize_text_field;
use function __;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ManualExportAdapter: paid adapter that queues operations for manual processing.
 *
 * Implements PaidAdapterContract so SMMA can call dry_run() / execute() with
 * a standard manifest. Returns status "awaiting_manual_export" from execute()
 * and stores the manifest bundle in WP options for ops pickup.
 *
 * Also registers as a WP filter handler for the legacy kh_smma_dispatch_schedule
 * flow so existing integrations continue to work unchanged.
 *
 * @see docs/contracts/paid_adapter_manifest.json
 * @see docs/contracts/paid_adapter_execute.json
 * @see docs/contracts/paid_adapter_notes.md
 */
class ManualExportAdapter implements PaidAdapterContract {

    /** @var AuditLogger|null */
    private $audit_logger;

    /** @var AdapterIdempotencyStore|null */
    private $idempotency;

    /**
     * @param AuditLogger|null             $audit_logger Optional audit logger.
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
     * Register the legacy WP filter dispatch hook.
     */
    public function register(): void {
        add_filter( 'kh_smma_dispatch_schedule', array( $this, 'handle_manual_queue' ), 10, 4 );
    }

    // -------------------------------------------------------------------------
    // PaidAdapterContract implementation
    // -------------------------------------------------------------------------

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
     * Stores manifest bundle to WP options for manual ops processing.
     * Returns status "awaiting_manual_export".
     * Idempotent: repeated calls with the same meta.idempotency_key return
     * the same cached response without creating duplicate bundles.
     */
    public function execute( array $manifest, array $opts = [] ): array {
        $key = $manifest['meta']['idempotency_key'] ?? '';

        // Idempotency check - return cached response if key already consumed.
        if ( $this->idempotency && '' !== $key ) {
            $cached = $this->idempotency->get( $key );
            if ( null !== $cached ) {
                return $cached;
            }
        }

        $dry       = $this->dry_run( $manifest );
        $estimated = $dry['total_estimated_spend'];
        $currency  = $dry['currency'];

        // Store bundle to WP option for ops pickup.
        $bundle_option = 'kh_paid_bundle_' . sanitize_text_field( $manifest['manifest_id'] ?? '' );
        update_option( $bundle_option, $manifest, false );

        // Audit log.
        if ( $this->audit_logger ) {
            $this->audit_logger->log( 'paid_adapter.execute', [
                'object_type' => 'manifest',
                'details'     => [
                    'manifest_id'     => $manifest['manifest_id'] ?? '',
                    'adapter'         => 'ManualExportAdapter',
                    'estimated_spend' => $estimated,
                    'currency'        => $currency,
                    'sponsor_id'      => $manifest['meta']['sponsor_id'] ?? null,
                    'idempotency_key' => $key,
                ],
            ] );
        }

        $response = [
            'manifest_id'           => $manifest['manifest_id'] ?? '',
            'status'                => 'awaiting_manual_export',
            'package_url'           => 'option:' . $bundle_option,
            'total_estimated_spend' => $estimated,
            'currency'              => $currency,
        ];

        // Store response for idempotency.
        if ( $this->idempotency && '' !== $key ) {
            $this->idempotency->store( $key, $response );
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // Legacy WP filter dispatch (unchanged)
    // -------------------------------------------------------------------------

    /**
     * Handle manual export queue via the kh_smma_dispatch_schedule filter.
     *
     * Creates an export bundle with sponsor metadata, variants, and recommended
     * budgets. Bundle format:
     * - schedule_id, sponsor_id
     * - variants with text, asset_ids
     * - recommended_budget with platform and daily amount
     * - sponsor_metadata (allowed_claims, co_brand_policy, assets)
     */
    public function handle_manual_queue( $result, $schedule_id, $payload, $context ) {
        $is_manual = ( isset( $context['delivery'] ) && 'manual_export' === $context['delivery'] )
                     || ( isset( $context['provider'] ) && 'manual' === $context['provider'] );

        if ( ! $is_manual ) {
            return $result;
        }

        // Gather sponsor metadata if applicable.
        $sponsor_id   = (int) get_post_meta( $schedule_id, '_kh_smma_sponsor_id', true );
        $sponsor_meta = [];

        if ( $sponsor_id && function_exists( 'kh_ad_manager_get_sponsor_meta' ) ) {
            $sponsor_meta = kh_ad_manager_get_sponsor_meta( $sponsor_id );
        }

        // Build variants with asset IDs.
        $variants = [];
        if ( is_array( $payload ) && isset( $payload['variant_id'] ) ) {
            $sponsor_assets = (array) get_post_meta( $schedule_id, '_kh_smma_sponsor_assets', true );
            $asset_ids      = is_array( $sponsor_assets ) ? array_column( $sponsor_assets, 'id' ) : [];

            $variants[] = [
                'variant_id' => sanitize_text_field( $payload['variant_id'] ?? '' ),
                'text'       => sanitize_text_field( $payload['message'] ?? ( $payload['text'] ?? '' ) ),
                'asset_ids'  => $asset_ids,
            ];
        }

        // Determine recommended budget from boost settings.
        $boost_settings     = get_post_meta( $schedule_id, '_kh_smma_boost_settings', true );
        $recommended_budget = [
            'platform' => 'LinkedIn',
            'daily'    => 0,
            'total'    => 0,
        ];

        if ( is_array( $boost_settings ) ) {
            $recommended_budget['daily'] = (float) ( $boost_settings['linkedin']['budget_daily'] ?? 0 );
            $recommended_budget['total'] = (float) ( $boost_settings['linkedin']['budget_total'] ?? 0 );
        } elseif ( $sponsor_id && is_array( $sponsor_meta ) ) {
            $recommended_budget['daily'] = (float) ( $sponsor_meta['ppc_daily_cap'] ?? 0 );
            $recommended_budget['total'] = (float) ( $sponsor_meta['ppc_budget_total'] ?? 0 );
        }

        $export_bundle = [
            'schedule_id'        => $schedule_id,
            'account_id'         => $context['account_id'] ?? 0,
            'sponsor_id'         => $sponsor_id,
            'payload'            => $payload,
            'variants'           => $variants,
            'recommended_budget' => $recommended_budget,
            'sponsor_metadata'   => [
                'name'            => $sponsor_meta['name'] ?? '',
                'allowed_claims'  => $sponsor_meta['allowed_claims'] ?? [],
                'co_brand_policy' => $sponsor_meta['co_brand_policy'] ?? 'co-brand',
                'assets'          => $sponsor_meta['sponsor_assets'] ?? [],
                'ppc_account_id'  => $sponsor_meta['ppc_account_id'] ?? '',
            ],
            'generated' => time(),
        ];

        update_post_meta( $schedule_id, '_kh_smma_export_bundle', $export_bundle );
        ScheduleQueueProcessor::log_telemetry( $schedule_id, [
            'mode'               => 'manual',
            'provider'           => $context['provider'] ?? 'manual',
            'payload_preview'    => $payload,
            'sponsor_id'         => $sponsor_id,
            'recommended_budget' => $recommended_budget,
            'note'               => __( 'Manual export bundle generated with sponsor metadata.', 'kh-smma' ),
        ] );

        return 'awaiting_manual_export';
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

        // Fallback: treat bid amount as total if no campaign window provided.
        return $amount;
    }
}
