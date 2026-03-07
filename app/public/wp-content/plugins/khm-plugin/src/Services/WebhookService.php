<?php

namespace KHM\Services;

use KHM\Membership\MembershipWebhookDeadLetterStore;
use KHM\Membership\StripeWebhookHandler;

class WebhookService {
    /** @var callable */
    private $processor;

    /** @var callable|null */
    private $sessionRetriever;

    public function __construct( ?callable $processor = null, ?callable $sessionRetriever = null ) {
        $this->processor = $processor ?: static function ( array $job ): void {
            $handler = new StripeWebhookHandler();
            $handler->process_queued_event( $job );
        };
        $this->sessionRetriever = $sessionRetriever;
    }

    /**
     * @return array<string,mixed>
     */
    public function requeueWebhookEvent( string $event_id ): array {
        global $wpdb;

        $event_id = sanitize_text_field( $event_id );
        if ( '' === $event_id ) {
            return $this->result( 'failed', 'missing_event_id' );
        }

        $row = MembershipWebhookDeadLetterStore::get_by_event_id( $event_id );
        if ( ! is_array( $row ) ) {
            return $this->result( 'failed', 'dead_letter_not_found', [ 'event_id' => $event_id ] );
        }

        $row_id = (int) ( $row['id'] ?? 0 );
        $payload = isset( $row['payload'] ) ? (string) $row['payload'] : '';
        $event = json_decode( $payload, true );
        if ( ! is_array( $event ) ) {
            MembershipWebhookDeadLetterStore::mark_open_with_attempt( $row_id, 'payload is not valid JSON' );
            return $this->result( 'failed', 'invalid_payload', [ 'event_id' => $event_id, 'attempts' => (int) ( $row['attempts'] ?? 0 ) + 1 ] );
        }

        $event_type = sanitize_text_field( (string) ( $event['event_type'] ?? $event['type'] ?? $row['event_type'] ?? '' ) );
        $data_object = $this->extract_data_object( $event );
        if ( '' === $event_type || empty( $data_object ) ) {
            MembershipWebhookDeadLetterStore::mark_open_with_attempt( $row_id, 'missing event_type or data object' );
            return $this->result( 'failed', 'missing_event_fields', [ 'event_id' => $event_id, 'attempts' => (int) ( $row['attempts'] ?? 0 ) + 1 ] );
        }

        $session_id = $this->extract_session_id( $data_object );
        $reconstructed = false;
        if ( '' !== $session_id && ! $this->temp_attribution_exists( $session_id ) ) {
            $reconstruction = $this->reconstruct_temp_attribution( $session_id, $data_object, $event_type );
            if ( $reconstruction['status'] === 'require_human_secret' ) {
                MembershipWebhookDeadLetterStore::mark_open_with_attempt( $row_id, (string) $reconstruction['reason'] );
                return $this->result(
                    'require_human_secret',
                    (string) $reconstruction['reason'],
                    [
                        'event_id' => $event_id,
                        'reconstructed' => false,
                        'attempts' => (int) ( $row['attempts'] ?? 0 ) + 1,
                    ]
                );
            }

            if ( $reconstruction['status'] === 'failed' ) {
                MembershipWebhookDeadLetterStore::mark_open_with_attempt( $row_id, (string) $reconstruction['reason'] );
                return $this->result(
                    'failed',
                    (string) $reconstruction['reason'],
                    [
                        'event_id' => $event_id,
                        'reconstructed' => false,
                        'attempts' => (int) ( $row['attempts'] ?? 0 ) + 1,
                    ]
                );
            }

            $reconstructed = true;
        }

        $this->ensure_processed_event_claim( $event_id, $event_type, $payload );

        $job = [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'data_object' => $data_object,
            'event_created' => isset( $event['event_created'] ) ? (int) $event['event_created'] : (int) ( $event['created'] ?? 0 ),
            'trace_id' => wp_generate_uuid4(),
        ];

        try {
            $wpdb->query( 'START TRANSACTION' );
            call_user_func( $this->processor, $job );
            $this->mark_processed_event_status( $event_id, $event_type, 'processed', 'requeue success' );
            MembershipWebhookDeadLetterStore::mark_resolved( $row_id );
            $wpdb->query( 'COMMIT' );
        } catch ( \Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            $this->mark_processed_event_status( $event_id, $event_type, 'failed', $e->getMessage() );
            MembershipWebhookDeadLetterStore::mark_open_with_attempt( $row_id, $e->getMessage() );

            error_log(
                sprintf(
                    'requeue: event=%s reconstructed=%s attempts=%d reason=%s',
                    $event_id,
                    $reconstructed ? 'true' : 'false',
                    (int) ( $row['attempts'] ?? 0 ) + 1,
                    $e->getMessage()
                )
            );

            return $this->result(
                'failed',
                $e->getMessage(),
                [
                    'event_id' => $event_id,
                    'reconstructed' => $reconstructed,
                    'attempts' => (int) ( $row['attempts'] ?? 0 ) + 1,
                ]
            );
        }

        error_log(
            sprintf(
                'requeue: event=%s reconstructed=%s attempts=%d reason=success',
                $event_id,
                $reconstructed ? 'true' : 'false',
                (int) ( $row['attempts'] ?? 0 )
            )
        );

        return $this->result(
            'success',
            'requeued',
            [
                'event_id' => $event_id,
                'reconstructed' => $reconstructed,
                'attempts' => (int) ( $row['attempts'] ?? 0 ),
            ]
        );
    }

