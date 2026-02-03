<?php

namespace KH_SMMA\Adapters;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PaidAdapterContract: Base interface/contract for paid ad adapters.
 * 
 * All paid adapters (LinkedIn Ads, Google Ads, Meta Ads, etc.) must implement
 * this contract to ensure consistent dry-run + execute behavior.
 * 
 * This allows SMMA to:
 * 1. Request dry-run to preview operations without executing
 * 2. Get estimated spend and policy warnings
 * 3. Execute when ready in real or sandbox mode
 */
abstract class PaidAdapterContract {
    
    /**
     * Dry-run mode: returns operation sequence without executing.
     * 
     * @param array $schedule_payload Schedule payload with variants, assets, targeting
     * @return array|WP_Error Array of operations with op_type, payload_preview, estimated_spend
     */
    abstract public function dry_run( array $schedule_payload );

    /**
     * Execute mode: performs the actual operations (create campaign, create creative, etc).
     * 
     * @param array $schedule_payload Schedule payload
     * @return array|WP_Error Result with operation_id, status, response
     */
    abstract public function execute( array $schedule_payload );

    /**
     * Get adapter metadata: name, version, capabilities.
     * 
     * @return array
     */
    abstract public function get_metadata();

    /**
     * Format a dry-run operation.
     * 
     * @param string $op_type Operation type: create_campaign, create_creative, associate, etc
     * @param array  $payload Operation payload preview
     * @param array  $metadata Optional metadata (estimated_spend, policy_warnings)
     * @return array
     */
    protected function format_operation( string $op_type, array $payload, array $metadata = array() ): array {
        return array(
            'op_type'           => $op_type,
            'payload_preview'   => $payload,
            'estimated_spend'   => $metadata['estimated_spend'] ?? 0,
            'policy_warnings'   => $metadata['policy_warnings'] ?? array(),
            'requires_review'   => ! empty( $metadata['policy_warnings'] ),
        );
    }

    /**
     * Build standard error response for operation failure.
     * 
     * @param string $code Error code
     * @param string $message Error message
     * @param array  $details Additional details
     * @return WP_Error
     */
    protected function error( string $code, string $message, array $details = array() ): WP_Error {
        return new WP_Error( $code, $message, $details );
    }

    /**
     * Validate schedule payload structure.
     * 
     * @param array $payload
     * @return bool|WP_Error
     */
    protected function validate_payload( array $payload ) {
        if ( empty( $payload['variant_id'] ) || empty( $payload['text'] ) ) {
            return $this->error(
                'invalid_payload',
                __( 'Payload must have variant_id and text.', 'kh-smma' )
            );
        }

        if ( ! isset( $payload['targeting'] ) || ! is_array( $payload['targeting'] ) ) {
            return $this->error(
                'invalid_targeting',
                __( 'Payload must have targeting array.', 'kh-smma' )
            );
        }

        return true;
    }

    /**
     * Estimate spend for operation.
     * 
     * @param array $boost_settings Boost settings with budget_daily, duration, etc
     * @return float
     */
    protected function estimate_spend( array $boost_settings = array() ): float {
        $daily = (float) ( $boost_settings['budget_daily'] ?? 0 );
        $duration = (int) ( $boost_settings['duration_days'] ?? 1 );
        return $daily * $duration;
    }
}
