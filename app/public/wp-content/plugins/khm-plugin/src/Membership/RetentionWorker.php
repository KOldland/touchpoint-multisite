<?php

namespace KHM\Membership;

use KHM\Services\MembershipRepository;

class RetentionWorker {
    public const HOOK = 'khm_cleanup_attribution';
    public const DEFAULT_CHUNK_SIZE = 1000;

    public function register(): void {
        add_action( 'init', [ $this, 'schedule' ] );
        add_action( self::HOOK, [ $this, 'run' ] );
    }

    public function schedule(): void {
        if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
            return;
        }

        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 300, 'daily', self::HOOK );
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function run( bool $dryRun = false, ?int $retentionDaysOverride = null, ?string $modeOverride = null, int $chunkSize = self::DEFAULT_CHUNK_SIZE ): array {
        $retentionDays = (int) get_site_option( 'khm_attribution_retention_days', 730 );
        if ( null !== $retentionDaysOverride ) {
            $retentionDays = (int) $retentionDaysOverride;
        }
        $retentionDays = max( 1, $retentionDays );
        $mode = sanitize_key( (string) get_site_option( 'khm_attribution_retention_mode', 'anonymize' ) );
        if ( null !== $modeOverride ) {
            $mode = sanitize_key( $modeOverride );
        }
        if ( ! in_array( $mode, [ 'anonymize', 'delete' ], true ) ) {
            $mode = 'anonymize';
        }

        $chunkSize = max( 1, min( 5000, (int) $chunkSize ) );

        $repository = new MembershipRepository();

        if ( $dryRun ) {
            $preview = $this->preview( $retentionDays, $mode, $chunkSize );
            do_action( 'khm_membership_reporting_telemetry', 'membership.retention.preview.completed', [
                'mode' => $mode,
                'retention_days' => $retentionDays,
                'candidates' => (int) ( $preview['candidates'] ?? 0 ),
                'chunk_size' => $chunkSize,
                'cutoff' => (string) ( $preview['cutoff'] ?? '' ),
            ] );
            return $preview;
        }

        try {
            if ( 'delete' === $mode ) {
                $updated = $repository->deleteExpiredAttribution( $retentionDays, $chunkSize );
                $payload = [
                    'mode' => $mode,
                    'retention_days' => $retentionDays,
                    'rows_updated' => $updated,
                    'chunk_size' => $chunkSize,
                ];
                do_action( 'khm_membership_reporting_telemetry', 'membership.retention.delete.completed', [
                    'mode' => $mode,
                    'retention_days' => $retentionDays,
                    'rows_updated' => $updated,
                    'chunk_size' => $chunkSize,
                ] );
                return $payload;
            }

            $result = $repository->anonymizeExpiredAttribution( $retentionDays, $chunkSize );
            $result['mode'] = $mode;
            $result['retention_days'] = $retentionDays;
            $result['chunk_size'] = $chunkSize;
            do_action( 'khm_membership_reporting_telemetry', 'membership.retention.anonymize.completed', [
                'mode' => $mode,
                'retention_days' => $retentionDays,
                'rows_updated' => (int) ( $result['updated'] ?? 0 ),
                'cutoff' => (string) ( $result['cutoff'] ?? '' ),
                'chunk_size' => $chunkSize,
            ] );
            return $result;
        } catch ( \Throwable $e ) {
            do_action( 'khm_membership_reporting_telemetry', 'membership.anonymize.failed', [
                'mode' => $mode,
                'retention_days' => $retentionDays,
                'message' => $e->getMessage(),
            ] );
            error_log( 'membership_retention_failed message=' . $e->getMessage() );
            return [
                'mode' => $mode,
                'retention_days' => $retentionDays,
                'failed' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function preview( int $retentionDays, string $mode, int $chunkSize = self::DEFAULT_CHUNK_SIZE ): array {
        $retentionDays = max( 1, $retentionDays );
        $mode = in_array( $mode, [ 'anonymize', 'delete' ], true ) ? $mode : 'anonymize';
        $chunkSize = max( 1, min( 5000, (int) $chunkSize ) );

        $repository = new MembershipRepository();
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retentionDays * 86400 ) );
        $result = $repository->anonymizeAttributionByFilters(
            [
                'created_before' => $cutoff,
            ],
            0,
            'retention_preview',
            $chunkSize,
            true
        );

        return [
            'mode' => $mode,
            'retention_days' => $retentionDays,
            'chunk_size' => $chunkSize,
            'candidates' => (int) ( $result['matched'] ?? 0 ),
            'cutoff' => $cutoff,
            'ids' => $result['ids'] ?? [],
            'dry_run' => true,
        ];
    }
}