    /**
     * @param array<string,mixed> $event
     * @return array<string,mixed>
     */
    private function extract_data_object( array $event ): array {
        if ( isset( $event['data_object'] ) && is_array( $event['data_object'] ) ) {
            return $event['data_object'];
        }

        if ( isset( $event['data']['object'] ) && is_array( $event['data']['object'] ) ) {
            return $event['data']['object'];
        }

        return [];
    }

    /**
     * @param array<string,mixed> $data_object
     */
    private function extract_session_id( array $data_object ): string {
        if ( isset( $data_object['id'] ) ) {
            return sanitize_text_field( (string) $data_object['id'] );
        }

        if ( isset( $data_object['session_id'] ) ) {
            return sanitize_text_field( (string) $data_object['session_id'] );
        }

        return '';
    }

    private function temp_attribution_exists( string $session_id ): bool {
        $repository = new MembershipRepository();
        $record = $repository->getTempAttribution( $session_id );
        return is_array( $record ) && ! empty( $record['payload'] );
    }

    /**
     * @param array<string,mixed> $data_object
     * @return array<string,mixed>
     */
    private function reconstruct_temp_attribution( string $session_id, array $data_object, string $event_type ): array {
        $metadata = isset( $data_object['metadata'] ) && is_array( $data_object['metadata'] ) ? $data_object['metadata'] : [];

        if ( empty( $metadata ) ) {
            if ( getenv( 'KHM_STRIPE_TEST_MODE' ) === 'ci' ) {
                $metadata = $this->load_ci_fixture_metadata( $event_type );
            } else {
                $metadata = $this->retrieve_session_metadata( $session_id );
            }
        }

        if ( empty( $metadata ) ) {
            if ( getenv( 'KHM_STRIPE_TEST_MODE' ) !== 'ci' && getenv( 'KH_STRIPE_SECRET_KEY' ) === false ) {
                return [
                    'status' => 'require_human_secret',
                    'reason' => 'REQUIRE_HUMAN_SECRET',
                ];
            }

            return [
                'status' => 'failed',
                'reason' => 'unable_to_reconstruct_temp_store',
            ];
        }

        $repository = new MembershipRepository();
        $stored = $repository->storeTempAttribution( $session_id, $metadata, 86400 );
        if ( ! $stored ) {
            return [
                'status' => 'failed',
                'reason' => 'temp_store_write_failed',
            ];
        }

        return [
            'status' => 'success',
            'reason' => 'reconstructed',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function retrieve_session_metadata( string $session_id ): array {
        if ( is_callable( $this->sessionRetriever ) ) {
            $session = call_user_func( $this->sessionRetriever, $session_id );
            if ( is_array( $session ) ) {
                return isset( $session['metadata'] ) && is_array( $session['metadata'] ) ? $session['metadata'] : [];
            }
            if ( is_object( $session ) && isset( $session->metadata ) ) {
                return is_array( $session->metadata ) ? $session->metadata : (array) $session->metadata;
            }
        }

        if ( getenv( 'KH_STRIPE_SECRET_KEY' ) === false || getenv( 'KH_STRIPE_SECRET_KEY' ) === '' || ! class_exists( '\\Stripe\\StripeClient' ) ) {
            return [];
        }

        try {
            $client = new \Stripe\StripeClient( getenv( 'KH_STRIPE_SECRET_KEY' ) );
            $session = $client->checkout->sessions->retrieve( $session_id, [] );
            if ( is_object( $session ) && isset( $session->metadata ) ) {
                return is_array( $session->metadata ) ? $session->metadata : (array) $session->metadata;
            }
        } catch ( \Throwable $e ) {
            error_log( 'requeue: stripe session retrieve failed ' . $e->getMessage() );
        }

        return [];
    }

    /**
     * @return array<string,mixed>
     */
    private function load_ci_fixture_metadata( string $event_type ): array {
        $map = [
            'checkout.session.completed' => 'checkout_session_completed.json',
        ];

        if ( ! isset( $map[ $event_type ] ) ) {
            return [];
        }

        $path = dirname( __DIR__, 2 ) . '/tests/fixtures/golden/' . $map[ $event_type ];
        if ( ! file_exists( $path ) ) {
            return [];
        }

        $decoded = json_decode( (string) file_get_contents( $path ), true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }

        return isset( $decoded['data']['object']['metadata'] ) && is_array( $decoded['data']['object']['metadata'] )
            ? $decoded['data']['object']['metadata']
            : [];
    }

    private function ensure_processed_event_claim( string $event_id, string $event_type, string $payload ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'khm_processed_webhook_events';
        $exists = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE event_id = %s LIMIT 1", $event_id ),
            ARRAY_A
        );

        if ( is_array( $exists ) ) {
            $wpdb->update(
                $table,
                [
                    'status' => 'processing',
                    'event_type' => $event_type,
                    'payload' => $payload,
                    'payload_hash' => hash( 'sha256', $payload ),
                    'updated_at' => current_time( 'mysql', 1 ),
                ],
                [ 'event_id' => $event_id ],
                [ '%s', '%s', '%s', '%s', '%s' ],
                [ '%s' ]
            );
            return;
        }

        $wpdb->insert(
            $table,
            [
                'event_id' => $event_id,
                'event_type' => $event_type,
                'status' => 'processing',
                'payload' => $payload,
                'payload_hash' => hash( 'sha256', $payload ),
                'attempts' => 1,
                'notes' => 'requeue claimed',
                'created_at' => current_time( 'mysql', 1 ),
                'updated_at' => current_time( 'mysql', 1 ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );
    }

    private function mark_processed_event_status( string $event_id, string $event_type, string $status, string $notes ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'khm_processed_webhook_events';
        $wpdb->update(
            $table,
            [
                'event_type' => $event_type,
                'status' => $status,
                'notes' => substr( $notes, 0, 65535 ),
                'updated_at' => current_time( 'mysql', 1 ),
                'processed_at' => $status === 'processed' ? current_time( 'mysql', 1 ) : null,
            ],
            [ 'event_id' => $event_id ],
            [ '%s', '%s', '%s', '%s', '%s' ],
            [ '%s' ]
        );
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function result( string $status, string $reason, array $extra = [] ): array {
        return array_merge(
            [
                'status' => $status,
                'reason' => $reason,
            ],
            $extra
        );
    }
}
