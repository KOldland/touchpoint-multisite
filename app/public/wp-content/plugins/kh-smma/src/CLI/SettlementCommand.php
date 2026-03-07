<?php
/**
 * Settlement WP-CLI Command
 *
 * Exposes SettlementWorker::run() as `wp kh-smma paid:settle`.
 *
 * @package KH_SMMA\CLI
 * @see     docs/paid/finance_reconciliation_runbook.md
 */

namespace KH_SMMA\CLI;

use KH_SMMA\Reconciliation\SettlementWorker;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettlementCommand {

    /** @var SettlementWorker */
    private $worker;

    public function __construct( SettlementWorker $worker ) {
        $this->worker = $worker;
    }

    public function register(): void {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
            return;
        }

        WP_CLI::add_command( 'kh-smma paid:settle', $this );
    }

    /**
     * Run the settlement batch for reconciled/discrepancy rows.
     *
     * ## OPTIONS
     *
     * [--sponsor_id=<sponsor_id>]
     * : Limit settlement to a single sponsor ID.
     *
     * [--currency=<currency>]
     * : Limit settlement to rows with this source currency (ISO 4217).
     *
     * [--target_currency=<currency>]
     * : Convert totals to this currency via FxService. Defaults to source currency.
     *
     * [--date_start=<YYYY-MM-DD>]
     * : Only settle reconciliations created on or after this date.
     *
     * [--date_end=<YYYY-MM-DD>]
     * : Only settle reconciliations created on or before this date.
     *
     * [--batch_size=<n>]
     * : Maximum number of reconciliation rows to process (default 500).
     *
     * ## EXAMPLES
     *
     *     wp kh-smma paid:settle
     *     wp kh-smma paid:settle --sponsor_id=sp_456 --currency=AUD
     *     wp kh-smma paid:settle --date_start=2026-03-01 --date_end=2026-03-31 --batch_size=200
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke( array $args, array $assoc_args ): void {
        $filters = array_filter( [
            'sponsor_id'      => $assoc_args['sponsor_id'] ?? null,
            'currency'        => $assoc_args['currency'] ?? null,
            'target_currency' => $assoc_args['target_currency'] ?? null,
            'date_start'      => $assoc_args['date_start'] ?? null,
            'date_end'        => $assoc_args['date_end'] ?? null,
            'batch_size'      => isset( $assoc_args['batch_size'] )
                ? (int) $assoc_args['batch_size']
                : null,
        ] );

        WP_CLI::log( 'Running settlement batch…' );

        $results = $this->worker->run( $filters );

        if ( empty( $results ) ) {
            WP_CLI::success( 'No unsettled reconciliations found. Nothing to do.' );
            return;
        }

        WP_CLI::success( wp_json_encode( $results ) );
    }
}
