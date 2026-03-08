<?php

namespace KH_SMMA\Adapters;

use KH_SMMA\Adapters\Exceptions\AdapterExecutionException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PaidAdapterContract: Canonical PHP interface for all paid ad adapters.
 *
 * Every paid adapter (ManualExportAdapter, LinkedInSandboxAdapter,
 * GoogleSandboxAdapter, etc.) must implement this interface.
 *
 * Method signatures are aligned with the JSON Schemas in:
 *   docs/contracts/paid_adapter_manifest.json  (input)
 *   docs/contracts/paid_adapter_execute.json   (response)
 *
 * Adapter implementations that need shared helpers (format_operation,
 * validate_payload, estimate_spend) should extend PaidAdapterBase instead
 * of implementing this interface directly.
 *
 * @see PaidAdapterBase
 * @see docs/contracts/paid_adapter_notes.md
 */
interface PaidAdapterContract {

    /**
     * Simulate operations without executing them on the provider.
     *
     * Must be deterministic for a given manifest — repeated dry_run calls
     * with identical input must return identical output.
     *
     * Return shape (subset of paid_adapter_manifest.json dry_run response):
     * [
     *   'manifest_id'           => string,
     *   'operations'            => [ ['operation_id'=>'...', 'estimated_ops'=>[...],
     *                                 'estimated_spend'=>float, 'currency'=>string,
     *                                 'confidence'=>float], ... ],
     *   'total_estimated_spend' => float,
     *   'currency'              => string,  // ISO 4217
     *   'timestamp'             => string,  // ISO 8601 UTC
     * ]
     *
     * @param array $manifest Paid adapter manifest conforming to paid_adapter_manifest.json
     * @param array $opts     Optional adapter-specific options
     * @return array          Dry-run response array
     */
    public function dry_run( array $manifest, array $opts = [] ): array;

    /**
     * Execute manifest on provider.
     *
     * Must be idempotent: repeated calls with the same meta.idempotency_key
     * must return the same operation_ids/status and must not create duplicate
     * ledger or reconciliation entries.
     *
     * Return shape (paid_adapter_execute.json):
     * [
     *   'manifest_id'        => string,
     *   'status'             => 'success'|'partial_success'|'failed'|'awaiting_manual_export',
     *   'operation_results'  => [ ['operation_id'=>'...', 'operation_id_on_channel'=>'...',
     *                              'result'=>'created'|'failed'|...,
     *                              'actual_spend'=>float, 'currency'=>string,
     *                              'error'=>array|null], ... ],
     *   'total_actual_spend' => float,
     *   'currency'           => string,
     *   'errors'             => array|null,
     *   'timestamp'          => string,
     * ]
     *
     * @param array $manifest Paid adapter manifest
     * @param array $opts     Optional adapter-specific options
     * @return array          Execute response array
     * @throws AdapterExecutionException on unrecoverable provider error (key is NOT consumed)
     */
    public function execute( array $manifest, array $opts = [] ): array;
}
