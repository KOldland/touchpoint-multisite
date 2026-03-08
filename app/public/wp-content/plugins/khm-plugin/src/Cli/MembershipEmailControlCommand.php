<?php

namespace KHM\CLI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class MembershipEmailControlCommand {
    /**
     * Control staged rollout state for membership transactional emails.
     *
     * ## OPTIONS
     *
     * --mode=<mode>
     * : One of status, enable, disable, canary, rollback.
     *
     * [--canary-percent=<n>]
     * : Canary percentage to record when mode=canary.
     * ---
     * default: 5
     * ---
     *
     * [--reason=<text>]
     * : Optional rollout/rollback reason.
     *
     * [--dry-run]
     * : Show intended changes without persisting options.
     *
     * @when after_wp_load
     */
    public function __invoke( array $args, array $assoc_args ): void {
        $mode = isset( $assoc_args['mode'] ) ? sanitize_key( (string) $assoc_args['mode'] ) : 'status';
        $reason = isset( $assoc_args['reason'] ) ? sanitize_text_field( (string) $assoc_args['reason'] ) : '';
        $dryRun = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

        if ( ! in_array( $mode, [ 'status', 'enable', 'disable', 'canary', 'rollback' ], true ) ) {
            \WP_CLI::error( 'Invalid mode. Use status|enable|disable|canary|rollback.' );
        }

        $enabled = (int) get_option( 'khm_membership_transactional_emails_enabled', 0 );
        $releaseMode = (string) get_option( 'khm_membership_release_mode', 'disabled' );
        $canaryPercent = (int) get_option( 'khm_membership_release_canary_percent', 0 );

        if ( 'status' === $mode ) {
            \WP_CLI::log( sprintf( 'transactional_emails_enabled=%d', $enabled ) );
            \WP_CLI::log( sprintf( 'release_mode=%s', $releaseMode ) );
            \WP_CLI::log( sprintf( 'canary_percent=%d', $canaryPercent ) );
            return;
        }

        $nextEnabled = $enabled;
        $nextReleaseMode = $releaseMode;
        $nextCanary = $canaryPercent;

        if ( 'enable' === $mode ) {
            $nextEnabled = 1;
            $nextReleaseMode = 'enabled';
            $nextCanary = 100;
        } elseif ( 'disable' === $mode ) {
            $nextEnabled = 0;
            $nextReleaseMode = 'disabled';
            $nextCanary = 0;
        } elseif ( 'canary' === $mode ) {
            $requested = isset( $assoc_args['canary-percent'] ) ? (int) $assoc_args['canary-percent'] : 5;
            $requested = max( 1, min( 100, $requested ) );
            $nextEnabled = 1;
            $nextReleaseMode = 'canary';
            $nextCanary = $requested;
        } elseif ( 'rollback' === $mode ) {
            $nextEnabled = 0;
            $nextReleaseMode = 'rollback';
            $nextCanary = 0;
        }

        if ( $dryRun ) {
            \WP_CLI::success(
                sprintf(
                    'Dry-run: would set transactional_emails_enabled=%d release_mode=%s canary_percent=%d',
                    $nextEnabled,
                    $nextReleaseMode,
                    $nextCanary
                )
            );
            return;
        }

        update_option( 'khm_membership_transactional_emails_enabled', $nextEnabled );
        update_option( 'khm_membership_release_mode', $nextReleaseMode );
        update_option( 'khm_membership_release_canary_percent', $nextCanary );
        update_option( 'khm_membership_release_last_changed_at', gmdate( 'c' ) );

        if ( '' !== $reason ) {
            update_option( 'khm_membership_release_last_reason', $reason );
        }

        if ( 'rollback' === $mode ) {
            update_option( 'khm_membership_release_last_rollback_at', gmdate( 'c' ) );
        }

        \WP_CLI::success(
            sprintf(
                'Updated: transactional_emails_enabled=%d release_mode=%s canary_percent=%d',
                $nextEnabled,
                $nextReleaseMode,
                $nextCanary
            )
        );
    }
}

if ( class_exists( 'WP_CLI' ) ) {
    \WP_CLI::add_command( 'khm membership-email-control', MembershipEmailControlCommand::class );
}
