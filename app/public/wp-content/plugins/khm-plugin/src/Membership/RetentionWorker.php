<?php

namespace KHM\Membership;

use KHM\Services\MembershipRepository;

class RetentionWorker {
    public const HOOK = 'khm_cleanup_attribution';

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

    public function run(): void {
        $retentionDays = (int) get_site_option( 'khm_attribution_retention_days', 730 );
        $retentionDays = max( 1, $retentionDays );
        $mode = sanitize_key( (string) get_site_option( 'khm_attribution_retention_mode', 'anonymize' ) );
        if ( ! in_array( $mode, [ 'anonymize', 'delete' ], true ) ) {
            $mode = 'anonymize';
        }

        $repository = new MembershipRepository();

        try {
            if ( 'delete' === $mode ) {
                $updated = $repository->deleteExpiredAttribution( $retentionDays, 1000 );
                do_action( 'khm_membership_reporting_telemetry', 'membership.retention.delete.completed', [
                    'mode' => $mode,
                    'retention_days' => $retentionDays,
                    'rows_updated' => $updated,
                ] );
                return;
            }

            $result = $repository->anonymizeExpiredAttribution( $retentionDays, 1000 );
            do_action( 'khm_membership_reporting_telemetry', 'membership.retention.anonymize.completed', [
                'mode' => $mode,
                'retention_days' => $retentionDays,
                'rows_updated' => (int) ( $result['updated'] ?? 0 ),
                'cutoff' => (string) ( $result['cutoff'] ?? '' ),
            ] );
        } catch ( \Throwable $e ) {
            do_action( 'khm_membership_reporting_telemetry', 'membership.anonymize.failed', [
                'mode' => $mode,
                'retention_days' => $retentionDays,
                'message' => $e->getMessage(),
            ] );
            error_log( 'membership_retention_failed message=' . $e->getMessage() );
        }
    }
}
