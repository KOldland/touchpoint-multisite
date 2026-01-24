<?php
namespace KH_SMMA\Adapters;

use KH_SMMA\Services\FeatureFlags;
use KH_SMMA\Services\ScheduleQueueProcessor;
use KH_SMMA\Services\TokenRepository;
use WP_Error;

use function __;
use function add_filter;
use function time;
use function update_post_meta;
use function get_post_meta;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GoogleAdsAdapter {
    /** @var TokenRepository */
    private $tokens;

    /** @var FeatureFlags */
    private $flags;

    public function __construct( TokenRepository $tokens, FeatureFlags $flags ) {
        $this->tokens = $tokens;
        $this->flags  = $flags;
    }

    public function register() {
        add_filter( 'kh_smma_dispatch_schedule', array( $this, 'handle_dispatch' ), 35, 4 );
    }

    public function handle_dispatch( $result, $schedule_id, $payload, $context ) {
        if ( empty( $context['provider'] ) || 'google_ads' !== $context['provider'] ) {
            return $result;
        }

        if ( ! $this->flags->is_enabled( 'smma_paid_adapters' ) ) {
            return $this->handle_manual_fallback( $schedule_id, $payload, $context, 'Paid adapters disabled.' );
        }

        $token = $context['token'] ?? array();
        if ( empty( $token['access_token'] ) || empty( $token['customer_id'] ) ) {
            return new WP_Error( 'kh_smma_google_ads_missing_token', __( 'Google Ads token missing.', 'kh-smma' ) );
        }

        $boost_settings = get_post_meta( $schedule_id, '_kh_smma_boost_settings', true );
        $dry_run = ! empty( $boost_settings['google']['dry_run'] );

        $operations = array(
            'campaign' => array(
                'name' => $payload['variant_id'] ?? 'SMMA Campaign',
                'daily_budget' => $boost_settings['google']['daily_budget'] ?? 0,
                'start' => $boost_settings['google']['start'] ?? time(),
                'end' => $boost_settings['google']['end'] ?? 0,
            ),
            'keyword_clusters' => $payload['keyword_clusters'] ?? array(),
            'ad_groups' => $payload['ad_groups'] ?? array(),
        );

        if ( $dry_run ) {
            update_post_meta( $schedule_id, '_kh_smma_result_metrics', array(
                'note' => 'Google Ads dry run',
                'operations' => $operations,
                'dry_run' => true,
                'queued_at' => time(),
            ) );

            ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
                'mode' => 'dry_run',
                'provider' => 'google_ads',
                'payload_preview' => $payload,
                'operations' => $operations,
            ) );

            return 'completed';
        }

        // Placeholder for real API integration
        $operation_id = 'ga-ads-' . $schedule_id . '-' . time();

        update_post_meta( $schedule_id, '_kh_smma_result_metrics', array(
            'note' => 'Google Ads draft created',
            'operation_id' => $operation_id,
            'operations' => $operations,
            'queued_at' => time(),
        ) );

        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode' => 'live',
            'provider' => 'google_ads',
            'payload_preview' => $payload,
            'operations' => $operations,
            'operation_id' => $operation_id,
        ) );

        return 'completed';
    }

    private function handle_manual_fallback( $schedule_id, $payload, $context, $note ) {
        $export_bundle = array(
            'schedule_id' => $schedule_id,
            'account_id'  => $context['account_id'] ?? 0,
            'payload'     => $payload,
            'generated'   => time(),
        );

        update_post_meta( $schedule_id, '_kh_smma_export_bundle', $export_bundle );
        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode' => 'manual',
            'provider' => 'google_ads',
            'payload_preview' => $payload,
            'note' => $note,
        ) );

        return 'awaiting_manual_export';
    }
}
