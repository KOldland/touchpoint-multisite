<?php

namespace KH_SMMA\Adapters;

use KH_SMMA\Adapters\Exceptions\AdapterExecutionException;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PaidAdapterBase: Abstract base class for paid ad adapters.
 *
 * Implements PaidAdapterContract and provides shared helper utilities
 * (spend estimation, operation formatting, error building, payload validation)
 * for concrete adapters such as LinkedInAdsAdapter and GoogleAdsAdapter.
 *
 * Adapters built for the legacy schedule_payload flow should extend this class.
 * New adapters conforming to the manifest-first contract may implement
 * PaidAdapterContract directly.
 *
 * @see PaidAdapterContract
 * @see docs/contracts/paid_adapter_manifest.json
 * @see docs/contracts/paid_adapter_execute.json
 */
abstract class PaidAdapterBase implements PaidAdapterContract {

    /**
     * Simulate operations without executing them on the provider.
     * Must be deterministic for a given manifest.
     *
     * @param array $manifest Paid adapter manifest conforming to paid_adapter_manifest.json
     * @param array $opts     Optional adapter-specific options
     * @return array          Dry-run response shape
     */
    abstract public function dry_run( array $manifest, array $opts = [] ): array;

    /**
     * Execute manifest on provider. Must be idempotent for same idempotency_key.
     *
     * @param array $manifest Paid adapter manifest
     * @param array $opts     Optional adapter-specific options
     * @return array          Execute response shape conforming to paid_adapter_execute.json
     * @throws AdapterExecutionException on unrecoverable provider error
     */
    abstract public function execute( array $manifest, array $opts = [] ): array;

    /**
     * Return adapter metadata: name, version, capabilities.
     *
     * @return array
     */
    abstract public function get_metadata(): array;

    // -------------------------------------------------------------------------
    // Protected helpers (shared across concrete adapters)
    // -------------------------------------------------------------------------

    /**
     * Format a dry-run operation entry (legacy schedule_payload shape).
     *
     * @param string $op_type  Operation type (create_campaign, create_creative, etc.)
     * @param array  $payload  Operation payload preview
     * @param array  $metadata Optional metadata (estimated_spend, policy_warnings)
     * @return array
     */
    protected function format_operation( string $op_type, array $payload, array $metadata = array() ): array {
        return array(
            'op_type'         => $op_type,
            'payload_preview' => $payload,
            'estimated_spend' => $metadata['estimated_spend'] ?? 0,
            'policy_warnings' => $metadata['policy_warnings'] ?? array(),
            'requires_review' => ! empty( $metadata['policy_warnings'] ),
        );
    }

    /**
     * Build a standard WP_Error response for operation failure.
     *
     * @param string $code    Error code
     * @param string $message Human-readable message
     * @param array  $details Additional context
     * @return WP_Error
     */
    protected function error( string $code, string $message, array $details = array() ): WP_Error {
        return new WP_Error( $code, $message, $details );
    }

    /**
     * Validate legacy schedule payload structure.
     *
     * @param array $payload
     * @return bool|WP_Error true on success, WP_Error on failure
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
     * Estimate spend from legacy boost settings.
     *
     * @param array $boost_settings Array with budget_daily and duration_days
     * @return float
     */
    protected function estimate_spend( array $boost_settings = array() ): float {
        $daily    = (float) ( $boost_settings['budget_daily'] ?? 0 );
        $duration = (int) ( $boost_settings['duration_days'] ?? 1 );
        return $daily * $duration;
    }
}
