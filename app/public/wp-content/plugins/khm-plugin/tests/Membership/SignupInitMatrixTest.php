<?php

namespace KHM\Tests\Membership;

use KHM\Membership\SignupEndpoint;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class SignupInitMatrixTest extends TestCase {
    private SignupEndpoint $endpoint;

    protected function setUp(): void {
        parent::setUp();
        $this->endpoint = new SignupEndpoint();

        $GLOBALS['khm_test_options'] = [];
        $GLOBALS['khm_test_transients'] = [];
        $GLOBALS['khm_test_filters'] = [];

        add_filter('khm_membership_signup_init_use_mock_session', '__return_true');
    }

    public function testSignupInitCreatesTempAttribution(): void {
        $request = new WP_REST_Request('POST', '/kh-membership/v1/signup-init');
        $request->set_body(wp_json_encode([
            'schedule_id' => 'sch_matrix_001',
            'sponsor_id' => null,
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'launch',
            'phase_at_click' => 'attention',
            'idempotency_key' => '123e4567-e89b-12d3-a456-426614174100',
            'consent' => true,
            'client_reference' => 'join',
            'plan_id' => null,
        ]));

        $response = $this->endpoint->handle_signup_init($request);
        $this->assertEquals(201, $response->get_status());

        $body = $response->get_data();
        $stored = get_option('khm_temp_attribution_' . $body['session_id']);

        $this->assertIsArray($stored);
        $this->assertEquals('sch_matrix_001', $stored['payload']['schedule_id']);
        $this->assertEquals('newsletter', $stored['payload']['utm_source']);
        $this->assertTrue((bool) $stored['payload']['consent']);
    }

    public function testSignupInitConsentFalseDoesNotPersistUtms(): void {
        $request = new WP_REST_Request('POST', '/kh-membership/v1/signup-init');
        $request->set_body(wp_json_encode([
            'schedule_id' => 'sch_matrix_002',
            'sponsor_id' => null,
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'launch',
            'phase_at_click' => 'attention',
            'idempotency_key' => '123e4567-e89b-12d3-a456-426614174101',
            'consent' => false,
            'client_reference' => 'join',
            'plan_id' => null,
        ]));

        $response = $this->endpoint->handle_signup_init($request);
        $this->assertEquals(201, $response->get_status());

        $body = $response->get_data();
        $stored = get_option('khm_temp_attribution_' . $body['session_id']);

        $this->assertIsArray($stored);
        $this->assertFalse((bool) $stored['payload']['consent']);
        $this->assertNull($stored['payload']['utm_source']);
        $this->assertNull($stored['payload']['utm_medium']);
        $this->assertNull($stored['payload']['utm_campaign']);
    }

    public function testSignupInitIdempotency(): void {
        $payload = [
            'schedule_id' => 'sch_matrix_003',
            'sponsor_id' => null,
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'launch',
            'phase_at_click' => 'attention',
            'idempotency_key' => '123e4567-e89b-12d3-a456-426614174102',
            'consent' => true,
            'client_reference' => 'join',
            'plan_id' => null,
        ];

        $requestA = new WP_REST_Request('POST', '/kh-membership/v1/signup-init');
        $requestA->set_body(wp_json_encode($payload));
        $responseA = $this->endpoint->handle_signup_init($requestA);

        $requestB = new WP_REST_Request('POST', '/kh-membership/v1/signup-init');
        $requestB->set_body(wp_json_encode($payload));
        $responseB = $this->endpoint->handle_signup_init($requestB);

        $this->assertEquals(201, $responseA->get_status());
        $this->assertEquals(201, $responseB->get_status());

        $dataA = $responseA->get_data();
        $dataB = $responseB->get_data();

        $this->assertSame($dataA['session_id'], $dataB['session_id']);
        $this->assertSame($dataA['checkout_url'], $dataB['checkout_url']);
    }
}
