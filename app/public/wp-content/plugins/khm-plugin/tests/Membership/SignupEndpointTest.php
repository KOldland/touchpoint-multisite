<?php

namespace KHM\Tests\Membership;

use PHPUnit\Framework\TestCase;
use KHM\Membership\SignupEndpoint;
use WP_REST_Request;

class SignupEndpointTest extends TestCase {
    private $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new SignupEndpoint();

        // Clean up test data
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}user_membership");
    }

    protected function tearDown(): void {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}user_membership");
        parent::tearDown();
    }

    public function test_validates_email_format() {
        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode([
            'email' => 'invalid-email',
            'plan_id' => 1
        ]));

        $response = $this->endpoint->handle_request($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('invalid email', $data['error']);
    }

    public function test_validates_plan_id_required() {
        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode([
            'email' => 'test@example.com'
        ]));

        $response = $this->endpoint->handle_request($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('invalid plan_id', $data['error']);
    }

    public function test_validates_plan_exists() {
        global $wpdb;

        // Create a test plan
        $wpdb->insert($wpdb->prefix . 'membership_tier', [
            'slug' => 'test-plan',
            'name' => 'Test Plan',
            'price_cents' => 0,
            'trial_days' => 14,
            'is_active' => 1
        ]);
        $valid_plan_id = $wpdb->insert_id;

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode([
            'email' => 'test@example.com',
            'plan_id' => 99999 // Non-existent plan
        ]));

        $response = $this->endpoint->handle_request($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('plan_id does not exist', $data['error']);

        // Cleanup
        $wpdb->delete($wpdb->prefix . 'membership_tier', ['id' => $valid_plan_id]);
    }

    public function test_returns_trial_response_for_free_plan() {
        global $wpdb;

        // Create free trial plan
        $wpdb->insert($wpdb->prefix . 'membership_tier', [
            'slug' => 'free-trial',
            'name' => 'Free Trial',
            'price_cents' => 0,
            'trial_days' => 14,
            'is_active' => 1
        ]);
        $plan_id = $wpdb->insert_id;

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode([
            'email' => 'test@example.com',
            'plan_id' => $plan_id
        ]));

        $response = $this->endpoint->handle_request($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertEquals('trialing', $data['status']);
        $this->assertArrayHasKey('user_id', $data);
        $this->assertArrayHasKey('membership', $data);
        $this->assertEquals($plan_id, $data['membership']['tier_id']);
        $this->assertEquals('trialing', $data['membership']['status']);
        $this->assertNotNull($data['membership']['trial_ends_at']);

        // Cleanup
        $wpdb->delete($wpdb->prefix . 'membership_tier', ['id' => $plan_id]);
    }

    public function test_prevents_duplicate_active_subscriptions() {
        global $wpdb;

        // Create plan
        $wpdb->insert($wpdb->prefix . 'membership_tier', [
            'slug' => 'test-plan',
            'name' => 'Test Plan',
            'price_cents' => 0,
            'trial_days' => 14,
            'is_active' => 1
        ]);
        $plan_id = $wpdb->insert_id;

        // Create existing active membership
        $wpdb->insert($wpdb->prefix . 'user_membership', [
            'user_id' => 123,
            'tier_id' => $plan_id,
            'status' => 'active'
        ]);

        // Mock email_exists to return user_id 123
        \WP_Mock::userFunction('email_exists', [
            'return' => 123
        ]);

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode([
            'email' => 'existing@example.com',
            'plan_id' => $plan_id
        ]));

        $response = $this->endpoint->handle_request($request);

        $this->assertEquals(409, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('user already has an active subscription', $data['error']);

        // Cleanup
        $wpdb->delete($wpdb->prefix . 'user_membership', ['user_id' => 123]);
        $wpdb->delete($wpdb->prefix . 'membership_tier', ['id' => $plan_id]);
    }

    public function test_response_schema_matches_contract() {
        global $wpdb;

        // Create free plan
        $wpdb->insert($wpdb->prefix . 'membership_tier', [
            'slug' => 'test-plan',
            'name' => 'Test Plan',
            'price_cents' => 0,
            'trial_days' => 7,
            'is_active' => 1
        ]);
        $plan_id = $wpdb->insert_id;

        $request = new WP_REST_Request('POST');
        $request->set_body(json_encode([
            'email' => 'test@example.com',
            'plan_id' => $plan_id
        ]));

        $response = $this->endpoint->handle_request($request);
        $data = $response->get_data();

        // Verify contract schema
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('user_id', $data);
        $this->assertArrayHasKey('membership', $data);
        $this->assertArrayHasKey('tier_id', $data['membership']);
        $this->assertArrayHasKey('status', $data['membership']);
        $this->assertArrayHasKey('trial_ends_at', $data['membership']);

        // Verify trial_ends_at is ISO 8601 format
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $data['membership']['trial_ends_at']
        );

        // Cleanup
        $wpdb->delete($wpdb->prefix . 'membership_tier', ['id' => $plan_id]);
    }
}
