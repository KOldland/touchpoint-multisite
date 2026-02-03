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

/**
 * Google Ads Adapter
 * 
 * Implements PaidAdapterContract for Google Ads campaigns.
 * Supports both dry-run (preview) and execute modes.
 */
class GoogleAdsAdapter extends PaidAdapterContract {
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

    /**
     * Dispatch handler: routes to dry_run or execute based on settings.
     */
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
        $is_dry_run = ! empty( $boost_settings['google']['dry_run'] );

        // Build schedule payload for adapter
        $schedule_payload = array(
            'variant_id'   => $payload['variant_id'] ?? $payload['message'] ?? 'variant',
            'text'         => $payload['message'] ?? $payload['text'] ?? '',
            'targeting'    => $boost_settings['google']['targeting'] ?? array(),
            'budget_daily' => $boost_settings['google']['budget_daily'] ?? 0,
            'start'        => $boost_settings['google']['start'] ?? time(),
            'end'          => $boost_settings['google']['end'] ?? 0,
            'customer_id'  => $token['customer_id'],
        );

        if ( $is_dry_run ) {
            $operations = $this->dry_run( $schedule_payload );
        } else {
            $operations = $this->execute( $schedule_payload );
        }

        if ( is_wp_error( $operations ) ) {
            return $this->handle_manual_fallback( $schedule_id, $payload, $context, $operations->get_error_message() );
        }

        // Store operation results
        update_post_meta( $schedule_id, '_kh_smma_adapter_operations', $operations );
        update_post_meta( $schedule_id, '_kh_smma_adapter_dry_run', $is_dry_run );

        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode'                => $is_dry_run ? 'dry_run' : 'live',
            'provider'            => 'google_ads',
            'operations'          => $operations,
            'operation_count'     => count( $operations ),
        ) );

        return $is_dry_run ? 'completed' : 'in_flight';
    }

    /**
     * Dry-run: Return operation sequence without executing API calls.
     */
    public function dry_run( array $schedule_payload ) {
        $validation = $this->validate_payload( $schedule_payload );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $estimated_spend = $this->estimate_spend( $schedule_payload );
        $policy_warnings = array();

        if ( $estimated_spend > 50000 ) {
            $policy_warnings[] = 'Large budget exceeds Google Ads recommended monthly limit.';
        }

        $operations = array(
            $this->format_operation(
                'create_campaign',
                array(
                    'name'           => $schedule_payload['variant_id'],
                    'budget_daily'   => (float) $schedule_payload['budget_daily'],
                    'start_date'     => date( 'Y-m-d', $schedule_payload['start'] ),
                    'end_date'       => $schedule_payload['end'] ? date( 'Y-m-d', $schedule_payload['end'] ) : null,
                    'campaign_type'  => 'SEARCH',
                ),
                array(
                    'estimated_spend'   => $estimated_spend,
                    'policy_warnings'   => $policy_warnings,
                )
            ),
            $this->format_operation(
                'create_ad_group',
                array(
                    'name'              => $schedule_payload['variant_id'] . ' Group',
                    'status'            => 'PAUSED',
                )
            ),
            $this->format_operation(
                'create_text_ads',
                array(
                    'headline'       => substr( $schedule_payload['text'], 0, 30 ),
                    'description'    => substr( $schedule_payload['text'], 30, 90 ),
                    'count'          => 1,
                )
            ),
            $this->format_operation(
                'add_keywords',
                array(
                    'targeting_type' => 'KEYWORDS',
                    'keyword_count'  => is_array( $schedule_payload['targeting'] ) ? count( $schedule_payload['targeting'] ) : 0,
                )
            ),
        );

        return $operations;
    }

    /**
     * Execute: Perform real API calls (currently stubbed for sandbox).
     */
    public function execute( array $schedule_payload ) {
        $validation = $this->validate_payload( $schedule_payload );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // TODO: Implement real Google Ads API integration
        
        $operation_id = 'gads-campaign-' . time();

        $operations = array(
            array(
                'op_type'         => 'create_campaign',
                'status'          => 'created',
                'campaign_id'     => $operation_id . '-campaign',
                'response'        => array(
                    'id'             => $operation_id . '-campaign',
                    'name'           => $schedule_payload['variant_id'],
                    'campaign_type'  => 'SEARCH',
                ),
            ),
            array(
                'op_type'         => 'create_ad_group',
                'status'          => 'created',
                'ad_group_id'     => $operation_id . '-group',
                'response'        => array(
                    'id'   => $operation_id . '-group',
                    'name' => $schedule_payload['variant_id'] . ' Group',
                ),
            ),
            array(
                'op_type'         => 'create_text_ads',
                'status'          => 'created',
                'ad_count'        => 1,
                'response'        => array(
                    'success' => true,
                ),
            ),
        );

        return $operations;
    }

    /**
     * Get adapter metadata.
     */
    public function get_metadata() {
        return array(
            'name'        => 'Google Ads',
            'version'     => '1.0.0',
            'type'        => 'paid_ads',
            'platforms'   => array( 'Google' ),
            'capabilities' => array(
                'dry_run'       => true,
                'execute'       => true,
                'resume'        => false,
                'budget_control' => true,
                'keyword_targeting' => true,
                'audience_targeting' => true,
            ),
        );
    }

    /**
     * Fallback to manual export when adapter can't execute.
     */
    private function handle_manual_fallback( $schedule_id, $payload, $context, $note ) {
        $export_bundle = array(
            'schedule_id' => $schedule_id,
            'payload'     => $payload,
            'generated'   => time(),
        );

        update_post_meta( $schedule_id, '_kh_smma_export_bundle', $export_bundle );
        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode'            => 'manual',
            'provider'        => 'google_ads',
            'fallback_reason' => $note,
        ) );

        return 'awaiting_manual_export';
    }
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
