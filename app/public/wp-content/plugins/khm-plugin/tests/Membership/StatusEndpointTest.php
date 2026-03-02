<?php

namespace KHM\Tests\Membership;

use PHPUnit\Framework\TestCase;
use KHM\Membership\StatusEndpoint;
use WP_REST_Request;

class StatusEndpointTest extends TestCase {
    private $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new StatusEndpoint();
    }

    public function test_requires_authentication() {
        // Mock unauthenticated user
        \WP_Mock::userFunction('get_current_user_id', [
            'return' => 0
        ]);

        $request = new WP_REST_Request('GET');
        $request->set_param('user_id', 123);

        $result = $this->endpoint->check_permission($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('rest_forbidden', $result->get_error_code());
        $this->assertEquals(401, $result->get_error_data()['status']);
    }

    public function test_user_can_only_access_own_status() {
        // Mock authenticated user with ID 123
        \WP_Mock::userFunction('get_current_user_id', [
            'return' => 123
        ]);

        // Mock non-admin user
        \WP_Mock::userFunction('current_user_can', [
            'args' => ['manage_options'],
            'return' => false
        ]);

        $request = new WP_REST_Request('GET');
        $request->set_param('user_id', 456); // Different user

        $result = $this->endpoint->check_permission($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('rest_forbidden', $result->get_error_code());
        $this->assertEquals(403, $result->get_error_data()['status']);
    }

    public function test_admin_can_access_any_user_status() {
        // Mock authenticated admin
        \WP_Mock::userFunction('get_current_user_id', [
            'return' => 1
        ]);

        \WP_Mock::userFunction('current_user_can', [
            'args' => ['manage_options'],
            'return' => true
        ]);

        $request = new WP_REST_Request('GET');
        $request->set_param('user_id', 456);

        $result = $this->endpoint->check_permission($request);

        $this->assertTrue($result);
    }

    public function test_returns_none_for_user_without_membership() {
        $request = new WP_REST_Request('GET');
        $request->set_param('user_id', 999);

        $response = $this->endpoint->handle_request($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertEquals('none', $data['status']);
        $this->assertEquals(999, $data['user_id']);
        $this->assertNull($data['tier']);
    }

    public function test_returns_membership_status_for_active_user() {
        global $wpdb;

        // Create tier
        $wpdb->insert($wpdb->prefix . 'membership_tier', [
            'slug' => 'premium',
            'name' => 'Premium Plan',
            'price_cents' => 1999,
            'is_active' => 1
        ]);
        $tier_id = $wpdb->insert_id;

        // Create membership
        $wpdb->insert($wpdb->prefix . 'user_membership', [
            'user_id' => 123,
            'tier_id' => $tier_id,
            'status' => 'active',
            'started_at' => '2026-01-01 00:00:00'
        ]);

        $request = new WP_REST_Request('GET');
        $request->set_param('user_id', 123);

        $response = $this->endpoint->handle_request($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertEquals(123, $data['user_id']);
        $this->assertEquals('active', $data['status']);
        $this->assertArrayHasKey('tier', $data);
        $this->assertEquals($tier_id, $data['tier']['id']);
        $this->assertEquals('premium', $data['tier']['slug']);
        $this->assertEquals('Premium Plan', $data['tier']['name']);

        // Cleanup
        $wpdb->delete($wpdb->prefix . 'user_membership', ['user_id' => 123]);
        $wpdb->delete($wpdb->prefix . 'membership_tier', ['id' => $tier_id]);
    }

    public function test_response_schema_matches_contract() {
        global $wpdb;

        // Create tier
        $wpdb->insert($wpdb->prefix . 'membership_tier', [
            'slug' => 'basic',
            'name' => 'Basic Plan',
            'price_cents' => 999,
            'is_active' => 1
        ]);
        $tier_id = $wpdb->insert_id;

        // Create trial membership
        $wpdb->insert($wpdb->prefix . 'user_membership', [
            'user_id' => 456,
            'tier_id' => $tier_id,
            'status' => 'trial',
            'trial_ends_at' => '2026-02-18 12:00:00',
            'started_at' => '2026-02-01 12:00:00'
        ]);

        $request = new WP_REST_Request('GET');
        $request->set_param('user_id', 456);

        $response = $this->endpoint->handle_request($request);
        $data = $response->get_data();

        // Verify all required fields from contract
        $this->assertArrayHasKey('user_id', $data);
        $this->assertArrayHasKey('tier', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('trial_ends_at', $data);
        $this->assertArrayHasKey('started_at', $data);
        $this->assertArrayHasKey('cancelled_at', $data);
        $this->assertArrayHasKey('renews_at', $data);

        // Verify tier structure
        $this->assertArrayHasKey('id', $data['tier']);
        $this->assertArrayHasKey('slug', $data['tier']);
        $this->assertArrayHasKey('name', $data['tier']);

        // Verify status values
        $this->assertContains($data['status'], ['trial', 'active', 'past_due', 'pending_cancel', 'canceled', 'none']);

        // Cleanup
        $wpdb->delete($wpdb->prefix . 'user_membership', ['user_id' => 456]);
        $wpdb->delete($wpdb->prefix . 'membership_tier', ['id' => $tier_id]);
    }
}
