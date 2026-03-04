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
        $wpdb->query("DELETE FROM {$wpdb->prefix}khm_processed_webhook_events");
        $wpdb->query("DELETE FROM {$wpdb->prefix}khm_membership_webhook_operations");
        $wpdb->query("DELETE FROM {$wpdb->prefix}khm_webhook_dead_letter");
        $wpdb->query("DELETE FROM {$wpdb->prefix}promotion_attribution");
        $wpdb->query("DELETE FROM {$wpdb->prefix}user_membership");
        if ( isset( $GLOBALS['khm_test_transients'] ) && is_array( $GLOBALS['khm_test_transients'] ) ) {
            $GLOBALS['khm_test_transients'] = [];
        }
    }

    protected function tearDown(): void {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}khm_processed_webhooks");
        $wpdb->query("DELETE FROM {$wpdb->prefix}khm_processed_webhook_events");
        $wpdb->query("DELETE FROM {$wpdb->prefix}khm_membership_webhook_operations");
        $wpdb->query("DELETE FROM {$wpdb->prefix}khm_webhook_dead_letter");
        $wpdb->query("DELETE FROM {$wpdb->prefix}promotion_attribution");
        $wpdb->query("DELETE FROM {$wpdb->prefix}user_membership");
        if ( isset( $GLOBALS['khm_test_transients'] ) && is_array( $GLOBALS['khm_test_transients'] ) ) {
            $GLOBALS['khm_test_transients'] = [];
        }
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
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'user_membership', [
            'user_id' => 999,
            'tier_id' => 1,
            'stripe_customer_id' => 'cus_12345',
            'status' => 'active'
        ]);

        $event_payload = [
            'id' => 'evt_test_12345',
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'id' => 'in_test_12345',
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
            'data_object' => [ 'id' => 'in_test_12345', 'customer' => 'cus_12345' ],
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

        $filter = function () {
            return [
                'premium' => [
                    'price_id' => 'price_premium_monthly',
                    'trial_eligible' => false,
                    'trial_days' => 0,
                ],
            ];
        };
        add_filter('khm_membership_tier_registry', $filter);

        $event_payload = [
            'id' => 'evt_checkout_completed',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test123',
                    'mode' => 'subscription',
                    'customer' => 'cus_test123',
                    'subscription' => 'sub_test123',
                    'metadata' => [
                        'user_id' => '123',
                        'membership_level_id' => (string) $plan_id,
                        'tier_slug' => 'premium',
                        'stripe_price_id' => 'price_premium_monthly',
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
        remove_filter('khm_membership_tier_registry', $filter);
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
                    'id' => 'in_paid_123',
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
                    'id' => 'in_failed_456',
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

        $this->assertEquals('canceled', $membership['status']);
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
                    'id' => 'in_rate_1',
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
            'data' => [ 'object' => [ 'id' => 'in_rate_2', 'customer' => 'cus_rate' ] ],
        ]));
        $response2 = $this->handler->handle_request($request2);
        $this->assertEquals(429, $response2->get_status());

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function test_checkout_subscription_missing_tier_metadata_marks_failed(): void {
        $event_payload = [
            'id' => 'evt_checkout_missing_tier',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_missing_tier',
                    'mode' => 'subscription',
                    'metadata' => [
                        'user_id' => '123'
                    ],
                ],
            ],
        ];

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode($event_payload));
        $response = $this->handler->handle_request($request);
        $this->assertEquals(200, $response->get_status());

        $this->handler->process_queued_event([
            'event_id' => 'evt_checkout_missing_tier',
            'event_type' => 'checkout.session.completed',
            'data_object' => $event_payload['data']['object'],
            'trace_id' => 'test-trace',
        ]);

        $failed = \KHM\Membership\ProcessedWebhook::get_event('evt_checkout_missing_tier');
        $this->assertNotNull($failed);
        $this->assertEquals('failed', $failed['status'] ?? '');
    }

    public function test_invoice_paid_missing_user_marks_failed(): void {
        $event_payload = [
            'id' => 'evt_invoice_missing_user',
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'id' => 'in_missing_user',
                    'customer' => 'cus_missing_user',
                    'subscription' => 'sub_missing_user',
                ],
            ],
        ];

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode($event_payload));
        $response = $this->handler->handle_request($request);
        $this->assertEquals(200, $response->get_status());

        $this->handler->process_queued_event([
            'event_id' => 'evt_invoice_missing_user',
            'event_type' => 'invoice.paid',
            'data_object' => $event_payload['data']['object'],
            'trace_id' => 'test-trace',
        ]);

        $failed = \KHM\Membership\ProcessedWebhook::get_event('evt_invoice_missing_user');
        $this->assertNotNull($failed);
        $this->assertEquals('failed', $failed['status'] ?? '');
    }

    public function test_invoice_paid_replay_with_new_event_id_is_operation_idempotent(): void {
        global $wpdb;
        $audit_table = $wpdb->prefix . 'khm_membership_webhook_audit';

        $wpdb->insert($wpdb->prefix . 'user_membership', [
            'user_id' => 321,
            'tier_id' => 1,
            'stripe_customer_id' => 'cus_replay',
            'status' => 'past_due'
        ]);

        $data_object = [
            'id' => 'in_replay',
            'customer' => 'cus_replay',
            'subscription' => 'sub_replay'
        ];

        $this->handler->process_queued_event([
            'event_id' => 'evt_replay_1',
            'event_type' => 'invoice.paid',
            'data_object' => $data_object,
            'trace_id' => 'trace-1',
        ]);

        $this->handler->process_queued_event([
            'event_id' => 'evt_replay_2',
            'event_type' => 'invoice.paid',
            'data_object' => $data_object,
            'trace_id' => 'trace-2',
        ]);

        $op_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$audit_table} WHERE operation_key = 'invoice_paid:in_replay'"
        );
        $this->assertEquals(2, (int) $op_count); // one success + one duplicate audit marker
    }

    public function test_checkout_session_rejects_tier_price_mismatch(): void {
        $filter = function () {
            return [
                'pro' => [
                    'price_id' => 'price_expected',
                    'trial_eligible' => false,
                    'trial_days' => 0,
                ],
            ];
        };
        add_filter(
            'khm_membership_tier_registry',
            $filter
        );

        $event_payload = [
            'id' => 'evt_checkout_mismatch',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_mismatch',
                    'mode' => 'subscription',
                    'customer' => 'cus_mismatch',
                    'subscription' => 'sub_mismatch',
                    'metadata' => [
                        'user_id' => '123',
                        'membership_level_id' => '1',
                        'tier_slug' => 'pro',
                        'stripe_price_id' => 'price_tampered',
                    ],
                ],
            ],
        ];

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode($event_payload));
        $response = $this->handler->handle_request($request);
        $this->assertEquals(200, $response->get_status());

        $this->handler->process_queued_event([
            'event_id' => 'evt_checkout_mismatch',
            'event_type' => 'checkout.session.completed',
            'data_object' => $event_payload['data']['object'],
            'trace_id' => 'trace-mismatch',
        ]);

        $failed = \KHM\Membership\ProcessedWebhook::get_event('evt_checkout_mismatch');
        $this->assertNotNull($failed);
        $this->assertEquals('failed', $failed['status'] ?? '');
        remove_filter( 'khm_membership_tier_registry', $filter );
    }

    public function test_subscription_updated_lifecycle_matrix_transitions(): void {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'user_membership', [
            'user_id' => 901,
            'tier_id' => 1,
            'tier_slug' => 'basic',
            'stripe_customer_id' => 'cus_matrix',
            'stripe_subscription_id' => 'sub_matrix',
            'status' => 'active'
        ]);

        $matrix = [
            [ 'label' => 'trialing to trial', 'remote_status' => 'trialing', 'cancel_at_period_end' => false, 'expected' => 'trial' ],
            [ 'label' => 'active to active', 'remote_status' => 'active', 'cancel_at_period_end' => false, 'expected' => 'active' ],
            [ 'label' => 'active to pending_cancel', 'remote_status' => 'active', 'cancel_at_period_end' => true, 'expected' => 'pending_cancel' ],
            [ 'label' => 'past due', 'remote_status' => 'past_due', 'cancel_at_period_end' => false, 'expected' => 'past_due' ],
            [ 'label' => 'canceled', 'remote_status' => 'canceled', 'cancel_at_period_end' => false, 'expected' => 'canceled' ],
        ];

        foreach ($matrix as $idx => $row) {
            $this->handler->process_queued_event([
                'event_id' => 'evt_sub_matrix_' . $idx,
                'event_type' => 'customer.subscription.updated',
                'event_created' => 1700000000 + $idx,
                'data_object' => [
                    'id' => 'sub_matrix',
                    'customer' => 'cus_matrix',
                    'status' => $row['remote_status'],
                    'cancel_at_period_end' => $row['cancel_at_period_end'],
                    'current_period_end' => 1701000000 + $idx,
                ],
                'trace_id' => 'trace-sub-matrix-' . $idx,
            ]);

            $membership = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}user_membership WHERE user_id = %d",
                901
            ), ARRAY_A);
            $this->assertEquals($row['expected'], $membership['status'], $row['label']);
        }
    }

    public function test_subscription_updated_plan_change_maps_tier_and_price(): void {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'membership_tier', [
            'slug' => 'pro',
            'name' => 'Pro',
            'price_cents' => 1000,
            'is_active' => 1,
        ]);
        $pro_tier_id = $wpdb->insert_id;

        $wpdb->insert($wpdb->prefix . 'user_membership', [
            'user_id' => 902,
            'tier_id' => 1,
            'tier_slug' => 'basic',
            'stripe_customer_id' => 'cus_plan_change',
            'stripe_subscription_id' => 'sub_plan_change',
            'status' => 'active'
        ]);

        $filter = function () {
            return [
                'pro' => [
                    'price_id' => 'price_pro_monthly',
                    'trial_eligible' => false,
                    'trial_days' => 0,
                ],
            ];
        };
        add_filter('khm_membership_tier_registry', $filter);

        $this->handler->process_queued_event([
            'event_id' => 'evt_sub_plan_change',
            'event_type' => 'customer.subscription.updated',
            'event_created' => 1702000000,
            'data_object' => [
                'id' => 'sub_plan_change',
                'customer' => 'cus_plan_change',
                'status' => 'active',
                'cancel_at_period_end' => false,
                'items' => [
                    'data' => [
                        [ 'price' => [ 'id' => 'price_pro_monthly' ] ],
                    ],
                ],
            ],
            'trace_id' => 'trace-plan-change',
        ]);

        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}user_membership WHERE user_id = %d",
            902
        ), ARRAY_A);

        $this->assertEquals('pro', $membership['tier_slug']);
        $this->assertEquals($pro_tier_id, (int) $membership['tier_id']);
        $this->assertEquals('price_pro_monthly', $membership['stripe_price_id']);
        remove_filter('khm_membership_tier_registry', $filter);
        $wpdb->delete($wpdb->prefix . 'membership_tier', ['id' => $pro_tier_id]);
    }

    public function test_checkout_no_consent_creates_stub_attribution_without_utm(): void {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'membership_tier', [
            'slug' => 'premium',
            'name' => 'Premium',
            'price_cents' => 2999,
            'is_active' => 1
        ]);
        $plan_id = $wpdb->insert_id;

        $filter = function () {
            return [
                'premium' => [
                    'price_id' => 'price_premium_monthly',
                    'trial_eligible' => false,
                    'trial_days' => 0,
                ],
            ];
        };
        add_filter('khm_membership_tier_registry', $filter);

        $event_payload = [
            'id' => 'evt_checkout_no_consent',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_no_consent',
                    'mode' => 'subscription',
                    'customer' => 'cus_no_consent',
                    'subscription' => 'sub_no_consent',
                    'metadata' => [
                        'user_id' => '123',
                        'membership_level_id' => (string) $plan_id,
                        'tier_slug' => 'premium',
                        'stripe_price_id' => 'price_premium_monthly',
                        'schedule_id' => '77',
                        'sponsor_id' => '11',
                        'utm_source' => 'newsletter',
                        'utm_campaign' => 'spring',
                        'consent' => '0',
                    ],
                ],
            ],
        ];

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode($event_payload));
        $response = $this->handler->handle_request($request);
        $this->assertEquals(200, $response->get_status());

        $this->handler->process_queued_event([
            'event_id' => 'evt_checkout_no_consent',
            'event_type' => 'checkout.session.completed',
            'data_object' => $event_payload['data']['object'],
            'trace_id' => 'trace-no-consent',
        ]);

        $attrib = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}promotion_attribution WHERE user_id = %d",
            123
        ), ARRAY_A);

        $this->assertNotNull($attrib);
        $this->assertEquals('paid_no_consent', $attrib['conversion_type'] ?? '');
        $this->assertEquals('', $attrib['utm_source'] ?? '');
        $this->assertEquals('', $attrib['utm_campaign'] ?? '');
        $this->assertEquals('', $attrib['user_email'] ?? '');

        remove_filter('khm_membership_tier_registry', $filter);
        $wpdb->delete($wpdb->prefix . 'membership_tier', ['id' => $plan_id]);
    }

    public function test_processing_failure_creates_dead_letter_record(): void {
        global $wpdb;

        $this->handler->process_queued_event([
            'event_id' => 'evt_dead_letter_1',
            'event_type' => 'invoice.paid',
            'data_object' => [
                'id' => 'in_dead_letter',
                'customer' => 'cus_missing',
                'subscription' => 'sub_missing',
            ],
            'trace_id' => 'trace-dead-letter',
        ]);

        $dead_letter = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}khm_webhook_dead_letter WHERE event_id = %s",
            'evt_dead_letter_1'
        ), ARRAY_A);

        $this->assertNotNull($dead_letter);
        $this->assertEquals('processing_failed', $dead_letter['reason'] ?? '');
    }
}
