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

/**
 * LinkedIn Ads Adapter
 * 
 * Implements PaidAdapterContract for LinkedIn Ads campaigns.
 * Supports both dry-run (preview) and execute modes.
 * 
 * Dry-run returns operation sequence without API calls:
 * - create_campaign: Campaign setup (name, budget, dates)
 * - create_creative: Ad creative (text, media assets)
 * - associate_audience: Target audience settings
 */
class LinkedInAdsAdapter extends PaidAdapterBase {
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

    /**
     * Dispatch handler: routes to dry_run or execute based on settings.
     */
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
        $is_dry_run = ! empty( $boost_settings['linkedin']['dry_run'] );

        // Build schedule payload for adapter
        $schedule_payload = array(
            'variant_id'   => $payload['variant_id'] ?? $payload['message'] ?? 'variant',
            'text'         => $payload['message'] ?? $payload['text'] ?? '',
            'asset'        => $payload['asset'] ?? array(),
            'targeting'    => $boost_settings['linkedin']['audience'] ?? array(),
            'budget_daily' => $boost_settings['linkedin']['budget_daily'] ?? 0,
            'start'        => $boost_settings['linkedin']['start'] ?? time(),
            'end'          => $boost_settings['linkedin']['end'] ?? 0,
            'account_id'   => $token['account_id'],
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
            'provider'            => 'linkedin_ads',
            'operations'          => $operations,
            'operation_count'     => count( $operations ),
            'estimated_spend'     => $this->sum_operation_spend( $operations ),
        ) );

        return $is_dry_run ? 'completed' : 'in_flight';
    }

    /**
     * Dry-run: Return operation sequence without executing API calls.
     * 
     * @param array $schedule_payload
     * @return array|WP_Error
     */
    public function dry_run( array $schedule_payload, array $opts = [] ): array {
        $validation = $this->validate_payload( $schedule_payload );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $estimated_spend = $this->estimate_spend( $schedule_payload );
        $policy_warnings = array();

        // Check budget constraints
        if ( $estimated_spend > 100000 ) {
            $policy_warnings[] = 'Large budget: ' . $estimated_spend . ' exceeds recommended monthly limit.';
        }

        // Check targeting for policy violations
        if ( ! empty( $schedule_payload['targeting']['excluded_criteria'] ) ) {
            $policy_warnings[] = 'Targeting includes exclusions that may limit reach.';
        }

        $operations = array(
            $this->format_operation(
                'create_campaign',
                array(
                    'name'           => $schedule_payload['variant_id'] ?? 'SMMA Campaign',
                    'budget_daily'   => (float) $schedule_payload['budget_daily'],
                    'start_date'     => date( 'Y-m-d', $schedule_payload['start'] ),
                    'end_date'       => $schedule_payload['end'] ? date( 'Y-m-d', $schedule_payload['end'] ) : null,
                    'status'         => 'PAUSED',
                ),
                array(
                    'estimated_spend'   => $estimated_spend,
                    'policy_warnings'   => $policy_warnings,
                )
            ),
            $this->format_operation(
                'create_creative',
                array(
                    'text'           => substr( $schedule_payload['text'], 0, 500 ),
                    'media_count'    => is_array( $schedule_payload['asset'] ) ? count( $schedule_payload['asset'] ) : 0,
                    'media_types'    => array( 'image', 'video' ),
                )
            ),
            $this->format_operation(
                'associate_audience',
                array(
                    'targeting_type'=> 'AUDIENCE',
                    'audience_count' => is_array( $schedule_payload['targeting'] ) ? count( $schedule_payload['targeting'] ) : 0,
                )
            ),
        );

        return $operations;
    }

    /**
     * Execute: Perform real API calls (currently stubbed for sandbox).
     * 
     * @param array $schedule_payload
     * @return array|WP_Error
     */
    public function execute( array $schedule_payload, array $opts = [] ): array {
        $validation = $this->validate_payload( $schedule_payload );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // TODO: Implement real LinkedIn Ads API integration
        // For now, return operation sequence as if executed in sandbox
        
        $operation_id = 'li-campaign-' . time();

        $operations = array(
            array(
                'op_type'         => 'create_campaign',
                'status'          => 'created',
                'campaign_id'     => $operation_id . '-campaign',
                'response'        => array(
                    'id'     => $operation_id . '-campaign',
                    'name'   => $schedule_payload['variant_id'],
                    'status' => 'PAUSED',
                ),
            ),
            array(
                'op_type'         => 'create_creative',
                'status'          => 'created',
                'creative_id'     => $operation_id . '-creative',
                'response'        => array(
                    'id'     => $operation_id . '-creative',
                    'status' => 'DRAFT',
                ),
            ),
            array(
                'op_type'         => 'associate_audience',
                'status'          => 'associated',
                'response'        => array(
                    'success' => true,
                ),
            ),
        );

        return $operations;
    }

    /**
     * Get adapter metadata.
     * 
     * @return array
     */
    public function get_metadata(): array {
        return array(
            'name'        => 'LinkedIn Ads',
            'version'     => '1.0.0',
            'type'        => 'paid_ads',
            'platforms'   => array( 'LinkedIn' ),
            'capabilities' => array(
                'dry_run'       => true,
                'execute'       => true,
                'resume'        => false,
                'budget_control' => true,
                'audience_targeting' => true,
            ),
        );
    }

    /**
     * Helper: Sum estimated spend across operations.
     * 
     * @param array $operations
     * @return float
     */
    private function sum_operation_spend( array $operations ): float {
        $total = 0;
        foreach ( $operations as $op ) {
            if ( isset( $op['estimated_spend'] ) ) {
                $total += (float) $op['estimated_spend'];
            }
        }
        return $total;
    }

    /**
     * Fallback to manual export when adapter can't execute.
     */
    private function handle_manual_fallback( $schedule_id, $payload, $context, $note ) {
        $export_bundle = array(
            'schedule_id' => $schedule_id,
            'account_id'  => $context['account_id'] ?? 0,
            'payload'     => $payload,
            'generated'   => time(),
        );

        update_post_meta( $schedule_id, '_kh_smma_export_bundle', $export_bundle );
        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode'            => 'manual',
            'provider'        => 'linkedin_ads',
            'fallback_reason' => $note,
        ) );

        return 'awaiting_manual_export';
    }
}
