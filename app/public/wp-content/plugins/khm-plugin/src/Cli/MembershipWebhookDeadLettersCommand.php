<?php

namespace KHM\CLI;

use KHM\Membership\MembershipWebhookDeadLetterStore;
use WP_CLI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class MembershipWebhookDeadLettersCommand {
    /**
     * List open membership webhook dead-letter events.
     *
     * ## OPTIONS
     *
     * [--last=<n>]
     * : Number of rows to return.
     * ---
     * default: 50
     * ---
     *
     * [--format=<format>]
     * : Output format: table, csv, json, yaml.
     * ---
     * default: table
     * ---
     *
     * @when after_wp_load
     */
    public function __invoke( array $args, array $assoc_args ): void {
        $limit = isset( $assoc_args['last'] ) ? (int) $assoc_args['last'] : 50;
        $rows = MembershipWebhookDeadLetterStore::list_open( $limit );

        if ( empty( $rows ) ) {
            WP_CLI::warning( 'No open membership webhook dead letters found.' );
            return;
        }

        $items = array_map(
            static function ( array $row ): array {
                return [
                    'id' => (int) ( $row['id'] ?? 0 ),
                    'event_id' => (string) ( $row['event_id'] ?? '' ),
                    'event_type' => (string) ( $row['event_type'] ?? '' ),
                    'reason' => (string) ( $row['reason'] ?? '' ),
                    'attempts' => (int) ( $row['attempts'] ?? 0 ),
                    'error' => (string) ( $row['error_message'] ?? '' ),
                    'created_at' => (string) ( $row['created_at'] ?? '' ),
                    'updated_at' => (string) ( $row['updated_at'] ?? '' ),
                ];
            },
            $rows
        );

        $format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
        $fields = [ 'id', 'event_id', 'event_type', 'reason', 'attempts', 'error', 'created_at', 'updated_at' ];
        \WP_CLI\Utils\format_items( $format, $items, $fields );
    }
}

if ( class_exists( 'WP_CLI' ) ) {
    WP_CLI::add_command( 'khm membership-webhook-dead-letters', MembershipWebhookDeadLettersCommand::class );
}
