<?php

namespace KHM\Tests\Membership;

use KHM\Membership\MembershipWebhookDeadLetterStore;
use KHM\Services\WebhookService;
use PHPUnit\Framework\TestCase;

class WebhookRequeueIntegrationTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        putenv( 'KH_STRIPE_WEBHOOK_SECRET=whsec_test_secret' );
        putenv( 'KHM_STRIPE_TEST_MODE=ci' );
        $GLOBALS['khm_test_options'] = [
            'khm_membership_transactional_emails_enabled' => false,
        ];

        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_processed_webhooks" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_processed_webhook_events" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_membership_webhook_operations" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}khm_webhook_dead_letter" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}promotion_attribution" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}user_membership" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}membership_tier" );
    }

    protected function tearDown(): void {
        putenv( 'KH_STRIPE_WEBHOOK_SECRET' );
        putenv( 'KHM_STRIPE_TEST_MODE' );
        parent::tearDown();
    }

    public function test_requeue_processes_checkout_event_and_clears_dead_letter(): void {
        global $wpdb;

        $wpdb->insert( $wpdb->prefix . 'membership_tier', [
            'slug' => 'premium',
            'name' => 'Premium',
            'price_cents' => 2999,
            'is_active' => 1,
        ] );
        $planId = (int) $wpdb->insert_id;

        $filter = static function (): array {
            return [
                'premium' => [
                    'price_id' => 'price_premium_monthly',
                    'trial_eligible' => false,
                    'trial_days' => 0,
                ],
            ];
        };
        add_filter( 'khm_membership_tier_registry', $filter );

        $eventId = 'evt_dlq_requeue_integration_001';
        MembershipWebhookDeadLetterStore::store(
            $eventId,
            'checkout.session.completed',
            wp_json_encode(
                [
                    'event_id' => $eventId,
                    'event_type' => 'checkout.session.completed',
                    'event_created' => time(),
                    'data_object' => [
                        'id' => 'cs_dlq_requeue_integration_001',
                        'mode' => 'subscription',
                        'customer' => 'cus_dlq_001',
                        'subscription' => 'sub_dlq_001',
                        'metadata' => [
                            'user_id' => '456',
                            'membership_level_id' => (string) $planId,
                            'tier_slug' => 'premium',
                            'stripe_price_id' => 'price_premium_monthly',
                            'schedule_id' => '987',
                            'sponsor_id' => '54',
                            'consent' => true,
                        ],
                    ],
                ]
            ) ?: '',
            'processing_failed',
            'temp store missing'
        );

        $service = new WebhookService();
        $result = $service->requeueWebhookEvent( $eventId );

        $this->assertSame( 'success', $result['status'] );

        $membership = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}user_membership WHERE user_id = %d", 456 ),
            ARRAY_A
        );
        $this->assertNotNull( $membership );

        $dlq = MembershipWebhookDeadLetterStore::get_by_event_id( $eventId );
        $this->assertIsArray( $dlq );
        $this->assertSame( 'resolved', (string) ( $dlq['status'] ?? '' ) );

        $processed = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}khm_processed_webhook_events WHERE event_id = %s", $eventId ),
            ARRAY_A
        );
        $this->assertIsArray( $processed );
        $this->assertSame( 'processed', (string) ( $processed['status'] ?? '' ) );

        remove_filter( 'khm_membership_tier_registry', $filter );
    }
}
