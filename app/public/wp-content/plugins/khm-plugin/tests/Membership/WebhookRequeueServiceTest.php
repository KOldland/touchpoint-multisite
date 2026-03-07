<?php

namespace WP_CLI\Utils {
    if ( ! function_exists( __NAMESPACE__ . '\\get_flag_value' ) ) {
        function get_flag_value( $assoc_args, $flag, $default = false ) {
            return $assoc_args[ $flag ] ?? $default;
        }
    }
}

namespace {
    if ( ! class_exists( 'WP_CLI' ) ) {
        class WP_CLI {
            public static array $lines = [];
            public static array $successes = [];
            public static array $warnings = [];
            public static array $errors = [];

            public static function line( $message ): void { self::$lines[] = (string) $message; }
            public static function success( $message ): void { self::$successes[] = (string) $message; }
            public static function warning( $message ): void { self::$warnings[] = (string) $message; }
            public static function error( $message ): void {
                self::$errors[] = (string) $message;
                throw new \RuntimeException( (string) $message );
            }
        }
    }
}

namespace KHM\Tests\Membership {

use KHM\CLI\MembershipWebhookDeadLettersReplayCommand;
use KHM\Membership\MembershipWebhookDeadLetterStore;
use KHM\Services\MembershipRepository;
use KHM\Services\WebhookService;
use PHPUnit\Framework\TestCase;

class WebhookRequeueServiceTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        global $wpdb;
        $wpdb->query( "CREATE TABLE {$wpdb->prefix}khm_webhook_dead_letter (id bigint unsigned)" );
        $wpdb->query( "CREATE TABLE {$wpdb->prefix}khm_processed_webhook_events (id bigint unsigned)" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_webhook_dead_letter" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_processed_webhook_events" );
        \WP_CLI::$lines = [];
        \WP_CLI::$successes = [];
        \WP_CLI::$warnings = [];
        \WP_CLI::$errors = [];
        putenv( 'KHM_STRIPE_TEST_MODE=ci' );
    }

    protected function tearDown(): void {
        putenv( 'KHM_STRIPE_TEST_MODE' );
        parent::tearDown();
    }

    public function test_requeue_reconstructs_temp_store_and_marks_dead_letter_resolved(): void {
        $eventId = 'evt_requeue_001';
        MembershipWebhookDeadLetterStore::store(
            $eventId,
            'checkout.session.completed',
            wp_json_encode(
                [
                    'event_id' => $eventId,
                    'event_type' => 'checkout.session.completed',
                    'data_object' => [
                        'id' => 'cs_requeue_001',
                        'metadata' => [
                            'schedule_id' => '77',
                            'sponsor_id' => '55',
                            'consent' => true,
                        ],
                    ],
                ]
            ) ?: '',
            'processing_failed',
            'temp store missing'
        );

        $capturedJob = null;
        $service = new WebhookService(
            static function ( array $job ) use ( &$capturedJob ): void {
                $capturedJob = $job;
            }
        );

        $result = $service->requeueWebhookEvent( $eventId );

        $this->assertSame( 'success', $result['status'] );
        $this->assertTrue( (bool) $result['reconstructed'] );
        $this->assertSame( 'checkout.session.completed', $capturedJob['event_type'] ?? '' );

        $repo = new MembershipRepository();
        $temp = $repo->getTempAttribution( 'cs_requeue_001' );
        $this->assertIsArray( $temp );
        $this->assertSame( '77', (string) ( $temp['payload']['schedule_id'] ?? '' ) );

        $dlq = MembershipWebhookDeadLetterStore::get_by_event_id( $eventId );
        $this->assertIsArray( $dlq );
        $this->assertSame( 'resolved', (string) ( $dlq['status'] ?? '' ) );

        global $wpdb;
        $processed = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}khm_processed_webhook_events WHERE event_id = %s", $eventId ),
            ARRAY_A
        );
        $this->assertIsArray( $processed );
        $this->assertSame( 'processed', (string) ( $processed['status'] ?? '' ) );
    }

    public function test_requeue_requires_human_secret_when_metadata_cannot_be_reconstructed(): void {
        putenv( 'KHM_STRIPE_TEST_MODE' );

        $eventId = 'evt_requeue_secret_001';
        MembershipWebhookDeadLetterStore::store(
            $eventId,
            'checkout.session.completed',
            wp_json_encode(
                [
                    'event_id' => $eventId,
                    'event_type' => 'checkout.session.completed',
                    'data_object' => [
                        'id' => 'cs_requeue_secret_001',
                        'metadata' => [],
                    ],
                ]
            ) ?: '',
            'processing_failed',
            'temp store missing'
        );

        $service = new WebhookService();
        $result = $service->requeueWebhookEvent( $eventId );

        $this->assertSame( 'require_human_secret', $result['status'] );
        $this->assertSame( 'REQUIRE_HUMAN_SECRET', $result['reason'] );

        $dlq = MembershipWebhookDeadLetterStore::get_by_event_id( $eventId );
        $this->assertIsArray( $dlq );
        $this->assertSame( 'open', (string) ( $dlq['status'] ?? '' ) );
    }

    public function test_cli_replays_specific_event_id(): void {
        $eventId = 'evt_requeue_cli_001';
        MembershipWebhookDeadLetterStore::store(
            $eventId,
            'checkout.session.completed',
            wp_json_encode(
                [
                    'event_id' => $eventId,
                    'event_type' => 'checkout.session.completed',
                    'data_object' => [
                        'id' => 'cs_requeue_cli_001',
                        'metadata' => [
                            'schedule_id' => '88',
                        ],
                    ],
                ]
            ) ?: '',
            'processing_failed'
        );

        $service = new WebhookService(
            static function ( array $job ): void {
            }
        );
        $command = new MembershipWebhookDeadLettersReplayCommand( $service );
        $command->__invoke( [], [ 'event-id' => $eventId ] );

        $this->assertNotEmpty( \WP_CLI::$lines );
        $this->assertNotEmpty( \WP_CLI::$successes );
    }
}
}
