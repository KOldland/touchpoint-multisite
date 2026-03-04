<?php

namespace KHM\Tests\Membership;

use KHM\Membership\SignupEndpoint;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class SignupInitTest extends TestCase {
    private SignupEndpoint $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new SignupEndpoint();

        global $khm_test_options, $khm_test_transients, $khm_test_filters;
        $khm_test_options = [];
        $khm_test_transients = [];
        $khm_test_filters = [];

        add_filter( 'khm_membership_signup_init_use_mock_session', '__return_true' );
    }

    protected function tearDown(): void {
        global $khm_test_options, $khm_test_transients, $khm_test_filters;
        $khm_test_options = [];
        $khm_test_transients = [];
        $khm_test_filters = [];
        parent::tearDown();
    }

    public function test_signup_init_creates_temp_attribution_with_consent(): void {
        $request = new WP_REST_Request( 'POST', '/kh-membership/v1/signup-init' );
        $request->set_body( wp_json_encode([
            'schedule_id' => 'sch_123',
            'sponsor_id' => null,
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring_sale',
            'phase_at_click' => 'attention',
            'idempotency_key' => '123e4567-e89b-12d3-a456-426614174000',
            'consent' => true,
            'client_reference' => 'join',
            'plan_id' => null,
        ]) );

        $response = $this->endpoint->handle_signup_init( $request );
        $this->assertEquals( 201, $response->get_status() );

        $body = $response->get_data();
        $this->assertArrayHasKey( 'session_id', $body );
        $this->assertArrayHasKey( 'checkout_url', $body );

        $stored = get_option( 'khm_temp_attribution_' . $body['session_id'] );
        $this->assertIsArray( $stored );
        $this->assertEquals( 'sch_123', $stored['payload']['schedule_id'] );
        $this->assertEquals( 'newsletter', $stored['payload']['utm_source'] );
        $this->assertTrue( (bool) $stored['payload']['consent'] );
    }

    public function test_signup_init_dedupes_by_idempotency_key(): void {
        $payload = [
            'schedule_id' => 'sch_123',
            'sponsor_id' => null,
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring_sale',
            'phase_at_click' => 'attention',
            'idempotency_key' => '123e4567-e89b-12d3-a456-426614174001',
            'consent' => true,
            'client_reference' => 'join',
            'plan_id' => null,
        ];

        $requestA = new WP_REST_Request( 'POST', '/kh-membership/v1/signup-init' );
        $requestA->set_body( wp_json_encode( $payload ) );
        $responseA = $this->endpoint->handle_signup_init( $requestA );

        $requestB = new WP_REST_Request( 'POST', '/kh-membership/v1/signup-init' );
        $requestB->set_body( wp_json_encode( $payload ) );
        $responseB = $this->endpoint->handle_signup_init( $requestB );

        $this->assertEquals( 201, $responseA->get_status() );
        $this->assertEquals( 201, $responseB->get_status() );

        $bodyA = $responseA->get_data();
        $bodyB = $responseB->get_data();

        $this->assertEquals( $bodyA['session_id'], $bodyB['session_id'] );
        $this->assertEquals( $bodyA['checkout_url'], $bodyB['checkout_url'] );
    }

    public function test_signup_init_rejects_invalid_sponsor(): void {
        $request = new WP_REST_Request( 'POST', '/kh-membership/v1/signup-init' );
        $request->set_body( wp_json_encode([
            'schedule_id' => 'sch_123',
            'sponsor_id' => 'sp_invalid',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring_sale',
            'phase_at_click' => 'attention',
            'idempotency_key' => '123e4567-e89b-12d3-a456-426614174002',
            'consent' => true,
            'client_reference' => 'join',
            'plan_id' => null,
        ]) );

        $response = $this->endpoint->handle_signup_init( $request );
        $this->assertEquals( 422, $response->get_status() );

        $body = $response->get_data();
        $this->assertEquals( 'MBR_ERR_INVALID_SPONSOR', $body['error']['code'] );
    }

    public function test_signup_init_with_no_consent_stores_minimal_marker(): void {
        $request = new WP_REST_Request( 'POST', '/kh-membership/v1/signup-init' );
        $request->set_body( wp_json_encode([
            'schedule_id' => 'sch_123',
            'sponsor_id' => null,
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring_sale',
            'phase_at_click' => 'attention',
            'idempotency_key' => '123e4567-e89b-12d3-a456-426614174003',
            'consent' => false,
            'client_reference' => 'join',
            'plan_id' => null,
        ]) );

        $response = $this->endpoint->handle_signup_init( $request );
        $this->assertEquals( 201, $response->get_status() );

        $body = $response->get_data();
        $stored = get_option( 'khm_temp_attribution_' . $body['session_id'] );

        $this->assertIsArray( $stored );
        $this->assertFalse( (bool) $stored['payload']['consent'] );
        $this->assertNull( $stored['payload']['utm_source'] );
        $this->assertNull( $stored['payload']['utm_medium'] );
        $this->assertNull( $stored['payload']['utm_campaign'] );
    }
}
