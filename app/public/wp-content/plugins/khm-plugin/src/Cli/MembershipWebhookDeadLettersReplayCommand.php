<?php

namespace KHM\CLI;

use KHM\Membership\MembershipWebhookDeadLetterStore;
use WP_CLI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class MembershipWebhookDeadLettersReplayCommand {
    /**
     * Replay membership webhook dead-letter events.
     *
     * ## OPTIONS
     *
     * [--id=<id>]
     * : Replay a specific dead-letter row ID.
     *
     * [--all-open]
     * : Replay all open dead-letter rows.
     *
     * [--limit=<n>]
     * : Max open rows to replay when using --all-open.
     * ---
     * default: 20
     * ---
     *
     * @when after_wp_load
     */
    public function __invoke( array $args, array $assoc_args ): void {
        $id = isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : 0;
        $all_open = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all-open', false );
        $limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 20;
        $limit = max( 1, min( 200, $limit ) );

        if ( $id <= 0 && ! $all_open ) {
            WP_CLI::error( 'Specify either --id=<id> or --all-open.' );
        }

        $rows = [];
        if ( $id > 0 ) {
            $row = MembershipWebhookDeadLetterStore::get_by_id( $id );
            if ( ! is_array( $row ) ) {
                WP_CLI::error( sprintf( 'Dead-letter id %d not found.', $id ) );
            }
            $rows[] = $row;
        } else {
            $rows = MembershipWebhookDeadLetterStore::list_open( $limit );
            if ( empty( $rows ) ) {
                WP_CLI::warning( 'No open membership webhook dead letters found.' );
                return;
            }
        }

        $success = 0;
        $failed = 0;

        foreach ( $rows as $row ) {
            $row_id = (int) ( $row['id'] ?? 0 );
            $payload = isset( $row['payload'] ) ? (string) $row['payload'] : '';
            $event = json_decode( $payload, true );
            if ( ! is_array( $event ) ) {
                $failed++;
                WP_CLI::warning( sprintf( 'Replay failed id=%d: payload is not valid JSON.', $row_id ) );
                continue;
            }

            $event_id = isset( $event['event_id'] ) ? sanitize_text_field( (string) $event['event_id'] ) : '';
            $event_type = isset( $event['event_type'] ) ? sanitize_text_field( (string) $event['event_type'] ) : '';
            $data_object = isset( $event['data_object'] ) && is_array( $event['data_object'] ) ? $event['data_object'] : [];

            if ( '' === $event_id || '' === $event_type ) {
                $failed++;
                WP_CLI::warning( sprintf( 'Replay failed id=%d: missing event_id/event_type.', $row_id ) );
                continue;
            }

            $job = [
                'event_id' => $event_id,
                'event_type' => $event_type,
                'data_object' => $data_object,
                'event_created' => isset( $event['event_created'] ) ? (int) $event['event_created'] : 0,
                'trace_id' => wp_generate_uuid4(),
            ];

            $queued = false;
            if ( function_exists( 'wp_schedule_single_event' ) ) {
                $queued = (bool) wp_schedule_single_event( time() + 1, 'khm_process_membership_stripe_webhook_event', [ $job ] );
            }
            if ( ! $queued ) {
                do_action( 'khm_process_membership_stripe_webhook_event', $job );
                $queued = true;
            }

            if ( ! $queued ) {
                $failed++;
                WP_CLI::warning( sprintf( 'Replay failed id=%d: enqueue returned false.', $row_id ) );
                continue;
            }

            MembershipWebhookDeadLetterStore::mark_resolved( $row_id );
            $success++;
            WP_CLI::success( sprintf( 'Requeued dead-letter id=%d event_id=%s', $row_id, $event_id ) );
        }

        if ( $failed > 0 ) {
            WP_CLI::warning( sprintf( 'Replay completed with failures. success=%d failed=%d', $success, $failed ) );
            return;
        }

        WP_CLI::success( sprintf( 'Replay completed. success=%d failed=%d', $success, $failed ) );
    }
}

if ( class_exists( 'WP_CLI' ) ) {
    WP_CLI::add_command( 'khm membership-webhook-dead-letters-replay', MembershipWebhookDeadLettersReplayCommand::class );
}
