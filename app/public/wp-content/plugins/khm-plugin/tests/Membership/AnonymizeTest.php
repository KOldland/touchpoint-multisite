<?php

namespace KHM\Tests\Membership;

use KHM\Services\MembershipRepository;
use PHPUnit\Framework\TestCase;

class AnonymizeTest extends TestCase {
    public function test_anonymize_by_id_sets_hash_and_redacts_fields(): void {
        putenv( 'KHM_ANON_SALT=test-salt' );

        global $wpdb;
        $table = $wpdb->prefix . 'promotion_attribution';

        $wpdb->insert( $table, [
            'id' => 9001,
            'schedule_id' => 12,
            'sponsor_id' => 44,
            'user_id' => 55,
            'user_email' => 'user@example.com',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'launch',
            'phase_at_click' => 'landing',
            'conversion_type' => 'signup',
            'reference' => 'cs_12345',
            'consent' => 1,
            'created_at' => gmdate( 'Y-m-d H:i:s' ),
        ] );

        $repo = new MembershipRepository();
        $ok = $repo->anonymizeAttributionById( 9001, 77, 'unit_test' );
        $this->assertTrue( $ok );

        $row = $wpdb->get_row( "SELECT * FROM {$table} WHERE id = 9001", ARRAY_A );
        $this->assertIsArray( $row );
        $this->assertEmpty( $row['utm_source'] ?? null );
        $this->assertEmpty( $row['utm_medium'] ?? null );
        $this->assertEmpty( $row['utm_campaign'] ?? null );
        $this->assertEmpty( $row['user_email'] ?? null );
        $this->assertSame( '0', (string) ( $row['consent'] ?? '' ) );
        $this->assertNotEmpty( $row['anonymized_at'] ?? '' );
        $this->assertSame( '77', (string) ( $row['anonymized_by'] ?? '' ) );
        $this->assertSame( hash( 'sha256', 'test-salt' . 'cs_12345' ), (string) ( $row['reference_hash'] ?? '' ) );
    }

    public function testAnonymizeCommandRedactsFields(): void {
        putenv( 'KHM_ANON_SALT=test-salt' );

        global $wpdb;
        $table = $wpdb->prefix . 'promotion_attribution';

        $wpdb->insert( $table, [
            'id' => 9002,
            'schedule_id' => 13,
            'sponsor_id' => 45,
            'user_id' => 56,
            'user_email' => 'command@example.com',
            'utm_source' => 'campaign',
            'utm_medium' => 'email',
            'utm_campaign' => 'winter',
            'phase_at_click' => 'decision',
            'conversion_type' => 'signup',
            'reference' => 'cs_67890',
            'consent' => 1,
            'created_at' => gmdate( 'Y-m-d H:i:s' ),
        ] );

        $repo = new MembershipRepository();
        $ok = $repo->anonymizeAttributionById( 9002, 88, 'cli_simulated' );
        $this->assertTrue( $ok );

        $row = $wpdb->get_row( "SELECT * FROM {$table} WHERE id = 9002", ARRAY_A );
        $this->assertIsArray( $row );
        $this->assertEmpty( $row['user_email'] ?? null );
        $this->assertEmpty( $row['utm_source'] ?? null );
        $this->assertNotEmpty( $row['reference_hash'] ?? '' );
    }
}
