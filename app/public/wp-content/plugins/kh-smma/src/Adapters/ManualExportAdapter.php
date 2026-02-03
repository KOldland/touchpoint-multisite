<?php
namespace KH_SMMA\Adapters;

use KH_SMMA\Services\ScheduleQueueProcessor;

use function add_filter;
use function time;
use function update_post_meta;
use function get_post_meta;
use function __;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ManualExportAdapter {
    public function register() {
        add_filter( 'kh_smma_dispatch_schedule', array( $this, 'handle_manual_queue' ), 10, 4 );
    }

    /**
     * Handle manual export queue.
     * 
     * Creates an export bundle with sponsor metadata, variants, and recommended budgets.
     * Bundle format includes:
     * - schedule_id, sponsor_id
     * - variants with text, asset_ids
     * - recommended_budget with platform and daily amount
     * - sponsor_metadata (allowed_claims, co_brand_policy, assets)
     */
    public function handle_manual_queue( $result, $schedule_id, $payload, $context ) {
        $is_manual = ( isset( $context['delivery'] ) && 'manual_export' === $context['delivery'] ) || ( isset( $context['provider'] ) && 'manual' === $context['provider'] );

        if ( ! $is_manual ) {
            return $result;
        }

        // Gather sponsor metadata if applicable
        $sponsor_id = (int) get_post_meta( $schedule_id, '_kh_smma_sponsor_id', true );
        $sponsor_meta = array();

        if ( $sponsor_id && function_exists( 'kh_ad_manager_get_sponsor_meta' ) ) {
            $sponsor_meta = kh_ad_manager_get_sponsor_meta( $sponsor_id );
        }

        // Build variants with asset IDs
        $variants = array();
        if ( is_array( $payload ) && isset( $payload['variant_id'] ) ) {
            $sponsor_assets = (array) get_post_meta( $schedule_id, '_kh_smma_sponsor_assets', true );
            $asset_ids = array();
            if ( is_array( $sponsor_assets ) ) {
                $asset_ids = array_column( $sponsor_assets, 'id' );
            }

            $variants[] = array(
                'variant_id' => sanitize_text_field( $payload['variant_id'] ?? '' ),
                'text'       => sanitize_text_field( $payload['message'] ?? ( $payload['text'] ?? '' ) ),
                'asset_ids'  => $asset_ids,
            );
        }

        // Determine recommended budget from boost settings
        $boost_settings = get_post_meta( $schedule_id, '_kh_smma_boost_settings', true );
        $recommended_budget = array(
            'platform' => 'LinkedIn',
            'daily'    => 0,
            'total'    => 0,
        );

        if ( is_array( $boost_settings ) ) {
            $recommended_budget['daily'] = (float) ( $boost_settings['linkedin']['budget_daily'] ?? 0 );
            $recommended_budget['total']  = (float) ( $boost_settings['linkedin']['budget_total'] ?? 0 );
        } elseif ( $sponsor_id && is_array( $sponsor_meta ) ) {
            // Fallback to sponsor default budget
            $recommended_budget['daily'] = (float) ( $sponsor_meta['ppc_daily_cap'] ?? 0 );
            $recommended_budget['total']  = (float) ( $sponsor_meta['ppc_budget_total'] ?? 0 );
        }

        $export_bundle = array(
            'schedule_id' => $schedule_id,
            'account_id'  => $context['account_id'] ?? 0,
            'sponsor_id'  => $sponsor_id,
            'payload'     => $payload,
            'variants'    => $variants,
            'recommended_budget' => $recommended_budget,
            'sponsor_metadata' => array(
                'name'            => $sponsor_meta['name'] ?? '',
                'allowed_claims'  => $sponsor_meta['allowed_claims'] ?? array(),
                'co_brand_policy' => $sponsor_meta['co_brand_policy'] ?? 'co-brand',
                'assets'          => $sponsor_meta['sponsor_assets'] ?? array(),
                'ppc_account_id'  => $sponsor_meta['ppc_account_id'] ?? '',
            ),
            'generated'   => time(),
        );

        update_post_meta( $schedule_id, '_kh_smma_export_bundle', $export_bundle );
        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode'            => 'manual',
            'provider'        => $context['provider'] ?? 'manual',
            'payload_preview' => $payload,
            'sponsor_id'      => $sponsor_id,
            'recommended_budget' => $recommended_budget,
            'note'            => __( 'Manual export bundle generated with sponsor metadata.', 'kh-smma' ),
        ) );

        return 'awaiting_manual_export';
    }
}
