<?php

namespace KHM\Membership;

use KHM\Services\MembershipRepository;

class RetentionWorker {
    public const HOOK = 'khm_cleanup_attribution';
    public const DEFAULT_CHUNK_SIZE = 1000;
    private const DEFAULT_MAX_BATCHES = 200;
    private const PROGRESS_OPTION = 'khm_retention_last_progress';

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
        $maxBatches = max( 1, (int) apply_filters( 'khm_retention_max_batches_per_run', self::DEFAULT_MAX_BATCHES ) );
        $backpressureUs = max( 0, (int) apply_filters( 'khm_retention_backpressure_microseconds', 0 ) );

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
            $totalMatched = 0;
            $totalUpdated = 0;
            $lastId = 0;
            $processedBatches = 0;
            $startedAt = microtime( true );
            $cutoff = '';

            while ( $processedBatches < $maxBatches ) {
                $processedBatches++;
                $batchStartedAt = microtime( true );

                if ( 'delete' === $mode ) {
                    $batch = $repository->deleteExpiredAttributionBatch( $retentionDays, $chunkSize, $lastId );
                    $matched = (int) ( $batch['matched'] ?? 0 );
                    $updated = (int) ( $batch['deleted'] ?? 0 );
                } else {
                    $batch = $repository->anonymizeExpiredAttributionBatch( $retentionDays, $chunkSize, $lastId );
                    $matched = (int) ( $batch['matched'] ?? 0 );
                    $updated = (int) ( $batch['updated'] ?? 0 );
                }

                $cutoff = (string) ( $batch['cutoff'] ?? $cutoff );
                $lastId = (int) ( $batch['last_id'] ?? $lastId );
                $totalMatched += $matched;
                $totalUpdated += $updated;

                $this->save_progress( [
                    'mode' => $mode,
                    'retention_days' => $retentionDays,
                    'chunk_size' => $chunkSize,
                    'batch' => $processedBatches,
                    'last_id' => $lastId,
                    'matched_total' => $totalMatched,
                    'updated_total' => $totalUpdated,
                    'cutoff' => $cutoff,
                    'updated_at' => gmdate( 'c' ),
                ] );

                do_action( 'khm_membership_reporting_telemetry', 'membership.retention.batch.completed', [
                    'mode' => $mode,
                    'batch' => $processedBatches,
                    'matched' => $matched,
                    'updated' => $updated,
                    'duration_ms' => (int) round( ( microtime( true ) - $batchStartedAt ) * 1000 ),
                    'last_id' => $lastId,
                    'chunk_size' => $chunkSize,
                ] );

                if ( $matched === 0 ) {
                    break;
                }

                if ( $backpressureUs > 0 ) {
                    usleep( $backpressureUs );
                }
            }

            $payload = [
                'mode' => $mode,
                'retention_days' => $retentionDays,
                'rows_updated' => $totalUpdated,
                'matched' => $totalMatched,
                'chunk_size' => $chunkSize,
                'batches' => $processedBatches,
                'last_id' => $lastId,
                'cutoff' => $cutoff,
                'duration_ms' => (int) round( ( microtime( true ) - $startedAt ) * 1000 ),
            ];

            do_action( 'khm_membership_reporting_telemetry', 'membership.retention.' . $mode . '.completed', [
                'mode' => $mode,
                'retention_days' => $retentionDays,
                'rows_updated' => $totalUpdated,
                'matched' => $totalMatched,
                'chunk_size' => $chunkSize,
                'batches' => $processedBatches,
                'last_id' => $lastId,
                'cutoff' => $cutoff,
                'duration_ms' => $payload['duration_ms'],
            ] );

            return $payload;
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
     * @param array<string,mixed> $payload
     */
    private function save_progress( array $payload ): void {
        update_site_option( self::PROGRESS_OPTION, $payload );
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
