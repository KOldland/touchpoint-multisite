<?php
/**
 * Paid Adapter Contract
 *
 * Canonical PHP interface for paid ad adapters within the khm-plugin ecosystem.
 * This is an independent copy of the interface — no cross-plugin dependency on kh-smma.
 *
 * Method signatures and response shapes are defined by the JSON Schemas at:
 *   docs/contracts/paid_adapter_manifest.json  (input)
 *   docs/contracts/paid_adapter_execute.json   (response)
 *
 * @package KHM\Adapters
 */

namespace KHM\Adapters;

interface PaidAdapterContract {

    /**
     * Simulate operations without executing them on the provider.
     *
     * Must be deterministic for a given manifest — repeated dry_run calls
     * with identical input must return identical output.
     *
     * @param array $manifest Paid adapter manifest conforming to paid_adapter_manifest.json
     * @param array $opts     Optional adapter-specific options
     * @return array          Dry-run response with manifest_id, operations[], total_estimated_spend, currency, timestamp
     */
    public function dry_run( array $manifest, array $opts = [] ): array;

    /**
     * Execute manifest on provider.
     *
     * Must be idempotent: repeated calls with the same meta.idempotency_key
     * must return the same status/operation_ids without creating duplicate records.
     *
     * @param array $manifest Paid adapter manifest
     * @param array $opts     Optional adapter-specific options
     * @return array          Execute response with manifest_id, status, total_actual_spend, currency
     */
    public function execute( array $manifest, array $opts = [] ): array;
}
