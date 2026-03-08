<?php
declare( strict_types=1 );

namespace KH_SMMA\CLI;

use KH_SMMA\Adapters\ReconciliationService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PAID-08 — WP-CLI reconciliation command.
 *
 * Usage:
 *   wp kh-smma paid:reconcile run [--sponsor_id=<id>] [--adapter=<slug>] [--date_start=<date>] [--date_end=<date>] [--dry_run]
 *   wp kh-smma paid:reconcile export --run_id=<id>
 */
class ReconcileCommand {

    private ReconciliationService $service;

    public function __construct( ReconciliationService $service ) {
        $this->service = $service;
    }

    public function register(): void {
        if ( ! class_exists( 'WP_CLI' ) ) {
            return;
        }
        \WP_CLI::add_command( 'kh-smma paid:reconcile', [ $this, '__invoke' ] );
    }

    /**
     * @param string[] $args        Positional args; $args[0] = subcommand ('run'|'export').
     * @param array<string,string> $assoc_args Named flags.
     */
    public function __invoke( array $args, array $assoc_args ): void {
        $subcommand = $args[0] ?? 'run';

        switch ( $subcommand ) {
            case 'run':
                $this->run_reconcile( $assoc_args );
                break;
            case 'export':
                $this->run_export( $assoc_args );
                break;
            default:
                \WP_CLI::error( "Unknown subcommand '{$subcommand}'. Use 'run' or 'export'." );
        }
    }

    // ── Subcommands ───────────────────────────────────────────────────────────

    private function run_reconcile( array $assoc_args ): void {
        $sponsor_id = $assoc_args['sponsor_id'] ?? '';
        $adapter    = $assoc_args['adapter']    ?? '';
        $date_start = $assoc_args['date_start'] ?? '';
        $date_end   = $assoc_args['date_end']   ?? '';
        $dry_run    = isset( $assoc_args['dry_run'] );

        $adapters = array_filter( array_map( 'trim', explode( ',', $adapter ) ) );

        $run = $this->service->start_run( [
            'sponsor_id' => $sponsor_id,
            'adapters'   => $adapters,
            'date_start' => $date_start,
            'date_end'   => $date_end,
            'initiator'  => 'cli',
        ] );

        \WP_CLI::log( "Created run: {$run['run_id']} (status: pending)" );

        if ( $dry_run ) {
            \WP_CLI::success( "Dry run — run created but not executed. run_id={$run['run_id']}" );
            return;
        }

        \WP_CLI::log( 'Executing run...' );
        $completed = $this->service->execute_run( $run['run_id'] );

        \WP_CLI::log( sprintf(
            'Run completed: total=%d matched=%d variance=%d unmatched=%d checksum=%s',
            $completed['total_rows']     ?? 0,
            $completed['matched_rows']   ?? 0,
            $completed['variance_rows']  ?? 0,
            $completed['unmatched_rows'] ?? 0,
            $completed['checksum']       ?? ''
        ) );
        \WP_CLI::success( "run_id={$run['run_id']}" );
    }

    private function run_export( array $assoc_args ): void {
        $run_id = $assoc_args['run_id'] ?? '';
        if ( empty( $run_id ) ) {
            \WP_CLI::error( '--run_id is required for export subcommand.' );
        }

        $export = $this->service->export_run( $run_id, 0 );

        $filename = 'recon_run_' . $run_id . '.csv';
        file_put_contents( $filename, $export['csv'] );

        \WP_CLI::success( sprintf(
            "Exported %d rows to %s (checksum=%s)",
            $export['row_count'],
            $filename,
            $export['checksum']
        ) );
    }
}
