<?php

namespace {
    if ( ! function_exists( 'wp_mail' ) ) {
        function wp_mail( $to, $subject, $message, $headers = '', $attachments = [] ) {
            return isset( $GLOBALS['khm_test_wp_mail_result'] )
                ? (bool) $GLOBALS['khm_test_wp_mail_result']
                : false;
        }
    }

    if ( ! function_exists( 'get_locale' ) ) {
        function get_locale() {
            return 'en_US';
        }
    }

    if ( ! function_exists( 'get_stylesheet_directory' ) ) {
        function get_stylesheet_directory() {
            return '/tmp';
        }
    }

    if ( ! function_exists( 'get_template_directory' ) ) {
        function get_template_directory() {
            return '/tmp';
        }
    }

    if ( ! function_exists( 'get_bloginfo' ) ) {
        function get_bloginfo( $show = '' ) {
            return 'KHM Test Site';
        }
    }

    if ( ! function_exists( 'get_site_url' ) ) {
        function get_site_url( $blog_id = null, $path = '', $scheme = null ) {
            return 'https://example.test';
        }
    }
}

namespace KHM\Tests\Services {
use KHM\Services\EnhancedEmailService;
use PHPUnit\Framework\TestCase;

class EnhancedEmailServiceQueueTest extends TestCase {
    private string $pluginDir;

    protected function setUp(): void {
        parent::setUp();
        global $wpdb, $khm_test_options;
        $this->pluginDir = dirname( __DIR__, 2 );
        $GLOBALS['khm_test_wp_mail_result'] = false;
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

    public function test_failed_queue_email_schedules_retry_with_backoff(): void {
        global $wpdb;

        $service = new EnhancedEmailService( $this->pluginDir );
        $email_log_id = $this->seed_email_log();
        $queue_id = $this->seed_queue_row( $email_log_id, 0, 3 );

        $service->process_email_queue();

        $row = $this->find_queue_row( $queue_id );
        $this->assertNotNull( $row );
        $this->assertSame( 'pending', (string) $row['status'] );
        $this->assertSame( '1', (string) $row['attempts'] );
        $this->assertStringContainsString( 'retry scheduled', (string) $row['error'] );
        $this->assertNotSame( '2026-01-01 00:00:00', (string) $row['scheduled_at'] );
    }

    public function test_failed_queue_email_marks_permanent_failure_at_max_attempts(): void {
        global $wpdb;

        $service = new EnhancedEmailService( $this->pluginDir );
        $email_log_id = $this->seed_email_log();
        $queue_id = $this->seed_queue_row( $email_log_id, 2, 3 );

        $service->process_email_queue();

        $row = $this->find_queue_row( $queue_id );
        $this->assertNotNull( $row );
        $this->assertSame( 'failed', (string) $row['status'] );
        $this->assertSame( '3', (string) $row['attempts'] );
        $this->assertStringContainsString( 'max retries reached', (string) $row['error'] );
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

    private function seed_queue_row( int $email_log_id, int $attempts, int $max_attempts ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_email_queue';
        $wpdb->insert(
            $table,
            [
                'email_log_id' => $email_log_id,
                'template_key' => 'welcome',
                'recipient' => 'queue-test@example.com',
                'subject' => 'Queue Test',
                'body' => '<p>Queue body</p>',
                'headers' => wp_json_encode( [ 'Content-Type: text/html; charset=UTF-8' ] ),
                'attachments' => wp_json_encode( [] ),
                'data' => wp_json_encode( [] ),
                'priority' => 5,
                'attempts' => $attempts,
                'max_attempts' => $max_attempts,
                'status' => 'pending',
                'scheduled_at' => '2026-01-01 00:00:00',
                'created_at' => current_time( 'mysql' ),
            ]
        );
        return (int) $wpdb->insert_id;
    }

    private function find_queue_row( int $queue_id ): ?array {
        global $wpdb;
        $rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}khm_email_queue LIMIT 10", ARRAY_A );
        foreach ( $rows as $row ) {
            if ( (int) ( $row['id'] ?? 0 ) === $queue_id ) {
                return $row;
            }
        }
        return null;
    }
}
}
