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
use function is_wp_error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LinkedInAdsAdapter {
    /** @var TokenRepository */
    private $tokens;

    /** @var FeatureFlags */
    private $flags;

    public function __construct( TokenRepository $tokens, FeatureFlags $flags ) {
        $this->tokens = $tokens;
        $this->flags  = $flags;
    }

    public function register() {
        add_filter( 'kh_smma_dispatch_schedule', array( $this, 'handle_dispatch' ), 30, 4 );
    }

    public function handle_dispatch( $result, $schedule_id, $payload, $context ) {
        if ( empty( $context['provider'] ) || 'linkedin_ads' !== $context['provider'] ) {
            return $result;
        }

        if ( ! $this->flags->is_enabled( 'smma_paid_adapters' ) ) {
            return $this->handle_manual_fallback( $schedule_id, $payload, $context, 'Paid adapters disabled.' );
        }

        $token = $context['token'] ?? array();
        if ( empty( $token['access_token'] ) || empty( $token['account_id'] ) ) {
            return new WP_Error( 'kh_smma_linkedin_ads_missing_token', __( 'LinkedIn Ads account token missing.', 'kh-smma' ) );
        }

        $boost_settings = get_post_meta( $schedule_id, '_kh_smma_boost_settings', true );
        $dry_run = ! empty( $boost_settings['linkedin']['dry_run'] );

        $operations = array(
            'campaign' => array(
                'name' => $payload['variant_id'] ?? 'SMMA Campaign',
                'daily_budget' => $boost_settings['linkedin']['budget_daily'] ?? 0,
                'start' => $boost_settings['linkedin']['start'] ?? time(),
                'end' => $boost_settings['linkedin']['end'] ?? 0,
            ),
            'creative' => array(
                'text' => $payload['message'] ?? ( $payload['text'] ?? '' ),
                'asset' => $payload['asset'] ?? array(),
            ),
            'targeting' => $boost_settings['linkedin']['audience'] ?? array(),
        );

        if ( $dry_run ) {
            update_post_meta( $schedule_id, '_kh_smma_result_metrics', array(
                'note' => 'LinkedIn Ads dry run',
                'operations' => $operations,
                'dry_run' => true,
                'queued_at' => time(),
            ) );

            ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
                'mode' => 'dry_run',
                'provider' => 'linkedin_ads',
                'payload_preview' => $payload,
                'operations' => $operations,
            ) );

            return 'completed';
        }

        // Placeholder for real API integration
        $operation_id = 'li-ads-' . $schedule_id . '-' . time();

        update_post_meta( $schedule_id, '_kh_smma_result_metrics', array(
            'note' => 'LinkedIn Ads draft created',
            'operation_id' => $operation_id,
            'operations' => $operations,
            'queued_at' => time(),
        ) );

        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode' => 'live',
            'provider' => 'linkedin_ads',
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
            'provider' => 'linkedin_ads',
            'payload_preview' => $payload,
            'note' => $note,
        ) );

        return 'awaiting_manual_export';
    }
}
