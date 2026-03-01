<?php

namespace KHM\Tests\Membership;

use PHPUnit\Framework\TestCase;
use KHM\Membership\ProcessedWebhook;
use KHM\Membership\StripeWebhookHandler;
use WP_REST_Request;

class StripeWebhookHandlerTest extends TestCase {
    private $handler;

    protected function setUp(): void {
        parent::setUp();
        $this->handler = new StripeWebhookHandler();
        ProcessedWebhook::maybe_create_table();
        add_filter( 'khm_membership_webhook_skip_signature_verification', '__return_true' );

        // Clean up test data
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}khm_processed_webhooks");
        $wpdb->query("DELETE FROM {$wpdb->prefix}user_membership");
    }

    protected function tearDown(): void {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}khm_processed_webhooks");
        $wpdb->query("DELETE FROM {$wpdb->prefix}user_membership");
        remove_filter( 'khm_membership_webhook_skip_signature_verification', '__return_true' );
        parent::tearDown();
    }

    public function test_rejects_invalid_event_format() {
        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode(['invalid' => 'data']));

        $response = $this->handler->handle_request($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('Invalid event', $data['error']);
    }

    public function test_idempotency_prevents_duplicate_processing() {
        $event_payload = [
            'id' => 'evt_test_12345',
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'customer' => 'cus_12345'
                ]
            ]
        ];

        // First request
        $request1 = new WP_REST_Request('POST');
        $request1->set_body(json_encode($event_payload));
        $response1 = $this->handler->handle_request($request1);

        $this->assertEquals(200, $response1->get_status());
        $this->handler->process_queued_event([
            'event_id' => 'evt_test_12345',
            'event_type' => 'invoice.paid',
            'data_object' => [ 'customer' => 'cus_12345' ],
            'trace_id' => 'test-trace',
        ]);

        // Second identical request
        $request2 = new WP_REST_Request('POST');
        $request2->set_body(json_encode($event_payload));
        $response2 = $this->handler->handle_request($request2);

        $this->assertEquals(200, $response2->get_status());
        $data = $response2->get_data();
        $this->assertEquals('already processed', $data['note']);

        // Verify only one event record exists
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}khm_processed_webhooks WHERE event_id = %s",
            'evt_test_12345'
        ));
        $this->assertEquals(1, $count);
    }

    public function test_checkout_session_completed_creates_membership() {
        global $wpdb;

        // Create plan
        $wpdb->insert($wpdb->prefix . 'membership_tier', [
            'slug' => 'premium',
            'name' => 'Premium',
            'price_cents' => 2999,
            'is_active' => 1
        ]);
        $plan_id = $wpdb->insert_id;

        $event_payload = [
            'id' => 'evt_checkout_completed',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'mode' => 'subscription',
                    'customer' => 'cus_test123',
                    'subscription' => 'sub_test123',
                    'metadata' => [
                        'user_id' => '123',
                        'membership_level_id' => (string) $plan_id
                    ]
                ]
            ]
        ];

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode($event_payload));
        $response = $this->handler->handle_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->handler->process_queued_event([
            'event_id' => 'evt_checkout_completed',
            'event_type' => 'checkout.session.completed',
            'data_object' => $event_payload['data']['object'],
            'trace_id' => 'test-trace',
        ]);

        // Verify membership created
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}user_membership WHERE user_id = %d",
            123
        ), ARRAY_A);

        $this->assertNotNull($membership);
        $this->assertEquals($plan_id, $membership['tier_id']);
        $this->assertEquals('active', $membership['status']);
        $this->assertEquals('cus_test123', $membership['stripe_customer_id']);
        $this->assertEquals('sub_test123', $membership['stripe_subscription_id']);

        // Cleanup
        $wpdb->delete($wpdb->prefix . 'membership_tier', ['id' => $plan_id]);
    }

    public function test_invoice_paid_updates_status_to_active() {
        global $wpdb;

        // Create existing membership
        $wpdb->insert($wpdb->prefix . 'user_membership', [
            'user_id' => 123,
            'tier_id' => 1,
            'stripe_customer_id' => 'cus_test456',
            'status' => 'past_due'
        ]);

        $event_payload = [
            'id' => 'evt_invoice_paid',
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'customer' => 'cus_test456'
                ]
            ]
        ];

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode($event_payload));
        $response = $this->handler->handle_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->handler->process_queued_event([
            'event_id' => 'evt_invoice_paid',
            'event_type' => 'invoice.paid',
            'data_object' => $event_payload['data']['object'],
            'trace_id' => 'test-trace',
        ]);

        // Verify status updated
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}user_membership WHERE user_id = %d",
            123
        ), ARRAY_A);

        $this->assertEquals('active', $membership['status']);
    }

    public function test_invoice_payment_failed_updates_status_to_past_due() {
        global $wpdb;

        // Create active membership
        $wpdb->insert($wpdb->prefix . 'user_membership', [
            'user_id' => 456,
            'tier_id' => 1,
            'stripe_customer_id' => 'cus_test789',
            'status' => 'active'
        ]);

        $event_payload = [
            'id' => 'evt_payment_failed',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'customer' => 'cus_test789'
                ]
            ]
        ];

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode($event_payload));
        $response = $this->handler->handle_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->handler->process_queued_event([
            'event_id' => 'evt_payment_failed',
            'event_type' => 'invoice.payment_failed',
            'data_object' => $event_payload['data']['object'],
            'trace_id' => 'test-trace',
        ]);

        // Verify status updated
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}user_membership WHERE user_id = %d",
            456
        ), ARRAY_A);

        $this->assertEquals('past_due', $membership['status']);
    }

    public function test_subscription_deleted_cancels_membership() {
        global $wpdb;

        // Create active membership
        $wpdb->insert($wpdb->prefix . 'user_membership', [
            'user_id' => 789,
            'tier_id' => 1,
            'stripe_customer_id' => 'cus_cancelled',
            'status' => 'active'
        ]);

        $event_payload = [
            'id' => 'evt_sub_deleted',
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'customer' => 'cus_cancelled'
                ]
            ]
        ];

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode($event_payload));
        $response = $this->handler->handle_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->handler->process_queued_event([
            'event_id' => 'evt_sub_deleted',
            'event_type' => 'customer.subscription.deleted',
            'data_object' => $event_payload['data']['object'],
            'trace_id' => 'test-trace',
        ]);

        // Verify cancellation
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}user_membership WHERE user_id = %d",
            789
        ), ARRAY_A);

        $this->assertEquals('cancelled', $membership['status']);
        $this->assertNotNull($membership['cancelled_at']);
    }

    public function test_handles_unknown_event_types_gracefully() {
        $event_payload = [
            'id' => 'evt_unknown',
            'type' => 'customer.unknown_event',
            'data' => []
        ];

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode($event_payload));
        $response = $this->handler->handle_request($request);

        // Should still return success
        $this->assertEquals(200, $response->get_status());
        $this->handler->process_queued_event([
            'event_id' => 'evt_unknown',
            'event_type' => 'customer.unknown_event',
            'data_object' => [],
            'trace_id' => 'test-trace',
        ]);

        // Event should still be marked as processed
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT event_id FROM {$wpdb->prefix}khm_processed_webhooks WHERE event_id = %s",
            'evt_unknown'
        ));
        $this->assertNotNull($exists);
    }

    public function test_rate_limit_returns_429_when_threshold_exceeded() {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.77';

        add_filter(
            'khm_membership_webhook_rate_limit_max_requests',
            function () {
                return 1;
            }
        );

        $event_payload = [
            'id' => 'evt_rate_1',
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'customer' => 'cus_rate'
                ]
            ]
        ];

        $request1 = new WP_REST_Request('POST');
        $request1->set_body(json_encode($event_payload));
        $response1 = $this->handler->handle_request($request1);
        $this->assertEquals(200, $response1->get_status());

        $request2 = new WP_REST_Request('POST');
        $request2->set_body(json_encode([
            'id' => 'evt_rate_2',
            'type' => 'invoice.paid',
            'data' => [ 'object' => [ 'customer' => 'cus_rate' ] ],
        ]));
        $response2 = $this->handler->handle_request($request2);
        $this->assertEquals(429, $response2->get_status());

        unset($_SERVER['REMOTE_ADDR']);
    }
}
