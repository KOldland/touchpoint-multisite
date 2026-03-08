<?php

namespace KHM\CLI;

use KHM\Membership\RetentionWorker;

if ( ! defined( 'WP_CLI' ) || ! constant( 'WP_CLI' ) ) {
    return;
}

class RetentionRunCommand {
    /**
     * Run attribution retention cleanup now.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview candidate rows without mutating data.
     *
     * [--retention-days=<days>]
     * : Override configured retention window for this run.
     *
     * [--mode=<mode>]
     * : Mode override (`anonymize` or `delete`).
     *
     * [--chunk-size=<size>]
     * : Max rows to process (or preview) in one invocation.
     *
     * @when after_wp_load
     */
    public function __invoke( array $args = [], array $assoc_args = [] ): void {
        $dryRun = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
        $retentionDays = isset( $assoc_args['retention-days'] ) ? (int) $assoc_args['retention-days'] : null;
        $mode = isset( $assoc_args['mode'] ) ? sanitize_key( (string) $assoc_args['mode'] ) : null;
        $chunkSize = isset( $assoc_args['chunk-size'] ) ? (int) $assoc_args['chunk-size'] : RetentionWorker::DEFAULT_CHUNK_SIZE;

        $worker = new RetentionWorker();

        if ( $dryRun ) {
            $preview = $worker->run( true, $retentionDays, $mode, $chunkSize );
            \WP_CLI::success(
                sprintf(
                    'Dry-run complete. mode=%s candidates=%d retention_days=%d cutoff=%s chunk_size=%d',
                    (string) ( $preview['mode'] ?? 'anonymize' ),
                    (int) ( $preview['candidates'] ?? 0 ),
                    (int) ( $preview['retention_days'] ?? 0 ),
                    (string) ( $preview['cutoff'] ?? '' ),
                    (int) ( $preview['chunk_size'] ?? 0 )
                )
            );
            return;
        }

        $result = $worker->run( false, $retentionDays, $mode, $chunkSize );
        if ( ! empty( $result['failed'] ) ) {
            \WP_CLI::error( 'Attribution retention run failed: ' . (string) ( $result['message'] ?? 'unknown error' ) );
            return;
        }

        \WP_CLI::success(
            sprintf(
                'Attribution retention run completed. mode=%s rows_updated=%d retention_days=%d chunk_size=%d',
                (string) ( $result['mode'] ?? 'anonymize' ),
                (int) ( $result['rows_updated'] ?? $result['updated'] ?? 0 ),
                (int) ( $result['retention_days'] ?? 0 ),
                (int) ( $result['chunk_size'] ?? 0 )
            )
        );
    }
}

if ( class_exists( '\\WP_CLI' ) ) {
    call_user_func( [ '\\WP_CLI', 'add_command' ], 'khm retention:run', RetentionRunCommand::class );
}
