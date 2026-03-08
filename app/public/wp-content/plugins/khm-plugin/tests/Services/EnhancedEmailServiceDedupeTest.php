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

namespace KHM\Tests\Services {

use KHM\Services\EnhancedEmailService;
use PHPUnit\Framework\TestCase;

class EnhancedEmailServiceDedupeTest extends TestCase {
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

    public function test_queue_email_is_idempotent_for_duplicate_reference(): void {
        $service = new EnhancedEmailService( $this->pluginDir );
        $email_log_id = $this->seed_email_log();

        $method = new \ReflectionMethod( EnhancedEmailService::class, 'queue_email' );
        $method->setAccessible( true );

        $data = [
            'reference' => 'session:cs_dup_001',
            'schedule_id' => '99',
        ];

        $first = $method->invoke( $service, $email_log_id, 'welcome', 'queue-test@example.com', '<p>Queue body</p>', $data );
        $second = $method->invoke( $service, $email_log_id, 'welcome', 'queue-test@example.com', '<p>Queue body</p>', $data );

        $this->assertTrue( (bool) $first );
        $this->assertTrue( (bool) $second );

        global $wpdb;
        $rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}khm_email_queue LIMIT 10", ARRAY_A );
        $this->assertCount( 1, $rows );
        $this->assertSame( 'welcome:session_cs_dup_001', (string) ( $rows[0]['idempotency_key'] ?? '' ) );
    }

    public function test_duplicate_queue_entry_is_sent_once(): void {
        $service = new EnhancedEmailService( $this->pluginDir );
        $email_log_id = $this->seed_email_log();

        $method = new \ReflectionMethod( EnhancedEmailService::class, 'queue_email' );
        $method->setAccessible( true );
        $method->invoke(
            $service,
            $email_log_id,
            'welcome',
            'queue-test@example.com',
            '<p>Queue body</p>',
            [ 'reference' => 'session:cs_dup_send_001' ]
        );
        $method->invoke(
            $service,
            $email_log_id,
            'welcome',
            'queue-test@example.com',
            '<p>Queue body</p>',
            [ 'reference' => 'session:cs_dup_send_001' ]
        );

        $service->setSubject( 'Queue Test' );
        $service->process_email_queue();

        global $wpdb;
        $rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}khm_email_queue LIMIT 10", ARRAY_A );
        $this->assertCount( 1, $rows );
        $this->assertSame( 'sent', (string) ( $rows[0]['status'] ?? '' ) );
    }

    private function seed_email_log(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_email_logs';
        $wpdb->insert(
            $table,
            [
                'template_key' => 'welcome',
                'recipient' => 'queue-test@example.com',
                'subject' => 'Queue Test',
                'delivery_method' => 'wordpress',
                'status' => 'pending',
                'created_at' => current_time( 'mysql' ),
            ]
        );
        return (int) $wpdb->insert_id;
    }
}
}
