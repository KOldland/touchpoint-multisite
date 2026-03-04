<?php

namespace KHM\CLI;

use KHM\Services\MembershipRepository;

if ( ! defined( 'WP_CLI' ) || ! constant( 'WP_CLI' ) ) {
    return;
}

class AnonymizeAttributionCommand {
    /**
     * Anonymize attribution records.
     *
     * ## OPTIONS
     *
     * [--id=<id>]
     * : Anonymize a single attribution row by ID.
     *
     * [--batch]
     * : Run in batch mode using --filter criteria.
     *
     * [--filter=<filter>]
     * : Filter expression, e.g. "consent=false AND created_at < '2025-01-01'".
     *
     * [--limit=<n>]
     * : Max rows in batch mode.
     * ---
     * default: 500
     * ---
     *
     * [--reason=<text>]
     * : Anonymization reason.
     * ---
     * default: cli_manual
     * ---
     *
     * [--dry-run]
     * : Preview matching rows without updating.
     *
     * @when after_wp_load
     */
    public function __invoke( array $args, array $assoc_args ): void {
        $id = isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : 0;
        $batch = \WP_CLI\Utils\get_flag_value( $assoc_args, 'batch', false );
        $dryRun = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
        $reason = isset( $assoc_args['reason'] ) ? sanitize_text_field( (string) $assoc_args['reason'] ) : 'cli_manual';
        $limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 500;
        $limit = max( 1, min( 5000, $limit ) );

        $repository = new MembershipRepository();

        if ( $id > 0 ) {
            if ( $dryRun ) {
                \WP_CLI::success( sprintf( 'Dry-run: would anonymize attribution row id=%d', $id ) );
                return;
            }

            $ok = $repository->anonymizeAttributionById( $id, 0, $reason );
            if ( ! $ok ) {
                \WP_CLI::error( sprintf( 'Failed to anonymize id=%d', $id ) );
            }

            \WP_CLI::success( sprintf( 'Anonymized attribution row id=%d', $id ) );
            return;
        }

        if ( ! $batch ) {
            \WP_CLI::error( 'Specify --id=<id> or --batch with --filter.' );
        }

        $filterText = isset( $assoc_args['filter'] ) ? (string) $assoc_args['filter'] : '';
        $filters = $this->parse_filters( $filterText );

        $result = $repository->anonymizeAttributionByFilters( $filters, 0, $reason, $limit, $dryRun );

        \WP_CLI::success(
            sprintf(
                'Batch complete. matched=%d updated=%d dry_run=%s',
                (int) ( $result['matched'] ?? 0 ),
                (int) ( $result['updated'] ?? 0 ),
                $dryRun ? 'true' : 'false'
            )
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function parse_filters( string $filterText ): array {
        $filters = [];

        if ( preg_match( '/consent\s*=\s*(false|0)/i', $filterText ) ) {
            $filters['consent'] = 0;
        } elseif ( preg_match( '/consent\s*=\s*(true|1)/i', $filterText ) ) {
            $filters['consent'] = 1;
        }

        if ( preg_match( "/created_at\s*<\s*'([^']+)'/i", $filterText, $match ) ) {
            $filters['created_before'] = sanitize_text_field( (string) $match[1] );
        }

        return $filters;
    }
}

if ( class_exists( '\\WP_CLI' ) ) {
    call_user_func( [ '\\WP_CLI', 'add_command' ], 'khm anonymize_attribution', AnonymizeAttributionCommand::class );
}
