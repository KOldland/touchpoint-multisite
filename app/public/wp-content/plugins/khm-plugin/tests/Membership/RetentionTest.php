<?php

namespace KHM\Tests\Membership;

use KHM\Membership\RetentionWorker;
use PHPUnit\Framework\TestCase;

class RetentionTest extends TestCase {
    public function test_retention_worker_anonymizes_expired_rows_only(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'promotion_attribution';

        $old = gmdate( 'Y-m-d H:i:s', time() - ( 5 * 86400 ) );
        $recent = gmdate( 'Y-m-d H:i:s', time() - 300 );

        $wpdb->insert( $table, [
            'id' => 9101,
            'user_id' => 1,
            'user_email' => 'old@example.com',
            'utm_source' => 'legacy',
            'consent' => 1,
            'created_at' => $old,
        ] );

        $wpdb->insert( $table, [
            'id' => 9102,
            'user_id' => 2,
            'user_email' => 'recent@example.com',
            'utm_source' => 'new',
            'consent' => 1,
            'created_at' => $recent,
        ] );

        update_site_option( 'khm_attribution_retention_days', 1 );
        update_site_option( 'khm_attribution_retention_mode', 'anonymize' );

        $worker = new RetentionWorker();
        $worker->run();

        $oldRow = $wpdb->get_row( "SELECT * FROM {$table} WHERE id = 9101", ARRAY_A );
        $newRow = $wpdb->get_row( "SELECT * FROM {$table} WHERE id = 9102", ARRAY_A );

        $this->assertNotEmpty( $oldRow['anonymized_at'] ?? '' );
        $this->assertEmpty( $oldRow['utm_source'] ?? null );

        $this->assertEmpty( $newRow['anonymized_at'] ?? '' );
        $this->assertSame( 'new', (string) ( $newRow['utm_source'] ?? '' ) );
    }

    public function testRetentionWorkerAnonymizesExpiredRows(): void {
        $this->test_retention_worker_anonymizes_expired_rows_only();
    }
}
