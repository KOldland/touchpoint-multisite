<?php
/**
 * Settlement Deliver WP-CLI Command
 *
 * Delivers settled ledgers to the accounting system via WP-CLI.
 *
 * Usage:
 *   wp kh-smma paid:deliver
 *   wp kh-smma paid:deliver --settlement_id=sett_abc123 --adapter=sftp
 *   wp kh-smma paid:deliver --adapter=accounting_api --sponsor_id=sp_456
 *   wp kh-smma paid:deliver --settlement_id=sett_abc123 --adapter=sftp --dry_run
 *   wp kh-smma paid:deliver --settlement_id=sett_abc123 --adapter=sftp --force
 *
 * Options:
 *   --settlement_id   Deliver a specific settlement. Omit to deliver all unsettled.
 *   --adapter         Adapter to use: 'sftp' (default) | 'accounting_api'.
 *   --dry_run         Run adapter->dry_run() only; do not deliver.
 *   --force           Invalidate idempotency store and re-deliver (use carefully).
 *   --sponsor_id      Filter undelivered settlements by sponsor (batch mode only).
 *   --date_start      Filter undelivered settlements by start date (YYYY-MM-DD).
 *   --date_end        Filter undelivered settlements by end date (YYYY-MM-DD).
 *
 * @package KH_SMMA\CLI
 * @see     docs/paid/accounting_integration_runbook.md
 */

namespace KH_SMMA\CLI;

use KH_SMMA\Reconciliation\SettlementDeliveryService;
use KH_SMMA\Reconciliation\SftpAccountingAdapter;
use KH_SMMA\Reconciliation\AccountingApiAdapter;
use KH_SMMA\Reconciliation\DeliveryIdempotencyStore;

use function WP_CLI;
use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettlementDeliverCommand {

    /** @var SettlementDeliveryService */
    private $service;

    /** @var SftpAccountingAdapter */
    private $sftp;

    /** @var AccountingApiAdapter */
    private $api;

    public function __construct(
        SettlementDeliveryService $service,
        SftpAccountingAdapter $sftp,
        AccountingApiAdapter $api
    ) {
        $this->service = $service;
        $this->sftp    = $sftp;
        $this->api     = $api;
    }

    public function register(): void {
        if ( ! class_exists( '\WP_CLI' ) ) {
            return;
        }
        \WP_CLI::add_command( 'kh-smma paid:deliver', $this );
    }

    /**
     * Deliver settled ledger(s) to the accounting system.
     *
     * @param array $args        Positional arguments (unused).
     * @param array $assoc_args  Named options.
     */
    public function __invoke( array $args, array $assoc_args ): void {
        $adapter_name  = $assoc_args['adapter'] ?? 'sftp';
        $settlement_id = $assoc_args['settlement_id'] ?? null;
        $is_dry_run    = isset( $assoc_args['dry_run'] );
        $force         = isset( $assoc_args['force'] );

        $adapter = $this->resolve_adapter( $adapter_name );
        if ( null === $adapter ) {
            \WP_CLI::error( "Unknown adapter: {$adapter_name}. Use 'sftp' or 'accounting_api'." );
            return;
        }

        // ── dry_run mode ──────────────────────────────────────────────────────
        if ( $is_dry_run ) {
            if ( null === $settlement_id ) {
                \WP_CLI::error( '--dry_run requires --settlement_id.' );
                return;
            }

            $settlement = $this->service->list_deliveries( [ 'settlement_id' => $settlement_id ] );
            // We need the raw settlement row — fetch via delivery service's worker indirectly.
            // For dry_run CLI we call the adapter directly.
            \WP_CLI::log( "Running dry_run for settlement {$settlement_id} via adapter {$adapter_name}…" );
            $dry_result = $adapter->dry_run( [ 'settlement_id' => $settlement_id ], [] );
            \WP_CLI::success( wp_json_encode( $dry_result ) );
            return;
        }

        // ── Force: clear idempotency so re-delivery is possible ───────────────
        if ( $force && null !== $settlement_id ) {
            $store = new DeliveryIdempotencyStore();
            $store->invalidate( $settlement_id, $adapter_name );
            \WP_CLI::log( "Idempotency cleared for {$settlement_id} / {$adapter_name}." );
        }

        // ── Single settlement ─────────────────────────────────────────────────
        if ( null !== $settlement_id ) {
            \WP_CLI::log( "Delivering settlement {$settlement_id} via {$adapter_name}…" );
            try {
                $row = $this->service->deliver( $settlement_id, $adapter );
                \WP_CLI::success( wp_json_encode( $row ) );
            } catch ( \Throwable $e ) {
                \WP_CLI::error( $e->getMessage() );
            }
            return;
        }

        // ── Batch: deliver all undelivered settlements ─────────────────────────
        $batch_filters = array_filter( [
            'sponsor_id' => $assoc_args['sponsor_id'] ?? null,
            'date_start' => $assoc_args['date_start'] ?? null,
            'date_end'   => $assoc_args['date_end']   ?? null,
        ] );

        \WP_CLI::log( "Running scheduled delivery batch via {$adapter_name}…" );
        $results = $this->service->run_scheduled( $adapter, $batch_filters );

        if ( empty( $results ) ) {
            \WP_CLI::success( 'No undelivered settlements found.' );
            return;
        }

        \WP_CLI::success( wp_json_encode( $results ) );
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function resolve_adapter( string $name ) {
        switch ( $name ) {
            case 'sftp':
                return $this->sftp;
            case 'accounting_api':
                return $this->api;
        }
        return null;
    }
}
