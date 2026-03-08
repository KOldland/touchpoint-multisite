<?php
/**
 * Accounting Adapter Contract
 *
 * Abstract base class for accounting adapters that deliver settled ledgers
 * to external systems (SFTP, REST API, etc.).
 *
 * Follows the same dry_run / execute pattern as PaidAdapterContract so
 * callers can validate before committing a real delivery.
 *
 * @package KH_SMMA\Reconciliation
 * @see     docs/paid/accounting_integration_runbook.md
 * @see     docs/contracts/paid_delivery.json
 */

namespace KH_SMMA\Reconciliation;

use KH_SMMA\Services\AuditLogger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class AccountingAdapterContract {

    /** @var AuditLogger|null */
    protected $audit_logger;

    /** @var DeliveryIdempotencyStore|null */
    protected $idempotency;

    public function __construct(
        ?AuditLogger $audit_logger = null,
        ?DeliveryIdempotencyStore $idempotency = null
    ) {
        $this->audit_logger = $audit_logger;
        $this->idempotency  = $idempotency;
    }

    /**
     * Validate and simulate the delivery without actually transmitting.
     *
     * Returns estimated ops, computed checksum, and payload size so
     * callers can verify the settlement is ready before execute().
     *
     * @param array $settlement  Settlement row from SettlementWorker::get_settlement().
     * @param array $opts        Optional overrides. 'simulate_failures' forces error path.
     * @return array {
     *     settlement_id:       string,
     *     adapter:             string,
     *     valid:               bool,
     *     checksum:            string|null,
     *     payload_size_bytes:  int,
     *     estimated_ops:       string[],
     *     timestamp:           string,
     *     error?:              array{code:string, message:string}
     * }
     */
    abstract public function dry_run( array $settlement, array $opts = [] ): array;

    /**
     * Deliver the settled ledger to the accounting system.
     *
     * Idempotent: repeated calls with the same settlement_id + adapter
     * return the cached delivery row without re-transmitting.
     *
     * @param array $settlement  Settlement row from SettlementWorker::get_settlement().
     * @param array $opts        Optional overrides. 'simulate_failures' key forces error path.
     * @return array {
     *     delivery_id:   string,
     *     settlement_id: string,
     *     adapter:       string,
     *     status:        string (delivered|failed),
     *     checksum:      string|null,
     *     delivered_at:  string|null,
     *     error:         array|null,
     *     timestamp:     string,
     *     adapter_meta:  array
     * }
     */
    abstract public function execute( array $settlement, array $opts = [] ): array;

    /**
     * Return the adapter slug used in delivery rows and audit events.
     *
     * @return string  e.g. 'sftp', 'accounting_api'
     */
    abstract public function adapter_name(): string;

    /**
     * Build the CSV ledger string for a settlement row.
     *
     * Replicates SettlementWorker::export_ledger_csv() locally so adapters
     * don't need a reference to the worker.
     *
     * @param array $settlement  Settlement row.
     * @return string  CSV string (header + 1 data row).
     */
    protected function build_ledger_csv( array $settlement ): string {
        $columns = [
            'settlement_id', 'sponsor_id', 'currency', 'total_settled',
            'fx_rate', 'settled_at', 'reconciliation_ids', 'batch_size',
        ];

        $output = fopen( 'php://temp', 'r+' );
        fputcsv( $output, $columns );

        $data_row = [];
        foreach ( $columns as $col ) {
            $data_row[] = $settlement[ $col ] ?? '';
        }
        fputcsv( $output, $data_row );

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv;
    }

    /**
     * Validate that the settlement array contains the required keys.
     *
     * @param array $settlement
     * @return bool
     */
    protected function is_valid_settlement( array $settlement ): bool {
        $required = [ 'settlement_id', 'sponsor_id', 'total_settled', 'currency', 'settled_at' ];
        foreach ( $required as $key ) {
            if ( empty( $settlement[ $key ] ) ) {
                return false;
            }
        }
        return true;
    }
}
