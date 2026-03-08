<?php

namespace {
    if ( ! function_exists( 'wp_mail' ) ) {
        function wp_mail( $to, $subject, $message, $headers = '', $attachments = [] ) {
            return isset( $GLOBALS['khm_test_wp_mail_result'] )
                ? (bool) $GLOBALS['khm_test_wp_mail_result']
                : false;
        }
    }
}

namespace KHM\Tests\Membership {

use KHM\Services\EnhancedEmailService;
use PHPUnit\Framework\TestCase;

class EmailDedupeTest extends TestCase {
    private string $pluginDir;

    protected function setUp(): void {
        parent::setUp();

        global $wpdb, $khm_test_options;
        $this->pluginDir = dirname( __DIR__, 2 );
        $GLOBALS['khm_test_wp_mail_result'] = true;
        $khm_test_options = [
            'khm_email_use_queue' => true,
            'khm_email_delivery_method' => 'wordpress',
            'khm_email_queue_max_attempts' => 3,
            'khm_email_queue_retry_base_seconds' => 5,
        ];

        $wpdb->query( "CREATE TABLE {$wpdb->prefix}khm_email_logs (id bigint unsigned)" );
        $wpdb->query( "CREATE TABLE {$wpdb->prefix}khm_email_queue (id bigint unsigned)" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_email_logs" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_email_queue" );
    }

    protected function tearDown(): void {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_email_logs" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_email_queue" );
        unset( $GLOBALS['khm_test_wp_mail_result'] );
        parent::tearDown();
    }

    public function test_duplicate_replay_results_in_single_queued_and_sent_email(): void {
        $service = new EnhancedEmailService( $this->pluginDir );
        $emailLogId = $this->seed_email_log();

        $queueMethod = new \ReflectionMethod( EnhancedEmailService::class, 'queue_email' );
        $queueMethod->setAccessible( true );

        $payload = [
            'event_id' => 'evt_email_replay_001',
            'email_type' => 'welcome',
            'reference' => 'session:cs_email_replay_001',
            'idempotency_key' => 'evt_email_replay_001:welcome',
        ];

        $first = $queueMethod->invoke( $service, $emailLogId, 'welcome', 'dedupe@example.com', '<p>Body</p>', $payload );
        $second = $queueMethod->invoke( $service, $emailLogId, 'welcome', 'dedupe@example.com', '<p>Body</p>', $payload );

        $this->assertTrue( (bool) $first );
        $this->assertTrue( (bool) $second );

        $service->setSubject( 'Dedupe' );
        $service->process_email_queue();

        global $wpdb;
        $rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}khm_email_queue LIMIT 10", ARRAY_A );

        $this->assertCount( 1, $rows );
        $this->assertSame( 'evt_email_replay_001:welcome', (string) ( $rows[0]['idempotency_key'] ?? '' ) );
        $this->assertSame( 'sent', (string) ( $rows[0]['status'] ?? '' ) );
    }

    private function seed_email_log(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_email_logs';
        $wpdb->insert(
            $table,
            [
                'template_key' => 'welcome',
                'recipient' => 'dedupe@example.com',
                'subject' => 'Dedupe',
                'delivery_method' => 'wordpress',
                'status' => 'pending',
                'created_at' => current_time( 'mysql' ),
            ]
        );

        return (int) $wpdb->insert_id;
    }
}
}
